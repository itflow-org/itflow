<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id']);
$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt mr-2"></i>Assign Location to <strong><?= $count ?></strong> Assets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="asset_ids[]" value="<?= $id ?>"><?php } ?>

    <div class="modal-body">

        <div class="form-group">
            <label>Location</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                </div>
                <select class="form-control select2" name="bulk_location_id">
                    <option value="">- Location -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                        $location_id = intval($row['location_id']);
                        $location_name = nullable_htmlentities($row['location_name']);
                    ?>
                        <option value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_asset_location" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Assign Location</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
