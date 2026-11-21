<?php

/*
 * What one edit of a document changed (functions/text_diff.php): a version - or, with no
 * version_id, the document as it is now - against the version before it. Lines taken out, put in
 * and changed, with the changed words marked; the rest folded away.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$document_id = intval($_GET['document_id'] ?? 0);
$version_id = intval($_GET['version_id'] ?? 0);

$document = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_name, document_content, document_created_at, document_updated_at,
        COALESCE(updater.user_name, creator.user_name) AS user_name
    FROM documents
    LEFT JOIN users creator ON document_created_by = creator.user_id
    LEFT JOIN users updater ON document_updated_by = updater.user_id
    WHERE document_id = $document_id LIMIT 1"));
if (!$document) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($document['document_client_id']);
enforceClientAccess();

$version_sql = "SELECT document_version_id AS id, document_version_name AS name, document_version_content AS content, document_version_created_at AS at, user_name
    FROM document_versions LEFT JOIN users ON document_version_created_by = user_id
    WHERE document_version_document_id = $document_id";

if ($version_id) {
    $after = mysqli_fetch_assoc(mysqli_query($mysqli, "$version_sql AND document_version_id = $version_id LIMIT 1"));
    if (!$after) {
        echo json_encode(['error' => 'That version no longer exists.']);
        exit;
    }
    $at = escapeSql($after['at']);
    $before = mysqli_fetch_assoc(mysqli_query($mysqli, "$version_sql AND (document_version_created_at < '$at' OR (document_version_created_at = '$at' AND document_version_id < $version_id))
        ORDER BY document_version_created_at DESC, document_version_id DESC LIMIT 1"));
} else {
    $after = ['id' => 0, 'name' => $document['document_name'], 'content' => $document['document_content'],
        'at' => $document['document_updated_at'] ?: $document['document_created_at'], 'user_name' => $document['user_name']];
    $before = mysqli_fetch_assoc(mysqli_query($mysqli, "$version_sql ORDER BY document_version_created_at DESC, document_version_id DESC LIMIT 1"));
}

$changes = $before ? textDiffHtml($before['content'], $after['content']) : '';

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <span class="itflow-file-icon itflow-file-icon-lg itflow-file-tone-text" aria-hidden="true"><i class="fas fa-fw fa-exchange-alt"></i></span>
        <div class="min-w-0">
            <h5 class="modal-title fw-semibold text-break">What changed in <?= escapeHtml($document['document_name']) ?></h5>
            <div class="small text-body-secondary mt-1">
                <?= $version_id ? 'The version' : 'Now - the edit' ?> of <?= date('M j, Y g:i A', strtotime($after['at'])) ?><?= $after['user_name'] ? ' by ' . escapeHtml($after['user_name']) : '' ?>,
                <?= $before ? 'against the one before it, ' . date('M j, Y g:i A', strtotime($before['at'])) : 'the first' ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <?php if (!$before) { ?>
        <p class="text-body-secondary mb-0">This is how the document started - there's nothing earlier to compare it with.</p>
    <?php } else { ?>
        <?php if ($before['name'] !== $after['name']) { ?>
            <p class="mb-2"><i class="fas fa-pen me-1 text-body-secondary"></i>Renamed from <del><?= escapeHtml($before['name']) ?></del> to <ins><?= escapeHtml($after['name']) ?></ins></p>
        <?php } ?>
        <?php if ($changes !== '') { ?>
            <div class="small text-body-secondary mb-2">
                <span class="itflow-diff-key is-del">Taken out</span> <span class="itflow-diff-key is-add">Put in</span> - formatting changes alone aren't shown.
            </div>
            <div class="itflow-diff"><?= $changes ?></div>
        <?php } else { ?>
            <p class="text-body-secondary mb-0">The words are the same - only the formatting or a picture changed.</p>
        <?php } ?>
    <?php } ?>
</div>

<div class="modal-footer">
    <?php if ($version_id) { ?>
        <a class="btn btn-outline-secondary me-auto ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/document/document_version_view.php?id=<?= $version_id ?>"><i class="fas fa-history me-2"></i>See this version</a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
