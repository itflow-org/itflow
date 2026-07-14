<?php

require_once '../../../includes/modal_header.php';

$payment_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM payments LEFT JOIN invoices ON invoice_id = payment_invoice_id WHERE payment_id = $payment_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$payment_date = escapeHtml($row['payment_date']);
$payment_method = escapeHtml($row['payment_method']);
$payment_amount = floatval($row['payment_amount']);
$payment_reference = escapeHtml($row['payment_reference']);
$payment_account_id = intval($row['payment_account_id']);
$client_id = intval($row['invoice_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Edit Payment</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
    <div class="modal-body">

        <div class="form-row">
            <div class="col-md">

                <div class="form-group">
                    <label>Date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        </div>
                        <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= $payment_date ?>" required>
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
                        <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" value="<?= $payment_amount ?>" placeholder="0.00" required>
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
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $account_id = intval($row['account_id']);
                        $account_name = escapeHtml($row['account_name']);
                        $opening_balance = floatval($row['opening_balance']);

                        $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
                        $row = mysqli_fetch_assoc($sql_payments);
                        $total_payments = floatval($row['total_payments']);

                        $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
                        $row = mysqli_fetch_assoc($sql_revenues);
                        $total_revenues = floatval($row['total_revenues']);

                        $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
                        $row = mysqli_fetch_assoc($sql_expenses);
                        $total_expenses = floatval($row['total_expenses']);

                        $account_balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                    ?>
                        <option <?php if ($payment_account_id == $account_id) { echo "selected"; } ?>
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

                    $sql = mysqli_query($mysqli, "SELECT payment_method_name FROM payment_methods ORDER BY payment_method_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $payment_method_name = escapeHtml($row['payment_method_name']);
                    ?>
                        <option <?php if ($payment_method == $payment_method_name) { echo "selected"; } ?>><?= $payment_method_name ?></option>

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
                <input type="text" class="form-control" name="reference" value="<?= $payment_reference ?>" placeholder="Check #, Trans #, etc" maxlength="200">
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_payment" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Changes</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
