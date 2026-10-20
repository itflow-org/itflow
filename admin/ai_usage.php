<?php

/*
 * Admin > AI Usage - every request made to an AI provider (ai_usage,
 * recorded by callAiApi() in functions/ai.php): when, the feature, who asked,
 * the client it was about, the model and provider, the tokens it took, how
 * long, how many passwords and keys were taken out of it first, and whether
 * it worked - why not, when it didn't. Totals for what is shown sit above.
 * Searched, and filtered by date, provider, model, feature and who; views for
 * the failed ones, the ones secrets were taken out of, and tests. Kept as long
 * as the audit log is (Admin > Settings > Logs).
 */

// Default Column Sortby Filter
$sort = "ai_usage_id";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$page_title = 'AI Usage';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'ai_usage_id' => "ai_usage_id $order",
    'ai_usage_feature' => "ai_usage_feature $order, ai_usage_id DESC",
    'user_name' => "user_name IS NULL, user_name $order, ai_usage_id DESC",
    'client_name' => "client_name IS NULL, client_name $order, ai_usage_id DESC",
    'ai_usage_model' => "ai_usage_model $order, ai_usage_id DESC",
    'tokens' => "(ai_usage_tokens_in + ai_usage_tokens_out) $order, ai_usage_id DESC",
    'ai_usage_ms' => "ai_usage_ms $order, ai_usage_id DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'ai_usage_id';
}

$features = aiFeatures();
$views = [
    '' => ['label' => 'All', 'icon' => 'fa-list', 'where' => "ai_usage_feature <> 'test'", 'title' => 'Every request but tests'],
    'failed' => ['label' => 'Failed', 'icon' => 'fa-times-circle', 'where' => "ai_usage_feature <> 'test' AND ai_usage_ok = 0", 'title' => 'The provider turned them away, or couldn\'t be reached'],
    'redacted' => ['label' => 'Secrets kept out', 'icon' => 'fa-user-secret', 'where' => "ai_usage_redacted > 0", 'title' => 'Passwords or keys were taken out before they were sent'],
    'test' => ['label' => 'Tests', 'icon' => 'fa-vial', 'where' => "ai_usage_feature = 'test'", 'title' => 'Try it, from a model\'s window'],
];
$view = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$provider_filter = intval($_GET['provider'] ?? 0);
$model_filter = intval($_GET['model'] ?? 0);
$user_filter = intval($_GET['user'] ?? 0);
$feature_filter = is_string($_GET['feature'] ?? null) && isset($features[$_GET['feature']]) ? $_GET['feature'] : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dtf) ? $dtf : '1970-01-01';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dtt) ? $dtt : '2099-12-31';

// Everything but the view - the views are counted with it
$where = ["DATE(ai_usage_created_at) BETWEEN '$from' AND '$to'"];
if ($q !== '') {
    $where[] = "(ai_usage_model LIKE '%$q%' OR ai_usage_provider_name LIKE '%$q%' OR ai_usage_feature LIKE '%$q%' OR ai_usage_use_case LIKE '%$q%'
        OR user_name LIKE '%$q%' OR client_name LIKE '%$q%' OR ai_usage_error LIKE '%$q%')";
}
if ($provider_filter) {
    $where[] = "ai_usage_provider_id = $provider_filter";
}
if ($model_filter) {
    $where[] = "ai_usage_model_id = $model_filter";
}
if ($user_filter) {
    $where[] = "ai_usage_user_id = $user_filter";
}
if ($feature_filter !== '') {
    $where[] = "ai_usage_feature = '" . escapeSql($feature_filter) . "'";
}
$where_sql = implode(' AND ', $where);
$from_sql = "FROM ai_usage LEFT JOIN users ON user_id = ai_usage_user_id LEFT JOIN clients ON client_id = ai_usage_client_id";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS ai_usage.*, user_name, client_name
    $from_sql
    WHERE $where_sql AND {$views[$view]['where']}
    ORDER BY {$sortable_columns[$sort]}
    LIMIT $record_from, $record_to");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Each view counted for the rest of the filters, and the totals for this one
$count_sql = [];
foreach ($views as $definition) {
    $count_sql[] = "COALESCE(SUM({$definition['where']}), 0)";
}
$counts = array_combine(array_keys($views), array_map('intval', mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " $from_sql WHERE $where_sql"))));
$totals = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS requests, COALESCE(SUM(ai_usage_tokens_in), 0) AS tokens_in, COALESCE(SUM(ai_usage_tokens_out), 0) AS tokens_out,
    COALESCE(SUM(ai_usage_ok = 0), 0) AS failed, COALESCE(SUM(ai_usage_redacted), 0) AS redacted, COALESCE(AVG(IF(ai_usage_ok = 1, ai_usage_ms, NULL)), 0) AS ms, COUNT(DISTINCT ai_usage_user_id) AS people
    $from_sql WHERE $where_sql AND {$views[$view]['where']}"));

$providers = mysqli_query($mysqli, "SELECT ai_provider_id, ai_provider_name, ai_provider_kind FROM ai_providers ORDER BY ai_provider_name");
$people = mysqli_query($mysqli, "SELECT DISTINCT user_id, user_name FROM ai_usage JOIN users ON user_id = ai_usage_user_id ORDER BY user_name");
$model_row = $model_filter ? aiModelRow($model_filter) : null;
$retention = appLogRetention();

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
// A view keeps the filters, and starts from its first page
$view_url = function ($key) {
    $query = $_GET;
    unset($query['page']);
    $query['view'] = $key;
    return '?' . http_build_query(array_filter($query, function ($value) {
        return $value !== '' && $value !== null && $value !== [];
    }));
};
// The page without one filter
$without_url = function ($key) {
    $query = $_GET;
    unset($query['page'], $query[$key]);
    return '?' . http_build_query($query);
};
$ms_text = function ($ms) {
    $ms = intval($ms);
    return $ms >= 1000 ? number_format($ms / 1000, 1) . 's' : $ms . 'ms';
};

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
    <li class="breadcrumb-item"><a href="ai_providers.php">AI Providers</a></li>
    <li class="breadcrumb-item active">AI Usage</li>
</ol>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-chart-bar me-2"></i>AI Usage</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light me-1" href="ai_models.php"><i class="fas fa-fw fa-brain"></i><span class="d-none d-lg-inline ms-2">Models</span></a>
            <a class="btn btn-outline-light" href="settings_logs.php" title="Kept as long as the audit log - <?= escapeHtml(appLogRetentionText($retention['audit'])) ?>"><i class="fas fa-fw fa-cog"></i><span class="d-none d-lg-inline ms-2">Keeping</span></a>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php foreach (['view' => $view, 'model' => $model_filter ?: ''] as $keep => $value) { ?>
                <?php if ($value !== '') { ?><input type="hidden" name="<?= $keep ?>" value="<?= escapeHtml($value) ?>"><?php } ?>
            <?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-12">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search AI usage" title="Model, provider, feature, who, client or what went wrong">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="When" aria-label="When">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml(is_string($_GET['canned_date'] ?? null) ? $_GET['canned_date'] : '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="provider" onchange="this.form.submit()" aria-label="Provider">
                        <option value="">- Any provider -</option>
                        <?php while ($provider = mysqli_fetch_assoc($providers)) { ?>
                            <option value="<?= intval($provider['ai_provider_id']) ?>" <?= intval($provider['ai_provider_id']) === $provider_filter ? 'selected' : '' ?><?= selectOptionData(['icon' => aiProviderIcon($provider['ai_provider_kind'])]) ?>><?= escapeHtml($provider['ai_provider_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="feature" onchange="this.form.submit()" aria-label="Feature">
                        <option value="">- Any feature -</option>
                        <?php foreach ($features as $key => $label) { ?>
                            <option value="<?= $key ?>" <?= $key === $feature_filter ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="user" onchange="this.form.submit()" aria-label="Who">
                        <option value="">- Anyone -</option>
                        <?php while ($person = mysqli_fetch_assoc($people)) { ?>
                            <option value="<?= intval($person['user_id']) ?>" <?= intval($person['user_id']) === $user_filter ? 'selected' : '' ?>><?= escapeHtml($person['user_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </form>
        <?php if ($model_filter) { ?>
            <div class="mt-2 small" data-usage-model-filter>
                Just <strong class="font-monospace"><?= escapeHtml($model_row['ai_model_name'] ?? "model #$model_filter") ?></strong>
                <a class="ms-1" href="<?= escapeHtml($without_url('model')) ?>" title="Every model"><i class="fas fa-times-circle"></i></a>
            </div>
        <?php } ?>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-3">
            <?php foreach ($views as $key => $definition) { ?>
                <a class="nav-link text-nowrap <?= $view === $key ? 'active' : '' ?>" href="<?= escapeHtml($view_url($key)) ?>" title="<?= escapeHtml($definition['title']) ?>" data-usage-view="<?= $key === '' ? 'all' : $key ?>">
                    <i class="fas fa-fw <?= $definition['icon'] ?> me-1"></i><?= $definition['label'] ?>
                    <span class="badge rounded-pill ms-1"><?= number_format($counts[$key]) ?></span>
                </a>
            <?php } ?>
        </nav>
        <?php if (intval($totals['requests'])) { ?>
            <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-2 mt-1 small" data-usage-totals>
                <div class="col"><div class="border rounded px-3 py-2 h-100"><div class="text-body-secondary">Requests</div><div class="fs-5 fw-semibold" data-usage-total="requests"><?= number_format($totals['requests']) ?></div></div></div>
                <div class="col"><div class="border rounded px-3 py-2 h-100" title="<?= number_format($totals['tokens_in']) ?> tokens sent"><div class="text-body-secondary">Tokens in</div><div class="fs-5 fw-semibold" data-usage-total="tokens_in"><?= aiTokensText($totals['tokens_in']) ?></div></div></div>
                <div class="col"><div class="border rounded px-3 py-2 h-100" title="<?= number_format($totals['tokens_out']) ?> tokens received"><div class="text-body-secondary">Tokens out</div><div class="fs-5 fw-semibold" data-usage-total="tokens_out"><?= aiTokensText($totals['tokens_out']) ?></div></div></div>
                <div class="col"><div class="border rounded px-3 py-2 h-100"><div class="text-body-secondary">Failed</div><div class="fs-5 fw-semibold <?= intval($totals['failed']) ? 'text-danger-emphasis' : '' ?>" data-usage-total="failed"><?= number_format($totals['failed']) ?></div></div></div>
                <div class="col"><div class="border rounded px-3 py-2 h-100" title="Passwords and keys taken out before they were sent"><div class="text-body-secondary">Secrets kept out</div><div class="fs-5 fw-semibold" data-usage-total="redacted"><?= number_format($totals['redacted']) ?></div></div></div>
                <div class="col"><div class="border rounded px-3 py-2 h-100" title="How long an answer took, on average"><div class="text-body-secondary">Answer time</div><div class="fs-5 fw-semibold"><?= $ms_text($totals['ms']) ?></div></div></div>
            </div>
        <?php } ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('ai_usage_id', 'When') ?></th>
                <th><?= $sort_link('ai_usage_feature', 'Feature') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('user_name', 'Who') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('client_name', 'Client') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('ai_usage_model', 'Model') ?></th>
                <th class="d-none d-sm-table-cell text-end"><?= $sort_link('tokens', 'Tokens') ?></th>
                <th class="d-none d-lg-table-cell text-end"><?= $sort_link('ai_usage_ms', 'Time') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $ok = intval($row['ai_usage_ok']) === 1;
                $redacted = intval($row['ai_usage_redacted']);
                $feature = (string) $row['ai_usage_feature'];
                ?>
                <tr data-ai-usage-id="<?= intval($row['ai_usage_id']) ?>">
                    <td class="ps-3 text-nowrap small" title="<?= escapeHtml($row['ai_usage_created_at']) ?>">
                        <div><?= escapeHtml(date('M j, Y g:i A', strtotime($row['ai_usage_created_at']))) ?></div>
                        <div class="text-body-secondary"><?= escapeHtml(timeAgo($row['ai_usage_created_at'])) ?></div>
                    </td>
                    <td class="small">
                        <div class="fw-semibold"><?= escapeHtml($features[$feature] ?? ucfirst(str_replace('_', ' ', $feature))) ?></div>
                        <?php if (!$ok) { ?>
                            <div class="text-danger-emphasis text-break" data-usage-failed><i class="fas fa-fw fa-times-circle me-1"></i><?= escapeHtml($row['ai_usage_error'] ?: 'Failed') ?></div>
                        <?php } ?>
                        <?php if ($redacted) { ?>
                            <div class="text-body-secondary" data-usage-redacted="<?= $redacted ?>" title="Taken out before it was sent, and put back in the answer"><i class="fas fa-fw fa-user-secret me-1"></i><?= $redacted ?> secret<?= $redacted === 1 ? '' : 's' ?> kept out</div>
                        <?php } ?>
                        <div class="d-md-none text-body-secondary"><?= escapeHtml(trim(($row['user_name'] ?? '') . ' · ' . $row['ai_usage_model'], ' ·')) ?></div>
                    </td>
                    <td class="d-none d-md-table-cell small"><?= $row['user_name'] !== null ? escapeHtml($row['user_name']) : '<span class="text-body-secondary">' . (intval($row['ai_usage_user_id']) ? 'Removed' : 'ITFlow') . '</span>' ?></td>
                    <td class="d-none d-lg-table-cell small">
                        <?php if ($row['client_name'] !== null) { ?>
                            <a class="text-reset" href="/agent/client_overview.php?client_id=<?= intval($row['ai_usage_client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <div class="font-monospace"><?= escapeHtml($row['ai_usage_model']) ?></div>
                        <div class="text-body-secondary"><?= escapeHtml($row['ai_usage_provider_name']) ?></div>
                    </td>
                    <td class="d-none d-sm-table-cell small text-end text-nowrap" title="<?= number_format($row['ai_usage_tokens_in']) ?> in, <?= number_format($row['ai_usage_tokens_out']) ?> out">
                        <?php if ($ok) { ?>
                            <div><?= aiTokensText($row['ai_usage_tokens_in']) ?> in</div>
                            <div class="text-body-secondary"><?= aiTokensText($row['ai_usage_tokens_out']) ?> out</div>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-end text-nowrap"><?= $ms_text($row['ai_usage_ms']) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'AI requests',
        'icon' => 'fa-chart-bar',
        'views' => ['view'],
        'view' => $view !== '' ? ['icon' => $views[$view]['icon'], 'title' => 'None ' . ($view === 'test' ? 'tried yet' : 'yet'), 'text' => escapeHtml($views[$view]['title']) . ' - they show here.'] : null,
        'text' => 'Each time an agent rewords a reply, summarizes a ticket or drafts a document, it shows here - who, for what, the model and the tokens it took. ' . ($retention['audit'] ? 'Kept for ' . escapeHtml(appLogRetentionText($retention['audit'])) . ', as the audit log is.' : 'Kept as long as the audit log is.'),
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
