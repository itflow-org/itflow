<?php

/*
 * Review a Knowledge Base article with AI (aiKbArticleReview(),
 * functions/ai_document.php) - whether it looks good, needs work or is out of
 * date, what to fix or check (the most important first), anything that
 * shouldn't be in an article its readers can read, a one-line summary for
 * it, and the passwords and keys written in it. Use as its summary saves
 * that line (set_kb_article_summary); Mark reviewed is the article's own.
 * The window's script is js/ai_review.js, shared with documents.
 */

require_once '../../../includes/modal_header.php';

$kb_closed = kbClosedText(1);
if ($kb_closed !== '') {
    exit(json_encode(['error' => $kb_closed]));
}

// Using AI is a permission of its own
if (!aiCan()) {
    permissionRefuse(permissionRefusedText('module_ai', 1, 0));
}

$row = kbArticleRow($_GET['id'] ?? 0);
if (!$row) {
    exit(json_encode(['error' => 'That article no longer exists.']));
}
$kb_article_id = intval($row['kb_article_id']);
$can_edit = kbCan(2) && !$row['kb_article_archived_at'];
$reviewed_on_schedule = $row['kb_article_published_at'] && intval($row['kb_article_review_months']) > 0;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-clipboard-check me-2"></i>Review <strong><?= escapeHtml($row['kb_article_title']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-ai-review-form data-ai-review-url="/agent/ajax.php?ai_kb_article_review" data-ai-review-param="kb_article_id" data-ai-review-id="<?= $kb_article_id ?>"
    data-ai-review-secrets="Take them out - <?= $row['kb_article_visibility'] === 'agents' ? 'anyone on your team who can read it can read them' : 'its readers outside ITFlow can read them' ?>.">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="kb_article_id" value="<?= $kb_article_id ?>">
    <input type="hidden" name="summary" value="" data-ai-review-summary-value>
    <div class="modal-body">
        <div data-ai-review aria-live="polite">
            <div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>AI is reading it...</div>
        </div>
        <div class="d-none mt-3" data-ai-review-summary-box>
            <div class="small text-body-secondary mb-1">A summary for it</div>
            <div class="p-2 border rounded bg-body-tertiary" data-ai-review-summary></div>
            <?php if (trim((string) $row['kb_article_summary']) !== '') { ?>
                <div class="small text-body-secondary mt-1">Now: <?= escapeHtml($row['kb_article_summary']) ?></div>
            <?php } ?>
        </div>
        <div class="small text-body-secondary mt-3 d-none" data-ai-review-said><i class="fas fa-fw fa-robot me-1"></i>Reviewed by AI - a second pair of eyes, not the last word. Passwords and keys in it weren't sent.</div>
    </div>
    <div class="modal-footer">
        <?php if ($can_edit) { ?>
            <button type="submit" name="set_kb_article_summary" class="btn btn-primary fw-bold d-none" data-ai-review-use-summary><i class="fas fa-fw fa-align-left me-2"></i>Use as Its Summary</button>
            <?php if ($reviewed_on_schedule) { ?>
                <a class="btn btn-light border d-none" href="post.php?mark_kb_article_reviewed=<?= $kb_article_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-ai-review-mark><i class="fas fa-fw fa-check me-2"></i>Mark Reviewed</a>
            <?php } ?>
        <?php } ?>
        <button type="button" class="btn btn-light border" data-ai-review-again disabled><i class="fas fa-fw fa-redo me-2"></i>Again</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
    </div>
</form>

<script src="<?= staticFileUrl('/js/ai_review.js') ?>"></script>

<?php
require_once '../../../includes/modal_footer.php';
