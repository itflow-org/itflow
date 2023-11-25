<?php

// Default Column Sortby Filter
$sort = "ticket_number";
$order = "DESC";

require_once "inc_all_client.php";

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
    WHERE ticket_client_id = $client_id
    AND $ticket_status_snippet
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_status LIKE '%$q%' OR ticket_priority LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR ticket_vendor_ticket_number LIKE '%q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

//Get Total tickets open
$sql_total_tickets_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_open FROM tickets WHERE ticket_client_id = $client_id AND ticket_status != 'Closed'");
$row = mysqli_fetch_array($sql_total_tickets_open);
$total_tickets_open = intval($row['total_tickets_open']);

//Get Total tickets closed
$sql_total_tickets_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_closed FROM tickets WHERE ticket_client_id = $client_id AND ticket_status = 'Closed'");
$row = mysqli_fetch_array($sql_total_tickets_closed);
$total_tickets_closed = intval($row['total_tickets_closed']);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring mr-2"></i>Tickets
            <small class="ml-3">
                <a href="?client_id=<?php echo $client_id?>&status=Open" class="text-white"><strong><?php echo $total_tickets_open; ?></strong> Open</a> |
                <a href="?client_id=<?php echo $client_id?>&status=Closed" class="text-white"><strong><?php echo $total_tickets_closed; ?></strong> Closed</a>
            </small>
        </h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketModal">
                    <i class="fas fa-plus mr-2"></i>New Ticket
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportTicketModal">
                        <i class="fa fa-fw fa-download mr-2"></i>Export
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
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
                    <div class="float-right">
                    </div>
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_number&order=<?php echo $disp; ?>">Number</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_subject&order=<?php echo $disp; ?>">Subject</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=contact_name&order=<?php echo $disp; ?>">Contact</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_priority&order=<?php echo $disp; ?>">Priority</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status&order=<?php echo $disp; ?>">Status</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">Assigned</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_updated_at&order=<?php echo $disp; ?>">Last Response</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_created_at&order=<?php echo $disp; ?>">Created</a></th>

                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $ticket_id = intval($row['ticket_id']);
                    $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                    $ticket_number = nullable_htmlentities($row['ticket_number']);
                    $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                    $ticket_details = nullable_htmlentities($row['ticket_details']);
                    $ticket_priority = nullable_htmlentities($row['ticket_priority']);
                    $ticket_status = nullable_htmlentities($row['ticket_status']);
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

                    if ($ticket_status == "Pending-Assignment") {
                        $ticket_status_color = "danger";
                    } elseif ($ticket_status == "Assigned") {
                        $ticket_status_color = "primary";
                    } elseif ($ticket_status == "In-Progress") {
                        $ticket_status_color = "success";
                    } elseif ($ticket_status == "Closed") {
                        $ticket_status_color = "dark";
                    } else{
                        $ticket_status_color = "secondary";
                    }

                    if ($ticket_priority == "High") {
                        $ticket_priority_display = "<span class='p-2 badge badge-danger'>$ticket_priority</span>";
                    } elseif ($ticket_priority == "Medium") {
                        $ticket_priority_display = "<span class='p-2 badge badge-warning'>$ticket_priority</span>";
                    } elseif ($ticket_priority == "Low") {
                        $ticket_priority_display = "<span class='p-2 badge badge-info'>$ticket_priority</span>";
                    } else{
                        $ticket_priority_display = "-";
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
                    $contact_id = intval($row['ticket_contact_id']);
                    $contact_name = nullable_htmlentities($row['contact_name']);
                    $contact_title = nullable_htmlentities($row['contact_title']);
                    $contact_email = nullable_htmlentities($row['contact_email']);
                    $contact_phone = formatPhoneNumber($row['contact_phone']);
                    $contact_extension = nullable_htmlentities($row['contact_extension']);
                    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
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


                    $asset_id = intval($row['ticket_asset_id']);
                    $vendor_id = intval($row['ticket_vendor_id']);

                    ?>

                    <tr class="<?php if(empty($ticket_updated_at)) { echo "text-bold"; }?>">
                        <td><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a></td>
                        <td>
                            <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a>
                        </td>
                        <td><a href="#" data-toggle="modal" data-target="#editTicketContactModal<?php echo $ticket_id; ?>"><?php echo $contact_display; ?></a></td>
                        <td><a href="#" data-toggle="modal" data-target="#editTicketPriorityModal<?php echo $ticket_id; ?>"><?php echo $ticket_priority_display; ?></a></td>
                        <td><span class='p-2 badge badge-pill badge-<?php echo $ticket_status_color; ?>'><?php echo $ticket_status; ?></span></td>
                        <td><a href="#" data-toggle="modal" data-target="#assignTicketModal<?php echo $ticket_id; ?>"><?php echo $ticket_assigned_to_display; ?></a></td>
                        <td><?php echo $ticket_updated_at_display; ?></td>
                        <td>
                            <?php echo $ticket_created_at_time_ago; ?>
                            <br>
                            <small class="text-secondary"><?php echo $ticket_created_at; ?></small>
                        </td>
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
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket=<?php echo $ticket_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>

                    <?php

                    if ($ticket_status !== "Closed") {
                        // Temp performance boost for closed tickets, until we move to dynamic modals

                        require "ticket_edit_modal.php";

                        require "ticket_assign_modal.php";

                        require "ticket_edit_priority_modal.php";

                        require "ticket_edit_contact_modal.php";

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

require_once "client_ticket_export_modal.php";

require_once "footer.php";

?>
