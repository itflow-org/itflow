<?php

/*
 * A project - what it is, who it is for and who runs it, how far along it
 * is, the time logged against the hours budgeted and when it is due, then
 * its tickets, all their tasks, what has been said on them lately and its
 * history. The side shows the scope, the details and the team that has
 * worked on it.
 *
 * Progress and time count every ticket on the project; the lists show the
 * tickets this agent may reach (an internal project can hold any client's).
 */

// Ticket list sorts are fixed here - open first, then newest
$sort = "ticket_number";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

$can_edit = projectCan(2);
$can_delete = projectCan(3);

$project = projectRow($_GET['project_id'] ?? 0);
$page_client_id = $client_url ? intval($client_id) : 0;

$not_here = !$project
    || (intval($project['project_client_id']) && !hasClientAccess($project['project_client_id']))
    || ($client_url && intval($project['project_client_id']) !== $page_client_id);

if ($not_here) { ?>
    <div class="text-center mt-5">
        <h1 class="h3 text-secondary">Nothing to see here</h1>
        <?php if ($project && (!intval($project['project_client_id']) || hasClientAccess($project['project_client_id']))) { ?>
            <!-- The right project, the wrong client in the address -->
            <a class="btn btn-primary mt-3" href="<?= escapeHtml(projectUrl($project['project_id'], $project['project_client_id'])) ?>"><i class="fas fa-fw fa-project-diagram me-2"></i>Open <?= escapeHtml(projectReference($project)) ?></a>
        <?php } ?>
        <a class="btn btn-secondary mt-3" href="projects.php<?= $client_url ? "?client_id=$page_client_id" : '' ?>"><i class="fa fa-fw fa-arrow-left me-2"></i>Projects</a>
    </div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$project_id = intval($project['project_id']);
$project_client_id = intval($project['project_client_id']);
$is_open = projectIsOpen($project);
$state = projectState($project);
$reference = escapeHtml(projectReference($project));
$project_name = escapeHtml($project['project_name']);
$stats = projectStats([$project_id])[$project_id];
$flags = projectFlags($project, $stats);
$due = projectDueState($project);
$budget_hours = floatval($project['project_budget_hours'] ?? 0);
$manager = formatAssignee($project['project_manager'], $project['user_name'], $project['user_avatar'], false, 'No manager');

// Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
$tab_title = projectReference($project);
$page_title = $project['project_name'];

// Its tickets this agent may reach - open first, then newest
$ticket_scope = clientScopeSql('ticket_client_id');
$sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_status, ticket_source,
        ticket_assigned_to, ticket_billable, ticket_invoice_id, ticket_created_at, ticket_updated_at, ticket_resolved_at, ticket_closed_at, ticket_client_id,
        ticket_due_at, ticket_sla_id, ticket_first_response_at, ticket_response_due_at, ticket_resolution_due_at,
        ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage, ticket_response_sla_met, ticket_resolution_sla_met,
        ticket_status_name, ticket_status_color, ticket_status_pauses_sla, client_name, user_name, user_avatar, asset_name,
        " . ticketLastSpokeSql() . " AS ticket_last_spoke
    FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    WHERE ticket_project_id = $project_id AND ticket_archived_at IS NULL $ticket_scope
    ORDER BY " . TICKET_OPEN_SQL . " DESC, ticket_number DESC");
$tickets = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $tickets[intval($row['ticket_id'])] = $row;
}
$ticket_ids = array_keys($tickets);
$last_replies = ticketLastReplies($ticket_ids);
$task_counts = ticketTaskCounts($ticket_ids);
$hidden_tickets = $stats['tickets'] - count($tickets);

// Time on each ticket
$ticket_seconds = [];
if ($ticket_ids) {
    $sql = mysqli_query($mysqli, "SELECT ticket_reply_ticket_id, SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies
        WHERE ticket_reply_ticket_id IN (" . implode(',', $ticket_ids) . ") AND ticket_reply_archived_at IS NULL GROUP BY ticket_reply_ticket_id");
    while ($row = mysqli_fetch_row($sql)) {
        $ticket_seconds[intval($row[0])] = intval($row[1]);
    }
}

// Every task on those tickets, by ticket
$tasks_by_ticket = [];
if ($ticket_ids) {
    $sql = mysqli_query($mysqli, "SELECT task_id, task_name, task_ticket_id, task_completed_at, user_name AS completed_by
        FROM tasks LEFT JOIN users ON user_id = task_completed_by
        WHERE task_ticket_id IN (" . implode(',', $ticket_ids) . ")
        ORDER BY task_order ASC, task_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tasks_by_ticket[intval($row['task_ticket_id'])][] = $row;
    }
}

$activity = projectActivity($project_id, 25);
$team = projectTeam($project_id);

// Templates to add tickets from
$has_templates = $can_edit && $is_open && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM project_templates WHERE project_template_archived_at IS NULL LIMIT 1"));

// Its history, from the audit log
$history = auditLogHistory('Project', $project_id);

$project_tabs = [
    'tickets' => ['label' => 'Tickets', 'icon' => 'fa-life-ring', 'count' => count($tickets), 'badge' => $stats['open']],
    'tasks' => ['label' => 'Tasks', 'icon' => 'fa-tasks', 'count' => $stats['tasks']],
    'activity' => ['label' => 'Activity', 'icon' => 'fa-comments', 'count' => count($activity)],
    'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => $history['count']],
];

$new_ticket_url = "modals/ticket/ticket_add.php?" . ($project_client_id ? "client_id=$project_client_id&" : '') . "project_id=$project_id";
$contact_archived = !empty($project['contact_archived_at']);

?>

<ol class="breadcrumb d-print-none">
    <?php if ($client_url) { ?>
        <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></li>
    <?php } ?>
    <li class="breadcrumb-item"><a href="projects.php<?= $client_url ? "?client_id=$client_id" : '' ?>">Projects</a></li>
    <li class="breadcrumb-item active"><?= $reference ?></li>
</ol>

<!-- What it is -->
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="itflow-asset-icon itflow-asset-icon-lg itflow-project-icon <?= $is_open ? '' : 'is-done' ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= $state === 'completed' ? 'flag-checkered' : 'project-diagram' ?>"></i></span>

        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span><?= $project_name ?></span>
                <span class="fs-6"><?= projectStatePill($project) ?></span>
                <span class="fs-6"><?= projectDuePill($project) ?></span>
            </h1>
            <div class="text-body-secondary small d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
                <span class="font-monospace"><?= $reference ?></span>
                <?php if ($project_client_id) { ?>
                    <a class="text-body-secondary" href="client_overview.php?client_id=<?= $project_client_id ?>"><i class="fas fa-fw fa-users me-1"></i><?= escapeHtml($project['client_name']) ?></a>
                <?php } else { ?>
                    <span><i class="fas fa-fw fa-building me-1"></i>Internal</span>
                <?php } ?>
                <span class="d-inline-flex align-items-center" title="Project manager"><i class="fas fa-fw fa-user-tie me-1"></i>
                    <?= $can_edit && $is_open ? quickPick('project_manager', $project_id, (string) intval($project['project_manager']), $manager, 'detail') : $manager ?>
                </span>
                <?php if (intval($project['project_contact_id']) && $project['contact_name'] !== null) { ?>
                    <span class="d-inline-flex align-items-center" title="Client contact"><?= contactLink($project_client_id, $project['project_contact_id'], $project['contact_name'], $project['contact_photo'] ?? '', ['archived' => $contact_archived]) ?></span>
                <?php } ?>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 d-print-none">
            <?php if ($can_edit && $is_open) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $new_ticket_url ?>">
                    <i class="fas fa-plus me-2"></i>New Ticket
                </button>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-link me-2"></i>Add tickets</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project/project_link_ticket.php?project_id=<?= $project_id ?>">
                            <i class="fas fa-fw fa-life-ring text-secondary me-2"></i>Tickets already raised
                        </a>
                        <?php if ($has_templates) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project/project_apply_template.php?project_id=<?= $project_id ?>">
                                <i class="fas fa-fw fa-cubes text-secondary me-2"></i>From a project template
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($can_edit && !$project['project_archived_at']) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/project/project_edit.php?id=<?= $project_id ?>">
                    <i class="fas fa-edit me-2"></i>Edit
                </button>
            <?php } ?>
            <?php if ($can_edit && $is_open) { ?>
                <?php if ($stats['open']) { ?>
                    <button type="button" class="btn btn-outline-success" disabled title="<?= $stats['open'] ?> of its tickets <?= $stats['open'] === 1 ? 'is' : 'are' ?> still open - resolve or close them first">
                        <i class="fas fa-check me-2"></i>Mark complete
                    </button>
                <?php } else { ?>
                    <a class="btn <?= $stats['tickets'] ? 'btn-success' : 'btn-outline-success' ?> confirm-link" href="post.php?complete_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="<?= $reference ?> is marked complete.">
                        <i class="fas fa-check me-2"></i>Mark complete
                    </a>
                <?php } ?>
            <?php } elseif ($can_edit && $state === 'completed') { ?>
                <a class="btn btn-outline-secondary" href="post.php?reopen_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                    <i class="fas fa-redo me-2"></i>Reopen
                </a>
            <?php } ?>
            <?php if ($can_edit) { ?>
                <div class="dropdown dropstart">
                    <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="tickets.php?<?= $project_client_id ? "client_id=$project_client_id&" : '' ?>project=<?= $project_id ?>&show=all">
                            <i class="fas fa-fw fa-life-ring text-secondary me-2"></i>Its tickets on the ticket list
                        </a>
                        <div class="dropdown-divider"></div>
                        <?php if ($project['project_archived_at']) { ?>
                            <a class="dropdown-item" href="post.php?restore_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                            </a>
                        <?php } else { ?>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="<?= $reference ?> moves to Archived. Its tickets stay as they are.">
                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                            </a>
                        <?php } ?>
                        <?php if ($can_delete) { ?>
                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                data-confirm-text="<?= $reference ?> goes for good. Its tickets stay, no longer part of a project.">
                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php foreach ($flags as $flag) {
    if ($flag['key'] === 'soon') {
        continue; // the due pill says it
    } ?>
    <div class="alert alert-<?= $flag['tone'] ?> py-2 d-flex align-items-center gap-2 mb-3">
        <i class="fas fa-fw fa-<?= ['overdue' => 'calendar-times', 'over_budget' => 'hourglass-end', 'ready' => 'flag-checkered', 'stalled' => 'hourglass-half'][$flag['key']] ?? 'info-circle' ?>"></i>
        <span class="flex-grow-1"><?= escapeHtml($flag['text']) ?></span>
        <?php if ($flag['key'] === 'ready' && $can_edit) { ?>
            <a class="btn btn-sm btn-success confirm-link" href="post.php?complete_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="<?= $reference ?> is marked complete.">Mark complete</a>
        <?php } ?>
    </div>
<?php } ?>

<!-- How it stands -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 mb-0 itflow-project-stat">
            <div class="card-body">
                <div class="itflow-project-stat-label"><i class="fas fa-fw fa-life-ring me-1"></i>Tickets done</div>
                <div class="itflow-project-stat-value"><?= $stats['done'] ?><span class="text-body-secondary fs-6 fw-normal"> / <?= $stats['tickets'] ?></span></div>
                <div class="progress mt-2" role="progressbar" aria-label="Tickets done" aria-valuenow="<?= $stats['tickets'] ? round($stats['done'] / $stats['tickets'] * 100) : 0 ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar <?= $stats['tickets'] && $stats['done'] === $stats['tickets'] ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $stats['tickets'] ? round($stats['done'] / $stats['tickets'] * 100) : 0 ?>%"></div>
                </div>
                <div class="small text-body-secondary mt-2">
                    <?= $stats['tickets'] ? $stats['open'] . ' open' : 'No tickets yet' ?>
                    <?php if ($stats['overdue']) { ?><span class="text-danger-emphasis ms-1">· <?= $stats['overdue'] ?> past due</span><?php } ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 mb-0 itflow-project-stat">
            <div class="card-body">
                <div class="itflow-project-stat-label"><i class="fas fa-fw fa-tasks me-1"></i>Tasks done</div>
                <div class="itflow-project-stat-value"><?= $stats['tasks_done'] ?><span class="text-body-secondary fs-6 fw-normal"> / <?= $stats['tasks'] ?></span></div>
                <div class="progress mt-2" role="progressbar" aria-label="Tasks done" aria-valuenow="<?= $stats['tasks'] ? round($stats['tasks_done'] / $stats['tasks'] * 100) : 0 ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-secondary" style="width: <?= $stats['tasks'] ? round($stats['tasks_done'] / $stats['tasks'] * 100) : 0 ?>%"></div>
                </div>
                <div class="small text-body-secondary mt-2"><?= $stats['tasks'] ? ($stats['tasks'] - $stats['tasks_done']) . ' to do' : 'No tasks on its tickets' ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 mb-0 itflow-project-stat">
            <div class="card-body">
                <div class="itflow-project-stat-label"><i class="far fa-fw fa-clock me-1"></i>Time logged</div>
                <?php
                $used = $budget_hours > 0 ? $stats['seconds'] / ($budget_hours * 3600) : 0;
                $budget_tone = $used > 1 ? 'bg-danger' : ($used >= .8 ? 'bg-warning' : 'bg-info');
                ?>
                <div class="itflow-project-stat-value <?= $used > 1 ? 'text-danger-emphasis' : '' ?>"><?= projectHoursText($stats['seconds']) ?>
                    <?php if ($budget_hours > 0) { ?><span class="text-body-secondary fs-6 fw-normal"> / <?= projectHoursText($budget_hours * 3600) ?></span><?php } ?>
                </div>
                <?php if ($budget_hours > 0) { ?>
                    <div class="progress mt-2" role="progressbar" aria-label="Budget used" aria-valuenow="<?= min(100, round($used * 100)) ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar <?= $budget_tone ?>" style="width: <?= min(100, round($used * 100)) ?>%"></div>
                    </div>
                    <div class="small text-body-secondary mt-2">
                        <?= $used > 1 ? projectHoursText($stats['seconds'] - $budget_hours * 3600) . ' over budget' : projectHoursText($budget_hours * 3600 - $stats['seconds']) . ' left of the budget' ?>
                    </div>
                <?php } else { ?>
                    <div class="small text-body-secondary mt-2">No budget set<?= $can_edit && !$project['project_archived_at'] ? ' - add one in Edit' : '' ?></div>
                <?php } ?>
                <?php if ($stats['billable_seconds']) { ?>
                    <div class="small text-body-secondary" title="Time on billable tickets"><?= projectHoursText($stats['billable_seconds']) ?> billable<?= $stats['unbilled_seconds'] ? ', <span class="text-warning-emphasis">' . projectHoursText($stats['unbilled_seconds']) . ' not invoiced</span>' : '' ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 mb-0 itflow-project-stat">
            <div class="card-body">
                <div class="itflow-project-stat-label"><i class="far fa-fw fa-calendar me-1"></i><?= $due['key'] === 'done' ? 'Completed' : 'Due' ?></div>
                <?php if ($due['key'] === 'done') { ?>
                    <div class="itflow-project-stat-value"><?= escapeHtml(date('M j, Y', strtotime($project['project_completed_at']))) ?></div>
                    <div class="small mt-2 <?= $due['late'] ? 'text-warning-emphasis' : 'text-body-secondary' ?>">
                        <?= $project['project_due'] ? ($due['late'] ? $due['days_late'] . ' day' . ($due['days_late'] === 1 ? '' : 's') . ' after its due date' : 'On time - due ' . escapeHtml(date('M j', strtotime($project['project_due'])))) : 'It had no due date' ?>
                    </div>
                <?php } elseif ($project['project_due']) { ?>
                    <div class="itflow-project-stat-value <?= $due['key'] === 'overdue' ? 'text-danger-emphasis' : '' ?>"><?= escapeHtml(date('M j, Y', strtotime($project['project_due']))) ?></div>
                    <div class="small mt-2 <?= ['overdue' => 'text-danger-emphasis', 'soon' => 'text-warning-emphasis'][$due['key']] ?? 'text-body-secondary' ?>">
                        <?= $due['key'] === 'overdue' ? abs($due['days']) . ' day' . (abs($due['days']) === 1 ? '' : 's') . ' overdue' : 'Due ' . projectDaysText($due['days']) ?>
                    </div>
                <?php } else { ?>
                    <div class="itflow-project-stat-value text-body-secondary">No due date</div>
                <?php } ?>
                <?php if ($project['project_start']) { ?>
                    <div class="small text-body-secondary"><?= $state === 'upcoming' ? 'Starts' : 'Started' ?> <?= escapeHtml(date('M j, Y', strtotime($project['project_start']))) ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- Its work -->
    <div class="col-xl-8 col-lg-7">
        <div class="card mb-3 itflow-asset-related">
            <div class="card-header py-2">
                <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                    <?php foreach ($project_tabs as $tab_key => $tab) { ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="project-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#project-pane-<?= $tab_key ?>" data-project-tab="<?= $tab_key ?>" type="button" role="tab">
                                <i class="fas fa-fw <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
                                <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                                <?php if (!empty($tab['badge'])) { ?>
                                    <span class="badge rounded-pill text-bg-danger" title="Open"><?= intval($tab['badge']) ?> open</span>
                                <?php } ?>
                            </button>
                        </li>
                    <?php } ?>
                </ul>
            </div>
            <div class="tab-content">

                <!-- Tickets -->
                <div class="tab-pane fade" id="project-pane-tickets" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">
                            <?= $tickets ? 'Open ones first.' : 'No tickets on it yet.' ?>
                            <?php if ($hidden_tickets > 0) { ?>
                                <?= $hidden_tickets ?> more belong to clients you can't reach - they still count towards its progress.
                            <?php } ?>
                        </span>
                        <?php if ($can_edit && $tickets) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_assign.php" data-bulk="true">
                                        <i class="fas fa-fw fa-user-check me-2"></i>Assign
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_edit_priority.php" data-bulk="true">
                                        <i class="fas fa-fw fa-thermometer-half me-2"></i>Set priority
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_edit_category.php" data-bulk="true">
                                        <i class="fas fa-fw fa-tag me-2"></i>Set category
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_reply.php" data-modal-size="lg" data-bulk="true">
                                        <i class="fas fa-fw fa-paper-plane me-2"></i>Reply or add a note
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_bulk_resolve.php" data-modal-size="lg" data-bulk="true">
                                        <i class="fas fa-fw fa-check me-2"></i>Resolve
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="remove_tickets_from_project"
                                        data-confirm-text="The tickets stay as they are, just no longer part of this project.">
                                        <i class="fas fa-fw fa-unlink me-2"></i>Take off the project
                                    </button>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($tickets) { ?>
                        <form id="bulkActions" action="post.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="project_id" value="<?= $project_id ?>">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 itflow-ticket-list itflow-ticket-table">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <?php if ($can_edit) { ?>
                                            <th class="checkbox-column border-end">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all">
                                                </div>
                                            </th>
                                        <?php } ?>
                                        <th>Ticket</th>
                                        <?php if (!$project_client_id) { ?><th>Client</th><?php } ?>
                                        <th>Status</th>
                                        <th>Assigned</th>
                                        <th>Due</th>
                                        <th>Time</th>
                                        <th>Activity</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($tickets as $ticket_id => $row) {
                                        $ticket_open = ticketIsOpen($row);
                                        $ticket_closed = !empty($row['ticket_closed_at']);
                                        $ticket_url = escapeHtml(ticketUrl($ticket_id, $row['ticket_client_id']));
                                        $move = ticketWhoseMove($row);
                                        $sla = ticketSlaState($row);
                                        $ticket_due = ticketDueState($row);
                                        $attention = $ticket_open ? ticketAttention($sla, $ticket_due) : '';
                                        $assignee = formatAssignee($row['ticket_assigned_to'], $row['user_name'], $row['user_avatar'], $ticket_open);
                                        $due_html = trim(ticketDuePill($ticket_due) . ' ' . ticketSlaPill($sla));
                                        ?>
                                        <tr class="itflow-ticket-row <?= $attention ? "is-$attention" : '' ?> <?= $ticket_open ? '' : 'is-done' ?>">
                                            <?php if ($can_edit) { ?>
                                                <td class="checkbox-column bg-light border-end">
                                                    <div class="form-check">
                                                        <input class="form-check-input bulk-select" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>" aria-label="Select <?= escapeHtml(ticketReference($row)) ?>">
                                                    </div>
                                                </td>
                                            <?php } ?>
                                            <td class="itflow-ticket-main">
                                                <div class="d-flex align-items-start gap-2">
                                                    <span class="itflow-ticket-move <?= $move === 'us' ? 'is-ours' : '' ?>" <?= $move === 'us' ? 'title="Needs a reply from us"' : 'aria-hidden="true"' ?>></span>
                                                    <div class="min-w-0">
                                                        <a class="<?= $ticket_open ? 'fw-semibold text-body' : 'text-body-secondary' ?> itflow-ticket-subject" href="<?= $ticket_url ?>"><?= escapeHtml($row['ticket_subject']) ?></a>
                                                        <div class="itflow-ticket-meta small text-body-secondary">
                                                            <a class="font-monospace text-body-secondary" href="<?= $ticket_url ?>"><?= escapeHtml(ticketReference($row)) ?></a>
                                                            <?= ticketPriorityHtml($row['ticket_priority'], 'detail') ?>
                                                            <?= ticketSourceIcon($row['ticket_source']) ?>
                                                            <?php if (!empty($row['asset_name'])) { ?>
                                                                <span class="text-nowrap text-truncate itflow-ticket-asset" title="Asset"><i class="fas fa-desktop me-1"></i><?= escapeHtml($row['asset_name']) ?></span>
                                                            <?php } ?>
                                                            <?= ticketTasksHtml($task_counts[$ticket_id] ?? null) ?>
                                                            <a class="ajax-modal text-body-secondary" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket.php?id=<?= $ticket_id ?>" title="Quick look"><i class="fas fa-eye"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php if (!$project_client_id) { ?>
                                                <td class="small text-truncate itflow-ticket-who-cell"><?= intval($row['ticket_client_id']) ? escapeHtml($row['client_name']) : '<span class="text-body-secondary">Internal</span>' ?></td>
                                            <?php } ?>
                                            <td class="text-nowrap">
                                                <?php if ($can_edit && $ticket_open) { ?>
                                                    <?= quickPick('status', $ticket_id, (string) intval($row['ticket_status']), ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail')) ?>
                                                <?php } else { ?>
                                                    <?= ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail') ?>
                                                <?php } ?>
                                                <?php if ($move === 'client') { ?>
                                                    <div class="small text-body-secondary mt-1"><i class="fas fa-hourglass-half me-1"></i>With client</div>
                                                <?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?= $can_edit && !$ticket_closed ? quickPick('assignee', $ticket_id, (string) intval($row['ticket_assigned_to']), $assignee) : $assignee ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?= $due_html !== '' ? '<div class="d-flex flex-column align-items-start gap-1">' . $due_html . '</div>' : '<span class="text-body-secondary">-</span>' ?>
                                            </td>
                                            <td class="text-nowrap small"><?= !empty($ticket_seconds[$ticket_id]) ? projectHoursText($ticket_seconds[$ticket_id]) : '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="small itflow-ticket-activity"><?= ticketActivityHtml($last_replies[$ticket_id] ?? null, $row) ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">
                            <i class="fas fa-life-ring fa-2x mb-2 opacity-50"></i>
                            <div>Raise its tickets here, or add ones already raised.</div>
                            <?php if ($can_edit && $is_open) { ?>
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $new_ticket_url ?>"><i class="fas fa-plus me-2"></i>New Ticket</button>
                                    <?php if ($has_templates) { ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/project/project_apply_template.php?project_id=<?= $project_id ?>"><i class="fas fa-cubes me-2"></i>From a template</button>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <!-- Tasks, ticket by ticket -->
                <div class="tab-pane fade" id="project-pane-tasks" role="tabpanel">
                    <?php if ($tasks_by_ticket) { ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($tickets as $ticket_id => $row) {
                                if (empty($tasks_by_ticket[$ticket_id])) {
                                    continue;
                                }
                                $ticket_open = ticketIsOpen($row);
                                $ticket_closed = !empty($row['ticket_closed_at']);
                                $ticket_tasks = $tasks_by_ticket[$ticket_id];
                                $ticket_done = count(array_filter(array_column($ticket_tasks, 'task_completed_at'))); ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <?= ticketLink($row) ?>
                                        <span class="ms-auto small text-body-secondary text-nowrap"><?= $ticket_done ?>/<?= count($ticket_tasks) ?> done</span>
                                    </div>
                                    <ul class="list-unstyled mb-0 itflow-project-tasks">
                                        <?php foreach ($ticket_tasks as $task) {
                                            $task_id = intval($task['task_id']);
                                            $task_done = !empty($task['task_completed_at']); ?>
                                            <li class="d-flex align-items-start gap-2 <?= $task_done ? 'text-body-secondary' : '' ?>">
                                                <?php if ($can_edit && !$ticket_closed) { ?>
                                                    <a href="post.php?<?= $task_done ? 'undo_complete_task' : 'complete_task' ?>=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="<?= $task_done ? 'Mark not done' : 'Mark done' ?>">
                                                        <i class="far fa-fw fa-<?= $task_done ? 'check-square text-success' : 'square text-secondary' ?>"></i>
                                                    </a>
                                                <?php } else { ?>
                                                    <i class="far fa-fw fa-<?= $task_done ? 'check-square text-success' : 'square text-secondary' ?>"></i>
                                                <?php } ?>
                                                <span class="flex-grow-1 <?= $task_done ? 'text-decoration-line-through' : '' ?>"><?= escapeHtml($task['task_name']) ?></span>
                                                <?php if ($task_done) { ?>
                                                    <span class="small text-nowrap" title="<?= escapeHtml($task['task_completed_at']) ?>"><?= escapeHtml(trim(($task['completed_by'] ?? '') . ', ' . timeAgo($task['task_completed_at']), ', ')) ?></span>
                                                <?php } ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">
                            <i class="fas fa-tasks fa-2x mb-2 opacity-50"></i>
                            <div>No tasks on its tickets. Tasks added to a ticket, or brought in by a template, show up here.</div>
                        </div>
                    <?php } ?>
                </div>

                <!-- History, from the audit log -->
                <div class="tab-pane fade" id="project-pane-history" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Changes to the project - newest first.</span>
                    </div>
                    <?= auditLogHistoryHtml($history, ['client_id' => $project_client_id]) ?>
                </div>

                <!-- What has been said lately -->
                <div class="tab-pane fade" id="project-pane-activity" role="tabpanel">
                    <?php if ($activity) { ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($activity as $reply) {
                                $type = $reply['ticket_reply_type'];
                                $who = $type === 'Client' ? ($reply['contact_name'] ?? '') : ($reply['user_name'] ?? '');
                                [$icon, $label] = ['Client' => ['fa-reply text-warning', 'The client replied'], 'Public' => ['fa-paper-plane text-primary', 'Reply'], 'Internal' => ['fa-sticky-note text-body-secondary', 'Internal note']][$type] ?? ['fa-sticky-note text-body-secondary', 'Note'];
                                $time = $reply['ticket_reply_time_worked'] && $reply['ticket_reply_time_worked'] !== '00:00:00' ? $reply['ticket_reply_time_worked'] : ''; ?>
                                <div class="list-group-item d-flex gap-3">
                                    <i class="fas fa-fw <?= $icon ?> mt-1" title="<?= $label ?>"></i>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center column-gap-2 small">
                                            <span class="fw-semibold"><?= escapeHtml($who !== '' ? $who : 'Someone') ?></span>
                                            <span class="text-body-secondary"><?= $type === 'Internal' ? 'noted on' : 'on' ?></span>
                                            <?= ticketLink($reply) ?>
                                            <span class="ms-auto text-body-secondary text-nowrap" title="<?= escapeHtml($reply['ticket_reply_created_at']) ?>">
                                                <?php if ($time) { ?><span class="me-2" title="Time logged"><i class="far fa-clock me-1"></i><?= escapeHtml(substr($time, 0, 5)) ?></span><?php } ?><?= timeAgo($reply['ticket_reply_created_at']) ?>
                                            </span>
                                        </div>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(projectReplySnippet($reply['ticket_reply'])) ?></div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">
                            <i class="fas fa-comments fa-2x mb-2 opacity-50"></i>
                            <div>Nothing said on its tickets yet.</div>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>
    </div>

    <!-- What it is for, the details, and who is on it -->
    <div class="col-xl-4 col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-align-left text-secondary me-2"></i>Scope</h3>
            </div>
            <div class="card-body itflow-project-scope">
                <?php if (trim((string) $project['project_description']) !== '') { ?>
                    <?= nl2br(escapeHtml($project['project_description'])) ?>
                <?php } else { ?>
                    <span class="text-body-secondary">No description<?= $can_edit && !$project['project_archived_at'] ? ' - what is in and out of the work, and what "done" looks like, goes here.' : '' ?></span>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-info-circle text-secondary me-2"></i>Details</h3>
            </div>
            <div class="card-body">
                <dl class="itflow-asset-dl mb-0">
                    <dt>Client</dt>
                    <dd><?= $project_client_id ? '<a href="client_overview.php?client_id=' . $project_client_id . '">' . escapeHtml($project['client_name']) . '</a>' : '<span class="text-body-secondary">Internal - our own work</span>' ?></dd>
                    <?php if ($project_client_id) { ?>
                        <dt>Contact</dt>
                        <dd>
                            <?php if (intval($project['project_contact_id']) && $project['contact_name'] !== null) { ?>
                                <?= contactLink($project_client_id, $project['project_contact_id'], $project['contact_name'], $project['contact_photo'] ?? '', ['archived' => $contact_archived]) ?>
                                <?php if ($project['contact_title']) { ?><div class="small text-body-secondary"><?= escapeHtml($project['contact_title']) ?></div><?php } ?>
                                <?php if ($project['contact_email']) { ?>
                                    <div class="small"><a href="mailto:<?= escapeHtml($project['contact_email']) ?>"><?= escapeHtml($project['contact_email']) ?></a></div>
                                <?php } ?>
                                <?php $contact_phone = $project['contact_phone'] ? formatPhoneNumber($project['contact_phone'], $project['contact_phone_country_code']) : ($project['contact_mobile'] ? formatPhoneNumber($project['contact_mobile'], $project['contact_mobile_country_code']) : ''); ?>
                                <?php if ($contact_phone) { ?><div class="small"><?= escapeHtml($contact_phone) ?></div><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">Nobody named</span>
                            <?php } ?>
                        </dd>
                    <?php } ?>
                    <dt>Manager</dt>
                    <dd><?= $manager ?></dd>
                    <?php if ($project['project_start']) { ?>
                        <dt>Start</dt><dd><?= escapeHtml(date('D M j, Y', strtotime($project['project_start']))) ?></dd>
                    <?php } ?>
                    <dt>Due</dt><dd><?= $project['project_due'] ? escapeHtml(date('D M j, Y', strtotime($project['project_due']))) : '<span class="text-body-secondary">-</span>' ?></dd>
                    <dt>Budget</dt><dd><?= $budget_hours > 0 ? projectHoursText($budget_hours * 3600) : '<span class="text-body-secondary">-</span>' ?></dd>
                    <dt>Created</dt><dd title="<?= escapeHtml($project['project_created_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($project['project_created_at']))) ?></dd>
                    <?php if ($project['project_completed_at']) { ?>
                        <dt>Completed</dt><dd><?= escapeHtml(date('M j, Y', strtotime($project['project_completed_at']))) ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-users text-secondary me-2"></i>Team</h3>
            </div>
            <?php if ($team) { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($team as $user_id => $member) { ?>
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <?= formatAssignee($user_id, $member['user_name'], $member['user_avatar'], false) ?>
                            <?php if ($user_id === intval($project['project_manager'])) { ?><span class="small text-body-secondary">manager</span><?php } ?>
                            <span class="ms-auto small text-body-secondary text-nowrap">
                                <?php if ($member['open']) { ?><span class="me-2" title="Open tickets of this project assigned to them"><i class="fas fa-life-ring me-1"></i><?= $member['open'] ?></span><?php } ?>
                                <span title="Time logged on its tickets"><i class="far fa-clock me-1"></i><?= projectHoursText($member['seconds']) ?></span>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <div class="card-body small text-body-secondary">Agents who work its tickets show up here, with their time.</div>
            <?php } ?>
        </div>
    </div>

</div>

<script>
    /*
     * The tab last looked at opens again - or the one in the address. Bootstrap
     * loads in the footer, so this waits for it.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var key = 'itflow.projectTab';
        var tabs = document.querySelectorAll('[data-project-tab]');
        if (!tabs.length) {
            return;
        }
        function byName(name) {
            return document.querySelector('[data-project-tab="' + name + '"]');
        }
        var remembered = '';
        try { remembered = localStorage.getItem(key) || ''; } catch (e) {}
        var start = byName(location.hash.replace('#', '')) || byName(remembered) || tabs[0];
        bootstrap.Tab.getOrCreateInstance(start).show();
        tabs.forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function () {
                var name = tab.getAttribute('data-project-tab');
                try { localStorage.setItem(key, name); } catch (e) {}
                history.replaceState(null, '', location.pathname + location.search + '#' + name);
            });
        });
    });
</script>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
