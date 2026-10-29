<?php

/*
 * One saved version of a document - what it said before an edit - with a way
 * to put it back. Restoring keeps the current content as a version too.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$document_version_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_version_id, document_version_name, document_version_description, document_version_content,
        document_version_created_at, document_version_document_id, document_client_id, document_archived_at, user_name
    FROM document_versions
    JOIN documents ON document_id = document_version_document_id
    LEFT JOIN users ON document_version_created_by = user_id
    WHERE document_version_id = $document_version_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That version no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

$can_edit = lookupUserPermission('module_support') >= 2 && empty($row['document_archived_at']);
$can_delete = lookupUserPermission('module_support') >= 3;

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <span class="itflow-file-icon itflow-file-icon-lg itflow-file-tone-text" aria-hidden="true"><i class="fas fa-fw fa-history"></i></span>
        <div class="min-w-0">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($row['document_version_name']) ?></h5>
            <div class="small text-body-secondary mt-1">
                Version of <?= date('M j, Y g:ia', strtotime($row['document_version_created_at'])) ?><?= $row['user_name'] ? ' by ' . escapeHtml($row['user_name']) : '' ?>
                <?php if ($row['document_version_description']) { ?> &middot; <?= escapeHtml($row['document_version_description']) ?><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="prettyContent itflow-document-quick-content"><?= documentHtmlPurify($row['document_version_content']) ?: '<p class="text-body-secondary mb-0">This version was empty.</p>' ?></div>
</div>

<div class="modal-footer">
    <?php if ($can_delete) { ?>
        <a class="btn btn-outline-danger confirm-link me-auto" href="post.php?delete_document_version=<?= $document_version_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="This version is removed from the history for good.">
            <i class="fas fa-trash me-2"></i>Delete version
        </a>
    <?php } ?>
    <?php if ($can_edit) { ?>
        <form action="post.php" method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="document_version_id" value="<?= $document_version_id ?>">
            <button type="submit" name="restore_document_version" class="btn btn-primary text-bold confirm-link" data-confirm-title="Put this version back?" data-confirm-text="What the document says now is kept in its history." data-confirm-button="Restore">
                <i class="fas fa-undo me-2"></i>Restore this version
            </button>
        </form>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script src="../js/pretty_content.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
