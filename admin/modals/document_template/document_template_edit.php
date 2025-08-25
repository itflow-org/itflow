<?php

require_once '../../../includes/modal_header_new.php';

$document_template_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM document_templates WHERE document_template_id = $document_template_id LIMIT 1");
$row = mysqli_fetch_array($sql);
$document_template_name = nullable_htmlentities($row['document_template_name']);
$document_template_description = nullable_htmlentities($row['document_template_description']);
$document_template_content = nullable_htmlentities($row['document_template_content']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Editing template: <strong><?php echo $document_template_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="document_template_id" value="<?php echo $document_template_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $document_template_name; ?>" placeholder="Name" required>
        </div>

        <div class="form-group">
            <textarea class="form-control tinymce" name="content"><?php echo $document_template_content; ?></textarea>
        </div>

        <div class="form-group">
            <input type="text" class="form-control" name="description" value="<?php echo $document_template_description; ?>" placeholder="Short summary">
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_document_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer_new.php';
