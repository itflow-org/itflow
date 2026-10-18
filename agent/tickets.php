<?php

/*
 * Tickets - every ticket the agent can reach, or one client's, as a list or
 * as a board. This page owns the filters and the views; ticket_list.php and
 * ticket_kanban.php draw the tickets from what it sets up.
 *
 * The pills along the top are the views a service desk works from - open,
 * mine, unassigned, the ones waiting on a reply from us, the ones waiting on
 * the client, overdue and scheduled - each counted over the filters above
 * them, so they always say how many are behind each one.
 *
 * Old links keep working: ?status=Open|Closed or a status by name (?status=New
 * from the nightly "pending assignment" notice), ?state=open|closed|all,
 * ?billable=1&unbilled, ?assigned=, ?sla=, ?status[]=.
 */

// Default Column Sortby Filter
$sort = "ticket_number";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND ticket_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

$can_edit = ticketCan(2);
$can_delete = ticketCan(3);

/*
 * Old query strings, into the ones this page reads, before anything reads
 * them - so every link and form below speaks one language.
 */
if (isset($_GET['status']) && !is_array($_GET['status'])) {
    $legacy_status = strtolower(trim((string) $_GET['status']));
    unset($_GET['status']);
    if ($legacy_status === 'open' || $legacy_status === 'closed') {
        $_GET['state'] = $_GET['state'] ?? $legacy_status;
    } elseif ($legacy_status !== '') {
        $named_status = mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_name = '" . escapeSql($legacy_status) . "' LIMIT 1"));
        if ($named_status) {
            $_GET['status'] = [intval($named_status[0])];
        }
    }
}
if (!isset($_GET['show']) && isset($_GET['state'])) {
    $_GET['show'] = ['open' => 'open', 'closed' => 'closed', 'all' => 'all'][$_GET['state']] ?? '';
}
unset($_GET['state']);
if (isset($_GET['billable']) && $_GET['billable'] == '1' && isset($_GET['unbilled'])) {
    $_GET['billing'] = 'unbilled';
}
unset($_GET['billable'], $_GET['unbilled']);

// filter_header.php fills in canned_date=custom when no range was asked for - not a filter, so not carried into links
if (($_GET['canned_date'] ?? '') === 'custom' && empty($_GET['dtf'])) {
    unset($_GET['canned_date']);
}

// filter_header.php built this from the raw query string - again, from the one above
$get_copy = $_GET;
unset($get_copy['sort'], $get_copy['order']);
$url_query_strings_sort = http_build_query($get_copy);

// List or board - the admin's default when the link doesn't say
$view = $_GET['view'] ?? '';
if (!in_array($view, ['list', 'kanban'], true)) {
    $view = intval($config_ticket_default_view) === 2 ? 'kanban' : 'list';
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null && $v !== []; }));
};

// Only offer what is in use
$sla_in_use = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(sla_id) FROM slas WHERE sla_archived_at IS NULL"))[0] > 0;
$show_billing = $config_module_enable_accounting && lookupUserPermission("module_sales") >= 2;

/*
 * The filters. $active_filters are the ones tucked away under the Filters
 * button, shown as chips so a filter is never invisible.
 */
$active_filters = [];
$filter_sql = [];

// Client (all clients)
$client_filter = 0;
if (!$client_url && !empty($_GET['client'])) {
    $client_filter = intval($_GET['client']);
    $filter_sql[] = "AND ticket_client_id = $client_filter";
}

// Assigned - an agent, or nobody
$assigned_filter = $_GET['assigned'] ?? '';
if ($assigned_filter === 'unassigned') {
    $filter_sql[] = 'AND ticket_assigned_to = 0';
} elseif (intval($assigned_filter)) {
    $assigned_filter = intval($assigned_filter);
    $filter_sql[] = "AND ticket_assigned_to = $assigned_filter";
} else {
    $assigned_filter = '';
}

// Specific statuses
$status_filter = [];
if (isset($_GET['status']) && is_array($_GET['status'])) {
    $status_filter = array_values(array_unique(array_filter(array_map('intval', $_GET['status']))));
}
if ($status_filter) {
    $status_ids = implode(',', $status_filter);
    $filter_sql[] = "AND ticket_status IN ($status_ids)";
    $status_names = [];
    $sql_status_names = mysqli_query($mysqli, "SELECT ticket_status_name FROM ticket_statuses WHERE ticket_status_id IN ($status_ids) ORDER BY ticket_status_order");
    while ($row = mysqli_fetch_assoc($sql_status_names)) {
        $status_names[] = escapeHtml($row['ticket_status_name']);
    }
    $active_filters[] = ['label' => 'Status', 'value' => implode(', ', $status_names), 'drop' => ['status' => null]];
}

// Priority
$priority_filter = in_array($_GET['priority'] ?? '', TICKET_PRIORITIES, true) ? $_GET['priority'] : '';
if ($priority_filter) {
    $filter_sql[] = "AND ticket_priority = '$priority_filter'";
    $active_filters[] = ['label' => 'Priority', 'value' => $priority_filter, 'drop' => ['priority' => null]];
}

// Category
$category_filter = intval($_GET['category'] ?? 0);
if ($category_filter) {
    $filter_sql[] = "AND ticket_category = $category_filter";
    $active_filters[] = ['label' => 'Category', 'value' => escapeHtml(getFieldById('categories', $category_filter, 'category_name')), 'drop' => ['category' => null]];
}

// Project
$project_filter = intval($_GET['project'] ?? 0);
if ($project_filter) {
    $filter_sql[] = "AND ticket_project_id = $project_filter";
    $active_filters[] = ['label' => 'Project', 'value' => escapeHtml(getFieldById('projects', $project_filter, 'project_name')), 'drop' => ['project' => null]];
}

// Tags - a ticket with any of them
$tag_filter_ids = tagFilterIds();
if ($tag_filter_ids) {
    $filter_sql[] = tagFilterSql('ticket', $tag_filter_ids, 'tickets.ticket_id');
    $tag_filter_names = [];
    $sql_tag_names = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_type = 12 AND tag_id IN (" . implode(',', $tag_filter_ids) . ") ORDER BY tag_name");
    while ($row = mysqli_fetch_row($sql_tag_names)) {
        $tag_filter_names[] = escapeHtml($row[0]);
    }
    $active_filters[] = ['label' => 'Tags', 'value' => implode(', ', $tag_filter_names), 'drop' => ['tags' => null]];
}

// SLA state - the dashboard's "SLA breached" links here
$sla_filters = [
    'breached' => ['SLA breached', 'ticket_sla_id > 0 AND (ticket_response_sla_alert_stage = 2 OR ticket_resolution_sla_alert_stage = 2 OR ticket_response_sla_met = 0 OR ticket_resolution_sla_met = 0)'],
    'at_risk' => ['SLA at risk', 'ticket_sla_id > 0 AND COALESCE(ticket_status_pauses_sla, 0) = 0 AND (ticket_response_sla_alert_stage = 1 OR ticket_resolution_sla_alert_stage = 1)'],
    'paused' => ['SLA paused', 'ticket_sla_id > 0 AND ticket_status_pauses_sla = 1 AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL'],
    'met' => ['SLA met', 'ticket_sla_id > 0 AND ticket_response_sla_met = 1 AND (ticket_resolution_sla_met = 1 OR ticket_resolution_due_at IS NULL)'],
    'none' => ['No SLA', 'ticket_sla_id = 0'],
];
$sla_filter = isset($sla_filters[$_GET['sla'] ?? '']) ? $_GET['sla'] : '';
if ($sla_filter) {
    $filter_sql[] = "AND ({$sla_filters[$sla_filter][1]})";
    $active_filters[] = ['label' => 'SLA', 'value' => $sla_filters[$sla_filter][0], 'drop' => ['sla' => null]];
}

// Billing
$billing_filters = [
    'unbilled' => ['Billable, not invoiced', 'ticket_billable = 1 AND ticket_invoice_id = 0'],
    'invoiced' => ['Invoiced', 'ticket_invoice_id > 0'],
    'nonbillable' => ['Not billable', 'ticket_billable = 0'],
];
$billing_filter = isset($billing_filters[$_GET['billing'] ?? '']) ? $_GET['billing'] : '';
if ($billing_filter) {
    $filter_sql[] = "AND ({$billing_filters[$billing_filter][1]})";
    $active_filters[] = ['label' => 'Billing', 'value' => $billing_filters[$billing_filter][0], 'drop' => ['billing' => null]];
}

// Created - filter_header.php resolves the canned ranges into $dtf / $dtt
$date_filter_active = (isset($_GET['canned_date']) && !in_array($_GET['canned_date'], ['custom', 'alltime'], true))
    || (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01');
if ($date_filter_active) {
    $filter_sql[] = "AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'";
    $active_filters[] = ['label' => 'Created', 'value' => escapeHtml("$dtf to $dtt"), 'drop' => ['canned_date' => null, 'dtf' => null, 'dtt' => null]];
}

// Search
if ($q !== '') {
    $filter_sql[] = "AND (CONCAT(ticket_prefix, ticket_number) LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR client_name LIKE '%$q%'
        OR contact_name LIKE '%$q%' OR contact_email LIKE '%$q%' OR ticket_status_name LIKE '%$q%' OR ticket_priority LIKE '%$q%'
        OR user_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR ticket_vendor_ticket_number LIKE '%$q%' OR category_name LIKE '%$q%'
        " . tagSearchSql('ticket', $q, 'tickets.ticket_id') . ")";
}

$filters = clientScopeSql('ticket_client_id') . " $client_query " . implode(' ', $filter_sql);

/*
 * The views. On the board, Open is "on the board" - everything not closed,
 * so the Resolved column has its tickets - and the closed ones have no place.
 */
$open_sql = TICKET_OPEN_SQL;
$base_sql = $view === 'kanban' ? 'tickets.ticket_closed_at IS NULL' : $open_sql;
$shows = [
    'open' => ['label' => $view === 'kanban' ? 'On the board' : 'Open', 'where' => $base_sql,
        'title' => $view === 'kanban' ? 'Everything not closed yet' : 'Neither resolved nor closed'],
    'mine' => ['label' => 'Mine', 'icon' => 'fa-user-check', 'where' => "$base_sql AND ticket_assigned_to = " . intval($session_user_id)],
    'unassigned' => ['label' => 'Unassigned', 'icon' => 'fa-user-slash', 'where' => "$base_sql AND ticket_assigned_to = 0", 'alarm' => 'warning'],
    'reply' => ['label' => 'Needs reply', 'icon' => 'fa-reply', 'where' => ticketNeedsReplySql(), 'alarm' => 'warning',
        'title' => 'The client spoke last, or nobody has replied yet'],
    'waiting' => ['label' => 'Waiting', 'icon' => 'fa-hourglass-half', 'where' => ticketWaitingSql(),
        'title' => 'We replied last and it is with the client - or it is parked in a status that pauses the clock, like On Hold'],
    'overdue' => ['label' => 'Overdue', 'icon' => 'fa-stopwatch', 'where' => ticketOverdueSql(), 'alarm' => 'danger',
        'title' => 'Past its due date, or its SLA is breached'],
    'scheduled' => ['label' => 'Scheduled', 'icon' => 'fa-calendar-check', 'where' => "$open_sql AND ticket_schedule IS NOT NULL",
        'title' => 'A visit or call is booked'],
];
if ($view === 'list') {
    $shows['closed'] = ['label' => 'Closed', 'icon' => 'fa-check', 'where' => 'tickets.ticket_resolved_at IS NOT NULL', 'title' => 'Resolved or closed', 'divider' => true];
    $shows['all'] = ['label' => 'All', 'where' => '1 = 1'];
}

// A status or billing filter asked for on its own looks at every ticket - unbilled work is nearly all done already
$show = $_GET['show'] ?? '';
if (!isset($shows[$show])) {
    $show = ($view === 'list' && !isset($_GET['show']) && ($status_filter || $billing_filter)) ? 'all' : 'open';
}

// Everything both views select and join - not ticket_details, a page of ticket bodies nobody sees
$ticket_select_columns =
    "tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_source,
    ticket_status, ticket_billable, ticket_schedule, ticket_onsite, ticket_order, ticket_due_at,
    ticket_created_at, ticket_updated_at, ticket_resolved_at, ticket_closed_at,
    ticket_client_id, ticket_contact_id, ticket_assigned_to, ticket_project_id, ticket_asset_id,
    ticket_invoice_id, ticket_quote_id, ticket_sla_id, ticket_vendor_ticket_number,
    ticket_first_response_at, ticket_response_due_at, ticket_resolution_due_at,
    ticket_response_sla_met, ticket_resolution_sla_met,
    ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage,
    ticket_status_id, ticket_status_name, ticket_status_color, ticket_status_pauses_sla,
    client_name, contact_id, contact_name, contact_email, contact_photo,
    user_name, user_avatar, asset_name, category_name, category_color, category_icon, project_prefix, project_number, project_name,
    " . ticketLastSpokeSql() . " AS ticket_last_spoke";

$ticket_joins =
    "LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    LEFT JOIN vendors ON ticket_vendor_id = vendor_id
    LEFT JOIN projects ON ticket_project_id = project_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id";

$ticket_where = "WHERE {$shows[$show]['where']} $filters";

// How many behind each pill - one pass over everything the filters leave
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM tickets $ticket_joins WHERE 1 = 1 $filters"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

// How many of the tucked-away filters are set - on the Filters button
$hidden_filter_count = count($active_filters);

// What the export starts from - the pill, in the export's own terms
$export_extra = [
    'state' => $show === 'closed' ? 'closed' : ($show === 'all' ? 'all' : 'open'),
    'assigned' => $show === 'mine' ? intval($session_user_id) : ($show === 'unassigned' ? 'unassigned' : ''),
    'dtf' => $date_filter_active ? $dtf : '',
    'dtt' => $date_filter_active ? $dtt : '',
];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring me-2"></i>Tickets</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>" data-modal-size="lg">
                        <i class="fas fa-plus me-2"></i>New Ticket
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="recurring_tickets.php<?= $client_url ? '?' . rtrim($client_url, '&') : '' ?>">
                        <i class="fa fa-fw fa-redo-alt me-2"></i>Recurring tickets
                    </a>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="<?= buildExportModalUrl('modals/ticket/ticket_export.php', ['client_id', 'client', 'status', 'billing', 'category', 'priority', 'assigned', 'sla', 'project', 'tags', 'q'], $export_extra) ?>">
                        <i class="fa fa-fw fa-download me-2"></i>Export
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <input type="hidden" name="sort" value="<?= escapeHtml($sort) ?>">
            <input type="hidden" name="order" value="<?= escapeHtml($order) ?>">
            <?php if ($billing_filter && !$show_billing) { ?>
                <!-- No Billing filter for this agent, but a link's billing filter still survives a search -->
                <input type="hidden" name="billing" value="<?= escapeHtml($billing_filter) ?>">
            <?php } ?>

            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-5' : 'col-lg-4' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search tickets..." title="Number, subject, client, contact, asset, vendor reference...">
                        <button class="btn <?= $hidden_filter_count ? 'btn-warning' : 'btn-outline-secondary' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter" title="More filters" aria-expanded="<?= $hidden_filter_count ? 'true' : 'false' ?>">
                            <i class="fas fa-sliders-h"></i><?php if ($hidden_filter_count) { ?><span class="ms-1"><?= $hidden_filter_count ?></span><?php } ?>
                        </button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="<?= $client_url ? 'col-lg-3' : 'col-lg-2' ?> col-md-6">
                    <select class="form-select select2" name="assigned" onchange="this.form.submit()">
                        <option value="">- Anyone -</option>
                        <option value="unassigned" <?= $assigned_filter === 'unassigned' ? 'selected' : '' ?> data-icon="fas fa-user-slash">Unassigned</option>
                        <?php
                        $sql_agents_filter = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_id = " . intval($session_user_id) . " DESC, user_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_agents_filter)) {
                            $filter_user_id = intval($row['user_id']); ?>
                            <option value="<?= $filter_user_id ?>" <?= $assigned_filter === $filter_user_id ? 'selected' : '' ?><?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?><?= $filter_user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . "
                                AND (EXISTS (SELECT 1 FROM tickets WHERE ticket_client_id = client_id AND ticket_closed_at IS NULL) OR client_id = $client_filter)
                                ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id_filter = intval($row['client_id']); ?>
                                <option <?= $client_filter === $client_id_filter ? 'selected' : '' ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($view === 'list' && $can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_assign.php" data-bulk="true">
                                        <i class="fas fa-fw fa-user-check me-2"></i>Assign
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_edit_priority.php" data-bulk="true">
                                        <i class="fas fa-fw fa-thermometer-half me-2"></i>Set priority
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_edit_category.php" data-bulk="true">
                                        <i class="fas fa-fw fa-tag me-2"></i>Set category
                                    </a>
                                    <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="ticket" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-tags me-2"></i>Tags
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_add_project.php" data-bulk="true">
                                        <i class="fas fa-fw fa-project-diagram me-2"></i>Add to a project
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_reply.php" data-modal-size="lg" data-bulk="true">
                                        <i class="fas fa-fw fa-paper-plane me-2"></i>Reply or add a note
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_merge.php" data-bulk="true">
                                        <i class="fas fa-fw fa-clone me-2"></i>Merge into a ticket
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_resolve.php" data-modal-size="lg" data-bulk="true">
                                        <i class="fas fa-fw fa-check me-2"></i>Resolve
                                    </a>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_tickets"
                                            data-confirm-text="The tickets go for good, with their replies, tasks and attachments. Closed tickets are kept.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="btn-group" role="group" aria-label="View">
                            <a href="<?= escapeHtml($filter_url(['view' => 'list'])) ?>" class="btn <?= $view === 'list' ? 'btn-dark' : 'btn-outline-dark' ?>" title="List">
                                <i class="fas fa-fw fa-list"></i><span class="d-none d-xl-inline ms-1">List</span>
                            </a>
                            <a href="<?= escapeHtml($filter_url(['view' => 'kanban', 'show' => in_array($show, ['closed', 'all'], true) ? '' : $show])) ?>" class="btn <?= $view === 'kanban' ? 'btn-dark' : 'btn-outline-dark' ?>" title="Board">
                                <i class="fas fa-fw fa-columns"></i><span class="d-none d-xl-inline ms-1">Board</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="collapse <?= $hidden_filter_count ? 'show' : '' ?>" id="advancedFilter">
                <div class="row g-2 mt-1">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-body-secondary mb-1">Status</label>
                        <select onchange="this.form.submit()" class="form-select select2" name="status[]" data-placeholder="Any status" multiple>
                            <?php
                            $sql_ticket_status = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name, ticket_status_color FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order, ticket_status_id");
                            while ($row = mysqli_fetch_assoc($sql_ticket_status)) {
                                $filter_status_id = intval($row['ticket_status_id']); ?>
                                <option value="<?= $filter_status_id ?>" <?= in_array($filter_status_id, $status_filter, true) ? 'selected' : '' ?><?= selectOptionColor($row['ticket_status_color'] ?? '') ?>><?= escapeHtml($row['ticket_status_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-body-secondary mb-1">Priority</label>
                        <select onchange="this.form.submit()" class="form-select select2" name="priority">
                            <option value="">Any priority</option>
                            <?php foreach (array_reverse(TICKET_PRIORITIES) as $priority_option) { ?>
                                <option value="<?= $priority_option ?>" <?= $priority_filter === $priority_option ? 'selected' : '' ?>><?= $priority_option ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-body-secondary mb-1">Category</label>
                        <select onchange="this.form.submit()" class="form-select select2" name="category">
                            <option value="">Any category</option>
                            <?= categoryFilterOptionsHtml('Ticket', $category_filter, "SELECT DISTINCT ticket_category FROM tickets WHERE ticket_category <> ''") ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-body-secondary mb-1">Project</label>
                        <select onchange="this.form.submit()" class="form-select select2" name="project">
                            <option value="">Any project</option>
                            <?php
                            $sql_projects = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name FROM projects
                                WHERE ((project_completed_at IS NULL AND project_archived_at IS NULL) OR project_id = $project_filter)
                                " . ($client_url ? "AND project_client_id IN (0, $client_id)" : clientScopeSql('project_client_id')) . "
                                ORDER BY project_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_projects)) {
                                $filter_project_id = intval($row['project_id']); ?>
                                <option value="<?= $filter_project_id ?>" <?= $project_filter === $filter_project_id ? 'selected' : '' ?> data-icon="fas fa-project-diagram" data-description="<?= escapeHtml($row['project_prefix'] . intval($row['project_number'])) ?>"><?= escapeHtml($row['project_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-body-secondary mb-1">Tags</label>
                        <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="Any tag" multiple>
                            <?= tagFilterOptionsHtml('ticket', $tag_filter_ids, '1 ' . clientScopeSql('ticket_client_id') . ($client_url ? " AND ticket_client_id = $client_id" : '')) ?>
                        </select>
                    </div>
                    <?php if ($sla_in_use) { ?>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-body-secondary mb-1">SLA</label>
                            <select class="form-select select2" name="sla" onchange="this.form.submit()">
                                <option value="">Any SLA state</option>
                                <?php foreach ($sla_filters as $sla_option => [$sla_option_label]) { ?>
                                    <option value="<?= $sla_option ?>" <?= $sla_filter === $sla_option ? 'selected' : '' ?>><?= $sla_option_label ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>
                    <?php if ($show_billing) { ?>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-body-secondary mb-1">Billing</label>
                            <select class="form-select select2" name="billing" onchange="this.form.submit()">
                                <option value="">Any</option>
                                <?php foreach ($billing_filters as $billing_option => [$billing_option_label]) { ?>
                                    <option value="<?= $billing_option ?>" <?= $billing_filter === $billing_option ? 'selected' : '' ?>><?= $billing_option_label ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-body-secondary mb-1">Created</label>
                        <input type="text" id="dateFilter" class="form-control" autocomplete="off" placeholder="Any time">
                        <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                        <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                        <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                    </div>
                </div>
            </div>
        </form>

        <!-- The views - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $counts[$key] && !empty($s['alarm']) && $show !== $key ? $s['alarm'] : ''; ?>
                <?php if (!empty($s['divider'])) { ?><span class="vr mx-1 my-1"></span><?php } ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? "text-bg-$alarm" : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>

        <?php if ($active_filters) { ?>
            <!-- What the tucked-away filters are set to - each with its own x -->
            <div class="d-flex flex-wrap align-items-center gap-1 mt-2">
                <?php foreach ($active_filters as $filter) { ?>
                    <span class="badge rounded-pill bg-body-secondary text-body fw-normal border py-2 px-2">
                        <span class="text-body-secondary"><?= $filter['label'] ?>:</span> <?= $filter['value'] ?>
                        <a href="<?= escapeHtml($filter_url($filter['drop'])) ?>" class="text-danger ms-1" title="Remove this filter"><i class="fas fa-times"></i></a>
                    </span>
                <?php } ?>
                <a href="<?= escapeHtml($filter_url(['status' => null, 'priority' => null, 'category' => null, 'project' => null, 'tags' => null, 'sla' => null, 'billing' => null, 'canned_date' => null, 'dtf' => null, 'dtt' => null])) ?>" class="small text-danger ms-1">Clear</a>
            </div>
        <?php } ?>
    </div>

    <?php
    if ($view === 'kanban') {
        require_once "ticket_kanban.php";
    } else {
        require_once "ticket_list.php";
    }
    ?>
</div>

<?php if (!empty($_GET['open']) || !empty($_GET['new_from_template'])) { ?>
    <script>
        // Opened from somewhere that wants one ticket's quick look - or a new
        // ticket from a template (New ticket from it, Admin > Ticket Templates)
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = <?= json_encode(!empty($_GET['open']) ? 'modals/ticket/ticket.php?id=' . intval($_GET['open']) : 'modals/ticket/ticket_add.php?ticket_template_id=' . intval($_GET['new_from_template'])) ?>;
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
