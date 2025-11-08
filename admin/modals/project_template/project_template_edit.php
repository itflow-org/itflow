<?php

require_once '../../../includes/modal_header.php';

$project_template_id = intval($_GET['project_template_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM project_templates WHERE project_template_id = $project_template_id LIMIT 1");
$row = mysqli_fetch_array($sql);
$project_template_name = nullable_htmlentities($row['project_template_name']);
$project_template_description = nullable_htmlentities($row['project_template_description']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram mr-2"></i>Editing Project Template: <strong><?php echo $project_template_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="project_template_id" value="<?php echo $project_template_id; ?>">

    <div class="modal-body">
        <div class="form-group">
            <label>Project Template Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Project Template Name" maxlength="255" value="<?php echo $project_template_name; ?>" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                </div>
                <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $project_template_description; ?>">
            </div>
        </div>

    </div>
    
    <div class="modal-footer">
        <button type="submit" name="edit_project_template" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
