<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT document_client_id, document_client_visible, document_content, document_description,
    document_folder_id, document_name FROM documents WHERE document_id = $document_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$document_name = escapeHtml($row['document_name']);
$document_description = escapeHtml($row['document_description']);
$document_content = escapeHtml($row['document_content']);
$document_folder_id = intval($row['document_folder_id']);
$document_client_visible = intval($row['document_client_visible']);
$client_id = intval($row['document_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt me-2"></i>Editing document: <strong><?= $document_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <input type="text" class="form-control" name="name" maxlength="200" value="<?= $document_name ?>" placeholder="Name" required>
        </div>

        <div class="mb-3">
            <textarea class="form-control tinymce" name="content"><?= $document_content ?></textarea>
        </div>

        <label>Description</label>
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" value="<?= $document_description ?>" placeholder="Short summary of the document">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
