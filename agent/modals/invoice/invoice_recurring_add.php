<?php

require_once '../../../includes/modal_header.php';

$invoice_id = intval($_GET['invoice_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$client_id = intval($row['invoice_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy mr-2"></i>Invoice <?= "$invoice_prefix$invoice_number" ?> to Recurring Invoice</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <div class="modal-body">
        <div class="form-group">
            <label>Frequency <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                </div>
                <select class="form-control select2" name="frequency" required>
                    <option value="">- Frequency -</option>
                    <option value="month">Monthly</option>
                    <option value="year">Yearly</option>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_invoice_recurring" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create Recurring Invoice</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
