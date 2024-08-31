<?php

// Default Column Sortby Filter
$sort = "scheduled_ticket_subject";
$order = "ASC";

require_once "inc_all.php";

// Ticket client access snippet
$rec_ticket_permission_snippet = '';
if (!empty($client_access_string)) {
    $rec_ticket_permission_snippet = "AND scheduled_ticket_client_id IN ($client_access_string)";
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

// SQL
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM scheduled_tickets
    LEFT JOIN clients on scheduled_ticket_client_id = client_id
    WHERE scheduled_tickets.scheduled_ticket_subject LIKE '%$q%'
    $rec_ticket_permission_snippet
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-redo-alt mr-2"></i>Recurring Tickets</h3>
        <div class='card-tools'>
            <div class="float-left">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringTicketModal">
                    <i class="fas fa-plus mr-2"></i>New Recurring Ticket
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">

        <form autocomplete="off">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {
                                                                                        echo stripslashes(nullable_htmlentities($q));
                                                                                    } ?>" placeholder="Search Recurring Tickets">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">

                    <div class="dropdown float-right" id="bulkActionButton" hidden>
                        <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                            <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                        </button>
                        <div class="dropdown-menu">
                            <button class="dropdown-item text-danger text-bold" type="submit" form="bulkActions" name="bulk_delete_recurring_tickets">
                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </form>
        <hr>

        <div class="table-responsive-sm">

            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <table class="table table-striped table-borderless table-hover">
                    <thead class="<?php if (!$num_rows[0]) {
                                        echo "d-none";
                                    } ?>">
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
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
                            $scheduled_ticket_subject = nullable_htmlentities($row['scheduled_ticket_subject']);
                            $scheduled_ticket_priority = nullable_htmlentities($row['scheduled_ticket_priority']);
                            $scheduled_ticket_frequency = nullable_htmlentities($row['scheduled_ticket_frequency']);
                            $scheduled_ticket_next_run = nullable_htmlentities($row['scheduled_ticket_next_run']);
                            $scheduled_ticket_client_name = nullable_htmlentities($row['client_name']);
                        ?>

                            <tr>
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="scheduled_ticket_ids[]" value="<?php echo $scheduled_ticket_id ?>">
                                    </div>
                                </td>

                                <th><a href="client_recurring_tickets.php?client_id=<?php echo $scheduled_ticket_client_id; ?>"><?php echo $scheduled_ticket_client_name ?></a>
                                </th>

                                <td>
                                    <a href="#" data-toggle="modal" data-target="#editRecurringTicketModal" onclick="populateRecurringTicketEditModal(<?php echo $scheduled_ticket_client_id, ",", $scheduled_ticket_id ?>)"> <?php echo $scheduled_ticket_subject ?>
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
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRecurringTicketModal" onclick="populateRecurringTicketEditModal(<?php echo $scheduled_ticket_client_id, ",", $scheduled_ticket_id ?>)">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <?php
                                            if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?php echo $scheduled_ticket_id; ?>">
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

        <?php require_once 'pagination.php';
        ?>

    </div>
</div>

<script src="js/recurring_tickets_edit_modal.js"></script>
<script src="js/bulk_actions.js"></script>

<?php
require_once "recurring_ticket_add_modal.php";

require_once "recurring_ticket_edit_modal.php";

require_once "footer.php";
