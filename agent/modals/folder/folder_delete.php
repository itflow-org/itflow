<?php

/*
 * Delete a folder. Nothing in it is lost: its files, documents and
 * subfolders move up into the folder it sat in.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 3);

$folder_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT folder_client_id, folder_name, parent_folder FROM folders WHERE folder_id = $folder_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That folder no longer exists.']);
    exit;
}
$client_id = intval($row['folder_client_id']);
enforceClientAccess();

$parent_folder = folderOwnedId($row['parent_folder'], $client_id);
$parent_name = escapeHtml(folderDisplayName($parent_folder));
$inside = array_filter([
    intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM files WHERE file_folder_id = $folder_id AND file_client_id = $client_id"))[0]) . ' file(s)',
    intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM documents WHERE document_folder_id = $folder_id AND document_client_id = $client_id"))[0]) . ' document(s)',
    intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM folders WHERE parent_folder = $folder_id AND folder_client_id = $client_id"))[0]) . ' folder(s)',
], function ($part) {
    return intval($part) > 0;
});

ob_start();

?>

<div class="modal-body text-center py-4">
    <i class="fas fa-folder fa-3x text-body-secondary mb-3"></i>
    <h5 class="mb-1">Delete <strong><?= escapeHtml($row['folder_name']) ?></strong>?</h5>
    <div class="small text-body-secondary mb-4">
        <?php if ($inside) { ?>
            The <?= implode(', ', $inside) ?> inside move up to <strong><?= $parent_name ?></strong> - nothing is deleted with it.
        <?php } else { ?>
            It is empty.
        <?php } ?>
    </div>
    <form action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="folder_id" value="<?= $folder_id ?>">
        <button type="button" class="btn btn-light px-4 me-2" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="delete_folder" class="btn btn-danger px-4"><i class="fas fa-trash me-2"></i>Delete folder</button>
    </form>
</div>

<?php
require_once '../../../includes/modal_footer.php';
