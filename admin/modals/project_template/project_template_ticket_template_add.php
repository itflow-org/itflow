<?php

/*
 * Add ticket templates to a project template - any number at once, at the
 * end of its list in the order picked.
 */

require_once '../../includes/modal_header.php';

$project_template_id = intval($_GET['project_template_id'] ?? 0);
$name = getFieldById('project_templates', $project_template_id, 'project_template_name');
if ($name === null || $name === '') {
    echo json_encode(['error' => 'That project template no longer exists.']);
    exit;
}

$sql = mysqli_query($mysqli, "SELECT ticket_template_id, ticket_template_name, ticket_template_subject,
        (SELECT COUNT(*) FROM task_templates WHERE task_template_ticket_template_id = ticket_template_id) AS task_count
    FROM ticket_templates
    WHERE ticket_template_id NOT IN (SELECT ticket_template_id FROM project_template_ticket_templates WHERE project_template_id = $project_template_id)
    AND ticket_template_archived_at IS NULL
    ORDER BY ticket_template_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-life-ring me-2"></i>Add ticket templates <small class="ms-1 opacity-75"><?= escapeHtml($name) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_template_id" value="<?= $project_template_id ?>">

    <div class="modal-body">
        <?php if (mysqli_num_rows($sql)) { ?>
            <label class="form-label">Ticket templates <strong class="text-danger">*</strong></label>
            <select class="form-select select2" name="ticket_template_ids[]" multiple required data-placeholder="Pick ticket templates...">
                <?php while ($row = mysqli_fetch_assoc($sql)) {
                    $task_count = intval($row['task_count']); ?>
                    <option value="<?= intval($row['ticket_template_id']) ?>" data-icon="fas fa-life-ring"
                        data-description="<?= escapeHtml(trim(($task_count ? "$task_count task" . ($task_count === 1 ? '' : 's') : 'No tasks') . ($row['ticket_template_subject'] ? ' - ' . $row['ticket_template_subject'] : ''))) ?>"><?= escapeHtml($row['ticket_template_name']) ?></option>
                <?php } ?>
            </select>
            <div class="form-text">They go at the end, in the order picked - drag them about on the template afterwards.</div>
        <?php } else { ?>
            <div class="text-body-secondary">Every ticket template is in it already. Make more under <a href="ticket_templates.php">Ticket Templates</a>.</div>
        <?php } ?>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_ticket_template_to_project_template" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
