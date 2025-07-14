<?php

require_once '../includes/ajax_header.php';

$invoice_id = intval($_GET['id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
    WHERE invoice_id = $invoice_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$invoice_id = intval($row['invoice_id']);
$invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$invoice_amount = floatval($row['invoice_amount']);
$client_id = intval($row['client_id']);
$client_name = nullable_htmlentities($row['client_name']);
$client_currency_code = nullable_htmlentities($row['client_currency_code']);
$contact_name = nullable_htmlentities($row['contact_name']);
$contact_email = nullable_htmlentities($row['contact_email']);


//Add up all the payments for the invoice and get the total amount paid to the invoice
$sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
$row = mysqli_fetch_array($sql_amount_paid);
$amount_paid = floatval($row['amount_paid']);

$balance = $invoice_amount - $amount_paid;

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i><?php echo "$invoice_prefix$invoice_number"; ?>: Make Payment</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
    <input type="hidden" name="balance" value="<?php echo $balance; ?>">
    <input type="hidden" name="currency_code" value="<?php echo $client_currency_code; ?>">
    <div class="modal-body bg-white">

        <div class="form-row">
            <div class="col-md">

                <div class="form-group">
                    <label>Date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        </div>
                        <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>" required>
                    </div>
                </div>

            </div>

            <div class="col-md">

                <div class="form-group">
                    <label>Amount <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" value="<?php echo number_format($balance, 2, '.', ''); ?>" placeholder="0.00" required>
                    </div>
                </div>

            </div>

        </div>

        <div class="form-group">
            <label>Account <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                </div>
                <select class="form-control select2" name="account" required>
                    <option value="">- Select an Account -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                        $account_id = intval($row['account_id']);
                        $account_name = nullable_htmlentities($row['account_name']);
                        $opening_balance = floatval($row['opening_balance']);

                        $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
                        $row = mysqli_fetch_array($sql_payments);
                        $total_payments = floatval($row['total_payments']);

                        $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
                        $row = mysqli_fetch_array($sql_revenues);
                        $total_revenues = floatval($row['total_revenues']);

                        $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
                        $row = mysqli_fetch_array($sql_expenses);
                        $total_expenses = floatval($row['total_expenses']);

                        $account_balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                    ?>
                        <option <?php if ($config_default_payment_account == $account_id) { echo "selected"; } ?>
                            value="<?php echo $account_id; ?>">
                            <?php echo $account_name; ?> [$<?php echo number_format($account_balance, 2); ?>]
                        </option>

                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Payment Method <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                </div>
                <select class="form-control select2" name="payment_method" required>
                    <option value="">- Method of Payment -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT * FROM payment_methods ORDER BY payment_method_name ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                        $payment_method_name = nullable_htmlentities($row['payment_method_name']);
                    ?>
                        <option <?php if ($config_default_payment_method == $payment_method_name) { echo "selected"; } ?>><?php echo $payment_method_name; ?></option>

                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Reference</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                </div>
                <input type="text" class="form-control" name="reference" placeholder="Check #, Trans #, etc" maxlength="200">
            </div>
        </div>

        <?php if (!empty($config_smtp_host) && !empty($contact_email)) { ?>

            <div class="form-group">
                <label>Email Receipt</label>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="email_receipt" value="1" checked>
                    <label class="custom-control-label" for="customControlAutosizing"><?php echo $contact_email; ?></label>
                </div>
            </div>

        <?php } ?>

    </div>

    <div class="modal-footer bg-white">
        <button type="submit" name="add_payment" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Pay</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";
