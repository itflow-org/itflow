<?php

/*
 * Batch payment - one payment from a client spread over their unpaid
 * invoices, oldest first, each paid in full until it runs out. Shows the
 * invoices it will go to. ?client_id=
 */

require_once '../../../includes/modal_header.php';

enforceBillingMoney('take');

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_currency_code FROM clients WHERE client_id = $client_id"));
if (!$client) {
    exit(json_encode(['error' => 'That client no longer exists.']));
}

// What the batch pays, in the order the batch pays it
$open = [];
$owed = 0;
$sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_due, invoice_amount, invoice_currency_code,
        invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS balance
    FROM invoices WHERE invoice_client_id = $client_id AND invoice_status IN ('Sent', 'Viewed', 'Partial')
    ORDER BY invoice_number ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    if (floatval($row['balance']) > 0) {
        $open[] = $row;
        $owed += floatval($row['balance']);
    }
}
$owed = round($owed, 2);
if (!$open) {
    exit(json_encode(['error' => $client['client_name'] . ' has no unpaid invoices.']));
}
$currency = (string) ($open[0]['invoice_currency_code'] ?: $client['client_currency_code']);
$contact_email = (string) mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 AND contact_archived_at IS NULL LIMIT 1"))[0];

$pay = [
    'id' => 'payBatch' . $client_id,
    'amount' => $owed,
    'owed' => $owed,
    'currency' => $currency,
    'email' => $contact_email,
    'full_text' => count($open) === 1 ? 'Pays it in full.' : 'Pays all ' . count($open) . ' in full.',
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-layer-group me-2"></i>Batch Payment <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($client['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="balance" value="<?= number_format($owed, 2, '.', '') ?>">
    <input type="hidden" name="currency_code" value="<?= escapeHtml($currency) ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">One payment spread over <?= escapeHtml($client['client_name']) ?>'s unpaid invoices, oldest first - each is paid in full until the payment runs out, and the last one it reaches may be part paid.</p>
        <div class="list-group list-group-flush border rounded mb-3" style="max-height: 12rem; overflow-y: auto;">
            <?php foreach ($open as $row) {
                $row_state = invoiceState(['invoice_status' => 'Sent', 'invoice_amount' => $row['invoice_amount'], 'invoice_due' => $row['invoice_due']], floatval($row['invoice_amount']) - floatval($row['balance'])); ?>
                <div class="list-group-item d-flex align-items-center gap-2 py-2">
                    <span class="fw-semibold"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></span>
                    <span class="small text-body-secondary text-truncate flex-grow-1"><?= escapeHtml($row['invoice_scope']) ?></span>
                    <?= invoiceDuePill($row_state) ?>
                    <span class="itflow-money small fw-semibold"><?= escapeHtml(billingMoney($row['balance'], $row['invoice_currency_code'])) ?></span>
                </div>
            <?php } ?>
        </div>
        <?php require __DIR__ . '/payment_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_bulk_payment" class="btn btn-success text-bold"><i class="fas fa-check me-2"></i>Add Payment</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
