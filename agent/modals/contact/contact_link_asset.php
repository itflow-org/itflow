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
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop me-2"></i>Link Asset to <strong><?= $contact_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                <select class="form-select select2" name="asset_id">
                    <option value="">- Select an Asset -</option>
                    <?php
                    $sql_assets_select = mysqli_query($mysqli, "
                        SELECT asset_id, asset_name
                        FROM assets
                        WHERE asset_client_id = $client_id
                        AND asset_contact_id = 0
                        AND asset_archived_at IS NULL
                        ORDER BY asset_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_assets_select)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = escapeHtml($row['asset_name']);
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
        <button type="submit" name="link_contact_to_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
?>
