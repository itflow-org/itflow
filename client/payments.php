<?php
/*
 * Client Portal
 * Payments - every payment made on the account, newest first, 20 a page:
 * card, check, transfer, autopay. One card payment over several invoices is
 * one payment here. Each opens its receipt.
 *
 * SCOPING: billingClientPayments() keys it to $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$list = billingClientPayments($session_client_id, $per_page, ($page - 1) * $per_page);
$total = $list['total'];
$pages = max(1, (int) ceil($total / $per_page));
if ($page > $pages) {
    $page = $pages;
    $list = billingClientPayments($session_client_id, $per_page, ($page - 1) * $per_page);
}
$offset = ($page - 1) * $per_page;
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Payments</h1>
        <p>What you have paid us, with a receipt for each</p>
    </div>
</div>

<section class="itflow-portal-panel">
    <?php if (!$list['rows']) { ?>
        <?= emptyStateHtml(['title' => 'No payments yet', 'text' => 'Once you pay an invoice, the payment and its receipt show here.', 'icon' => 'fa-receipt', 'filtered' => false]) ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php foreach ($list['rows'] as $row) {
                $method = billingPaymentMethodText($row['payment_method'], $row['payment_reference']);
                $count = intval($row['invoices']); ?>
                <li>
                    <a class="itflow-portal-ticket" href="receipt.php?id=<?= intval($row['payment_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml(date('M j, Y', strtotime($row['payment_date']))) ?><span class="fw-normal text-body-secondary"> · <?= escapeHtml($method) ?></span></span>
                            <span class="itflow-portal-ticket-meta">
                                <span class="text-truncate"><?= $count === 1 ? 'Invoice ' : $count . ' invoices: ' ?><?= escapeHtml($row['numbers']) ?></span>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <span class="itflow-portal-amount itflow-money"><?= escapeHtml(billingMoney($row['amount'], $row['payment_currency_code'])) ?></span>
                            <small>Receipt <i class="fas fa-chevron-right ms-1"></i></small>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="payments.php<?= $page - 1 > 1 ? '?page=' . ($page - 1) : '' ?>" aria-label="Newer"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="payments.php?page=<?= $page + 1 ?>" aria-label="Older"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
