<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$folder_location = intval($_GET['folder_location'] ?? 0);
$current_folder_id = intval($_GET['current_folder_id'] ?? 0);
$folder_name = nullable_htmlentities(getFieldByID('folders', $current_folder_id, 'folder_name') ?? '/');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-folder-plus mr-2"></i>Creating folder in <strong><?= $folder_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="folder_location" value="<?= $folder_location ?>">
    <input type="hidden" name="parent_folder" value="<?= $current_folder_id ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Folder Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                </div>
                <input type="text" class="form-control" name="folder_name" placeholder="Folder Name" maxlength="200" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="create_folder" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
