<?php
/*
 * Client Portal
 * Checkout - paying the invoices picked on Pay invoices with a new card, in
 * Stripe's card form on this page (js/portal_stripe.js). Stripe sends the
 * contact back to post.php?portal_pay_return, which books the payment and
 * shows the receipt.
 *
 * SCOPING: portal_pay keeps the choice in the session, and it is checked
 * again here (billingPaySelection()) before the payment is set up - an
 * invoice paid some other way in between drops the page back to the list.
 */

$portal_stripe = true; // Stripe's script and card form - see includes/header.php

require_once "includes/inc_all.php";

portalBillingGate();

$checkout = $_SESSION['portal_checkout'] ?? null;
$error = '';
$selection = null;
$pi = null;
$provider = billingStripeProvider();

if (!$provider) {
    $error = 'Card payments are not set up';
} elseif (!$checkout || empty($checkout['ids'])) {
    $error = 'Choose the invoices to pay first';
} else {
    $selection = billingPaySelection($session_client_id, (array) $checkout['ids'], $error);
}

if ($selection) {
    try {
        $pi = billingStripeCheckoutIntent($selection, $session_client_id, ['name' => (string) ($contact['contact_name'] ?? '')], (string) ($checkout['pi'] ?? ''));
        $_SESSION['portal_checkout']['pi'] = (string) $pi->id;
    } catch (Throwable $e) {
        logApp("Stripe", "error", "Could not set up a card payment for client $session_client_id: " . $e->getMessage());
        $error = 'The card form could not be set up just now - nothing has been charged. Try again in a moment';
        $selection = null;
    }
}

$back = 'unpaid_invoices.php' . ($checkout && !empty($checkout['ids']) ? '?' . http_build_query(['select' => implode(',', array_map('intval', (array) $checkout['ids']))]) : '');
?>

<a class="itflow-portal-back" href="<?= escapeHtml($back) ?>"><i class="fas fa-chevron-left"></i>Pay invoices</a>

<?php if (!$selection) { ?>

    <div class="itflow-portal-page-head">
        <div>
            <h1>Pay by card</h1>
        </div>
    </div>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => escapeHtml($error), 'text' => 'Nothing has been charged.', 'icon' => 'fa-credit-card', 'filtered' => false,
            'actions' => [['label' => 'Back to Pay invoices', 'href' => $back, 'icon' => 'fa-arrow-left']]]) ?>
    </section>

<?php } else {
    $amount = billingMoney($selection['total'], $selection['currency']);
    $count = count($selection['invoices']); ?>

    <div class="itflow-portal-page-head">
        <div>
            <h1>Pay <?= escapeHtml($amount) ?></h1>
            <p><?= $count ?> <?= $count === 1 ? 'invoice' : 'invoices' ?> · paid securely through Stripe</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="itflow-portal-panel">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-credit-card"></i>Card details</h2>
                    <span class="small text-body-secondary"><i class="fas fa-lock me-1"></i>Secure</span>
                </div>
                <form class="itflow-portal-panel-body" id="portalPayForm" data-stripe-pay
                    data-stripe-key="<?= escapeHtml($provider['payment_provider_public_key']) ?>"
                    data-client-secret="<?= escapeHtml((string) $pi->client_secret) ?>"
                    data-return-url="<?= escapeHtml("https://$config_base_url/client/post.php?portal_pay_return=1") ?>">
                    <div class="itflow-portal-stripe-element" id="payment-element">
                        <div class="text-body-secondary small" data-stripe-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading the card form...</div>
                    </div>
                    <div class="alert alert-warning small mt-3 mb-0" data-stripe-unavailable hidden>
                        The card form did not load - check your connection and reload the page. Nothing has been charged.
                    </div>
                    <div class="alert alert-danger small mt-3 mb-0" data-pay-error hidden></div>
                    <button type="submit" class="btn btn-success btn-lg w-100 mt-3" data-stripe-submit disabled>
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true" data-stripe-busy hidden></span><i class="fas fa-lock me-2" data-stripe-idle></i>Pay <?= escapeHtml($amount) ?>
                    </button>
                    <p class="small text-body-secondary text-center mt-2 mb-0">Your card details go straight to Stripe and never reach us.</p>
                </form>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="itflow-portal-panel">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-file-invoice-dollar"></i>Paying</h2>
                    <a href="<?= escapeHtml($back) ?>">Change</a>
                </div>
                <ul class="itflow-portal-list">
                    <?php foreach ($selection['invoices'] as $id => $row) { ?>
                        <li class="itflow-portal-todo">
                            <span class="itflow-portal-todo-main">
                                <div><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></div>
                                <div><?= trim((string) $row['invoice_scope']) !== '' ? escapeHtml($row['invoice_scope']) : 'Due ' . escapeHtml(date('M j, Y', strtotime($row['invoice_due']))) ?></div>
                            </span>
                            <span class="itflow-money"><?= escapeHtml(billingMoney($selection['cents'][$id] / 100, $selection['currency'])) ?></span>
                        </li>
                    <?php } ?>
                </ul>
                <div class="itflow-portal-paybar d-flex justify-content-between align-items-baseline">
                    <span class="text-body-secondary">Total</span>
                    <span class="itflow-portal-big-amount itflow-money"><?= escapeHtml($amount) ?></span>
                </div>
            </section>
        </div>
    </div>

<?php } ?>

<?php
require_once "includes/footer.php";
