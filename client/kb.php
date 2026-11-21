<?php
/*
 * Client Portal
 * Help - the Knowledge Base articles shared with this client: every client's,
 * their tags', their own and the ones anyone can read. In tabs by category,
 * searchable by title, summary and what they say, 25 a page.
 *
 * SCOPING: any contact who can sign in; only published articles kbClientSql()
 * lets $session_client_id read.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

if (!kbOn()) {
    redirect('/client/index.php');
}

$per_page = 25;
$q = trim((string) ($_GET['q'] ?? ''));
$visible = kbClientSql($session_client_id);

// The categories that hold something for them - and the articles in none
$categories = [];
$sql = mysqli_query($mysqli, "SELECT kb_article_category_id, category_name, category_icon, COUNT(*) AS n FROM kb_articles
    LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
    WHERE $visible GROUP BY kb_article_category_id, category_name, category_icon, category_order
    ORDER BY kb_article_category_id = 0, category_order ASC, category_name ASC");
$all_count = 0;
while ($row = mysqli_fetch_assoc($sql)) {
    $categories[intval($row['kb_article_category_id'])] = ['name' => $row['category_name'] ?? 'Other', 'icon' => categoryIcon($row['category_icon'] ?? '') ?: 'book', 'count' => intval($row['n'])];
    $all_count += intval($row['n']);
}
$category = isset($_GET['category']) && isset($categories[intval($_GET['category'])]) ? intval($_GET['category']) : null;

$where = $visible . ($category !== null ? " AND kb_article_category_id = $category" : '');
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND (kb_article_title LIKE '%$q_sql%' OR kb_article_summary LIKE '%$q_sql%' OR MATCH(kb_article_content_raw) AGAINST ('$q_sql'))";
}
$total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM kb_articles WHERE $where"))[0]);

// A search of all of Help that found nothing goes on the Knowledge Base's Not found list
if ($q !== '' && !$total && $category === null && !portalViewingAs()) {
    kbSearchMissSave($q, 'portal', $session_client_id);
}
$pages = max(1, intval(ceil($total / $per_page)));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

// A search puts the closest first; otherwise the newest
$order = $q !== '' ? "(kb_article_title LIKE '%$q_sql%') DESC, MATCH(kb_article_content_raw) AGAINST ('$q_sql') DESC" : 'COALESCE(kb_article_updated_at, kb_article_created_at) DESC';
$articles = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_created_at, kb_article_updated_at, category_name, category_icon
    FROM kb_articles
    LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
    WHERE $where
    ORDER BY $order, kb_article_title ASC
    LIMIT $per_page OFFSET $offset");

/** This page's address with some of its settings changed. */
function kbPortalUrl(array $changes) {
    global $category, $q;
    $params = array_merge(['category' => $category, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'kb.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Help</h1>
        <p>How-tos and answers from <?= escapeHtml($session_company_name) ?></p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" class="d-flex" role="search">
            <?php if ($category !== null) { ?><input type="hidden" name="category" value="<?= $category ?>"><?php } ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Search help" aria-label="Search help" maxlength="200">
            </div>
        </form>
        <a class="btn btn-outline-primary" href="ticket_add.php"><i class="fas fa-plus me-2"></i>Ask Us</a>
    </div>
</div>

<?php if (count($categories) > 1) { ?>
    <nav class="itflow-portal-tabs" aria-label="Categories">
        <a class="<?= $category === null ? 'active' : '' ?>" href="<?= escapeHtml(kbPortalUrl(['category' => null, 'page' => null])) ?>"><i class="fas fa-list"></i>All <span class="badge rounded-pill"><?= $all_count ?></span></a>
        <?php foreach ($categories as $id => $c) { ?>
            <a class="<?= $category === $id ? 'active' : '' ?>" href="<?= escapeHtml(kbPortalUrl(['category' => $id, 'page' => null])) ?>"><i class="fas fa-<?= $c['icon'] ?>"></i><?= escapeHtml($c['name']) ?> <span class="badge rounded-pill"><?= $c['count'] ?></span></a>
        <?php } ?>
    </nav>
<?php } ?>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'articles', 'filtered' => true, 'match_text' => 'No article mentions &ldquo;' . escapeHtml($q) . '&rdquo;.', 'clear_url' => kbPortalUrl(['q' => null, 'page' => null]),
                'actions' => [['label' => 'Ask Us', 'href' => portalAskUrl($q), 'icon' => 'fa-plus']]]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => 'Nothing here yet', 'text' => 'How-tos and answers we share with you show here. Ask us anything in the meantime.', 'icon' => 'fa-book', 'filtered' => false,
                'actions' => [['label' => 'Ask Us', 'href' => 'ticket_add.php', 'icon' => 'fa-plus']]]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($articles)) {
                $updated = $row['kb_article_updated_at'] ?: $row['kb_article_created_at'];
                $excerpt = kbArticleExcerpt($row); ?>
                <li>
                    <a class="itflow-portal-ticket" href="kb_article.php?id=<?= intval($row['kb_article_id']) ?>">
                        <?= portalTile('fa-' . (categoryIcon($row['category_icon'] ?? '') ?: 'book')) ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['kb_article_title']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <?php if ($row['category_name'] !== null && $category === null) { ?><span><i class="fas fa-<?= categoryIcon($row['category_icon'] ?? '') ?: 'book' ?> me-1"></i><?= escapeHtml($row['category_name']) ?></span><?php } ?>
                                <?php if ($excerpt !== '') { ?><span class="text-truncate"><?= escapeHtml($excerpt) ?></span><?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <small title="<?= escapeHtml($updated) ?>">Updated <?= escapeHtml(date('M j, Y', strtotime($updated))) ?></small>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(kbPortalUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(kbPortalUrl(['page' => $page + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
