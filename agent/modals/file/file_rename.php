<?php

/*
 * Rename a file and change its description.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$file_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_client_id, file_description, file_ext, file_name FROM files WHERE file_id = $file_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That file no longer exists.']);
    exit;
}
$client_id = intval($row['file_client_id']);
enforceClientAccess();

$file_name = escapeHtml($row['file_name']);
$file_description = escapeHtml($row['file_description']);
$file_info = fileTypeInfo($row['file_ext']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-<?= $file_info['icon'] ?> me-2"></i>Rename <strong><?= $file_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="file_id" value="<?= $file_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-<?= $file_info['icon'] ?>"></i></span>
                <input type="text" class="form-control" name="file_name" placeholder="File name" maxlength="200" value="<?= $file_name ?>" required autofocus>
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="file_description" placeholder="What this is" maxlength="250" value="<?= $file_description ?>">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="rename_file" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
