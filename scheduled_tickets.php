<?php

// Default Column Sortby Filter
$sb = "scheduled_ticket_subject";
$o = "ASC";

require_once("inc_all.php");

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

// SQL
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM scheduled_tickets
    LEFT JOIN clients on scheduled_ticket_client_id = client_id
    WHERE scheduled_tickets.scheduled_ticket_subject LIKE '%$q%'
    ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-clock mr-2"></i>Scheduled Tickets</h3>
            <div class='card-tools'>
                <div class="float-left">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addScheduledTicketModal">
                        <i class="fas fa-plus mr-2"></i>New Scheduled Ticket
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <form autocomplete="off">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(htmlentities($q));} ?>" placeholder="Search Scheduled Tickets">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
            <hr>

            <div class="table-responsive">

                <form id="bulk_actions" action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                    <table class="table table-striped table-borderless table-hover">
                        <thead class="<?php if ($num_rows[0] == 0) {
                            echo "d-none";
                        } ?>">
                        <tr>
                            <th><a class="text-dark">Select</a></th>
                            <th><a class="text-dark">Client</a></th>
                            <th><a class="text-dark">Subject</a></th>
                            <th><a class="text-dark">Priority</a></th>
                            <th><a class="text-dark">Frequency</a></th>
                            <th><a class="text-dark">Next Run Date</a></th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $scheduled_ticket_id = intval($row['scheduled_ticket_id']);
                            $scheduled_ticket_client_id = intval($row['client_id']);
                            $scheduled_ticket_subject = htmlentities($row['scheduled_ticket_subject']);
                            $scheduled_ticket_priority = htmlentities($row['scheduled_ticket_priority']);
                            $scheduled_ticket_frequency = htmlentities($row['scheduled_ticket_frequency']);
                            $scheduled_ticket_next_run = htmlentities($row['scheduled_ticket_next_run']);
                            $scheduled_ticket_client_name = htmlentities($row['client_name']);
                            ?>

                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="scheduled_ticket_ids[]" onchange="showBulkDeleteButton()" value="<?php echo $scheduled_ticket_id ?>">
                                    </div>
                                </td>

                                <td class="text-bold"><a href="client_scheduled_tickets.php?client_id=<?php echo $scheduled_ticket_client_id; ?>"><?php echo $scheduled_ticket_client_name ?></a></td>

                                <td>
                                    <a href="#" data-toggle="modal" data-target="#editScheduledTicketModal"
                                       onclick="populateScheduledTicketEditModal(<?php echo $scheduled_ticket_client_id, ",", $scheduled_ticket_id ?>)"> <?php echo $scheduled_ticket_subject ?>
                                    </a>
                                </td>

                                <td><?php echo $scheduled_ticket_priority ?></td>

                                <td><?php echo $scheduled_ticket_frequency ?></td>

                                <td class="text-bold"><?php echo $scheduled_ticket_next_run ?></td>

                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#" data-toggle="modal"
                                               data-target="#editScheduledTicketModal" onclick="populateScheduledTicketEditModal(<?php echo $scheduled_ticket_client_id, ",", $scheduled_ticket_id ?>)">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <?php
                                            if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold" href="post.php?delete_scheduled_ticket=<?php echo $scheduled_ticket_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </form>

            </div>

            <div class="form-check">
                <input type="submit" id="button_bulk_delete" form="bulk_actions" name="bulk_delete_scheduled_tickets" value="Bulk Delete" hidden>
            </div>

            <?php require_once('pagination.php'); ?>

        </div>
    </div>

    <script src="js/scheduled_tickets_edit_modal.js"></script>
    <script src="js/scheduled_tickets_bulk_delete_button.js"></script>

<?php
require_once("scheduled_ticket_add_modal.php");
require_once("scheduled_ticket_edit_modal.php");
require_once("footer.php");
