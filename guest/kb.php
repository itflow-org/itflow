<?php
/*
 * The public help center - the Knowledge Base articles set for anyone to read,
 * for anyone with the link: no sign-in. Search and categories on the list;
 * ?article=12 (or 12-its-title-as-words, from kbPublicUrl()) opens one. Only
 * while Admin > Settings > Knowledge Base has it on (kbPublicOn()), and only
 * published, unarchived articles set to Anyone (kbPublicSql()).
 *
 * Laid out like the client portal, with your logo, so it looks like yours.
 * An article's own tables and colours are inline styles, already through
 * HTMLPurifier (documentHtmlPurify()), so styles are let in; scripts are not.
 *
 * Each visit counts as a read (kbCountView()), "Was this helpful?" goes under
 * an article (kbFeedbackHtml(), posted back here), and a search that finds
 * nothing goes on the Knowledge Base's Not found list. A session keeps a
 * visit's reads, answer and form token.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_set_timezone.php';

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'");
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: strict-origin-when-cross-origin');

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1")) ?: ['company_name' => '', 'company_logo' => ''];
$company_name = (string) $company['company_name'];
$company_logo = (string) $company['company_logo'];
$dark = ($_COOKIE['itflow_portal_theme'] ?? '') === 'dark';

$open = kbPublicOn();
$visible = kbPublicSql();
$per_page = 25;
$q = trim((string) ($_GET['q'] ?? ''));
$q_sql = escapeSql($q);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = randomString(32);
}

// "Was this helpful?" under an article - the answer, then for a no what was missing. Back to the article.
if ($open && $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['kb_feedback']) || isset($_POST['kb_feedback_comment']))) {
    $kb_article_id = intval($_POST['kb_feedback'] ?? $_POST['kb_feedback_comment']);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title FROM kb_articles WHERE kb_article_id = $kb_article_id AND $visible LIMIT 1"));
    if ($row && kbFeedbackReady() && hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        if (isset($_POST['kb_feedback'])) {
            kbFeedbackSave($kb_article_id, intval($_POST['helpful'] ?? 0) === 1, 'public');
        } else {
            kbFeedbackComment($kb_article_id, $_POST['comment'] ?? '');
        }
    }
    redirect($row ? kbPublicArticleUrl($row) . '#kb-feedback' : 'kb.php');
}

$article = null;
$categories = [];
$category = null;
$total = 0;
$articles = [];
$related = [];

if ($open && isset($_GET['article'])) {

    $kb_article_id = intval($_GET['article']);
    $article = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_category_id,
            kb_article_created_at, kb_article_updated_at, category_name, category_icon
        FROM kb_articles
        LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
        WHERE kb_article_id = $kb_article_id AND $visible LIMIT 1"));
    if (!$article) {
        http_response_code(404);
    } else {
        kbCountView($kb_article_id);
    }
    if ($article && intval($article['kb_article_category_id'])) {
        $sql = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title FROM kb_articles
            WHERE $visible AND kb_article_category_id = " . intval($article['kb_article_category_id']) . " AND kb_article_id != $kb_article_id
            ORDER BY COALESCE(kb_article_updated_at, kb_article_created_at) DESC LIMIT 5");
        while ($row = mysqli_fetch_assoc($sql)) {
            $related[] = $row;
        }
    }

} elseif ($open) {

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
        $where .= " AND (kb_article_title LIKE '%$q_sql%' OR kb_article_summary LIKE '%$q_sql%' OR MATCH(kb_article_content_raw) AGAINST ('$q_sql'))";
    }
    $total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM kb_articles WHERE $where"))[0]);
    if ($q !== '' && !$total && $category === null) {
        kbSearchMissSave($q, 'public');
    }
    $pages = max(1, intval(ceil($total / $per_page)));
    $page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
    $offset = ($page - 1) * $per_page;
    $order = $q !== '' ? "(kb_article_title LIKE '%$q_sql%') DESC, MATCH(kb_article_content_raw) AGAINST ('$q_sql') DESC" : 'COALESCE(kb_article_updated_at, kb_article_created_at) DESC';
    $sql = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_created_at, kb_article_updated_at, category_name, category_icon
        FROM kb_articles
        LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
        WHERE $where
        ORDER BY $order, kb_article_title ASC
        LIMIT $per_page OFFSET $offset");
    while ($row = mysqli_fetch_assoc($sql)) {
        $articles[] = $row;
    }

} else {
    http_response_code(404);
}

/** The list's address with some of its settings changed. */
function kbPublicListUrl(array $changes) {
    global $category, $q;
    $params = array_merge(['category' => $category, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'kb.php' . ($params ? '?' . http_build_query($params) : '');
}

/** An article's address here - its id, then its title as words. */
function kbPublicArticleUrl(array $row) {
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $row['kb_article_title'])), '-');
    return 'kb.php?article=' . intval($row['kb_article_id']) . ($slug !== '' ? '-' . mb_substr($slug, 0, 80) : '');
}

$title = $article ? $article['kb_article_title'] . ' | ' : '';

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $dark ? 'dark' : 'light' ?>" data-lte-color-mode="off" data-lte-print="plain">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="<?= $dark ? 'dark' : 'light' ?>">
    <title><?= escapeHtml($title . $company_name) ?> | Help Center</title>
    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" href="/uploads/favicon.ico">
    <?php } ?>
    <link rel="stylesheet" href="<?= staticFileUrl('/libs/fontawesome-free/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= staticFileUrl('/libs/adminlte/css/adminlte.min.css') ?>">
    <link rel="stylesheet" href="<?= staticFileUrl('/libs/adminlte/css/adminlte-colors-v3.min.css') ?>">
    <link rel="stylesheet" href="<?= staticFileUrl('/css/itflow_custom.css') ?>">
</head>
<body class="itflow-portal itflow-kb-public d-flex flex-column min-vh-100 bg-body-tertiary theme-<?= escapeHtml($config_theme) ?>" data-lte-primary="<?= escapeHtml($config_theme) ?>">

<header class="itflow-portal-header">
    <div class="container d-flex align-items-center gap-3">
        <a class="itflow-portal-brand" href="kb.php">
            <?php if ($company_logo !== '') { ?>
                <img src="/uploads/settings/<?= escapeHtml($company_logo) ?>" alt="<?= escapeHtml($company_name) ?>">
            <?php } else { ?>
                <span class="itflow-brand-mark itflow-brand-mark-initials" aria-hidden="true"><?= escapeHtml(clientInitials('', $company_name)) ?></span>
                <span class="itflow-portal-brand-name"><?= escapeHtml($company_name) ?></span>
            <?php } ?>
        </a>
        <span class="text-body-secondary fw-semibold border-start ps-3">Help Center</span>
        <?php if (intval($config_client_portal_enable) === 1) { ?>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="/login.php"><i class="fas fa-sign-in-alt me-2"></i>Sign in</a>
        <?php } ?>
    </div>
</header>

<main class="container py-4 flex-grow-1">

<?php if (!$open || (isset($_GET['article']) && !$article)) { ?>

    <section class="itflow-portal-panel mt-3">
        <?= emptyStateHtml(['title' => $open ? "That article isn't here" : 'Nothing here', 'text' => $open ? 'It may have been taken down.' : '', 'icon' => 'fa-book', 'filtered' => false,
            'actions' => $open ? [['label' => 'All articles', 'href' => 'kb.php', 'icon' => 'fa-arrow-left']] : []]) ?>
    </section>

<?php } elseif ($article) {
    $updated = $article['kb_article_updated_at'] ?: $article['kb_article_created_at'];
    $content = documentHtmlPurify($article['kb_article_content']);
    $category_id = intval($article['kb_article_category_id']); ?>

    <a class="itflow-portal-back d-print-none" href="kb.php<?= $category_id ? '?category=' . $category_id : '' ?>"><i class="fas fa-chevron-left"></i><?= $category_id ? escapeHtml($article['category_name']) : 'Help Center' ?></a>

    <div class="itflow-portal-page-head">
        <div class="min-w-0">
            <h1><?= escapeHtml($article['kb_article_title']) ?></h1>
            <p>
                <?= trim((string) $article['kb_article_summary']) !== '' ? escapeHtml($article['kb_article_summary']) . ' · ' : '' ?>
                Updated <?= escapeHtml(date('M j, Y', strtotime($updated))) ?>
            </p>
        </div>
    </div>

    <?php if (trim(strip_tags($content, '<img><table>')) !== '') { ?>
        <article class="itflow-portal-panel itflow-portal-panel-body itflow-portal-document prettyContent mb-4">
            <?= $content ?>
        </article>
    <?php } ?>

    <?php $files = kbArticleFiles($article['kb_article_id']);
    if ($files) { ?>
        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head"><h2><i class="fas fa-paperclip me-2"></i>Files</h2></div>
            <?= kbFilesListHtml($files, function ($id) { return "kb_file.php?file_id=$id"; }) ?>
        </section>
    <?php } ?>

    <?php if (kbFeedbackReady()) { ?>
        <?= kbFeedbackHtml($article['kb_article_id'], kbFeedbackState($article['kb_article_id']), kbPublicArticleUrl($article), $_SESSION['csrf_token']) ?>
    <?php } ?>

    <?php if ($related) { ?>
        <section class="itflow-portal-panel d-print-none">
            <div class="itflow-portal-panel-head"><h2><i class="fas fa-<?= categoryIcon($article['category_icon'] ?? '') ?: 'book' ?> me-2"></i>More in <?= escapeHtml($article['category_name']) ?></h2></div>
            <ul class="itflow-portal-list">
                <?php foreach ($related as $row) { ?>
                    <li>
                        <a class="itflow-portal-ticket" href="<?= escapeHtml(kbPublicArticleUrl($row)) ?>">
                            <span class="itflow-portal-ticket-main"><span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['kb_article_title']) ?></span></span>
                            <i class="fas fa-chevron-right text-body-secondary" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </section>
    <?php } ?>

<?php } else { ?>

    <div class="itflow-portal-page-head">
        <div>
            <h1>Help Center</h1>
            <p>How-tos and answers from <?= escapeHtml($company_name) ?></p>
        </div>
        <form method="get" class="d-flex" role="search">
            <?php if ($category !== null) { ?><input type="hidden" name="category" value="<?= $category ?>"><?php } ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Search help" aria-label="Search help" maxlength="200">
            </div>
        </form>
    </div>

    <?php if (count($categories) > 1) { ?>
        <nav class="itflow-portal-tabs" aria-label="Categories">
            <a class="<?= $category === null ? 'active' : '' ?>" href="<?= escapeHtml(kbPublicListUrl(['category' => null, 'page' => null])) ?>"><i class="fas fa-list"></i>All <span class="badge rounded-pill"><?= $all_count ?></span></a>
            <?php foreach ($categories as $id => $c) { ?>
                <a class="<?= $category === $id ? 'active' : '' ?>" href="<?= escapeHtml(kbPublicListUrl(['category' => $id, 'page' => null])) ?>"><i class="fas fa-<?= $c['icon'] ?>"></i><?= escapeHtml($c['name']) ?> <span class="badge rounded-pill"><?= $c['count'] ?></span></a>
            <?php } ?>
        </nav>
    <?php } ?>

    <section class="itflow-portal-panel">
        <?php if (!$total) { ?>
            <?= $q !== ''
                ? emptyStateHtml(['thing' => 'articles', 'filtered' => true, 'match_text' => 'No article mentions &ldquo;' . escapeHtml($q) . '&rdquo;.', 'clear_url' => kbPublicListUrl(['q' => null, 'page' => null])])
                : emptyStateHtml(['title' => 'Nothing here yet', 'text' => '', 'icon' => 'fa-book', 'filtered' => false]) ?>
        <?php } else { ?>
            <ul class="itflow-portal-list">
                <?php foreach ($articles as $row) {
                    $updated = $row['kb_article_updated_at'] ?: $row['kb_article_created_at'];
                    $excerpt = kbArticleExcerpt($row);
                    $icon = categoryIcon($row['category_icon'] ?? '') ?: 'book'; ?>
                    <li>
                        <a class="itflow-portal-ticket" href="<?= escapeHtml(kbPublicArticleUrl($row)) ?>">
                            <span class="itflow-portal-tile" aria-hidden="true"><i class="fas fa-fw fa-<?= $icon ?>"></i></span>
                            <span class="itflow-portal-ticket-main">
                                <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['kb_article_title']) ?></span>
                                <span class="itflow-portal-ticket-meta">
                                    <?php if ($row['category_name'] !== null && $category === null) { ?><span><i class="fas fa-<?= $icon ?> me-1"></i><?= escapeHtml($row['category_name']) ?></span><?php } ?>
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
                        <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(kbPublicListUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                        <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(kbPublicListUrl(['page' => $page + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </section>

<?php } ?>

</main>

<footer class="itflow-portal-footer mt-auto">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
        <span><?= escapeHtml($company_name) ?></span>
        <?php if (!$config_whitelabel_enabled) { ?>
            <span>Powered by ITFlow</span>
        <?php } ?>
    </div>
</footer>

<script src="<?= staticFileUrl('/js/pretty_content.js') ?>"></script>

</body>
</html>
