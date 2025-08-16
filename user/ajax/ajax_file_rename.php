<?php
require_once '../../includes/modal_header.php';

$file_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM files WHERE file_id = $file_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['file_client_id']);
$file_name = nullable_htmlentities($row['file_name']);
$file_description = nullable_htmlentities($row['file_description']);
$file_ext = nullable_htmlentities($row['file_description']);

if ($file_ext == 'pdf') {
    $file_icon = "file-pdf";
} elseif ($file_ext == 'gz' || $file_ext == 'tar' || $file_ext == 'zip' || $file_ext == '7z' || $file_ext == 'rar') {
    $file_icon = "file-archive";
} elseif ($file_ext == 'txt' || $file_ext == 'md') {
    $file_icon = "file-alt";
} elseif ($file_ext == 'msg') {
    $file_icon = "envelope";
} elseif ($file_ext == 'doc' || $file_ext == 'docx' || $file_ext == 'odt') {
    $file_icon = "file-word";
} elseif ($file_ext == 'xls' || $file_ext == 'xlsx' || $file_ext == 'ods') {
    $file_icon = "file-excel";
} elseif ($file_ext == 'pptx' || $file_ext == 'odp') {
    $file_icon = "file-powerpoint";
} elseif ($file_ext == 'mp3' || $file_ext == 'wav' || $file_ext == 'ogg') {
    $file_icon = "file-audio";
} elseif ($file_ext == 'mov' || $file_ext == 'mp4' || $file_ext == 'av1') {
    $file_icon = "file-video";
} elseif ($file_ext == 'jpg' || $file_ext == 'jpeg' || $file_ext == 'png' || $file_ext == 'gif' || $file_ext == 'webp' || $file_ext == 'bmp' || $file_ext == 'tif') {
    $file_icon = "file-image";
} else {
    $file_icon = "file";
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-<?php echo $file_icon; ?> mr-2"></i>Renaming file: <strong><?php echo $file_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="file_id" value="<?php echo $file_id; ?>">
    <div class="modal-body">

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
    <div class="modal-footer">
        <button type="submit" name="rename_file" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Rename</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../includes/modal_footer.php';
