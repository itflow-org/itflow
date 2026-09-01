<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT client_name, invoice_client_id, invoice_number, invoice_prefix FROM invoices LEFT JOIN clients ON invoice_client_id = client_id WHERE invoice_id = $invoice_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['invoice_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy me-2"></i>Copying invoice: <strong><?= "$invoice_prefix$invoice_number" ?></strong> - <?= $client_name ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Invoice Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date("Y-m-d") ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_invoice_copy" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
