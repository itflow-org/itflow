<?php
/*
 * Client Portal
 * Statement - what is owed and how late it is (current, then 1-30, 31-60,
 * 61-90 and over 90 days past due), with the invoices behind it; or
 * everything from the last twelve months, paid ones too. The PDF is the same
 * statement the agents email (billingStatement() in
 * functions/billing_email.php).
 *
 * SCOPING: billingStatement() is given $session_client_id from the session -
 * nothing here reads a client id from the request.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$with_paid = ($_GET['view'] ?? '') === 'year';
$statement = billingStatement($session_client_id, $with_paid ? date('Y-m-d', strtotime('-12 months')) : '', '', $with_paid);
$currency = $statement['currency'];
$cards = billingStripeProvider() !== null;
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Statement</h1>
        <p><?= escapeHtml($session_client_name) ?> · as of <?= escapeHtml(date('M j, Y')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($statement['invoices']) { ?>
            <a class="btn btn-outline-secondary" href="post.php?export_statement_pdf=1<?= $with_paid ? '&paid=1' : '' ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>
        <?php } ?>
        <?php if ($statement['total'] > 0 && $cards) { ?>
            <a class="btn btn-primary" href="unpaid_invoices.php"><i class="fas fa-credit-card me-2"></i>Pay invoices</a>
        <?php } ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="itflow-portal-stat">
            <span class="itflow-portal-stat-icon"><i class="fas fa-file-invoice-dollar"></i></span>
            <span>
                <span class="itflow-portal-stat-value d-block"><?= $money($statement['total']) ?></span>
                <span class="itflow-portal-stat-label">Total due</span>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="itflow-portal-stat<?= $statement['overdue'] > 0 ? ' itflow-portal-stat-danger' : '' ?>">
            <span class="itflow-portal-stat-icon"><i class="fas fa-exclamation-circle"></i></span>
            <span>
                <span class="itflow-portal-stat-value d-block"><?= $money($statement['overdue']) ?></span>
                <span class="itflow-portal-stat-label">Past due</span>
            </span>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="itflow-portal-aging" aria-label="How late">
            <?php foreach ($statement['aging'] as $bucket => $amount) { ?>
                <div class="<?= $amount > 0 && $bucket !== 'Current' ? 'is-late' : '' ?>">
                    <span><?= escapeHtml($bucket) ?></span>
                    <strong class="itflow-money"><?= $money($amount) ?></strong>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Statement">
    <a class="<?= !$with_paid ? 'active' : '' ?>" href="statement.php"><i class="fas fa-hourglass-half"></i>Open invoices</a>
    <a class="<?= $with_paid ? 'active' : '' ?>" href="statement.php?view=year"><i class="fas fa-calendar-alt"></i>Last 12 months</a>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$statement['invoices']) { ?>
        <?= $with_paid
            ? emptyStateHtml(['title' => 'No invoices in the last 12 months', 'text' => '', 'icon' => 'fa-file-alt', 'filtered' => false])
            : emptyStateHtml(['title' => 'Nothing outstanding', 'text' => 'You are all paid up - thank you.', 'icon' => 'fa-check', 'filtered' => false]) ?>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 itflow-portal-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th class="d-none d-md-table-cell">Date</th>
                        <th>Due</th>
                        <th class="text-end d-none d-md-table-cell">Amount</th>
                        <th class="text-end d-none d-md-table-cell">Paid</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statement['invoices'] as $row) {
                        $late = $row['balance'] > 0 && $row['days'] > 0; ?>
                        <tr>
                            <td>
                                <a class="fw-semibold" href="invoice.php?id=<?= intval($row['invoice_id']) ?>"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></a>
                                <?php if (trim((string) $row['invoice_scope']) !== '') { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($row['invoice_scope']) ?></div><?php } ?>
                            </td>
                            <td class="text-nowrap d-none d-md-table-cell"><?= escapeHtml(date('M j, Y', strtotime($row['invoice_date']))) ?></td>
                            <td class="text-nowrap<?= $late ? ' text-danger-emphasis' : '' ?>">
                                <?= escapeHtml(date('M j, Y', strtotime($row['invoice_due']))) ?>
                                <?php if ($late) { ?><div class="small"><?= intval($row['days']) ?> <?= intval($row['days']) === 1 ? 'day' : 'days' ?> late</div><?php } ?>
                            </td>
                            <td class="text-end itflow-money d-none d-md-table-cell"><?= $money($row['invoice_amount']) ?></td>
                            <td class="text-end itflow-money d-none d-md-table-cell"><?= $money($row['paid']) ?></td>
                            <td class="text-end itflow-money fw-semibold"><?= $row['balance'] > 0 ? $money($row['balance']) : '<span class="text-success-emphasis">Paid</span>' ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="d-none d-md-table-cell" colspan="3"></td>
                        <th colspan="2" class="text-end">Total due</th>
                        <th class="text-end itflow-money"><?= $money($statement['total']) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
