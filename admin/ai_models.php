<?php

/*
 * Admin > AI Models - the models on the AI providers, each for a use case
 * (functions/ai.php aiUseCases()) with General the fallback. Above, which
 * model each use case lands on; in the list, a second model for a use case is
 * marked as not used, the provider each is on, its instructions and
 * temperature, and its use in the last 30 days. Add and Edit are one window
 * (modals/ai/ai_model_edit.php), with Try it; the rest are
 * admin/post/ai_models.php.
 */

// Default Column Sortby Filter
$sort = "ai_model_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$page_title = 'AI Models';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'ai_model_name' => "ai_model_name $order",
    'ai_provider_name' => "ai_provider_name $order, ai_model_name ASC",
    'ai_model_use_case' => "FIELD(ai_model_use_case, 'General', 'Tickets', 'Documentation', 'Billing') " . ($order === 'ASC' ? 'ASC' : 'DESC') . ", ai_model_id ASC",
    'requests' => "requests $order, ai_model_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'ai_model_name';
}

$provider_filter = intval($_GET['provider'] ?? 0);

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS ai_model_id, ai_model_name, ai_model_prompt, ai_model_use_case, ai_model_temperature,
        ai_provider_id, ai_provider_name, ai_provider_kind,
        COALESCE(used.requests, 0) AS requests, COALESCE(used.failed, 0) AS failed, COALESCE(used.tokens_in, 0) AS tokens_in, COALESCE(used.tokens_out, 0) AS tokens_out, used.last_used
    FROM ai_models
    JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id
    LEFT JOIN (
        SELECT ai_usage_model_id,
            SUM(ai_usage_created_at >= NOW() - INTERVAL 30 DAY) AS requests,
            SUM(ai_usage_created_at >= NOW() - INTERVAL 30 DAY AND ai_usage_ok = 0) AS failed,
            SUM(IF(ai_usage_created_at >= NOW() - INTERVAL 30 DAY, ai_usage_tokens_in, 0)) AS tokens_in,
            SUM(IF(ai_usage_created_at >= NOW() - INTERVAL 30 DAY, ai_usage_tokens_out, 0)) AS tokens_out,
            MAX(ai_usage_created_at) AS last_used
        FROM ai_usage WHERE ai_usage_feature <> 'test' GROUP BY ai_usage_model_id
    ) used ON used.ai_usage_model_id = ai_model_id
    WHERE (ai_model_name LIKE '%$q%' OR ai_provider_name LIKE '%$q%' OR ai_model_use_case LIKE '%$q%' OR ai_model_prompt LIKE '%$q%')
        " . ($provider_filter ? "AND ai_provider_id = $provider_filter" : '') . "
    ORDER BY {$sortable_columns[$sort]}, ai_model_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$unused = aiUseCaseMap()['unused'];
$providers = mysqli_query($mysqli, "SELECT ai_provider_id, ai_provider_name, ai_provider_kind FROM ai_providers ORDER BY ai_provider_name");
$any_provider = mysqli_num_rows($providers) > 0;

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$add_url = 'modals/ai/ai_model_edit.php' . ($provider_filter ? "?provider=$provider_filter" : '');
$csrf = $_SESSION['csrf_token'];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
    <li class="breadcrumb-item"><a href="ai_providers.php">AI Providers</a></li>
    <li class="breadcrumb-item active">AI Models</li>
</ol>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-brain me-2"></i>AI Models</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light me-1" href="ai_usage.php" title="Each request - who, for what, and the tokens it took"><i class="fas fa-fw fa-chart-bar"></i><span class="d-none d-lg-inline ms-2">Usage</span></a>
            <?php if ($any_provider) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">Add Model</span></button>
            <?php } ?>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search AI models..." title="Name, provider, use case or instructions">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="provider" onchange="this.form.submit()" aria-label="Provider">
                        <option value="">- Any provider -</option>
                        <?php while ($provider = mysqli_fetch_assoc($providers)) { ?>
                            <option value="<?= intval($provider['ai_provider_id']) ?>" <?= intval($provider['ai_provider_id']) === $provider_filter ? 'selected' : '' ?><?= selectOptionData(['icon' => aiProviderIcon($provider['ai_provider_kind'])]) ?>><?= escapeHtml($provider['ai_provider_name']) ?></option>
                        <?php } ?>
                    </select>
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
                <th class="ps-3"><?= $sort_link('ai_model_name', 'Model') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('ai_provider_name', 'Provider') ?></th>
                <th><?= $sort_link('ai_model_use_case', 'For') ?></th>
                <th class="d-none d-lg-table-cell">Instructions</th>
                <th class="d-none d-md-table-cell"><?= $sort_link('requests', 'Last 30 days') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $model_id = intval($row['ai_model_id']);
                $name = (string) $row['ai_model_name'];
                $use_case = (string) $row['ai_model_use_case'];
                $edit_url = "modals/ai/ai_model_edit.php?id=$model_id";
                $prompt = trim((string) $row['ai_model_prompt']);
                $requests = intval($row['requests']);
                $failed = intval($row['failed']);
                $is_unused = array_key_exists($model_id, $unused);
                $stopping = $is_unused ? [] : aiUseCasesStopping(['model' => $model_id]);
                $then = $is_unused ? null : aiUseCaseMap(['model' => $model_id])['map'][$use_case] ?? null;
                if ($is_unused) {
                    $delete_text = 'Nothing uses it - ' . ($unused[$model_id] ? $unused[$model_id]['ai_model_name'] . " is the one used for $use_case." : "$use_case isn't a use case.");
                } elseif ($stopping) {
                    $delete_text = 'AI stops for ' . implode(' and ', $stopping) . ' until another model is set up.';
                } else {
                    $delete_text = "$use_case uses " . ($then && $then['model'] ? $then['model']['ai_model_name'] . ($then['own'] ? '' : ' (General)') : 'nothing') . ' instead.';
                }
                ?>
                <tr data-ai-model-id="<?= $model_id ?>" class="<?= $is_unused ? 'text-body-secondary' : '' ?>">
                    <td class="ps-3">
                        <a class="fw-semibold text-body font-monospace ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><?= escapeHtml($name) ?></a>
                        <?php if ($is_unused) { ?>
                            <span class="badge text-bg-light border fw-normal ms-1" data-model-unused title="<?= escapeHtml($unused[$model_id] ? $unused[$model_id]['ai_model_name'] . " is for $use_case already, and is the one used" : "$use_case isn't a use case") ?>">Not used</span>
                        <?php } ?>
                        <div class="d-md-none small text-body-secondary"><?= escapeHtml($row['ai_provider_name']) ?></div>
                        <?php if ($row['ai_model_temperature'] !== null) { ?>
                            <div class="small text-body-secondary" title="Temperature">Temperature <?= escapeHtml(aiTemperatureText($row['ai_model_temperature'])) ?></div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <a class="text-reset" href="?provider=<?= intval($row['ai_provider_id']) ?>"><i class="<?= aiProviderIcon($row['ai_provider_kind']) ?> fa-fw text-secondary me-1"></i><?= escapeHtml($row['ai_provider_name']) ?></a>
                    </td>
                    <td class="small">
                        <div class="fw-semibold"><?= escapeHtml($use_case) ?></div>
                        <div class="text-body-secondary d-none d-sm-block"><?= escapeHtml(aiUseCases()[$use_case] ?? '') ?></div>
                    </td>
                    <td class="d-none d-lg-table-cell small" style="max-width: 22rem;">
                        <?php if ($prompt !== '') { ?>
                            <div class="text-truncate" title="<?= escapeHtml($prompt) ?>"><?= escapeHtml($prompt) ?></div>
                        <?php } else { ?>
                            <span class="text-body-secondary">None</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap" data-model-requests="<?= $requests ?>">
                        <?php if ($requests) { ?>
                            <div><a class="text-reset" href="ai_usage.php?model=<?= $model_id ?>"><?= number_format($requests) ?> request<?= $requests === 1 ? '' : 's' ?></a></div>
                            <div class="text-body-secondary" title="Tokens sent and received"><?= aiTokensText($row['tokens_in']) ?> in · <?= aiTokensText($row['tokens_out']) ?> out</div>
                            <?php if ($failed) { ?>
                                <div><a class="text-danger-emphasis" href="ai_usage.php?model=<?= $model_id ?>&amp;view=failed"><?= $failed ?> failed</a></div>
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
                                <a class="dropdown-item" href="ai_usage.php?model=<?= $model_id ?>">
                                    <i class="fas fa-fw fa-chart-bar text-secondary me-2"></i>Usage
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ai_model=<?= $model_id ?>&csrf_token=<?= $csrf ?>"
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
        'thing' => 'AI models',
        'icon' => 'fa-brain',
        'text' => $any_provider ? 'The model on each provider that ITFlow uses - one for everything (General), and others for tickets or documents if you like.' : 'Add an AI provider first - then the models on it ITFlow uses.',
        'actions' => $any_provider ? [['label' => 'Add Model', 'modal' => $add_url]] : [['label' => 'AI Providers', 'href' => 'ai_providers.php', 'icon' => 'fa-robot']],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
