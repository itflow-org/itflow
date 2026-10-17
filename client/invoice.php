<?php
/*
 * Client Portal
 * An invoice - as it prints, with what is owed and by when alongside, Pay,
 * the PDF, the payments made with their receipts, and the recurring
 * invoice it came from. On a phone, what is owed comes first.
 *
 * SCOPING: the invoice must be the session's client's, and not a draft.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$invoice = billingRow('invoice', intval($_GET['id'] ?? 0), false);
if (!$invoice || intval($invoice['client_id']) !== $session_client_id || $invoice['invoice_status'] === 'Draft') { ?>
    <a class="itflow-portal-back" href="invoices.php"><i class="fas fa-chevron-left"></i>Invoices</a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => "That invoice isn't here", 'text' => 'It may have been replaced, or the link is not quite right.', 'icon' => 'fa-file-invoice-dollar', 'filtered' => false,
            'actions' => [['label' => 'All invoices', 'href' => 'invoices.php', 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$invoice_id = intval($invoice['invoice_id']);
$number = billingNumber('invoice', $invoice);
$currency = (string) $invoice['invoice_currency_code'];

portalBillingViewed('invoice', $invoice);

$items = billingItems('invoice', $invoice_id);
$totals = billingTotals('invoice', $invoice, $items);
$payments = invoicePayments($invoice_id);
$state = portalInvoiceState($invoice, invoicePaid($invoice_id));
$company = billingCompany();
$owing = in_array($state['state'], ['open', 'overdue'], true) && invoiceCanPay($invoice, $state);
$cards = billingStripeProvider() !== null;
$can_pay = $owing && $cards && billingOnlinePayAllowed($state['balance']);

// The other invoices still owed, to pay with this one
$others = array_filter(billingClientOpenInvoices($session_client_id), function ($row) use ($invoice_id) {
    return intval($row['invoice_id']) !== $invoice_id;
});

// The recurring invoice it came from - and the card that pays it, when autopay is on
$recurring = intval($invoice['invoice_recurring_invoice_id'] ?? 0) ? billingRow('recurring', $invoice['invoice_recurring_invoice_id'], false) : null;
if ($recurring && intval($recurring['client_id']) !== $session_client_id) {
    $recurring = null;
}
$autopay_card = null;
if ($recurring && $owing && intval($recurring['recurring_payment_saved_payment_id']) && $invoice['invoice_due'] >= date('Y-m-d')) {
    foreach (billingSavedCards($session_client_id) as $card) {
        if (intval($card['saved_payment_id']) === intval($recurring['recurring_payment_saved_payment_id'])) {
            $autopay_card = $card['card'];
        }
    }
}

$paper = [
    'type' => 'invoice',
    'row' => $invoice,
    'items' => $items,
    'totals' => $totals,
    'paid' => $state['paid'],
    'balance' => $state['balance'],
    'logo_url' => $company['logo_url'],
    'client' => true,
];
?>

<a class="itflow-portal-back d-print-none" href="invoices.php"><i class="fas fa-chevron-left"></i>Invoices</a>

<div class="itflow-portal-page-head d-print-none">
    <div>
        <h1>Invoice <?= escapeHtml($number) ?></h1>
        <p><?= trim((string) $invoice['invoice_scope']) !== '' ? escapeHtml($invoice['invoice_scope']) : 'Dated ' . escapeHtml(date('M j, Y', strtotime($invoice['invoice_date']))) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="pdf.php?invoice=<?= $invoice_id ?>"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>
        <button type="button" class="btn btn-outline-secondary" data-portal-print title="Print"><i class="fas fa-print"></i><span class="visually-hidden">Print</span></button>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-4 order-lg-2 d-print-none">

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-body">
                <?php if ($state['state'] === 'paid') { ?>
                    <div class="itflow-portal-outcome">
                        <span class="itflow-portal-outcome-icon is-success" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <div class="fw-semibold">Paid in full</div>
                            <div class="small text-body-secondary"><?= $payments ? 'On ' . escapeHtml(date('M j, Y', strtotime($payments[0]['payment_date']))) . ' - thank you.' : 'Thank you.' ?></div>
                        </div>
                    </div>
                <?php } elseif (!$owing) { ?>
                    <div class="itflow-portal-outcome">
                        <span class="itflow-portal-outcome-icon" aria-hidden="true"><i class="fas fa-ban"></i></span>
                        <div>
                            <div class="fw-semibold"><?= $state['state'] === 'cancelled' ? 'Cancelled' : 'Nothing to pay' ?></div>
                            <div class="small text-body-secondary">Nothing is owed on this invoice.</div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="itflow-billing-stat-label">Amount due</div>
                    <div class="itflow-portal-big-amount itflow-money<?= $state['state'] === 'overdue' ? ' text-danger-emphasis' : '' ?>"><?= escapeHtml(billingMoney($state['balance'], $currency)) ?></div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <?= portalStateBadge($state) ?>
                        <?php if ($state['class'] !== 'itflow-portal-state-new') { // "Due Nov 30" already says when ?>
                            <span class="small text-body-secondary">Due <?= escapeHtml(date('M j, Y', strtotime($invoice['invoice_due']))) ?></span>
                        <?php } ?>
                    </div>
                    <?php if ($state['paid'] > 0) { ?>
                        <p class="small text-body-secondary mb-3"><?= escapeHtml(billingMoney($state['paid'], $currency)) ?> of <?= escapeHtml(billingMoney($invoice['invoice_amount'], $currency)) ?> paid so far.</p>
                    <?php } ?>
                    <?php if ($autopay_card) { ?>
                        <div class="itflow-portal-note mb-3">
                            <i class="<?= escapeHtml($autopay_card['icon']) ?> fa-fw"></i>
                            <span>Autopay will charge <?= escapeHtml($autopay_card['label']) ?> on <?= escapeHtml(date('M j', strtotime($invoice['invoice_due']))) ?> - nothing to do, unless you would rather pay now.</span>
                        </div>
                    <?php } ?>
                    <?php if ($can_pay) { ?>
                        <a class="btn btn-success btn-lg w-100" href="unpaid_invoices.php?select=<?= $invoice_id ?>"><i class="fas fa-credit-card me-2"></i>Pay <?= escapeHtml(billingMoney($state['balance'], $currency)) ?></a>
                        <?php if ($others) { ?>
                            <div class="text-center mt-2"><a class="small" href="unpaid_invoices.php">Or pay it with your <?= count($others) ?> other open <?= count($others) === 1 ? 'invoice' : 'invoices' ?></a></div>
                        <?php } ?>
                    <?php } elseif ($cards) { ?>
                        <p class="small text-body-secondary mb-0">This invoice is too large to pay by card here - please pay it another way, or <a href="<?= escapeHtml(portalAskUrl("Paying invoice $number")) ?>">ask us</a>.</p>
                    <?php } else { ?>
                        <p class="small text-body-secondary mb-0">To pay, use the payment details on the invoice, or <a href="<?= escapeHtml(portalAskUrl("Paying invoice $number")) ?>">ask us</a> how.</p>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <?php if ($payments) { ?>
            <section class="itflow-portal-panel mb-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-receipt"></i>Payments</h2>
                </div>
                <ul class="itflow-portal-list">
                    <?php foreach ($payments as $payment) { ?>
                        <li>
                            <a class="itflow-portal-todo" href="receipt.php?id=<?= intval($payment['payment_id']) ?>">
                                <span class="itflow-portal-todo-main">
                                    <div class="itflow-money"><?= escapeHtml(billingMoney($payment['payment_amount'], $payment['payment_currency_code'])) ?></div>
                                    <div><?= escapeHtml(billingPaymentMethodText($payment['payment_method'], $payment['payment_reference'])) ?> · <?= escapeHtml(date('M j, Y', strtotime($payment['payment_date']))) ?></div>
                                </span>
                                <span class="small text-body-secondary">Receipt<i class="fas fa-chevron-right ms-2"></i></span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($recurring) { ?>
            <section class="itflow-portal-panel mb-4">
                <a class="itflow-portal-todo" href="recurring_invoice.php?id=<?= intval($recurring['recurring_invoice_id']) ?>">
                    <span class="itflow-portal-todo-icon is-info"><i class="fas fa-redo-alt"></i></span>
                    <span class="itflow-portal-todo-main">
                        <div><?= escapeHtml(trim((string) $recurring['recurring_invoice_scope']) !== '' ? $recurring['recurring_invoice_scope'] : billingNumber('recurring', $recurring)) ?></div>
                        <div>Billed <?= escapeHtml(strtolower(recurringInvoiceFrequencies()[$recurring['recurring_invoice_frequency']] ?? $recurring['recurring_invoice_frequency'])) ?> - see what it covers</div>
                    </span>
                    <i class="fas fa-chevron-right text-body-secondary small"></i>
                </a>
            </section>
        <?php } ?>

        <p class="small text-body-secondary px-1">
            Questions about this invoice? <a href="<?= escapeHtml(portalAskUrl("Question about invoice $number")) ?>">Ask us</a><?php if (!empty($company['phone_display'])) { ?> or call <?= escapeHtml($company['phone_display']) ?><?php } ?>.
        </p>

    </div>

    <div class="col-lg-8 order-lg-1 itflow-paper-col">
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/billing_paper.php'; ?>
    </div>

</div>

<?php
require_once "includes/footer.php";
