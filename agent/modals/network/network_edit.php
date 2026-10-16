<?php

/*
 * Edit network - its details, addressing and notes (network_form.php).
 */

require_once '../../../includes/modal_header.php';

$form = networkForModal($_GET['id'] ?? 0, 2);
$network_id = intval($form['network_id']);
$form['network_type'] = networkType($form);
$is_new = false;
$form_client_id = intval($form['network_client_id']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-network-wired me-2"></i>Editing network: <span class="text-bold"><?= escapeHtml($form['network_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="network_id" value="<?= $network_id ?>">

    <div class="modal-body">
        <?php require 'network_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_network" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/network_form.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
