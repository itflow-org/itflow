<?php

/*
 * Your account > Activity - everything you did, from the audit log: your
 * changes, your sign ins (and wrong MFA codes), the passwords you looked at
 * and what you exported, with when, the client and from where. Searched,
 * filtered by date, in views, a page at a time, each entry linking to its
 * record while it still exists. Yours only.
 */

// Default Column Sortby Filter
$sort = "log_id";
$order = "DESC";

require_once "includes/inc_all_user.php";

$page_title = 'Activity';

$sortable_columns = [
    'log_id' => "log_id $order",
    'log_created_at' => "log_created_at $order, log_id $order",
    'client_name' => "client_name IS NULL, client_name $order, log_id DESC",
    'log_type' => "log_type $order, log_action $order, log_id DESC",
    'log_action' => "log_action $order, log_id DESC",
    'log_ip' => "log_ip IS NULL, log_ip $order, log_id DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'log_id';
}

$views = auditLogViews();
unset($views['security']);
$views['signins']['title'] = 'Signing in and out, and wrong MFA codes';
$views['data']['title'] = 'Passwords you looked at, and what you exported, downloaded and shared';

$view = isset($_GET['view']) && is_string($_GET['view']) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$scope = "log_user_id = " . intval($session_user_id);
$filter_src = array_merge($_GET, ['user' => '', 'dtf' => $dtf, 'dtt' => $dtt, 'view' => $view]);
$joins = auditLogJoinsSql();

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS log_id, log_type, log_action, log_description, log_ip, log_user_agent, log_created_at, log_client_id, log_user_id, log_entity_id,
        user_name, client_name
    FROM logs $joins
    WHERE $scope AND " . auditLogFiltersSql($filter_src) . "
    ORDER BY {$sortable_columns[$sort]}
    LIMIT $record_from, $record_to");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$rows = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[] = $row;
}
$records = auditLogRecords($rows);
$kinds = auditLogKinds();

// Each view counted for the search and dates
$count_sql = [];
foreach ($views as $definition) {
    $count_sql[] = 'COALESCE(SUM(' . ($definition['where'] === '' ? '1' : $definition['where']) . '), 0)';
}
$counts = array_combine(array_keys($views), array_map('intval', mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . "
    FROM logs $joins WHERE $scope AND " . auditLogFiltersSql(array_merge($filter_src, ['view' => '']))))));

$any_filter = $q !== '' || $dtf !== '1970-01-01' || $dtt !== '2099-12-31';

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$view_url = function ($key) {
    $query = $_GET;
    unset($query['page']);
    $query['view'] = $key;
    return '?' . http_build_query(array_filter($query, function ($value) {
        return $value !== '' && $value !== null && $value !== [];
    }));
};
$view_empty = [
    'changes' => 'No changes yet',
    'deletions' => 'Nothing deleted yet',
    'signins' => 'No sign ins yet',
    'data' => 'Nothing looked at or taken out yet',
];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-history me-2"></i>Your activity</h3>
        <?php if ($session_is_admin) { ?>
            <div class="card-tools">
                <a class="btn btn-outline-light" href="/admin/audit_logs.php?user=<?= intval($session_user_id) ?>" title="The same in Admin > Audit Logs, with each entry's quick look and export">
                    <i class="fas fa-fw fa-user-shield"></i><span class="d-none d-lg-inline ms-2">Admin &gt; Audit Logs</span>
                </a>
            </div>
        <?php } ?>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($view !== '') { ?><input type="hidden" name="view" value="<?= escapeHtml($view) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-6 col-md-7">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search what you did" title="What you did, the client, the IP address or the browser">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-5">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="When" aria-label="When">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml(is_string($_GET['canned_date'] ?? null) ? $_GET['canned_date'] : '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs flex-nowrap overflow-auto mt-3">
            <?php foreach ($views as $key => $definition) { ?>
                <a class="nav-link text-nowrap <?= $view === $key ? 'active' : '' ?>" href="<?= escapeHtml($view_url($key)) ?>" title="<?= escapeHtml($definition['title']) ?>" data-audit-view="<?= $key === '' ? 'all' : $key ?>">
                    <i class="fas fa-fw <?= $definition['icon'] ?> me-1"></i><?= $definition['label'] ?>
                    <span class="badge rounded-pill ms-1"><?= number_format($counts[$key]) ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('log_created_at', 'When') ?></th>
                <th><?= $sort_link('log_type', 'What') ?></th>
                <th>Description</th>
                <th class="d-none d-md-table-cell"><?= $sort_link('client_name', 'Client') ?></th>
                <th class="d-none d-xl-table-cell pe-3"><?= $sort_link('log_ip', 'From') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $type = (string) $row['log_type'];
                $link = auditLogRecordLink($row, $records);
                $gone = $link === '' && intval($row['log_entity_id']) > 0 && isset($kinds[$type]['table']);
                $device = auditLogDevice($row['log_user_agent']);
                $from = implode(' · ', array_filter([(string) $row['log_ip'], $device]));
                ?>
                <tr data-log-id="<?= intval($row['log_id']) ?>">
                    <td class="ps-3 text-nowrap small" title="<?= escapeHtml($row['log_created_at']) ?>">
                        <div><?= escapeHtml(date('M j, Y g:i A', strtotime($row['log_created_at']))) ?></div>
                        <div class="text-body-secondary"><?= escapeHtml(timeAgo($row['log_created_at'])) ?></div>
                    </td>
                    <td class="text-nowrap">
                        <div class="small mb-1"><i class="fas fa-fw <?= auditLogTypeIcon($type) ?> text-secondary me-1"></i><?= escapeHtml($type) ?></div>
                        <?= auditLogActionPill($row['log_action']) ?>
                    </td>
                    <td class="small" style="min-width: 16rem;">
                        <div class="text-break"><?= escapeHtml(auditLogDescription($row)) ?></div>
                        <?php if ($link !== '') { ?>
                            <div class="mt-1" data-audit-record><i class="fas fa-fw fa-external-link-alt text-secondary me-1"></i><?= $link ?></div>
                        <?php } elseif ($gone) { ?>
                            <div class="mt-1 text-body-secondary" data-audit-record-gone title="The <?= escapeHtml(strtolower($type)) ?> this is about has been deleted"><i class="fas fa-fw fa-ghost me-1"></i>No longer here</div>
                        <?php } ?>
                        <?php if ($from !== '') { ?>
                            <div class="d-xl-none text-body-secondary mt-1"><?= escapeHtml($from) ?></div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?php if ($row['client_name'] !== null) { ?>
                            <a class="text-reset" href="/agent/client_overview.php?client_id=<?= intval($row['log_client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-xl-table-cell small text-nowrap pe-3">
                        <div class="font-monospace"><?= escapeHtml((string) $row['log_ip']) ?></div>
                        <div class="text-body-secondary"><?= escapeHtml($device) ?></div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if ($view === 'signins' && $num_rows[0] > 0) { ?>
        <div class="card-footer small text-body-secondary" data-not-you><i class="fas fa-fw fa-info-circle me-1"></i>A sign in that wasn't you? <a href="user_security.php">Change your password</a>, and tell an administrator.</div>
    <?php } ?>
    <?php
    $empty_state = [
        'thing' => 'entries',
        'icon' => 'fa-history',
        'filtered' => $any_filter,
        'views' => ['view'],
        'view' => $view !== '' ? ['icon' => $views[$view]['icon'], 'title' => $view_empty[$view], 'text' => escapeHtml($views[$view]['title']) . ' show here.'] : null,
        'title' => 'Nothing yet',
        'text' => 'What you change, your sign ins, the passwords you look at and what you export show here.',
    ];
    require_once "../../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../../includes/footer.php";
