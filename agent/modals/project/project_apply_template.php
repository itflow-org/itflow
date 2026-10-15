<?php

/*
 * Add a project template's tickets to a project that already exists - the
 * second phase of a rollout, or a template written after the project began.
 */

require_once '../../../includes/modal_header.php';

$project = projectForModal($_GET['project_id'] ?? 0, 2, true);
$project_id = intval($project['project_id']);

$sql_templates = mysqli_query($mysqli, "SELECT project_templates.project_template_id, project_template_name, project_template_description,
        COUNT(DISTINCT ptt.ticket_template_id) AS ticket_count, COUNT(task_template_id) AS task_count
    FROM project_templates
    LEFT JOIN project_template_ticket_templates ptt ON ptt.project_template_id = project_templates.project_template_id
    LEFT JOIN task_templates ON task_template_ticket_template_id = ptt.ticket_template_id
    WHERE project_template_archived_at IS NULL
    GROUP BY project_templates.project_template_id ORDER BY project_template_name ASC");

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-cubes me-2"></i>Tickets from a template <small class="ms-1 opacity-75"><?= escapeHtml(projectReference($project)) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Project template <strong class="text-danger">*</strong></label>
            <select class="form-select select2" name="project_template_id" required>
                <option value="">Pick a template...</option>
                <?php while ($row = mysqli_fetch_assoc($sql_templates)) {
                    $ticket_count = intval($row['ticket_count']);
                    $task_count = intval($row['task_count']); ?>
                    <option value="<?= intval($row['project_template_id']) ?>" data-icon="fas fa-cubes"
                        data-description="<?= escapeHtml(trim("$ticket_count ticket" . ($ticket_count === 1 ? '' : 's') . ($task_count ? ", $task_count task" . ($task_count === 1 ? '' : 's') : '') . ($row['project_template_description'] ? ' - ' . $row['project_template_description'] : ''))) ?>"><?= escapeHtml($row['project_template_name']) ?></option>
                <?php } ?>
            </select>
            <div class="form-text">Its tickets are raised on this project in the template's order, each with its tasks<?= intval($project['project_contact_id']) ? ', for ' . escapeHtml($project['contact_name']) : '' ?>. The client isn't emailed.</div>
        </div>
        <?php if (intval($project['project_manager'])) { ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="assign_to_manager" value="1" id="applyAssignToManager" checked>
                <label class="form-check-label" for="applyAssignToManager">Assign them to <?= escapeHtml($project['user_name']) ?>, the manager</label>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="apply_project_template" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add tickets</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
