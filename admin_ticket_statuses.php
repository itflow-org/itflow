<?php

// Default Column Sortby Filter
$sort = "ticket_status_name";
$order = "ASC";

require_once "inc_all_admin.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM ticket_statuses
    WHERE ticket_status_name LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-info-circle mr-2"></i>Tickets Statuses</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketStatusModal"><i class="fas fa-plus mr-2"></i>New Ticket Status</button>
            </div>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-sm-4 mb-2">
                    <form autocomplete="off">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Ticket Statuses">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-sm-8">
                </div>
            </div>

            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status_name&order=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status_color&order=<?php echo $disp; ?>">Color</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status_arctive&order=<?php echo $disp; ?>">Status</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $ticket_status_id = intval($row['ticket_status_id']);
                        $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                        $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);
                        $ticket_status_active = intval($row['ticket_status_active']);
                        if ($ticket_status_active) {
                            $ticket_status_display = "<div class='text-success text-bold'>Active</div>";
                        } else {
                            $ticket_status_display = "<div class='text-secondary'>Disabled</div>";
                        }

                        ?>
                        <tr>
                            <td>
                                <a href="#" data-toggle="modal" data-target="#editTicketStatusModal<?php echo $ticket_status_id; ?>">
                                    <?php echo $ticket_status_name; ?>
                                </a>
                            </td>
                            <td>
                                <span class='badge badge-pill text-light p-2' style="background-color: <?php echo $ticket_status_color; ?>"><?php echo $ticket_status_name; ?></span>
                            <td><?php echo $ticket_status_display; ?></td>
                            <td>
                                <?php if ( $ticket_status_id > 5 ) { ?>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketStatusModal<?php echo $ticket_status_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket_status=<?php echo $ticket_status_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                                <?php } ?>
                            </td>
                        </tr>

                        <?php
                        if ( $ticket_status_id > 5 ) {
                            require "admin_ticket_status_edit_modal.php";
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
require_once "admin_ticket_status_add_modal.php";

require_once "footer.php";

