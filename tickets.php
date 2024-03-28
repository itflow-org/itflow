<?php


// Default Column Sortby Filter
$sort = "ticket_number";
$order = "DESC";

require_once "inc_all.php";


// Ticket status from GET
if (isset($_GET['status']) && is_array($_GET['status']) && !empty($_GET['status'])) {
    // Sanitize each element of the status array
    $sanitizedStatuses = array();
    foreach ($_GET['status'] as $status) {
        // Escape each status to prevent SQL injection
        $sanitizedStatuses[] = "'" . sanitizeInput($status) . "'";
    }

    // Convert the sanitized statuses into a comma-separated string
    $sanitizedStatusesString = implode(",", $sanitizedStatuses);
    $ticket_status_snippet = "ticket_status IN ($sanitizedStatusesString)";
} else {

    if (isset($_GET['status']) && ($_GET['status']) == 'Open') {
        $status = 'Open';
        $ticket_status_snippet = "ticket_status != 'Closed'";
    } elseif (isset($_GET['status']) && ($_GET['status']) == 'Closed') {
        $status = 'Closed';
        $ticket_status_snippet = "ticket_status = 'Closed'";
    } else {
        $status = 'Open';
        $ticket_status_snippet = "ticket_status != 'Closed'";
    }
}

// Ticket assignment status filter
if (isset($_GET['assigned']) & !empty($_GET['assigned'])) {
    if ($_GET['assigned'] == 'unassigned') {
        $ticket_assigned_query = 'AND ticket_assigned_to = 0';
        $ticket_assigned_filter_id = 0;
    } else {
        $ticket_assigned_query = 'AND ticket_assigned_to = ' . intval($_GET['assigned']);
        $ticket_assigned_filter_id = intval($_GET['assigned']);
    }
} else {
    // Default - any
    $ticket_assigned_query = '';
    $ticket_assigned_filter_id = '';
}

//Rebuild URL
$url_query_strings_sort = http_build_query(array_merge($_GET, array('sort' => $sort, 'order' => $order, 'status' => $status, 'assigned' => $ticket_assigned_filter)));

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
    WHERE $ticket_status_snippet " . $ticket_assigned_query . "
    AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_status LIKE '%$q%' OR ticket_priority LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR ticket_vendor_ticket_number LIKE '%q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

//Get Total tickets open
$sql_total_tickets_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_open FROM tickets WHERE ticket_status != 'Closed'");
$row = mysqli_fetch_array($sql_total_tickets_open);
$total_tickets_open = intval($row['total_tickets_open']);

//Get Total tickets closed
$sql_total_tickets_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_closed FROM tickets WHERE ticket_status = 'Closed'");
$row = mysqli_fetch_array($sql_total_tickets_closed);
$total_tickets_closed = intval($row['total_tickets_closed']);

//Get Total Recurring (scheduled) tickets
$sql_total_scheduled_tickets = mysqli_query($mysqli, "SELECT COUNT(scheduled_ticket_id) AS total_scheduled_tickets FROM scheduled_tickets");
$row = mysqli_fetch_array($sql_total_scheduled_tickets);
$total_scheduled_tickets = intval($row['total_scheduled_tickets']);

//Get Unassigned tickets
$sql_total_tickets_unassigned = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_unassigned FROM tickets WHERE ticket_assigned_to = '0' AND ticket_status != 'Closed'");
$row = mysqli_fetch_array($sql_total_tickets_unassigned);
$total_tickets_unassigned = intval($row['total_tickets_unassigned']);

//Get Total tickets assigned to me
$sql_total_tickets_assigned = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_assigned FROM tickets WHERE ticket_assigned_to = $session_user_id AND ticket_status != 'Closed'");
$row = mysqli_fetch_array($sql_total_tickets_assigned);
$user_active_assigned_tickets = intval($row['total_tickets_assigned']);

?>
    <style>
        .popover {
            max-width: 600px;
        }
    </style>
    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring mr-2"></i>Support Tickets
                <small class="ml-3">
                    <a href="?status=Open" class="text-white"><strong><?php echo $total_tickets_open; ?></strong> Open</a> |
                    <a href="?status=Closed" class="text-white"><strong><?php echo $total_tickets_closed; ?></strong> Closed</a>
                </small>
            </h3>
            <div class='card-tools'>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketModal">
                    <i class="fas fa-plus mr-2"></i>New Ticket
                </button>
            </div>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Tickets">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="btn-group float-right">
                            <div class="btn-group">
                                <button class="btn btn-outline-dark dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown">
                                    <i class="fa fa-fw fa-envelope mr-2"></i>My Tickets
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="?status=Open&assigned=<?php echo $session_user_id ?>">Active tickets (<?php echo $user_active_assigned_tickets ?>)</a>
                                    <a class="dropdown-item " href="?status=Closed&assigned=<?php echo $session_user_id ?>">Closed tickets</a>
                                </div>
                            </div>
                            <a href="?assigned=unassigned" class="btn btn-outline-danger">
                                <i class="fa fa-fw fa-exclamation-triangle mr-2"></i>Unassigned Tickets | <strong> <?php echo $total_tickets_unassigned; ?></strong>
                            </a>

                            <a href="recurring_tickets.php" class="btn btn-outline-info">
                                <i class="fa fa-fw fa-redo-alt mr-2"></i>Recurring Tickets | <strong> <?php echo $total_scheduled_tickets; ?></strong>
                            </a>

                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTicketModal">
                                        <i class="fas fa-fw fa-user-check mr-2"></i>Assign Tech
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditPriorityTicketModal">
                                        <i class="fas fa-fw fa-thermometer-half mr-2"></i>Update Priority
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkCloseTicketsModal">
                                        <i class="fas fa-fw fa-gavel mr-2"></i>Close
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkReplyTicketModal">
                                        <i class="fas fa-fw fa-paper-plane mr-2"></i>Bulk Update/Reply
                                    </a>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                <div class="collapse <?php if (!empty($_GET['dtf']) || (isset($_GET['canned_date']) && $_GET['canned_date'] !== "custom") || (isset($_GET['status']) && is_array($_GET['status']))) { echo "show"; } ?>" id="advancedFilter">
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
                                    <option value="New" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('New', $_GET['status'])) { echo 'selected'; } ?>>New</option>
                                    <option value="Open" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Open', $_GET['status'])) { echo 'selected'; } ?>>Open</option>
                                    <option value="On Hold" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('On Hold', $_GET['status'])) { echo 'selected'; } ?>>On Hold</option>
                                    <option value="Auto Close" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Auto Close', $_GET['status'])) { echo 'selected'; } ?>>Auto Close</option>
                                    <option value="Closed" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Closed', $_GET['status'])) { echo 'selected'; } ?>>Closed</option>
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
                                    $sql_assign_to = mysqli_query($mysqli, "SELECT * FROM users WHERE user_archived_at IS NULL ORDER BY user_name ASC");
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
            <hr>
            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if (!$num_rows[0]) { echo "d-none"; } ?>">
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_number&order=<?php echo $disp; ?>">Number</a>
                            </th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_subject&order=<?php echo $disp; ?>">Subject</a>
                            </th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">Client / Contact</a>
                            </th>
                            <?php if ($config_module_enable_accounting) { ?>
                                <th class="text-center"><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_billable&order=<?php echo $disp; ?>">Billable</a>
                                </th>
                            <?php } ?>

                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_priority&order=<?php echo $disp; ?>">Priority</a>
                            </th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status&order=<?php echo $disp; ?>">Status</a>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">Assigned</a>
                            </th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_updated_at&order=<?php echo $disp; ?>">Last Response</a>
                            </th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_created_at&order=<?php echo $disp; ?>">Created</a>
                            </th>

                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $ticket_id = intval($row['ticket_id']);
                            $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                            $ticket_number = intval($row['ticket_number']);
                            $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                            $ticket_priority = nullable_htmlentities($row['ticket_priority']);
                            $ticket_status = nullable_htmlentities($row['ticket_status']);
                            $ticket_billable = intval($row['ticket_billable']);
                            $ticket_scheduled_for = nullable_htmlentities($row['ticket_schedule']);
                            $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                            $ticket_created_at_time_ago = timeAgo($row['ticket_created_at']);
                            $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                            $ticket_updated_at_time_ago = timeAgo($row['ticket_updated_at']);
                            if (empty($ticket_updated_at)) {
                                if ($ticket_status == "Closed") {
                                    $ticket_updated_at_display = "<p>Never</p>";
                                } else {
                                    $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                }
                            } else {
                                $ticket_updated_at_display = "$ticket_updated_at_time_ago<br><small class='text-secondary'>$ticket_updated_at</small>";
                            }
                            $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);
                            $project_id = intval($row['ticket_project_id']);
                            $client_id = intval($row['ticket_client_id']);
                            $client_name = nullable_htmlentities($row['client_name']);
                            $contact_name = nullable_htmlentities($row['contact_name']);
                            $contact_email = nullable_htmlentities($row['contact_email']);

                            if ($ticket_status == "New") {
                                $ticket_status_color = "danger";
                            } elseif ($ticket_status == "Open") {
                                $ticket_status_color = "primary";
                            } elseif ($ticket_status == "On Hold") {
                                $ticket_status_color = "success";
                            } elseif ($ticket_status == "Auto Close") {
                                $ticket_status_color = "dark";
                            } elseif ($ticket_status == "Closed") {
                                $ticket_status_color = "dark";
                            }

                            if ($ticket_priority == "High") {
                                $ticket_priority_color = "danger";
                            } elseif ($ticket_priority == "Medium") {
                                $ticket_priority_color = "warning";
                            } else {
                                $ticket_priority_color = "info";
                            }

                            $ticket_assigned_to = intval($row['ticket_assigned_to']);
                            if (empty($ticket_assigned_to)) {
                                if ($ticket_status == "Closed") {
                                    $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                } else {
                                    $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                }
                            } else {
                                $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                            }

                            if (empty($contact_name)) {
                                $contact_display = "-";
                            } else {
                                $contact_display = "$contact_name<br><small class='text-secondary'>$contact_email</small>";
                            }

                            // Get who last updated the ticket - to be shown in the last Response column
                            $ticket_reply_type = "Client"; // Default to client for unreplied tickets
                            $ticket_reply_by_display = ""; // Default none
                            $sql_ticket_reply = mysqli_query($mysqli, "SELECT ticket_reply_type, contact_name, user_name FROM ticket_replies
                            LEFT JOIN users ON ticket_reply_by = user_id
                            LEFT JOIN contacts ON ticket_reply_by = contact_id
                            WHERE ticket_reply_ticket_id = $ticket_id
                            AND ticket_reply_archived_at IS NULL
                            ORDER BY ticket_reply_id DESC LIMIT 1"
                            );
                            $row = mysqli_fetch_array($sql_ticket_reply);

                            if ($row) {
                                $ticket_reply_type = nullable_htmlentities($row['ticket_reply_type']);
                                if ($ticket_reply_type == "Client") {
                                    $ticket_reply_by_display = nullable_htmlentities($row['contact_name']);
                                } else {
                                    $ticket_reply_by_display = nullable_htmlentities($row['user_name']);
                                }
                                $ticket_reply_created_at = nullable_htmlentities($row['ticket_reply_created_at']);
                            }

                            ?>

                            <tr class="<?php if(empty($ticket_updated_at)) { echo "text-bold"; }?> <?php if ($ticket_reply_type == "Client") { echo "table-warning"; } ?>">

                                <!-- Ticket Bulk Select -->
                                <td>
                                    <?php if ($ticket_status !== "Closed") { ?>
                                        <div class="form-check">
                                            <input class="form-check-input bulk-select" type="checkbox" name="ticket_ids[]" value="<?php echo $ticket_id ?>">
                                        </div>
                                    <?php } ?>
                                </td>

                                <!-- Ticket Number -->
                                <td>
                                    <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>">
                                        <span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span>
                                    </a>
                                </td>

                                <!-- Ticket Subject -->
                                <td>
                                    <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a>
                                </td>

                                <!-- Ticket Contact -->
                                <td>
                                    <a href="client_tickets.php?client_id=<?php echo $client_id; ?>"><strong><?php echo $client_name; ?></strong></a>

                                    <div class="mt-1"><?php echo $contact_display; ?></div>
                                </td>

                                <!-- Ticket Billable (if accounting enabled -->
                                <?php if ($config_module_enable_accounting) { ?>
                                    <td class="text-center">
                                        <a href="#" data-toggle="modal" data-target="#editTicketBillableModal<?php echo $ticket_id; ?>">
                                            <?php
                                            if ($ticket_billable == 1) {
                                                echo "<span class='badge badge-pill badge-success'>$</span>";
                                            } else {
                                                echo "<span class='badge badge-pill badge-secondary'>X</span>";
                                            }
                                            ?>
                                    </td>
                                <?php } ?>

                                <!-- Ticket Priority -->
                                <td>
                                    <a href="#" data-toggle="modal" data-target="#editTicketPriorityModal<?php echo $ticket_id; ?>"><span class='p-2 badge badge-pill badge-<?php echo $ticket_priority_color; ?>'><?php echo $ticket_priority; ?></span></a>
                                </td>

                                <!-- Ticket Status -->
                                <td>
                                    <span class='p-2 badge badge-pill badge-<?php echo $ticket_status_color; ?>'><?php echo $ticket_status; ?></span> <?php if ($ticket_status == 'On Hold' && isset ($ticket_scheduled_for)) { echo "<div class=\"mt-1\"> <small class='text-secondary'> $ticket_scheduled_for </small></div>"; } ?>
                                </td>

                                <!-- Ticket Assigned agent -->
                                <td>
                                    <a href="#" data-toggle="modal" data-target="#assignTicketModal<?php echo $ticket_id; ?>"><?php echo $ticket_assigned_to_display; ?></a>
                                </td>

                                <!-- Ticket Last Response -->
                                <td>
                                    <div><?php echo $ticket_updated_at_display; ?></div>
                                    <div><?php echo $ticket_reply_by_display; ?></div>
                                </td>

                                <!-- Ticket Created At -->
                                <td>
                                    <?php echo $ticket_created_at_time_ago; ?>
                                    <br>
                                    <small class="text-secondary"><?php echo $ticket_created_at; ?></small>
                                </td>

                            </tr>

                            <?php
                            // Edit actions, for open tickets
                            if ($ticket_status !== "Closed") {

                                require "ticket_assign_modal.php";

                                require "ticket_edit_priority_modal.php";

                                if ($config_module_enable_accounting) {
                                    require "ticket_edit_billable_modal.php";
                                }
                            }
                        }

                        ?>

                        </tbody>
                    </table>
                </div>
                <?php require_once "ticket_bulk_assign_modal.php"; ?>
                <?php require_once "ticket_bulk_edit_priority_modal.php"; ?>
                <?php require_once "ticket_bulk_close_modal.php"; ?>
                <?php require_once "ticket_bulk_reply_modal.php"; ?>
            </form>
            <?php require_once "pagination.php"; ?>
        </div>
    </div>

    <script src="js/bulk_actions.js"></script>

<?php
require_once "ticket_add_modal.php";

require_once "footer.php";
