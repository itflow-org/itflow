<?php
/*
 * Ticket list view
 *
 * Included by tickets.php, which owns the filters and the views and sets the
 * query parts this runs. Each row: the subject and what it is about, who it
 * is for, its status, priority and agent (changed in place), when it is due,
 * and who spoke last - with a dot before the subject when it is our move and
 * a red or amber edge when it is late or close to it.
 */

isset($mysqli) || die("Direct file access is not allowed");

/*
 * ORDER BY for each column. $order is ASC|DESC from filter_header.php. Due
 * sorts by urgency - late first (on the first click), then close to it, then
 * by the nearest deadline, then parked - and tickets with nothing due always
 * last, whichever way.
 */
$ticket_stage_sql = "GREATEST(ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage)";
$ticket_next_due_sql = "LEAST(COALESCE(ticket_due_at, '9999-12-31'),
    COALESCE(IF(ticket_sla_id > 0, CASE WHEN ticket_first_response_at IS NULL THEN ticket_response_due_at ELSE ticket_resolution_due_at END, NULL), '9999-12-31'))";
$sort_sql = [
    'ticket_number' => "ticket_number $order",
    'ticket_subject' => "ticket_subject $order, ticket_number DESC",
    'client_name' => "client_name $order, ticket_number DESC",
    'contact_name' => "contact_name $order, ticket_number DESC",
    'ticket_billable' => "ticket_invoice_id > 0 $order, ticket_billable $order, ticket_number DESC",
    'ticket_priority' => "CASE ticket_priority WHEN 'Urgent' THEN 0 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END $order, ticket_number DESC",
    'ticket_status' => "ticket_status_order $order, ticket_status $order, ticket_number DESC",
    'user_name' => "ticket_assigned_to = 0 $order, user_name $order, ticket_number DESC",
    'ticket_updated_at' => "COALESCE(ticket_updated_at, ticket_created_at) $order",
    'ticket_created_at' => "ticket_created_at $order",
    'ticket_sla' => "(NOT " . TICKET_OPEN_SQL . " OR (ticket_due_at IS NULL AND ticket_sla_id = 0)) ASC,
        CASE WHEN (ticket_sla_id > 0 AND $ticket_stage_sql = 2) OR ticket_due_at < NOW() THEN 0
            WHEN COALESCE(ticket_status_pauses_sla, 0) = 1 THEN 3
            WHEN ticket_sla_id > 0 AND $ticket_stage_sql = 1 THEN 1
            ELSE 2 END $order,
        $ticket_next_due_sql $order, ticket_number DESC",
];
if (!isset($sort_sql[$sort])) {
    $sort = 'ticket_number';
}

$sql = mysqli_query($mysqli,
    "SELECT SQL_CALC_FOUND_ROWS $ticket_select_columns
    FROM tickets
    $ticket_joins
    $ticket_where
    ORDER BY {$sort_sql[$sort]}
    LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// The page first, then the last reply and the tasks for all of it in two queries
$tickets = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $tickets[intval($row['ticket_id'])] = $row;
}
$last_replies = ticketLastReplies(array_keys($tickets));
$tickets_tags = tagsForRecords('ticket', array_keys($tickets));
$task_counts = ticketTaskCounts(array_keys($tickets));

// A Due column only when there is something to put in it
$show_due = $sla_in_use || mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM tickets WHERE ticket_due_at IS NOT NULL AND ticket_closed_at IS NULL LIMIT 1"));

$sort_link = function ($column, $label, $class = '') use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none ' . $class . '" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

?>

<form id="bulkActions" action="post.php" method="post">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-ticket-list">
            <thead class="<?php if (!$tickets) { echo "d-none"; } ?> text-nowrap table-light">
            <tr>
                <?php if ($can_edit) { ?>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                <?php } ?>
                <th><?= $sort_link('ticket_number', 'Ticket') ?></th>
                <th>
                    <?php if (!$client_url) { ?>
                        <?= $sort_link('client_name', 'Client') ?> <span class="text-body-secondary">/</span>
                    <?php } ?>
                    <?= $sort_link('contact_name', 'Contact', $client_url ? '' : 'text-body-secondary') ?>
                </th>
                <th><?= $sort_link('ticket_status', 'Status') ?></th>
                <th><?= $sort_link('ticket_priority', 'Priority') ?></th>
                <th><?= $sort_link('user_name', 'Assigned') ?></th>
                <?php if ($show_due) { ?>
                    <th><?= $sort_link('ticket_sla', 'Due') ?></th>
                <?php } ?>
                <th><?= $sort_link('ticket_updated_at', 'Activity') ?></th>
                <?php if ($show_billing) { ?>
                    <th class="text-center"><?= $sort_link('ticket_billable', 'Billing') ?></th>
                <?php } ?>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php

            foreach ($tickets as $ticket_id => $row) {
                $row_client_id = intval($row['ticket_client_id']);
                $reference = escapeHtml(ticketReference($row));
                $subject = escapeHtml($row['ticket_subject']);
                $url = escapeHtml(ticketUrl($ticket_id, $row_client_id));
                $is_open = ticketIsOpen($row);
                $is_closed = !empty($row['ticket_closed_at']);
                $mine = intval($row['ticket_assigned_to']) === intval($session_user_id);
                $move = ticketWhoseMove($row);
                $sla = ticketSlaState($row);
                $due = ticketDueState($row);
                $attention = $is_open ? ticketAttention($sla, $due) : '';
                $schedule = $row['ticket_schedule'];
                $scheduled_today = $schedule && substr($schedule, 0, 10) === date('Y-m-d');
                $category_color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $row['category_color']) ? $row['category_color'] : '';
                $ticket_invoice_id = intval($row['ticket_invoice_id']);
                $ticket_quote_id = intval($row['ticket_quote_id']);

                $row_title = ['danger' => $sla['key'] === 'breached' ? 'SLA breached' : 'Past its due date', 'warning' => $sla['key'] === 'at_risk' ? 'SLA at risk' : 'Due within a day'][$attention] ?? '';

                ?>
                <tr class="itflow-ticket-row <?= $attention ? "is-$attention" : '' ?> <?= $is_open ? '' : 'is-done' ?>"<?= $row_title ? ' title="' . $row_title . '"' : '' ?>>
                    <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <?php if (!$is_closed) { ?>
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>" aria-label="Select <?= $reference ?>">
                                </div>
                            <?php } ?>
                        </td>
                    <?php } ?>

                    <!-- What it is -->
                    <td class="itflow-ticket-main">
                        <div class="d-flex align-items-start gap-2">
                            <span class="itflow-ticket-move <?= $move === 'us' ? 'is-ours' : '' ?>" <?= $move === 'us' ? 'title="Needs a reply from us"' : 'aria-hidden="true"' ?>></span>
                            <div class="min-w-0">
                                <a class="<?= $is_open ? 'fw-semibold text-body' : 'text-body-secondary' ?> itflow-ticket-subject" href="<?= $url ?>"><?= $subject ?></a>
                                <div class="itflow-ticket-meta small text-body-secondary">
                                    <a class="font-monospace text-body-secondary" href="<?= $url ?>"><?= $reference ?></a>
                                    <span title="Opened <?= escapeHtml($row['ticket_created_at']) ?>"><?= timeAgo($row['ticket_created_at']) ?></span>
                                    <?= ticketSourceIcon($row['ticket_source']) ?>
                                    <?php if ($row['category_name'] !== null && $row['category_name'] !== '') { ?>
                                        <span class="text-nowrap"><i class="fas fa-circle itflow-ticket-dot" <?= $category_color ? 'style="color: ' . $category_color . '"' : '' ?>></i><?= escapeHtml($row['category_name']) ?></span>
                                    <?php } ?>
                                    <?php if (!empty($row['asset_name'])) { ?>
                                        <span class="text-nowrap text-truncate itflow-ticket-asset" title="Asset"><i class="fas fa-desktop me-1"></i><?= escapeHtml($row['asset_name']) ?></span>
                                    <?php } ?>
                                    <?php if (intval($row['ticket_project_id']) && $row['project_name'] !== null) { ?>
                                        <a class="text-nowrap text-body-secondary ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/project/project.php?id=<?= intval($row['ticket_project_id']) ?>" title="Project <?= escapeHtml($row['project_prefix'] . intval($row['project_number'])) ?> - quick look"><i class="fas fa-project-diagram me-1"></i><?= escapeHtml($row['project_name']) ?></a>
                                    <?php } ?>
                                    <?php if ($schedule && $is_open) { ?>
                                        <span class="text-nowrap <?= $scheduled_today ? 'text-warning-emphasis fw-semibold' : '' ?>" title="Scheduled<?= intval($row['ticket_onsite']) ? ' on site' : '' ?>">
                                            <i class="fas fa-<?= intval($row['ticket_onsite']) ? 'truck' : 'calendar-check' ?> me-1"></i><?= $scheduled_today ? 'Today ' . date($config_time_format, strtotime($schedule)) : date('M j, ' . $config_time_format, strtotime($schedule)) ?>
                                        </span>
                                    <?php } ?>
                                    <?= ticketTasksHtml($task_counts[$ticket_id] ?? null) ?>
                                </div>
                                <?php if (!empty($tickets_tags[$ticket_id])) { ?>
                                    <!-- Tags - the + to change them while it is open; tag an untagged one from its page or Bulk Action -->
                                    <div class="mt-1"><?= tagPills('ticket', $ticket_id, $tickets_tags[$ticket_id], "tickets.php?$client_url", $can_edit && !$is_closed) ?></div>
                                <?php } ?>
                                <!-- On a phone the other columns are off to the side - the essentials come along -->
                                <div class="d-md-none d-flex flex-wrap align-items-center gap-1 mt-1 small">
                                    <?= ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail') ?>
                                    <?= ticketPriorityHtml($row['ticket_priority'], 'detail') ?>
                                    <?php if (!$client_url && $row_client_id) { ?><span class="text-body-secondary text-truncate"><?= escapeHtml($row['client_name']) ?></span><?php } ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Who it is for -->
                    <td class="itflow-ticket-who-cell">
                        <?php if (!$client_url) { ?>
                            <?php if ($row_client_id) { ?>
                                <a class="d-block text-truncate text-body fw-semibold small" href="tickets.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                            <?php } else { ?>
                                <div class="small text-body-secondary"><i class="fas fa-building me-1"></i>Internal</div>
                            <?php } ?>
                        <?php } ?>
                        <?php if (intval($row['contact_id'])) { ?>
                            <div class="small text-truncate"><?= contactLink($row_client_id, $row['contact_id'], $row['contact_name'], $row['contact_photo'] ?? '') ?></div>
                        <?php } elseif ($client_url) { ?>
                            <span class="small text-body-secondary">-</span>
                        <?php } ?>
                    </td>

                    <!-- Status -->
                    <td class="text-nowrap">
                        <span data-ticket-status-for="<?= $ticket_id ?>" data-status-id="<?= intval($row['ticket_status']) ?>">
                            <?php if ($can_edit && $is_open) { ?>
                                <?= quickPick('status', $ticket_id, (string) intval($row['ticket_status']), ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail')) ?>
                            <?php } else { ?>
                                <?= ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail') ?>
                            <?php } ?>
                        </span>
                        <?php if ($move === 'client') { ?>
                            <div class="small text-body-secondary mt-1"><i class="fas fa-hourglass-half me-1"></i>With client</div>
                        <?php } ?>
                    </td>

                    <!-- Priority -->
                    <td class="text-nowrap">
                        <?php if ($can_edit && !$is_closed) { ?>
                            <?= quickPick('priority', $ticket_id, $row['ticket_priority'], ticketPriorityHtml($row['ticket_priority'], 'detail')) ?>
                        <?php } else { ?>
                            <?= ticketPriorityHtml($row['ticket_priority'], 'detail') ?>
                        <?php } ?>
                    </td>

                    <!-- Agent -->
                    <td class="text-nowrap">
                        <?php $assignee = formatAssignee($row['ticket_assigned_to'], $row['user_name'], $row['user_avatar'], $is_open); ?>
                        <?php if ($can_edit && !$is_closed) { ?>
                            <?= quickPick('assignee', $ticket_id, (string) intval($row['ticket_assigned_to']), $assignee) ?>
                        <?php } else { ?>
                            <?= $assignee ?>
                        <?php } ?>
                    </td>

                    <!-- Due - its own date and its SLA -->
                    <?php if ($show_due) { ?>
                        <td class="text-nowrap">
                            <?php $due_html = trim(ticketDuePill($due) . ' ' . ticketSlaPill($sla)); ?>
                            <?= $due_html !== '' ? '<div class="d-flex flex-column align-items-start gap-1">' . $due_html . '</div>' : '<span class="text-body-secondary">-</span>' ?>
                        </td>
                    <?php } ?>

                    <!-- Who spoke last -->
                    <td class="small itflow-ticket-activity">
                        <?= ticketActivityHtml($last_replies[$ticket_id] ?? null, $row) ?>
                    </td>

                    <!-- Billing -->
                    <?php if ($show_billing) { ?>
                        <td class="text-center text-nowrap">
                            <?php if ($ticket_invoice_id) { ?>
                                <a href="invoice.php?invoice_id=<?= $ticket_invoice_id ?>"><span class="badge rounded-pill text-bg-success">Invoiced</span></a>
                            <?php } elseif ($ticket_quote_id) { ?>
                                <a href="quote.php?quote_id=<?= $ticket_quote_id ?>"><span class="badge rounded-pill text-bg-primary">Quoted</span></a>
                            <?php } else { ?>
                                <?= quickPick('billable', $ticket_id, intval($row['ticket_billable']) ? '1' : '0', ticketBillableHtml(intval($row['ticket_billable']), 'list')) ?>
                            <?php } ?>
                        </td>
                    <?php } ?>

                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket.php?id=<?= $ticket_id ?>">
                                    <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                </a>
                                <a class="dropdown-item" href="<?= $url ?>" target="_blank">
                                    <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open in a new tab
                                </a>
                                <?php if ($can_edit && !$is_closed) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>">
                                        <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                    </a>
                                    <?php if (!$mine) { ?>
                                        <a class="dropdown-item" href="post.php?assign_ticket_to_me=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-hand-paper text-secondary me-2"></i>Assign to me
                                        </a>
                                    <?php } ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_merge.php?ticket_id=<?= $ticket_id ?>">
                                        <i class="fas fa-fw fa-clone text-secondary me-2"></i>Merge into a ticket
                                    </a>
                                    <?php if ($is_open) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item confirm-link" href="post.php?resolve_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="<?= $reference ?> is marked resolved<?= intval($config_ticket_client_general_notifications) === 1 && intval($row['contact_id']) ? ' and the contact is told' : '' ?>.">
                                            <i class="fas fa-fw fa-check text-success me-2"></i>Resolve
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="<?= $reference ?> goes for good, with its replies, tasks and attachments.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>

            <?php } ?>

            </tbody>
        </table>
    </div>
</form>
<?php
$empty_state = [
    'thing' => 'tickets',
    'icon' => 'fa-life-ring',
    'filtered' => $q !== '' || $active_filters || $assigned_filter !== '' || $client_filter,
    'views' => ['show', 'view'],
    'view' => $show === 'open' ? ['title' => 'No open tickets', 'text' => 'Nothing is waiting to be worked on.', 'icon' => 'fa-check'] : emptyStatePill($shows, $show),
    'text' => 'Tickets come in by email and from the client portal, or add one here.',
    'actions' => $can_edit ? [['label' => 'New Ticket', 'modal' => "modals/ticket/ticket_add.php?$client_url", 'size' => 'lg']] : [],
];
require_once "../includes/filter_footer.php";
?>
