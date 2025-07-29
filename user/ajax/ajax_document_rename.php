<?php

require_once '../../includes/modal_header.php';

$document_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = $document_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$client_id = intval($row['document_client_id']);
$document_name = nullable_htmlentities($row['document_name']);


// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Renaming document: <strong><?php echo $document_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Document Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                </div>
                <input class="form-control" type="text" name="name" maxlength="200" value="<?php echo $document_name; ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="rename_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Rename</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../includes/modal_footer.php';
