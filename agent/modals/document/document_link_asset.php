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
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>Link Asset to <strong><?= $document_name ?></strong></h5>
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
                    <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                </div>
                <select class="form-control select2" name="asset_id">
                    <option value="">- Select an Asset -</option>
                    <?php
                    $sql_assets_select = mysqli_query($mysqli, "
                        SELECT assets.asset_id, asset_name
                        FROM assets
                        LEFT JOIN asset_documents
                            ON assets.asset_id = asset_documents.asset_id
                            AND asset_documents.document_id = $document_id
                        WHERE asset_client_id = $client_id
                        AND asset_archived_at IS NULL
                        AND asset_documents.asset_id IS NULL
                        ORDER BY asset_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_assets_select)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = nullable_htmlentities($row['asset_name']);

                        ?>
                        <option value="<?= $asset_id ?>"><?= $asset_name ?></option>
                        <?php
                    }
                    ?>

                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_asset_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
