<?php
/*
 * Client Portal
 * Account Statement
 *
 * SCOPING: every query on this page is keyed to $session_client_id, which
 * check_login.php sets from the session. Nothing here reads a client id from
 * the request, so there is no id for a contact to tamper with - and
 * enforceContactCan('accounting') keeps it to primary and billing contacts,
 * matching invoices.php and quotes.php.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";


enforceContactCan('accounting');

/*
 * Amounts render in the client's own currency, matching the guest invoice view
 * and the statement an agent emails. This page and its PDF used to render in
 * the company currency, so the same invoice carried a different symbol
 * depending on where you read it.
 */
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_currency_code FROM clients WHERE client_id = $session_client_id LIMIT 1"));
$statement_currency_code = escapeHtml($row['client_currency_code']);
if (empty($statement_currency_code)) {
    $statement_currency_code = $session_company_currency;
}

/*
 * Payments are summed in a derived table rather than joined directly, or an
 * invoice with two payments against it would be counted twice.
 *
 * Draft / Cancelled / Non-Billable are not money owed, and the balance test
 * drops anything fully paid, so Paid invoices fall out without naming them.
 */
$statement_sql = mysqli_query(
    $mysqli,
    "SELECT invoice_amount, invoice_date, invoice_due, invoice_id, invoice_number, invoice_prefix,
        invoice_scope, invoice_url_key, IFNULL(amount_paid, 0) AS amount_paid
    FROM invoices
    LEFT JOIN (
        SELECT payment_invoice_id, SUM(payment_amount) AS amount_paid FROM payments
        WHERE payment_archived_at IS NULL
        GROUP BY payment_invoice_id
    ) AS invoice_payments ON payment_invoice_id = invoice_id
    WHERE invoice_client_id = $session_client_id
    AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
    AND invoice_amount - IFNULL(invoice_payments.amount_paid, 0) > 0
    ORDER BY invoice_date ASC, invoice_number ASC"
);

$statement_count = mysqli_num_rows($statement_sql);
$statement_total = 0;

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Account Statement</h3>
    <?php if ($statement_count > 0) { ?>
        <a class="btn btn-primary" href="post.php?export_statement_pdf=1&csrf_token=<?= $_SESSION['csrf_token'] ?>">
            <i class="fa fa-fw fa-download me-2"></i>Download PDF
        </a>
    <?php } ?>
</div>

<div class="row">

    <div class="col-md-10">

        <?php if ($statement_count == 0) { ?>

            <?= portalEmptyState('There is nothing outstanding on this account.', 'fa-check', 'success') ?>

        <?php } else { ?>

            <table class="table table-bordered border border-dark">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Scope</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                </tr>
                </thead>
                <tbody>

                <?php

                while ($row = mysqli_fetch_assoc($statement_sql)) {
                    $invoice_id = intval($row['invoice_id']);
                    $invoice_prefix = escapeHtml($row['invoice_prefix']);
                    $invoice_number = intval($row['invoice_number']);
                    $invoice_scope = escapeHtml($row['invoice_scope']);
                    $invoice_date = escapeHtml($row['invoice_date']);
                    $invoice_due = escapeHtml($row['invoice_due']);
                    $invoice_url_key = escapeHtml($row['invoice_url_key']);
                    $invoice_amount = floatval($row['invoice_amount']);
                    $amount_paid = floatval($row['amount_paid']);
                    $invoice_balance = $invoice_amount - $amount_paid;

                    $statement_total = $statement_total + $invoice_balance;

                    if (empty($invoice_scope)) {
                        $invoice_scope_display = "-";
                    } else {
                        $invoice_scope_display = $invoice_scope;
                    }

                    // Same one-day grace as invoices.php, so the two pages agree
                    // on what counts as late
                    if (strtotime($invoice_due) + 86400 < time()) {
                        $overdue_color = "text-danger fw-bold";
                    } else {
                        $overdue_color = "";
                    }

                    ?>

                    <tr>
                        <td>
                            <a target="_blank" href="//<?= $config_base_url ?>/guest/guest_view_invoice.php?invoice_id=<?= "$invoice_id&url_key=$invoice_url_key" ?>">
                                <?= "$invoice_prefix$invoice_number" ?>
                            </a>
                        </td>
                        <td><?= $invoice_scope_display ?></td>
                        <td><?= $invoice_date ?></td>
                        <td class="<?= $overdue_color ?>"><?= $invoice_due ?></td>
                        <td class="text-end"><?= numfmt_format_currency($currency_format, $invoice_amount, $statement_currency_code) ?></td>
                        <td class="text-end"><?= numfmt_format_currency($currency_format, $amount_paid, $statement_currency_code) ?></td>
                        <td class="text-end fw-bold"><?= numfmt_format_currency($currency_format, $invoice_balance, $statement_currency_code) ?></td>
                    </tr>

                    <?php

                }

                ?>

                </tbody>
                <tfoot>
                <tr>
                    <th colspan="6" class="text-end">Total Balance Due</th>
                    <th class="text-end"><?= numfmt_format_currency($currency_format, $statement_total, $statement_currency_code) ?></th>
                </tr>
                </tfoot>
            </table>

        <?php } ?>

    </div>

</div>


<?php
require_once "includes/footer.php";
