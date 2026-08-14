<?php

require_once '../../../includes/modal_header.php';

$document_id = intval($_GET['document_id']);

$sql = mysqli_query($mysqli, "SELECT document_client_id, document_name FROM documents
    WHERE document_id = $document_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$document_name = escapeHtml($row['document_name']);
$client_id = intval($row['document_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-paperclip me-2"></i>Link File to <strong><?= $document_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
                <select class="form-select select2" name="file_id">
                    <option value="">- Select a File -</option>
                    <?php
                    $sql_files_select = mysqli_query($mysqli, "
                        SELECT files.file_id, file_name, folder_name
                        FROM files
                        LEFT JOIN folders
                            ON folder_id = file_folder_id
                        LEFT JOIN document_files
                            ON files.file_id = document_files.file_id
                            AND document_files.document_id = $document_id
                        WHERE file_client_id = $client_id
                        AND file_archived_at IS NULL
                        AND document_files.file_id IS NULL
                        ORDER BY folder_name ASC, file_name ASC
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
        <button type="submit" name="link_file_to_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link File</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
