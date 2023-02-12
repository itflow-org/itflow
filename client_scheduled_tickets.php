<?php

require_once("inc_all_client.php");

if (!empty($_GET['sb'])) {
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
    $sb = "scheduled_ticket_subject";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

// SQL
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM scheduled_tickets
    LEFT JOIN clients on scheduled_ticket_client_id = client_id
    WHERE scheduled_ticket_client_id = $client_id
    AND scheduled_tickets.scheduled_ticket_subject LIKE '%$q%'
    ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>
    <script src="js/scheduled_tickets_edit_modal.js"></script>

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-sync"></i> Scheduled Tickets</h3>
            <button type="button" class="btn btn-dark dropdown-toggle ml-1" data-toggle="dropdown"></button>
            <div class="dropdown-menu">
                <a class="dropdown-item text-dark" href="client_tickets.php?client_id=<?php echo $client_id; ?>">Tickets</a>
            </div>
        </div>

        <div class="card-body">

            <form autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Scheduled Tickets">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
            <hr>

            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="<?php if ($num_rows[0] == 0) {
                        echo "d-none";
                    } ?>">
                    <tr>
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
                        $scheduled_ticket_id = $row['scheduled_ticket_id'];
                        $scheduled_ticket_subject = htmlentities($row['scheduled_ticket_subject']);
                        $scheduled_ticket_priority = htmlentities($row['scheduled_ticket_priority']);
                        $scheduled_ticket_frequency = htmlentities($row['scheduled_ticket_frequency']);
                        $scheduled_ticket_next_run = $row['scheduled_ticket_next_run'];
                        ?>

                        <tr>
                            <td><a href="#" data-toggle="modal" data-target="#editScheduledTicketModal" onclick="populateScheduledTicketEditModal(<?php echo $client_id, ',', $scheduled_ticket_id ?>)"> <?php echo $scheduled_ticket_subject ?> </a></td>
                            <td><a> <?php echo $scheduled_ticket_priority ?></a></td>
                            <td><a> <?php echo $scheduled_ticket_frequency ?></a></td>
                            <td><a> <?php echo $scheduled_ticket_next_run ?></a></td>

                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal"
                                           data-target="#editScheduledTicketModal" onclick="populateScheduledTicketEditModal(<?php echo $client_id, ',', $scheduled_ticket_id ?>)">Edit</a>
                                        <?php
                                        if ($session_user_role == 3) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="post.php?delete_scheduled_ticket=<?php echo $scheduled_ticket_id; ?>">Delete</a>
                                    </div>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>
                </table>
            </div>
            <?php
            require_once('pagination.php');
            require_once("scheduled_ticket_edit_modal.php")
            ?>
        </div>
    </div>

<?php
require_once("footer.php");
