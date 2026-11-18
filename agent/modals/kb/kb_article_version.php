<?php

/*
 * One saved version of a Knowledge Base article - what it said before an
 * edit - with a way to put it back. Restoring keeps what it says now as a
 * version too.
 */

require_once '../../../includes/modal_header.php';

$kb_closed = kbClosedText(1);
if ($kb_closed !== '') {
    exit(json_encode(['error' => $kb_closed]));
}

$kb_article_version_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_version_id, kb_article_version_title, kb_article_version_summary, kb_article_version_content,
        kb_article_version_created_at, kb_article_archived_at, user_name
    FROM kb_article_versions
    JOIN kb_articles ON kb_article_id = kb_article_version_kb_article_id
    LEFT JOIN users ON user_id = kb_article_version_created_by AND kb_article_version_created_by > 0
    WHERE kb_article_version_id = $kb_article_version_id LIMIT 1"));
if (!$row) {
    exit(json_encode(['error' => 'That version no longer exists.']));
}

$can_edit = kbCan(2) && empty($row['kb_article_archived_at']);
$can_delete = kbCan(3);

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <span class="itflow-file-icon itflow-file-icon-lg itflow-file-tone-text" aria-hidden="true"><i class="fas fa-fw fa-history"></i></span>
        <div class="min-w-0">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($row['kb_article_version_title']) ?></h5>
            <div class="small text-body-secondary mt-1">
                Version of <?= date('M j, Y g:ia', strtotime($row['kb_article_version_created_at'])) ?><?= $row['user_name'] ? ' by ' . escapeHtml($row['user_name']) : '' ?>
                <?php if ($row['kb_article_version_summary']) { ?> &middot; <?= escapeHtml($row['kb_article_version_summary']) ?><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="prettyContent itflow-document-quick-content"><?= documentHtmlPurify($row['kb_article_version_content']) ?: '<p class="text-body-secondary mb-0">This version was empty.</p>' ?></div>
</div>

<div class="modal-footer">
    <?php if ($can_delete) { ?>
        <a class="btn btn-outline-danger confirm-link me-auto" href="post.php?delete_kb_article_version=<?= $kb_article_version_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="This version is removed from the history for good.">
            <i class="fas fa-trash me-2"></i>Delete version
        </a>
    <?php } ?>
    <?php if ($can_edit) { ?>
        <form action="post.php" method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="kb_article_version_id" value="<?= $kb_article_version_id ?>">
            <button type="submit" name="restore_kb_article_version" class="btn btn-primary text-bold confirm-link" data-confirm-title="Put this version back?" data-confirm-text="What the article says now is kept in its versions." data-confirm-button="Restore">
                <i class="fas fa-undo me-2"></i>Restore this version
            </button>
        </form>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script src="<?= staticFileUrl('/js/pretty_content.js') ?>"></script>

<?php
require_once '../../../includes/modal_footer.php';
