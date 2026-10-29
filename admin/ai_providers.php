<?php

/*
 * Admin > AI Providers - the AI services ITFlow asks (functions/ai.php): a
 * ready-made kind each (OpenAI, Claude, Gemini, Azure, a model on your own
 * network...), its address, whether its key is saved (never shown), whether
 * passwords and keys are taken out of what is sent to it, the models on it and
 * what each is for, and its use in the last 30 days. Above, which model each
 * use case lands on. Test asks it for its models; Add and Edit are one window
 * (modals/ai/ai_provider_edit.php); the rest are admin/post/ai_provider.php.
 */

// Default Column Sortby Filter
$sort = "ai_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'ai_provider_name' => "ai_provider_name $order",
    'ai_provider_kind' => "ai_provider_kind $order, ai_provider_name ASC",
    'model_count' => "model_count $order, ai_provider_name ASC",
    'requests' => "requests $order, ai_provider_name ASC",
    'last_used' => "last_used IS NULL, last_used $order, ai_provider_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'ai_provider_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS ai_provider_id, ai_provider_name, ai_provider_kind, ai_provider_api_url, ai_provider_redact,
        ai_provider_api_key IS NOT NULL AND ai_provider_api_key <> '' AS has_key,
        (SELECT COUNT(*) FROM ai_models WHERE ai_model_ai_provider_id = ai_provider_id) AS model_count,
        COALESCE(used.requests, 0) AS requests, COALESCE(used.failed, 0) AS failed, COALESCE(used.tokens_in, 0) AS tokens_in, COALESCE(used.tokens_out, 0) AS tokens_out, used.last_used
    FROM ai_providers
    LEFT JOIN (
        SELECT ai_usage_provider_id,
            SUM(ai_usage_created_at >= NOW() - INTERVAL 30 DAY) AS requests,
            SUM(ai_usage_created_at >= NOW() - INTERVAL 30 DAY AND ai_usage_ok = 0) AS failed,
            SUM(IF(ai_usage_created_at >= NOW() - INTERVAL 30 DAY, ai_usage_tokens_in, 0)) AS tokens_in,
            SUM(IF(ai_usage_created_at >= NOW() - INTERVAL 30 DAY, ai_usage_tokens_out, 0)) AS tokens_out,
            MAX(ai_usage_created_at) AS last_used
        FROM ai_usage WHERE ai_usage_feature <> 'test' GROUP BY ai_usage_provider_id
    ) used ON used.ai_usage_provider_id = ai_provider_id
    WHERE (ai_provider_name LIKE '%$q%' OR ai_provider_kind LIKE '%$q%' OR ai_provider_api_url LIKE '%$q%'
        OR EXISTS (SELECT 1 FROM ai_models WHERE ai_model_ai_provider_id = ai_provider_id AND ai_model_name LIKE '%$q%'))
    ORDER BY {$sortable_columns[$sort]}, ai_provider_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Each provider's models, with what they are for - and those nothing uses
$models_by_provider = [];
$model_sql = mysqli_query($mysqli, "SELECT ai_model_id, ai_model_name, ai_model_use_case, ai_model_ai_provider_id FROM ai_models ORDER BY FIELD(ai_model_use_case, 'General', 'Tickets', 'Documentation', 'Billing'), ai_model_name");
while ($model = mysqli_fetch_assoc($model_sql)) {
    $models_by_provider[intval($model['ai_model_ai_provider_id'])][] = $model;
}
$unused = aiUseCaseMap()['unused'];
$any_provider = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM ai_providers"))[0]) > 0;

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$add_url = 'modals/ai/ai_provider_edit.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-robot me-2"></i>AI Providers</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light me-1" href="ai_models.php" title="The models on them, and what each is for"><i class="fas fa-fw fa-brain"></i><span class="d-none d-lg-inline ms-2">Models</span></a>
            <a class="btn btn-outline-light me-1" href="ai_usage.php" title="Each request - who, for what, and the tokens it took"><i class="fas fa-fw fa-chart-bar"></i><span class="d-none d-lg-inline ms-2">Usage</span></a>
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">Add Provider</span></button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search AI providers..." title="Name, kind, address or a model on it">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <?php if ($any_provider) { ?>
            <div class="mt-3"><?= aiUseCaseStripHtml() ?></div>
        <?php } ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('ai_provider_name', 'Provider') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('ai_provider_kind', 'Kind') ?></th>
                <th class="d-none d-md-table-cell">Key</th>
                <th class="d-none d-md-table-cell"><?= $sort_link('model_count', 'Models') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('requests', 'Last 30 days') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $provider_id = intval($row['ai_provider_id']);
                $name = (string) $row['ai_provider_name'];
                $kind = aiProviderKind($row['ai_provider_kind']);
                $host = (string) parse_url((string) $row['ai_provider_api_url'], PHP_URL_HOST);
                $port = parse_url((string) $row['ai_provider_api_url'], PHP_URL_PORT);
                $edit_url = "modals/ai/ai_provider_edit.php?id=$provider_id";
                $models = $models_by_provider[$provider_id] ?? [];
                $requests = intval($row['requests']);
                $failed = intval($row['failed']);
                $stopping = aiUseCasesStopping(['provider' => $provider_id]);
                $model_names = array_column($models, 'ai_model_name');
                $delete_text = ($models ? (count($models) === 1 ? "Its model, {$model_names[0]}, goes with it." : 'Its ' . count($models) . ' models go with it - ' . implode(', ', $model_names) . '.') : 'It has no models.')
                    . ($stopping ? ' AI stops for ' . implode(' and ', $stopping) . ' until another model is set up.' : '');
                ?>
                <tr data-ai-provider-id="<?= $provider_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-sm-inline-flex itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="<?= aiProviderIcon($row['ai_provider_kind']) ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><?= escapeHtml($name) ?></a>
                                <?php if ($kind['local']) { ?>
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle ms-1" title="It runs on your own network - what is sent to it doesn't leave it">Your network</span>
                                <?php } ?>
                                <?php if (!intval($row['ai_provider_redact'])) { ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" data-provider-redact="off" title="Passwords and keys in what is sent go to it as they are">Secrets sent</span>
                                <?php } ?>
                                <div class="small text-body-secondary text-truncate" style="max-width: 22rem;" title="<?= escapeHtml($row['ai_provider_api_url']) ?>"><?= escapeHtml($host . ($port ? ":$port" : '')) ?></div>
                                <!-- On smaller screens its kind and models come along under the name -->
                                <div class="d-lg-none small text-body-secondary"><?= escapeHtml($kind['name']) ?><span class="d-md-none"><?= $models ? ' · ' . escapeHtml(implode(', ', $model_names)) : '' ?></span></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell small"><?= escapeHtml($kind['name']) ?></td>
                    <td class="d-none d-md-table-cell small text-nowrap" data-provider-key="<?= intval($row['has_key']) ? 'saved' : 'none' ?>">
                        <?php if (intval($row['has_key'])) { ?>
                            <i class="fas fa-fw fa-lock text-secondary me-1"></i>Saved
                        <?php } elseif ($kind['key']) { ?>
                            <span class="text-danger-emphasis"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Needs one - add it in Edit</span>
                        <?php } else { ?>
                            <span class="text-body-secondary">None</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?php foreach ($models as $model) {
                            $model_id = intval($model['ai_model_id']);
                            ?>
                            <div class="text-nowrap" data-provider-model="<?= escapeHtml($model['ai_model_name']) ?>">
                                <a class="text-reset ajax-modal" href="#" data-modal-url="modals/ai/ai_model_edit.php?id=<?= $model_id ?>"><?= escapeHtml($model['ai_model_name']) ?></a>
                                <span class="text-body-secondary">· <?= escapeHtml($model['ai_model_use_case']) ?></span>
                                <?php if (array_key_exists($model_id, $unused)) { ?>
                                    <span class="badge text-bg-light border fw-normal ms-1" title="<?= escapeHtml($unused[$model_id] ? $unused[$model_id]['ai_model_name'] . ' is for ' . $model['ai_model_use_case'] . ' already, and is the one used' : 'Not one of the use cases') ?>">Not used</span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <?php if (!$models) { ?>
                            <a class="ajax-modal" href="#" data-modal-url="modals/ai/ai_model_edit.php?provider=<?= $provider_id ?>"><i class="fas fa-fw fa-plus me-1"></i>Add a model</a>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap" data-provider-requests="<?= $requests ?>">
                        <?php if ($requests) { ?>
                            <div><a class="text-reset" href="ai_usage.php?provider=<?= $provider_id ?>"><?= number_format($requests) ?> request<?= $requests === 1 ? '' : 's' ?></a></div>
                            <div class="text-body-secondary" title="Tokens sent and received"><?= aiTokensText($row['tokens_in']) ?> in · <?= aiTokensText($row['tokens_out']) ?> out</div>
                            <?php if ($failed) { ?>
                                <div><a class="text-danger-emphasis" href="ai_usage.php?provider=<?= $provider_id ?>&amp;view=failed"><?= $failed ?> failed</a></div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="text-body-secondary"><?= $row['last_used'] ? 'None' : 'Not used yet' ?></div>
                        <?php } ?>
                        <?php if ($row['last_used']) { ?>
                            <div class="text-body-secondary" title="<?= escapeHtml($row['last_used']) ?>">Last <?= escapeHtml(timeAgo($row['last_used'])) ?></div>
                        <?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <a class="dropdown-item" href="post.php?test_ai_provider=<?= $provider_id ?>&csrf_token=<?= $csrf ?>" title="Whether it answers, with its key, and has the models set up on it">
                                    <i class="fas fa-fw fa-plug text-secondary me-2"></i>Test
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ai/ai_model_edit.php?provider=<?= $provider_id ?>">
                                    <i class="fas fa-fw fa-plus text-secondary me-2"></i>Add a model
                                </a>
                                <a class="dropdown-item" href="ai_usage.php?provider=<?= $provider_id ?>">
                                    <i class="fas fa-fw fa-chart-bar text-secondary me-2"></i>Usage
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ai_provider=<?= $provider_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= escapeHtml($name) ?>?" data-confirm-button="Delete" data-confirm-text="<?= escapeHtml($delete_text) ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'AI providers',
        'icon' => 'fa-robot',
        'text' => 'Connect OpenAI, Claude, Gemini, Azure OpenAI, or a model on your own network like Ollama - then agents can reword replies, summarize tickets and draft documents, with passwords and keys taken out of what is sent.',
        'actions' => [['label' => 'Add Provider', 'modal' => $add_url]],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
