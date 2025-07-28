<?php

require_once '../includes/ajax_header.php';

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM assets
    WHERE asset_id = $asset_id
    LIMIT 1
");

$row = mysqli_fetch_array($sql);
$asset_name = nullable_htmlentities($row['asset_name']);
$client_id = intval($row['asset_client_id']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-paperclip mr-2"></i>Link File to <strong><?php echo $asset_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
    <div class="modal-body bg-white">

        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
                </div>
                <select class="form-control select2" name="file_id">
                    <option value="">- Select a File -</option>
                    <?php
                    $sql_files_select = mysqli_query($mysqli, "
                        SELECT files.file_id, files.file_name, folders.folder_name
                        FROM files
                        LEFT JOIN asset_files
                            ON files.file_id = asset_files.file_id
                            AND asset_files.asset_id = $asset_id
                        LEFT JOIN folders
                            ON folders.folder_id = files.file_folder_id
                        WHERE files.file_client_id = $client_id
                        AND asset_files.asset_id IS NULL
                        ORDER BY folders.folder_name ASC, files.file_name ASC
                    ");
                    
                    while ($row = mysqli_fetch_array($sql_files_select)) {
                        $file_id = intval($row['file_id']);
                        $file_name = nullable_htmlentities($row['file_name']);
                        $folder_name = nullable_htmlentities($row['folder_name']);
                        ?>
                        <option value="<?php echo $file_id ?>"><?php echo "$folder_name/$file_name"; ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="link_asset_to_file" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
?>
