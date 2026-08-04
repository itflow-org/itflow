<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>Import Assets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">
        <p><strong>Format csv file with headings & data:</strong><br>Name, Description, Type, Make, Model, Serial, OS, Purchase Date, Assigned To, Location, Physical Location, Notes</p>
        <hr>
        <div class="form-group my-4">
            <input type="file" class="form-control-file" name="file" accept=".csv" required>
        </div>
        <hr>
        <div>Download <a href="post.php?download_assets_csv_template=<?= $client_id ?>">sample csv template</a></div>
        <small class="text-muted">Note: Purchase date must be in the format YYYY-MM-DD. Spreadsheet tools may automatically reformat dates.</small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="import_assets_csv" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Import</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
