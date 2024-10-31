<?php


// Default Column Sortby Filter
$sort = "ticket_number";
$order = "DESC";

require_once "inc_all_client.php";

// Perms
enforceUserPermission('module_support');

if (isset($_GET['status']) && ($_GET['status']) == 'Closed') {
    $status = 'Closed';
    $ticket_status_snippet = "ticket_resolved_at IS NOT NULL";
} else {
    // Default - Show open tickets
    $status = 'Open';
    $ticket_status_snippet = "ticket_resolved_at IS NULL";
}

if (isset($_GET['billable']) && ($_GET['billable']) == '1') {
    if (isset($_GET['unbilled'])) {
        $billable = 1;
        $ticket_billable_snippet = "ticket_billable = 1 AND ticket_invoice_id = 0";
        $ticket_status_snippet = '1 = 1';
    }
} else {
    $billable = 0;
    $ticket_billable_snippet = '1 = 1';
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM tickets
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    LEFT JOIN locations ON ticket_location_id = location_id
    LEFT JOIN vendors ON ticket_vendor_id = vendor_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE ticket_client_id = $client_id
    AND $ticket_status_snippet
    AND $ticket_billable_snippet
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_status_name LIKE '%$q%' OR ticket_priority LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR ticket_vendor_ticket_number LIKE '%q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Get Total tickets open
$sql_total_tickets_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_open FROM tickets WHERE ticket_client_id = $client_id AND ticket_resolved_at IS NULL");
$row = mysqli_fetch_array($sql_total_tickets_open);
$total_tickets_open = intval($row['total_tickets_open']);

// Get Total tickets closed
$sql_total_tickets_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_closed FROM tickets WHERE ticket_client_id = $client_id AND ticket_resolved_at IS NOT NULL");
$row = mysqli_fetch_array($sql_total_tickets_closed);
$total_tickets_closed = intval($row['total_tickets_closed']);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring mr-2"></i><?php if (isset($_GET['unbilled'])) { echo "Unbilled "; } ?> Tickets
            <small class="ml-3">
                <a href="?client_id=<?php echo $client_id?>&status=Open" class="text-light"><strong><?php echo $total_tickets_open; ?></strong> Open</a> |
                <a href="?client_id=<?php echo $client_id?>&status=Closed" class="text-light"><strong><?php echo $total_tickets_closed; ?></strong> Closed</a>
            </small>
        </h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketModal">
                    <i class="fas fa-plus mr-2"></i>New Ticket
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
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <input type="hidden" name="status" value="<?php echo $status; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Tickets">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_number&order=<?php echo $disp; ?>">
                                Number <?php if ($sort == 'ticket_number') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_subject&order=<?php echo $disp; ?>">
                                Subject <?php if ($sort == 'ticket_subject') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=contact_name&order=<?php echo $disp; ?>">
                                Contact <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 2) { ?>
                            <th class="text-center">
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_billable&order=<?php echo $disp; ?>">
                                    Billable <?php if ($sort == 'ticket_billable') { echo $order_icon; } ?>
                                </a>
                            </th>
                        <?php } ?>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_priority&order=<?php echo $disp; ?>">
                                Priority <?php if ($sort == 'ticket_priority') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status&order=<?php echo $disp; ?>">
                                Status <?php if ($sort == 'ticket_status') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                                Assigned <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_updated_at&order=<?php echo $disp; ?>">
                                Last Response <?php if ($sort == 'ticket_updated_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_created_at&order=<?php echo $disp; ?>">
                                Created <?php if ($sort == 'ticket_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $ticket_id = intval($row['ticket_id']);
                    $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                    $ticket_number = nullable_htmlentities($row['ticket_number']);
                    $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                    $ticket_priority = nullable_htmlentities($row['ticket_priority']);
                    $ticket_status_id = intval($row['ticket_status_id']);
                    $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                    $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);
                    $ticket_billable = intval($row['ticket_billable']);
                    $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                    $ticket_created_at_time_ago = timeAgo($row['ticket_created_at']);
                    $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                    $ticket_updated_at_time_ago = timeAgo($row['ticket_updated_at']);
                    if (empty($ticket_updated_at)) {
                        if (!empty($ticket_closed_at)) {
                            $ticket_updated_at_display = "<p>Never</p>";
                        } else {
                            $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                        }
                    } else {
                        $ticket_updated_at_display = "$ticket_updated_at_time_ago<br><small class='text-secondary'>$ticket_updated_at</small>";
                    }
                    $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);

                    if ($ticket_priority == "High") {
                        $ticket_priority_display = "<span class='p-2 badge badge-pill badge-danger'>$ticket_priority</span>";
                    } elseif ($ticket_priority == "Medium") {
                        $ticket_priority_display = "<span class='p-2 badge badge-pill badge-warning'>$ticket_priority</span>";
                    } elseif ($ticket_priority == "Low") {
                        $ticket_priority_display = "<span class='p-2 badge badge-pill badge-info'>$ticket_priority</span>";
                    } else{
                        $ticket_priority_display = "-";
                    }

                    $ticket_assigned_to = intval($row['ticket_assigned_to']);
                    if (empty($ticket_assigned_to)) {
                        if (!empty($ticket_closed_at)) {
                            $ticket_assigned_to_display = "<p>Not Assigned</p>";
                        } else {
                            $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                        }
                    } else {
                        $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                    }

                    $project_id = intval($row['ticket_project_id']);

                    $contact_name = nullable_htmlentities($row['contact_name']);
                    $contact_email = nullable_htmlentities($row['contact_email']);
                    $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                    if (empty($contact_archived_at)) {
                        $contact_archived_display = "";
                    } else {
                        $contact_archived_display = "Archived - ";
                    }
                    if (empty($contact_name)) {
                        $contact_display = "-";
                    } else {
                        $contact_display = "$contact_archived_display$contact_name<br><small class='text-secondary'>$contact_email</small>";
                    }

                    // Get who last updated the ticket - to be shown in the last Response column
                    $ticket_reply_type = "Client"; // Default to client for unreplied tickets
                    $ticket_reply_by_display = ""; // Default none
                    $sql_ticket_reply = mysqli_query($mysqli, "SELECT ticket_reply_type, ticket_reply_created_at, contact_name, user_name FROM ticket_replies
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
                        $ticket_reply_created_at_time_ago = timeAgo($ticket_reply_created_at);
                    }

                    ?>

                    <tr class="<?php if(empty($ticket_reply_created_at)) { echo "text-bold"; }?> <?php if (empty($ticket_closed_at) && $ticket_reply_type == "Client") { echo "table-warning"; } ?>">

                        <!-- Ticket Number -->
                        <td>
                            <a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a>
                        </td>

                        <!-- Ticket Subject -->
                        <td>
                            <a href="ticket.php?client_id=<?php echo $client_id; ?>&ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a>
                        </td>

                        <!-- Ticket Contact -->
                        <td>
                            <a href="#" data-toggle="modal" data-target="#editTicketContactModal<?php echo $ticket_id; ?>"><?php echo $contact_display; ?></a>
                        </td>

                        <!-- Ticket Billable (if accounting perms & enabled) -->
                        <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 2) { ?>
                            <td class="text-center">
                                <a href="#" data-toggle="modal" data-target="#editTicketBillableModal<?php echo $ticket_id; ?>">
                                    <?php
                                    if ($ticket_billable == 1) {
                                        echo "<span class='badge badge-pill badge-success p-2'>Yes</span>";
                                    } else {
                                        echo "<span class='badge badge-pill badge-secondary p-2'>No</span>";
                                    }
                                    ?>
                            </td>
                        <?php } ?>

                        <!-- Ticket Priority -->
                        <td>
                            <a href="#" data-toggle="modal" data-target="#editTicketPriorityModal<?php echo $ticket_id; ?>"><?php echo $ticket_priority_display; ?></a>
                        </td>

                        <!-- Ticket Status -->
                        <td>
                            <span class='badge badge-pill text-light p-2' style="background-color: <?php echo $ticket_status_color; ?>"><?php echo $ticket_status_name; ?></span>
                        </td>

                        <!-- Ticket Assigned agent -->
                        <td>
                            <a href="#" data-toggle="modal" data-target="#assignTicketModal<?php echo $ticket_id; ?>"><?php echo $ticket_assigned_to_display; ?></a>
                        </td>

                        <!-- Ticket Last Response -->
                        <td>
                            <?php if (!empty($ticket_reply_created_at)) { ?>
                                <div title="<?php echo $ticket_reply_created_at; ?>"><?php echo $ticket_reply_created_at_time_ago; ?></div>
                                <div><?php echo $ticket_reply_by_display; ?></div>
                            <?php } ?>
                        </td>

                        <!-- Ticket Created At -->
                        <td title="<?php echo $ticket_created_at; ?>">
                            <?php echo $ticket_created_at_time_ago; ?>
                        </td>

                    </tr>

                    <?php
                    // Edit actions, for open tickets
                    if (empty($ticket_closed_at)) {

                        require "ticket_assign_modal.php";

                        require "ticket_edit_priority_modal.php";

                        require "ticket_edit_contact_modal.php";

                        if ($config_module_enable_accounting) {
                            require "ticket_edit_billable_modal.php";
                        }

                    }

                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php"; ?>
    </div>
</div>

<?php
require_once "ticket_add_modal.php";

require_once "client_ticket_export_modal.php";

require_once "footer.php";

