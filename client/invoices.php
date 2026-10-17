<?php
/*
 * Client Portal
 * Invoices - the ones to pay, the paid ones, or all of them, searchable, 20
 * a page, each opening here in the portal. What is owed, and what of it is
 * late, under the title.
 *
 * SCOPING: every query is keyed to $session_client_id from the session, and
 * drafts are never shown.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$per_page = 20;
$paid_sql = "COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0)";
$open_sql = "invoice_status IN ('Sent', 'Viewed', 'Partial') AND ROUND(invoice_amount - $paid_sql, 2) > 0";
$tabs = [
    'open' => ['label' => 'To pay', 'icon' => 'fa-hourglass-half', 'where' => $open_sql],
    'paid' => ['label' => 'Paid', 'icon' => 'fa-check', 'where' => "NOT ($open_sql) AND invoice_status NOT IN ('Cancelled', 'Non-Billable')"],
    'all' => ['label' => 'All', 'icon' => 'fa-list', 'where' => '1 = 1'],
];
$q = trim((string) ($_GET['q'] ?? ''));
$where = "invoice_client_id = $session_client_id AND invoice_status != 'Draft'";
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND (CONCAT(invoice_prefix, invoice_number) LIKE '%$q_sql%' OR invoice_scope LIKE '%$q_sql%')";
}

$count_sql = [];
foreach ($tabs as $key => $t) {
    $count_sql[] = "COALESCE(SUM({$t['where']}), 0) AS `$key`";
}
$counts = array_map('intval', mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM invoices WHERE $where")));

// The To pay tab when there is something to pay, else all of them
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : ($counts['open'] ? 'open' : 'all');
$total = $counts[$tab];
$pages = max(1, (int) ceil($total / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$invoices = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_due, invoice_status, invoice_amount,
        invoice_currency_code, $paid_sql AS paid
    FROM invoices WHERE $where AND {$tabs[$tab]['where']}
    ORDER BY " . ($tab === 'open' ? 'invoice_due ASC, invoice_number ASC' : 'invoice_date DESC, invoice_number DESC') . "
    LIMIT $per_page OFFSET $offset");

// What is owed, over every open invoice whatever the search
$owed = 0.0;
$late = 0.0;
$currency = '';
foreach (billingClientOpenInvoices($session_client_id) as $open) {
    $left = round(floatval($open['invoice_amount']) - floatval($open['paid']), 2);
    $owed += $left;
    $currency = $currency ?: (string) $open['invoice_currency_code'];
    if ($open['invoice_due'] < date('Y-m-d')) {
        $late += $left;
    }
}
$cards = billingStripeProvider() !== null;

/** This page's address with some of its settings changed. */
function invoicesUrl(array $changes) {
    global $tab, $q;
    $params = array_merge(['tab' => $tab, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'invoices.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Invoices</h1>
        <p>
            <?php if ($owed > 0) { ?>
                You owe <strong class="text-body"><?= escapeHtml(billingMoney($owed, $currency)) ?></strong><?php if ($late > 0) { ?> - <span class="text-danger-emphasis"><?= escapeHtml(billingMoney($late, $currency)) ?> of it past due</span><?php } ?>
            <?php } else { ?>
                Nothing is owed - thank you
            <?php } ?>
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" class="d-flex" role="search">
            <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Search number or scope" aria-label="Search invoices" maxlength="200">
            </div>
        </form>
        <?php if ($owed > 0 && $cards) { ?>
            <a class="btn btn-primary" href="unpaid_invoices.php"><i class="fas fa-credit-card me-2"></i>Pay invoices</a>
        <?php } ?>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Invoices">
    <?php foreach ($tabs as $key => $t) { ?>
        <a class="<?= $key === $tab ? 'active' : '' ?><?= $key === 'open' && $counts['open'] && $late > 0 ? ' itflow-portal-tab-alert' : '' ?>" href="<?= escapeHtml(invoicesUrl(['tab' => $key, 'page' => null])) ?>">
            <i class="fas <?= $t['icon'] ?>"></i><?= escapeHtml($t['label']) ?>
            <span class="badge rounded-pill"><?= $counts[$key] ?></span>
        </a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'invoices', 'filtered' => true, 'match_text' => 'Nothing here has &ldquo;' . escapeHtml($q) . '&rdquo; in its number or scope.', 'clear_url' => invoicesUrl(['q' => null, 'page' => null])]) ?>
        <?php } elseif ($tab === 'open') { ?>
            <?= emptyStateHtml(['title' => 'Nothing to pay', 'text' => 'You are all paid up - thank you.', 'icon' => 'fa-check', 'filtered' => false]) ?>
        <?php } elseif ($tab === 'paid') { ?>
            <?= emptyStateHtml(['title' => 'No paid invoices yet', 'text' => 'Invoices show here once they are paid.', 'icon' => 'fa-check', 'filtered' => false]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => 'No invoices yet', 'text' => 'When we bill you, the invoice shows here.', 'icon' => 'fa-file-invoice-dollar', 'filtered' => false]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($invoices)) {
                $state = portalInvoiceState($row, $row['paid']);
                $open = in_array($state['state'], ['open', 'overdue'], true); ?>
                <li>
                    <a class="itflow-portal-ticket" href="invoice.php?id=<?= intval($row['invoice_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?><?php if (trim((string) $row['invoice_scope']) !== '') { ?><span class="fw-normal text-body-secondary"> · <?= escapeHtml($row['invoice_scope']) ?></span><?php } ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <span>Dated <?= escapeHtml(date('M j, Y', strtotime($row['invoice_date']))) ?></span>
                                <?php if ($open) { ?><span>Due <?= escapeHtml(date('M j, Y', strtotime($row['invoice_due']))) ?></span><?php } ?>
                                <?php if ($open && $state['paid'] > 0) { ?><span><?= escapeHtml(billingMoney($state['paid'], $row['invoice_currency_code'])) ?> of <?= escapeHtml(billingMoney($row['invoice_amount'], $row['invoice_currency_code'])) ?> paid</span><?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <span class="itflow-portal-amount itflow-money"><?= escapeHtml(billingMoney($open ? $state['balance'] : $row['invoice_amount'], $row['invoice_currency_code'])) ?></span>
                            <?= portalStateBadge($state) ?>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(invoicesUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(invoicesUrl(['page' => $page + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
