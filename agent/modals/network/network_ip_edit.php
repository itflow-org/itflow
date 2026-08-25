<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ip_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT ip_address, ip_description, ip_hostname, ip_network_id, network, network_client_id, network_name FROM network_ips
    LEFT JOIN networks ON network_id = ip_network_id
    WHERE ip_id = $ip_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$ip_address = escapeHtml($row['ip_address']);
$ip_hostname = escapeHtml($row['ip_hostname']);
$ip_description = escapeHtml($row['ip_description']);
$network_name = escapeHtml($row['network_name']);
$network = escapeHtml($row['network']);
$client_id = intval($row['network_client_id']);

enforceClientAccess();

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
                    <span class="input-group-text"><i class="fa fa-fw fa-map-pin"></i></span>
                <input type="text" class="form-control font-monospace" name="ip_address" value="<?= $ip_address ?>" maxlength="45" required>
            </div>
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

<?php

require_once '../../../includes/modal_footer.php';
