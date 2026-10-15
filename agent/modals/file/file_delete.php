<?php

/*
 * Delete an archived file for good - the file on disk and every link to it.
 * An ajax modal so the file's name is drawn by the server, escaped, rather
 * than written into an onclick handler.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 3);

$file_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_client_id, file_ext, file_name, file_size FROM files WHERE file_id = $file_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That file no longer exists.']);
    exit;
}
$client_id = intval($row['file_client_id']);
enforceClientAccess();

ob_start();

?>

<div class="modal-body text-center py-4">
    <div class="mb-3"><?= fileTypeTile($row['file_ext'], 'lg') ?></div>
    <h5 class="mb-1">Delete this file for good?</h5>
    <div class="fw-semibold text-break"><?= escapeHtml($row['file_name']) ?></div>
    <div class="small text-body-secondary mb-4"><?= formatBytes(intval($row['file_size'])) ?> &middot; it is removed from the server and unlinked from everything. This cannot be undone.</div>
    <form action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="file_id" value="<?= $file_id ?>">
        <button type="button" class="btn btn-light px-4 me-2" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="delete_file" class="btn btn-danger px-4"><i class="fas fa-trash me-2"></i>Delete</button>
    </form>
</div>

<?php
require_once '../../../includes/modal_footer.php';
