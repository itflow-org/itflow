<?php

// Default Column Sortby Filter
$sort = "recurring_ticket_next_run";
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

// Category Filter
if (isset($_GET['category']) & !empty($_GET['category'])) {
    $category_query = 'AND (category_id = ' . intval($_GET['category']) . ')';
    $category_filter = intval($_GET['category']);
} else {
    // Default - any
    $category_query = '';
    $category_filter = '';
}

// Assigned Agent Filter
if (isset($_GET['assigned_agent']) & !empty($_GET['assigned_agent'])) {
    $assigned_agent_query = 'AND (user_id = ' . intval($_GET['assigned_agent']) . ')';
    $assigned_agent_filter = intval($_GET['assigned_agent']);
} else {
    // Default - any
    $assigned_agent_query = '';
    $assigned_agent_filter = '';
}

// Billable Filter
if (isset($_GET['billable']) && $_GET['billable'] == 1) {
    $billable_query = 'AND (recurring_ticket_billable = 1)';
    $billable_filter = 1;
} elseif (isset($_GET['billable']) && $_GET['billable'] == 0) {
    $billable_query = 'AND (recurring_ticket_billable = 0)';
    $billable_filter = 0;
} else {
    // Default - any
    $billable_query = '';
    $billable_filter = '';
}

// SQL
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM recurring_tickets
    LEFT JOIN clients ON recurring_ticket_client_id = client_id
    LEFT JOIN categories ON category_id = recurring_ticket_category
    LEFT JOIN users ON user_id = recurring_ticket_assigned_to
    WHERE (recurring_tickets.recurring_ticket_subject LIKE '%$q%' OR category_name LIKE '%$q%')
    $access_permission_query
    $category_query
    $assigned_agent_query
    $billable_query
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
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>" data-modal-size="lg">
                    <i class="fas fa-plus"></i><span class="d-none d-lg-inline ml-2">New Recurring Ticket</span>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">

        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
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
                <div class="col-sm-2">
                    <div class="form-group">
                        <select class="form-control select2" name="category" onchange="this.form.submit()">
                            <option value="">- All Categories -</option>

                            <?php
                            $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND EXISTS (SELECT 1 FROM recurring_tickets WHERE recurring_ticket_category = category_id $client_query) ORDER BY category_name ASC");
                            while ($row = mysqli_fetch_array($sql_categories_filter)) {
                                $category_id = intval($row['category_id']);
                                $category_name = nullable_htmlentities($row['category_name']);
                            ?>
                                <option <?php if ($category_filter == $category_id) { echo "selected"; } ?> value="<?= $category_id ?>"><?= $category_name ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        <select class="form-control select2" name="assigned_agent" onchange="this.form.submit()">
                            <option value="">- All Agents -</option>

                            <?php
                            $sql_assigned_agents_filter = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND EXISTS (SELECT 1 FROM recurring_tickets WHERE recurring_ticket_assigned_to = user_id $client_query) ORDER BY user_name ASC");
                            while ($row = mysqli_fetch_array($sql_assigned_agents_filter)) {
                                $user_id = intval($row['user_id']);
                                $user_name = nullable_htmlentities($row['user_name']);
                            ?>
                                <option <?php if ($assigned_agent_filter == $user_id) { echo "selected"; } ?> value="<?= $user_id ?>"><?= $user_name ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        <select class="form-control select2" name="billable" onchange="this.form.submit()">
                            <option value="">- Billable Status -</option>                    
                            <option <?php if ($billable_filter == 1) { echo "selected"; } ?> value="1">Billable</option>
                            <option <?php if ($billable_filter == 0) { echo "selected"; } ?> value="0">Non-Billable</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">

                    <?php if (lookupUserPermission("module_support") >= 2) { ?>
                        <div class="dropdown float-right" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_force_recurring_tickets">
                                    <i class="fas fa-fw fa-paper-plane mr-2"></i>Force Reoccur
                                </button>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignAgentRecurringTicketModal">
                                    <i class="fas fa-fw fa-user-check mr-2"></i>Assign Agent
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditCategoryRecurringTicketModal">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Set Category
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditPriorityRecurringTicketModal">
                                    <i class="fas fa-fw fa-thermometer-half mr-2"></i>Set Priority
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditBillableRecurringTicketModal">
                                    <i class="fas fa-fw fa-dollar-sign mr-2"></i>Set Billable
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditNextRunRecurringTicketModal">
                                    <i class="fas fa-fw fa-calendar-day mr-2"></i>Set Next Run Date
                                </a>
                                <div class="dropdown-divider"></div>
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
                    <thead class="<?php if (!$num_rows[0]) { echo "d-none"; } ?> text-nowrap">
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=recurring_ticket_next_run&order=<?= $disp ?>">
                                    Next Run Date <?php if ($sort == 'recurring_ticket_next_run') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_ticket_subject&order=<?php echo $disp; ?>">
                                    Subject <?php if ($sort == 'recurring_ticket_subject') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                                    Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
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
                            <th class="text-center">
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_ticket_billable&order=<?php echo $disp; ?>">
                                    Billable <?php if ($sort == 'recurring_ticket_billable') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                                    Agent <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            
                            <?php if (!$client_url) { ?>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php } ?>
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
                            $recurring_ticket_billable = intval($row['recurring_ticket_billable']);
                            if ($recurring_ticket_billable) {
                                $recurring_ticket_billable_display = "<i class='fas fa-fw fa-check text-success'></i>";
                            } else {
                                $recurring_ticket_billable_display = "-";
                            }
                            $recurring_ticket_category = getFallBack(nullable_htmlentities($row['category_name']));
                            $recurring_ticket_client_name = nullable_htmlentities($row['client_name']);
                            $assigned_to = getFallBack(nullable_htmlentities($row['user_name']));
                        ?>

                            <tr>
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="recurring_ticket_ids[]" value="<?= $recurring_ticket_id ?>">
                                    </div>
                                </td>
                                <td class="text-bold"><?= $recurring_ticket_next_run ?></td>
                                <td>
                                    <a class="ajax-modal" href="#"
                                        data-modal-size="lg"
                                        data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>">
                                        <?= $recurring_ticket_subject ?>
                                    </a>
                                </td>
                                <td><?= $recurring_ticket_category ?></td>
                                <td><?= $recurring_ticket_priority ?></td>
                                <td><?= $recurring_ticket_frequency ?></td>
                                <td class="text-center"><?= $recurring_ticket_billable_display ?></td>
                                <td><?= $assigned_to ?></td>
                                <?php if (!$client_url) { ?>
                                <th><a href="recurring_tickets.php?client_id=<?= $recurring_ticket_client_id ?>"><?= $recurring_ticket_client_name ?></a>
                                </th>
                                <?php } ?>

                                <?php if (lookupUserPermission("module_support") >= 2) { ?>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item ajax-modal" href="#"
                                                    data-modal-size="lg"
                                                    data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="post.php?force_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Force Reoccur
                                                </a>
                                                <?php if (lookupUserPermission("module_support") == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
                <?php
                require_once "modals/recurring_ticket/recurring_ticket_bulk_agent_edit.php";
                require_once "modals/recurring_ticket/recurring_ticket_bulk_billable_edit.php";
                require_once "modals/recurring_ticket/recurring_ticket_bulk_category_edit.php";
                require_once "modals/recurring_ticket/recurring_ticket_bulk_next_run_edit.php";
                require_once "modals/recurring_ticket/recurring_ticket_bulk_priority_edit.php";
                ?>

            </form>

        </div>

        <?php require_once '../includes/filter_footer.php';
        ?>

    </div>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
