<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);
intval($_GET['folder_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt me-2"></i>New Document</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="contact" value="<?= $contact_id ?>">
    <input type="hidden" name="asset" value="<?= $asset_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <input type="text" class="form-control" name="name" placeholder="Name" maxlength="200" required autofocus>
        </div>

        <div class="mb-3">
            <textarea class="form-control tinymce" name="content"></textarea>
        </div>

        <div class="mb-3">
            <label>Select Folder</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                <select class="form-control select2" name="folder">
                    <option value="0">/</option>
                    <?php
                    // Start displaying folder options from the root (parent_folder = 0)
                    displayFolderOptions(0, $client_id);
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Short summary of the document">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
