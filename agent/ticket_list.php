<?php
/*
 * Ticket list view
 *
 * Included by tickets.php, which owns the filters and sets the query parts
 * this page runs. Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$query =
    "SELECT SQL_CALC_FOUND_ROWS $ticket_select_columns
    FROM tickets
    $ticket_joins
    $ticket_where
    ORDER BY
        CASE
            WHEN '$sort' = 'ticket_priority' THEN
                CASE ticket_priority
                    WHEN 'Urgent' THEN 0
                    WHEN 'High' THEN 1
                    WHEN 'Medium' THEN 2
                    WHEN 'Low' THEN 3
                    ELSE 4
                END
            ELSE NULL
        END $order,
        $sort $order
    LIMIT $record_from, $record_to";

$sql = mysqli_query($mysqli, $query);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

/*
 * Collect the page before rendering so the per-row lookups can be done as two
 * grouped queries instead of three per ticket. A 100-row page used to fire
 * 300 extra queries to fill in the last reply and the task progress bar.
 */
$tickets = array();
while ($row = mysqli_fetch_assoc($sql)) {
    $tickets[intval($row['ticket_id'])] = $row;
}

$ticket_replies = array();
$ticket_tasks = array();

if ($tickets) {
    $page_ticket_ids = implode(',', array_keys($tickets));

    // Latest non-archived reply per ticket, for the Last Response column
    $sql_ticket_replies = mysqli_query($mysqli,
        "SELECT ticket_reply_ticket_id, ticket_reply_type, ticket_reply_created_at, contact_name, user_name
        FROM ticket_replies
        LEFT JOIN users ON ticket_reply_by = user_id
        LEFT JOIN contacts ON ticket_reply_by = contact_id
        WHERE ticket_reply_ticket_id IN ($page_ticket_ids)
        AND ticket_reply_archived_at IS NULL
        AND ticket_reply_id IN (
            SELECT MAX(ticket_reply_id) FROM ticket_replies
            WHERE ticket_reply_ticket_id IN ($page_ticket_ids)
            AND ticket_reply_archived_at IS NULL
            GROUP BY ticket_reply_ticket_id
        )"
    );
    while ($row = mysqli_fetch_assoc($sql_ticket_replies)) {
        $ticket_replies[intval($row['ticket_reply_ticket_id'])] = $row;
    }

    // Task counts per ticket
    $sql_ticket_tasks = mysqli_query($mysqli,
        "SELECT task_ticket_id, COUNT(task_id) AS task_count,
            SUM(CASE WHEN task_completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_count
        FROM tasks
        WHERE task_ticket_id IN ($page_ticket_ids)
        GROUP BY task_ticket_id"
    );
    while ($row = mysqli_fetch_assoc($sql_ticket_tasks)) {
        $ticket_tasks[intval($row['task_ticket_id'])] = $row;
    }
}

?>

<div class="card">

    <?php if (!$tickets) { ?>

        <div class="text-center text-secondary py-5">
            <i class="fas fa-fw fa-life-ring fa-2x mb-3"></i>
            <p class="mb-1">No tickets match these filters.</p>
            <?php if ($active_filters) { ?>
                <a href="<?= escapeHtml('?' . http_build_query(array_filter(array('client_id' => $_GET['client_id'] ?? null, 'view' => 'list')))) ?>">Clear the filters</a>
            <?php } ?>
        </div>

    <?php } else { ?>

        <form id="bulkActions" action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <?php if (lookupUserPermission("module_support") >= 2) { ?>
                <!-- Bulk actions - sits with the rows it acts on, and only appears once something is ticked -->
                <div class="mb-2" id="bulkActionButton" hidden>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-layer-group me-2"></i><span id="selectedCount">0</span> selected
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_assign.php" data-bulk="true">
                                <i class="fas fa-fw fa-user-check me-2"></i>Assign Agent
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_edit_priority.php" data-bulk="true">
                                <i class="fas fa-fw fa-thermometer-half me-2"></i>Set Priority
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_edit_category.php" data-bulk="true">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Set Category
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_add_project.php" data-bulk="true">
                                <i class="fas fa-fw fa-project-diagram me-2"></i>Set Project
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_reply.php" data-modal-size="lg" data-bulk="true">
                                <i class="fas fa-fw fa-paper-plane me-2"></i>Update/Reply
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_merge.php" data-bulk="true">
                                <i class="fas fa-fw fa-clone me-2"></i>Merge
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_resolve.php" data-modal-size="lg" data-bulk="true">
                                <i class="fas fa-fw fa-check me-2"></i>Resolve
                            </a>
                            <?php if (lookupUserPermission("module_support") === 3) { ?>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_tickets">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover mb-0">
                    <thead class="text-dark text-nowrap">
                    <tr>
                        <td class="checkbox-column">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all on this page">
                            </div>
                        </td>

                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_number&order=<?= $disp ?>">
                                Ticket <?php if ($sort == 'ticket_number') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_subject&order=<?= $disp ?>">
                                Subject <?php if ($sort == 'ticket_subject') { echo $order_icon; } ?>
                            </a>
                        </th>

                        <th>
                            <?php if (!$client_url) { ?>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                                Client <?php if ($sort == 'client_name') { echo $order_icon; } ?> /
                            </a>
                            <?php } ?>
                            <a class="text-secondary <?php if ($client_url) { echo "text-dark"; } ?>" href="?<?= $url_query_strings_sort ?>&sort=contact_name&order=<?= $disp ?>">
                                Contact <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                            </a>
                        </th>

                        <?php if ($show_billing_column) { ?>
                        <th class="text-center">
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_billable&order=<?= $disp ?>">
                                Billing <?php if ($sort == 'ticket_billable') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php } ?>

                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_priority&order=<?= $disp ?>">
                                Priority <?php if ($sort == 'ticket_priority') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_status&order=<?= $disp ?>">
                                Status <?php if ($sort == 'ticket_status') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if ($sla_filter_in_use) { ?>
                        <th>SLA</th>
                        <?php } ?>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=user_name&order=<?= $disp ?>">
                                Assigned <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_updated_at&order=<?= $disp ?>">
                                Last Response <?php if ($sort == 'ticket_updated_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ticket_created_at&order=<?= $disp ?>">
                                Created <?php if ($sort == 'ticket_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    foreach ($tickets as $ticket_id => $row) {
                        $ticket_prefix = escapeHtml($row['ticket_prefix']);
                        $ticket_number = intval($row['ticket_number']);
                        $ticket_subject = escapeHtml($row['ticket_subject']);
                        $ticket_priority = escapeHtml($row['ticket_priority']);
                        $ticket_status_name = escapeHtml($row['ticket_status_name']);
                        $ticket_status_color = escapeHtml($row['ticket_status_color']);
                        $ticket_billable = intval($row['ticket_billable']);
                        $ticket_scheduled_for = escapeHtml($row['ticket_schedule']);
                        $ticket_created_at = escapeHtml($row['ticket_created_at']);
                        $ticket_created_at_time_ago = timeAgo($row['ticket_created_at']);
                        $ticket_closed_at = escapeHtml($row['ticket_closed_at']);
                        $ticket_resolved_at = escapeHtml($row['ticket_resolved_at']);
                        $ticket_is_open = empty($ticket_resolved_at) && empty($ticket_closed_at);

                        // SLA alert stages are maintained by cron/ticket_sla.php (1 = warned, 2 = breached)
                        $ticket_sla_alert_stage = max(intval($row['ticket_response_sla_alert_stage']), intval($row['ticket_resolution_sla_alert_stage']));
                        // Only an open ticket can be paused - Resolved and Closed pause the
                        // clock too, but what those tickets have is a verdict, not a pause
                        $ticket_sla_paused = $ticket_is_open && intval($row['ticket_status_pauses_sla']);
                        // A paused ticket isn't running down its clock, so drop the
                        // at-risk warning - a breach already recorded still stands
                        if ($ticket_sla_paused && $ticket_sla_alert_stage < 2) {
                            $ticket_sla_alert_stage = 0;
                        }

                        $project_id = intval($row['ticket_project_id']);
                        $client_id = intval($row['ticket_client_id']);
                        $client_name = escapeHtml($row['client_name']);
                        $contact_id = intval($row['contact_id']);
                        $contact_name = escapeHtml($row['contact_name']);
                        $has_client = $client_id ? "&client_id=$client_id" : "";

                        if ($ticket_priority == "Urgent") {
                            $ticket_priority_color = "dark";
                        } elseif ($ticket_priority == "High") {
                            $ticket_priority_color = "danger";
                        } elseif ($ticket_priority == "Medium") {
                            $ticket_priority_color = "warning";
                        } else {
                            $ticket_priority_color = "info";
                        }

                        $ticket_assigned_to = intval($row['ticket_assigned_to']);
                        if (empty($ticket_assigned_to)) {
                            $ticket_assigned_to_display = $ticket_is_open ? "<span class='text-muted'>Unassigned</span>" : "<span>Unassigned</span>";
                        } else {
                            $ticket_assigned_to_display = escapeHtml($row['user_name']);
                        }

                        if (empty($contact_name)) {
                            $contact_display = "<span class='text-muted'>-</span>";
                        } else {
                            $contact_display = "<a href='contact.php?client_id=$client_id&contact_id=$contact_id'>$contact_name</a>";
                        }

                        $ticket_invoice_id = intval($row['ticket_invoice_id']);
                        $ticket_quote_id = intval($row['ticket_quote_id']);

                        // Last reply, from the batched lookup above
                        $ticket_reply_created_at = "";
                        $ticket_reply_created_at_time_ago = "Never";
                        $ticket_reply_by_display = "";
                        $ticket_reply_type = "Client"; // Un-replied tickets are waiting on us

                        if (isset($ticket_replies[$ticket_id])) {
                            $reply = $ticket_replies[$ticket_id];
                            $ticket_reply_type = escapeHtml($reply['ticket_reply_type']);
                            if ($ticket_reply_type == "Client") {
                                $ticket_reply_by_display = escapeHtml($reply['contact_name']);
                            } else {
                                $ticket_reply_by_display = escapeHtml($reply['user_name']);
                            }
                            $ticket_reply_created_at = escapeHtml($reply['ticket_reply_created_at']);
                            $ticket_reply_created_at_time_ago = timeAgo($reply['ticket_reply_created_at']);
                        }

                        // Task progress, from the batched lookup above
                        $task_count = isset($ticket_tasks[$ticket_id]) ? intval($ticket_tasks[$ticket_id]['task_count']) : 0;
                        $completed_task_count = isset($ticket_tasks[$ticket_id]) ? intval($ticket_tasks[$ticket_id]['completed_count']) : 0;
                        $tasks_completed_percent = $task_count ? round(($completed_task_count / $task_count) * 100) : 0;

                        /*
                         * Row highlighting, in priority order. The title explains why the
                         * row is coloured - previously you just got a red row with no
                         * indication of which of several rules had fired.
                         */
                        $row_class = '';
                        $row_title = '';
                        if ($ticket_is_open && $ticket_sla_alert_stage == 2) {
                            $row_class = 'table-danger';
                            $row_title = 'SLA breached';
                        } elseif ($ticket_is_open && $ticket_sla_alert_stage == 1) {
                            $row_class = 'table-warning';
                            $row_title = 'SLA at risk';
                        } elseif ($ticket_is_open && $ticket_reply_type == "Client") {
                            $row_class = 'table-warning';
                            $row_title = 'Waiting on a reply from us';
                        }
                        if ($ticket_is_open && $ticket_reply_created_at_time_ago == "Never") {
                            $row_class .= ' fw-bold';
                            $row_title = $row_title ? $row_title . ', not yet replied to' : 'Not yet replied to';
                        }

                        ?>

                        <tr class="<?= trim($row_class) ?>" <?php if ($row_title) { ?>title="<?= $row_title ?>"<?php } ?>>

                            <td class="checkbox-column">
                                <!-- Ticket Bulk Select (for open tickets) -->
                                <?php if (empty($ticket_closed_at)) { ?>
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>">
                                </div>
                                <?php } ?>
                            </td>

                            <!-- Ticket Number -->
                            <td>
                                <a href="ticket.php?ticket_id=<?= "$ticket_id$has_client" ?>">
                                    <span class="badge rounded-pill bg-dark p-2"><?= "$ticket_prefix$ticket_number" ?></span>
                                </a>
                            </td>

                            <!-- Ticket Subject -->
                            <td>
                                <a href="ticket.php?ticket_id=<?= "$ticket_id$has_client" ?>"><?= $ticket_subject ?></a>

                                <?php if ($task_count) { ?>
                                    <div class="d-flex align-items-center mt-1">
                                        <div class="progress me-2" style="flex: 0 0 48px; height: 5px;" role="progressbar" aria-valuenow="<?= $tasks_completed_percent ?>" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar <?= $completed_task_count == $task_count ? 'bg-success' : 'bg-secondary' ?>" style="width: <?= $tasks_completed_percent ?>%;"></div>
                                        </div>
                                        <small class="text-muted"><?= "$completed_task_count / $task_count tasks" ?></small>
                                    </div>
                                <?php } ?>
                            </td>

                            <!-- Ticket Client / Contact -->
                            <td>
                                <?php if (!$client_url) { ?>
                                    <?php if ($client_id) { ?>
                                        <div><a href="tickets.php?client_id=<?= $client_id ?>"><strong><?= $client_name ?></strong></a></div>
                                    <?php } else { ?>
                                        <!-- Tickets with no client rendered an empty bold link to client_id=0 -->
                                        <div class="text-muted">No client</div>
                                    <?php } ?>
                                <?php } ?>
                                <div><?= $contact_display ?></div>
                            </td>

                            <!-- Ticket Billing (if accounting enabled) -->
                            <?php if ($show_billing_column) { ?>
                                <td class="text-center">
                                    <?php if ($ticket_invoice_id) { ?>
                                        <a href="invoice.php?client_id=<?= $client_id ?>&invoice_id=<?= $ticket_invoice_id ?>"><span class="badge rounded-pill bg-success p-2">Invoiced</span></a>
                                    <?php } elseif ($ticket_quote_id) { ?>
                                        <a href="quote.php?client_id=<?= $client_id ?>&quote_id=<?= $ticket_quote_id ?>"><span class="badge rounded-pill bg-primary p-2">Quoted</span></a>
                                    <?php } else { ?>
                                        <a href="#" class="ajax-modal" data-modal-url="modals/ticket/ticket_billable.php?id=<?= $ticket_id ?>"
                                            title="<?= $ticket_billable ? 'Billable - click to change' : 'Not billable - click to change' ?>">
                                            <?php if ($ticket_billable) { ?>
                                                <span class="badge rounded-pill bg-success p-2"><i class="fas fa-fw fa-dollar-sign"></i></span>
                                            <?php } else { ?>
                                                <span class="badge rounded-pill bg-secondary p-2"><i class="fas fa-fw fa-minus"></i></span>
                                            <?php } ?>
                                        </a>
                                    <?php } ?>
                                </td>
                            <?php } ?>

                            <!-- Ticket Priority -->
                            <td>
                                <a href="#"
                                    <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                                    class="ajax-modal"
                                    data-modal-url="modals/ticket/ticket_priority.php?id=<?= $ticket_id ?>"
                                    <?php } ?>
                                    >
                                    <span class="p-2 badge rounded-pill text-bg-<?= $ticket_priority_color ?>"><?= $ticket_priority ?></span>
                                </a>
                            </td>

                            <!-- Ticket Status -->
                            <td>
                                <span class="badge rounded-pill text-light p-2" style="background-color: <?= $ticket_status_color ?>"><?= $ticket_status_name ?></span>
                                <?php if (!empty($ticket_scheduled_for)) { ?>
                                    <div class="mt-1"><small class="text-secondary"><i class="fas fa-fw fa-calendar-check me-1"></i><?= $ticket_scheduled_for ?></small></div>
                                <?php } ?>
                            </td>

                            <!-- Ticket SLA state (only when SLAs are in use) -->
                            <?php if ($sla_filter_in_use) { ?>
                                <td class="text-nowrap">
                                    <?php
                                    if (!intval($row['ticket_sla_id'])) {
                                        echo "<span class='text-muted'>-</span>";
                                    } elseif ($ticket_sla_paused) {
                                        echo "<span class='badge rounded-pill bg-secondary p-2' title='The resolution clock is paused in this status'><i class='fas fa-fw fa-pause'></i> Paused</span>";
                                    } elseif ($ticket_sla_alert_stage == 2) {
                                        echo "<span class='badge rounded-pill bg-danger p-2'><i class='fas fa-fw fa-stopwatch'></i> Breached</span>";
                                    } elseif ($ticket_sla_alert_stage == 1) {
                                        echo "<span class='badge rounded-pill bg-warning p-2'><i class='fas fa-fw fa-stopwatch'></i> At risk</span>";
                                    } elseif (!$ticket_is_open) {
                                        $sla_met = intval($row['ticket_response_sla_met'] ?? 0) && (is_null($row['ticket_resolution_due_at']) || intval($row['ticket_resolution_sla_met'] ?? 0));
                                        echo $sla_met ? "<span class='badge rounded-pill bg-success p-2'>Met</span>" : "<span class='text-muted'>-</span>";
                                    } else {
                                        // Whichever clock is still running is the one worth showing
                                        $sla_due_at = empty($row['ticket_first_response_at']) ? $row['ticket_response_due_at'] : $row['ticket_resolution_due_at'];
                                        $sla_due_label = empty($row['ticket_first_response_at']) ? 'First response due' : 'Resolution due';
                                        if (!empty($sla_due_at)) {
                                            echo "<small class='text-secondary' title='" . escapeHtml($sla_due_label) . " " . escapeHtml($sla_due_at) . "'>" . timeAgo($sla_due_at) . "</small>";
                                        } else {
                                            echo "<span class='text-muted'>-</span>";
                                        }
                                    }
                                    ?>
                                </td>
                            <?php } ?>

                            <!-- Ticket Assigned agent -->
                            <td>
                                <a href="#"
                                    <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                                    class="ajax-modal"
                                    data-modal-url="modals/ticket/ticket_assign.php?id=<?= $ticket_id ?>"
                                    <?php } ?>
                                    >
                                    <?= $ticket_assigned_to_display ?>
                                </a>
                            </td>

                            <!-- Ticket Last Response -->
                            <td>
                                <div title="<?= $ticket_reply_created_at ?>"><?= $ticket_reply_created_at_time_ago ?></div>
                                <?php if ($ticket_reply_by_display) { ?>
                                    <small class="text-secondary"><?= $ticket_reply_by_display ?><?php if ($ticket_reply_type == "Client") { echo " <i class='fas fa-fw fa-reply text-warning' title='Client replied last'></i>"; } ?></small>
                                <?php } ?>
                            </td>

                            <!-- Ticket Created At -->
                            <td>
                                <?= $ticket_created_at_time_ago ?>
                                <br>
                                <small class="text-secondary"><?= date("$config_date_format $config_time_format", strtotime($ticket_created_at)) ?></small>
                            </td>

                        </tr>

                        <?php
                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </form>
        <?php require_once "../includes/filter_footer.php"; ?>

        <?php } ?>

</div>
