<?php

/*
 * Admin > Audit Logs - who did what, when and from where: every change,
 * sign-in, look at a secret and export logAudit() writes. Searched, filtered
 * by date, who, client, type and action, and in views - changes, deletions,
 * sign-ins, security and data out - each counted for the filters picked.
 * Each entry links to the record it is about while that still exists, and
 * opens a quick look with all of it (modals/audit_log/audit_log.php). What is
 * shown can be exported (export_audit_logs). The work is in
 * functions/audit_log.php.
 */

// Default Column Sortby Filter
$sort = "log_id";
$order = "DESC";

require_once "includes/inc_all_admin.php";

// Tab title - the file is named for the table, the page for what it holds
$page_title = 'Audit Logs';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'log_id' => "log_id $order",
    'log_created_at' => "log_created_at $order, log_id $order",
    'user_name' => "user_name IS NULL, user_name $order, log_id DESC",
    'client_name' => "client_name IS NULL, client_name $order, log_id DESC",
    'log_type' => "log_type $order, log_action $order, log_id DESC",
    'log_action' => "log_action $order, log_id DESC",
    'log_ip' => "log_ip IS NULL, log_ip $order, log_id DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'log_id';
}

$views = auditLogViews();
$view = isset($_GET['view']) && is_string($_GET['view']) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$filter_src = array_merge($_GET, ['dtf' => $dtf, 'dtt' => $dtt, 'view' => $view]);
$joins = auditLogJoinsSql();

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS log_id, log_type, log_action, log_description, log_ip, log_user_agent, log_created_at, log_client_id, log_user_id, log_entity_id,
        user_name, user_avatar, user_type, client_name
    FROM logs $joins
    WHERE " . auditLogFiltersSql($filter_src) . "
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
    FROM logs $joins WHERE " . auditLogFiltersSql(array_merge($filter_src, ['view' => '']))))));

$client_filter = is_scalar($_GET['client'] ?? null) ? intval($_GET['client']) : 0;
$user_filter = is_string($_GET['user'] ?? null) ? $_GET['user'] : '';
$type_filter = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$action_filter = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
$entity_filter = is_scalar($_GET['entity'] ?? null) ? intval($_GET['entity']) : 0;
$more_filters = $action_filter !== '' || $entity_filter;
$any_filter = $q !== '' || $client_filter || $user_filter !== '' || $type_filter !== '' || $action_filter !== '' || $entity_filter || $dtf !== '1970-01-01' || $dtt !== '2099-12-31';

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

// When a view has nothing in it, and there are no filters to blame
$view_empty = [
    'changes' => 'No changes yet',
    'deletions' => 'Nothing deleted yet',
    'signins' => 'No sign-ins yet',
    'security' => 'Nothing to do with security yet',
    'data' => 'Nothing looked at or taken out yet',
];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-history me-2"></i>Audit Logs</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= buildExportModalUrl('modals/audit_log/audit_log_export.php', ['client', 'user', 'type', 'action', 'entity', 'q'], ['dtf' => $dtf, 'dtt' => $dtt, 'view' => $view]) ?>">
                <i class="fas fa-fw fa-download"></i><span class="d-none d-lg-inline ms-2">Export</span>
            </button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($view !== '') { ?><input type="hidden" name="view" value="<?= escapeHtml($view) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search the audit log" title="What was done, who did it, the client, the IP address or the browser">
                        <button class="btn btn-outline-secondary <?= $more_filters ? 'active' : '' ?>" title="More filters" type="button" data-bs-toggle="collapse" data-bs-target="#auditMoreFilters"><i class="fas fa-filter"></i></button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="When" aria-label="When">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml(is_string($_GET['canned_date'] ?? null) ? $_GET['canned_date'] : '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="user" onchange="this.form.submit()" aria-label="Who">
                        <option value="">- Anyone -</option>
                        <option value="system" <?= $user_filter === 'system' ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas fa-cog', 'description' => 'Cron, the API, the email parser']) ?>>ITFlow itself</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, user_type FROM users WHERE EXISTS (SELECT 1 FROM logs WHERE log_user_id = user_id) ORDER BY user_type ASC, user_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['user_id']); ?>
                            <option <?= $user_filter === (string) $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?><?= intval($row['user_type']) === 2 ? ' data-description="Portal"' : '' ?>><?= escapeHtml($row['user_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="Client">
                        <option value="">- Any client -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE EXISTS (SELECT 1 FROM logs WHERE log_client_id = client_id) ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['client_id']); ?>
                            <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="type" onchange="this.form.submit()" aria-label="Type">
                        <option value="">- Any type -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT DISTINCT log_type FROM logs ORDER BY log_type ASC");
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
                                $sql_filter = mysqli_query($mysqli, "SELECT DISTINCT log_action FROM logs ORDER BY log_action ASC");
                                while ($row = mysqli_fetch_row($sql_filter)) { ?>
                                    <option <?= $action_filter === $row[0] ? 'selected' : '' ?> value="<?= escapeHtml($row[0]) ?>"<?= selectOptionData(['icon' => 'fas ' . auditLogActionStyle($row[0])['icon']]) ?>><?= escapeHtml($row[0]) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($entity_filter) { ?>
                        <div class="col-lg-9 col-md-6 small" data-audit-entity="<?= $entity_filter ?>">
                            <input type="hidden" name="entity" value="<?= $entity_filter ?>">
                            <span class="text-body-secondary"><i class="fas fa-fw fa-filter me-1"></i>Just one record: <?= escapeHtml($type_filter !== '' ? $type_filter : 'record') ?> #<?= $entity_filter ?></span>
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
                <th class="d-none d-md-table-cell"><?= $sort_link('client_name', 'Client') ?></th>
                <th><?= $sort_link('log_type', 'What') ?></th>
                <th>Description</th>
                <th class="d-none d-xl-table-cell"><?= $sort_link('log_ip', 'From') ?></th>
                <th></th>
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
                    <td class="d-none d-md-table-cell small">
                        <?php if ($row['client_name'] !== null) { ?>
                            <a class="text-reset" href="/agent/client_overview.php?client_id=<?= intval($row['log_client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
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
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-light ajax-modal" data-modal-size="lg" data-modal-url="modals/audit_log/audit_log.php?id=<?= $log_id ?>" title="All of it" aria-label="All of it"><i class="fas fa-fw fa-search-plus"></i></button>
                    </td>
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
        'text' => 'Who changed what, signed in, looked at a password or exported something - when, and from where - shows here.',
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
