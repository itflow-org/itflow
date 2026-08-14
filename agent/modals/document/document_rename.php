<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT document_client_id, document_name FROM documents WHERE document_id = $document_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$client_id = intval($row['document_client_id']);
$document_name = escapeHtml($row['document_name']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt me-2"></i>Renaming document: <strong><?= $document_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Document Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                <input class="form-control" type="text" name="name" maxlength="200" value="<?= $document_name ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="rename_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Rename</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
