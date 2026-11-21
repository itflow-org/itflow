<?php
/*
 * Client Portal
 * A Help article - what it says, as written (its tables and colours too),
 * with a way to ask about it and the others in its category. Printable.
 *
 * SCOPING: any contact who can sign in; the article must be one kbClientSql()
 * lets $session_client_id read - published, not archived, and shared with
 * them.
 *
 * Each visit counts as a read (kbCountView()), but not viewing the portal as a
 * contact. Under it, its files (kb_file.php), then "Was this helpful?"
 * (kbFeedbackHtml(), kb_feedback in post.php).
 */

header("Content-Security-Policy: default-src 'self'");
$portal_csp_inline_styles = true; // an article's own tables and colours - see includes/header.php

require_once "includes/inc_all.php";

if (!kbOn()) {
    redirect('/client/index.php');
}

$kb_article_id = intval($_GET['id'] ?? 0);
$visible = kbClientSql($session_client_id);
$article = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_category_id,
        kb_article_created_at, kb_article_updated_at, category_name, category_icon
    FROM kb_articles
    LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
    WHERE kb_article_id = $kb_article_id AND $visible
    LIMIT 1"));

if (!$article) { ?>
    <a class="itflow-portal-back" href="kb.php"><i class="fas fa-chevron-left"></i>Help</a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => "That article isn't here", 'text' => 'It may have been taken down, or isn\'t shared with you.', 'icon' => 'fa-book', 'filtered' => false,
            'actions' => [['label' => 'All Help', 'href' => 'kb.php', 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

if (!portalViewingAs()) {
    kbCountView($kb_article_id);
}

$content = documentHtmlPurify($article['kb_article_content']);
$updated = $article['kb_article_updated_at'] ?: $article['kb_article_created_at'];
$category_id = intval($article['kb_article_category_id']);

// The others in its category they can read
$related = [];
if ($category_id) {
    $sql = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title FROM kb_articles
        WHERE $visible AND kb_article_category_id = $category_id AND kb_article_id != $kb_article_id
        ORDER BY COALESCE(kb_article_updated_at, kb_article_created_at) DESC LIMIT 5");
    while ($row = mysqli_fetch_assoc($sql)) {
        $related[] = $row;
    }
}
?>

<a class="itflow-portal-back d-print-none" href="kb.php<?= $category_id ? '?category=' . $category_id : '' ?>"><i class="fas fa-chevron-left"></i><?= $category_id ? escapeHtml($article['category_name']) : 'Help' ?></a>

<div class="itflow-portal-page-head">
    <div class="min-w-0">
        <h1><?= escapeHtml($article['kb_article_title']) ?></h1>
        <p>
            <?= trim((string) $article['kb_article_summary']) !== '' ? escapeHtml($article['kb_article_summary']) . ' · ' : '' ?>
            Updated <?= escapeHtml(date('M j, Y', strtotime($updated))) ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
        <button type="button" class="btn btn-outline-secondary" data-portal-print><i class="fas fa-print me-2"></i>Print</button>
        <a class="btn btn-outline-secondary" href="<?= escapeHtml(portalAskUrl('Question about ' . $article['kb_article_title'])) ?>"><i class="fas fa-question-circle me-2"></i>Still Stuck? Ask Us</a>
    </div>
</div>

<?php if (trim(strip_tags($content, '<img><table>')) !== '') { ?>
    <article class="itflow-portal-panel itflow-portal-panel-body itflow-portal-document prettyContent mb-4">
        <?= $content ?>
    </article>
<?php } else { ?>
    <section class="itflow-portal-panel mb-4">
        <?= emptyStateHtml(['title' => 'This article is empty', 'text' => '', 'icon' => 'fa-book', 'filtered' => false]) ?>
    </section>
<?php } ?>

<?php $files = kbArticleFiles($kb_article_id);
if ($files) { ?>
    <section class="itflow-portal-panel mb-4">
        <div class="itflow-portal-panel-head"><h2><i class="fas fa-paperclip me-2"></i>Files</h2></div>
        <?= kbFilesListHtml($files, function ($id) { return "kb_file.php?file_id=$id"; }) ?>
    </section>
<?php } ?>

<?php if (kbFeedbackReady()) { ?>
    <?= kbFeedbackHtml($kb_article_id, kbFeedbackState($kb_article_id, $session_contact_id), 'post.php', $_SESSION['csrf_token'], portalAskUrl('Question about ' . $article['kb_article_title'])) ?>
<?php } ?>

<?php if ($related) { ?>
    <section class="itflow-portal-panel d-print-none">
        <div class="itflow-portal-panel-head"><h2><i class="fas fa-<?= categoryIcon($article['category_icon'] ?? '') ?: 'book' ?> me-2"></i>More in <?= escapeHtml($article['category_name']) ?></h2></div>
        <ul class="itflow-portal-list">
            <?php foreach ($related as $row) { ?>
                <li>
                    <a class="itflow-portal-ticket" href="kb_article.php?id=<?= intval($row['kb_article_id']) ?>">
                        <span class="itflow-portal-ticket-main"><span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['kb_article_title']) ?></span></span>
                        <i class="fas fa-chevron-right text-body-secondary" aria-hidden="true"></i>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<?php
require_once "includes/footer.php";
