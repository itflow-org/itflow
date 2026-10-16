<?php

/*
 * Document an address on a network. Opened with ?ip= (a free run's Use, a
 * device's Document) it starts on that address, with ?hostname= ?mac=
 * ?description= ?type= filled in; ?next=1 starts on the next free one.
 */

require_once '../../../includes/modal_header.php';

$network = networkForModal($_GET['network_id'] ?? 0, 2);
$network_id = intval($network['network_id']);
$is_new = true;

$ip = normalizeIpAddress((string) ($_GET['ip'] ?? ''));
if ($ip === false && !empty($_GET['next'])) {
    $ip = networkAddressPlan($network)['next_free'] ?: false;
}
$form = [
    'ip_address' => $ip === false ? '' : $ip,
    'ip_type' => $_GET['type'] ?? '',
    'ip_hostname' => (string) ($_GET['hostname'] ?? ''),
    'ip_mac' => (string) ($_GET['mac'] ?? ''),
    'ip_description' => (string) ($_GET['description'] ?? ''),
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-pin me-2"></i>Document an address</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="network_id" value="<?= $network_id ?>">

    <div class="modal-body">
        <?php require 'network_ip_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_network_ip" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    // Live version of the duplicate / inside-the-subnet checks. Advisory only -
    // agent/post/network_ip.php runs the same checks again on submit.
    itflowWatchNetworkIp(<?= $network_id ?>, 0);
    // An address it was opened with is checked straight away
    if (document.getElementById('networkIpAddress').value.trim() !== '') {
        document.getElementById('networkIpAddress').dispatchEvent(new Event('blur'));
    }
</script>

<?php
require_once '../../../includes/modal_footer.php';
