<?php

// Default Column Sortby Filter
$sort = "recurring_ticket_subject";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND recurring_ticket_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

// Ticket client access snippet
$rec_ticket_permission_snippet = '';
if (!empty($client_access_string)) {
    $rec_ticket_permission_snippet = "AND recurring_ticket_client_id IN ($client_access_string)";
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

// SQL
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM recurring_tickets
    LEFT JOIN clients on recurring_ticket_client_id = client_id
    WHERE recurring_tickets.recurring_ticket_subject LIKE '%$q%'
    $rec_ticket_permission_snippet
    $client_query
    ORDER BY
        CASE 
            WHEN '$sort' = 'recurring_ticket_priority' THEN
                CASE recurring_ticket_priority
                    WHEN 'High' THEN 1
                    WHEN 'Medium' THEN 2
                    WHEN 'Low' THEN 3
                    ELSE 4  -- Optional: for unexpected priority values
                END
            ELSE NULL
        END $order, 
        $sort $order  -- Apply normal sorting by $sort and $order
    LIMIT $record_from, $record_to"
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
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Recurring Tickets">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">

                    <?php if (lookupUserPermission("module_support") >= 2) { ?>
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
                    <?php } ?>

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
                            <?php if (!$client_url) { ?>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php } ?>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_ticket_subject&order=<?php echo $disp; ?>">
                                    Subject <?php if ($sort == 'recurring_ticket_subject') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_ticket_priority&order=<?php echo $disp; ?>">
                                    Priority <?php if ($sort == 'recurring_ticket_priority') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_ticket_frequency&order=<?php echo $disp; ?>">
                                    Frequency <?php if ($sort == 'recurring_ticket_frequency') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_ticket_next_run&order=<?php echo $disp; ?>">
                                    Next Run Date <?php if ($sort == 'recurring_ticket_next_run') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php if (lookupUserPermission("module_support") >= 2) { ?>
                                <th class="text-center">Action</th>
                            <?php } ?>
                        </tr>
                    </thead>

                    <tbody>

                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $recurring_ticket_id = intval($row['recurring_ticket_id']);
                            $recurring_ticket_client_id = intval($row['client_id']);
                            $recurring_ticket_subject = nullable_htmlentities($row['recurring_ticket_subject']);
                            $recurring_ticket_priority = nullable_htmlentities($row['recurring_ticket_priority']);
                            $recurring_ticket_frequency = nullable_htmlentities($row['recurring_ticket_frequency']);
                            $recurring_ticket_next_run = nullable_htmlentities($row['recurring_ticket_next_run']);
                            $recurring_ticket_client_name = nullable_htmlentities($row['client_name']);
                        ?>

                            <tr>
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="recurring_ticket_ids[]" value="<?php echo $recurring_ticket_id ?>">
                                    </div>
                                </td>

                                <?php if (!$client_url) { ?>
                                <th><a href="recurring_tickets.php?client_id=<?php echo $recurring_ticket_client_id; ?>"><?php echo $recurring_ticket_client_name ?></a>
                                </th>
                                <?php } ?>

                                <td>
                                    <a href="#" 
                                        data-toggle="ajax-modal"
                                        data-modal-size="lg"
                                        data-ajax-url="ajax/ajax_recurring_ticket_edit.php"
                                        data-ajax-id="<?php echo $recurring_ticket_id; ?>"
                                        >
                                        <?php echo $recurring_ticket_subject ?>
                                    </a>
                                </td>

                                <td><?php echo $recurring_ticket_priority ?></td>

                                <td><?php echo $recurring_ticket_frequency ?></td>

                                <td class="text-bold"><?php echo $recurring_ticket_next_run ?></td>

                                <?php if (lookupUserPermission("module_support") >= 2) { ?>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-modal-size="lg"
                                                    data-ajax-url="ajax/ajax_recurring_ticket_edit.php"
                                                    data-ajax-id="<?php echo $recurring_ticket_id; ?>"
                                                    >
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="post.php?force_recurring_ticket=<?php echo $recurring_ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                    <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Force Reoccur
                                                </a>
                                                <?php if (lookupUserPermission("module_support") == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?php echo $recurring_ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                <?php } ?>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </form>

        </div>

        <?php require_once 'includes/filter_footer.php';
        ?>

    </div>
</div>

<script src="js/bulk_actions.js"></script>

<?php
require_once "modals/recurring_ticket_add_modal.php";
require_once "includes/footer.php";
