<?php

require_once '../../../includes/modal_header.php';

$document_id = intval($_GET['document_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM documents
    WHERE document_id = $document_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$document_name = nullable_htmlentities($row['document_name']);
$client_id = intval($row['document_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>Link Software to <strong><?= $document_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">

        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-box-open"></i></span>
                </div>
                <select class="form-control select2" name="software_id">
                    <option value="">- Select a License -</option>
                    <?php
                    $sql_software_select = mysqli_query($mysqli, "
                        SELECT software.software_id, software_name
                        FROM software
                        LEFT JOIN software_documents
                            ON software.software_id = software_documents.software_id
                            AND software_documents.document_id = $document_id
                        WHERE software_client_id = $client_id
                        AND software_archived_at IS NULL
                        AND software_documents.software_id IS NULL
                        ORDER BY software_name ASC
                    ");

                    while ($row = mysqli_fetch_assoc($sql_software_select)) {
                        $software_id = intval($row['software_id']);
                        $software_name = nullable_htmlentities($row['software_name']);

                        ?>
                        <option value="<?= $software_id ?>"><?= $software_name ?></option>
                        <?php
                    }
                    ?>

                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_software_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link License</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
