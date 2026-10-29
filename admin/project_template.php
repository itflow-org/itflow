<?php

/*
 * A project template - its ticket templates in the order a new project
 * raises them (drag to change it), each with its tasks.
 */

require_once "includes/inc_all_admin.php";

$project_template_id = intval($_GET['project_template_id'] ?? 0);

$template = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT project_template_name, project_template_description, project_template_created_at,
        project_template_updated_at, project_template_archived_at FROM project_templates
    WHERE project_template_id = $project_template_id LIMIT 1"));

if (!$template) { ?>
    <div class="text-center mt-5">
        <h1 class="h3 text-secondary">Nothing to see here</h1>
        <a class="btn btn-secondary mt-3" href="project_templates.php"><i class="fa fa-fw fa-arrow-left me-2"></i>Project templates</a>
    </div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

// Its ticket templates in order, then all their tasks in one go
$sql = mysqli_query($mysqli, "SELECT ticket_templates.ticket_template_id, ticket_template_name, ticket_template_subject, ticket_template_description, ticket_template_order
    FROM project_template_ticket_templates
    JOIN ticket_templates ON ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
    WHERE project_template_id = $project_template_id
    ORDER BY ticket_template_order ASC, ticket_template_name ASC");
$ticket_templates = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $ticket_templates[intval($row['ticket_template_id'])] = $row + ['tasks' => []];
}
if ($ticket_templates) {
    $sql = mysqli_query($mysqli, "SELECT task_template_ticket_template_id, task_template_name FROM task_templates
        WHERE task_template_ticket_template_id IN (" . implode(',', array_keys($ticket_templates)) . ")
        ORDER BY task_template_order ASC, task_template_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $ticket_templates[intval($row['task_template_ticket_template_id'])]['tasks'][] = $row['task_template_name'];
    }
}
$task_count = array_sum(array_map(function ($t) { return count($t['tasks']); }, $ticket_templates));
$archived = !empty($template['project_template_archived_at']);

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="users.php">Admin</a></li>
    <li class="breadcrumb-item"><a href="project_templates.php">Project Templates</a></li>
    <li class="breadcrumb-item active"><?= escapeHtml($template['project_template_name']) ?></li>
</ol>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="itflow-asset-icon itflow-asset-icon-lg itflow-project-icon" aria-hidden="true"><i class="fas fa-fw fa-cubes"></i></span>
        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span><?= escapeHtml($template['project_template_name']) ?></span>
                <span class="badge rounded-pill text-bg-info fw-normal fs-6">Template</span>
                <?php if ($archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h1>
            <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                <span><i class="fas fa-fw fa-life-ring me-1"></i><?= count($ticket_templates) ?> ticket<?= count($ticket_templates) === 1 ? '' : 's' ?></span>
                <span><i class="fas fa-fw fa-tasks me-1"></i><?= $task_count ?> task<?= $task_count === 1 ? '' : 's' ?></span>
                <?php if ($template['project_template_description']) { ?><span><?= escapeHtml($template['project_template_description']) ?></span><?php } ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/project_template/project_template_ticket_template_add.php?project_template_id=<?= $project_template_id ?>">
                <i class="fas fa-plus me-2"></i>Add ticket templates
            </button>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/project_template/project_template_edit.php?project_template_id=<?= $project_template_id ?>">
                <i class="fas fa-edit me-2"></i>Edit
            </button>
            <div class="dropdown dropstart">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="post.php?duplicate_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-copy text-secondary me-2"></i>Duplicate
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php if ($archived) { ?>
                        <a class="dropdown-item" href="post.php?restore_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                            <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                        </a>
                    <?php } else { ?>
                        <a class="dropdown-item text-danger" href="post.php?archive_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                        </a>
                    <?php } ?>
                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                        data-confirm-text="The template goes for good. Its ticket templates stay, and so do projects started from it.">
                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-list-ol text-secondary me-2"></i>Tickets it raises</h3>
                <?php if (count($ticket_templates) > 1) { ?><span class="small text-body-secondary">Drag to change the order</span><?php } ?>
            </div>
            <?php if ($ticket_templates) { ?>
                <ol class="list-group list-group-flush list-group-numbered" id="projectTemplateTickets">
                    <?php foreach ($ticket_templates as $ticket_template_id => $row) { ?>
                        <li class="list-group-item d-flex align-items-start gap-2" data-ticket-template-id="<?= $ticket_template_id ?>">
                            <div class="flex-grow-1 min-w-0 ms-1">
                                <div class="d-flex align-items-center gap-2">
                                    <a class="fw-semibold" href="ticket_template.php?ticket_template_id=<?= $ticket_template_id ?>"><?= escapeHtml($row['ticket_template_name']) ?></a>
                                    <?php if ($row['tasks']) { ?>
                                        <a class="small text-body-secondary text-decoration-none" data-bs-toggle="collapse" href="#ptTasks<?= $ticket_template_id ?>" role="button"><i class="fas fa-tasks me-1"></i><?= count($row['tasks']) ?> task<?= count($row['tasks']) === 1 ? '' : 's' ?> <i class="fas fa-caret-down"></i></a>
                                    <?php } ?>
                                </div>
                                <?php if ($row['ticket_template_subject'] && $row['ticket_template_subject'] !== $row['ticket_template_name']) { ?>
                                    <div class="small text-body-secondary">Raised as "<?= escapeHtml($row['ticket_template_subject']) ?>"</div>
                                <?php } ?>
                                <?php if ($row['tasks']) { ?>
                                    <ul class="collapse small text-body-secondary mt-1 mb-0 ps-3" id="ptTasks<?= $ticket_template_id ?>">
                                        <?php foreach ($row['tasks'] as $task_name) { ?><li><?= escapeHtml($task_name) ?></li><?php } ?>
                                    </ul>
                                <?php } ?>
                            </div>
                            <span class="drag-handle text-body-secondary px-1" title="Drag to move" role="button"><i class="fas fa-grip-vertical"></i></span>
                            <form action="post.php" method="post" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="project_template_id" value="<?= $project_template_id ?>">
                                <input type="hidden" name="ticket_template_id" value="<?= $ticket_template_id ?>">
                                <button type="submit" class="btn btn-sm btn-light confirm-link" title="Take it out of this template" name="remove_ticket_template_from_project_template"
                                    data-confirm-text="The ticket template stays - it just won't be raised by this project template.">
                                    <i class="fas fa-fw fa-times"></i>
                                </button>
                            </form>
                        </li>
                    <?php } ?>
                </ol>
            <?php } else { ?>
                <div class="itflow-asset-empty">
                    <i class="fas fa-life-ring fa-2x mb-2 opacity-50"></i>
                    <div>No ticket templates in it yet. Add one per step of the work - each brings its own task checklist.</div>
                </div>
            <?php } ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-info-circle text-secondary me-2"></i>How it is used</h3>
            </div>
            <div class="card-body small">
                <p>Pick it on <strong>New Project</strong>, or <strong>Add tickets &gt; From a project template</strong> on a project, and these tickets are raised on the project in this order, each with its tasks.</p>
                <p class="text-body-secondary">They go to the project's client contact, and to its manager when asked. The client isn't emailed - it's planned work.</p>
                <p class="mb-0 text-body-secondary"><code>[Client]</code>, <code>[Project]</code>, <code>[Month]</code>, <code>[Quarter]</code> and <code>[Year]</code> in a ticket template's subject are filled in. Anything else in brackets is left for the agent.</p>
            </div>
        </div>

        <!-- History, from the audit log -->
        <?= auditLogHistoryCard(auditLogHistory('Project Template', $project_template_id, 10)) ?>

    </div>
</div>

<script src="../libs/SortableJS/Sortable.min.js"></script>
<script>
    (function () {
        var list = document.getElementById('projectTemplateTickets');
        if (!list) {
            return;
        }
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                var positions = Array.prototype.map.call(list.querySelectorAll('[data-ticket-template-id]'), function (row, index) {
                    return {id: row.dataset.ticketTemplateId, order: index};
                });
                itflowPostForm('/agent/ajax.php', {
                    update_project_template_ticket_order: true,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                    project_template_id: <?= $project_template_id ?>,
                    positions: positions
                });
            }
        });
    })();
</script>

<?php
require_once "../includes/footer.php";
