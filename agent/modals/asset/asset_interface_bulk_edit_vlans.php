<?php

/*
 * Bulk - the VLANs of the interfaces ticked on an asset's Interfaces tab: a
 * switch's access ports to one VLAN, its uplinks to a trunk.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$interface_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['interface_ids'] ?? [])))));
$count = count($interface_ids);

ob_start();

?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($interface_ids as $interface_id) { ?><input type="hidden" name="interface_ids[]" value="<?= $interface_id ?>"><?php } ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-layer-group me-2"></i>VLANs of <strong><?= $count ?></strong> interface<?= $count === 1 ? '' : 's' ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
        <label class="form-label">VLANs</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
            <input type="text" class="form-control" name="bulk_vlans" maxlength="200" placeholder="20, or Trunk 1, 10-20" autofocus>
        </div>
        <div class="form-text">Left empty, their VLANs are cleared.</div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_asset_interface_vlans" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
