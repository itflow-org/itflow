<?php

/*
 * One of a ticket template's tasks - its name, how long it takes, and the
 * answer it is for when it isn't for every ticket (one of the template's list
 * or yes-or-no fill-ins). Its place in the list is changed by dragging it on
 * the template's page.
 */

require_once '../../includes/modal_header.php';

$task_template_id = intval($_GET['id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT task_template_completion_estimate, task_template_name, task_template_ticket_template_id, task_template_when_fill_in, task_template_when_value FROM task_templates WHERE task_template_id = $task_template_id LIMIT 1"));
$when_html = '';
if ($row) {
    $when = trim((string) $row['task_template_when_fill_in']) !== '' ? ['fill_in' => $row['task_template_when_fill_in'], 'value' => (string) $row['task_template_when_value']] : null;
    $when_html = ticketTemplateWhenSelectHtml(ticketTemplateFillInsFor($row['task_template_ticket_template_id']), $when);
}

ob_start();

if (!$row) { ?>
    <div class="modal-body text-center text-secondary py-5">That task is gone - it may have been deleted.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-tasks me-2"></i>Task</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="task_template_id" value="<?= $task_template_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tasks"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Name the task" maxlength="200" value="<?= escapeHtml($row['task_template_name']) ?>" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">How long it takes</label>
            <div class="input-group">
                <span class="input-group-text"><i class="far fa-fw fa-clock"></i></span>
                <input type="number" class="form-control" name="completion_estimate" min="0" placeholder="Minutes" value="<?= intval($row['task_template_completion_estimate']) ?: '' ?>">
                <span class="input-group-text">minutes</span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">For</label>
            <?php if ($when_html !== '') { ?>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-code-branch"></i></span>
                    <?= $when_html ?>
                </div>
                <div class="form-text">A task for one answer only is left off tickets given another. Until it is answered, the task is added.</div>
            <?php } else { ?>
                <div class="form-text mt-0">Every ticket. A task can be for one answer only once the template has a list or yes-or-no fill-in.</div>
            <?php } ?>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_template_task" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php }

require_once '../../../includes/modal_footer.php';
