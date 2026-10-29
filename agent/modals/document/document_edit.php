<?php

/*
 * Edit a document. Saving keeps what it said before as a version.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_client_visible, document_content, document_description,
    document_folder_id, document_name, document_archived_at FROM documents WHERE document_id = $document_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

$document_form_mode = 'edit';
$document_name = escapeHtml($row['document_name']);
$document_description = escapeHtml($row['document_description']);
$document_content = escapeHtml($row['document_content']);
$document_folder_id = intval($row['document_folder_id']);
$document_client_visible = intval($row['document_client_visible']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-edit me-2"></i>Editing <strong><?= $document_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">
        <?php require 'document_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
