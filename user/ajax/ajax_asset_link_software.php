<?php

require_once '../../includes/modal_header.php';

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


<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube mr-2"></i>License Software to <strong><?php echo $asset_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                </div>
                <select class="form-control select2" name="software_id">
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
                    while ($row = mysqli_fetch_array($sql_software_select)) {
                        $software_id = intval($row['software_id']);
                        $software_name = nullable_htmlentities($row['software_name']);

                        ?>
                        <option value="<?php echo $software_id ?>"><?php echo $software_name; ?></option>
                        <?php
                    }
                    ?>

                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_software_to_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../includes/modal_footer.php';