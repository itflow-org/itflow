<?php

/*
 * Edit an interface - asset_interface_form.php. From the asset's Interfaces
 * tab, and from a network's Devices tab.
 */

require_once '../../../includes/modal_header.php';

$form = interfaceForModal($_GET['id'] ?? 0, 2);
$interface_id = intval($form['interface_id']);
$asset = ['asset_id' => $form['asset_id'], 'asset_name' => $form['asset_name'], 'asset_type' => $form['asset_type'], 'asset_client_id' => $form['asset_client_id'],
    'client_name' => getFieldById('clients', $form['asset_client_id'], 'client_name')];
$is_new = false;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-ethernet me-2"></i><?= escapeHtml($form['asset_name']) ?> - <strong><?= escapeHtml($form['interface_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="interface_id" value="<?= $interface_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require 'asset_interface_form.php'; ?>
    </div>
    <div class="modal-footer">
        <?php if (!intval($form['interface_primary'])) { ?>
            <a class="btn btn-outline-danger me-auto confirm-link" href="post.php?delete_asset_interface=<?= $interface_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                data-confirm-text="<?= escapeHtml($form['interface_name']) ?> goes for good<?= $form['link'] ? ', and its connection to ' . escapeHtml($form['link']['asset_name'] . ' ' . $form['link']['interface_name']) . ' with it' : '' ?>."><i class="fas fa-trash-alt me-2"></i>Delete</a>
        <?php } ?>
        <button type="submit" name="edit_asset_interface" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/interface_form.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
