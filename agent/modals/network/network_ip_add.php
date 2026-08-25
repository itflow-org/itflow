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
                    <span class="input-group-text"><i class="fa fa-fw fa-map-pin"></i></span>
                <input type="text" class="form-control font-monospace" name="ip_address" placeholder="Must be inside <?= $network ?>" maxlength="45" required autofocus>
            </div>
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

<?php

require_once '../../../includes/modal_footer.php';
