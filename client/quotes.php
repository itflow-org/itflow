<?php
/*
 * Client Portal
 * Quotes - the ones waiting for an answer, the accepted ones, or all of
 * them, searchable, 20 a page, each opening here to be accepted or declined.
 *
 * SCOPING: every query is keyed to $session_client_id from the session, and
 * drafts are never shown.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$per_page = 20;
$tabs = [
    'waiting' => ['label' => 'Waiting for you', 'icon' => 'fa-reply', 'where' => portalQuoteWaitingSql()],
    'accepted' => ['label' => 'Accepted', 'icon' => 'fa-thumbs-up', 'where' => "quote_status IN ('Accepted', 'Invoiced')"],
    'all' => ['label' => 'All', 'icon' => 'fa-list', 'where' => '1 = 1'],
];
$q = trim((string) ($_GET['q'] ?? ''));
$where = "quote_client_id = $session_client_id AND quote_status != 'Draft'";
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND (CONCAT(quote_prefix, quote_number) LIKE '%$q_sql%' OR quote_scope LIKE '%$q_sql%')";
}

$count_sql = [];
foreach ($tabs as $key => $t) {
    $count_sql[] = "COALESCE(SUM({$t['where']}), 0) AS `$key`";
}
$counts = array_map('intval', mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM quotes WHERE $where")));

// The waiting tab when something is waiting, else all of them
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : ($counts['waiting'] ? 'waiting' : 'all');
$total = $counts[$tab];
$pages = max(1, (int) ceil($total / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$quotes = mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number, quote_scope, quote_date, quote_expire, quote_status, quote_amount, quote_monthly_amount, quote_currency_code
    FROM quotes WHERE $where AND {$tabs[$tab]['where']}
    ORDER BY " . ($tab === 'waiting' ? 'quote_expire ASC, quote_number ASC' : 'quote_date DESC, quote_number DESC') . "
    LIMIT $per_page OFFSET $offset");

/** This page's address with some of its settings changed. */
function quotesUrl(array $changes) {
    global $tab, $q;
    $params = array_merge(['tab' => $tab, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'quotes.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Quotes</h1>
        <p><?= $counts['waiting'] ? $counts['waiting'] . ' waiting for your answer' : 'What we have quoted you' ?></p>
    </div>
    <form method="get" class="d-flex" role="search">
        <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
            <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Search number or scope" aria-label="Search quotes" maxlength="200">
        </div>
    </form>
</div>

<nav class="itflow-portal-tabs" aria-label="Quotes">
    <?php foreach ($tabs as $key => $t) { ?>
        <a class="<?= $key === $tab ? 'active' : '' ?><?= $key === 'waiting' && $counts['waiting'] ? ' itflow-portal-tab-alert' : '' ?>" href="<?= escapeHtml(quotesUrl(['tab' => $key, 'page' => null])) ?>">
            <i class="fas <?= $t['icon'] ?>"></i><?= escapeHtml($t['label']) ?>
            <span class="badge rounded-pill"><?= $counts[$key] ?></span>
        </a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'quotes', 'filtered' => true, 'match_text' => 'Nothing here has &ldquo;' . escapeHtml($q) . '&rdquo; in its number or scope.', 'clear_url' => quotesUrl(['q' => null, 'page' => null])]) ?>
        <?php } elseif ($tab === 'waiting') { ?>
            <?= emptyStateHtml(['title' => 'Nothing waiting for you', 'text' => 'When we send you a quote, it waits here for your answer.', 'icon' => 'fa-check', 'filtered' => false]) ?>
        <?php } elseif ($tab === 'accepted') { ?>
            <?= emptyStateHtml(['title' => 'No accepted quotes', 'text' => '', 'icon' => 'fa-thumbs-up', 'filtered' => false]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => 'No quotes yet', 'text' => 'Need something priced up? Raise a ticket and we will put a quote together.', 'icon' => 'fa-comment-dollar', 'filtered' => false,
                'actions' => [['label' => 'Ask for a quote', 'href' => portalAskUrl('Quote request'), 'icon' => 'fa-plus']]]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($quotes)) {
                $state = portalQuoteState($row); ?>
                <li>
                    <a class="itflow-portal-ticket" href="quote.php?id=<?= intval($row['quote_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['quote_prefix'] . $row['quote_number']) ?><?php if (trim((string) $row['quote_scope']) !== '') { ?><span class="fw-normal text-body-secondary"> · <?= escapeHtml($row['quote_scope']) ?></span><?php } ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <span>Dated <?= escapeHtml(date('M j, Y', strtotime($row['quote_date']))) ?></span>
                                <?php if ($state['key'] === 'waiting') { ?>
                                    <span>Good until <?= escapeHtml(date('M j, Y', strtotime($row['quote_expire']))) ?></span>
                                <?php } elseif ($state['key'] === 'expired') { ?>
                                    <span>Ran out <?= escapeHtml(date('M j, Y', strtotime($row['quote_expire']))) ?></span>
                                <?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <span class="itflow-portal-amount itflow-money"><?= escapeHtml(billingMoney($row['quote_amount'], $row['quote_currency_code'])) ?></span>
                            <?php if (floatval($row['quote_monthly_amount']) != 0) { ?>
                                <small>+ <?= escapeHtml(billingMoney($row['quote_monthly_amount'], $row['quote_currency_code'])) ?> a month</small>
                            <?php } ?>
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
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(quotesUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(quotesUrl(['page' => $page + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
