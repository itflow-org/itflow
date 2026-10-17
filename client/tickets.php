<?php
/*
 * Client Portal
 * Tickets - this contact's, or the whole company's for a primary or technical
 * contact, in tabs by where they stand for the client: open, waiting on them,
 * resolved for them to confirm, closed, all. Searchable, 20 a page.
 *
 * SCOPING: portalTicketScopeSql() keeps every query to the session's client,
 * and to the contact's own tickets unless they may see the company's.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$per_page = 20;
$sees_all = portalSeesAllTickets();
$scope = $sees_all && ($_GET['scope'] ?? '') === 'mine' ? 'mine' : ($sees_all ? 'all' : 'mine');
$tabs = portalTicketTabs();
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'open';
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, intval($_GET['page'] ?? 1));

$where = portalTicketScopeSql($scope === 'mine') . " AND tickets.ticket_archived_at IS NULL";
if ($q !== '') {
    $q_sql = escapeSql($q);
    $q_number = intval(preg_replace('/\D/', '', $q));
    $where .= " AND (tickets.ticket_subject LIKE '%$q_sql%' OR CONCAT(tickets.ticket_prefix, tickets.ticket_number) LIKE '%$q_sql%'" . ($q_number ? " OR tickets.ticket_number = $q_number" : '') . ")";
}
$from = "FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN contacts ON contact_id = tickets.ticket_contact_id
    LEFT JOIN categories ON category_id = tickets.ticket_category
    WHERE $where";

// One pass for every tab's count
$count_sql = [];
foreach ($tabs as $key => $t) {
    $count_sql[] = "COALESCE(SUM({$t['where']}), 0) AS `$key`";
}
$counts = array_map('intval', mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " $from")));

$total = $counts[$tab];
$pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;

$tickets = mysqli_query($mysqli, "SELECT " . portalTicketColumnsSql() . ", contact_name, category_name $from AND {$tabs[$tab]['where']}
    ORDER BY " . ($tab === 'closed' ? 'tickets.ticket_closed_at' : 'COALESCE(tickets.ticket_updated_at, tickets.ticket_created_at)') . " DESC, tickets.ticket_id DESC
    LIMIT $per_page OFFSET $offset");

/** This page's address with some of its settings changed. */
function ticketsUrl(array $changes) {
    global $tab, $scope, $q, $sees_all;
    $params = array_merge(['tab' => $tab, 'scope' => $sees_all && $scope === 'mine' ? 'mine' : null, 'q' => $q !== '' ? $q : null], $changes);
    if (($params['tab'] ?? '') === 'open') {
        unset($params['tab']);
    }
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'tickets.php' . ($params ? '?' . http_build_query($params) : '');
}

$empty_titles = [
    'open' => ['No open tickets', 'Nothing is being worked on right now.', 'fa-check'],
    'you' => ['Nothing waiting on you', 'When we need something from you, the ticket shows here.', 'fa-check'],
    'resolved' => ['Nothing to confirm', 'Tickets we have resolved wait here for you to close or reopen.', 'fa-check'],
    'closed' => ['No closed tickets', '', 'fa-check-circle'],
    'all' => ['No tickets yet', 'Something not working? Raise a ticket and we will pick it up.', 'fa-life-ring'],
];
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Tickets</h1>
        <p><?= $scope === 'all' ? 'Everyone at ' . escapeHtml($session_client_name) : 'The ones you raised' ?></p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <?php if ($sees_all) { ?>
            <div class="btn-group" role="group" aria-label="Whose tickets">
                <a class="btn btn-outline-secondary<?= $scope === 'all' ? ' active' : '' ?>" href="<?= escapeHtml(ticketsUrl(['scope' => null, 'page' => null])) ?>"><i class="fas fa-users me-2"></i>Everyone</a>
                <a class="btn btn-outline-secondary<?= $scope === 'mine' ? ' active' : '' ?>" href="<?= escapeHtml(ticketsUrl(['scope' => 'mine', 'page' => null])) ?>"><i class="fas fa-user me-2"></i>Mine</a>
            </div>
        <?php } ?>
        <form method="get" class="d-flex" role="search">
            <input type="hidden" name="tab" value="<?= escapeHtml($tab) ?>">
            <?php if ($sees_all && $scope === 'mine') { ?><input type="hidden" name="scope" value="mine"><?php } ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Search subject or number" aria-label="Search tickets" maxlength="200">
            </div>
        </form>
    </div>
</div>

<nav class="itflow-portal-tabs" aria-label="Ticket status">
    <?php foreach ($tabs as $key => $t) { ?>
        <a class="<?= $key === $tab ? 'active' : '' ?><?= $key === 'you' && $counts['you'] ? ' itflow-portal-tab-alert' : '' ?>" href="<?= escapeHtml(ticketsUrl(['tab' => $key, 'page' => null])) ?>">
            <i class="fas <?= $t['icon'] ?>"></i><?= escapeHtml($t['label']) ?>
            <span class="badge rounded-pill"><?= $counts[$key] ?></span>
        </a>
    <?php } ?>
</nav>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'tickets', 'filtered' => true, 'match_text' => 'Nothing here has &ldquo;' . escapeHtml($q) . '&rdquo; in its subject or number.', 'clear_url' => ticketsUrl(['q' => null, 'page' => null])]) ?>
        <?php } else {
            [$title, $text, $icon] = $empty_titles[$tab]; ?>
            <?= emptyStateHtml(['title' => $title, 'text' => $text, 'icon' => $icon, 'filtered' => false,
                'actions' => in_array($tab, ['open', 'all'], true) ? [['label' => 'New ticket', 'href' => 'ticket_add.php']] : []]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($tickets)) {
                $updated = $row['ticket_closed_at'] ?: ($row['ticket_updated_at'] ?: $row['ticket_created_at']); ?>
                <li>
                    <a class="itflow-portal-ticket" href="ticket.php?id=<?= intval($row['ticket_id']) ?>">
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['ticket_subject']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <span><?= escapeHtml($row['ticket_prefix'] . $row['ticket_number']) ?></span>
                                <?php if ($scope === 'all' && !empty($row['contact_name'])) { ?>
                                    <span><i class="fas fa-user me-1"></i><?= escapeHtml($row['contact_name']) ?></span>
                                <?php } ?>
                                <?php if (!empty($row['category_name'])) { ?>
                                    <span><i class="fas fa-layer-group me-1"></i><?= escapeHtml($row['category_name']) ?></span>
                                <?php } ?>
                                <span>Opened <?= escapeHtml(date('M j, Y', strtotime($row['ticket_created_at']))) ?></span>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <?= portalTicketStateHtml($row) ?>
                            <small title="<?= escapeHtml($updated) ?>"><?= $row['ticket_closed_at'] ? 'Closed ' : 'Updated ' ?><?= timeAgo($updated) ?></small>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(ticketsUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Newer"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(ticketsUrl(['page' => $page + 1])) ?>" aria-label="Older"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php require_once "includes/footer.php"; ?>
