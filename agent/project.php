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
$project_permission_snippet = clientScopeSql('project_client_id');

if (isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);

    $sql_project = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, project_archived_at, project_completed_at, project_created_at,
            project_description, project_due, project_id, project_name, project_number, project_prefix,
            project_updated_at, user_id, user_name, user_avatar FROM projects
        LEFT JOIN clients ON project_client_id = client_id
        LEFT JOIN users ON project_manager = user_id
        WHERE project_id = $project_id
        $project_permission_snippet
        LIMIT 1"
    );

    if (mysqli_num_rows($sql_project) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='projects.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        include_once "../includes/footer.php";
        exit;
    }

    $row = mysqli_fetch_assoc($sql_project);

    $project_id = intval($row['project_id']);
    $project_prefix = escapeHtml($row['project_prefix']);
    $project_number = intval($row['project_number']);
    $project_name = escapeHtml($row['project_name']);
    $project_description = escapeHtml($row['project_description']);
    $project_due = escapeHtml($row['project_due']);
    $project_created_at = date("Y-m-d", strtotime($row['project_created_at']));
    $project_updated_at = escapeHtml($row['project_updated_at']);
    $project_completed_at = escapeHtml($row['project_completed_at']);
    $project_archived_at = escapeHtml($row['project_archived_at']);
    $client_id = intval($row['client_id']);
    $client_name = escapeHtml($row['client_name']);
    if ($client_name) {
        $client_name_display = "<div class='text-secondary'><i class='fas fa-fw fa-users me-2'></i>$client_name</div>";
    } else {
        $client_name_display = "";
    }

    $project_manager = intval($row['user_id']);
    $project_manager_display = formatAssignee($project_manager, $row['user_name'], $row['user_avatar'], false, 'No manager');
    if (lookupUserPermission('module_support') >= 2 && !$project_completed_at && !$project_archived_at) {
        $project_manager_display = quickPick('project_manager', $project_id, $project_manager, $project_manager_display);
    }
    $project_manager_display = "<div class='d-flex align-items-center text-secondary my-1' title='Project manager'><i class='fas fa-fw fa-user-tie me-2'></i>$project_manager_display</div>";

    if ($project_completed_at) {
        $project_status_display = "<span class='badge rounded-pill bg-dark ms-2'>Closed</span>";
        $project_completed_date_display = "<div class='text-primary text-bold'><small><i class='fa fa-fw fa-door-closed me-2'></i>" . date('Y-m-d', strtotime($project_completed_at)) . "</small></div>";
    } else {
        $project_status_display = "<span class='badge rounded-pill bg-primary ms-2'>Open</span>";
        $project_completed_date_display = "";
    }

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $tab_title = "{$row['project_prefix']}{$row['project_number']}";
    $page_title = $row['project_name'];

    // Tickets on the project - the ones this agent may reach (an internal project can hold any client's)
    $ticket_scope = clientScopeSql('ticket_client_id');
    $project_ticket_sorts = [
        'ticket_number' => "ticket_number $order",
        'ticket_priority' => "CASE ticket_priority WHEN 'Urgent' THEN 0 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END $order",
        'ticket_status' => "ticket_status_order $order",
        'user_name' => "ticket_assigned_to = 0 $order, user_name $order",
        'ticket_updated_at' => "COALESCE(ticket_updated_at, ticket_created_at) $order",
        'client_name' => "client_name $order",
    ];
    if (!isset($project_ticket_sorts[$sort])) {
        $sort = 'ticket_number';
    }
    $sql_tickets = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_status,
            ticket_assigned_to, ticket_billable, ticket_created_at, ticket_updated_at, ticket_resolved_at, ticket_closed_at, ticket_client_id,
            ticket_status_name, ticket_status_color, client_name, user_name, user_avatar
        FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        WHERE ticket_project_id = $project_id $ticket_scope
        ORDER BY {$project_ticket_sorts[$sort]}, ticket_number DESC"
    );
    $project_tickets = [];
    while ($ticket_row = mysqli_fetch_assoc($sql_tickets)) {
        $project_tickets[intval($ticket_row['ticket_id'])] = $ticket_row;
    }
    $project_ticket_replies = ticketLastReplies(array_keys($project_tickets));
    $ticket_count = count($project_tickets);
    $closed_ticket_count = count(array_filter(array_column($project_tickets, 'ticket_closed_at')));
    $resolved_ticket_count = count(array_filter(array_column($project_tickets, 'ticket_resolved_at')));

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
        "SELECT task_completed_at, task_id, task_name FROM tickets, tasks
        WHERE ticket_id = task_ticket_id
        AND ticket_project_id = $project_id $ticket_scope
        ORDER BY task_created_at ASC"
    );
    $task_count = mysqli_num_rows($sql_tasks);

    // Get Completed Task Count
    $sql_tasks_completed = mysqli_query($mysqli,
        "SELECT 1 FROM tickets, tasks
        WHERE ticket_id = task_ticket_id
        AND ticket_project_id = $project_id $ticket_scope
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
    $row = mysqli_fetch_assoc($sql_ticket_total_reply_time);
    $ticket_total_reply_time = escapeHtml($row['ticket_total_reply_time']);

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
    $ticket_collaborators = escapeHtml($row['user_names']);

    ?>

<!-- Breadcrumbs-->
<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="projects.php">Projects</a>
    </li>
    <li class="breadcrumb-item active">Project Details</li>
</ol>

<!-- Project Header -->
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-project-diagram text-secondary me-2"></i>
            <span class="h4"><?= "$project_prefix$project_number$project_status_display" ?></span>
        </h5>
        <div class="card-tools d-print-none">
            <div class="btn-group">
                <?php if (empty($project_completed_at)) { ?>
                    <div class="dropdown me-2">
                        <button class="btn btn-primary btn-sm" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-plus me-2"></i>New
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&project_id=<?= $project_id ?>" data-modal-size="lg">
                            <i class="fa fa-fw fa-life-ring me-2"></i>Ticket
                        </a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary btn-sm me-3" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-link me-2"></i>Link
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project/project_link_ticket.php?<?= $client_url ?>project_id=<?= $project_id ?>">
                                <i class="fas fa-fw fa-life-ring me-2"></i>Open Ticket
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project/project_link_closed_ticket.php?<?= $client_url ?>project_id=<?= $project_id ?>.php">
                                <i class="fas fa-fw fa-life-ring me-2"></i>Closed Ticket
                            </a>
                        </div>
                    </div>
                <?php } ?>
                <?php if (($tickets_closed_percent == 100 || $tickets_resolved_percent == 100) && empty($project_completed_at)) { ?>
                    <a class="btn btn-dark btn-sm confirm-link" href="post.php?close_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-check me-2"></i>Close
                    </a>
                <?php } ?>
                <div class="dropdown dropstart text-center ms-3">
                    <button class="btn btn-secondary btn-sm" title="More actions" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <?php if (empty($project_completed_at)) { ?>
                            <a class="dropdown-item ajax-modal" href="#"
                                data-modal-url = "modals/project/project_edit.php?id=<?= $project_id ?>">
                                <i class="fas fa-fw fa-edit me-2"></i>Edit
                            </a>
                        <?php } ?>
                        <?php if (!empty($project_completed_at) && empty($project_archived_at) && lookupUserPermission("module_support" >= 2)) { ?>
                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?archive_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                            </a>
                        <?php } ?>
                        <?php if (!empty($project_archived_at) && lookupUserPermission("module_support" >= 3)) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-trash me-2"></i>Delete
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
        <div><small class="text-secondary"><?= $project_description ?></small></div>
    </div>
    <div class="card card-body">
        <div><?= $client_name_display ?></div>
        <div><?= $project_manager_display ?></div>
        <div class='text-secondary'><i class='fa fa-fw fa-clock me-2'></i><?= $project_due ?></div>
        <div><?= $project_completed_date_display ?></div>
        <!-- Time tracking -->
        <?php if ($ticket_total_reply_time) { ?>
            <div>
                <i class="far fa-fw fa-clock text-secondary me-2"></i>Total time worked: <?= $ticket_total_reply_time ?>
            </div>
        <?php } ?>
    </div>

    <div class="card card-body">
        <?php if ($ticket_count) { ?>
            <div class="progress" style="height: 20px;">
                <i class="fa fas fa-fw fa-life-ring me-2"></i>
                <div class="progress-bar bg-primary" style="width: <?= $tickets_closed_percent ?>%;"><?= $closed_ticket_count ?> / <?= $ticket_count ?></div>
            </div>
        <?php } ?>
        <?php if ($task_count) { ?>
            <div class="progress mt-2" style="height: 20px;">
                <i class="fa fas fa-fw fa-tasks me-2"></i>
                <div class="progress-bar bg-secondary" style="width: <?= $tasks_completed_percent ?>%;"><?= $completed_task_count ?> / <?= $task_count ?></div>
            </div>
        <?php } ?>
        <?php if ($ticket_collaborators) { ?>
            <div class=mt-1>
                <i class="fas fa-fw fa-users me-2 text-secondary"></i><?= $ticket_collaborators ?>
            </div>
        <?php } ?>
    </div>
</div>

<div class="row">
    <div class="col-md-9">

        <!-- Tickets card -->
        <?php if ($project_tickets) { ?>
            <div class="card card-outline card-dark mb-3">
                <div class="card-header py-2">

                    <h5 class="card-title mt-2 mb-2"><i class="fa fa-fw fa-life-ring me-2"></i>Project Tickets</h5>

                    <div class="card-tools">
                        <?php if (lookupUserPermission("module_support") >= 2) { ?>
                            <div class="dropdown ms-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/ticket/ticket_bulk_assign.php"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-user-check me-2"></i>Assign Agent
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/ticket/ticket_bulk_edit_category.php"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-layer-group me-2"></i>Set Category
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/ticket/ticket_bulk_edit_priority.php"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-thermometer-half me-2"></i>Set Priority
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/ticket/ticket_bulk_reply.php"
                                            data-modal-size="lg"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-paper-plane me-2"></i>Update/Reply
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/ticket/ticket_bulk_merge.php"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-clone me-2"></i>Merge
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/ticket/ticket_bulk_resolve.php"
                                            data-modal-size="lg"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-check me-2"></i>Resolve
                                        </a>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                </div>

                <div class="card-body p-0">
                    <form id="bulkActions" action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 itflow-ticket-table">
                                <thead class="table-light text-nowrap">
                                <tr>
                                    <td class="checkbox-column border-end">
                                        <div class="form-check">
                                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                        </div>
                                    </td>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_number&order=<?= $disp ?>">
                                            Ticket <?php if ($sort == 'ticket_number') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_priority&order=<?= $disp ?>">
                                            Priority <?php if ($sort == 'ticket_priority') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_status&order=<?= $disp ?>">
                                            Status <?php if ($sort == 'ticket_status') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=user_name&order=<?= $disp ?>">
                                            Assigned <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_updated_at&order=<?= $disp ?>">
                                            Activity <?php if ($sort == 'ticket_updated_at') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                foreach ($project_tickets as $ticket_id => $ticket_row) {
                                    $ticket_is_open = ticketIsOpen($ticket_row);
                                    $ticket_url = escapeHtml(ticketUrl($ticket_id, $ticket_row['ticket_client_id']));
                                    ?>
                                    <tr class="<?= $ticket_is_open ? '' : 'text-body-secondary' ?>">
                                        <td class="checkbox-column bg-light border-end">
                                            <?php if (empty($ticket_row['ticket_closed_at'])) { ?>
                                                <div class="form-check">
                                                    <input class="form-check-input bulk-select" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>">
                                                </div>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <a class="<?= $ticket_is_open ? 'fw-semibold text-body' : 'text-body-secondary' ?>" href="<?= $ticket_url ?>"><?= escapeHtml($ticket_row['ticket_subject']) ?></a>
                                            <div class="small"><a class="font-monospace text-body-secondary" href="<?= $ticket_url ?>"><?= escapeHtml(ticketReference($ticket_row)) ?></a></div>
                                        </td>
                                        <td class="text-nowrap"><?= ticketPriorityHtml($ticket_row['ticket_priority'], 'detail') ?></td>
                                        <td class="text-nowrap"><?= ticketStatusHtml($ticket_row['ticket_status_name'], $ticket_row['ticket_status_color'], 'detail') ?></td>
                                        <td class="text-nowrap"><?= formatAssignee($ticket_row['ticket_assigned_to'], $ticket_row['user_name'], $ticket_row['user_avatar'], $ticket_is_open) ?></td>
                                        <td class="small"><?= ticketActivityHtml($project_ticket_replies[$ticket_id] ?? null, $ticket_row) ?></td>
                                        <td class="small"><?= intval($ticket_row['ticket_client_id']) ? escapeHtml($ticket_row['client_name']) : '<span class="text-body-secondary">Internal</span>' ?></td>
                                    </tr>

                                <?php } ?>

                                </tbody>
                            </table>
                        </div>
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
                    <h5 class="card-title"><i class="fas fa-fw fa-tasks me-2"></i>All Tasks</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <?php
                        while($row = mysqli_fetch_assoc($sql_tasks)){
                            $task_id = intval($row['task_id']);
                            $task_name = escapeHtml($row['task_name']);
                            $task_completed_at = escapeHtml($row['task_completed_at']);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($task_completed_at) { ?>
                                        <i class="far fa-check-square text-success me-2"></i>
                                    <?php } else { ?>
                                        <a href="post.php?complete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="far fa-square text-secondary me-2"></i>
                                        </a>
                                    <?php } ?>
                                    <?= $task_name ?>
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

}

require_once "../includes/footer.php";

?>

<script src="../js/bulk_actions.js"></script>
<script src="../js/pretty_content.js"></script>
