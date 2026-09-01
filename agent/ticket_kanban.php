<?php
/*
 * Ticket kanban view
 *
 * Included by tickets.php, which owns the filters and sets the query parts
 * this page runs. Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

/*
 * Columns are the active ticket statuses. Closed (5) is excluded - a board
 * column of every ticket ever closed is not something anyone drags between.
 */
$status_sql = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name, ticket_status_color, ticket_status_order, ticket_status_pauses_sla FROM ticket_statuses WHERE ticket_status_active = 1 AND ticket_status_id != 5 ORDER BY ticket_status_order");

$statuses = array();
while ($row = mysqli_fetch_assoc($status_sql)) {
    $status_id = intval($row['ticket_status_id']);
    $statuses[$status_id] = array(
        'id'         => $status_id,
        'name'       => escapeHtml($row['ticket_status_name']),
        'color'      => escapeHtml($row['ticket_status_color']),
        'pauses_sla' => intval($row['ticket_status_pauses_sla']),
        // Resolved pauses the clock by finishing the ticket rather than parking it, so
        // the header marker would be noise on that column (Closed is not a column here)
        'show_sla_pause' => intval($row['ticket_status_pauses_sla']) && $status_id != 4,
        'tickets'    => array()
    );
}

$ordering_snippet = "ORDER BY
    CASE
        WHEN ticket_priority = 'Urgent' THEN 0
        WHEN ticket_priority = 'High' THEN 1
        WHEN ticket_priority = 'Medium' THEN 2
        WHEN ticket_priority = 'Low' THEN 3
        ELSE 4
    END,
    ticket_id DESC";

if ($config_ticket_ordering == 1) {
    $ordering_snippet = "ORDER BY ticket_order ASC";
}

/*
 * Same WHERE clause the list view runs, so the filter bar means the same
 * thing in both views. The old kanban query built its own subset and quietly
 * ignored the SLA, priority and billing filters.
 */
$sql = mysqli_query($mysqli,
    "SELECT $ticket_select_columns
    FROM tickets
    $ticket_joins
    $ticket_where
    $ordering_snippet"
);

$kanban_ticket_count = 0;
$kanban_offboard_count = 0;
while ($row = mysqli_fetch_assoc($sql)) {
    $status_id = intval($row['ticket_status_id']);
    if (isset($statuses[$status_id])) {
        $statuses[$status_id]['tickets'][] = $row;
        $kanban_ticket_count++;
    } else {
        // Matched the filters but sits in a status with no column - Closed, or
        // a status that has since been deactivated
        $kanban_offboard_count++;
    }
}

$kanban = array_values($statuses);

?>

<link rel="stylesheet" href="css/ticket_kanban.css">

<?php if (!$kanban_ticket_count) { ?>
    <div class="card card-dark">
        <div class="card-body text-center text-secondary py-5">
            <i class="fas fa-fw fa-columns fa-2x mb-3"></i>
            <?php if ($kanban_offboard_count) { ?>
                <p class="mb-1">The board has no column for these tickets - closed tickets are not shown on the kanban.</p>
                <a href="<?= ticketsFilterUrl(['view' => 'list']) ?>">Show them in the list</a>
            <?php } else { ?>
                <p class="mb-1">No tickets match these filters.</p>
                <?php if ($active_filters) { ?>
                    <a href="<?= escapeHtml('?' . http_build_query(array_filter(array('client_id' => $_GET['client_id'] ?? null, 'view' => 'kanban')))) ?>">Clear the filters</a>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
<?php } elseif ($kanban_offboard_count) { ?>
    <div class="alert alert-secondary py-2">
        <i class="fas fa-fw fa-info-circle me-1"></i>
        <?= $kanban_offboard_count ?> matching ticket<?= $kanban_offboard_count == 1 ? ' is' : 's are' ?> not shown - the board has no column for closed tickets.
        <a href="<?= ticketsFilterUrl(['view' => 'list']) ?>">Show them in the list</a>
    </div>
<?php } ?>

<div class="kanban-board" id="kanban-board">

    <?php foreach ($kanban as $column) { ?>

        <div class="kanban-column" data-status-id="<?= $column['id'] ?>">

            <div class="kanban-column-header" style="border-top-color: <?= $column['color'] ?>">
                <span class="kanban-column-name"><?= $column['name'] ?></span>
                <span class="badge rounded-pill bg-secondary ms-1"><?= count($column['tickets']) ?></span>
                <?php if ($column['show_sla_pause']) { ?>
                    <i class="fas fa-fw fa-pause text-secondary ms-1" title="The resolution SLA clock is paused in this status"></i>
                <?php } ?>
            </div>

            <div class="kanban-status" data-column-name="<?= $column['name'] ?>" data-status-id="<?= $column['id'] ?>">

                <?php foreach ($column['tickets'] as $item) {

                    $item_ticket_id = intval($item['ticket_id']);
                    $item_priority = escapeHtml($item['ticket_priority']);

                    if ($item_priority == "Urgent") {
                        $ticket_priority_color = "dark";
                    } elseif ($item_priority == "High") {
                        $ticket_priority_color = "danger";
                    } elseif ($item_priority == "Medium") {
                        $ticket_priority_color = "warning";
                    } else {
                        $ticket_priority_color = "info";
                    }

                    // SLA state, same stages the ticket list colours on. A paused
                    // ticket drops its at-risk flag but keeps a recorded breach.
                    $ticket_sla_alert_stage = max(intval($item['ticket_response_sla_alert_stage']), intval($item['ticket_resolution_sla_alert_stage']));
                    if (intval($item['ticket_status_pauses_sla']) && $ticket_sla_alert_stage < 2) {
                        $ticket_sla_alert_stage = 0;
                    }
                    $ticket_sla_class = '';
                    if ($ticket_sla_alert_stage == 2) {
                        $ticket_sla_class = ' kanban-card-breached';
                    } elseif ($ticket_sla_alert_stage == 1) {
                        $ticket_sla_class = ' kanban-card-at-risk';
                    }

                    $item_client_id = intval($item['ticket_client_id']);
                    $item_has_client = $item_client_id ? "&client_id=$item_client_id" : "";
                    ?>

                    <div class="task kanban-card<?= $ticket_sla_class ?>"
                         data-ticket-id="<?= $item_ticket_id ?>"
                         data-ticket-status-id="<?= intval($item['ticket_status_id']) ?>">

                        <div class="kanban-card-top">
                            <a href="ticket.php?ticket_id=<?= $item_ticket_id . $item_has_client ?>" class="kanban-card-number">
                                <?= escapeHtml($item['ticket_prefix']) . intval($item['ticket_number']) ?>
                            </a>

                            <span class="badge text-bg-<?= $ticket_priority_color ?>"><?= $item_priority ?></span>

                            <?php if ($ticket_sla_alert_stage == 2) { ?>
                                <span class="badge bg-danger" title="SLA breached"><i class="fas fa-fw fa-stopwatch"></i></span>
                            <?php } elseif ($ticket_sla_alert_stage == 1) { ?>
                                <span class="badge bg-warning text-dark" title="SLA at risk"><i class="fas fa-fw fa-stopwatch"></i></span>
                            <?php } ?>

                            <span class="drag-handle-class" title="Drag to move"><i class="fas fa-grip-vertical"></i></span>
                        </div>

                        <a href="ticket.php?ticket_id=<?= $item_ticket_id . $item_has_client ?>" class="kanban-card-subject">
                            <?= escapeHtml($item['ticket_subject']) ?>
                        </a>

                        <div class="kanban-card-client">
                            <?php
                            if (!$client_url) {
                                echo escapeHtml($item['client_name']);
                                if (!empty($item['contact_name'])) {
                                    echo ' <span class="text-secondary">&middot; ' . escapeHtml($item['contact_name']) . '</span>';
                                }
                            } else {
                                echo escapeHtml($item['contact_name']);
                            }
                            ?>
                        </div>

                        <?php if (!empty($item['asset_name'])) { ?>
                            <div class="kanban-card-meta"><i class="fa fa-fw fa-desktop me-1"></i><?= escapeHtml($item['asset_name']) ?></div>
                        <?php } ?>

                        <div class="kanban-card-footer">
                            <span title="Assigned to">
                                <?php if (!empty($item['user_name'])) { ?>
                                    <i class="fas fa-fw fa-user me-1"></i><?= escapeHtml($item['user_name']) ?>
                                <?php } else { ?>
                                    <i class="fas fa-fw fa-user-slash me-1 text-danger"></i><span class="text-danger">Unassigned</span>
                                <?php } ?>
                            </span>
                            <span title="Created <?= escapeHtml($item['ticket_created_at']) ?>"><?= timeAgo($item['ticket_created_at']) ?></span>
                        </div>

                        <?php if (!empty($item['ticket_schedule'])) { ?>
                            <div class="kanban-card-meta"><i class="fa fa-fw fa-calendar-check me-1"></i><span class="badge bg-warning text-dark"><?= escapeHtml($item['ticket_schedule']) ?></span></div>
                        <?php } ?>

                    </div>

                <?php } ?>

            </div>
        </div>

    <?php } ?>

</div>

<script>
    const CONFIG_TICKET_MOVING_COLUMNS = <?= json_encode($config_ticket_moving_columns) ?>;
    const CONFIG_TICKET_ORDERING = <?= json_encode($config_ticket_ordering) ?>;
</script>

<script src="../libs/SortableJS/Sortable.min.js"></script>
<script src="js/tickets_kanban.js"></script>
