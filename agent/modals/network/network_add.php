<?php

/*
 * New network - for the client it was opened from, or pick the client here.
 * ?location_id= starts it at that site, ?type= as that type.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$is_new = true;
$form_client_id = $client_id;
// At the site it was opened from - or the client's primary one
$location_id = $client_id ? assetClientOwnedId('location', $_GET['location_id'] ?? 0, $client_id) : 0;
if ($client_id && !$location_id) {
    $location_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_client_id = $client_id AND location_primary = 1 AND location_archived_at IS NULL LIMIT 1"))[0] ?? 0);
}
$form = [
    'network_location_id' => $location_id,
    'network_type' => isset(NETWORK_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '',
    'client_name' => $client_id ? getFieldById('clients', $client_id, 'client_name') : '',
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-network-wired me-2"></i>New Network</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require 'network_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_network" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/network_form.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
