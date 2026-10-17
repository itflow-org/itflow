<?php
/*
 * Client Portal
 * Recurring invoices - what is billed each month, quarter or year, when the
 * next one comes, and whether autopay pays it. Each opens to show what it
 * covers.
 *
 * SCOPING: keyed to $session_client_id; only the active ones are shown.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$recurring = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_amount,
        recurring_invoice_currency_code, recurring_invoice_frequency, recurring_invoice_next_date, recurring_payment_saved_payment_id
    FROM recurring_invoices
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id
    WHERE recurring_invoice_client_id = $session_client_id AND recurring_invoice_status = 1
    ORDER BY recurring_invoice_next_date ASC, recurring_invoice_id ASC");

$cards_on = billingStripeProvider() !== null;
$cards = [];
foreach ($cards_on ? billingSavedCards($session_client_id) : [] as $card) {
    $cards[intval($card['saved_payment_id'])] = $card['card'];
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Recurring invoices</h1>
        <p>What we bill you regularly</p>
    </div>
    <?php if ($cards_on && mysqli_num_rows($recurring)) { ?>
        <a class="btn btn-outline-secondary" href="saved_payment_methods.php"><i class="fas fa-sync-alt me-2"></i>Autopay</a>
    <?php } ?>
</div>

<section class="itflow-portal-panel">
    <?php if (!mysqli_num_rows($recurring)) { ?>
        <?= emptyStateHtml(['title' => 'No recurring invoices', 'text' => 'Services billed each month, quarter or year show here.', 'icon' => 'fa-redo-alt', 'filtered' => false]) ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($recurring)) {
                $card = $cards[intval($row['recurring_payment_saved_payment_id'])] ?? null; ?>
                <li>
                    <a class="itflow-portal-ticket" href="recurring_invoice.php?id=<?= intval($row['recurring_invoice_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml(trim((string) $row['recurring_invoice_scope']) !== '' ? $row['recurring_invoice_scope'] : $row['recurring_invoice_prefix'] . $row['recurring_invoice_number']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <span><?= escapeHtml(recurringInvoiceFrequencies()[$row['recurring_invoice_frequency']] ?? ucfirst((string) $row['recurring_invoice_frequency'])) ?></span>
                                <span>Next invoice <?= escapeHtml(date('M j, Y', strtotime($row['recurring_invoice_next_date']))) ?></span>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <span class="itflow-portal-amount itflow-money"><?= escapeHtml(billingMoney($row['recurring_invoice_amount'], $row['recurring_invoice_currency_code'])) ?> <small class="fw-normal">/ <?= escapeHtml(recurringInvoicePeriodWord($row['recurring_invoice_frequency'])) ?></small></span>
                            <?php if ($cards_on) { ?>
                                <?= portalStateBadge($card
                                    ? ['label' => 'Autopay · ' . $card['label'], 'icon' => 'fa-sync-alt', 'class' => 'itflow-portal-state-resolved']
                                    : ['label' => 'Autopay off', 'icon' => 'fa-hand-paper', 'class' => 'itflow-portal-state-closed']) ?>
                            <?php } ?>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
