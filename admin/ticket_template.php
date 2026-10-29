<?php

/*
 * One ticket template - the ticket it starts (subject and details, with its
 * placeholders marked), what it asks for when a ticket is raised (its
 * fill-ins: set up, dragged into order), how it sets the ticket up, its tasks
 * (added, changed, dragged into order, some for one answer only) and their
 * time, and its use: how many tickets it has raised, the latest of them, and
 * the recurring tickets and project templates that raise it. The work is in
 * functions/ticket_template.php.
 */

require_once "includes/inc_all_admin.php";

$template = ticketTemplateRow($_GET['ticket_template_id'] ?? 0);
if (!$template) {
    echo emptyStateHtml(['thing' => 'ticket templates', 'title' => 'That ticket template is gone', 'text' => 'It may have been deleted.', 'icon' => 'fa-life-ring', 'filtered' => false,
        'actions' => [['label' => 'Ticket Templates', 'href' => 'ticket_templates.php']]]);
    require_once "../includes/footer.php";
    exit();
}

$ticket_template_id = intval($template['ticket_template_id']);
$name = escapeHtml($template['ticket_template_name']);
$archived = !empty($template['ticket_template_archived_at']);
$tasks = ticketTemplateTasks($ticket_template_id);
$task_minutes = array_sum(array_column($tasks, 'estimate'));
$tag_ids = ticketTemplateTagIds($ticket_template_id);
$tag_rows = [];
if ($tag_ids) {
    $sql = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_id IN (" . implode(',', $tag_ids) . ") ORDER BY tag_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tag_rows[] = $row;
    }
}
$usage = ticketTemplateUsage([$ticket_template_id])[$ticket_template_id] ?? ['count' => 0, 'open' => 0, 'last' => null];
$csrf = $_SESSION['csrf_token'];

// The tickets it raised most lately
$recent = [];
$sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_created_at, ticket_resolved_at, ticket_closed_at, client_name, ticket_status_name, ticket_status_color
    FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
    WHERE ticket_template_id = $ticket_template_id AND ticket_archived_at IS NULL
    ORDER BY ticket_created_at DESC, ticket_id DESC LIMIT 8");
while ($row = mysqli_fetch_assoc($sql)) {
    $recent[] = $row;
}

// What raises it
$recurring = [];
$sql = mysqli_query($mysqli, "SELECT recurring_ticket_id, recurring_ticket_subject, recurring_ticket_frequency, recurring_ticket_client_id, client_name FROM recurring_tickets
    LEFT JOIN clients ON client_id = recurring_ticket_client_id
    WHERE recurring_ticket_ticket_template_id = $ticket_template_id ORDER BY client_name, recurring_ticket_subject");
while ($row = mysqli_fetch_assoc($sql)) {
    $recurring[] = $row;
}
$projects = [];
$sql = mysqli_query($mysqli, "SELECT project_templates.project_template_id, project_template_name FROM project_template_ticket_templates
    JOIN project_templates ON project_templates.project_template_id = project_template_ticket_templates.project_template_id
    WHERE project_template_ticket_templates.ticket_template_id = $ticket_template_id ORDER BY project_template_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $projects[] = $row;
}

// Its placeholders - filled in from the ticket - and what it asks for: its fill-ins
$known = array_map('strtolower', array_keys(TICKET_PLACEHOLDERS));
$found = ticketTemplatePlaceholdersIn($template['ticket_template_subject'] . ' ' . $template['ticket_template_details'] . ' ' . implode(' ', array_column($tasks, 'name')));
$filled = array_values(array_filter($found, function ($word) use ($known) {
    return in_array(strtolower($word), $known, true);
}));
$fill_ins = ticketTemplateFillIns($template, $tasks, ticketTemplateFillInRowsFor([$ticket_template_id])[$ticket_template_id]);
$when_select = ticketTemplateWhenSelectHtml($fill_ins);

// [Words] marked where they appear - escaped text, or HTML already made safe. Fill-ins in blue.
$mark = function ($html) use ($known) {
    return preg_replace_callback('/\[(' . TICKET_PLACEHOLDER_NAME . ')\]/', function ($match) use ($known) {
        $tone = in_array(strtolower(trim($match[1])), $known, true) ? 'text-bg-light border' : 'text-primary-emphasis bg-primary-subtle border border-primary-subtle';
        return '<span class="badge rounded-pill ' . $tone . ' font-monospace fw-normal">[' . $match[1] . ']</span>';
    }, $html);
};
$fill_in_url = "modals/ticket_template/ticket_template_fill_in.php?ticket_template_id=$ticket_template_id";

// Its request form in the client portal - who sees it, and what it has raised
$portal = intval($template['ticket_template_portal']) === 1;
$portal_clients = [];
if ($portal && !intval($template['ticket_template_portal_all_clients'])) {
    $sql = mysqli_query($mysqli, "SELECT clients.client_id, client_name FROM ticket_template_portal_clients JOIN clients ON clients.client_id = ticket_template_portal_clients.client_id
        WHERE ticket_template_id = $ticket_template_id ORDER BY client_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $portal_clients[] = $row;
    }
}
$portal_requests = ticketTemplatePortalRequests([$ticket_template_id])[$ticket_template_id] ?? 0;
$asks_client = count(array_filter($fill_ins, function ($fill_in) {
    return $fill_in['client'];
}));
$details = trim((string) $template['ticket_template_details']) !== '' ? $mark(documentHtmlPurify($template['ticket_template_details'])) : '';

$edit_url = "modals/ticket_template/ticket_template_edit.php?id=$ticket_template_id";

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item"><a href="users.php">Admin</a></li>
    <li class="breadcrumb-item"><a href="ticket_templates.php">Ticket Templates</a></li>
    <li class="breadcrumb-item active"><?= $name ?></li>
</ol>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw fa-life-ring"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h1 class="h4 mb-0"><?= $name ?></h1>
            <?php if (trim((string) $template['ticket_template_description']) !== '') { ?>
                <div class="text-secondary"><?= escapeHtml($template['ticket_template_description']) ?></div>
            <?php } ?>
            <?php if ($archived) { ?>
                <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($template['ticket_template_archived_at'])) ?> - not offered for new tickets</div>
            <?php } ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!$archived) { ?>
                <a class="btn btn-primary" href="/agent/tickets.php?new_from_template=<?= $ticket_template_id ?>"><i class="fas fa-fw fa-plus me-2"></i>New ticket from it</a>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="post.php?copy_ticket_template=<?= $ticket_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-copy me-2"></i>Copy</a>
                    <a class="dropdown-item" href="post.php?export_ticket_template=<?= $ticket_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-file-download me-2"></i>Export</a>
                    <?php if ($archived) { ?>
                        <a class="dropdown-item" href="post.php?restore_ticket_template=<?= $ticket_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                    <?php } else { ?>
                        <a class="dropdown-item" href="post.php?archive_ticket_template=<?= $ticket_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ticket_template=<?= $ticket_template_id ?>&from_page=1&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Its tasks go with it. Tickets raised from it stay as they are." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-life-ring me-2"></i>The ticket it starts</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-edit me-1"></i>Edit</button>
            </div>
            <div class="card-body">
                <div class="small text-secondary">Subject</div>
                <div class="fw-semibold mb-3"><?= trim((string) $template['ticket_template_subject']) !== '' ? $mark(escapeHtml($template['ticket_template_subject'])) : '<span class="text-secondary fw-normal">None - typed in on New Ticket</span>' ?></div>
                <div class="small text-secondary">Details</div>
                <div class="prettyContent"><?= $details !== '' ? $details : '<span class="text-secondary">None</span>' ?></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-pen-square me-2"></i>Fill-ins</h3>
                <span class="ms-auto small text-secondary me-2"><?= $fill_ins ? count($fill_ins) . ' asked for' : '' ?></span>
                <button type="button" class="btn btn-sm btn-link ajax-modal" data-modal-url="<?= $fill_in_url ?>"><i class="fas fa-plus me-1"></i>Add</button>
            </div>
            <div class="card-body">
                <?php if ($fill_ins) { ?>
                    <p class="small text-secondary">Asked for when a ticket is raised from it - on New Ticket, the New Tickets windows and recurring tickets - and kept on the ticket. Each answer goes in wherever its <span class="font-monospace">[Name]</span> is. Drag to change the order they are asked in.</p>
                    <div class="list-group" id="ticketTemplateFillIns">
                        <?php foreach ($fill_ins as $fill_in) {
                            $kind = TICKET_FILL_IN_TYPES[$fill_in['type']];
                            $about = [];
                            if ($fill_in['type'] === 'list') {
                                $about[] = escapeHtml(implode(' · ', $fill_in['options']));
                            }
                            if ($fill_in['help'] !== '') {
                                $about[] = escapeHtml($fill_in['help']);
                            }
                            ?>
                            <div class="list-group-item d-flex align-items-center gap-2" data-fill-in-name="<?= escapeHtml($fill_in['name']) ?>">
                                <span class="drag-handle text-secondary" title="Drag to reorder" style="cursor: grab;"><i class="fas fa-fw fa-grip-vertical"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <span class="fw-semibold me-1"><?= escapeHtml($fill_in['name']) ?></span>
                                        <span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw <?= $kind['icon'] ?> me-1 text-secondary"></i><?= $kind['label'] ?></span>
                                        <?php if ($fill_in['required']) { ?><span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-asterisk me-1 text-danger"></i>Required</span><?php } ?>
                                        <?php if (!$fill_in['client']) { ?><span class="badge rounded-pill text-bg-light border fw-normal" title="Not asked on the portal's request form, and not shown to the client"><i class="fas fa-fw fa-user-lock me-1 text-secondary"></i>Agents only</span><?php } ?>
                                        <?php if (!$fill_in['in_text']) { ?><span class="badge rounded-pill text-bg-warning fw-normal" title="Not in the subject, details or tasks - asked for, and kept on the ticket, but put in nowhere">Not in the text</span><?php } ?>
                                    </div>
                                    <?php if ($about) { ?><div class="small text-secondary text-truncate"><?= implode(' - ', $about) ?></div><?php } ?>
                                </div>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-light text-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $fill_in_url ?>&name=<?= urlencode($fill_in['name']) ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                        <?php if ($fill_in['id']) { ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($fill_in['in_text']) { ?>
                                                <a class="dropdown-item confirm-link" href="post.php?delete_ticket_template_fill_in=<?= $fill_in['id'] ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-title="Reset <?= escapeHtml($fill_in['name']) ?>?" data-confirm-text="It is still in the text, so it stays asked for - as plain, optional text." data-confirm-button="Reset"><i class="fas fa-fw fa-undo me-2"></i>Reset</a>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ticket_template_fill_in=<?= $fill_in['id'] ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-title="Remove <?= escapeHtml($fill_in['name']) ?>?" data-confirm-text="It is no longer asked for. Tickets already raised keep their answers." data-confirm-button="Remove"><i class="fas fa-fw fa-trash-alt me-2"></i>Remove</a>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="text-secondary small">Nothing to ask. Put a word in square brackets in the subject, details or a task - <span class="font-monospace">[Employee Name]</span>, <span class="font-monospace">[Start Date]</span> - and it is asked for when a ticket is raised from it, then filled in. Add one here to make it a date, a choice from a list or yes or no, or to make it required.</div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-tasks me-2"></i>Tasks</h3>
                <span class="ms-auto small text-secondary"><?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?><?= $task_minutes ? ' · <i class="far fa-clock me-1"></i>' . escapeHtml(ticketTemplateMinutesText($task_minutes)) : '' ?></span>
            </div>
            <div class="card-body">
                <form action="post.php" method="post" autocomplete="off" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="ticket_template_id" value="<?= $ticket_template_id ?>">
                    <div class="row g-2">
                        <div class="col-sm">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-tasks"></i></span>
                                <input type="text" class="form-control" name="task_name" placeholder="Add a task" required maxlength="200">
                            </div>
                        </div>
                        <div class="col-8 col-sm-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="far fa-fw fa-clock"></i></span>
                                <input type="number" class="form-control" name="task_estimate" min="0" placeholder="Mins" aria-label="Estimate in minutes">
                            </div>
                        </div>
                        <div class="col-4 col-sm-auto">
                            <button type="submit" name="add_ticket_template_task" class="btn btn-primary w-100"><i class="fas fa-fw fa-plus me-1"></i>Add</button>
                        </div>
                        <?php if ($when_select !== '') { ?>
                            <div class="col-12">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-code-branch"></i></span>
                                    <?= $when_select ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </form>
                <?php if ($tasks) { ?>
                    <div class="list-group" id="ticketTemplateTasks">
                        <?php foreach ($tasks as $number => $task) { ?>
                            <div class="list-group-item d-flex align-items-center gap-2" data-task-id="<?= $task['id'] ?>">
                                <span class="drag-handle text-secondary" title="Drag to reorder" style="cursor: grab;"><i class="fas fa-fw fa-grip-vertical"></i></span>
                                <span class="text-secondary small" style="min-width: 1.5rem;"><?= $number + 1 ?>.</span>
                                <span class="flex-grow-1 min-w-0 text-break">
                                    <?= $mark(escapeHtml($task['name'])) ?>
                                    <?php if ($task['when']) { ?>
                                        <span class="d-block small <?= ticketTemplateTaskWhenOffered($task, $fill_ins) ? 'text-secondary' : 'text-warning-emphasis' ?>"><i class="fas fa-fw fa-code-branch me-1"></i><?= ticketTemplateTaskWhenText($task) ?><?= ticketTemplateTaskWhenOffered($task, $fill_ins) ? '' : ' - no longer an answer it offers, so it is always added' ?></span>
                                    <?php } ?>
                                </span>
                                <?php if ($task['estimate']) { ?><span class="small text-secondary text-nowrap"><i class="far fa-clock me-1"></i><?= escapeHtml(ticketTemplateMinutesText($task['estimate'])) ?></span><?php } ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-light text-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_template/ticket_template_task_edit.php?id=<?= $task['id'] ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_task_template=<?= $task['id'] ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-trash-alt me-2"></i>Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="text-secondary small">No tasks yet. A ticket raised from it gets a copy of each, in this order - and later changes here don't touch tickets already raised. A task can be for one answer only - "Order a laptop" when Needs a laptop is Yes.</div>
                <?php } ?>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-sliders-h me-2"></i>Sets up</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-edit me-1"></i>Edit</button>
            </div>
            <div class="card-body">
                <?php
                $leave = '<span class="text-secondary">As it is</span>';
                $set_up = [
                    'Category' => intval($template['ticket_template_category_id']) && !empty($template['category_name'])
                        ? categoryChip(['category_name' => $template['category_name'], 'category_type' => 'Ticket', 'category_color' => $template['category_color'], 'category_icon' => $template['category_icon']]) : $leave,
                    'Priority' => !empty($template['ticket_template_priority']) ? ticketPriorityHtml($template['ticket_template_priority'], 'detail') : $leave,
                    'Assign to' => intval($template['ticket_template_assigned_to']) && !empty($template['assignee_name'])
                        ? userAvatar($template['ticket_template_assigned_to'], $template['assignee_name'], $template['assignee_avatar'] ?? '', 'sm') . ' ' . escapeHtml($template['assignee_name']) : $leave,
                    'Due' => intval($template['ticket_template_due_hours']) ? escapeHtml(ticketTemplateDueText($template['ticket_template_due_hours'])) . ' after it is raised' : '<span class="text-secondary">No due date</span>',
                ];
                if (!empty($config_module_enable_accounting)) {
                    $set_up['Billable'] = $template['ticket_template_billable'] === null ? $leave : (intval($template['ticket_template_billable']) ? 'Billable' : 'Not billable');
                }
                $set_up['Tags'] = $tag_rows ? '<span class="d-inline-flex flex-wrap gap-1">' . implode('', array_map('tagPill', $tag_rows)) . '</span>' : '<span class="text-secondary">None</span>';
                ?>
                <dl class="row mb-0 small">
                    <?php foreach ($set_up as $label => $value) { ?>
                        <dt class="col-5 fw-normal text-secondary"><?= $label ?></dt>
                        <dd class="col-7 mb-2"><?= $value ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-door-open me-2"></i>Client portal</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-edit me-1"></i>Edit</button>
            </div>
            <div class="card-body">
                <?php if ($portal) { ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw <?= ticketTemplatePortalIcon($template) ?>"></i></span>
                        <div class="min-w-0">
                            <div class="fw-semibold"><?= escapeHtml(ticketTemplatePortalTitle($template)) ?></div>
                            <?php if (trim((string) $template['ticket_template_portal_description']) !== '') { ?><div class="small text-secondary"><?= escapeHtml($template['ticket_template_portal_description']) ?></div><?php } ?>
                        </div>
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-5 fw-normal text-secondary">Who</dt>
                        <dd class="col-7 mb-2"><?= TICKET_TEMPLATE_PORTAL_WHO[$template['ticket_template_portal_who']] ?? TICKET_TEMPLATE_PORTAL_WHO['everyone'] ?></dd>
                        <dt class="col-5 fw-normal text-secondary">At</dt>
                        <dd class="col-7 mb-2">
                            <?php if (intval($template['ticket_template_portal_all_clients'])) { ?>
                                Every client
                            <?php } else { ?>
                                <?= implode(', ', array_map(function ($row) {
                                    return '<a href="/agent/client_overview.php?client_id=' . intval($row['client_id']) . '">' . escapeHtml($row['client_name']) . '</a>';
                                }, $portal_clients)) ?: '<span class="text-danger">No client</span>' ?>
                            <?php } ?>
                        </dd>
                        <dt class="col-5 fw-normal text-secondary">Asks</dt>
                        <dd class="col-7 mb-2"><?= $asks_client ? $asks_client . ' of its fill-ins' : 'Nothing - the client just sends it' ?></dd>
                        <dt class="col-5 fw-normal text-secondary">Requests</dt>
                        <dd class="col-7 mb-0"><?= $portal_requests ? $portal_requests . ' from the portal' : 'None yet' ?></dd>
                    </dl>
                    <?php if ($archived) { ?><div class="small text-warning-emphasis mt-2"><i class="fas fa-fw fa-archive me-1"></i>Archived - not offered until it is restored</div><?php } ?>
                <?php } else { ?>
                    <div class="small text-secondary">Not offered there. Offer it as a request form and clients answer what it asks - the ticket is raised set up as it says, with its tasks.</div>
                <?php } ?>
            </div>
        </div>

        <?php if ($filled) { ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-code me-2"></i>Placeholders</h3></div>
                <div class="card-body small">
                    <div class="text-secondary mb-1">Filled in from the ticket</div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($filled as $word) { ?>
                            <span class="badge rounded-pill text-bg-light border font-monospace fw-normal" title="<?= escapeHtml(ucfirst(TICKET_PLACEHOLDERS[ucfirst(strtolower($word))] ?? '')) ?>">[<?= escapeHtml($word) ?>]</span>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-chart-line me-2"></i>Used</h3></div>
            <div class="card-body">
                <?php if ($usage['count']) { ?>
                    <div class="mb-2"><strong><?= $usage['count'] ?></strong> ticket<?= $usage['count'] === 1 ? '' : 's' ?> raised<?= $usage['open'] ? ', <strong>' . $usage['open'] . '</strong> still open' : '' ?> <span class="small text-secondary">- last <?= timeAgo($usage['last']) ?></span></div>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($recent as $row) {
                            $open = !$row['ticket_resolved_at'] && !$row['ticket_closed_at']; ?>
                            <a class="list-group-item list-group-item-action px-0 d-flex align-items-center gap-2" href="/agent/<?= escapeHtml(ticketUrl($row['ticket_id'], $row['ticket_client_id'])) ?>">
                                <span class="font-monospace text-nowrap"><?= escapeHtml(ticketReference($row)) ?></span>
                                <span class="min-w-0 flex-grow-1 text-truncate <?= $open ? '' : 'text-secondary' ?>"><?= escapeHtml($row['client_name'] ?: $row['ticket_subject']) ?></span>
                                <?= ticketStatusHtml($row['ticket_status_name'] ?? '', $row['ticket_status_color'] ?? '', 'detail') ?>
                            </a>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="small text-secondary">No tickets raised from it yet - counting started with this version of ITFlow.</div>
                <?php } ?>
                <?php if ($recurring || $projects) { ?>
                    <div class="small text-secondary mt-3 mb-1">Raised by</div>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($recurring as $row) { ?>
                            <li class="mb-1"><i class="fas fa-fw fa-redo-alt text-secondary me-1"></i><a href="/agent/recurring_tickets.php?client_id=<?= intval($row['recurring_ticket_client_id']) ?>"><?= escapeHtml($row['recurring_ticket_subject']) ?></a> <span class="text-secondary">- <?= escapeHtml(trim($row['recurring_ticket_frequency'] . ', ' . ($row['client_name'] ?? ''), ', ')) ?></span></li>
                        <?php } ?>
                        <?php foreach ($projects as $row) { ?>
                            <li class="mb-1"><i class="fas fa-fw fa-project-diagram text-secondary me-1"></i><a href="project_template.php?project_template_id=<?= intval($row['project_template_id']) ?>"><?= escapeHtml($row['project_template_name']) ?></a> <span class="text-secondary">- project template</span></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>

        <!-- History, from the audit log -->
        <?= auditLogHistoryCard(auditLogHistory('Ticket Template', $ticket_template_id, 10)) ?>

    </div>
</div>

<script src="../js/pretty_content.js"></script>
<script src="../libs/SortableJS/Sortable.min.js"></script>
<script>
    (function () {
        var list = document.getElementById('ticketTemplateFillIns');
        if (!list) {
            return;
        }
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                itflowPost('post.php', {
                    reorder_ticket_template_fill_ins: 1,
                    csrf_token: <?= json_encode($csrf) ?>,
                    ticket_template_id: <?= $ticket_template_id ?>,
                    names: Array.from(list.querySelectorAll('[data-fill-in-name]')).map(function (row) {
                        return row.dataset.fillInName;
                    })
                });
            }
        });
    })();
    (function () {
        var list = document.getElementById('ticketTemplateTasks');
        if (!list) {
            return;
        }
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                var positions = Array.from(list.querySelectorAll('[data-task-id]')).map(function (row, index) {
                    row.querySelector('.text-secondary.small').textContent = (index + 1) + '.';
                    return { id: row.dataset.taskId, order: index + 1 };
                });
                itflowPostForm('/agent/ajax.php', {
                    update_task_templates_order: true,
                    csrf_token: <?= json_encode($csrf) ?>,
                    ticket_template_id: <?= $ticket_template_id ?>,
                    positions: positions
                });
            }
        });
    })();
</script>

<?php
require_once "../includes/footer.php";
