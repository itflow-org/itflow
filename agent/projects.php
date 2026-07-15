<?php

// Default Column Sortby/Order Filter
$sort = "project_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND project_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms & Project client access snippet
enforceUserPermission('module_support');
$project_permission_snippet = '';
if (!empty($client_access_string)) {
    $project_permission_snippet = "AND project_client_id IN ($client_access_string) OR project_client_id = 0";
}

// Project Completed Status Query
if (isset($_GET['status']) && $_GET['status'] == 1) {
    $status = 1; // Closed
    $status_query = "IS NOT NULL";
} else {
    $status = 0; // Open
    $status_query = "IS NULL";
}

$sql_projects = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM projects
    LEFT JOIN clients ON client_id = project_client_id
    LEFT JOIN users ON user_id = project_manager
    WHERE DATE(project_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(project_prefix,project_number) LIKE '%$q%' OR project_name LIKE '%$q%' OR project_description LIKE '%$q%' OR user_name LIKE '%$q%')
    AND project_completed_at $status_query
    $project_permission_snippet
    AND project_$archive_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-project-diagram mr-2"></i>Projects</h3>
        <?php if (lookupUserPermission("module_support") >= 2) { ?>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/project/project_add.php?<?= $client_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ml-2">New Project</span></button>
            </div>
        <?php } ?>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <input type="hidden" name="status" value="<?php echo $status; ?>">
            <input type="hidden" name="archived" value="<?php echo $archived; ?>">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group mb-3 mb-sm-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(escapeHtml($q));} ?>" placeholder="Search Projects">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="btn-toolbar float-right">
                        <div class="btn-group mr-2">
                            <a href="?<?php echo $client_url; ?>status=0" class="btn btn-<?php if ($status == 0){ echo "primary"; } else { echo "default"; } ?>"><i class="fa fa-fw fa-door-open mr-2"></i>Open</a>
                            <a href="?<?php echo $client_url; ?>status=1" class="btn btn-<?php if ($status == 1){ echo "primary"; } else { echo "default"; } ?>"><i class="fa fa-fw fa-door-closed mr-2"></i>Closed</a>
                        </div>

                        <div class="btn-group">
                            <a href="?<?php echo $url_query_strings_sort ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
                               class="btn btn-<?php if ($archived == 1) { echo "primary"; } else { echo "default"; } ?>">
                                <i class="fa fa-fw fa-archive mr-2"></i>Archived
                            </a>
                        </div>

                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Date range</label>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                            <input type="hidden" name="canned_date" id="canned_date" value="<?php echo escapeHtml($_GET['canned_date']) ?? ''; ?>">
                            <input type="hidden" name="dtf" id="dtf" value="<?php echo escapeHtml($dtf ?? ''); ?>">
                            <input type="hidden" name="dtt" id="dtt" value="<?php echo escapeHtml($dtt ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-borderless">
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=project_number&order=<?php echo $disp; ?>">
                            Number <?php if ($sort == 'project_number') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=project_name&order=<?php echo $disp; ?>">
                            Project <?php if ($sort == 'project_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Tickets / Tasks</th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=project_due&order=<?php echo $disp; ?>">
                            Due <?php if ($sort == 'project_due') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if ($status == 1) { ?>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=project_completed_at&order=<?php echo $disp; ?>">
                            Completed
                        </a>
                    </th>
                    <?php } ?>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                            Manager
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=project_created_at&order=<?php echo $disp; ?>">
                            Created
                        </a>
                    </th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                            Client
                        </a>
                    </th>
                    <?php } ?>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql_projects)) {
                    $project_id = intval($row['project_id']);
                    $project_prefix = escapeHtml($row['project_prefix']);
                    $project_number = intval($row['project_number']);
                    $project_name = escapeHtml($row['project_name']);
                    $project_description = escapeHtml($row['project_description']);
                    $project_due = escapeHtml($row['project_due']);
                    $project_created_at = escapeHtml($row['project_created_at']);
                    $project_created_at_display = date("Y-m-d", strtotime($project_created_at));
                    $project_updated_at = escapeHtml($row['project_updated_at']);
                    $project_completed_at = escapeHtml($row['project_completed_at']);
                    $project_completed_at_display = date("Y-m-d", strtotime($project_completed_at));
                    $project_archived_at = escapeHtml($row['project_archived_at']);

                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    if ($client_name) {
                        $client_name_display = "<a href='projects.php?client_id=$client_id'>$client_name</a>";
                    } else {
                        $client_name_display = "-";
                    }

                    $project_manager = intval($row['user_id']);
                    if ($project_manager) {
                        $project_manager_display = escapeHtml($row['user_name']);
                    } else {
                        $project_manager_display = "-";
                    }


                    // Get Tasks and Tickets Stats
                    // Get Tickets
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS count FROM tickets WHERE ticket_project_id = $project_id"));
                    $ticket_count = $row['count'];

                    // Get Closed Ticket Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS count FROM tickets WHERE ticket_project_id = $project_id AND ticket_closed_at IS NOT NULL"));
                    $closed_ticket_count = $row['count'];

                    // Ticket Closed Percent
                    if($ticket_count) {
                        $tickets_closed_percent = round(($closed_ticket_count / $ticket_count) * 100);
                    }
                    // Get All Tasks
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('task_id') AS count FROM tickets, tasks WHERE ticket_id = task_ticket_id AND ticket_project_id = $project_id"));
                    $task_count = $row['count'];

                    // Get Completed Task Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('task_id') AS count FROM tickets, tasks WHERE ticket_id = task_ticket_id AND ticket_project_id = $project_id AND task_completed_at IS NOT NULL"));
                    $completed_task_count = $row['count'];

                    // Tasks Completed Percent
                    if($task_count) {
                        $tasks_completed_percent = round(($completed_task_count / $task_count) * 100);
                    }

                    ?>

                    <tr>
                        <td>
                            <a class="text-dark" href="project.php?<?php echo $client_url; ?>project_id=<?php echo $project_id; ?>">
                                <?php echo "$project_prefix$project_number"; ?>
                            </a>
                        </td>
                        <td>
                            <a class="text-dark" href="project.php?project_id=<?php echo $project_id; ?>">
                                <div class="media">
                                    <i class="fa fa-fw fa-2x fa-project-diagram mr-3"></i>
                                    <div class="media-body">
                                        <div><?php echo $project_name; ?></div>
                                        <div><small class="text-secondary"><?php echo $project_description; ?></small></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td>
                            <?php if($ticket_count) { ?>
                            <div class="progress" style="height: 20px;">
                                <i class="fa fas fa-fw fa-life-ring mr-2"></i>
                                <div class="progress-bar bg-primary" style="width: <?php echo $tickets_closed_percent; ?>%;"><?php echo $closed_ticket_count; ?> / <?php echo $ticket_count; ?></div>
                            </div>
                            <?php } else { echo "<div>-</div>"; } ?>
                            <?php if($task_count) { ?>
                            <div class="progress mt-2" style="height: 20px;">
                                <i class="fa fas fa-fw fa-tasks mr-2"></i>
                                <div class="progress-bar bg-secondary" style="width: <?php echo $tasks_completed_percent; ?>%;"><?php echo $completed_task_count; ?> / <?php echo $task_count; ?></div>
                            </div>
                            <?php } ?>
                        </td>
                        <td><?php echo $project_due; ?></td>
                        <?php if ($status == 1) { ?>
                        <td><?php echo $project_completed_at_display; ?></td>
                        <?php } ?>
                        <td><?php echo $project_manager_display; ?></td>
                        <td><?php echo $project_created_at_display; ?></td>
                        <?php if (!$client_url) { ?>
                        <td><?php echo $client_name_display; ?></td>
                        <?php } ?>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <?php if (empty($project_completed_at)) { ?>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url = "modals/project/project_edit.php?id=<?= $project_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                    <?php } ?>
                                    <?php if (!empty($project_completed_at) && lookupUserPermission("module_support" >= 2)) { ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if (empty($project_archived_at)) { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-info confirm-link" href="post.php?restore_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo mr-2"></i>Restore
                                            </a>
                                            <?php if (lookupUserPermission("module_support" >= 3)) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
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
