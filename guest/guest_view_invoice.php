<?php

/*
 * An invoice as the client opens it from its link - the invoice on the
 * left, as it prints; on the right what is owed and by when, Pay Now when
 * it can be paid online, the PDF, the payments made, and everything else
 * they owe with a link to each. Opening it marks a sent invoice viewed and
 * tells the agents.
 */

require_once "includes/inc_all_guest.php";

$invoice = billingRow('invoice', intval($_GET['invoice_id'] ?? 0), false);
$url_key = (string) ($_GET['url_key'] ?? '');

if (!$invoice || (string) $invoice['invoice_url_key'] === '' || !hash_equals((string) $invoice['invoice_url_key'], $url_key)) {
    echo '<div class="itflow-asset-empty mt-5"><i class="fas fa-file-invoice-dollar fa-3x mb-3 opacity-50"></i><h1 class="h4">This invoice link isn\'t working</h1><p>It may have been mistyped or replaced. Ask whoever sent it for a new link.</p></div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

$invoice_id = intval($invoice['invoice_id']);
$client_id = intval($invoice['client_id']);
$number = billingNumber('invoice', $invoice);
$status = (string) $invoice['invoice_status'];
$currency = (string) $invoice['invoice_currency_code'];
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

billingGuestViewed('invoice', $invoice);

$items = billingItems('invoice', $invoice_id);
$totals = billingTotals('invoice', $invoice, $items);
$payments = invoicePayments($invoice_id);
$state = invoiceState($invoice, invoicePaid($invoice_id));
$company = billingCompany();
$open_invoices = billingClientOpenInvoices($client_id);
$open_total = 0;
foreach ($open_invoices as $open) {
    $open_total += floatval($open['invoice_amount']) - floatval($open['paid']);
}
$query_key = rawurlencode($url_key);

// Paying online - through Stripe, under its limit, when there is something to pay
$stripe = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_id, payment_provider_threshold FROM payment_providers WHERE payment_provider_active = 1 AND payment_provider_name = 'Stripe' LIMIT 1"));
$pay_url = '';
if ($stripe && invoiceCanPay($invoice, $state) && (floatval($stripe['payment_provider_threshold']) == 0 || floatval($stripe['payment_provider_threshold']) > floatval($invoice['invoice_amount']))) {
    $stripe_customer = mysqli_fetch_row(mysqli_query($mysqli, "SELECT payment_provider_client FROM client_payment_provider WHERE client_id = $client_id AND payment_provider_id = " . intval($stripe['payment_provider_id']) . " LIMIT 1"));
    $pay_url = ($stripe_customer ? 'guest_pay_invoice_stripe.php' : 'guest_pay_setup_stripe_customer.php') . "?invoice_id=$invoice_id&url_key=$query_key";
}

$paper = [
    'type' => 'invoice',
    'row' => $invoice,
    'items' => $items,
    'totals' => $totals,
    'paid' => $state['paid'],
    'balance' => $state['balance'],
    'logo_url' => $company['logo_url'],
];

?>

<div class="row g-3 my-1">

    <div class="col-lg-8 itflow-paper-col">
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/billing_paper.php'; ?>
    </div>

    <div class="col-lg-4 d-print-none">

        <!-- What is owed, and paying it -->
        <div class="card mb-3">
            <div class="card-body">
                <?php if ($state['state'] === 'paid') { ?>
                    <div class="d-flex align-items-center gap-3">
                        <span class="itflow-asset-icon bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <div class="fw-semibold">Paid in full</div>
                            <div class="small text-body-secondary"><?= $payments ? 'On ' . escapeHtml($payments[0]['payment_date']) . ' - thank you.' : 'Thank you.' ?></div>
                        </div>
                    </div>
                <?php } elseif (in_array($state['state'], ['cancelled', 'nonbillable'], true)) { ?>
                    <div class="d-flex align-items-center gap-3">
                        <span class="itflow-asset-icon bg-secondary-subtle text-secondary-emphasis" aria-hidden="true"><i class="fas fa-ban"></i></span>
                        <div>
                            <div class="fw-semibold"><?= $state['state'] === 'cancelled' ? 'Cancelled' : 'Nothing to pay' ?></div>
                            <div class="small text-body-secondary">Nothing is owed on this invoice.</div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="itflow-billing-stat-label">Amount due</div>
                    <div class="itflow-billing-stat-value mb-1 <?= $state['state'] === 'overdue' ? 'text-danger-emphasis' : '' ?>"><?= $money($state['balance']) ?></div>
                    <div class="mb-3"><?= invoiceDuePill($state) ?: '<span class="small text-body-secondary">Due ' . escapeHtml($invoice['invoice_due']) . '</span>' ?></div>
                    <?php if ($pay_url) { ?>
                        <a class="btn btn-success btn-lg w-100 mb-2" href="<?= escapeHtml($pay_url) ?>"><i class="fas fa-credit-card me-2"></i>Pay Now</a>
                        <div class="small text-body-secondary text-center mb-3"><i class="fas fa-lock me-1"></i>Securely, by card</div>
                    <?php } ?>
                <?php } ?>
                <div class="d-flex gap-2 <?= $state['state'] === 'paid' || in_array($state['state'], ['cancelled', 'nonbillable'], true) ? 'mt-3' : '' ?>">
                    <a class="btn btn-outline-primary flex-fill" href="guest_post.php?export_invoice_pdf=<?= $invoice_id ?>&url_key=<?= $query_key ?>"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print();" title="Print"><i class="fas fa-print"></i></button>
                </div>
            </div>
        </div>

        <?php if ($payments) { ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-receipt text-secondary me-2"></i>Payments</h3></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($payments as $payment) { ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($payment['payment_amount'], $payment['payment_currency_code'])) ?></div>
                                <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$payment['payment_method'], $payment['payment_reference']]))) ?></div>
                            </div>
                            <span class="small text-body-secondary"><?= escapeHtml($payment['payment_date']) ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if ($open_invoices && !(count($open_invoices) === 1 && intval($open_invoices[0]['invoice_id']) === $invoice_id)) { ?>
            <!-- Everything else they owe -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-file-alt text-secondary me-2"></i>Your account</h3>
                    <span class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($open_total, $currency)) ?></span>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($open_invoices as $open) {
                        $open_id = intval($open['invoice_id']);
                        $open_state = invoiceState($open, $open['paid']); ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= $open_id === $invoice_id ? 'active' : '' ?>"
                            href="guest_view_invoice.php?invoice_id=<?= $open_id ?>&url_key=<?= rawurlencode((string) $open['invoice_url_key']) ?>">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold"><?= escapeHtml($open['invoice_prefix'] . $open['invoice_number']) ?></div>
                                <div class="small text-truncate <?= $open_id === $invoice_id ? '' : 'text-body-secondary' ?>"><?= escapeHtml($open['invoice_scope']) ?: 'Dated ' . escapeHtml($open['invoice_date']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="itflow-money small fw-semibold"><?= escapeHtml(billingMoney($open_state['balance'], $open['invoice_currency_code'])) ?></div>
                                <?php if ($open_id !== $invoice_id) { ?><?= invoiceDuePill($open_state) ?><?php } ?>
                            </div>
                        </a>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if (!empty($company['company_email']) || !empty($company['phone_display'])) { ?>
            <div class="small text-body-secondary px-1">
                Questions about this invoice? Contact <?= escapeHtml($company['company_name']) ?>
                <?php if (!empty($company['company_email'])) { ?> at <a href="mailto:<?= escapeHtml($company['company_email']) ?>?subject=<?= rawurlencode("Invoice $number") ?>"><?= escapeHtml($company['company_email']) ?></a><?php } ?>
                <?php if (!empty($company['phone_display'])) { ?> or <?= escapeHtml($company['phone_display']) ?><?php } ?>.
            </div>
        <?php } ?>

    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
