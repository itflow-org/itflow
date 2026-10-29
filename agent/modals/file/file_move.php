<?php

/*
 * Move a file to another of its client's folders.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$file_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_client_id, file_ext, file_folder_id, file_name FROM files WHERE file_id = $file_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That file no longer exists.']);
    exit;
}
$client_id = intval($row['file_client_id']);
enforceClientAccess();

$file_name = escapeHtml($row['file_name']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-folder-open me-2"></i>Move <strong><?= $file_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="file_id" value="<?= $file_id ?>">
    <div class="modal-body">

        <label class="form-label">To folder</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
            <select class="form-select select2" name="folder_id">
                <?= folderOptionsHtml($client_id, intval($row['file_folder_id'])) ?>
            </select>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="move_file" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Move</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
