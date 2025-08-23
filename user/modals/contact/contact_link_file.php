<?php

require_once '../../../includes/modal_header_new.php';

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM contacts
    WHERE contact_id = $contact_id
    LIMIT 1
");

$row = mysqli_fetch_array($sql);
$contact_name = nullable_htmlentities($row['contact_name']);
$client_id = intval($row['contact_client_id']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-paperclip mr-2"></i>Link File to <strong><?php echo $contact_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
    <div class="modal-body">

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
                        LEFT JOIN contact_files
                            ON files.file_id = contact_files.file_id
                            AND contact_files.contact_id = $contact_id
                        LEFT JOIN folders
                            ON folders.folder_id = files.file_folder_id
                        WHERE files.file_client_id = $client_id
                        AND contact_files.contact_id IS NULL
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
    <div class="modal-footer">
        <button type="submit" name="link_contact_to_file" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer_new.php';
