<?php

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

// Ticket status from GET
if (isset($_GET['status']) && is_array($_GET['status']) && !empty($_GET['status'])) {
    // Sanitize each element of the status array
    $sanitizedStatuses = array();
    foreach ($_GET['status'] as $status) {
        // Escape each status to prevent SQL injection
        $sanitizedStatuses[] = "'" . intval($status) . "'";
    }

    // Convert the sanitized statuses into a comma-separated string
    $sanitizedStatusesString = implode(",", $sanitizedStatuses);
    $ticket_status_snippet = "ticket_status IN ($sanitizedStatusesString)";

} else {

    // TODO: Convert this to use the status IDs
    if (isset($_GET['status']) && ($_GET['status']) == 'Closed') {
        $status = 'Closed';
        $ticket_status_snippet = "ticket_resolved_at IS NOT NULL";
    } else {
        // Default - Show open tickets
        $status = 'Open';
        $ticket_status_snippet = "ticket_resolved_at IS NULL";
    }
}

if (isset($_GET['billable']) && ($_GET['billable']) == '1') {
    if (isset($_GET['unbilled'])) {
        $billable = 1;
        $ticket_billable_snippet = "AND ticket_billable = 1 AND ticket_invoice_id = 0";
        $ticket_status_snippet = '1 = 1';
    }
} else {
    $billable = 0;
    $ticket_billable_snippet = '';
}

if (!empty($_GET['category'])) {
    $category = intval($_GET['category']);
    $category_snippet = "AND ticket_category = $category";
} else {
    $category_snippet = '';
}


// Ticket assignment status filter
// Default - any
$ticket_assigned_query = '';
$ticket_assigned_filter_id = '';
if (isset($_GET['assigned']) & !empty($_GET['assigned'])) {
    if ($_GET['assigned'] == 'unassigned') {
        $ticket_assigned_query = 'AND ticket_assigned_to = 0';
        $ticket_assigned_filter_id = 0;
    } else {
        $ticket_assigned_query = 'AND ticket_assigned_to = ' . intval($_GET['assigned']);
        $ticket_assigned_filter_id = intval($_GET['assigned']);
    }
}

// Ticket client access snippet
$ticket_permission_snippet = '';
if (!empty($client_access_string)) {
    $ticket_permission_snippet = "AND ticket_client_id IN ($client_access_string)";
}

// Main ticket query:
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    LEFT JOIN locations ON ticket_location_id = location_id
    LEFT JOIN vendors ON ticket_vendor_id = vendor_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id
    WHERE $ticket_status_snippet " . $ticket_assigned_query . "
    $category_snippet
    AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_status_name LIKE '%$q%' OR ticket_priority LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR ticket_vendor_ticket_number LIKE '%q%')
    $ticket_billable_snippet
    $ticket_permission_snippet
    $client_query
    ORDER BY
        CASE 
            WHEN '$sort' = 'ticket_priority' THEN
                CASE ticket_priority
                    WHEN 'High' THEN 1
                    WHEN 'Medium' THEN 2
                    WHEN 'Low' THEN 3
                    ELSE 4  -- Optional: for unexpected priority values
                END
            ELSE NULL
        END $order, 
        $sort $order  -- Apply normal sorting by $sort and $order
    LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

//Get Total tickets open
$sql_total_tickets_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_open FROM tickets WHERE ticket_resolved_at IS NULL $client_query $ticket_permission_snippet");
$row = mysqli_fetch_array($sql_total_tickets_open);
$total_tickets_open = intval($row['total_tickets_open']);

//Get Total tickets closed
$sql_total_tickets_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_closed FROM tickets WHERE ticket_resolved_at IS NOT NULL $client_query $ticket_permission_snippet");
$row = mysqli_fetch_array($sql_total_tickets_closed);
$total_tickets_closed = intval($row['total_tickets_closed']);

//Get Unassigned tickets
$sql_total_tickets_unassigned = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_unassigned FROM tickets WHERE ticket_assigned_to = '0' AND ticket_resolved_at IS NULL $client_query $ticket_permission_snippet");
$row = mysqli_fetch_array($sql_total_tickets_unassigned);
$total_tickets_unassigned = intval($row['total_tickets_unassigned']);

//Get Total tickets assigned to me
$sql_total_tickets_assigned = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_assigned FROM tickets WHERE ticket_assigned_to = $session_user_id AND ticket_resolved_at IS NULL $client_query $ticket_permission_snippet");
$row = mysqli_fetch_array($sql_total_tickets_assigned);
$user_active_assigned_tickets = intval($row['total_tickets_assigned']);

$sql_categories = mysqli_query(
    $mysqli,
    "SELECT * FROM categories
    WHERE category_type = 'Ticket'
    AND category_archived_at IS NULL
    ORDER BY category_name"
);



?>
    <style>
        .popover {
            max-width: 600px;
        }
    </style>
    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring mr-2"></i>Tickets
                <small class="ml-3">
                    <a href="?<?php echo $client_url; ?>status=Open" class="badge badge-pill text-light p-1 <?php if($status == 'Open') { echo "badge-light text-dark"; } ?>"><strong><?php echo $total_tickets_open; ?></strong> Open</a> |
                    <a href="?<?php echo $client_url; ?>status=Closed" class="badge badge-pill text-light p-1 <?php if($status == 'Closed') { echo "badge-light text-dark"; } ?>"><strong><?php echo $total_tickets_closed; ?></strong> Closed</a>
                </small>
            </h3>
            <?php if (lookupUserPermission("module_support") >= 2) { ?>
                <div class="card-tools">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketModal">
                            <i class="fas fa-plus"></i><span class="d-none d-lg-inline ml-2">New Ticket</span>
                        </button>
                        <?php if ($num_rows[0] > 0) { ?>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportTicketModal">
                                <i class="fa fa-fw fa-download mr-2"></i>Export
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <?php if ($client_url) { ?>
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <input type="hidden" name="status" value="<?php echo $status; ?>">
                <div class="row">
                    <div class="col-sm-5">
                        <div class="input-group mb-3 mb-sm-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Tickets">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-7">
                        <div class="btn-group float-right">
                            <div class="btn-group">
                                <button class="btn btn-outline-dark dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown">
                                    <i class="fa fa-fw fa-eye"></i>
                                    <span class="d-none d-xl-inline ml-2">View</span>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="<?=htmlspecialchars('?' . http_build_query(array_merge($_GET, ['view' => 'list']))); ?>">List</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item " href="<?=htmlspecialchars('?' . http_build_query(array_merge($_GET, ['view' => 'compact']))); ?>">Compact List</a>
                                    <?php if ($status !== 'Closed') {?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item " href="<?=htmlspecialchars('?' . http_build_query(array_merge($_GET, ['view' => 'kanban']))); ?>">Kanban</a>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-outline-dark dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown">
                                    <i class="fa fa-fw fa-layer-group"></i>
                                    <span class="d-none d-xl-inline ml-2">Categories</span>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item " href="<?=htmlspecialchars('?' . http_build_query(array_merge($_GET, ['category' => '']))); ?>">All</a>
                                    <div class="dropdown-divider"></div>
                                    <?php
                                    while ($row = mysqli_fetch_array($sql_categories)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = nullable_htmlentities($row['category_name']);
                                        $category_color = nullable_htmlentities($row['category_color']);
                                    ?>
                                    <a class="dropdown-item" href="<?=htmlspecialchars('?' . http_build_query(array_merge($_GET, ['category' => $category_id]))); ?>"><?php echo $category_name ?></a>
                                    <div class="dropdown-divider"></div>
                                <?php } ?>
                                    <a class="dropdown-item " href="<?=htmlspecialchars('?' . http_build_query(array_merge($_GET, ['category' => 'none']))); ?>">No Category</a>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-outline-dark dropdown-toggle" id="categoriesDropdownMenuButton" data-toggle="dropdown">
                                    <i class="fa fa-fw fa-envelope"></i>
                                    <span class="d-none d-xl-inline ml-2">My Tickets</span>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="?<?php echo $client_url; ?>status=Open&assigned=<?php echo $session_user_id ?>">Active tickets (<?php echo $user_active_assigned_tickets ?>)</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item " href="?<?php echo $client_url; ?>status=Closed&assigned=<?php echo $session_user_id ?>">Closed tickets</a>
                                </div>
                            </div>
                            <a href="?<?php echo $client_url; ?>assigned=unassigned" class="btn btn-outline-danger">
                                <i class="fa fa-fw fa-exclamation-triangle"></i>
                                <span class="d-none d-xl-inline ml-2">Unassigned</span> | <strong> <?php echo $total_tickets_unassigned; ?></strong>
                            </a>

                            <?php if (lookupUserPermission("module_support") >= 2) { ?>
                                <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                    <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTicketModal">
                                            <i class="fas fa-fw fa-user-check mr-2"></i>Assign Tech
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditCategoryTicketModal">
                                            <i class="fas fa-fw fa-layer-group mr-2"></i>Set Category
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditPriorityTicketModal">
                                            <i class="fas fa-fw fa-thermometer-half mr-2"></i>Update Priority
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkReplyTicketModal">
                                            <i class="fas fa-fw fa-paper-plane mr-2"></i>Bulk Update/Reply
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTicketToProjectModal">
                                            <i class="fas fa-fw fa-project-diagram mr-2"></i>Add to Project
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkMergeTicketModal">
                                            <i class="fas fa-fw fa-clone mr-2"></i>Merge
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkCloseTicketsModal">
                                            <i class="fas fa-fw fa-check mr-2"></i>Resolve
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                    </div>
                </div>

                <div
                    class="collapse mt-3
                        <?php
                        if (
                            !empty($_GET['dtf'])
                            || (isset($_GET['canned_date']) && $_GET['canned_date'] !== "custom")
                            || (isset($_GET['status']) && is_array($_GET['status'])
                            || (isset($_GET['assigned']) && $_GET['assigned']
                        )))
                            { echo "show"; }
                        ?>"
                    id="advancedFilter"
                >
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
                                    <option <?php if ($_GET['canned_date'] == "custom") {
                                        echo "selected";
                                    } ?> value="custom">Custom
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "today") {
                                        echo "selected";
                                    } ?> value="today">Today
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "yesterday") {
                                        echo "selected";
                                    } ?> value="yesterday">Yesterday
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "thisweek") {
                                        echo "selected";
                                    } ?> value="thisweek">This Week
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "lastweek") {
                                        echo "selected";
                                    } ?> value="lastweek">Last Week
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "thismonth") {
                                        echo "selected";
                                    } ?> value="thismonth">This Month
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "lastmonth") {
                                        echo "selected";
                                    } ?> value="lastmonth">Last Month
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "thisyear") {
                                        echo "selected";
                                    } ?> value="thisyear">This Year
                                    </option>
                                    <option <?php if ($_GET['canned_date'] == "lastyear") {
                                        echo "selected";
                                    } ?> value="lastyear">Last Year
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Ticket Status</label>
                                <select onchange="this.form.submit()" class="form-control select2" name="status[]" data-placeholder="Select Status" multiple>

                                        <?php $sql_ticket_status = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order");
                                        while ($row = mysqli_fetch_array($sql_ticket_status)) {
                                            $ticket_status_id = intval($row['ticket_status_id']);
                                            $ticket_status_name = nullable_htmlentities($row['ticket_status_name']); ?>

                                            <option value="<?php echo $ticket_status_id ?>" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array($ticket_status_id, $_GET['status'])) { echo 'selected'; } ?>> <?php echo $ticket_status_name ?> </option>

                                        <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Assigned to</label>
                                <select onchange="this.form.submit()" class="form-control select2" name="assigned">
                                    <option value="" <?php if ($ticket_assigned_filter_id == "") { echo "selected"; } ?>>Any</option>
                                    <option value="unassigned" <?php if ($ticket_assigned_filter_id == "0") { echo "selected"; } ?>>Unassigned</option>

                                    <?php
                                    $sql_assign_to = mysqli_query($mysqli, "SELECT * FROM users WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                                    while ($row = mysqli_fetch_array($sql_assign_to)) {
                                        $user_id = intval($row['user_id']);
                                        $user_name = nullable_htmlentities($row['user_name']);
                                        ?>
                                        <option <?php if ($ticket_assigned_filter_id == $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                        <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php

if (isset($_GET["view"])) {
    if ($_GET["view"] == "list") {
        require_once "tickets_list.php";
    } elseif ($_GET["view"] == "compact") {
        require_once "tickets_compact.php";
    } elseif ($_GET["view"] == "kanban") {
        require_once "tickets_kanban.php";
    }
} else {
    // here we have to get default view setting
    if ($config_ticket_default_view === 0) {
        require_once "tickets_list.php";
    } elseif ($config_ticket_default_view === 1) {
        require_once "tickets_compact.php";
    } elseif ($config_ticket_default_view === 2) {
        require_once "tickets_kanban.php";
    } else {
        require_once "tickets_list.php";
    }
}

?>

<script src="js/bulk_actions.js"></script>

<?php
require_once "modals/ticket_add_modal.php";
require_once "modals/ticket_export_modal.php";
require_once "includes/footer.php";
