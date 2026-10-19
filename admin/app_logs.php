<?php

/*
 * Admin > App Logs - what ITFlow itself does and runs into: cron runs, mail
 * going out and coming in, payment providers, the API, errors. Listed by
 * level - errors, warnings, info and debug, each counted for the filters
 * picked - searched, and filtered by category and date. Each entry opens a
 * quick look with all of it and what happened around then
 * (modals/app_log/app_log.php). What is shown can be exported
 * (export_app_logs) or cleared (clear_app_logs). The work is in
 * functions/app_log.php.
 */

// Default Column Sortby Filter
$sort = "app_log_id";
$order = "DESC";

require_once "includes/inc_all_admin.php";

// Tab title - the file is named for the table, the page for what it holds
$page_title = 'App Logs';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'app_log_id' => "app_log_id $order",
    'app_log_created_at' => "app_log_created_at $order, app_log_id $order",
    'app_log_type' => "FIELD(app_log_type, 'error', 'warning', 'info', 'debug') " . ($order === 'ASC' ? 'DESC' : 'ASC') . ", app_log_id DESC",
    'app_log_category' => "app_log_category IS NULL, app_log_category $order, app_log_id DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'app_log_id';
}

$views = appLogViews();
$view = isset($_GET['view']) && is_string($_GET['view']) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$category_filter = is_string($_GET['category'] ?? null) ? trim($_GET['category']) : '';
$filter_src = array_merge($_GET, ['dtf' => $dtf, 'dtt' => $dtt, 'view' => $view, 'category' => $category_filter]);

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS app_log_id, app_log_category, app_log_type, app_log_details, app_log_created_at
    FROM app_logs
    WHERE " . appLogFiltersSql($filter_src) . "
    ORDER BY {$sortable_columns[$sort]}
    LIMIT $record_from, $record_to");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Each level counted for the rest of the filters
$count_sql = [];
foreach ($views as $definition) {
    $count_sql[] = 'COALESCE(SUM(' . ($definition['where'] === '' ? '1' : $definition['where']) . '), 0)';
}
$counts = array_combine(array_keys($views), array_map('intval', mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . "
    FROM app_logs WHERE " . appLogFiltersSql(array_merge($filter_src, ['view' => '']))))));
// Nothing logs at debug as it comes - its view shows once something does
if (!$counts['debug'] && $view !== 'debug') {
    unset($views['debug']);
}

$categories = appLogCategories();
$any_filter = $q !== '' || $category_filter !== '' || $dtf !== '1970-01-01' || $dtt !== '2099-12-31';
$retention = appLogRetention();

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
// A view keeps the filters, and starts from its first page
$view_url = function ($key) {
    $query = $_GET;
    unset($query['page'], $query['open']);
    $query['view'] = $key;
    return '?' . http_build_query(array_filter($query, function ($value) {
        return $value !== '' && $value !== null && $value !== [];
    }));
};
// The page with just this category - from a category in the list
$category_url = function ($category) {
    $query = $_GET;
    unset($query['page'], $query['open']);
    $query['category'] = $category;
    return '?' . http_build_query($query);
};
$filters_url = function ($modal) use ($dtf, $dtt, $view, $category_filter) {
    return buildExportModalUrl($modal, ['q'], ['dtf' => $dtf, 'dtt' => $dtt, 'view' => $view, 'category' => $category_filter]);
};

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-clipboard-list me-2"></i>App Logs</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $filters_url('modals/app_log/app_log_export.php') ?>">
                    <i class="fas fa-fw fa-download"></i><span class="d-none d-lg-inline ms-2">Export</span>
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="<?= $filters_url('modals/app_log/app_log_clear.php') ?>" data-app-log-clear>
                        <i class="fas fa-fw fa-trash-alt me-2"></i>Clear
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="settings_logs.php">
                        <i class="fas fa-fw fa-cog text-secondary me-2"></i>Keeping and forwarding
                    </a>
                    <a class="dropdown-item" href="audit_logs.php">
                        <i class="fas fa-fw fa-history text-secondary me-2"></i>Audit Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($view !== '') { ?><input type="hidden" name="view" value="<?= escapeHtml($view) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-12">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search the app logs" title="The category or what it says">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="When" aria-label="When">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml(is_string($_GET['canned_date'] ?? null) ? $_GET['canned_date'] : '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-4 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Category">
                        <option value="">- Any category -</option>
                        <?php foreach ($categories as $category => $count) { ?>
                            <option value="<?= escapeHtml($category) ?>" <?= $category_filter === (string) $category ? 'selected' : '' ?><?= selectOptionData(['icon' => appLogCategoryIcon($category), 'description' => number_format($count) . ' entr' . ($count === 1 ? 'y' : 'ies')]) ?>><?= escapeHtml($category) ?></option>
                        <?php } ?>
                        <?php if ($category_filter !== '' && !isset($categories[$category_filter])) { ?>
                            <option value="<?= escapeHtml($category_filter) ?>" selected><?= escapeHtml($category_filter) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-3">
            <?php foreach ($views as $key => $definition) { ?>
                <a class="nav-link text-nowrap <?= $view === $key ? 'active' : '' ?>" href="<?= escapeHtml($view_url($key)) ?>" title="<?= escapeHtml($definition['title']) ?>" data-app-log-view="<?= $key === '' ? 'all' : $key ?>">
                    <i class="fas fa-fw <?= $definition['icon'] ?> me-1 <?= $definition['tone'] !== '' && $view !== $key ? 'text-' . $definition['tone'] : '' ?>"></i><?= $definition['label'] ?>
                    <span class="badge rounded-pill ms-1"><?= number_format($counts[$key]) ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('app_log_created_at', 'When') ?></th>
                <th><?= $sort_link('app_log_type', 'Level') ?></th>
                <th><?= $sort_link('app_log_category', 'Category') ?></th>
                <th>Details</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $app_log_id = intval($row['app_log_id']);
                $category = (string) $row['app_log_category'];
                $details = (string) $row['app_log_details'];
                $short = mb_strlen($details) > 240 ? rtrim(mb_substr($details, 0, 240)) . '…' : $details;
                ?>
                <tr data-app-log-id="<?= $app_log_id ?>">
                    <td class="ps-3 text-nowrap small" title="<?= escapeHtml($row['app_log_created_at']) ?>">
                        <div><?= escapeHtml(date('M j, Y g:i A', strtotime($row['app_log_created_at']))) ?></div>
                        <div class="text-body-secondary"><?= escapeHtml(timeAgo($row['app_log_created_at'])) ?></div>
                    </td>
                    <td><?= appLogLevelPill($row['app_log_type']) ?></td>
                    <td class="small text-nowrap">
                        <?php if ($category !== '') { ?>
                            <a class="text-reset" href="<?= escapeHtml($category_url($category)) ?>" title="Just <?= escapeHtml($category) ?>"><i class="<?= appLogCategoryIcon($category) ?> fa-fw text-secondary me-1"></i><?= escapeHtml($category) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="small" style="min-width: 18rem;">
                        <div class="text-break" data-app-log-details><?= escapeHtml($short) ?></div>
                    </td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-light ajax-modal" data-modal-size="lg" data-modal-url="modals/app_log/app_log.php?id=<?= $app_log_id ?>" title="All of it" aria-label="All of it"><i class="fas fa-fw fa-search-plus"></i></button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'app log entries',
        'icon' => 'fa-clipboard-list',
        'filtered' => $any_filter,
        'views' => ['view'],
        'view' => $view !== '' ? ['icon' => $views[$view]['icon'], 'title' => 'No ' . ($view === 'info' ? 'info entries' : strtolower($views[$view]['label'])) . ' yet', 'text' => escapeHtml($views[$view]['title']) . ' shows here.'] : null,
        'text' => 'What ITFlow itself does - cron runs, mail, payment providers, the API - and the errors it runs into show here. ' . ($retention['app'] ? 'They are kept for ' . appLogRetentionText($retention['app']) . '.' : 'They are kept until they are cleared.'),
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if (!empty($_GET['open'])) { ?>
    <script>
        // Opened from somewhere that names one entry - straight to its quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = 'modals/app_log/app_log.php?id=<?= intval(is_scalar($_GET['open']) ? $_GET['open'] : 0) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<?php
require_once "../includes/footer.php";
