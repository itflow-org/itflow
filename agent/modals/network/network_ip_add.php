<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$network_id = intval($_GET['network_id'] ?? 0);

$sql = mysqli_query($mysqli, "SELECT network, network_client_id, network_name FROM networks WHERE network_id = $network_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$network_name = escapeHtml($row['network_name']);
$network = escapeHtml($row['network']);
$client_id = intval($row['network_client_id']);

enforceClientAccess();

// The part of the address this subnet fixes. Empty for IPv6, a prefix under
// /8, or a subnet that won't parse - then the field takes a whole address.
$ip_fixed_octets = ipSubnetFixedOctets($row['network']);
$ip_host_octets = $ip_fixed_octets ? 4 - substr_count($ip_fixed_octets, '.') : 0;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-pin me-2"></i>New IP Address</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="network_id" value="<?= $network_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>IP Address <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <?php if ($ip_fixed_octets) { ?>
                    <span class="input-group-text font-monospace text-bold"><?= escapeHtml($ip_fixed_octets) ?></span>
                    <input type="text" class="form-control font-monospace" name="ip_address" id="networkIpAddress"
                        placeholder="<?= $ip_host_octets === 1 ? '10' : str_repeat('0.', $ip_host_octets - 1) . '10' ?>"
                        <?= $ip_host_octets === 1 ? 'inputmode="numeric"' : '' ?>
                        maxlength="<?= ($ip_host_octets * 4) - 1 ?>" required autofocus>
                <?php } else { ?>
                    <span class="input-group-text"><i class="fa fa-fw fa-map-pin"></i></span>
                    <input type="text" class="form-control font-monospace" name="ip_address" id="networkIpAddress"
                        placeholder="Must be inside <?= $network ?>" maxlength="45" required autofocus>
                <?php } ?>
            </div>
            <div class="small mt-1" id="networkIpFeedback"></div>
            <small class="text-secondary"><?= $network_name ?> &mdash; <span class="font-monospace"><?= $network ?></span></small>
        </div>

        <div class="mb-3">
            <label>Hostname</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                <input type="text" class="form-control" name="hostname" placeholder="dc01, printer-hr, ap-lobby" maxlength="200">
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Domain controller, HR floor printer" maxlength="200">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_network_ip" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    // Live version of the duplicate / inside-the-subnet checks. Advisory only -
    // agent/post/network_ip.php runs the same checks again on submit.
    itflowWatchNetworkIp(<?= $network_id ?>, 0);
</script>

<?php

require_once '../../../includes/modal_footer.php';
