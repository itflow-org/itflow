<?php

require_once '../includes/ajax_header.php';

$document_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = $document_id");
$row = mysqli_fetch_array($sql);
$document_name = nullable_htmlentities($row['document_name']);
$document_description = nullable_htmlentities($row['document_description']);
$document_content = nullable_htmlentities($row['document_content']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Editing template: <strong><?php echo $document_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
    <div class="modal-body bg-white">

        <div class="form-group">
            <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $document_name; ?>" placeholder="Name" required>
        </div>

        <div class="form-group">
            <textarea class="form-control tinymce" name="content"><?php echo $document_content; ?></textarea>
        </div>

        <div class="form-group">
            <input type="text" class="form-control" name="description" value="<?php echo $document_description; ?>" placeholder="Short summary">
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_document_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
