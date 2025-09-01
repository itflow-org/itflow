<?php

require_once '../../../includes/modal_header.php';

$invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$invoice_amount = floatval($row['invoice_amount']);
$client_id = intval($row['invoice_client_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i><?php echo "$invoice_prefix$invoice_number"; ?>: Make Payment</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <div class="modal-body">

       <h2>Paying <strong><?= $invoice_amount ?></strong> Amount</h2>

        <div class="form-group">
            <label>Payment Method <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                </div>
                <select class="form-control select2" name="saved_payment_id" required>
                    <option value="">- Saved Payment Methods -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT * FROM client_saved_payment_methods WHERE saved_payment_client_id = $client_id ORDER BY saved_payment_description ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                        $saved_payment_id = intval($row['saved_payment_id']);
                        $saved_payment_description = nullable_htmlentities($row['saved_payment_description']);
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
        <button type="submit" name="add_payment_stripe" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Pay</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
