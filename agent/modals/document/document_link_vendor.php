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
    <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i>Link Vendor to <strong><?= $document_name ?></strong></h5>
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
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                </div>
                <select class="form-control select2" name="vendor_id">
                    <option value="">- Select a Vendor -</option>
                    <?php
                    $sql_vendors_select = mysqli_query($mysqli, "
                        SELECT vendors.vendor_id, vendor_name
                        FROM vendors
                        LEFT JOIN vendor_documents
                            ON vendors.vendor_id = vendor_documents.vendor_id
                            AND vendor_documents.document_id = $document_id
                        WHERE vendor_client_id = $client_id
                        AND vendor_archived_at IS NULL
                        AND vendor_documents.vendor_id IS NULL
                        ORDER BY vendor_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_vendors_select)) {
                        $vendor_id = intval($row['vendor_id']);
                        $vendor_name = nullable_htmlentities($row['vendor_name']);

                        ?>
                        <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                        <?php
                    }
                    ?>

                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_vendor_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link Vendor</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
