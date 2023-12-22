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
        $ticket_assigned_filter = 'AND ticket_assigned_to = 0';
    } else {
        $ticket_assigned_filter = 'AND ticket_assigned_to = '.intval($_GET['assigned']);
    }
} else {
    // Default - any
    $ticket_assigned_filter = '';
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
    WHERE $ticket_status_snippet " . $ticket_assigned_filter . "
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
            <div class="float-left">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketModal">
                    <i class="fas fa-plus mr-2"></i>New Ticket
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Tickets">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse"
                                    data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group btn-group-lg float-right">
                        <button class="btn btn-outline-dark dropdown-toggle" style="border-top-right-radius: 0; border-bottom-right-radius: 0;" type="button" id="dropdownMenuButton"
                                data-toggle="dropdown">
                            <i class="fa fa-fw fa-envelope"></i> My Tickets
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="?status=Open&assigned=<?php echo $session_user_id ?>">Active tickets (<?php echo $user_active_assigned_tickets ?>)</a>
                            <a class="dropdown-item " href="?status=Closed&assigned=<?php echo $session_user_id ?>">Closed tickets</a>
                        </div>
                        <a href="?assigned=unassigned" class="btn btn-outline-danger"><i class="fa fa-fw fa-exclamation-triangle"></i>
                            Unassigned Tickets | <strong> <?php echo $total_tickets_unassigned; ?></strong></a>
                        <!--                            <a href="#" class="btn  btn-outline-info"><i class="fa fa-fw fa-cogs"></i> Tasks</a>-->
                    </div>
                </div>
            </div>

            <div class="collapse <?php if (!empty($_GET['dtf']) || (isset($_GET['canned_date']) && $_GET['canned_date'] !== "custom") || (isset($_GET['status']) && is_array($_GET['status']))) { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Canned Date</label>
                            <select class="form-control select2" name="canned_date">
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
                            <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Ticket Status</label>
                            <select class="form-control select2" name="status[]" data-placeholder = "Select Status" multiple>
                                <option value="In-Progress" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('In-Progress', $_GET['status'])) { echo 'selected'; } ?> >In-Progress</option>
                                <option value="Client-Replied" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Client-Replied', $_GET['status'])) { echo 'selected'; } ?> >Client-Replied</option>
                                <option value="Pending-Client" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Pending-Client', $_GET['status'])) { echo 'selected'; } ?> >Pending-Client</option>
                                <option value="Pending-Vendor" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Pending-Vendor', $_GET['status'])) { echo 'selected'; } ?> >Pending-Vendor</option>
                                <option value="Pending-Shipment" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Pending-Shipment', $_GET['status'])) { echo 'selected'; } ?> >Pending-Shipment</option>
                                <option value="Scheduled" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Scheduled', $_GET['status'])) { echo 'selected'; } ?> >Scheduled</option>
                                <option value="Closed" <?php if (isset($_GET['status']) && is_array($_GET['status']) && in_array('Closed', $_GET['status'])) { echo 'selected'; } ?> >Closed</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Assigned to</label>
                            <select class="form-control select2" name="assigned">
                                <option value="" <?php if ($ticket_assigned_filter == "") {echo "selected";}?> >Any</option>
                                <option value="unassigned"<?php if ($ticket_assigned_filter == "0") {echo "selected";}?> >Unassigned</option>

                                <?php
                                $sql_assign_to = mysqli_query($mysqli, "SELECT * FROM users WHERE user_archived_at IS NULL ORDER BY user_name ASC");
                                while ($row = mysqli_fetch_array($sql_assign_to)) {
                                    $user_id = intval($row['user_id']);
                                    $user_name = nullable_htmlentities($row['user_name']);
                                    ?>
                                    <option <?php if ($ticket_assigned_filter == $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
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
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) {
                    echo "d-none";
                } ?>">
                <tr>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_number&order=<?php echo $disp; ?>">Number</a>
                    </th>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_subject&order=<?php echo $disp; ?>">Subject</a>
                    </th>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">Client / Contact</a>
                    </th>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_billable&order=<?php echo $disp; ?>">Billable</a>
                    </th>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_priority&order=<?php echo $disp; ?>">Priority</a>
                    </th>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status&order=<?php echo $disp; ?>">Status</a>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">Assigned</a>
                    </th>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_updated_at&order=<?php echo $disp; ?>">Last Response</a>
                    </th>
                    <th><a class="text-dark"
                           href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_created_at&order=<?php echo $disp; ?>">Created</a>
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
                    $ticket_vendor_ticket_number = nullable_htmlentities($row['ticket_vendor_ticket_number']);
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
                    $client_id = intval($row['ticket_client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $contact_id = intval($row['ticket_contact_id']);
                    $contact_name = nullable_htmlentities($row['contact_name']);
                    $contact_title = nullable_htmlentities($row['contact_title']);
                    $contact_email = nullable_htmlentities($row['contact_email']);
                    $contact_phone = formatPhoneNumber($row['contact_phone']);
                    $contact_extension = nullable_htmlentities($row['contact_extension']);
                    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                    if ($ticket_status == "Pending-Assignment") {
                        $ticket_status_color = "danger";
                    } elseif ($ticket_status == "Assigned") {
                        $ticket_status_color = "primary";
                    } elseif ($ticket_status == "In-Progress") {
                        $ticket_status_color = "success";
                    } elseif ($ticket_status == "Closed") {
                        $ticket_status_color = "dark";
                    } elseif ($ticket_status == "Client-Replied") {
                        $ticket_status_color = "warning";
                    } else{
                        $ticket_status_color = "secondary";
                    }

                    if ($ticket_priority == "High") {
                        $ticket_priority_color = "danger";
                    } elseif ($ticket_priority == "Medium") {
                        $ticket_priority_color = "warning";
                    } else{
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

                    $asset_id = intval($row['ticket_asset_id']);
                    $vendor_id = intval($row['ticket_vendor_id']);

                    ?>

                    <tr class="<?php if(empty($ticket_updated_at)) { echo "text-bold"; }?>">
                        <td>
                            <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>">
                                <span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span>
                            </a>
                        </td>
                        <td>
                            <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a>
                        </td>
                        <td>
                            <a href="client_tickets.php?client_id=<?php echo $client_id; ?>"><strong><?php echo $client_name; ?></strong></a>

                            <div class="mt-1"><?php echo $contact_display; ?></div>
                        </td>
                        <td class="text-center">
                            <a href="#" data-toggle="modal" data-target="#editTicketBillableModal<?php echo $ticket_id; ?>">
                            <?php
                            if ($ticket_billable == 1) {
                                echo "<span class='badge badge-pill badge-success'>$</span>";
                            } else {
                                echo "<span class='badge badge-pill badge-secondary'>X</span>";
                            }
                        ?></td>
                        <td><a href="#" data-toggle="modal" data-target="#editTicketPriorityModal<?php echo $ticket_id; ?>"><span class='p-2 badge badge-pill badge-<?php echo $ticket_priority_color; ?>'><?php echo $ticket_priority; ?></span></a></td>
                        <td><span class='p-2 badge badge-pill badge-<?php echo $ticket_status_color; ?>'><?php echo $ticket_status; ?></span></td>
                        <td><a href="#" data-toggle="modal" data-target="#assignTicketModal<?php echo $ticket_id; ?>"><?php echo $ticket_assigned_to_display; ?></a></td>
                        <td><?php echo $ticket_updated_at_display; ?></td>
                        <td>
                            <?php echo $ticket_created_at_time_ago; ?>
                            <br>
                            <small class="text-secondary"><?php echo $ticket_created_at; ?></small>
                        </td>
                    </tr>

                    <?php

                    if ($ticket_status !== "Closed") {
                        // Temp performance boost for closed tickets, until we move to dynamic modals

                        require "ticket_assign_modal.php";

                        require "ticket_edit_priority_modal.php";

                        require "ticket_edit_billable_modal.php";

                    }

                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php";
        ?>
    </div>
</div>

<?php
require_once "ticket_add_modal.php";

require_once "footer.php";

?>
