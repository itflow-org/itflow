<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

// Scoped to one network, so there are no page filters to inherit - column
// selection only, same as the asset interfaces export
$network_id = intval($_GET['network_id'] ?? 0);

$client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export IP Addresses</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="network_id" value="<?= $network_id ?>">

    <div class="modal-body">

        <?php renderExportColumnPicker('network_ips'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_network_ips'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
