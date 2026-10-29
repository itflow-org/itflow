<?php

/*
 * New folder - inside the folder it was opened from, or anywhere else picked.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$parent_folder = folderOwnedId($_GET['current_folder_id'] ?? 0, $client_id);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-folder-plus me-2"></i>New folder</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                <input type="text" class="form-control" name="folder_name" placeholder="e.g. Contracts" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label">Inside</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-sitemap"></i></span>
                <select class="form-select select2" name="parent_folder">
                    <?= folderOptionsHtml($client_id, $parent_folder) ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="create_folder" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
