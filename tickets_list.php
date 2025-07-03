<div class="card card-dark">
    <div class="card-body">
        <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if (!$num_rows[0]) { echo "d-none"; } ?> text-nowrap">
                        <tr>
                            
                            <td>
                                <?php if(isset($_GET['status'])){ if ($_GET['status'] !== 'Closed') { ?>
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" onkeydown="checkAll(this)">
                                </div>
                                <?php }} ?>
                            </td>

                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_number&order=<?php echo $disp; ?>">
                                    Ticket <?php if ($sort == 'ticket_number') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_subject&order=<?php echo $disp; ?>">
                                    Subject <?php if ($sort == 'ticket_subject') { echo $order_icon; } ?>
                                </a>
                            </th>
                            
                            <th>
                                <?php if (!$client_url) { ?>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?> /
                                </a>
                                <?php } ?>
                                <a class="text-secondary <?php if ($client_url) { echo "text-dark"; } ?>" href="?<?php echo $url_query_strings_sort; ?>&sort=contact_name&order=<?php echo $disp; ?>">
                                    Contact <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 2) { ?>
                            <th class="text-center">
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_billable&order=<?php echo $disp; ?>">
                                    Billable <?php if ($sort == 'ticket_billable') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php } ?>

                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_priority&order=<?php echo $disp; ?>">
                                    Priority <?php if ($sort == 'ticket_priority') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_status&order=<?php echo $disp; ?>">
                                    Status <?php if ($sort == 'ticket_status') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                                    Assigned <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_updated_at&order=<?php echo $disp; ?>">
                                    Last Response <?php if ($sort == 'ticket_updated_at') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=ticket_created_at&order=<?php echo $disp; ?>">
                                    Created <?php if ($sort == 'ticket_created_at') { echo $order_icon; } ?>
                                </a>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $ticket_id = intval($row['ticket_id']);
                            $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                            $ticket_number = intval($row['ticket_number']);
                            $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                            $ticket_priority = nullable_htmlentities($row['ticket_priority']);
                            $ticket_status_id = intval($row['ticket_status_id']);
                            $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                            $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);
                            $ticket_billable = intval($row['ticket_billable']);
                            $ticket_scheduled_for = nullable_htmlentities($row['ticket_schedule']);
                            $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                            $ticket_created_at_time_ago = timeAgo($row['ticket_created_at']);
                            $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                            $ticket_updated_at_time_ago = timeAgo($row['ticket_updated_at']);
                            $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);
                            if (empty($ticket_updated_at)) {
                                if (!empty($ticket_closed_at)) {
                                    $ticket_updated_at_display = "<p>Never</p>";
                                } else {
                                    $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                }
                            } else {
                                $ticket_updated_at_display = "$ticket_updated_at_time_ago<br><small class='text-secondary'>$ticket_updated_at</small>";
                            }

                            $project_id = intval($row['ticket_project_id']);

                            $client_id = intval($row['ticket_client_id']);
                            $client_name = nullable_htmlentities($row['client_name']);
                            $contact_id = intval($row['contact_id']);
                            $contact_name = nullable_htmlentities($row['contact_name']);
                            $contact_email = nullable_htmlentities($row['contact_email']);

                            if ($ticket_priority == "High") {
                                $ticket_priority_color = "danger";
                            } elseif ($ticket_priority == "Medium") {
                                $ticket_priority_color = "warning";
                            } else {
                                $ticket_priority_color = "info";
                            }

                            $ticket_assigned_to = intval($row['ticket_assigned_to']);
                            if (empty($ticket_assigned_to)) {
                                if (!empty($ticket_closed_at)) {
                                    $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                } else {
                                    $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                }
                            } else {
                                $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                            }

                            if (empty($contact_name)) {
                                $contact_display = "-";
                            } else {
                                $contact_display = "<div><a href='contact_details.php?client_id=$client_id&contact_id=$contact_id'>$contact_name</a></div>";
                            }

                            // Get who last updated the ticket - to be shown in the last Response column

                            // Defaults to prevent undefined errors
                            $ticket_reply_created_at = "";
                            $ticket_reply_created_at_time_ago = "Never";
                            $ticket_reply_by_display = "";
                            $ticket_reply_type = "Client"; // Default to client for un-replied tickets

                            $sql_ticket_reply = mysqli_query($mysqli,
                                "SELECT ticket_reply_type, ticket_reply_created_at, contact_name, user_name FROM ticket_replies
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
                                $ticket_reply_created_at = nullable_htmlentities($row['ticket_reply_created_at']);
                                $ticket_reply_created_at_time_ago = timeAgo($ticket_reply_created_at);
                            }


                            // Get Tasks
                            $sql_tasks = mysqli_query( $mysqli, "SELECT * FROM tasks WHERE task_ticket_id = $ticket_id ORDER BY task_created_at ASC");
                            $task_count = mysqli_num_rows($sql_tasks);
                                    // Get Completed Task Count
                            $sql_tasks_completed = mysqli_query($mysqli,
                                "SELECT * FROM tasks
                                WHERE task_ticket_id = $ticket_id
                                AND task_completed_at IS NOT NULL"
                            );
                            $completed_task_count = mysqli_num_rows($sql_tasks_completed);

                            // Tasks Completed Percent
                            if($task_count) {
                                $tasks_completed_percent = round(($completed_task_count / $task_count) * 100);
                            }
                            
                            ?>

                            <tr class="<?php if(empty($ticket_closed_at) && empty($ticket_updated_at)) { echo "text-bold"; }?> <?php if (empty($ticket_closed_at) && $ticket_reply_type == "Client") { echo "table-warning"; } ?>">

                                <td>
                                    <!-- Ticket Bulk Select (for open tickets) -->
                                    <?php if (empty($ticket_closed_at)) { ?>
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="ticket_ids[]" value="<?php echo $ticket_id ?>">
                                    </div>
                                    <?php } ?>
                                </td>
                                
                                <!-- Ticket Number -->
                                <td>
                                    <a href="ticket.php?<?php echo $client_url; ?>ticket_id=<?php echo $ticket_id; ?>">
                                        <span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span>
                                    </a>
                                </td>

                                <!-- Ticket Subject -->
                                <td>
                                    <a href="ticket.php?<?php echo $client_url; ?>ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a>

                                    <?php if($task_count && $completed_task_count > 0) { ?>
                                    <div class="progress mt-2" style="height: 20px;">
                                        <div class="progress-bar" style="width: <?php echo $tasks_completed_percent; ?>%;"><?php echo $completed_task_count.' / '.$task_count; ?></div>
                                    </div>
                                    <?php } ?>
                                    <?php if($task_count && $completed_task_count == 0) { ?>
                                    <div class="mt-2" style="height: 20px; background-color:#e9ecef;">
                                        <p class="text-center" ><?php echo $completed_task_count.' / '.$task_count; ?></p>
                                    </div>
                                    <?php } ?>
                                </td>

                                <!-- Ticket Contact -->
                                <td>
                                    <?php if (!$client_url) { ?>
                                    <a href="tickets.php?client_id=<?php echo $client_id; ?>"><strong><?php echo $client_name; ?></strong></a>
                                    <?php } ?>
                                    <div><?php echo $contact_display; ?></div>
                                </td>

                                <!-- Ticket Billable (if accounting enabled -->
                                <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 2) { ?>
                                    <td class="text-center">
                                        <a href="#" 
                                            data-toggle = "ajax-modal"
                                            data-ajax-url = "ajax/ajax_ticket_billable.php"
                                            data-ajax-id = "<?php echo $ticket_id; ?>"
                                            >
                                            <?php
                                            if ($ticket_billable == 1) {
                                                echo "<span class='badge badge-pill badge-success p-2'>Yes</span>";
                                            } else {
                                                echo "<span class='badge badge-pill badge-secondary p-2'>No</span>";
                                            }
                                            ?>
                                        </a>
                                    </td>
                                <?php } ?>

                                <!-- Ticket Priority -->
                                <td>
                                    <a href="#"
                                        <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                                        data-toggle = "ajax-modal"
                                        data-ajax-url = "ajax/ajax_ticket_priority.php"
                                        data-ajax-id = "<?php echo $ticket_id; ?>"
                                        <?php } ?>
                                        >
                                        <span class='p-2 badge badge-pill badge-<?php echo $ticket_priority_color; ?>'>
                                            <?php echo $ticket_priority; ?>
                                        </span>
                                    </a>
                                </td>

                                <!-- Ticket Status -->
                                <td>
                                    <span class='badge badge-pill text-light p-2' style="background-color: <?php echo $ticket_status_color; ?>"><?php echo $ticket_status_name; ?></span>
                                    <?php if (isset ($ticket_scheduled_for)) { echo "<div class=\"mt-1\"> <small class='text-secondary'> $ticket_scheduled_for </small></div>"; } ?>
                                </td>

                                <!-- Ticket Assigned agent -->
                                <td>
                                    <a href="#"
                                        <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                                        data-toggle = "ajax-modal"
                                        data-ajax-url = "ajax/ajax_ticket_assign.php"
                                        data-ajax-id = "<?php echo $ticket_id; ?>"
                                        <?php } ?>
                                        >
                                        <?php echo $ticket_assigned_to_display; ?>
                                    </a>
                                </td>

                                <!-- Ticket Last Response -->
                                <td>
                                    <div title="<?php echo $ticket_reply_created_at; ?>">
                                        <?php echo $ticket_reply_created_at_time_ago; ?>
                                    </div>
                                    <div class="text-secondary"><?php echo $ticket_reply_by_display; ?></div>
                                </td>

                                <!-- Ticket Created At -->
                                <td>
                                    <?php echo $ticket_created_at_time_ago; ?>
                                    <br>
                                    <small class="text-secondary"><?php echo date("$config_date_format $config_time_format", strtotime($ticket_created_at)); ?></small>
                                </td>

                            </tr>

                            <?php
                        }
                        
                        ?>

                        </tbody>
                    </table>
                </div>
                <?php require_once "modals/ticket_bulk_assign_modal.php"; ?>
                <?php require_once "modals/ticket_bulk_edit_category_modal.php"; ?>
                <?php require_once "modals/ticket_bulk_edit_priority_modal.php"; ?>
                <?php require_once "modals/ticket_bulk_add_project_modal.php"; ?>
                <?php require_once "modals/ticket_bulk_reply_modal.php"; ?>
                <?php require_once "modals/ticket_bulk_merge_modal.php"; ?>
                <?php require_once "modals/ticket_bulk_resolve_modal.php"; ?>
            </form>
            <?php require_once "includes/filter_footer.php"; ?>
        </div>
    </div>

