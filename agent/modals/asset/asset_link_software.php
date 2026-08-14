<?php

require_once '../../../includes/modal_header.php';

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT asset_client_id, asset_name FROM assets
    WHERE asset_id = $asset_id
    LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$asset_name = escapeHtml($row['asset_name']);
$client_id = intval($row['asset_client_id']);

enforceClientAccess();

ob_start();

?>


<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube me-2"></i>License Software to <strong><?= $asset_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <select class="form-select select2" name="software_id">
                    <option value="">- Select a Device Software License -</option>
                    <?php
                    $sql_software_select = mysqli_query($mysqli, "
                        SELECT software.software_id, software.software_name
                        FROM software
                        LEFT JOIN software_assets
                        ON software.software_id = software_assets.software_id
                        AND software_assets.asset_id = $asset_id
                        WHERE software.software_client_id = $client_id
                        AND software.software_archived_at IS NULL
                        AND software.software_license_type = 'Device'
                        AND software_assets.asset_id IS NULL
                        ORDER BY software.software_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_software_select)) {
                        $software_id = intval($row['software_id']);
                        $software_name = escapeHtml($row['software_name']);

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
        <button type="submit" name="link_software_to_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
