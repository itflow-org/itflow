<?php

/*
 * Review a document with AI (aiDocumentReview(), functions/ai_document.php) -
 * whether it looks good, needs work or is out of date, what to fix or check
 * (the most important first), a one-line summary for it, and the passwords
 * and keys written in it, which belong in Credentials. Use as its summary
 * saves that line (rename_document); Mark reviewed is the document page's.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$document_id = intval($_GET['id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_description, document_client_id, document_archived_at FROM documents WHERE document_id = $document_id"));
if (!$row) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

// Using AI is a permission of its own
if (!aiCan()) {
    permissionRefuse(permissionRefusedText('module_ai', 1, 0));
}

$can_edit = lookupUserPermission('module_support') >= 2 && !$row['document_archived_at'];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-clipboard-check me-2"></i>Review <strong><?= escapeHtml($row['document_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-ai-review-form data-ai-review-url="/agent/ajax.php?ai_document_review" data-ai-review-param="document_id" data-ai-review-id="<?= $document_id ?>"
    data-ai-review-secrets="Keep them in Credentials, and link the credential to this document instead - anyone who can read it can read them.">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <input type="hidden" name="name" value="<?= escapeHtml($row['document_name']) ?>">
    <input type="hidden" name="description" value="" data-ai-review-summary-value>
    <div class="modal-body">
        <div data-ai-review aria-live="polite">
            <div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>AI is reading it...</div>
        </div>
        <div class="d-none mt-3" data-ai-review-summary-box>
            <div class="small text-body-secondary mb-1">A summary for it</div>
            <div class="p-2 border rounded bg-body-tertiary" data-ai-review-summary></div>
            <?php if (trim((string) $row['document_description']) !== '') { ?>
                <div class="small text-body-secondary mt-1">Now: <?= escapeHtml($row['document_description']) ?></div>
            <?php } ?>
        </div>
        <div class="small text-body-secondary mt-3 d-none" data-ai-review-said><i class="fas fa-fw fa-robot me-1"></i>Reviewed by AI - a second pair of eyes, not the last word. Passwords and keys in it weren't sent.</div>
    </div>
    <div class="modal-footer">
        <?php if ($can_edit) { ?>
            <button type="submit" name="rename_document" class="btn btn-primary fw-bold d-none" data-ai-review-use-summary><i class="fas fa-fw fa-align-left me-2"></i>Use as Its Summary</button>
            <a class="btn btn-light border d-none" href="post.php?mark_document_reviewed=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-ai-review-mark><i class="fas fa-fw fa-check me-2"></i>Mark Reviewed</a>
        <?php } ?>
        <button type="button" class="btn btn-light border" data-ai-review-again disabled><i class="fas fa-fw fa-redo me-2"></i>Again</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
    </div>
</form>

<script src="<?= staticFileUrl('/js/ai_review.js') ?>"></script>

<?php
require_once '../../../includes/modal_footer.php';
