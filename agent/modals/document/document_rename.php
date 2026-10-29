<?php

/*
 * Rename a document and change its summary, without opening the editor.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_name, document_description FROM documents WHERE document_id = $document_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

$document_name = escapeHtml($row['document_name']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-pen me-2"></i>Rename <strong><?= $document_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-file-alt"></i></span>
                <input class="form-control" type="text" name="name" maxlength="200" value="<?= $document_name ?>" required autofocus>
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label">Summary</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                <input class="form-control" type="text" name="description" value="<?= escapeHtml($row['document_description']) ?>" placeholder="One line on what it covers">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="rename_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
