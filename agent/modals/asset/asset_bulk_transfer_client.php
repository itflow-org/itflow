<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);
$count = count($selected_ids);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    $client_select_query = "AND client_id != $client_id";
} else {
    $client_select_query = '';
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-exchange-alt mr-2"></i>Transfer <strong><?= $count ?></strong> Asset(s) to Client</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="asset_ids[]" value="<?= $id ?>"><?php } ?>

    <div class="modal-body">

        <div class="form-group">
            <label>Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                </div>
                <select class="form-control select2" name="bulk_client_id">
                    <option value="">- Select Client -</option>
                    <?php
                        $clients_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $client_select_query");
                
                        while ($row = mysqli_fetch_array($clients_sql)) {
                            $client_id_select = intval($row["client_id"]);
                            $client_name_select = nullable_htmlentities($row["client_name"]);
                        ?>
                        <option value='<?php echo $client_id_select; ?>'><?php echo $client_name_select; ?></option>
                        <?php
                        }
                    ?>
                </select>
            </div>
        </div>

        <div class="alert alert-dark" role="alert">
            <i>The current asset will be archived and content copied to a new asset.</i>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_transfer_client_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Transfer to Client</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
