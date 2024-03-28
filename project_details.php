<?php

require_once "inc_all.php";


if (isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);

    $sql_project = mysqli_query(
        $mysqli,
        "SELECT * FROM projects
        LEFT JOIN clients ON project_client_id = client_id
        WHERE project_id = $project_id LIMIT 1"
    );

    if (mysqli_num_rows($sql_project) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='projects.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        include_once "footer.php";
        exit;
    }

    $row = mysqli_fetch_array($sql_project);
    
    $project_id = intval($row['project_id']);
    $project_name = nullable_htmlentities($row['project_name']);
    $project_description = nullable_htmlentities($row['project_description']);
    $project_created_at = date("Y-m-d", strtotime($row['project_created_at']));
    $project_updated_at = nullable_htmlentities($row['project_updated_at']);

    $client_id = intval($row['client_id']);
    $client_name = nullable_htmlentities($row['client_name']);

    // Get Tickets
    $sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN users ON ticket_assigned_to = user_id WHERE ticket_project_id = $project_id ORDER BY ticket_number DESC");
    $ticket_count = mysqli_num_rows($sql_tickets);

    // Get Closed Ticket Count
    $sql_closed_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_project_id = $project_id AND ticket_status = 'Closed'");

    $closed_ticket_count = mysqli_num_rows($sql_closed_tickets);
    
    if($ticket_count) {
        $tickets_closed_percent = ($closed_ticket_count / $ticket_count) * 100;
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
    if($task_count) {
        $tasks_completed_percent = ($completed_task_count / $task_count) * 100;
    }
?>

<!-- Breadcrumbs-->
<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="projects.php">Projects</a>
    </li>
    <li class="breadcrumb-item active">Project Details</li>
</ol>

<!-- Project Header -->
<div class="card card-body">
    <div class="row">
        <div class="col-sm-3">
            <div class="media">
                <i class="fa fa-fw fa-2x fa-project-diagram text-secondary mr-3"></i>
                <div class="media-body">
                    <h3 class="mb-0"><?php echo $project_name; ?></h3>
                    <div><small class="text-secondary"><?php echo $project_description; ?></small></div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="media">
                <i class="fa fa-fw fa-2x fa-user text-secondary mr-3"></i>
                <div class="media-body">
                    <h3 class="mb-0"><?php echo $client_name; ?></h3>
                    <div><small class="text-secondary"><i class="fa fa-fw fa-clock mr-2"></i><?php echo $project_created_at; ?></small></div>
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <?php if($ticket_count) { ?>
            <div class="progress" style="height: 20px;">
                <i class="fa fas fa-fw fa-life-ring mr-2"></i>
                <div class="progress-bar bg-success" style="width: <?php echo $tickets_closed_percent; ?>%;"><?php echo $closed_ticket_count; ?> / <?php echo $ticket_count; ?></div>
            </div>
            <?php } ?>
            <?php if($task_count) { ?>
            <div class="progress mt-2" style="height: 20px;">
                <i class="fa fas fa-fw fa-tasks mr-2"></i>
                <div class="progress-bar" style="width: <?php echo $tasks_completed_percent; ?>%;"><?php echo $completed_task_count; ?> / <?php echo $task_count; ?></div>
            </div>
            <?php } ?>
        </div>
        
        <div class="col-sm-3">
            <div class="btn-group float-right d-print-none">
                <div class="dropdown dropleft text-center ml-3">
                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editProjectModal<?php echo $project_id; ?>">
                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                        </a>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addProjectTicketModal">
                            <i class="fas fa-fw fa-life-ring mr-2"></i>Add Ticket
                        </a>
                        <?php if ($session_user_role == 3) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?archive_project=<?php echo $project_id; ?>">
                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                            </a>
                        <?php } ?>
                        <?php if ($session_user_role == 3) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_project=<?php echo $project_id; ?>">
                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        
        <!-- Tickets card -->
        <?php if (mysqli_num_rows($sql_tickets) > 0) { ?>
            <div class="card card-body card-outline card-dark mb-3">

                <h5 class="text-secondary"><i class="fa fa-fw fa-life-ring mr-2"></i>Tickets</h5>
                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark">
                        <tr>
                            <th>Number</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Last Response</th>
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
                            $ticket_status = nullable_htmlentities($row['ticket_status']);
                            $ticket_billable = intval($row['ticket_billable']);
                            $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                            $ticket_created_at_time_ago = timeAgo($row['ticket_created_at']);
                            $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                            $ticket_updated_at_time_ago = timeAgo($row['ticket_updated_at']);
                            if (empty($ticket_updated_at)) {
                                if ($ticket_status == "Closed") {
                                    $ticket_updated_at_display = "<p>Never</p>";
                                } else {
                                    $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                }
                            } else {
                                $ticket_updated_at_display = "$ticket_updated_at_time_ago<br><small class='text-secondary'>$ticket_updated_at</small>";
                            }
                            $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);

                            $ticket_status_color = getTicketStatusColor($ticket_status);

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
                                if ($ticket_status == "Closed") {
                                    $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                } else {
                                    $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                }
                            } else {
                                $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                            }
                            
                            $project_id = intval($row['ticket_project_id']);

                            $contact_name = nullable_htmlentities($row['contact_name']);
                            $contact_email = nullable_htmlentities($row['contact_email']);
                            $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                            if (empty($contact_archived_at)) {
                                $contact_archived_display = "";
                            } else {
                                $contact_archived_display = "Archived - ";
                            }
                            if (empty($contact_name)) {
                                $contact_display = "-";
                            } else {
                                $contact_display = "$contact_archived_display$contact_name<br><small class='text-secondary'>$contact_email</small>";
                            }

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

                                <!-- Ticket Number -->
                                <td>
                                    <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a>
                                </td>

                                <!-- Ticket Subject -->
                                <td>
                                    <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a>
                                </td>

                                <!-- Ticket Priority -->
                                <td><?php echo $ticket_priority_display; ?></a></td>

                                <!-- Ticket Status -->
                                <td>
                                    <span class='p-2 badge badge-pill badge-<?php echo $ticket_status_color; ?>'><?php echo $ticket_status; ?></span>
                                </td>

                                <!-- Ticket Assigned agent -->
                                <td><?php echo $ticket_assigned_to_display; ?></td>

                                <!-- Ticket Last Response -->
                                <td>
                                    <div><?php echo $ticket_updated_at_display; ?></div>
                                    <div><?php echo $ticket_reply_by_display; ?></div>
                                </td>
                            </tr>


                        <?php } ?>

                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="col-md-4">
        
        <!-- Tasks card -->
        <?php if (mysqli_num_rows($sql_tasks) > 0) { ?>
            <div class="card card-body card-outline card-dark mb-3">
                <h5 class="text-secondary"><i class="fa fa-fw fa-tasks mr-2"></i>Tasks</h5>

                <?php
                // Get Tasks
                while ($row = mysqli_fetch_array($sql_tasks)) {
                    $task_id = intval($row['task_id']);
                    $task_name = nullable_htmlentities($row['task_name']);
                    $task_description = nullable_htmlentities($row['task_description']);
                    $task_completed_at = nullable_htmlentities($row['task_completed_at']);
                    ?>
                    
                    <?php if($task_completed_at) { ?>
                        <div class='mt-1 text-success'>
                            <i class="fas fa-fw fa-check-circle mr-2"></i><s><?php echo $task_name; ?></s><span class="float-right"><small class="text-secondary"><i class="fa fa-fw fa-clock mr-1"></i><?php echo $task_completed_at; ?></small></span>
                        </div>
                    <?php } else { ?>
                        <div class='mt-1'>
                            <a href="post.php?complete_task=<?php echo $task_id; ?>"><i class="fas fa-fw fa-check-circle mr-2"></i></a><?php echo $task_name; ?>
                            <?php if ($ticket_status !== "Closed") { ?>
                                <div class="float-right">
                                    <a  href="#" data-toggle="modal" data-target="#editTaskModal<?php echo $task_id; ?>">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </a>
                                    <a class="confirm-link" href="post.php?delete_task=<?php echo $task_id; ?>">
                                        <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                                    </a>  
                                </div>
                            <?php } ?>
                        </div>
                        <?php require "task_edit_modal.php"; ?>
                    <?php } ?>
                
                <?php } ?>
            </div>
        <?php } ?>
        <!-- End Tasks card -->

    </div> <!-- End col-3 -->

</div> <!-- End row -->

<?php

require_once "project_edit_modal.php";

}

require_once "footer.php";

?>

<script src="js/pretty_content.js"></script>
