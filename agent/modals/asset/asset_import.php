<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop me-2"></i>Import Assets</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">
        <p><strong>A CSV file with these headings</strong>, in any order - leave out the ones you don't have. Only Name is needed, and a file exported from the assets list imports as it is:<br><?= escapeHtml(importHeadingsText('assets')) ?></p>
        <p class="small text-body-secondary mb-0">Assigned To, Location, Vendor and Network are the client's own, by name - Assigned To by email too. Tags are separated by commas. Any of an asset's details can be added too, headed as the asset export heads them - <span class="font-monospace">Ports</span>, <span class="font-monospace">SFP Ports</span>, <span class="font-monospace">RAM (GB)</span>, <span class="font-monospace">Capacity (VA)</span> and so on. Each is kept where the row's type has it, and Ports and SFP Ports add its interfaces.</p>
        <hr>
        <div class="mb-3 my-4">
            <input type="file" class="form-control" name="file" accept=".csv" required>
        </div>
        <hr>
        <div>Download <a href="post.php?download_assets_csv_template=<?= $client_id ?>">sample csv template</a></div>
        <small class="text-muted">Note: dates must be in the format YYYY-MM-DD. Spreadsheet tools may automatically reformat dates.</small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="import_assets_csv" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Import</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
