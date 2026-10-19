<?php

/*
 * A client's Audit Log - who did what for this client, when and from where,
 * from the audit log: its records made, changed and deleted, its contacts
 * signing in to the portal, passwords looked at and files taken out. Only
 * what the agent's role can see (auditLogRoleSql()): the kinds of record in
 * the parts of ITFlow it reads, and never another agent turned away from the
 * client. Searched, filtered by date, who, type and action, in views, a page
 * at a time; each entry links to its record while it still exists. An admin
 * also gets each entry's quick look and the way to Admin > Audit Logs.
 * Record pages link here for all of one record's history (type, entity).
 */

// Default Column Sortby Filter
$sort = "log_id";
$order = "DESC";

if (!isset($_GET['client_id'])) {
    // Every client's is Admin > Audit Logs
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
    redirect($session_is_admin ? '/admin/audit_logs.php' : '/agent/clients.php');
}

require_once "includes/inc_all_client.php";

$page_title = 'Audit Log';

$sortable_columns = [
    'log_id' => "log_id $order",
    'log_created_at' => "log_created_at $order, log_id $order",
    'user_name' => "user_name IS NULL, user_name $order, log_id DESC",
    'log_type' => "log_type $order, log_action $order, log_id DESC",
    'log_action' => "log_action $order, log_id DESC",
    'log_ip' => "log_ip IS NULL, log_ip $order, log_id DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'log_id';
}

// Security is sign-ins, users and settings - an admin's, and in Admin > Audit Logs
$views = auditLogViews();
unset($views['security']);
$views['signins']['title'] = 'Their contacts signing in to the client portal';

$view = isset($_GET['view']) && is_string($_GET['view']) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$scope = "log_client_id = $client_id AND " . auditLogRoleSql();
$filter_src = array_merge($_GET, ['client' => $client_id, 'dtf' => $dtf, 'dtt' => $dtt, 'view' => $view]);
$joins = auditLogJoinsSql();

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS log_id, log_type, log_action, log_description, log_ip, log_user_agent, log_created_at, log_client_id, log_user_id, log_entity_id,
        user_name, user_avatar, user_type, client_name
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
$new_ips = signInNewIpIds($rows);
$kinds = auditLogKinds();

// Each view counted for the rest of the filters
$count_sql = [];
foreach ($views as $definition) {
    $count_sql[] = 'COALESCE(SUM(' . ($definition['where'] === '' ? '1' : $definition['where']) . '), 0)';
}
$counts = array_combine(array_keys($views), array_map('intval', mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . "
    FROM logs $joins WHERE $scope AND " . auditLogFiltersSql(array_merge($filter_src, ['view' => '']))))));

$user_filter = is_string($_GET['user'] ?? null) ? $_GET['user'] : '';
$type_filter = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$action_filter = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
$entity_filter = is_scalar($_GET['entity'] ?? null) ? intval($_GET['entity']) : 0;
$more_filters = $action_filter !== '' || $entity_filter;
$any_filter = $q !== '' || $user_filter !== '' || $type_filter !== '' || $action_filter !== '' || $entity_filter || $dtf !== '1970-01-01' || $dtt !== '2099-12-31';

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
    'signins' => 'No portal sign-ins yet',
    'data' => 'Nothing looked at or taken out yet',
];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-history me-2"></i>Audit Log</h3>
        <?php if ($session_is_admin) { ?>
            <div class="card-tools">
                <a class="btn btn-outline-light" href="/admin/audit_logs.php?<?= escapeHtml(http_build_query(array_filter(['client' => $client_id, 'view' => $view, 'q' => $_GET['q'] ?? '', 'type' => $type_filter, 'entity' => $entity_filter ?: '']))) ?>" title="Every client's, with each entry's quick look and export">
                    <i class="fas fa-fw fa-user-shield"></i><span class="d-none d-lg-inline ms-2">Admin &gt; Audit Logs</span>
                </a>
            </div>
        <?php } ?>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php if ($view !== '') { ?><input type="hidden" name="view" value="<?= escapeHtml($view) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search <?= $client_name ?>'s audit log" title="What was done, who did it, the IP address or the browser">
                        <button class="btn btn-outline-secondary <?= $more_filters ? 'active' : '' ?>" title="More filters" type="button" data-bs-toggle="collapse" data-bs-target="#auditMoreFilters"><i class="fas fa-filter"></i></button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="When" aria-label="When">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml(is_string($_GET['canned_date'] ?? null) ? $_GET['canned_date'] : '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="user" onchange="this.form.submit()" aria-label="Who">
                        <option value="">- Anyone -</option>
                        <option value="system" <?= $user_filter === 'system' ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas fa-cog', 'description' => 'Cron, the API, the email parser']) ?>>ITFlow itself</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, user_type FROM users
                            WHERE EXISTS (SELECT 1 FROM logs WHERE log_user_id = user_id AND log_client_id = $client_id AND " . auditLogRoleSql() . ")
                            ORDER BY user_type ASC, user_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['user_id']); ?>
                            <option <?= $user_filter === (string) $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?><?= intval($row['user_type']) === 2 ? ' data-description="Portal"' : '' ?>><?= escapeHtml($row['user_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="type" onchange="this.form.submit()" aria-label="Type">
                        <option value="">- Any type -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT DISTINCT log_type FROM logs WHERE $scope ORDER BY log_type ASC");
                        while ($row = mysqli_fetch_row($sql_filter)) { ?>
                            <option <?= $type_filter === $row[0] ? 'selected' : '' ?> value="<?= escapeHtml($row[0]) ?>"<?= selectOptionData(['icon' => 'fas ' . auditLogTypeIcon($row[0])]) ?>><?= escapeHtml($row[0]) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="collapse <?= $more_filters ? 'show' : '' ?>" id="auditMoreFilters">
                <div class="row g-2 mt-0 align-items-center">
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-bolt"></i></span>
                            <select class="form-select select2" name="action" onchange="this.form.submit()" aria-label="Action">
                                <option value="">- Any action -</option>
                                <?php
                                $sql_filter = mysqli_query($mysqli, "SELECT DISTINCT log_action FROM logs WHERE $scope ORDER BY log_action ASC");
                                while ($row = mysqli_fetch_row($sql_filter)) { ?>
                                    <option <?= $action_filter === $row[0] ? 'selected' : '' ?> value="<?= escapeHtml($row[0]) ?>"<?= selectOptionData(['icon' => 'fas ' . auditLogActionStyle($row[0])['icon']]) ?>><?= escapeHtml($row[0]) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($entity_filter) {
                        $entity_record = auditLogRecords([['log_type' => $type_filter, 'log_entity_id' => $entity_filter]])[$type_filter . '|' . $entity_filter] ?? null; ?>
                        <div class="col-lg-9 col-md-6 small" data-audit-entity="<?= $entity_filter ?>">
                            <input type="hidden" name="entity" value="<?= $entity_filter ?>">
                            <span class="text-body-secondary"><i class="fas fa-fw fa-filter me-1"></i>Just one record:</span>
                            <?php if ($entity_record) { ?>
                                <a href="<?= escapeHtml($entity_record['url']) ?>"><?= escapeHtml($type_filter . ' ' . $entity_record['name']) ?></a>
                            <?php } else { ?>
                                <span class="text-body-secondary"><?= escapeHtml($type_filter !== '' ? $type_filter : 'record') ?> #<?= $entity_filter ?></span>
                            <?php } ?>
                            <a class="ms-2" href="<?= escapeHtml('?' . http_build_query(array_diff_key($_GET, ['entity' => 1, 'page' => 1]))) ?>">Every record</a>
                        </div>
                    <?php } ?>
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
                <th><?= $sort_link('user_name', 'Who') ?></th>
                <th><?= $sort_link('log_type', 'What') ?></th>
                <th>Description</th>
                <th class="d-none d-xl-table-cell"><?= $sort_link('log_ip', 'From') ?></th>
                <?php if ($session_is_admin) { ?><th></th><?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $log_id = intval($row['log_id']);
                $who = auditLogWho($row);
                $type = (string) $row['log_type'];
                $link = auditLogRecordLink($row, $records);
                $gone = $link === '' && intval($row['log_entity_id']) > 0 && isset($kinds[$type]['table']);
                $device = auditLogDevice($row['log_user_agent']);
                $from = implode(' · ', array_filter([(string) $row['log_ip'], $device]));
                ?>
                <tr data-log-id="<?= $log_id ?>">
                    <td class="ps-3 text-nowrap small" title="<?= escapeHtml($row['log_created_at']) ?>">
                        <div><?= escapeHtml(date('M j, Y g:i A', strtotime($row['log_created_at']))) ?></div>
                        <div class="text-body-secondary"><?= escapeHtml(timeAgo($row['log_created_at'])) ?></div>
                    </td>
                    <td class="small" style="max-width: 14rem;"><?= $who['html'] ?></td>
                    <td class="text-nowrap">
                        <div class="small mb-1"><i class="fas fa-fw <?= auditLogTypeIcon($type) ?> text-secondary me-1"></i><?= escapeHtml($type) ?></div>
                        <?= auditLogActionPill($row['log_action']) ?><?= isset($new_ips[intval($row['log_id'])]) ? ' ' . signInNewIpBadge() : '' ?>
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
                    <td class="d-none d-xl-table-cell small text-nowrap">
                        <div class="font-monospace"><?= escapeHtml((string) $row['log_ip']) ?></div>
                        <div class="text-body-secondary"><?= escapeHtml($device) ?></div>
                    </td>
                    <?php if ($session_is_admin) { ?>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-light ajax-modal" data-modal-size="lg" data-modal-url="/admin/modals/audit_log/audit_log.php?id=<?= $log_id ?>" title="All of it" aria-label="All of it"><i class="fas fa-fw fa-search-plus"></i></button>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'audit log entries',
        'icon' => 'fa-history',
        'filtered' => $any_filter,
        'views' => ['view'],
        'view' => $view !== '' ? ['icon' => $views[$view]['icon'], 'title' => $view_empty[$view], 'text' => escapeHtml($views[$view]['title']) . ' show here.'] : null,
        'text' => 'Who changed what for ' . $client_name . ', looked at a password or took something out - when, and from where - shows here.',
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
