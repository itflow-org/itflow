<?php

/*
 * New interface on an asset - asset_interface_form.php. ?network_id= starts it
 * on that network.
 */

require_once '../../../includes/modal_header.php';

$asset = interfaceAssetForModal($_GET['asset_id'] ?? 0);
$asset_id = intval($asset['asset_id']);
$asset['client_name'] = getFieldById('clients', $asset['asset_client_id'], 'client_name');
$is_new = true;

// The first one an asset has is its primary
$has_interfaces = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_archived_at IS NULL LIMIT 1"));
$form = [
    'interface_primary' => $has_interfaces ? 0 : 1,
    'interface_network_id' => assetClientOwnedId('network', $_GET['network_id'] ?? 0, $asset['asset_client_id']),
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-ethernet me-2"></i>New interface on <strong><?= escapeHtml($asset['asset_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require 'asset_interface_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset_interface" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/interface_form.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
