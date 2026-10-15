<?php

/*
 * Rename a folder, or move it - with everything in it - under another one.
 * The folder and its own subfolders are left out of the picker, so it can
 * never end up inside itself.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$folder_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT folder_client_id, folder_name, parent_folder FROM folders WHERE folder_id = $folder_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That folder no longer exists.']);
    exit;
}
$client_id = intval($row['folder_client_id']);
enforceClientAccess();

$folder_name = escapeHtml($row['folder_name']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-folder me-2"></i><?= $folder_name ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="folder_id" value="<?= $folder_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                <input type="text" class="form-control" name="folder_name" placeholder="Folder name" maxlength="200" value="<?= $folder_name ?>" required autofocus>
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label">Inside</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-sitemap"></i></span>
                <select class="form-select select2" name="parent_folder">
                    <?= folderOptionsHtml($client_id, intval($row['parent_folder']), $folder_id) ?>
                </select>
            </div>
            <div class="form-text">Moving it takes everything inside along with it.</div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="rename_folder" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
