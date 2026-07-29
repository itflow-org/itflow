<?php

require_once '../../../includes/modal_header.php';

$payment_id = intval($_GET['id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM payments
    LEFT JOIN invoices ON payment_invoice_id = invoice_id
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
    WHERE payment_id = $payment_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);

if (!$row) {
    exit("Payment not found");
}

$payment_date = escapeHtml($row['payment_date']);
$payment_amount = floatval($row['payment_amount']);
$payment_currency_code = escapeHtml($row['payment_currency_code']);
$payment_method = escapeHtml($row['payment_method']);
$payment_reference = escapeHtml($row['payment_reference']);
$payment_account_id = intval($row['payment_account_id']);
$payment_refund_of_id = $row['payment_refund_of_id'];
$invoice_id = intval($row['invoice_id']);
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$client_id = intval($row['client_id']);
$contact_name = escapeHtml($row['contact_name']);
$contact_email = escapeHtml($row['contact_email']);

enforceClientAccess();

// A refund row cannot itself be refunded
if (!is_null($payment_refund_of_id) || $payment_amount <= 0) {
    exit("This entry is a refund and cannot be refunded");
}

$refunded_amount = getPaymentRefundedTotal($payment_id);
$refundable_amount = round($payment_amount - $refunded_amount, 2);

// Stripe payments carry the PaymentIntent in their reference - that is what makes
// them refundable through the gateway rather than only on paper
$stripe_pi_id = getStripePaymentIntentId($row['payment_reference']);
$stripe_available = false;

if ($stripe_pi_id) {
    $stripe_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM payment_providers WHERE payment_provider_name = 'Stripe' LIMIT 1"));
    if ($stripe_provider && !empty($stripe_provider['payment_provider_private_key'])) {
        $stripe_available = true;
    }
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-undo mr-2"></i><?= "$invoice_prefix$invoice_number" ?>: Refund Payment</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
    <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">
    <div class="modal-body">

        <?php if ($refundable_amount <= 0) { ?>

            <div class="alert alert-warning mb-0">
                <i class="fas fa-fw fa-exclamation-triangle mr-2"></i>This payment has already been refunded in full.
            </div>

        <?php } else { ?>

            <div class="callout callout-info">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Original Payment</small>
                        <span class="text-bold"><?= numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code) ?></span>
                        on <?= $payment_date ?>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Available to Refund</small>
                        <span class="text-bold"><?= numfmt_format_currency($currency_format, $refundable_amount, $payment_currency_code) ?></span>
                        <?php if ($refunded_amount > 0) { ?>
                            <small class="text-muted">(<?= numfmt_format_currency($currency_format, $refunded_amount, $payment_currency_code) ?> already refunded)</small>
                        <?php } ?>
                    </div>
                </div>
                <?php if ($payment_method || $payment_reference) { ?>
                    <div class="mt-2">
                        <small class="text-muted"><?= $payment_method ?><?php if ($payment_reference) { ?> &middot; <?= $payment_reference ?><?php } ?></small>
                    </div>
                <?php } ?>
            </div>

            <div class="form-row">
                <div class="col-md">

                    <div class="form-group">
                        <label>Refund Date <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date("Y-m-d") ?>" required>
                        </div>
                    </div>

                </div>

                <div class="col-md">

                    <div class="form-group">
                        <label>Refund Amount <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" value="<?= number_format($refundable_amount, 2, '.', '') ?>" placeholder="0.00" required>
                        </div>
                        <small class="form-text text-muted">Lower this to issue a partial refund.</small>
                    </div>

                </div>

            </div>

            <div class="form-group">
                <label>Refund From Account <strong class="text-danger">*</strong></label>
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
                            $account_currency = escapeHtml($row['account_currency_code']);
                        ?>
                            <option <?php if ($payment_account_id == $account_id) { echo "selected"; } ?>
                                value="<?= $account_id ?>">
                                <?= $account_name ?>
                            </option>

                        <?php
                        }
                        ?>
                    </select>
                </div>
                <small class="form-text text-muted">Defaults to the account the payment was deposited into.</small>
            </div>

            <div class="form-group">
                <label>Reason / Reference</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                    </div>
                    <input type="text" class="form-control" name="reference" placeholder="Overpayment, service credit, etc" maxlength="150">
                </div>
            </div>

            <?php if ($stripe_available) { ?>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="refundViaStripe" name="refund_stripe" value="1" checked>
                        <label class="custom-control-label" for="refundViaStripe">Refund this amount through Stripe</label>
                    </div>
                    <small class="form-text text-muted">
                        Sends the money back to the card used for <?= escapeHtml($stripe_pi_id) ?>. Leave unchecked to record the refund in ITFlow only.
                        Stripe does not return the processing fee on a refund, so the gateway fee expense is left in place.
                    </small>
                </div>

            <?php } elseif ($stripe_pi_id) { ?>

                <div class="alert alert-warning">
                    <i class="fas fa-fw fa-exclamation-triangle mr-2"></i>This was a Stripe payment, but no Stripe secret key is configured. The refund will be recorded in ITFlow only and must be issued manually in the Stripe dashboard.
                </div>

            <?php } ?>

            <?php if (!empty($config_smtp_provider) && !empty($contact_email)) { ?>

                <div class="form-group">
                    <label>Email Notification</label>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="refundEmailReceipt" name="email_receipt" value="1">
                        <label class="custom-control-label" for="refundEmailReceipt"><?= $contact_email ?></label>
                    </div>
                </div>

            <?php } ?>

        <?php } ?>

    </div>

    <div class="modal-footer">
        <?php if ($refundable_amount > 0) { ?>
            <button type="submit" name="refund_payment" class="btn btn-danger text-bold"><i class="fas fa-undo mr-2"></i>Issue Refund</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
