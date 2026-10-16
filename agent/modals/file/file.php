<?php

/*
 * A file's quick look - what it is, a preview where the browser can show one,
 * where it lives, who uploaded it, and everything it is linked to - opened
 * from the files list, the grid's previewer, and anywhere a linked file is
 * listed (a document, an asset, a contact).
 *
 * Readable by anyone who can open the file itself (agent/file.php asks for
 * client access, not support), changed by support write access.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$file_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_id, file_name, file_description, file_reference_name, file_ext, file_size,
        file_mime_type, file_created_at, file_updated_at, file_archived_at, file_folder_id, file_client_id, user_name
    FROM files LEFT JOIN users ON file_created_by = user_id
    WHERE file_id = $file_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That file no longer exists.']);
    exit;
}

$client_id = intval($row['file_client_id']);
enforceClientAccess();

$can_edit = lookupUserPermission('module_support') >= 2;
$file_name = escapeHtml($row['file_name']);
$file_archived = !empty($row['file_archived_at']);
$file_info = fileTypeInfo($row['file_ext']);
$file_mime = $row['file_mime_type'];

// What the preview can show - the same rule agent/file.php serves inline by
if (!in_array($file_mime, getInlineViewableMimeTypes(), true)) {
    $preview_kind = '';
} elseif ($file_mime === 'application/pdf') {
    $preview_kind = 'pdf';
} elseif ($file_mime === 'text/plain' || $file_mime === 'application/json') {
    $preview_kind = 'text';
} else {
    $preview_kind = 'image';
}

$folder_tree = folderTree($client_id);
$folder_id = isset($folder_tree[intval($row['file_folder_id'])]) ? intval($row['file_folder_id']) : 0;
$folder_label = $folder_id ? implode(' / ', array_column(folderPath($folder_tree, $folder_id), 'folder_name')) : 'Top level';

$share = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT item_recipient, item_expire_at FROM shared_items
    WHERE item_client_id = $client_id AND item_type = 'File' AND item_related_id = $file_id AND " . shareOpenSql() . "
    ORDER BY item_expire_at DESC LIMIT 1"));

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= fileTypeTile($row['file_ext'], 'lg') ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2">
                <span class="fw-semibold text-break"><?= $file_name ?></span>
                <?php if ($file_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <div class="small text-body-secondary mt-1"><?= escapeHtml($file_info['label']) ?> &middot; <?= formatBytes(intval($row['file_size'])) ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <!-- Preview -->
    <div class="itflow-file-preview mb-3">
        <?php if ($preview_kind === 'image') { ?>
            <a href="file.php?file_id=<?= $file_id ?>&action=view" target="_blank" title="Open full size"><img src="file.php?file_id=<?= $file_id ?>&thumb=1" alt="<?= $file_name ?>"></a>
        <?php } elseif ($preview_kind === 'pdf') { ?>
            <iframe src="file.php?file_id=<?= $file_id ?>&action=view#view=FitH" title="<?= $file_name ?>"></iframe>
        <?php } elseif ($preview_kind === 'text') { ?>
            <pre><?= escapeHtml(getFileTextExcerpt($client_id, $row['file_reference_name'], 2000)) ?></pre>
        <?php } else { ?>
            <div class="itflow-asset-empty py-4">
                <?= fileTypeTile($row['file_ext'], 'lg') ?>
                <div class="mt-2">No preview for this kind of file - download it to open it.</div>
            </div>
        <?php } ?>
    </div>

    <!-- At a glance -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Folder">
                <i class="fas fa-fw fa-folder"></i>
                <a class="text-truncate" href="files.php?client_id=<?= $client_id ?>&folder_id=<?= $folder_id ?>"><?= escapeHtml($folder_label) ?></a>
            </div>
            <div class="itflow-glance-item" title="Uploaded">
                <i class="fas fa-fw fa-cloud-upload-alt"></i>
                <span><span title="<?= escapeHtml($row['file_created_at']) ?>"><?= timeAgo($row['file_created_at']) ?></span><?php if ($row['user_name']) { ?> <span class="text-body-secondary">by <?= escapeHtml($row['user_name']) ?></span><?php } ?></span>
            </div>
        </div>
        <div class="col-sm-6">
            <?php if ($row['file_description']) { ?>
                <div class="itflow-glance-item" title="Description">
                    <i class="fas fa-fw fa-align-left"></i>
                    <span><?= escapeHtml($row['file_description']) ?></span>
                </div>
            <?php } ?>
            <?php if ($share) { ?>
                <div class="itflow-glance-item" title="Shared">
                    <i class="fas fa-fw fa-link"></i>
                    <span>Shared<?= $share['item_recipient'] ? ' with ' . escapeHtml($share['item_recipient']) : '' ?> <span class="text-body-secondary">&middot; link expires <?= timeAgo($share['item_expire_at']) ?></span></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- What it is linked to -->
    <div class="mt-3">
        <div class="small fw-semibold text-uppercase text-body-secondary mb-2">Linked</div>
        <?= linkGroupsHtml('file', $file_id, $client_id, $file_archived) ?>
    </div>

</div>

<div class="modal-footer">
    <a class="btn btn-primary text-bold" href="file.php?file_id=<?= $file_id ?>"><i class="fas fa-download me-2"></i>Download</a>
    <?php if (!$file_archived && shareCanMake('File')) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/share/share_add.php?type=File&amp;id=<?= $file_id ?>"><i class="fas fa-share-alt me-2"></i>Share</button>
    <?php } ?>
    <?php if ($can_edit && !$file_archived) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/file/file_rename.php?id=<?= $file_id ?>"><i class="fas fa-pen me-2"></i>Rename</button>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/file/file_move.php?id=<?= $file_id ?>"><i class="fas fa-folder-open me-2"></i>Move</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
