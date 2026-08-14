<?php

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-life-ring me-2"></i>New Ticket Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Template Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Template name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Subject</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                <input type="text" class="form-control" name="subject" placeholder="Subject" maxlength="500">
            </div>
        </div>

       <div class="mb-3">
            <textarea class="form-control tinymceTicket" name="details"></textarea>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Short description">
            </div>
        </div>

        <div class="mb-3">
            <label>Add it to a Project Template?</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                <select class="form-select select2" name="project_template">
                    <option value="0">- No -</option>
                    <?php

                    $sql_project_templates = mysqli_query($mysqli, "SELECT project_template_id, project_template_name FROM project_templates WHERE project_template_archived_at IS NULL ORDER BY project_template_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_project_templates)) {
                        $project_template_id_select = intval($row['project_template_id']);
                        $project_template_name_select = escapeHtml($row['project_template_name']); ?>
                        <option value="<?= $project_template_id_select ?>"><?= $project_template_name_select ?></option>

                    <?php } ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ticket_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create Template</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
