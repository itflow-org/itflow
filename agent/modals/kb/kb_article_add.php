<?php

/*
 * New Article in the Knowledge Base - the fields are kb_article_form.php.
 * ?category starts it in a category, and ?title with a title - Write an
 * article on the Not found list. Starts reviewed every year, published.
 */

require_once '../../../includes/modal_header.php';

$kb_closed = kbClosedText(2);
if ($kb_closed !== '') {
    exit(json_encode(['error' => $kb_closed]));
}

$kb_form_mode = 'add';
$kb_form = [
    'kb_article_title' => mb_substr(trim((string) ($_GET['title'] ?? '')), 0, 200),
    'kb_article_category_id' => intval($_GET['category'] ?? 0),
    'kb_article_review_months' => 12,
    'kb_article_published_at' => date('Y-m-d H:i:s'),
];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-book me-2"></i>New Article</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/kb_article_form.php'; ?>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_kb_article" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
