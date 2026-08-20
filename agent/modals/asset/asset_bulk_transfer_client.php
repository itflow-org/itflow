<?php

require_once '../../../includes/modal_header.php';

$asset_ids = array_map('intval', $_GET['asset_ids'] ?? []);
$count = count($asset_ids);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    $client_select_query = "AND client_id != $client_id";
} else {
    $client_select_query = '';
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-exchange-alt me-2"></i>Transfer <strong><?= $count ?></strong> Asset(s) to Client</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($asset_ids as $asset_id) { ?><input type="hidden" name="asset_ids[]" value="<?= $asset_id ?>"><?php } ?>

    <div class="modal-body">

        <div class="mb-3">
            <label>Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <select class="form-select select2" name="bulk_client_id">
                    <option value="">- Select Client -</option>
                    <?php
                        $clients_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $client_select_query");

                        while ($row = mysqli_fetch_assoc($clients_sql)) {
                            $client_id_select = intval($row["client_id"]);
                            $client_name_select = escapeHtml($row["client_name"]);
                        ?>
                        <option value='<?= $client_id_select ?>'><?= $client_name_select ?></option>
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
        <button type="submit" name="bulk_transfer_client_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Transfer to Client</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
