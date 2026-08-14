<?php

require_once '../../../includes/modal_header.php';

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT contact_client_id, contact_name FROM contacts
    WHERE contact_id = $contact_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$contact_name = escapeHtml($row['contact_name']);
$client_id = intval($row['contact_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-paperclip me-2"></i>Link File to <strong><?= $contact_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
                <select class="form-select select2" name="file_id">
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

                    while ($row = mysqli_fetch_assoc($sql_files_select)) {
                        $file_id = intval($row['file_id']);
                        $file_name = escapeHtml($row['file_name']);
                        $folder_name = escapeHtml($row['folder_name']);
                        ?>
                        <option value="<?= $file_id ?>"><?= "$folder_name/$file_name" ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_contact_to_file" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
