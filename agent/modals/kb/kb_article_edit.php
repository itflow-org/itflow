<?php

/*
 * Edit an article - the fields are kb_article_form.php. Saving keeps what it
 * said before as a version.
 */

require_once '../../../includes/modal_header.php';

$kb_closed = kbClosedText(2);
if ($kb_closed !== '') {
    exit(json_encode(['error' => $kb_closed]));
}

$kb_form = kbArticleRow($_GET['id'] ?? 0);
if (!$kb_form || $kb_form['kb_article_archived_at']) {
    exit(json_encode(['error' => $kb_form ? 'Restore the article to change it.' : 'That article no longer exists.']));
}
$kb_form_mode = 'edit';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-book me-2"></i>Edit Article <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($kb_form['kb_article_title']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="kb_article_id" value="<?= intval($kb_form['kb_article_id']) ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/kb_article_form.php'; ?>
    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_kb_article" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
