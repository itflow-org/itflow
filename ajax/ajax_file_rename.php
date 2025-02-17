<?php

require_once '../includes/ajax_header.php';

$file_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM files WHERE file_id = $file_id");
                     
$row = mysqli_fetch_array($sql);
$client_id = intval($row['file_client_id']);
$file_name = nullable_htmlentities($row['file_name']);
$file_description = nullable_htmlentities($row['file_description']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-<?php echo $file_icon; ?> mr-2"></i>Renaming file: <strong><?php echo $file_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="file_id" value="<?php echo $file_id; ?>">
    <div class="modal-body bg-white">

        <div class="form-group">
            <label>File Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                </div>
                <input type="text" class="form-control" name="file_name" placeholder="File Name" maxlength="200" value="<?php echo $file_name; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                </div>
                <input type="text" class="form-control" name="file_description" placeholder="Description" maxlength="250" value="<?php echo $file_description; ?>">
            </div>
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="rename_file" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Rename</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
