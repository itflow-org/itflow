<?php

/*
 * Bulk - the network of the interfaces ticked on an asset's Interfaces tab.
 * One whose address is outside the network picked is still moved, and said.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();
$interface_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['interface_ids'] ?? [])))));
$count = count($interface_ids);
$networks = interfaceNetworkOptions($client_id);

ob_start();

?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($interface_ids as $interface_id) { ?><input type="hidden" name="interface_ids[]" value="<?= $interface_id ?>"><?php } ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-network-wired me-2"></i>Network of <strong><?= $count ?></strong> interface<?= $count === 1 ? '' : 's' ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
        <label class="form-label">Network</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
            <select class="form-select select2" name="bulk_network">
                <option value="0">- No network -</option>
                <?php foreach ($networks as $network) { ?>
                    <option value="<?= intval($network['network_id']) ?>"<?= selectOptionNetwork($network) ?>><?= escapeHtml($network['network_name']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_asset_interface_network" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
