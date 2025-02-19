<?php

require_once '../includes/ajax_header.php';

$folder_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_id = $folder_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$folder_name = nullable_htmlentities($row['folder_name']);


// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-folder mr-2"></i>Renaming folder: <strong><?php echo $folder_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="folder_id" value="<?php echo $folder_id; ?>">
    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                </div>
                <input type="text" class="form-control" name="folder_name" placeholder="Folder Name" maxlength="200" value="<?php echo $folder_name; ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="rename_folder" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Rename</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
