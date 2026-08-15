<?php

// Default Column Sortby Filter
$sort = "ticket_status_order";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS ticket_status_active, ticket_status_color, ticket_status_id, ticket_status_name,
        ticket_status_pauses_sla FROM ticket_statuses
    WHERE ticket_status_name LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-info-circle me-2"></i>Tickets Statuses</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket_status/ticket_status_add.php"><i class="fas fa-plus me-2"></i>New Ticket Status</button>
        </div>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="col-sm-4 mb-2">
                <form autocomplete="off">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Ticket Statuses">
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
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
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_status_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'ticket_status_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_status_color&order=<?= $disp ?>">
                            Color <?php if ($sort == 'ticket_status_color') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_status_active&order=<?= $disp ?>">
                            Status <?php if ($sort == 'ticket_status_active') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>SLA</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $ticket_status_id = intval($row['ticket_status_id']);
                    $ticket_status_name = escapeHtml($row['ticket_status_name']);
                    $ticket_status_color = escapeHtml($row['ticket_status_color']);
                    $ticket_status_active = intval($row['ticket_status_active']);
                    $ticket_status_pauses_sla = intval($row['ticket_status_pauses_sla']);
                    if ($ticket_status_active) {
                        $ticket_status_display = "<div class='text-success text-bold'>Active</div>";
                    } else {
                        $ticket_status_display = "<div class='text-secondary'>Inactive</div>";
                    }

                    ?>
                    <tr>
                        <td>
                            <a href="#"
                                <?php if ( $ticket_status_id > 5 ) { ?>
                                class="ajax-modal" data-modal-url="modals/ticket_status/ticket_status_edit.php?id=<?= $ticket_status_id ?>"
                                <?php } ?>
                                >
                                <?= $ticket_status_name ?>
                            </a>
                        </td>
                        <td>
                            <span class='badge rounded-pill text-light p-2' style="background-color: <?= $ticket_status_color ?>"><?= $ticket_status_name ?></span>
                        <td><?= $ticket_status_display ?></td>
                        <td>
                            <?php if ($ticket_status_pauses_sla) { ?>
                                <span class="text-warning"><i class="fas fa-fw fa-pause-circle me-1"></i>Paused</span>
                            <?php } else { ?>
                                <span class="text-secondary">Running</span>
                            <?php } ?>
                            <?php if (!is_null(getTicketStatusSlaLock($ticket_status_id))) { ?>
                                <small class="text-muted ms-1" title="Built-in statuses have fixed SLA behaviour">(fixed)</small>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_status/ticket_status_edit.php?id=<?= $ticket_status_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <?php if (!$ticket_status_active) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket_status=<?= $ticket_status_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>
</div>

<?php
require_once "../includes/footer.php";
