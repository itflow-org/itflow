<?php

/*
 * Tickets - agent listing
 *
 * Owns filter parsing, the filter bar and the ticket query. Rendering is
 * handed to ticket_list.php or ticket_kanban.php, both of which read the
 * variables set here.
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

/*
 * Normalise legacy query strings into the canonical filter params before
 * anything reads them, so every link and form on the page speaks one
 * language and $_GET can be trusted as the single source of truth.
 *
 *   status=Open|Closed  ->  state=open|closed   (status[] stays as-is)
 *   billable=1&unbilled ->  billing=unbilled
 *
 * The old page carried the coarse Open/Closed state in a scalar `status`
 * while the status multi-select posted `status[]`. Both landed in the same
 * key, so whichever came last in the query string won and the two controls
 * silently fought each other. They are separate params now.
 */
if (isset($_GET['status']) && !is_array($_GET['status'])) {
    if (!isset($_GET['state'])) {
        $_GET['state'] = strtolower($_GET['status']) === 'closed' ? 'closed' : 'open';
    }
    unset($_GET['status']);
}
if (isset($_GET['billable']) && $_GET['billable'] == '1' && isset($_GET['unbilled'])) {
    $_GET['billing'] = 'unbilled';
}
unset($_GET['billable'], $_GET['unbilled']);

// filter_header.php built this from the raw query string - rebuild it so the
// sort links carry the normalised params too
$get_copy = $_GET;
unset($get_copy['sort'], $get_copy['order']);
$url_query_strings_sort = http_build_query($get_copy);

/*
 * filter_header.php strips a sort value down to [a-z_], which blocks injection
 * but happily leaves an invalid column name behind - ?sort=ticket_id;DROP...
 * becomes ticket_idtickets, and the ORDER BY then takes the page down with an
 * uncaught mysqli exception. Only these columns are sortable here, so anything
 * else falls back to the default.
 */
$sortable_columns = array(
    'ticket_number', 'ticket_subject', 'client_name', 'contact_name',
    'ticket_billable', 'ticket_priority', 'ticket_status', 'user_name',
    'ticket_updated_at', 'ticket_created_at'
);
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'ticket_number';
}

/*
 * Builds a link to this page with filter overrides applied. Pass null to drop
 * a filter. Paging always resets - a page number from the previous filter set
 * means nothing once the result set changes.
 */
function ticketsFilterUrl($overrides = [])
{
    $params = $_GET;
    foreach ($overrides as $name => $value) {
        if ($value === null) {
            unset($params[$name]);
        } else {
            $params[$name] = $value;
        }
    }
    unset($params['page']);

    return escapeHtml('?' . http_build_query($params));
}

// View - list or kanban
$view = ($_GET['view'] ?? '') === 'kanban' ? 'kanban' : (($_GET['view'] ?? '') === 'list' ? 'list' : '');
if (!$view) {
    // Admin default (0 = list, 2 = kanban)
    $view = $config_ticket_default_view === 2 ? 'kanban' : 'list';
}

// Chips describing what is currently filtering the list, built as each
// filter is parsed. Each one renders with an x that drops just that filter.
$active_filters = array();

// Specific ticket statuses - takes precedence over the open/closed state
$status_filter = array();
if (isset($_GET['status']) && is_array($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = array_filter(array_map('intval', $_GET['status']));
}

/*
 * Coarse state. Anything with a resolved date is "closed" as far as this page
 * is concerned, matching what the counts in the header report.
 */
$state = $_GET['state'] ?? '';
if (!in_array($state, array('open', 'closed', 'all'), true)) {
    $state = 'open';
}

// Billing filter
$billing_filter = $_GET['billing'] ?? '';
if (!in_array($billing_filter, array('unbilled', 'invoiced', 'nonbillable'), true)) {
    $billing_filter = '';
}
$ticket_billable_snippet = '';
if ($billing_filter == 'unbilled') {
    $ticket_billable_snippet = 'AND ticket_billable = 1 AND ticket_invoice_id = 0';
    $active_filters[] = array('label' => 'Billing', 'value' => 'Billable, not invoiced', 'drop' => 'billing');
} elseif ($billing_filter == 'invoiced') {
    $ticket_billable_snippet = 'AND ticket_invoice_id > 0';
    $active_filters[] = array('label' => 'Billing', 'value' => 'Invoiced', 'drop' => 'billing');
} elseif ($billing_filter == 'nonbillable') {
    $ticket_billable_snippet = 'AND ticket_billable = 0';
    $active_filters[] = array('label' => 'Billing', 'value' => 'Not billable', 'drop' => 'billing');
}

/*
 * Unbilled work is nearly always on tickets that are already done, so a
 * billing filter with no state asked for widens to every ticket rather than
 * returning the empty list the open default would give. The old page did the
 * same thing invisibly - now it shows up as a chip.
 */
if ($billing_filter && !isset($_GET['state'])) {
    $state = 'all';
}

// Status snippet - specific statuses win, otherwise the coarse state
if ($status_filter) {
    $status_ids = implode(',', $status_filter);
    $ticket_status_snippet = "ticket_status IN ($status_ids)";

    $status_names = array();
    $sql_status_names = mysqli_query($mysqli, "SELECT ticket_status_name FROM ticket_statuses WHERE ticket_status_id IN ($status_ids) ORDER BY ticket_status_order");
    while ($row = mysqli_fetch_assoc($sql_status_names)) {
        $status_names[] = escapeHtml($row['ticket_status_name']);
    }
    $active_filters[] = array('label' => 'Status', 'value' => implode(', ', $status_names), 'drop' => 'status');
} elseif ($state == 'closed') {
    $ticket_status_snippet = 'ticket_resolved_at IS NOT NULL';
} elseif ($state == 'all') {
    $ticket_status_snippet = '1 = 1';
} else {
    $ticket_status_snippet = 'ticket_resolved_at IS NULL';
}

// Category Filter
$category_query = '';
$category_filter = '';
if (!empty($_GET['category'])) {
    $category_filter = intval($_GET['category']);
    $category_query = "AND ticket_category = $category_filter";
    $active_filters[] = array('label' => 'Category', 'value' => escapeHtml(getFieldById('categories', $category_filter, 'category_name')), 'drop' => 'category');
}

// Priority Filter
$ticket_priority_query = '';
$priority_filter = '';
if (!empty($_GET['priority']) && in_array($_GET['priority'], array('Low', 'Medium', 'High', 'Urgent'), true)) {
    $priority_filter = $_GET['priority'];
    $ticket_priority_query = "AND ticket_priority = '$priority_filter'";
    $active_filters[] = array('label' => 'Priority', 'value' => $priority_filter, 'drop' => 'priority');
}

// SLA state filter - breached / at risk / paused / met / no SLA
$ticket_sla_query = '';
$ticket_sla_filter = '';
$sla_filter_labels = array(
    'breached' => 'SLA breached',
    'at_risk'  => 'SLA at risk',
    'paused'   => 'SLA paused',
    'met'      => 'SLA met',
    'none'     => 'No SLA'
);
if (!empty($_GET['sla']) && isset($sla_filter_labels[$_GET['sla']])) {
    $ticket_sla_filter = $_GET['sla'];
    if ($ticket_sla_filter == 'breached') {
        $ticket_sla_query = 'AND ticket_sla_id > 0 AND (ticket_response_sla_alert_stage = 2 OR ticket_resolution_sla_alert_stage = 2 OR ticket_response_sla_met = 0 OR ticket_resolution_sla_met = 0)';
    } elseif ($ticket_sla_filter == 'at_risk') {
        $ticket_sla_query = 'AND ticket_sla_id > 0 AND COALESCE(ticket_status_pauses_sla, 0) = 0 AND (ticket_response_sla_alert_stage = 1 OR ticket_resolution_sla_alert_stage = 1)';
    } elseif ($ticket_sla_filter == 'paused') {
        $ticket_sla_query = 'AND ticket_sla_id > 0 AND ticket_status_pauses_sla = 1';
    } elseif ($ticket_sla_filter == 'met') {
        $ticket_sla_query = 'AND ticket_sla_id > 0 AND ticket_response_sla_met = 1 AND (ticket_resolution_sla_met = 1 OR ticket_resolution_due_at IS NULL)';
    } elseif ($ticket_sla_filter == 'none') {
        $ticket_sla_query = 'AND ticket_sla_id = 0';
    }
    $active_filters[] = array('label' => 'SLA', 'value' => $sla_filter_labels[$ticket_sla_filter], 'drop' => 'sla');
}

// Ticket assignment filter
$ticket_assigned_query = '';
$ticket_assigned_filter_id = '';
if (!empty($_GET['assigned'])) {
    if ($_GET['assigned'] == 'unassigned') {
        $ticket_assigned_query = 'AND ticket_assigned_to = 0';
        $ticket_assigned_filter_id = 0;
        $active_filters[] = array('label' => 'Assigned', 'value' => 'Unassigned', 'drop' => 'assigned');
    } else {
        $ticket_assigned_filter_id = intval($_GET['assigned']);
        $ticket_assigned_query = "AND ticket_assigned_to = $ticket_assigned_filter_id";
        $assigned_name = $ticket_assigned_filter_id === intval($session_user_id) ? 'Me' : escapeHtml(getFieldById('users', $ticket_assigned_filter_id, 'user_name'));
        $active_filters[] = array('label' => 'Assigned', 'value' => $assigned_name, 'drop' => 'assigned');
    }
}

// Project filter
$ticket_project_snippet = '';
$ticket_project_filter_id = '';
if (!empty($_GET['project'])) {
    $ticket_project_filter_id = intval($_GET['project']);
    $ticket_project_snippet = "AND ticket_project_id = $ticket_project_filter_id";
    $active_filters[] = array('label' => 'Project', 'value' => escapeHtml(getFieldById('projects', $ticket_project_filter_id, 'project_name')), 'drop' => 'project');
}

// Date range - filter_header.php resolves the canned ranges into $dtf / $dtt
$date_filter_active = isset($_GET['canned_date']) && $_GET['canned_date'] !== 'custom' && $_GET['canned_date'] !== 'alltime';
if (!$date_filter_active && !empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') {
    $date_filter_active = true;
}
if ($date_filter_active) {
    $active_filters[] = array('label' => 'Created', 'value' => "$dtf to $dtt", 'drop' => array('canned_date', 'dtf', 'dtt'));
}

// Search
if ($q !== '') {
    $active_filters[] = array('label' => 'Search', 'value' => stripslashes(escapeHtml($q)), 'drop' => 'q');
}

// Tickets with no client stay visible to restricted agents - clientScopeSql() includes 0
$access_permission_query_overide = clientScopeSql('ticket_client_id');

/*
 * Columns the two views need. Explicit rather than SELECT * - the tickets
 * table carries ticket_details (longtext), and pulling a page of ticket
 * bodies to render a list that never shows them is pure overhead.
 */
$ticket_select_columns =
    "ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority,
    ticket_status, ticket_billable, ticket_schedule, ticket_order,
    ticket_created_at, ticket_updated_at, ticket_resolved_at, ticket_closed_at,
    ticket_client_id, ticket_contact_id, ticket_assigned_to, ticket_project_id,
    ticket_invoice_id, ticket_quote_id, ticket_sla_id,
    ticket_first_response_at, ticket_response_due_at, ticket_resolution_due_at,
    ticket_response_sla_met, ticket_resolution_sla_met,
    ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage,
    ticket_status_id, ticket_status_name, ticket_status_color, ticket_status_pauses_sla,
    client_name, contact_id, contact_name, contact_email,
    user_name, asset_name, category_name";

$ticket_joins =
    "LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    LEFT JOIN locations ON ticket_location_id = location_id
    LEFT JOIN vendors ON ticket_vendor_id = vendor_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id";

$ticket_where =
    "WHERE $ticket_status_snippet
    $ticket_assigned_query
    $category_query
    $ticket_priority_query
    $ticket_sla_query
    $ticket_billable_snippet
    $ticket_project_snippet
    $access_permission_query_overide
    $client_query
    AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_status_name LIKE '%$q%' OR ticket_priority LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR ticket_vendor_ticket_number LIKE '%$q%')";

// Counts for the quick views in the header - scope-wide, not filter-aware
$count_where = "$access_permission_query_overide $client_query";
$total_tickets_open = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE ticket_resolved_at IS NULL $count_where"))[0]);
$total_tickets_closed = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE ticket_resolved_at IS NOT NULL $count_where"))[0]);
$total_tickets_unassigned = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE ticket_assigned_to = 0 AND ticket_resolved_at IS NULL $count_where"))[0]);
$user_active_assigned_tickets = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE ticket_assigned_to = $session_user_id AND ticket_resolved_at IS NULL $count_where"))[0]);

// Only offer the SLA filter once SLAs are actually in use
$sla_filter_in_use = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(sla_id) FROM slas WHERE sla_archived_at IS NULL"))[0] > 0;

// Gates both the Billing column in the table and the Billing filter select
$show_billing_column = $config_module_enable_accounting && lookupUserPermission("module_sales") >= 2;

/*
 * How many of the collapsed filters are set. The panel stays closed even when
 * filters are active - the count on the filter button and the chips below the
 * bar are what make them visible, without costing the vertical space an
 * auto-expanded panel would.
 */
$hidden_filter_count = 0;
foreach (array('status', 'assigned', 'priority', 'category', 'sla', 'project', 'billing') as $panel_filter) {
    if (!empty($_GET[$panel_filter])) {
        $hidden_filter_count++;
    }
}
if ($date_filter_active) {
    $hidden_filter_count++;
}

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring mr-2"></i>Tickets
            <small class="ml-3">
                <a href="<?= ticketsFilterUrl(['state' => 'open', 'status' => null]) ?>" class="badge badge-pill p-1 <?= (!$status_filter && $state == 'open') ? 'badge-light text-dark' : 'text-light' ?>"><strong><?= $total_tickets_open ?></strong> Open</a> |
                <a href="<?= ticketsFilterUrl(['state' => 'closed', 'status' => null]) ?>" class="badge badge-pill p-1 <?= (!$status_filter && $state == 'closed') ? 'badge-light text-dark' : 'text-light' ?>"><strong><?= $total_tickets_closed ?></strong> Closed</a> |
                <a href="<?= ticketsFilterUrl(['state' => 'all', 'status' => null]) ?>" class="badge badge-pill p-1 <?= (!$status_filter && $state == 'all') ? 'badge-light text-dark' : 'text-light' ?>">All</a>
            </small>
        </h3>
        <?php if (lookupUserPermission("module_support") >= 2) { ?>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>" data-modal-size="lg">
                        <i class="fas fa-plus"></i><span class="d-none d-lg-inline ml-2">New Ticket</span>
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/ticket/ticket_export.php', ['client_id', 'state', 'status', 'billing', 'category', 'priority', 'assigned', 'sla', 'project', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="card-body">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="hidden" name="state" value="<?= $state ?>">
            <input type="hidden" name="sort" value="<?= escapeHtml($sort) ?>">
            <input type="hidden" name="order" value="<?= escapeHtml($order) ?>">
            <?php if ($billing_filter && !$show_billing_column) { ?>
                <!-- No Billing select for this agent, but a drill-through filter must still survive a search submit -->
                <input type="hidden" name="billing" value="<?= escapeHtml($billing_filter) ?>">
            <?php } ?>

            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group mb-2 mb-sm-0">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Tickets">
                            <div class="input-group-append">
                                <button class="btn <?= $hidden_filter_count ? 'btn-warning' : 'btn-secondary' ?>" type="button" data-toggle="collapse" data-target="#advancedFilter" title="Filters">
                                    <i class="fas fa-filter"></i><?php if ($hidden_filter_count) { ?><span class="ml-1"><?= $hidden_filter_count ?></span><?php } ?>
                                </button>
                                <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-8">
                    <div class="btn-group float-sm-right">
                        <a href="<?= $ticket_assigned_filter_id === intval($session_user_id) ? ticketsFilterUrl(['assigned' => null]) : ticketsFilterUrl(['assigned' => $session_user_id]) ?>"
                            class="btn <?= $ticket_assigned_filter_id === intval($session_user_id) ? 'btn-primary' : 'btn-outline-primary' ?>">
                            <i class="fas fa-fw fa-user"></i><span class="d-none d-xl-inline ml-2">Mine</span> | <strong><?= $user_active_assigned_tickets ?></strong>
                        </a>
                        <a href="<?= $ticket_assigned_filter_id === 0 ? ticketsFilterUrl(['assigned' => null]) : ticketsFilterUrl(['assigned' => 'unassigned']) ?>"
                            class="btn <?= $ticket_assigned_filter_id === 0 ? 'btn-danger' : 'btn-outline-danger' ?>">
                            <i class="fas fa-fw fa-exclamation-triangle"></i><span class="d-none d-xl-inline ml-2">Unassigned</span> | <strong><?= $total_tickets_unassigned ?></strong>
                        </a>
                        <a href="<?= ticketsFilterUrl(['view' => $view == 'kanban' ? 'list' : 'kanban']) ?>" class="btn btn-outline-dark ml-2" title="Switch to the <?= $view == 'kanban' ? 'list' : 'kanban' ?> view">
                            <i class="fa fa-fw <?= $view == 'kanban' ? 'fa-list' : 'fa-columns' ?>"></i>
                            <span class="d-none d-xl-inline ml-2"><?= $view == 'kanban' ? 'List' : 'Kanban' ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="collapse mt-3 <?php if ($hidden_filter_count) { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group mb-md-0">
                            <label>Status</label>
                            <select onchange="this.form.submit()" class="form-control select2" name="status[]" data-placeholder="Any status" multiple>
                                <?php
                                $sql_ticket_status = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order");
                                while ($row = mysqli_fetch_assoc($sql_ticket_status)) {
                                    $filter_status_id = intval($row['ticket_status_id']);
                                    $filter_status_name = escapeHtml($row['ticket_status_name']);
                                    ?>
                                    <option value="<?= $filter_status_id ?>" <?php if (in_array($filter_status_id, $status_filter, true)) { echo 'selected'; } ?>><?= $filter_status_name ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group mb-md-0">
                            <label>Assigned to</label>
                            <select onchange="this.form.submit()" class="form-control select2" name="assigned">
                                <option value="">Anyone</option>
                                <option value="unassigned" <?php if ($ticket_assigned_filter_id === 0) { echo "selected"; } ?>>Unassigned</option>

                                <?php
                                $sql_assign_to = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_assign_to)) {
                                    $filter_user_id = intval($row['user_id']);
                                    $filter_user_name = escapeHtml($row['user_name']);
                                    ?>
                                    <option value="<?= $filter_user_id ?>" <?php if ($ticket_assigned_filter_id === $filter_user_id) { echo "selected"; } ?>><?= $filter_user_name ?><?php if ($filter_user_id === intval($session_user_id)) { echo ' (me)'; } ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group mb-md-0">
                            <label>Priority</label>
                            <select onchange="this.form.submit()" class="form-control select2" name="priority">
                                <option value="">Any priority</option>
                                <?php foreach (array('Urgent', 'High', 'Medium', 'Low') as $priority_option) { ?>
                                    <option value="<?= $priority_option ?>" <?php if ($priority_filter == $priority_option) { echo "selected"; } ?>><?= $priority_option ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group mb-md-0">
                            <label>Category</label>
                            <select onchange="this.form.submit()" class="form-control select2" name="category">
                                <option value="">Any category</option>

                                <?php
                                $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name");
                                while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                                    $filter_category_id = intval($row['category_id']);
                                    $filter_category_name = escapeHtml($row['category_name']);
                                    ?>
                                    <option value="<?= $filter_category_id ?>" <?php if ($category_filter === $filter_category_id) { echo "selected"; } ?>><?= $filter_category_name ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="form-group mb-md-0">
                            <label>Project</label>
                            <select onchange="this.form.submit()" class="form-control select2" name="project">
                                <option value="">Any project</option>

                                <?php
                                $sql_projects = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name FROM projects WHERE project_completed_at IS NULL AND project_archived_at IS NULL ORDER BY project_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_projects)) {
                                    $filter_project_id = intval($row['project_id']);
                                    $filter_project_label = escapeHtml($row['project_prefix'] . intval($row['project_number']) . ' - ' . $row['project_name']);
                                    ?>
                                    <option value="<?= $filter_project_id ?>" <?php if ($ticket_project_filter_id === $filter_project_id) { echo "selected"; } ?>><?= $filter_project_label ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($show_billing_column) { ?>
                        <div class="col-md-3">
                            <div class="form-group mb-md-0">
                                <label>Billing</label>
                                <select class="form-control select2" name="billing" onchange="this.form.submit()">
                                    <option value="">Any</option>
                                    <option value="unbilled" <?php if ($billing_filter == 'unbilled') { echo "selected"; } ?>>Billable, not invoiced</option>
                                    <option value="invoiced" <?php if ($billing_filter == 'invoiced') { echo "selected"; } ?>>Invoiced</option>
                                    <option value="nonbillable" <?php if ($billing_filter == 'nonbillable') { echo "selected"; } ?>>Not billable</option>
                                </select>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ($sla_filter_in_use) { ?>
                        <div class="col-md-3">
                            <div class="form-group mb-md-0">
                                <label>SLA</label>
                                <select class="form-control select2" name="sla" onchange="this.form.submit()">
                                    <option value="">Any SLA state</option>
                                    <?php foreach ($sla_filter_labels as $sla_option => $sla_option_label) { ?>
                                        <option value="<?= $sla_option ?>" <?php if ($ticket_sla_filter == $sla_option) { echo "selected"; } ?>><?= $sla_option_label ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="col-md-3">
                        <div class="form-group mb-md-0">
                            <label>Created</label>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                            <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                            <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                            <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($active_filters) { ?>
            <!-- The filters are collapsed by default, so this line is what stops an
                 active filter from being invisible -->
            <div class="mt-2">
                <?php foreach ($active_filters as $filter) {
                    $drop = is_array($filter['drop']) ? array_fill_keys($filter['drop'], null) : array($filter['drop'] => null);
                    ?>
                    <span class="badge badge-pill badge-light border p-2 mr-1 font-weight-normal">
                        <span class="text-secondary"><?= $filter['label'] ?>:</span> <?= $filter['value'] ?>
                        <a href="<?= ticketsFilterUrl($drop) ?>" class="text-danger ml-1" title="Remove this filter"><i class="fas fa-times"></i></a>
                    </span>
                <?php } ?>
                <a href="<?= escapeHtml('?' . http_build_query(array_filter(array('client_id' => $_GET['client_id'] ?? null, 'view' => $view)))) ?>" class="small text-danger ml-1">Clear all</a>
            </div>
        <?php } ?>

    </div>
</div>

<?php

if ($view == 'kanban') {
    require_once "ticket_kanban.php";
} else {
    require_once "ticket_list.php";
}

?>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
