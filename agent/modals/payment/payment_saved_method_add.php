<?php

/*
 * Charge a card the client saved - the invoice's balance, through the
 * provider the card is with. ?id= (the invoice)
 */

require_once '../../../includes/modal_header.php';

enforceBillingMoney('take');

$invoice_id = intval($_GET['id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);
if (!$invoice) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();

$state = invoiceState($invoice, invoicePaid($invoice_id));
$cards = [];
$sql = mysqli_query($mysqli, "SELECT saved_payment_id, saved_payment_description, payment_provider_name FROM client_saved_payment_methods
    JOIN payment_providers ON payment_provider_id = saved_payment_provider_id
    WHERE saved_payment_client_id = $client_id AND payment_provider_active = 1 ORDER BY saved_payment_description ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $cards[] = $row;
}
if (!$cards) {
    exit(json_encode(['error' => $invoice['client_name'] . ' has no card on file with a payment provider that is turned on.']));
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-wallet me-2"></i>Charge Saved Card <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?> &middot; <?= escapeHtml($invoice['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body">
        <div class="text-center p-3 mb-3 rounded bg-body-tertiary">
            <div class="itflow-billing-stat-label">Charging</div>
            <div class="itflow-billing-stat-value"><?= escapeHtml(billingMoney($invoice['invoice_amount'], $invoice['invoice_currency_code'])) ?></div>
        </div>
        <label class="form-label">Card <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
            <select class="form-select" name="saved_payment_id" required>
                <?php foreach ($cards as $card) { ?>
                    <option value="<?= intval($card['saved_payment_id']) ?>"><?= escapeHtml($card['saved_payment_description']) ?> (<?= escapeHtml($card['payment_provider_name']) ?>)</option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">Charged straight away - the payment is recorded on the invoice and a receipt goes to the client.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_payment_stripe" class="btn btn-success text-bold confirm-link" data-confirm-title="Charge the card?"
            data-confirm-text="<?= escapeHtml(billingMoney($invoice['invoice_amount'], $invoice['invoice_currency_code'])) ?> is taken from the card now." data-confirm-button="Charge">
            <i class="fas fa-bolt me-2"></i>Charge
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
