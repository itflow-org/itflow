<?php

/*
 * Edit a documented address - network_ip_form.php.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$form = networkIpRow($_GET['id'] ?? 0);
if (!$form) {
    echo json_encode(['error' => 'That address is no longer documented.']);
    exit;
}
$network = networkForModal($form['ip_network_id'], 2);
$network_id = intval($network['network_id']);
$ip_id = intval($form['ip_id']);
$is_new = false;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-pin me-2"></i>Editing <span class="font-monospace"><?= escapeHtml($form['ip_address']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ip_id" value="<?= $ip_id ?>">

    <div class="modal-body">
        <?php require 'network_ip_form.php'; ?>
    </div>
    <div class="modal-footer">
        <a class="btn btn-outline-danger me-auto confirm-link" href="post.php?delete_network_ip=<?= $ip_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= escapeHtml($form['ip_address']) ?> is no longer documented."><i class="fas fa-trash-alt me-2"></i>Delete</a>
        <button type="submit" name="edit_network_ip" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    // Live version of the duplicate / inside-the-subnet checks - the address's own row excluded
    itflowWatchNetworkIp(<?= $network_id ?>, <?= $ip_id ?>);
    // An address it was opened with is checked straight away
    if (document.getElementById('networkIpAddress').value.trim() !== '') {
        document.getElementById('networkIpAddress').dispatchEvent(new Event('blur'));
    }
</script>

<?php
require_once '../../../includes/modal_footer.php';
