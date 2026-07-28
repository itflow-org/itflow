<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales');

$invoice_id = intval($_GET['invoice_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = escapeHtml($row['invoice_number']);
$invoice_status = escapeHtml($row['invoice_status']);
$invoice_amount = floatval($row['invoice_amount']);
$invoice_currency_code = escapeHtml($row['invoice_currency_code']);
$client_id = intval($row['invoice_client_id']);

enforceClientAccess();

$invoice_badge_color = getInvoiceBadgeColor($invoice_status);

$sql_payments = mysqli_query(
    $mysqli,
    "SELECT * FROM payments
    LEFT JOIN accounts ON payment_account_id = account_id
    WHERE payment_invoice_id = $invoice_id
    AND payment_archived_at IS NULL
    ORDER BY payment_date ASC, payment_id ASC"
);

// Amount paid, so the modal shows what is still outstanding alongside the payments
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id AND payment_archived_at IS NULL"));
$amount_paid = floatval($row['amount_paid']);

$balance = $invoice_amount - $amount_paid;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-white">
        <i class="fa fa-fw fa-credit-card mr-2"></i>Payments for <?= "$invoice_prefix$invoice_number" ?>
        <span class="p-2 ml-2 badge badge-<?= $invoice_badge_color ?>"><?= $invoice_status ?></span>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body">

    <?php if (mysqli_num_rows($sql_payments) == 0) { ?>

        <p class="text-muted mb-0">No payments have been recorded against this invoice.</p>

    <?php } else { ?>

        <div class="table-responsive">
            <table class="table table-striped table-borderless mb-0">
                <thead class="text-dark text-nowrap">
                <tr>
                    <th>Date</th>
                    <th class="text-right">Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Account</th>
                </tr>
                </thead>
                <tbody>

                <?php

                while ($row = mysqli_fetch_assoc($sql_payments)) {
                    $payment_id = intval($row['payment_id']);
                    $payment_date = escapeHtml($row['payment_date']);
                    $payment_amount = floatval($row['payment_amount']);
                    $payment_currency_code = escapeHtml($row['payment_currency_code']);
                    $payment_method = escapeHtml($row['payment_method']);
                    $payment_reference = escapeHtml($row['payment_reference']);
                    if (empty($payment_reference)) {
                        $payment_reference_display = "-";
                    } else {
                        $payment_reference_display = $payment_reference;
                    }
                    $account_name = escapeHtml($row['account_name']);
                    $account_archived_at = escapeHtml($row['account_archived_at']);
                    if (empty($account_archived_at)) {
                        $account_archived_display = "";
                    } else {
                        $account_archived_display = "Archived - ";
                    }

                    ?>

                    <tr>
                        <td>
                            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                                <a class="ajax-modal" href="#"
                                    data-modal-size = "lg"
                                    data-modal-url = "modals/payment/payment_edit.php?id=<?= $payment_id ?>">
                                    <?= $payment_date ?>
                                </a>
                            <?php } else { ?>
                                <?= $payment_date ?>
                            <?php } ?>
                        </td>
                        <td class="text-right text-monospace"><?= numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code) ?></td>
                        <td><?= $payment_method ?></td>
                        <td><?= $payment_reference_display ?></td>
                        <td><?= "$account_archived_display$account_name" ?></td>
                    </tr>

                <?php

                }

                ?>

                </tbody>
            </table>
        </div>

    <?php } ?>

    <hr>

    <div class="row">
        <div class="col-6 text-right ml-auto">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td>Invoice Total:</td>
                    <td class="text-right text-monospace"><?= numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) ?></td>
                </tr>
                <tr>
                    <td>Amount Paid:</td>
                    <td class="text-right text-monospace"><?= numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code) ?></td>
                </tr>
                <tr class="text-bold">
                    <td>Balance:</td>
                    <td class="text-right text-monospace"><?= numfmt_format_currency($currency_format, $balance, $invoice_currency_code) ?></td>
                </tr>
            </table>
        </div>
    </div>

</div>
<div class="modal-footer">
    <a href="invoice.php?client_id=<?= $client_id ?>&invoice_id=<?= $invoice_id ?>" class="btn btn-primary text-bold">
        <i class="fa fa-fw fa-file-invoice-dollar mr-2"></i>Open Invoice
    </a>
    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
