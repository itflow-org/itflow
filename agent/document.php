<?php

/*
 * A document - read it, edit it, share or print it, see what it is linked to
 * and every earlier version of it. Laid out like the asset and contact pages:
 * a header card with the actions, the content beside a column of what goes
 * with it.
 *
 * Open to anyone with access to the client, like the files list; changing it
 * takes support write access, like the handlers behind it.
 */

require_once "includes/inc_all_client.php";

$document_id = intval($_GET['document_id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_archived_at, document_client_visible, document_content, document_created_at,
    document_description, document_folder_id, document_name, document_updated_at,
    creator.user_name AS created_by_name, updater.user_name AS updated_by_name
    FROM documents
    LEFT JOIN users creator ON document_created_by = creator.user_id
    LEFT JOIN users updater ON document_updated_by = updater.user_id
    WHERE document_client_id = $client_id AND document_id = $document_id
    LIMIT 1"));

if (!$row) {
    echo "<div class='text-center'><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='files.php?client_id=$client_id'><i class='fa fa-fw fa-arrow-left me-2'></i>Back to files</a></div>";
    require_once "../includes/footer.php";
    exit();
}

$can_edit = lookupUserPermission('module_support') >= 2;
$can_delete = lookupUserPermission('module_support') >= 3;

$document_name = escapeHtml($row['document_name']);
$document_description = escapeHtml($row['document_description']);
$document_archived = !empty($row['document_archived_at']);
$document_visible = intval($row['document_client_visible']);
$document_updated_at = $row['document_updated_at'] ?: $row['document_created_at'];
$document_updated_by = escapeHtml($row['updated_by_name'] ?: $row['created_by_name']);

$folder_tree = folderTree($client_id);
$document_folder_id = isset($folder_tree[intval($row['document_folder_id'])]) ? intval($row['document_folder_id']) : 0;
$folder_path = folderPath($folder_tree, $document_folder_id);

$versions = [];
$sql_versions = mysqli_query($mysqli, "SELECT document_version_id, document_version_name, document_version_description, document_version_created_at, user_name
    FROM document_versions LEFT JOIN users ON document_version_created_by = user_id
    WHERE document_version_document_id = $document_id
    ORDER BY document_version_created_at DESC, document_version_id DESC");
while ($version = mysqli_fetch_assoc($sql_versions)) {
    $versions[] = $version;
}

$csrf = $_SESSION['csrf_token'];

// Tab title - used raw, the header escapes it
$page_title = $row['document_name'];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></li>
    <li class="breadcrumb-item"><a href="files.php?client_id=<?= $client_id ?>">Files</a></li>
    <?php foreach ($folder_path as $crumb) { ?>
        <li class="breadcrumb-item"><a href="files.php?client_id=<?= $client_id ?>&folder_id=<?= $crumb['folder_id'] ?>"><?= escapeHtml($crumb['folder_name']) ?></a></li>
    <?php } ?>
    <li class="breadcrumb-item active"><?= $document_name ?></li>
</ol>

<!-- What it is, and what you can do with it -->
<div class="card mb-3 itflow-document-head">
    <div class="card-body d-flex flex-wrap align-items-start gap-3">
        <?= documentTile('lg') ?>
        <div class="min-w-0 flex-grow-1">
            <h1 class="h3 mb-1 d-flex flex-wrap align-items-center column-gap-2">
                <span class="text-break"><?= $document_name ?></span>
                <?php if ($document_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                <?php if ($document_visible) { ?><span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal fs-6" title="Technical contacts can read this in the client portal">Portal</span><?php } ?>
            </h1>
            <?php if ($document_description) { ?>
                <div class="text-body-secondary"><?= $document_description ?></div>
            <?php } ?>
            <div class="small text-body-secondary mt-1">
                <span title="<?= escapeHtml($document_updated_at) ?>">Updated <?= timeAgo($document_updated_at) ?></span><?= $document_updated_by ? " by $document_updated_by" : '' ?>
                &middot; Created <?= date('M j, Y', strtotime($row['document_created_at'])) ?><?= $row['created_by_name'] ? ' by ' . escapeHtml($row['created_by_name']) : '' ?>
                <?php if ($versions) { ?>&middot; <a class="text-reset" href="#documentHistory"><?= count($versions) ?> earlier version<?= count($versions) === 1 ? '' : 's' ?></a><?php } ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <?php if ($can_edit && !$document_archived) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_edit.php?id=<?= $document_id ?>">
                    <i class="fas fa-edit me-2"></i>Edit
                </button>
            <?php } ?>
            <?php if (!$document_archived && shareCanMake('Document')) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/share/share_add.php?type=Document&amp;id=<?= $document_id ?>" title="Share a link to it">
                    <i class="fas fa-share-alt me-2"></i>Share
                </button>
            <?php } ?>
            <a class="btn btn-outline-secondary" href="post.php?export_document=<?= $document_id ?>&csrf_token=<?= $csrf ?>" title="Export as PDF"><i class="fas fa-file-pdf"></i><span class="d-none d-md-inline ms-2">PDF</span></a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print();" title="Print"><i class="fas fa-print"></i></button>
            <?php if ($can_edit) { ?>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="dropdown" title="More"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <?php if (!$document_archived) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document/document_rename.php?id=<?= $document_id ?>"><i class="fas fa-fw fa-pen me-2"></i>Rename</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document/document_move.php?id=<?= $document_id ?>"><i class="fas fa-fw fa-folder-open me-2"></i>Move</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/link/link.php?from=document&id=<?= $document_id ?>"><i class="fas fa-fw fa-link me-2"></i>Link</a>
                            <?php if ($config_client_portal_enable) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document/document_edit_visibility.php?document_id=<?= $document_id ?>"><i class="fas fa-fw fa-eye<?= $document_visible ? '-slash' : '' ?> me-2"></i><?= $document_visible ? 'Hide from' : 'Show in' ?> client portal</a>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_document=<?= $document_id ?>&csrf_token=<?= $csrf ?>" data-confirm-text="Archiving unlinks it from everything it is linked to."><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                        <?php } else { ?>
                            <a class="dropdown-item" href="post.php?restore_document=<?= $document_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                        <?php } ?>
                        <?php if ($can_delete) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_document=<?= $document_id ?>&csrf_token=<?= $csrf ?>&from=document_details" data-confirm-text="The document and all its versions are deleted for good."><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row">

    <!-- The document -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body prettyContent itflow-document-body">
                <?= documentHtmlPurify($row['document_content']) ?: '<p class="text-body-secondary">This document is empty.' . ($can_edit && !$document_archived ? ' <a class="ajax-modal" href="#" data-modal-size="xl" data-modal-url="modals/document/document_edit.php?id=' . $document_id . '">Start writing</a>' : '') . '</p>' ?>
            </div>
        </div>

        <!-- On paper, the revision history goes at the end of the document -->
        <?php if ($versions) { ?>
            <div class="d-none d-print-block">
                <h4>Revision history</h4>
                <table class="table table-sm">
                    <thead><tr><th>Version</th><th>Date</th><th>Name</th><th>Summary</th><th>Author</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($versions) as $number => $version) { ?>
                        <tr>
                            <td><?= $number + 1 ?></td>
                            <td><?= date('Y-m-d', strtotime($version['document_version_created_at'])) ?></td>
                            <td><?= escapeHtml($version['document_version_name']) ?></td>
                            <td><?= escapeHtml($version['document_version_description']) ?: '-' ?></td>
                            <td><?= escapeHtml($version['user_name']) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>

    <div class="col-lg-4 d-print-none">

        <!-- What goes with it -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-link me-2 text-body-secondary"></i>Linked</h3>
                <?php if ($can_edit && !$document_archived) { ?>
                    <a class="small ajax-modal" href="#" data-modal-url="modals/link/link.php?from=document&id=<?= $document_id ?>"><i class="fas fa-plus me-1"></i>Link</a>
                <?php } ?>
            </div>
            <div class="card-body">
                <?= linkGroupsHtml('document', $document_id, $client_id, $document_archived) ?>
            </div>
        </div>

        <!-- Where it lives -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="itflow-glance-item" title="Folder">
                    <i class="fas fa-fw fa-folder"></i>
                    <span class="min-w-0 d-flex align-items-center">
                        <a class="text-truncate" href="files.php?client_id=<?= $client_id ?>&folder_id=<?= $document_folder_id ?>"><?= escapeHtml($folder_path ? implode(' / ', array_column($folder_path, 'folder_name')) : 'Top level') ?></a>
                        <?php if ($can_edit && !$document_archived) { ?>
                            <a class="itflow-copy ajax-modal" href="#" data-modal-url="modals/document/document_move.php?id=<?= $document_id ?>" title="Move"><i class="fas fa-pen"></i></a>
                        <?php } ?>
                    </span>
                </div>
                <?php if ($config_client_portal_enable) { ?>
                    <div class="itflow-glance-item" title="Client portal">
                        <i class="fas fa-fw fa-eye<?= $document_visible ? '' : '-slash' ?>"></i>
                        <span class="d-flex align-items-center">
                            <?= $document_visible ? 'Shown in the client portal' : 'Not in the client portal' ?>
                            <?php if ($can_edit && !$document_archived) { ?>
                                <a class="itflow-copy ajax-modal" href="#" data-modal-url="modals/document/document_edit_visibility.php?document_id=<?= $document_id ?>" title="Change"><i class="fas fa-pen"></i></a>
                            <?php } ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Every earlier version -->
        <div class="card mb-3" id="documentHistory">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-history me-2 text-body-secondary"></i>History</h3>
            </div>
            <ul class="list-group list-group-flush itflow-document-history">
                <li class="list-group-item">
                    <div class="fw-semibold small">Now</div>
                    <div class="small text-body-secondary">Updated <?= timeAgo($document_updated_at) ?><?= $document_updated_by ? " by $document_updated_by" : '' ?></div>
                </li>
                <?php foreach ($versions as $version) {
                    $version_id = intval($version['document_version_id']); ?>
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <div class="min-w-0 flex-grow-1">
                            <a class="small fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/document/document_version_view.php?id=<?= $version_id ?>" title="<?= escapeHtml($version['document_version_created_at']) ?>">
                                <?= date('M j, Y g:ia', strtotime($version['document_version_created_at'])) ?>
                            </a>
                            <div class="small text-body-secondary text-truncate">
                                <?= escapeHtml($version['user_name'] ?: 'Unknown') ?>
                                <?php if ($version['document_version_name'] !== $row['document_name']) { ?> &middot; was "<?= escapeHtml($version['document_version_name']) ?>"<?php } ?>
                            </div>
                        </div>
                        <?php if ($can_delete) { ?>
                            <a class="itflow-copy confirm-link text-danger" href="post.php?delete_document_version=<?= $version_id ?>&csrf_token=<?= $csrf ?>" title="Delete this version" data-confirm-text="This version is removed from the history for good."><i class="fas fa-trash-alt"></i></a>
                        <?php } ?>
                    </li>
                <?php } ?>
                <?php if (!$versions) { ?>
                    <li class="list-group-item small text-body-secondary">No earlier versions - each edit keeps the one before it here.</li>
                <?php } ?>
            </ul>
        </div>

    </div>

</div>

<script src="../js/pretty_content.js"></script>

<?php

require_once "../includes/footer.php";
