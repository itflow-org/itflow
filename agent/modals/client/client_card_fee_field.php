<?php

/*
 * Letting a client off the card fee - on the Billing tab of client_add.php and
 * client_edit.php, while Stripe takes card payments. They set
 * $client_card_fee_exempt (bool) and $client_card_fee_key (keeps ids apart).
 * Saved by agent/post/client.php as client_card_fee_exempt; the fee itself is
 * set on Stripe in Admin > Payment Providers (functions/billing_pay.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

if (billingStripeProvider()) {
    $ccf_rates = billingCardFeeRates();
    $ccf_key = $client_card_fee_key ?? 'New';
    ?>
    <div class="mb-3">
        <input type="hidden" name="card_fee_field" value="1">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="card_fee_exempt" value="1" id="clientCardFeeExempt<?= escapeHtml($ccf_key) ?>"<?= !empty($client_card_fee_exempt) ? ' checked' : '' ?>>
            <label class="form-check-label" for="clientCardFeeExempt<?= escapeHtml($ccf_key) ?>">No card fee</label>
        </div>
        <small class="text-secondary">
            <?= $ccf_rates
                ? 'Clients pay a ' . escapeHtml(billingCardFeeText($ccf_rates)) . ' card fee when they pay by card - this one doesn\'t.'
                : 'Clients don\'t pay a card fee now - if they are set to in Admin > Payment Providers, this one won\'t.' ?>
        </small>
    </div>
    <?php
}
