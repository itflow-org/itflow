<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ip_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT ip_address, ip_description, ip_hostname, ip_network_id, network, network_client_id, network_name FROM network_ips
    LEFT JOIN networks ON network_id = ip_network_id
    WHERE ip_id = $ip_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$network_id = intval($row['ip_network_id']);
$ip_address = escapeHtml($row['ip_address']);
$ip_hostname = escapeHtml($row['ip_hostname']);
$ip_description = escapeHtml($row['ip_description']);
$network_name = escapeHtml($row['network_name']);
$network = escapeHtml($row['network']);
$client_id = intval($row['network_client_id']);

enforceClientAccess();

// Same split as the add modal - the fixed octets are shown, the host part is
// what's editable. An address that predates the subnet (or sits outside it)
// won't match the prefix and is shown in full so it can still be corrected.
$ip_fixed_octets = ipSubnetFixedOctets($row['network']);
$ip_host_octets = $ip_fixed_octets ? 4 - substr_count($ip_fixed_octets, '.') : 0;
$ip_host_value = escapeHtml(ipSuffixForDisplay($row['ip_address'], $row['network']));
$ip_prefix_matched = ($ip_fixed_octets !== '' && $ip_host_value !== $ip_address);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-pin me-2"></i>Editing <span class="font-monospace"><?= $ip_address ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ip_id" value="<?= $ip_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>IP Address <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <?php if ($ip_prefix_matched) { ?>
                    <span class="input-group-text font-monospace text-bold"><?= escapeHtml($ip_fixed_octets) ?></span>
                    <input type="text" class="form-control font-monospace" name="ip_address" id="networkIpAddress"
                        value="<?= $ip_host_value ?>"
                        <?= $ip_host_octets === 1 ? 'inputmode="numeric"' : '' ?>
                        maxlength="<?= ($ip_host_octets * 4) - 1 ?>" required>
                <?php } else { ?>
                    <span class="input-group-text"><i class="fa fa-fw fa-map-pin"></i></span>
                    <input type="text" class="form-control font-monospace" name="ip_address" id="networkIpAddress"
                        value="<?= $ip_address ?>" maxlength="45" required>
                <?php } ?>
            </div>
            <div class="small mt-1" id="networkIpFeedback"></div>
            <small class="text-secondary"><?= $network_name ?> &mdash; <span class="font-monospace"><?= $network ?></span></small>
        </div>

        <div class="mb-3">
            <label>Hostname</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                <input type="text" class="form-control" name="hostname" value="<?= $ip_hostname ?>" maxlength="200">
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" value="<?= $ip_description ?>" maxlength="200">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_network_ip" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    // This row's own id is passed so saving an unchanged address doesn't report
    // itself as a duplicate
    itflowWatchNetworkIp(<?= $network_id ?>, <?= $ip_id ?>);
</script>

<?php

require_once '../../../includes/modal_footer.php';
