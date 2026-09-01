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
    <h5 class="modal-title"><i class="fas fa-fw fa-upload me-2"></i>Import IP Addresses</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="network_id" value="<?= $network_id ?>">

    <div class="modal-body">
        <p><strong>Format csv file with headings &amp; data:</strong><br>IP Address, Hostname, Description</p>
        <p class="text-secondary mb-0">Importing into <strong><?= $network_name ?></strong> (<span class="font-monospace"><?= $network ?></span>). Rows that aren't a valid address, fall outside the subnet, or are already documented here are skipped.</p>
        <hr>
        <div class="mb-3 my-4">
            <input type="file" class="form-control" name="file" accept=".csv" required>
        </div>
        <hr>
        <div>Download: <a class="text-bold" href="post.php?download_network_ips_csv_template=<?= $network_id ?>">sample csv template</a></div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="import_network_ips_csv" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i>Import</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
