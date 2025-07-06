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
    <h5 class="modal-title"><i class="fa fa-fw fa-stream mr-2"></i>Link Service to <strong><?php echo $asset_name; ?></strong></h5>
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
                    <span class="input-group-text"><i class="fa fa-fw fa-stream"></i></span>
                </div>
                <select class="form-control select2" name="service_id">
                    <option value="">- Select a Service -</option>
                    <?php
                    $sql_services_select = mysqli_query($mysqli, "
                        SELECT services.service_id, services.service_name
                        FROM services
                        LEFT JOIN service_assets
                        ON services.service_id = service_assets.service_id
                        AND service_assets.asset_id = $asset_id
                        WHERE services.service_client_id = $client_id
                        AND service_assets.asset_id IS NULL
                        ORDER BY services.service_name ASC
                    ");
                    while ($row = mysqli_fetch_array($sql_services_select)) {
                        $service_id = intval($row['service_id']);
                        $service_name = nullable_htmlentities($row['service_name']);
                        ?>
                        <option value="<?php echo $service_id ?>"><?php echo $service_name; ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="link_service_to_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
