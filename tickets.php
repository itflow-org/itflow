<?php

// Default Column Sortby Filter
$sb = "ticket_number";
$o = "DESC";

require_once("inc_all.php");

//Initialize the HTML Purifier to prevent XSS
require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

// Ticket status from GET
if (!isset($_GET['status'])) {
    // If nothing is set, assume we only want to see open tickets
    $status = 'Open';
    $ticket_status_snippet = "ticket_status != 'Closed'";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Open') {
    $status = 'Open';
    $ticket_status_snippet = "ticket_status != 'Closed'";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Closed') {
    $status = 'Closed';
    $ticket_status_snippet = "ticket_status = 'Closed'";
} else {
    $status = '%';
    $ticket_status_snippet = "ticket_status LIKE '%'";
}

// Ticket assignment status filter
if (isset($_GET['assigned']) & !empty($_GET['assigned'])) {
    if ($_GET['assigned'] == 'unassigned') {
        $ticket_assigned_filter = '0';
    } else {
        $ticket_assigned_filter = intval($_GET['assigned']);
    }
} else {
    // Default - any
    $ticket_assigned_filter = '';
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o, 'status' => $status, 'assigned' => $ticket_assigned_filter)));

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
    WHERE ticket_assigned_to LIKE '%$ticket_assigned_filter%'
    AND $ticket_status_snippet
    AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR user_name LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to"
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
    <div class="card card-dark elevation-3">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring mr-2"></i>Tickets
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(htmlentities($q)); } ?>" placeholder="Search Tickets">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse"
                                        data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="btn-group btn-group-lg float-right">
                            <button class="btn btn-outline-dark dropdown-toggle" type="button" id="dropdownMenuButton"
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

                <div class="collapse <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
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
                                <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo htmlentities($dtt); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Ticket Status</label>
                                <select class="form-control select2" name="status">
                                    <option value="%" <?php if ($status == "%") {echo "selected";}?> >Any</option>
                                    <option value="Open" <?php if ($status == "Open") {echo "selected";}?> >Open</option>
                                    <option value="Closed" <?php if ($status == "Closed") {echo "selected";}?> >Closed</option>
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
                                        $user_name = htmlentities($row['user_name']);
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
                               href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_number&o=<?php echo $disp; ?>">Number</a>
                        </th>
                        <th><a class="text-dark"
                               href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_subject&o=<?php echo $disp; ?>">Subject</a>
                        </th>
                        <th><a class="text-dark"
                               href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client / Contact</a>
                        </th>
                        <th><a class="text-dark"
                               href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_priority&o=<?php echo $disp; ?>">Priority</a>
                        </th>
                        <th><a class="text-dark"
                               href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_status&o=<?php echo $disp; ?>">Status</a>
                        <th><a class="text-dark"
                               href="?<?php echo $url_query_strings_sb; ?>&sb=user_name&o=<?php echo $disp; ?>">Assigned</a>
                        </th>
                        <th><a class="text-dark"
                               href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_updated_at&o=<?php echo $disp; ?>">Last Response</a>
                        </th>
                        <th><a class="text-dark"
                               href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_created_at&o=<?php echo $disp; ?>">Created</a>
                        </th>

                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $ticket_id = intval($row['ticket_id']);
                        $ticket_prefix = htmlentities($row['ticket_prefix']);
                        $ticket_number = intval($row['ticket_number']);
                        $ticket_subject = htmlentities($row['ticket_subject']);
                        $ticket_details = $purifier->purify($row['ticket_details']);
                        //$ticket_details = htmlentities($row['ticket_details']);
                        //$ticket_details = $row['ticket_details'];
                        $ticket_priority = htmlentities($row['ticket_priority']);
                        $ticket_status = htmlentities($row['ticket_status']);
                        $ticket_created_at = htmlentities($row['ticket_created_at']);
                        $ticket_updated_at = htmlentities($row['ticket_updated_at']);
                        if (empty($ticket_updated_at)) {
                            if ($ticket_status == "Closed") {
                                $ticket_updated_at_display = "<p>Never</p>";
                            } else {
                                $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                            }
                        } else {
                            $ticket_updated_at_display = $ticket_updated_at;
                        }
                        $ticket_closed_at = htmlentities($row['ticket_closed_at']);
                        $client_id = intval($row['client_id']);
                        $client_name = htmlentities($row['client_name']);
                        $contact_id = intval($row['contact_id']);
                        $contact_name = htmlentities($row['contact_name']);
                        $contact_title = htmlentities($row['contact_title']);
                        $contact_email = htmlentities($row['contact_email']);
                        $contact_phone = formatPhoneNumber($row['contact_phone']);
                        $contact_extension = htmlentities($row['contact_extension']);
                        $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                        if ($ticket_status == "Open") {
                            $ticket_status_color = "primary";
                        } elseif ($ticket_status == "Working") {
                            $ticket_status_color = "success";
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
                            $ticket_assigned_to_display = htmlentities($row['user_name']);
                        }

                        if (empty($contact_name)) {
                            $contact_display = "-";
                        } else {
                            $contact_display = "$contact_name<br><small class='text-secondary'>$contact_email</small>";
                        }

                        $asset_id = intval($row['asset_id']);
                        $vendor_id = intval($row['vendor_id']);

                        ?>

                        <tr>
                            <td>
                                <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>">
                                    <span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span>
                                </a>
                            </td>
                            <td>
                                <strong><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a></strong>
                            </td>
                            <td>
                                <strong><a href="client_tickets.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></strong>
                                <br>
                                <?php echo $contact_display; ?>
                            </td>
                            <td><span class='p-2 badge badge-pill badge-<?php echo $ticket_priority_color; ?>'><?php echo $ticket_priority; ?></td>
                            <td><span class='p-2 badge badge-pill badge-<?php echo $ticket_status_color; ?>'><?php echo $ticket_status; ?></span></td>
                            <td><?php echo $ticket_assigned_to_display; ?></td>
                            <td><?php echo $ticket_updated_at_display; ?></td>
                            <td><?php echo $ticket_created_at; ?></td>
                            <td>
                                <?php if ($ticket_status !== "Closed") { ?>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketModal<?php echo $ticket_id; ?>">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <?php if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold" href="post.php?delete_ticket=<?php echo $ticket_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php }

                                require("ticket_edit_modal.php");

                                ?>
                            </td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("ticket_add_modal.php");
require_once("footer.php");
