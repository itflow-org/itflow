<?php

require_once '../../../includes/modal_header.php';

$invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT invoice_amount, invoice_client_id, invoice_number, invoice_prefix FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$invoice_amount = floatval($row['invoice_amount']);
$client_id = intval($row['invoice_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card me-2"></i><?= "$invoice_prefix$invoice_number" ?>: Make Payment</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <div class="modal-body">

       <h2>Paying <strong><?= $invoice_amount ?></strong> Amount</h2>

        <div class="mb-3">
            <label>Payment Method <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                <select class="form-select select2" name="saved_payment_id" required>
                    <option value="">- Saved Payment Methods -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT saved_payment_description, saved_payment_id FROM client_saved_payment_methods WHERE saved_payment_client_id = $client_id ORDER BY saved_payment_description ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $saved_payment_id = intval($row['saved_payment_id']);
                        $saved_payment_description = escapeHtml($row['saved_payment_description']);
                    ?>
                        <option value="<?= $saved_payment_id ?>"><?= $saved_payment_description ?></option>

                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_payment_stripe" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Pay</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
