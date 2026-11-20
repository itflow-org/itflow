<?php
/*
 * Client Portal
 * Search - one box across what the contact can open: their tickets (subject,
 * number and what was said on them), the documents shared with them and the
 * Help articles - the first eight of each, with See all to that page's own
 * search. The box is in the top bar on every page.
 *
 * SCOPING: tickets by portalTicketScopeSql(); documents for primary and
 * technical contacts only, those marked visible to the client (as
 * documents.php); articles kbClientSql() lets $session_client_id read (as kb.php).
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 200));
$limit = 8;
$can_documents = $config_module_enable_itdoc && contactCan('itdoc');
$can_help = kbOn() && kbClientHas($session_client_id);
$sections = [];

if ($q !== '') {
    $q_sql = escapeSql($q);
    $q_number = intval(preg_replace('/\D/', '', $q));

    // Tickets - the subject, the number, or something said on it they can see
    $where = portalTicketScopeSql() . " AND tickets.ticket_archived_at IS NULL
        AND (tickets.ticket_subject LIKE '%$q_sql%' OR CONCAT(tickets.ticket_prefix, tickets.ticket_number) LIKE '%$q_sql%'" . ($q_number ? " OR tickets.ticket_number = $q_number" : '') . "
            OR EXISTS (SELECT 1 FROM ticket_replies WHERE ticket_reply_ticket_id = tickets.ticket_id AND ticket_reply_type != 'Internal'
                AND ticket_reply_archived_at IS NULL AND ticket_reply LIKE '%$q_sql%'))";
    $total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tickets WHERE $where"))[0]);
    $rows = [];
    if ($total) {
        $sql = mysqli_query($mysqli, "SELECT " . portalTicketColumnsSql() . " FROM tickets LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
            WHERE $where ORDER BY " . TICKET_OPEN_SQL . " DESC, COALESCE(tickets.ticket_updated_at, tickets.ticket_created_at) DESC LIMIT $limit");
        while ($row = mysqli_fetch_assoc($sql)) {
            $rows[] = $row;
        }
    }
    $sections['tickets'] = ['label' => 'Tickets', 'icon' => 'fa-life-ring', 'total' => $total, 'rows' => $rows, 'all' => 'tickets.php?' . http_build_query(['tab' => 'all', 'q' => $q])];

    // Documents shared with them - for those who see the company's IT
    if ($can_documents) {
        $where = "document_client_id = $session_client_id AND document_client_visible = 1 AND document_archived_at IS NULL
            AND (document_name LIKE '%$q_sql%' OR document_description LIKE '%$q_sql%' OR document_content_raw LIKE '%$q_sql%')";
        $total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM documents WHERE $where"))[0]);
        $rows = [];
        if ($total) {
            $sql = mysqli_query($mysqli, "SELECT document_id, document_name, document_description, document_created_at, document_updated_at, folder_name,
                    (SELECT file_ext FROM document_files JOIN files ON files.file_id = document_files.file_id WHERE document_files.document_id = documents.document_id ORDER BY files.file_id LIMIT 1) AS file_ext
                FROM documents LEFT JOIN folders ON folder_id = document_folder_id AND folder_client_id = $session_client_id
                WHERE $where ORDER BY (document_name LIKE '%$q_sql%') DESC, COALESCE(document_updated_at, document_created_at) DESC LIMIT $limit");
            while ($row = mysqli_fetch_assoc($sql)) {
                $rows[] = $row;
            }
        }
        $sections['documents'] = ['label' => 'Documents', 'icon' => 'fa-folder-open', 'total' => $total, 'rows' => $rows, 'all' => 'documents.php?' . http_build_query(['q' => $q])];
    }

    // Help - the articles shared with them
    if ($can_help) {
        $where = kbClientSql($session_client_id) . " AND (kb_article_title LIKE '%$q_sql%' OR kb_article_summary LIKE '%$q_sql%' OR MATCH(kb_article_content_raw) AGAINST ('$q_sql'))";
        $total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM kb_articles WHERE $where"))[0]);
        $rows = [];
        if ($total) {
            $sql = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_created_at, kb_article_updated_at, category_name, category_icon
                FROM kb_articles LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
                WHERE $where ORDER BY (kb_article_title LIKE '%$q_sql%') DESC, MATCH(kb_article_content_raw) AGAINST ('$q_sql') DESC, kb_article_title ASC LIMIT $limit");
            while ($row = mysqli_fetch_assoc($sql)) {
                $rows[] = $row;
            }
        } elseif (!portalViewingAs()) {
            // Nothing in Help - on the Knowledge Base's Not found list, as a search in Help is
            kbSearchMissSave($q, 'portal', $session_client_id);
        }
        $sections['help'] = ['label' => 'Help', 'icon' => 'fa-book', 'total' => $total, 'rows' => $rows, 'all' => 'kb.php?' . http_build_query(['q' => $q])];
    }
}

$found = array_sum(array_column($sections, 'total'));
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Search</h1>
        <p><?= $q === '' ? 'Your tickets' . ($can_documents ? ', documents' : '') . ($can_help ? ' and Help' : '') . ', all at once' : ($found ? $found . ' found for &ldquo;' . escapeHtml($q) . '&rdquo;' : 'Nothing found for &ldquo;' . escapeHtml($q) . '&rdquo;') ?></p>
    </div>
    <form method="get" class="d-flex" role="search">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
            <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Search everything" aria-label="Search tickets, documents and help" maxlength="200" <?= $q === '' ? 'autofocus' : '' ?>>
        </div>
    </form>
</div>

<?php if ($q === '') { ?>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => 'What are you looking for?', 'text' => 'Type a word or a ticket number - it looks through your tickets and what was said on them' . ($can_documents ? ', your documents' : '') . ($can_help ? ' and our Help articles' : '') . '.', 'icon' => 'fa-search', 'filtered' => false]) ?>
    </section>
<?php } elseif (!$found) { ?>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => 'Nothing found', 'text' => 'Nothing mentions &ldquo;' . escapeHtml($q) . '&rdquo;. Try another word - or ask us.', 'icon' => 'fa-search', 'filtered' => false,
            'actions' => [['label' => 'Ask us', 'href' => portalAskUrl($q), 'icon' => 'fa-plus']]]) ?>
    </section>
<?php } else { ?>
    <?php foreach ($sections as $key => $section) {
        if (!$section['total']) {
            continue;
        } ?>
        <section class="itflow-portal-panel mb-4" data-search-section="<?= $key ?>">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas <?= $section['icon'] ?>"></i><?= $section['label'] ?> <span class="badge rounded-pill text-bg-secondary ms-1"><?= $section['total'] ?></span></h2>
                <?php if ($section['total'] > count($section['rows'])) { ?>
                    <a class="small" href="<?= escapeHtml($section['all']) ?>">See all <?= $section['total'] ?></a>
                <?php } ?>
            </div>
            <ul class="itflow-portal-list">
                <?php foreach ($section['rows'] as $row) { ?>
                    <li>
                        <?php if ($key === 'tickets') {
                            $updated = $row['ticket_closed_at'] ?: ($row['ticket_updated_at'] ?: $row['ticket_created_at']); ?>
                            <a class="itflow-portal-ticket" href="ticket.php?id=<?= intval($row['ticket_id']) ?>">
                                <span class="itflow-portal-ticket-main">
                                    <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['ticket_subject']) ?></span>
                                    <span class="itflow-portal-ticket-meta">
                                        <span><?= escapeHtml($row['ticket_prefix'] . $row['ticket_number']) ?></span>
                                        <span>Opened <?= escapeHtml(date('M j, Y', strtotime($row['ticket_created_at']))) ?></span>
                                    </span>
                                </span>
                                <span class="itflow-portal-ticket-side">
                                    <?= portalTicketStateHtml($row) ?>
                                    <small title="<?= escapeHtml($updated) ?>"><?= $row['ticket_closed_at'] ? 'Closed ' : 'Updated ' ?><?= timeAgo($updated) ?></small>
                                </span>
                            </a>
                        <?php } elseif ($key === 'documents') {
                            $updated = $row['document_updated_at'] ?: $row['document_created_at']; ?>
                            <a class="itflow-portal-ticket" href="document.php?id=<?= intval($row['document_id']) ?>">
                                <?= portalTile($row['file_ext'] !== null ? 'fa-' . getFileIcon($row['file_ext']) : 'fa-file-alt') ?>
                                <span class="itflow-portal-ticket-main">
                                    <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['document_name']) ?></span>
                                    <span class="itflow-portal-ticket-meta">
                                        <?php if (!empty($row['folder_name'])) { ?><span><i class="fas fa-folder me-1"></i><?= escapeHtml($row['folder_name']) ?></span><?php } ?>
                                        <?php if (trim((string) $row['document_description']) !== '') { ?><span class="text-truncate"><?= escapeHtml($row['document_description']) ?></span><?php } ?>
                                    </span>
                                </span>
                                <span class="itflow-portal-ticket-side">
                                    <small title="<?= escapeHtml($updated) ?>">Updated <?= escapeHtml(date('M j, Y', strtotime($updated))) ?></small>
                                </span>
                            </a>
                        <?php } else {
                            $updated = $row['kb_article_updated_at'] ?: $row['kb_article_created_at'];
                            $excerpt = kbArticleExcerpt($row); ?>
                            <a class="itflow-portal-ticket" href="kb_article.php?id=<?= intval($row['kb_article_id']) ?>">
                                <?= portalTile('fa-' . (categoryIcon($row['category_icon'] ?? '') ?: 'book')) ?>
                                <span class="itflow-portal-ticket-main">
                                    <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['kb_article_title']) ?></span>
                                    <span class="itflow-portal-ticket-meta">
                                        <?php if ($row['category_name'] !== null) { ?><span><i class="fas fa-<?= categoryIcon($row['category_icon'] ?? '') ?: 'book' ?> me-1"></i><?= escapeHtml($row['category_name']) ?></span><?php } ?>
                                        <?php if ($excerpt !== '') { ?><span class="text-truncate"><?= escapeHtml($excerpt) ?></span><?php } ?>
                                    </span>
                                </span>
                                <span class="itflow-portal-ticket-side">
                                    <small title="<?= escapeHtml($updated) ?>">Updated <?= escapeHtml(date('M j, Y', strtotime($updated))) ?></small>
                                </span>
                            </a>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        </section>
    <?php } ?>
<?php } ?>

<?php require_once "includes/footer.php"; ?>
