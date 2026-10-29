<?php
/*
 * Client Portal
 * A payment's receipt - what was paid, when and how, and what it went on:
 * every invoice one card payment covered, with what each still owes. Made
 * to print.
 *
 * SCOPING: billingPaymentReceipt() only finds a payment on one of the
 * session's client's invoices.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$receipt = billingPaymentReceipt(intval($_GET['id'] ?? 0), $session_client_id);
if (!$receipt) { ?>
    <a class="itflow-portal-back" href="payments.php"><i class="fas fa-chevron-left"></i>Payments</a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => "That payment isn't here", 'text' => 'It may have been removed, or the link is not quite right.', 'icon' => 'fa-receipt', 'filtered' => false,
            'actions' => [['label' => 'All payments', 'href' => 'payments.php', 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$company = billingCompany();
$method = billingPaymentMethodText($receipt['method'], $receipt['reference']);
$reference = preg_replace('/^Stripe - /', '', $receipt['reference']);
$currency = $receipt['currency'];
?>

<a class="itflow-portal-back d-print-none" href="payments.php"><i class="fas fa-chevron-left"></i>Payments</a>

<div class="itflow-portal-page-head d-print-none">
    <div>
        <h1>Receipt</h1>
        <p><?= escapeHtml(date('M j, Y', strtotime($receipt['date']))) ?> · <?= escapeHtml($method) ?></p>
    </div>
    <button type="button" class="btn btn-outline-secondary" data-portal-print><i class="fas fa-print me-2"></i>Print</button>
</div>

<section class="itflow-portal-panel itflow-portal-receipt">
    <div class="itflow-portal-receipt-head">
        <div>
            <?php if ($company['logo_url']) { ?>
                <img class="itflow-paper-logo" src="<?= escapeHtml($company['logo_url']) ?>" alt="<?= escapeHtml($company['company_name'] ?? '') ?>">
            <?php } else { ?>
                <div class="fw-semibold fs-5"><?= escapeHtml($company['company_name'] ?? '') ?></div>
            <?php } ?>
        </div>
        <div class="text-md-end">
            <div class="itflow-billing-stat-label">Payment received</div>
            <div class="itflow-portal-big-amount itflow-money"><?= escapeHtml(billingMoney($receipt['total'], $currency)) ?></div>
        </div>
    </div>

    <div class="itflow-portal-panel-body">
        <dl class="itflow-portal-facts mb-4">
            <div><dt>Received from</dt><dd><?= escapeHtml($session_client_name) ?></dd></div>
            <div><dt>Date</dt><dd><?= escapeHtml(date('F j, Y', strtotime($receipt['date']))) ?></dd></div>
            <div><dt>Paid by</dt><dd><?= escapeHtml($method) ?></dd></div>
            <?php if ($reference !== '') { ?>
                <div><dt>Reference</dt><dd class="font-monospace small"><?= escapeHtml($reference) ?></dd></div>
            <?php } ?>
        </dl>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Still owed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipt['lines'] as $line) {
                        $state = invoiceState($line, $line['paid']); ?>
                        <tr>
                            <td>
                                <a class="fw-semibold" href="invoice.php?id=<?= intval($line['invoice_id']) ?>"><?= escapeHtml($line['invoice_prefix'] . $line['invoice_number']) ?></a>
                                <?php if (trim((string) $line['invoice_scope']) !== '') { ?><div class="small text-body-secondary"><?= escapeHtml($line['invoice_scope']) ?></div><?php } ?>
                            </td>
                            <td class="text-end itflow-money"><?= escapeHtml(billingMoney($line['payment_amount'], $currency)) ?></td>
                            <td class="text-end itflow-money"><?= $state['balance'] > 0 ? escapeHtml(billingMoney($state['balance'], $line['invoice_currency_code'])) : '<span class="text-success-emphasis">Paid</span>' ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <?php if (count($receipt['lines']) > 1) { ?>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-end itflow-money"><?= escapeHtml(billingMoney($receipt['total'], $currency)) ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                <?php } ?>
            </table>
        </div>
    </div>

    <div class="itflow-portal-receipt-foot">
        Thank you for your payment.
        <?= escapeHtml(implode(' · ', array_filter([$company['company_name'] ?? '', $company['company_email'] ?? '', $company['phone_display'] ?? '']))) ?>
    </div>
</section>

<?php
require_once "includes/footer.php";
