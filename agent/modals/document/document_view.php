<?php

/*
 * A document's quick look - its content, where it lives, and what it is linked
 * to, without leaving the page. Open goes to the full document page.
 *
 * Readable by anyone who can open the document page (client access).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$document_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_content, document_name, document_description, document_folder_id,
        document_client_visible, document_archived_at, document_created_at, document_updated_at, COALESCE(updater.user_name, creator.user_name) AS user_name
    FROM documents
    LEFT JOIN users creator ON document_created_by = creator.user_id
    LEFT JOIN users updater ON document_updated_by = updater.user_id
    WHERE document_id = $document_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

$can_edit = lookupUserPermission('module_support') >= 2;
$document_archived = !empty($row['document_archived_at']);
$folder_tree = folderTree($client_id);
$folder_id = isset($folder_tree[intval($row['document_folder_id'])]) ? intval($row['document_folder_id']) : 0;
$folder_label = $folder_id ? implode(' / ', array_column(folderPath($folder_tree, $folder_id), 'folder_name')) : 'Top level';

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= documentTile('lg') ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2">
                <span class="fw-semibold text-break"><?= escapeHtml($row['document_name']) ?></span>
                <?php if ($document_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                <?php if (intval($row['document_client_visible'])) { ?><span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal fs-6">Portal</span><?php } ?>
            </h5>
            <div class="small text-body-secondary mt-1">
                <?php if ($row['document_description']) { ?><?= escapeHtml($row['document_description']) ?> &middot; <?php } ?>
                Updated <?= timeAgo($row['document_updated_at'] ?: $row['document_created_at']) ?><?= $row['user_name'] ? ' by ' . escapeHtml($row['user_name']) : '' ?>
                &middot; <i class="fas fa-folder me-1"></i><?= escapeHtml($folder_label) ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="prettyContent itflow-document-quick-content"><?= documentHtmlPurify($row['document_content']) ?: '<p class="text-body-secondary mb-0">This document is empty.</p>' ?></div>

    <div class="mt-3">
        <div class="small fw-semibold text-uppercase text-body-secondary mb-2">Linked</div>
        <?= linkGroupsHtml('document', $document_id, $client_id, $document_archived) ?>
    </div>
</div>

<div class="modal-footer">
    <a href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>" class="btn btn-primary text-bold"><i class="fas fa-arrow-right me-2"></i>Open document</a>
    <?php if ($can_edit && !$document_archived) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_edit.php?id=<?= $document_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script src="../js/pretty_content.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
