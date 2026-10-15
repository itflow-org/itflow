<?php
/*
 * Ticket board
 *
 * Included by tickets.php, which owns the filters and the views and sets the
 * query parts this runs - the board shows exactly what the list would. One
 * column per active status (Closed has none), each card the ticket at a
 * glance: whose move it is, when it is due, who it is for and who has it.
 * Drag a card to change its status - into Resolved resolves it (and tells
 * the client, as Resolve does), out of Resolved reopens it. js/tickets_kanban.js
 * does the dragging, agent/ajax.php the saving.
 */

isset($mysqli) || die("Direct file access is not allowed");

$columns = [];
$sql_columns = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name, ticket_status_color, ticket_status_pauses_sla FROM ticket_statuses
    WHERE ticket_status_active = 1 AND ticket_status_id <> " . TICKET_STATUS_CLOSED . " ORDER BY ticket_status_order, ticket_status_id");
while ($row = mysqli_fetch_assoc($sql_columns)) {
    $columns[intval($row['ticket_status_id'])] = $row + ['tickets' => []];
}

$ordering_sql = intval($config_ticket_ordering) === 1
    ? "ticket_order ASC, ticket_id DESC"
    : "CASE ticket_priority WHEN 'Urgent' THEN 0 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END, ticket_id DESC";

$sql = mysqli_query($mysqli, "SELECT $ticket_select_columns FROM tickets $ticket_joins $ticket_where ORDER BY $ordering_sql");

$board_count = 0;
$off_board_count = 0;
while ($row = mysqli_fetch_assoc($sql)) {
    $status_id = intval($row['ticket_status_id']);
    if (isset($columns[$status_id])) {
        $columns[$status_id]['tickets'][intval($row['ticket_id'])] = $row;
        $board_count++;
    } else {
        // In a status with no column - one that has since been switched off
        $off_board_count++;
    }
}

$board_ids = [];
foreach ($columns as $column) {
    $board_ids = array_merge($board_ids, array_keys($column['tickets']));
}
$last_replies = ticketLastReplies($board_ids);
$task_counts = ticketTaskCounts($board_ids);

?>

<link rel="stylesheet" href="css/ticket_kanban.css">

<?php if ($off_board_count) { ?>
    <div class="alert alert-secondary py-2 mx-3 mt-3 mb-0 small">
        <i class="fas fa-fw fa-info-circle me-1"></i>
        <?= $off_board_count ?> matching ticket<?= $off_board_count === 1 ? ' is' : 's are' ?> in a status that is switched off, so not on the board.
        <a href="<?= escapeHtml($filter_url(['view' => 'list'])) ?>">See them in the list</a>
    </div>
<?php } ?>

<div class="kanban-board" id="kanban-board" data-csrf="<?= escapeHtml($_SESSION['csrf_token']) ?>"
     data-can-edit="<?= $can_edit ? 1 : 0 ?>" data-moving-columns="<?= intval($config_ticket_moving_columns) ?>" data-ordering="<?= intval($config_ticket_ordering) ?>">

    <?php foreach ($columns as $status_id => $column) {
        $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $column['ticket_status_color']) ? $column['ticket_status_color'] : 'var(--bs-secondary)';
        ?>
        <section class="kanban-column" data-status-id="<?= $status_id ?>" style="--itflow-status-color: <?= $color ?>">
            <header class="kanban-column-header" <?= intval($config_ticket_moving_columns) === 1 && $can_edit ? 'title="Drag to move this column"' : '' ?>>
                <span class="kanban-column-dot" aria-hidden="true"></span>
                <span class="kanban-column-name text-truncate"><?= escapeHtml($column['ticket_status_name']) ?></span>
                <?php if (intval($column['ticket_status_pauses_sla']) && $status_id !== TICKET_STATUS_RESOLVED) { ?>
                    <i class="fas fa-pause small text-body-secondary" title="The SLA clock is paused in this status"></i>
                <?php } ?>
                <span class="badge rounded-pill bg-body-secondary text-body-secondary ms-auto kanban-column-count"><?= count($column['tickets']) ?></span>
            </header>

            <div class="kanban-status" data-status-id="<?= $status_id ?>" data-empty="<?= $status_id === TICKET_STATUS_RESOLVED ? 'Drop here to resolve' : 'Nothing here' ?>">
                <?php foreach ($column['tickets'] as $ticket_id => $row) {
                    $row_client_id = intval($row['ticket_client_id']);
                    $reference = escapeHtml(ticketReference($row));
                    $url = escapeHtml(ticketUrl($ticket_id, $row_client_id));
                    $is_open = ticketIsOpen($row);
                    $move = ticketWhoseMove($row);
                    $sla = ticketSlaState($row);
                    $due = ticketDueState($row);
                    $attention = $is_open ? ticketAttention($sla, $due) : '';
                    $schedule = $is_open ? $row['ticket_schedule'] : null;
                    $scheduled_today = $schedule && substr($schedule, 0, 10) === date('Y-m-d');
                    $reply = $last_replies[$ticket_id] ?? null;
                    $mine = intval($row['ticket_assigned_to']) === intval($session_user_id);
                    $flags = trim(ticketDuePill($due) . ' ' . (in_array($sla['key'], ['breached', 'at_risk', 'running'], true) ? ticketSlaPill($sla) : ''));
                    ?>
                    <article class="kanban-card itflow-ticket-card <?= $attention ? "is-$attention" : '' ?>" data-ticket-id="<?= $ticket_id ?>" data-ticket-status-id="<?= intval($row['ticket_status']) ?>">
                        <div class="d-flex align-items-center gap-2 small">
                            <span class="itflow-ticket-move <?= $move === 'us' ? 'is-ours' : '' ?>" <?= $move === 'us' ? 'title="Needs a reply from us"' : 'aria-hidden="true"' ?>></span>
                            <a class="font-monospace text-body-secondary" href="<?= $url ?>"><?= $reference ?></a>
                            <span class="ms-auto"><?= ticketPriorityHtml($row['ticket_priority'], 'detail') ?></span>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-body-secondary p-0 px-1" type="button" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket.php?id=<?= $ticket_id ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <a class="dropdown-item" href="<?= $url ?>" target="_blank">
                                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open in a new tab
                                    </a>
                                    <?php if ($can_edit) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <?php if (!$mine) { ?>
                                            <a class="dropdown-item" href="post.php?assign_ticket_to_me=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-hand-paper text-secondary me-2"></i>Assign to me
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <a class="itflow-ticket-card-subject" href="<?= $url ?>"><?= escapeHtml($row['ticket_subject']) ?></a>

                        <div class="small text-body-secondary text-truncate">
                            <?php if (!$client_url) { ?>
                                <?= $row_client_id ? escapeHtml($row['client_name']) : '<i class="fas fa-building me-1"></i>Internal' ?>
                                <?php if (intval($row['contact_id'])) { ?><span class="mx-1">&middot;</span><?php } ?>
                            <?php } ?>
                            <?php if (intval($row['contact_id'])) { ?>
                                <?= contactAvatar($row_client_id, $row['contact_id'], $row['contact_name'], $row['contact_photo'] ?? '', 'xs') ?><?= escapeHtml($row['contact_name']) ?>
                            <?php } ?>
                        </div>

                        <?php if ($flags !== '' || $schedule || !empty($row['asset_name']) || isset($task_counts[$ticket_id])) { ?>
                            <div class="itflow-ticket-meta small text-body-secondary mt-1">
                                <?= $flags ?>
                                <?php if ($schedule) { ?>
                                    <span class="text-nowrap <?= $scheduled_today ? 'text-warning-emphasis fw-semibold' : '' ?>" title="Scheduled<?= intval($row['ticket_onsite']) ? ' on site' : '' ?>">
                                        <i class="fas fa-<?= intval($row['ticket_onsite']) ? 'truck' : 'calendar-check' ?> me-1"></i><?= $scheduled_today ? 'Today ' . date($config_time_format, strtotime($schedule)) : date('M j', strtotime($schedule)) ?>
                                    </span>
                                <?php } ?>
                                <?php if (!empty($row['asset_name'])) { ?>
                                    <span class="text-nowrap text-truncate itflow-ticket-asset"><i class="fas fa-desktop me-1"></i><?= escapeHtml($row['asset_name']) ?></span>
                                <?php } ?>
                                <?= ticketTasksHtml($task_counts[$ticket_id] ?? null) ?>
                            </div>
                        <?php } ?>

                        <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                            <?php $assignee = formatAssignee($row['ticket_assigned_to'], $row['user_name'], $row['user_avatar'], $is_open); ?>
                            <span class="min-w-0 text-truncate"><?= $can_edit ? quickPick('assignee', $ticket_id, (string) intval($row['ticket_assigned_to']), $assignee) : $assignee ?></span>
                            <span class="small text-body-secondary text-nowrap" title="<?= $reply ? escapeHtml(['Client' => 'The client replied', 'Public' => 'We replied', 'Internal' => 'Internal note'][$reply['ticket_reply_type']] ?? 'Last reply') . ' ' . escapeHtml($reply['ticket_reply_created_at']) : 'Opened ' . escapeHtml($row['ticket_created_at']) ?>">
                                <?php if ($reply && $reply['ticket_reply_type'] === 'Client') { ?>
                                    <i class="fas fa-reply text-warning me-1"></i>
                                <?php } elseif ($reply) { ?>
                                    <i class="fas fa-<?= $reply['ticket_reply_type'] === 'Public' ? 'paper-plane' : 'sticky-note' ?> me-1"></i>
                                <?php } else { ?>
                                    <i class="far fa-clock me-1"></i>
                                <?php } ?>
                                <?= timeAgo($reply ? $reply['ticket_reply_created_at'] : $row['ticket_created_at']) ?>
                            </span>
                        </div>
                    </article>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

</div>

<?php if (!$board_count && !$off_board_count) { ?>
    <div class="itflow-asset-empty pt-0">
        <div><?= $q !== '' || $active_filters || $assigned_filter !== '' || $client_filter ? 'No tickets match.' : 'Nothing on the board here.' ?></div>
    </div>
<?php } ?>

<?php if ($can_edit) { ?>
    <script src="../libs/SortableJS/Sortable.min.js"></script>
    <script src="js/tickets_kanban.js"></script>
<?php } ?>
