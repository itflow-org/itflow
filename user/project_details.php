<?php

// Default Column Sortby Filter
$sort = "ticket_number";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND ticket_client_id = $client_id";
    $client_ticket_select_query = "AND ticket_client_id = $client_id"; // Used when linking a ticket to the project
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_ticket_select_query = '';
    $client_url = '';
}

// Perms & Project client access snippet
enforceUserPermission('module_support');
$project_permission_snippet = '';

if (!empty($client_access_string)) {
    $project_permission_snippet = "AND project_client_id IN ($client_access_string) OR project_client_id = 0";
}

if (isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);

    $sql_project = mysqli_query(
        $mysqli,
        "SELECT * FROM projects
        LEFT JOIN clients ON project_client_id = client_id
        LEFT JOIN users ON project_manager = user_id
        WHERE project_id = $project_id
        $project_permission_snippet
        LIMIT 1"
    );

    if (mysqli_num_rows($sql_project) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='projects.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        include_once "footer.php";
        exit;
    }

    $row = mysqli_fetch_array($sql_project);

    $project_id = intval($row['project_id']);
    $project_prefix = nullable_htmlentities($row['project_prefix']);
    $project_number = intval($row['project_number']);
    $project_name = nullable_htmlentities($row['project_name']);
    $project_description = nullable_htmlentities($row['project_description']);
    $project_due = nullable_htmlentities($row['project_due']);
    $project_created_at = date("Y-m-d", strtotime($row['project_created_at']));
    $project_updated_at = nullable_htmlentities($row['project_updated_at']);
    $project_completed_at = nullable_htmlentities($row['project_completed_at']);
    $project_archived_at = nullable_htmlentities($row['project_archived_at']);
    $client_id = intval($row['client_id']);
    $client_name = nullable_htmlentities($row['client_name']);
    if ($client_name) {
        $client_name_display = "<div class='text-secondary'><i class='fas fa-fw fa-users mr-2'></i>$client_name</div>";
    } else {
        $client_name_display = "";
    }

    $project_manager = intval($row['user_id']);
    $project_manager_name = nullable_htmlentities($row['user_name']);
    if ($project_manager) {
        $project_manager_display = "<div class='text-secondary'><i class='fas fa-fw fa-user-tie mr-2'></i>$project_manager_name</div>";
    } else {
        $project_manager_display = "-";
    }

    if ($project_completed_at) {
        $project_status_display = "<span class='badge badge-pill badge-dark ml-2'>Closed</span>";
        $project_completed_date_display = "<div class='text-primary text-bold'><small><i class='fa fa-fw fa-door-closed mr-2'></i>" . date('Y-m-d', strtotime($project_completed_at)) . "</small></div>";
    } else {
        $project_status_display = "<span class='badge badge-pill badge-primary ml-2'>Open</span>";
        $project_completed_date_display = "";
    }

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $tab_title = "{$row['project_prefix']}{$row['project_number']}";
    $page_title = $row['project_name'];

    // Get Tickets
    $sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        WHERE ticket_project_id = $project_id
        ORDER BY $sort $order"
    );
    $ticket_count = mysqli_num_rows($sql_tickets);

    // Get Closed Ticket Count
    $sql_closed_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_project_id = $project_id AND ticket_closed_at IS NOT NULL");
    $closed_ticket_count = mysqli_num_rows($sql_closed_tickets);

    // Get Resolved Ticket Count
    $sql_resolved_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_project_id = $project_id AND ticket_resolved_at IS NOT NULL");

    $resolved_ticket_count = mysqli_num_rows($sql_resolved_tickets);

    $tickets_closed_percent = 100; //Default
    if ($ticket_count) {
        $tickets_closed_percent = round(($closed_ticket_count / $ticket_count) * 100);
    }

    $tickets_resolved_percent = 100; //Default
    if ($ticket_count) {
        $tickets_resolved_percent = round(($resolved_ticket_count / $ticket_count) * 100);
    }

    // Get All Tasks
    $sql_tasks = mysqli_query($mysqli,
        "SELECT * FROM tickets, tasks
        WHERE ticket_id = task_ticket_id
        AND ticket_project_id = $project_id
        ORDER BY task_created_at ASC"
    );
    $task_count = mysqli_num_rows($sql_tasks);

    // Get Completed Task Count
    $sql_tasks_completed = mysqli_query($mysqli,
        "SELECT * FROM tickets, tasks
        WHERE ticket_id = task_ticket_id
        AND ticket_project_id = $project_id
        AND task_completed_at IS NOT NULL"
    );
    $completed_task_count = mysqli_num_rows($sql_tasks_completed);

    // Tasks Completed Percent
    if ($task_count) {
        $tasks_completed_percent = round(($completed_task_count / $task_count) * 100);
    }

    //Get Total Ticket Time
    $sql_ticket_total_reply_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS ticket_total_reply_time FROM ticket_replies 
        LEFT JOIN tickets ON ticket_id = ticket_reply_ticket_id
        WHERE ticket_reply_archived_at IS NULL AND ticket_project_id = $project_id");
    $row = mysqli_fetch_array($sql_ticket_total_reply_time);
    $ticket_total_reply_time = nullable_htmlentities($row['ticket_total_reply_time']);

    // Get all Assigned ticket Users as a comma-separated string
    $sql_project_collaborators = mysqli_query($mysqli, "
        SELECT GROUP_CONCAT(DISTINCT user_name SEPARATOR ', ') AS user_names
        FROM users
        LEFT JOIN ticket_replies ON user_id = ticket_reply_by 
        LEFT JOIN tickets ON ticket_id = ticket_reply_ticket_id
        WHERE ticket_reply_archived_at IS NULL AND ticket_project_id = $project_id
    ");

    // Fetch the result
    $row = mysqli_fetch_assoc($sql_project_collaborators);

    // The user names in a comma-separated string
    $ticket_collaborators = nullable_htmlentities($row['user_names']);

    ?>

<!-- Breadcrumbs-->
<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="projects.php">Projects</a>
    </li>
    <li class="breadcrumb-item active">Project Details</li>
</ol>

<!-- Project Header -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-project-diagram text-secondary mr-2"></i>
            <span class="h4"><?= "$project_prefix$project_number$project_status_display" ?></span>
        </h5>
        <div class="card-tools d-print-none">
            <div class="btn-group">
                <?php if (empty($project_completed_at)) { ?>
                    <div class="dropdown mr-2">
                        <button class="btn btn-primary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                            <i class="fas fa-fw fa-plus mr-2"></i>New
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addTicketModal">
                                <i class="fas fa-fw fa-life-ring mr-2"></i>Ticket
                            </a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary btn-sm mr-3" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                            <i class="fas fa-fw fa-link mr-2"></i>Link
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#linkTicketModal">
                                <i class="fas fa-fw fa-life-ring mr-2"></i>Open Ticket
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#linkClosedTicketModal">
                                <i class="fas fa-fw fa-life-ring mr-2"></i>Closed Ticket
                            </a>
                        </div>
                    </div>
                <?php } ?>
                <?php if (($tickets_closed_percent == 100 || $tickets_resolved_percent == 100) && empty($project_completed_at)) { ?>
                    <a class="btn btn-dark btn-sm confirm-link" href="post.php?close_project=<?php echo $project_id; ?>">
                        <i class="fas fa-fw fa-check mr-2"></i>Close
                    </a>
                <?php } ?>
                <div class="dropdown dropleft text-center ml-3">
                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <?php if (empty($project_completed_at)) { ?>
                            <a class="dropdown-item" href="#"
                                data-toggle = "ajax-modal"
                                data-ajax-url = "ajax/ajax_project_edit.php"
                                data-ajax-id = "<?php echo $project_id; ?>"
                                >
                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                            </a>
                        <?php } ?>
                        <?php if (!empty($project_completed_at) && empty($project_archived_at) && lookupUserPermission("module_support" >= 2)) { ?>
                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?archive_project=<?php echo $project_id; ?>">
                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                            </a>
                        <?php } ?>
                        <?php if (!empty($project_archived_at) && lookupUserPermission("module_support" >= 3)) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_project=<?php echo $project_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-group mb-3">
    <div class="card card-body">
        <h5 class="mb-0"><?= $project_name ?></h5>
        <div><small class="text-secondary"><?php echo $project_description; ?></small></div>
    </div>
    <div class="card card-body">
        <div><?php echo $client_name_display; ?></div>
        <div><?php echo $project_manager_display; ?></div>
        <div class='text-secondary'><i class='fa fa-fw fa-clock mr-2'></i><?php echo $project_due; ?></div>
        <div><?php echo $project_completed_date_display; ?></div>
        <!-- Time tracking -->
        <?php if ($ticket_total_reply_time) { ?>
            <div>
                <i class="far fa-fw fa-clock text-secondary mr-2"></i>Total time worked: <?php echo $ticket_total_reply_time; ?>
            </div>
        <?php } ?>
    </div>

    <div class="card card-body">
        <?php if ($ticket_count) { ?>
            <div class="progress" style="height: 20px;">
                <i class="fa fas fa-fw fa-life-ring mr-2"></i>
                <div class="progress-bar bg-primary" style="width: <?php echo $tickets_closed_percent; ?>%;"><?php echo $closed_ticket_count; ?> / <?php echo $ticket_count; ?></div>
            </div>
        <?php } ?>
        <?php if ($task_count) { ?>
            <div class="progress mt-2" style="height: 20px;">
                <i class="fa fas fa-fw fa-tasks mr-2"></i>
                <div class="progress-bar bg-secondary" style="width: <?php echo $tasks_completed_percent; ?>%;"><?php echo $completed_task_count; ?> / <?php echo $task_count; ?></div>
            </div>
        <?php } ?>
        <?php if ($ticket_collaborators) { ?>
            <div class=mt-1>
                <i class="fas fa-fw fa-users mr-2 text-secondary"></i><?php echo $ticket_collaborators; ?>
            </div>
        <?php } ?>
    </div>
</div>

<div class="row">
    <div class="col-md-9">

        <!-- Tickets card -->
        <?php if (mysqli_num_rows($sql_tickets) > 0) { ?>
            <div class="card card-outline card-dark mb-3">
                <div class="card-header py-2">

                    <h5 class="card-title mt-2 mb-2"><i class="fa fa-fw fa-life-ring mr-2"></i>Project Tickets</h5>

                    <div class="card-tools">
                        <?php if (lookupUserPermission("module_support") >= 2) { ?>
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTicketModal">
                                        <i class="fas fa-fw fa-user-check mr-2"></i>Assign Tech
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditCategoryTicketModal">
                                        <i class="fas fa-fw fa-layer-group mr-2"></i>Set Category
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditPriorityTicketModal">
                                        <i class="fas fa-fw fa-thermometer-half mr-2"></i>Update Priority
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkReplyTicketModal">
                                        <i class="fas fa-fw fa-paper-plane mr-2"></i>Bulk Update/Reply
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkMergeTicketModal">
                                        <i class="fas fa-fw fa-clone mr-2"></i>Merge
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkCloseTicketsModal">
                                        <i class="fas fa-fw fa-check mr-2"></i>Resolve
                                    </a>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                </div>

                <div class="card-body p-0">
                    <form id="bulkActions" action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                        <div class="table-responsive-sm">
                            <table class="table table-border table-hover">
                                <thead class="thead-light">
                                <tr>
                                    <td class="bg-light pr-0">
                                        <div class="form-check">
                                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" onkeydown="checkAll(this)">
                                        </div>
                                    </td>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_number&order=<?php echo $disp; ?>">
                                            Ticket <?php if ($sort == 'ticket_number') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_priority&order=<?php echo $disp; ?>">
                                            Priority <?php if ($sort == 'ticket_priority') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status&order=<?php echo $disp; ?>">
                                            Status <?php if ($sort == 'ticket_status') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                                            Assigned <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_updated_at&order=<?php echo $disp; ?>">
                                            Last Response <?php if ($sort == 'ticket_updated_at') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_array($sql_tickets)) {
                                    $ticket_id = intval($row['ticket_id']);
                                    $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                                    $ticket_number = nullable_htmlentities($row['ticket_number']);
                                    $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                                    $ticket_priority = nullable_htmlentities($row['ticket_priority']);
                                    $ticket_status = intval($row['ticket_status']);
                                    $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                                    $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);
                                    $ticket_billable = intval($row['ticket_billable']);
                                    $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                                    $ticket_created_at_time_ago = timeAgo($row['ticket_created_at']);
                                    $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                                    $ticket_updated_at_time_ago = timeAgo($row['ticket_updated_at']);
                                    if (empty($ticket_updated_at)) {
                                        if ($ticket_status == 5) {
                                            $ticket_updated_at_display = "<p>Never</p>";
                                        } else {
                                            $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                        }
                                    } else {
                                        $ticket_updated_at_display = "$ticket_updated_at_time_ago<br><small class='text-secondary'>$ticket_updated_at</small>";
                                    }
                                    $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);

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
                                        if ($ticket_status == 5) {
                                            $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                        } else {
                                            $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                        }
                                    } else {
                                        $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                                    }

                                    $project_id = intval($row['ticket_project_id']);

                                    $client_id = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']);

                                    // Get who last updated the ticket - to be shown in the last Response column
                                    $ticket_reply_type = "Client"; // Default to client for unreplied tickets
                                    $ticket_reply_by_display = ""; // Default none
                                    $sql_ticket_reply = mysqli_query($mysqli, "SELECT ticket_reply_type, contact_name, user_name FROM ticket_replies
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
                                    }

                                    ?>

                                    <tr>
                                        <td class="pr-0 bg-light">
                                            <!-- Ticket Bulk Select (for open tickets) -->
                                            <?php if (empty($ticket_closed_at)) { ?>
                                            <div class="form-check">
                                                <input class="form-check-input bulk-select" type="checkbox" name="ticket_ids[]" value="<?php echo $ticket_id ?>">
                                            </div>
                                            <?php } ?>
                                        </td>
                                        <!-- Ticket Number / Subject -->
                                        <td>
                                            <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>">
                                                <span class="badge badge-pill badge-secondary p-3 mr-2"><?php echo "$ticket_prefix$ticket_number"; ?></span>
                                                <?php echo $ticket_subject; ?>
                                            </a>
                                        </td>
                                        <!-- Ticket Priority -->
                                        <td><?php echo $ticket_priority_display; ?></a></td>

                                        <!-- Ticket Status -->
                                        <td>
                                            <span class='badge badge-pill text-light p-2' style="background-color: <?php echo $ticket_status_color; ?>"><?php echo $ticket_status_name; ?></span>
                                        </td>

                                        <!-- Ticket Assigned agent -->
                                        <td><?php echo $ticket_assigned_to_display; ?></td>

                                        <!-- Ticket Last Response -->
                                        <td>
                                            <div><?php echo $ticket_updated_at_display; ?></div>
                                            <div><?php echo $ticket_reply_by_display; ?></div>
                                        </td>
                                        <td><?php echo $client_name; ?></td>
                                    </tr>


                                <?php } ?>

                                </tbody>
                            </table>
                        </div>
                        <?php require_once "modals/ticket_bulk_assign_modal.php"; ?>
                        <?php require_once "modals/ticket_bulk_edit_category_modal.php"; ?>
                        <?php require_once "modals/ticket_bulk_edit_priority_modal.php"; ?>
                        <?php require_once "modals/ticket_bulk_reply_modal.php"; ?>
                        <?php require_once "modals/ticket_bulk_merge_modal.php"; ?>
                        <?php require_once "modals/ticket_bulk_resolve_modal.php"; ?>
                    </form>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="col-md-3">

        <!-- Tasks Card -->
        <?php if (mysqli_num_rows($sql_tasks) > 0) { ?>
            <div class="card card-outline card-dark">
                <div class="card-header py-3">
                    <h5 class="card-title"><i class="fas fa-fw fa-tasks mr-2"></i>All Tasks</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <?php
                        while($row = mysqli_fetch_array($sql_tasks)){
                            $task_id = intval($row['task_id']);
                            $task_name = nullable_htmlentities($row['task_name']);
                            $task_completed_at = nullable_htmlentities($row['task_completed_at']);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($task_completed_at) { ?>
                                        <i class="far fa-check-square text-success mr-2"></i>
                                    <?php } else { ?>
                                        <a href="post.php?complete_task=<?php echo $task_id; ?>">
                                            <i class="far fa-square text-secondary mr-2"></i>
                                        </a>
                                    <?php } ?>
                                    <?php echo $task_name; ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
        <?php } ?>
        <!-- End Tasks Card -->

    </div> <!-- End col-3 -->

</div> <!-- End row -->

<?php

require_once "modals/project_link_ticket_modal.php";
require_once "modals/project_link_closed_ticket_modal.php";
require_once "modals/ticket_add_modal.php";

}

require_once "../includes/footer.php";

?>

<script src="../js/bulk_actions.js"></script>
<script src="../js/pretty_content.js"></script>
