<?php

/*
 * Files - one client's uploaded files and created documents, in their folders.
 *
 * The folder tree runs down the left (collapses above the list on a phone),
 * the current folder's path across the top, and the list - or a grid of
 * previews - in the middle. Uploaded files open their quick look (preview,
 * details, what they are linked to); documents open their page. Files can be
 * dropped straight onto the list to upload them into the folder on show.
 *
 * Documents can be tagged; filtering by a tag shows the documents with it -
 * from every folder at the top level, like a search.
 *
 * Open to anyone with access to the client (contracts and the like live here),
 * changes need support write access, like the handlers behind them.
 */

// Default sort - a logical field, not a column: files and documents are two tables
$sort = "name";
$order = "ASC";

require_once "includes/inc_all_client.php";

$can_edit = lookupUserPermission('module_support') >= 2;
$can_delete = lookupUserPermission('module_support') >= 3;

$folder_tree = folderTree($client_id);

// The folder on show - one of this client's, or the top level
$folder_id = intval($_GET['folder_id'] ?? 0);
if (!isset($folder_tree[$folder_id])) {
    $folder_id = 0;
}
$folder_path = folderPath($folder_tree, $folder_id);
$folder_name = $folder_id ? escapeHtml($folder_tree[$folder_id]['folder_name']) : 'Top level';

// '' everything, 'file' uploads only, 'document' created documents only
$type_filter = $_GET['type'] ?? '';
if (!in_array($type_filter, ['file', 'document'], true)) {
    $type_filter = '';
}

// 0 list, 1 grid
$view = intval($_GET['view'] ?? 0) === 1 ? 1 : 0;

// Whitelisted - anything else sorts by name
$sort = in_array($_GET['sort'] ?? '', ['name', 'type', 'size', 'updated'], true) ? $_GET['sort'] : 'name';
$order = ($_GET['order'] ?? '') === 'DESC' ? 'DESC' : 'ASC';

// A search at the top level looks in every folder; inside a folder, just that one
$search = trim((string) ($_GET['q'] ?? ''));
$search_everywhere = $search !== '' && $folder_id === 0;

// Tags - documents with any of them, and no uploads, which have none. At the
// top level that is in every folder, like a search
$tag_filter_ids = tagFilterIds();
$look_everywhere = $search_everywhere || ($tag_filter_ids && $folder_id === 0);

/** This page's URL with some parameters changed - the rest (folder, type, view, search, archived) ride along. */
function filesUrl($changes = []) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || ($key !== 'client_id' && $value === 0)) {
            unset($params[$key]);
        }
    }
    return '?' . http_build_query($params);
}

// ---- Folder counts: one grouped query each, not one per folder ----
$folder_counts = [];
foreach ([['files', 'file'], ['documents', 'document']] as [$table, $prefix]) {
    $sql = mysqli_query($mysqli, "SELECT {$prefix}_folder_id AS folder_id, COUNT(*) AS num FROM $table
        WHERE {$prefix}_client_id = $client_id AND {$prefix}_$archive_query GROUP BY {$prefix}_folder_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $count_folder = isset($folder_tree[intval($row['folder_id'])]) ? intval($row['folder_id']) : 0;
        $folder_counts[$count_folder] = ($folder_counts[$count_folder] ?? 0) + intval($row['num']);
    }
}

// ---- What is on show: files and documents, merged and sorted here ----
$search_sql = escapeSql($search);
$file_where = "file_client_id = $client_id AND file_$archive_query" . ($look_everywhere ? '' : " AND file_folder_id = $folder_id") . ($tag_filter_ids ? ' AND 0' : '');
$document_where = "document_client_id = $client_id AND document_$archive_query" . ($look_everywhere ? '' : " AND document_folder_id = $folder_id")
    . ' ' . tagFilterSql('document', $tag_filter_ids, 'documents.document_id');
if ($search !== '') {
    $file_where .= " AND (file_name LIKE '%$search_sql%' OR file_ext LIKE '%$search_sql%' OR file_description LIKE '%$search_sql%')";
    $document_where .= " AND (MATCH(document_content_raw) AGAINST ('$search_sql') OR document_name LIKE '%$search_sql%' OR document_description LIKE '%$search_sql%'
        " . tagSearchSql('document', $search_sql, 'documents.document_id') . ")";
}

$items = [];
$type_counts = ['file' => 0, 'document' => 0];

$sql_files = mysqli_query($mysqli, "SELECT file_id, file_name, file_description, file_reference_name, file_ext, file_size, file_mime_type,
        file_created_at, file_updated_at, file_archived_at, file_folder_id, user_name
    FROM files LEFT JOIN users ON file_created_by = user_id
    WHERE $file_where");
while ($row = mysqli_fetch_assoc($sql_files)) {
    $type_counts['file']++;
    if ($type_filter === 'document') {
        continue;
    }
    $info = fileTypeInfo($row['file_ext']);
    $items[] = [
        'kind' => 'file',
        'id' => intval($row['file_id']),
        'name' => $row['file_name'],
        'description' => $row['file_description'],
        'type' => $info['label'],
        'size' => intval($row['file_size']),
        'updated' => $row['file_updated_at'] ?: $row['file_created_at'],
        'by' => $row['user_name'],
        'folder_id' => intval($row['file_folder_id']),
        'archived' => $row['file_archived_at'],
        'row' => $row,
    ];
}

$sql_documents = mysqli_query($mysqli, "SELECT document_id, document_name, document_description, document_client_visible,
        document_created_at, document_updated_at, document_archived_at, document_folder_id,
        " . ($view === 1 ? 'document_content,' : '') . " COALESCE(updater.user_name, creator.user_name) AS user_name
    FROM documents
    LEFT JOIN users creator ON document_created_by = creator.user_id
    LEFT JOIN users updater ON document_updated_by = updater.user_id
    WHERE $document_where");
while ($row = mysqli_fetch_assoc($sql_documents)) {
    $type_counts['document']++;
    if ($type_filter === 'file') {
        continue;
    }
    $items[] = [
        'kind' => 'document',
        'id' => intval($row['document_id']),
        'name' => $row['document_name'],
        'description' => $row['document_description'],
        'type' => 'Document',
        'size' => -1,
        'updated' => $row['document_updated_at'] ?: $row['document_created_at'],
        'by' => $row['user_name'],
        'folder_id' => intval($row['document_folder_id']),
        'archived' => $row['document_archived_at'],
        'row' => $row,
    ];
}

usort($items, function ($a, $b) use ($sort, $order) {
    if ($sort === 'type') {
        $result = strcasecmp($a['type'], $b['type']);
    } elseif ($sort === 'size') {
        $result = $a['size'] <=> $b['size'];
    } elseif ($sort === 'updated') {
        $result = strcmp((string) $a['updated'], (string) $b['updated']);
    } else {
        $result = 0;
    }
    // Name breaks every tie, so the order never shuffles between page loads
    $result = $result ?: strnatcasecmp($a['name'], $b['name']);
    return $order === 'DESC' ? -$result : $result;
});

$num_rows = [count($items)];
$items = array_slice($items, $record_from, $record_to);

// ---- For the page on show only: shares and link counts, batched ----
$page_ids = ['file' => [], 'document' => []];
foreach ($items as $item) {
    $page_ids[$item['kind']][] = $item['id'];
}
$shared = [];
foreach (['file' => 'File', 'document' => 'Document'] as $kind => $share_type) {
    if (!$page_ids[$kind]) {
        continue;
    }
    $sql = mysqli_query($mysqli, "SELECT item_related_id, item_recipient, item_expire_at FROM shared_items
        WHERE item_client_id = $client_id AND item_type = '$share_type' AND " . shareOpenSql() . "
        AND item_related_id IN (" . implode(',', $page_ids[$kind]) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $shared[$kind][intval($row['item_related_id'])] = $row;
    }
}
$documents_tags = tagsForRecords('document', $page_ids['document']);
$link_counts = [];
foreach (['file', 'document'] as $kind) {
    if (!$page_ids[$kind]) {
        continue;
    }
    $ids = implode(',', $page_ids[$kind]);
    foreach (linkTypesFor($kind) as $to) {
        $relation = linkRelation($kind, $to);
        $to_entity = LINK_ENTITIES[$to];
        $sql = mysqli_query($mysqli, "SELECT l.{$kind}_id AS id, COUNT(*) AS num FROM {$relation['table']} l
            JOIN {$to_entity['table']} t ON t.{$to_entity['id']} = l.{$to_entity['id']} AND t.{$to_entity['archived']} IS NULL
            WHERE l.{$kind}_id IN ($ids) GROUP BY l.{$kind}_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $link_counts[$kind][intval($row['id'])][$to] = intval($row['num']);
        }
    }
}

/** The linked-record chips for one row: an icon and a count per kind. */
function filesLinkChips($kind, $id) {
    global $link_counts;
    $html = '';
    foreach ($link_counts[$kind][$id] ?? [] as $to => $num) {
        $entity = LINK_ENTITIES[$to];
        $html .= '<span class="itflow-contact-chip" title="' . $num . ' ' . ($num === 1 ? $entity['label'] : strtolower($entity['plural'])) . '"><i class="fas ' . $entity['icon'] . '"></i>' . $num . '</span>';
    }
    return $html;
}

/** The ⋮ menu for one file or document - the same in the list and the grid. */
function filesItemMenu($item) {
    global $client_id, $can_edit, $can_delete;
    $id = $item['id'];
    $csrf = $_SESSION['csrf_token'];
    $archived = !empty($item['archived']);
    $html = '';
    if ($item['kind'] === 'file') {
        $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/file/file.php?id=$id\"><i class=\"fas fa-fw fa-info-circle me-2\"></i>Details</a>";
        $html .= "<a class=\"dropdown-item\" href=\"file.php?file_id=$id\"><i class=\"fas fa-fw fa-download me-2\"></i>Download</a>";
        if (!$archived && shareCanMake('File')) {
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/share/share_add.php?type=File&amp;id=$id\"><i class=\"fas fa-fw fa-share-alt me-2\"></i>Share</a>";
        }
        if ($can_edit && !$archived) {
            $html .= '<div class="dropdown-divider"></div>';
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/file/file_rename.php?id=$id\"><i class=\"fas fa-fw fa-pen me-2\"></i>Rename</a>";
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/file/file_move.php?id=$id\"><i class=\"fas fa-fw fa-folder-open me-2\"></i>Move</a>";
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/link/link.php?from=file&amp;id=$id\"><i class=\"fas fa-fw fa-link me-2\"></i>Link</a>";
        }
        $archive_name = 'file';
    } else {
        $html .= "<a class=\"dropdown-item\" href=\"document.php?client_id=$client_id&amp;document_id=$id\"><i class=\"fas fa-fw fa-arrow-right me-2\"></i>Open</a>";
        $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/document/document_view.php?id=$id\"><i class=\"fas fa-fw fa-eye me-2\"></i>Quick view</a>";
        if (!$archived && shareCanMake('Document')) {
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/share/share_add.php?type=Document&amp;id=$id\"><i class=\"fas fa-fw fa-share-alt me-2\"></i>Share</a>";
        }
        if ($can_edit && !$archived) {
            $html .= '<div class="dropdown-divider"></div>';
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-size=\"xl\" data-modal-url=\"modals/document/document_edit.php?id=$id\"><i class=\"fas fa-fw fa-edit me-2\"></i>Edit</a>";
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/document/document_rename.php?id=$id\"><i class=\"fas fa-fw fa-pen me-2\"></i>Rename</a>";
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/document/document_move.php?id=$id\"><i class=\"fas fa-fw fa-folder-open me-2\"></i>Move</a>";
            $html .= "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/link/link.php?from=document&amp;id=$id\"><i class=\"fas fa-fw fa-link me-2\"></i>Link</a>";
        }
        $archive_name = 'document';
    }
    if ($can_edit) {
        $html .= '<div class="dropdown-divider"></div>';
        if ($archived) {
            $html .= "<a class=\"dropdown-item\" href=\"post.php?restore_$archive_name=$id&amp;csrf_token=$csrf\"><i class=\"fas fa-fw fa-redo me-2\"></i>Restore</a>";
            if ($can_delete) {
                $html .= $item['kind'] === 'file'
                    ? "<a class=\"dropdown-item text-danger ajax-modal\" href=\"#\" data-modal-url=\"modals/file/file_delete.php?id=$id\"><i class=\"fas fa-fw fa-trash me-2\"></i>Delete</a>"
                    : "<a class=\"dropdown-item text-danger confirm-link\" href=\"post.php?delete_document=$id&amp;csrf_token=$csrf\"><i class=\"fas fa-fw fa-trash me-2\"></i>Delete</a>";
            }
        } else {
            $html .= "<a class=\"dropdown-item text-danger confirm-link\" href=\"post.php?archive_$archive_name=$id&amp;csrf_token=$csrf\"><i class=\"fas fa-fw fa-archive me-2\"></i>Archive</a>";
        }
    }
    return $html;
}

/** "in Folder / Sub" for a search result outside the folder on show. */
function filesFolderNote($item) {
    global $folder_tree, $folder_id, $client_id;
    if ($item['folder_id'] === $folder_id || !isset($folder_tree[$item['folder_id']])) {
        return '';
    }
    $path = array_column(folderPath($folder_tree, $item['folder_id']), 'folder_name');
    return '<a class="itflow-above-stretch small text-body-secondary text-decoration-none" href="' . escapeHtml(filesUrl(['folder_id' => $item['folder_id'], 'q' => ''])) . '"><i class="fas fa-folder me-1"></i>' . escapeHtml($path ? implode(' / ', $path) : 'Top level') . '</a>';
}

/** One folder and its subfolders in the tree. */
function filesFolderTree($parent_id, $depth = 0) {
    global $folder_tree, $folder_id, $folder_counts, $folder_path, $can_edit, $can_delete, $client_id;
    $open_path = array_column($folder_path, 'folder_id');
    $html = '';
    foreach ($folder_tree[$parent_id]['children'] as $id) {
        $folder = $folder_tree[$id];
        $has_children = (bool) $folder['children'];
        $is_open = in_array($id, $open_path, true);
        $count = $folder_counts[$id] ?? 0;
        $name = escapeHtml($folder['folder_name']);
        $menu = '';
        if ($can_edit) {
            $menu = '<div class="dropdown itflow-folder-menu"><button type="button" class="btn btn-sm" data-bs-toggle="dropdown" title="Folder actions"><i class="fas fa-ellipsis-v"></i></button><div class="dropdown-menu dropdown-menu-end">'
                . "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/folder/folder_add.php?client_id=$client_id&amp;current_folder_id=$id\"><i class=\"fas fa-fw fa-folder-plus me-2\"></i>New folder inside</a>"
                . "<a class=\"dropdown-item ajax-modal\" href=\"#\" data-modal-url=\"modals/folder/folder_rename.php?id=$id\"><i class=\"fas fa-fw fa-pen me-2\"></i>Rename or move</a>"
                . ($can_delete ? "<div class=\"dropdown-divider\"></div><a class=\"dropdown-item text-danger ajax-modal\" href=\"#\" data-modal-url=\"modals/folder/folder_delete.php?id=$id\"><i class=\"fas fa-fw fa-trash me-2\"></i>Delete</a>" : '')
                . '</div></div>';
        }
        $html .= '<li class="itflow-folder' . ($is_open ? ' is-open' : '') . '" data-folder-id="' . $id . '">'
            . '<div class="itflow-folder-row' . ($id === $folder_id ? ' active' : '') . '" style="--itflow-folder-depth: ' . $depth . '">'
            . ($has_children ? '<button type="button" class="itflow-folder-toggle" data-folder-toggle aria-label="Show or hide subfolders"><i class="fas fa-chevron-right"></i></button>' : '<span class="itflow-folder-toggle"></span>')
            . '<a class="itflow-folder-link" href="' . escapeHtml(filesUrl(['folder_id' => $id, 'q' => ''])) . '"><i class="fas fa-fw ' . ($id === $folder_id || $is_open ? 'fa-folder-open' : 'fa-folder') . ' me-2"></i><span class="text-truncate">' . $name . '</span>'
            . ($count ? '<span class="badge rounded-pill ms-auto">' . $count . '</span>' : '') . '</a>'
            . $menu . '</div>'
            . ($has_children ? '<ul class="list-unstyled mb-0">' . filesFolderTree($id, $depth + 1) . '</ul>' : '')
            . '</li>';
    }
    return $html;
}

$empty_state_skip = true;
$upload_url = "modals/file/file_upload.php?client_id=$client_id&folder_id=$folder_id";
$search_label = $look_everywhere || $folder_id === 0 ? 'all folders' : $folder_name;
$preview = []; // the grid's previewer payload

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-folder me-2"></i>Files</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $upload_url ?>">
                        <i class="fas fa-cloud-upload-alt me-2"></i>Upload
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More ways to add"></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&folder_id=<?= $folder_id ?>">
                            <i class="fas fa-fw fa-file-alt me-2"></i>New document
                        </a>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document/document_add_from_template.php?client_id=<?= $client_id ?>&folder_id=<?= $folder_id ?>">
                            <i class="fas fa-fw fa-puzzle-piece me-2"></i>Document from template
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/folder/folder_add.php?client_id=<?= $client_id ?>&current_folder_id=<?= $folder_id ?>">
                            <i class="fas fa-fw fa-folder-plus me-2"></i>New folder
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Documents, uploads or both -->
    <div class="card-header py-2 itflow-asset-tabs">
        <nav class="nav nav-pills flex-nowrap overflow-auto">
            <a class="nav-link text-nowrap <?= $type_filter === '' ? 'active' : '' ?>" href="<?= escapeHtml(filesUrl(['type' => ''])) ?>">
                All <span class="badge rounded-pill ms-1"><?= $type_counts['file'] + $type_counts['document'] ?></span>
            </a>
            <a class="nav-link text-nowrap <?= $type_filter === 'document' ? 'active' : '' ?>" href="<?= escapeHtml(filesUrl(['type' => 'document'])) ?>">
                <i class="fas fa-fw fa-file-alt me-1"></i>Documents <span class="badge rounded-pill ms-1"><?= $type_counts['document'] ?></span>
            </a>
            <a class="nav-link text-nowrap <?= $type_filter === 'file' ? 'active' : '' ?>" href="<?= escapeHtml(filesUrl(['type' => 'file'])) ?>">
                <i class="fas fa-fw fa-paperclip me-1"></i>Uploads <span class="badge rounded-pill ms-1"><?= $type_counts['file'] ?></span>
            </a>
        </nav>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <input type="hidden" name="folder_id" value="<?= $folder_id ?>">
            <input type="hidden" name="type" value="<?= $type_filter ?>">
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml($search) ?>" placeholder="Search <?= $search_label === 'all folders' ? 'all folders' : 'in ' . $search_label ?> - names and document text">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="- Document tags -" multiple>
                        <?= tagFilterOptionsHtml('document', $tag_filter_ids, "document_client_id = $client_id AND document_$archive_query") ?>
                    </select>
                </div>
                <div class="col-md-auto ms-md-auto d-flex flex-wrap gap-2">
                    <div class="btn-group" role="group" aria-label="View">
                        <a href="<?= escapeHtml(filesUrl(['view' => 0])) ?>" class="btn <?= $view === 0 ? 'btn-dark' : 'btn-outline-dark' ?>" title="List"><i class="fas fa-list-ul"></i></a>
                        <a href="<?= escapeHtml(filesUrl(['view' => 1])) ?>" class="btn <?= $view === 1 ? 'btn-dark' : 'btn-outline-dark' ?>" title="Grid with previews"><i class="fas fa-th-large"></i></a>
                    </div>
                    <a href="<?= escapeHtml(filesUrl(['archived' => $archived ? 0 : 1])) ?>" class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Showing archived - back to current' : 'Show archived' ?>">
                        <i class="fa fa-fw fa-archive"></i><span class="d-none d-xl-inline ms-2">Archived</span>
                    </a>
                    <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php if (!$archived) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-bulk="true"
                                        data-modal-url="modals/file/file_bulk_move.php?client_id=<?= $client_id ?>&current_folder_id=<?= $folder_id ?>">
                                        <i class="fas fa-fw fa-folder-open me-2"></i>Move
                                    </a>
                                    <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="document" data-tag-bulk="document_ids[]" data-csrf="<?= $_SESSION['csrf_token'] ?>" title="The documents ticked - uploads don't take tags">
                                        <i class="fas fa-fw fa-tags me-2"></i>Tags
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_files">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </button>
                                <?php } else { ?>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_files">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_delete_files">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete for good
                                        </button>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-0 itflow-files">

        <!-- Folders - a button on a phone, a column beside the list from lg up -->
        <div class="col-lg-3 border-end itflow-files-tree">
            <div class="p-2 d-lg-none">
                <button class="btn btn-outline-secondary btn-sm w-100 text-truncate" type="button" data-bs-toggle="collapse" data-bs-target="#filesFolderTree" aria-expanded="false">
                    <i class="fas fa-folder-open me-2"></i>Folders<span class="text-body-secondary ms-1">&middot; <?= $folder_name ?></span>
                </button>
            </div>
            <div class="collapse d-lg-block" id="filesFolderTree">
                <div class="d-flex align-items-center px-3 pt-3 pb-1">
                    <span class="small fw-semibold text-uppercase text-body-secondary me-auto">Folders</span>
                    <button type="button" class="btn btn-sm btn-link text-body-secondary p-0 me-2" data-folder-expand-all title="Open every folder"><i class="fas fa-angle-double-down"></i></button>
                    <button type="button" class="btn btn-sm btn-link text-body-secondary p-0 me-2" data-folder-collapse-all title="Close every folder"><i class="fas fa-angle-double-up"></i></button>
                    <?php if ($can_edit) { ?>
                        <button type="button" class="btn btn-sm btn-link text-body-secondary p-0 ajax-modal" data-modal-url="modals/folder/folder_add.php?client_id=<?= $client_id ?>&current_folder_id=<?= $folder_id ?>" title="New folder in <?= $folder_name ?>"><i class="fas fa-folder-plus"></i></button>
                    <?php } ?>
                </div>
                <ul class="list-unstyled mb-0 px-2 pb-3 itflow-folder-tree" data-client-id="<?= $client_id ?>">
                    <li class="itflow-folder is-open">
                        <div class="itflow-folder-row<?= $folder_id === 0 ? ' active' : '' ?>" style="--itflow-folder-depth: 0">
                            <span class="itflow-folder-toggle"></span>
                            <a class="itflow-folder-link" href="<?= escapeHtml(filesUrl(['folder_id' => 0, 'q' => ''])) ?>">
                                <i class="fas fa-fw fa-home me-2"></i><span class="text-truncate">Top level</span>
                                <?php if (!empty($folder_counts[0])) { ?><span class="badge rounded-pill ms-auto"><?= $folder_counts[0] ?></span><?php } ?>
                            </a>
                        </div>
                    </li>
                    <?= filesFolderTree(0) ?>
                </ul>
            </div>
        </div>

        <div class="col-lg-9 min-w-0 itflow-files-main" <?= $can_edit && !$archived ? 'data-files-drop="' . escapeHtml($upload_url) . '" data-files-folder="' . $folder_name . '"' : '' ?>>

            <!-- Where you are -->
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom itflow-files-path">
                <nav aria-label="Folder" class="min-w-0 me-auto">
                    <ol class="breadcrumb mb-0 flex-nowrap text-nowrap overflow-auto">
                        <li class="breadcrumb-item"><a href="<?= escapeHtml(filesUrl(['folder_id' => 0, 'q' => ''])) ?>"><i class="fas fa-home me-1"></i>Top level</a></li>
                        <?php foreach ($folder_path as $crumb) { ?>
                            <li class="breadcrumb-item"><a href="<?= escapeHtml(filesUrl(['folder_id' => $crumb['folder_id'], 'q' => ''])) ?>"><?= escapeHtml($crumb['folder_name']) ?></a></li>
                        <?php } ?>
                        <?php if ($search !== '') { ?>
                            <li class="breadcrumb-item active">Results for "<?= escapeHtml($search) ?>"</li>
                        <?php } ?>
                    </ol>
                </nav>
                <?php if ($folder_id && $can_edit) { ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" title="This folder"><i class="fas fa-ellipsis-h"></i></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/folder/folder_add.php?client_id=<?= $client_id ?>&current_folder_id=<?= $folder_id ?>"><i class="fas fa-fw fa-folder-plus me-2"></i>New folder inside</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/folder/folder_rename.php?id=<?= $folder_id ?>"><i class="fas fa-fw fa-pen me-2"></i>Rename or move</a>
                            <?php if ($can_delete) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/folder/folder_delete.php?id=<?= $folder_id ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete folder</a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <?php if (!$items) { ?>

                <!-- Nothing here -->
                <?= emptyStateHtml([
                    'thing' => 'files or documents',
                    'icon' => 'fa-folder-open',
                    'filtered' => $search !== '' || $tag_filter_ids,
                    'clear_url' => filesUrl(['q' => '', 'tags' => null]),
                    'match_title' => $search !== '' ? 'Nothing matches "' . escapeHtml($search) . '"' . ($look_everywhere ? '' : ' in ' . $folder_name) : ($tag_filter_ids ? 'No documents with ' . (count($tag_filter_ids) === 1 ? 'that tag' : 'those tags') . ($look_everywhere ? '' : ' in ' . $folder_name) : null),
                    'archived' => $archived,
                    'archived_title' => 'Nothing archived ' . ($folder_id ? 'in ' . $folder_name : 'here'),
                    'title' => $folder_id ? $folder_name . ' is empty' : 'No files or documents yet',
                    'text' => $can_edit ? 'Drop files here to upload them, or start a document.' : '',
                    'view' => $type_filter && !$folder_id ? [
                        'title' => $type_filter === 'document' ? 'No documents yet' : 'No uploads yet',
                        'text' => $can_edit ? ($type_filter === 'document' ? 'Write one here - a procedure, a how-to, notes on the site.' : 'Drop files here to upload them.') : '',
                    ] : null,
                    'actions' => $can_edit ? [
                        ['label' => 'Upload files', 'modal' => $upload_url, 'icon' => 'fa-cloud-upload-alt'],
                        ['label' => 'New document', 'modal' => "modals/document/document_add.php?client_id=$client_id&folder_id=$folder_id", 'size' => 'xl', 'icon' => 'fa-file-alt'],
                    ] : [],
                ]) ?>

            <?php } else { ?>

                <form id="bulkActions" action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <?php if ($view === 1) { ?>

                        <!-- Grid: a preview of each, opened full size in the previewer -->
                        <div class="row row-cols-2 row-cols-sm-3 row-cols-xl-4 row-cols-xxl-5 g-3 p-3 itflow-files-grid">
                            <?php foreach ($items as $item) {
                                $id = $item['id'];
                                $name = escapeHtml($item['name']);
                                $row = $item['row'];
                                if ($item['kind'] === 'document') {
                                    // From the HTML itself, so headings and paragraphs keep the gaps between them
                                    $excerpt = cleanTextExcerpt(documentContentRaw('', $row['document_content'] ?? ''));
                                    $kind = 'document';
                                    $preview_url = "ajax.php?get_document_content=$id";
                                    $action_url = "document.php?client_id=$client_id&document_id=$id";
                                    $meta = 'Document';
                                } else {
                                    $mime = $row['file_mime_type'];
                                    $excerpt = '';
                                    if (!in_array($mime, getInlineViewableMimeTypes(), true)) {
                                        $kind = 'none';
                                    } elseif ($mime === 'application/pdf') {
                                        $kind = 'pdf';
                                    } elseif ($mime === 'text/plain' || $mime === 'application/json') {
                                        $kind = 'text';
                                        $excerpt = getFileTextExcerpt($client_id, $row['file_reference_name']);
                                    } else {
                                        $kind = 'image';
                                    }
                                    $preview_url = "file.php?file_id=$id&action=view";
                                    $action_url = "file.php?file_id=$id";
                                    $meta = strtoupper($row['file_ext']) . ' · ' . formatBytes($item['size']);
                                }
                                $preview_index = count($preview);
                                $preview[] = [
                                    'id' => $id,
                                    'name' => $item['name'],
                                    'kind' => $kind,
                                    'icon' => $item['kind'] === 'document' ? 'file-alt' : fileTypeInfo($row['file_ext'])['icon'],
                                    'meta' => $meta,
                                    'preview' => $preview_url,
                                    'download' => $action_url,
                                    'action' => $item['kind'] === 'document' ? 'Open document' : 'Download',
                                    'details' => $item['kind'] === 'file' ? "modals/file/file.php?id=$id" : '',
                                ];
                                ?>
                                <div class="col">
                                    <div class="card h-100 mb-0 itflow-file-card <?= $item['kind'] === 'document' ? 'is-document' : '' ?>">
                                        <?php if ($can_edit) { ?>
                                            <div class="form-check itflow-file-card-check">
                                                <input class="form-check-input bulk-select" type="checkbox" name="<?= $item['kind'] ?>_ids[]" value="<?= $id ?>" aria-label="Select <?= $name ?>">
                                            </div>
                                        <?php } ?>
                                        <a href="#" class="itflow-file-card-preview" data-files-preview="<?= $preview_index ?>" title="Preview <?= $name ?>">
                                            <?php if ($kind === 'image') { ?>
                                                <img src="file.php?file_id=<?= $id ?>&thumb=1" alt="" loading="lazy">
                                            <?php } elseif ($kind === 'pdf') { ?>
                                                <iframe src="file.php?file_id=<?= $id ?>&action=view#toolbar=0&navpanes=0&scrollbar=0&view=FitH" loading="lazy" title="<?= $name ?>" tabindex="-1"></iframe>
                                            <?php } elseif ($excerpt !== '') { ?>
                                                <pre><?= escapeHtml($excerpt) ?></pre>
                                            <?php } else { ?>
                                                <span class="itflow-file-card-icon"><?= $item['kind'] === 'document' ? documentTile('lg') : fileTypeTile($row['file_ext'], 'lg') ?></span>
                                            <?php } ?>
                                        </a>
                                        <div class="card-body p-2 d-flex align-items-start gap-2">
                                            <div class="min-w-0 flex-grow-1">
                                                <?php if ($item['kind'] === 'document') { ?>
                                                    <a class="fw-semibold small d-block text-truncate text-body" href="document.php?client_id=<?= $client_id ?>&document_id=<?= $id ?>" title="<?= $name ?>"><?= $name ?></a>
                                                <?php } else { ?>
                                                    <a class="fw-semibold small d-block text-truncate text-body ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/file/file.php?id=<?= $id ?>" title="<?= $name ?>"><?= $name ?></a>
                                                <?php } ?>
                                                <div class="small text-body-secondary text-truncate"><?= escapeHtml($meta) ?> &middot; <?= timeAgo($item['updated']) ?></div>
                                                <?php if ($item['kind'] === 'document' && !empty($documents_tags[$id])) { ?>
                                                    <div class="mt-1"><?= tagPills('document', $id, $documents_tags[$id], "files.php?client_id=$client_id&", false) ?></div>
                                                <?php } ?>
                                            </div>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-sm btn-link text-body-secondary px-1" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-ellipsis-v"></i></button>
                                                <div class="dropdown-menu dropdown-menu-end"><?= filesItemMenu($item) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                    <?php } else { ?>

                        <!-- List -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 itflow-files-table">
                                <thead class="text-nowrap table-light">
                                <tr>
                                    <?php if ($can_edit) { ?>
                                        <td class="checkbox-column bg-light border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" aria-label="Select all">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <?php foreach (['name' => 'Name', 'type' => 'Type', 'size' => 'Size', 'updated' => 'Updated'] as $column => $label) { ?>
                                        <th class="<?= $column === 'size' ? 'd-none d-md-table-cell' : ($column === 'type' ? 'd-none d-sm-table-cell' : '') ?>">
                                            <a class="text-reset" href="<?= escapeHtml(filesUrl(['sort' => $column, 'order' => $sort === $column && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                                <?= $label ?><?php if ($sort === $column) { ?><i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> ms-1"></i><?php } ?>
                                            </a>
                                        </th>
                                    <?php } ?>
                                    <th class="d-none d-lg-table-cell">Linked</th>
                                    <th class="position-relative"><span class="visually-hidden">Actions</span></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $item) {
                                    $id = $item['id'];
                                    $name = escapeHtml($item['name']);
                                    $row = $item['row'];
                                    $share = $shared[$item['kind']][$id] ?? null;
                                    ?>
                                    <tr>
                                        <?php if ($can_edit) { ?>
                                            <td class="checkbox-column bg-light border-end">
                                                <div class="form-check">
                                                    <input class="form-check-input bulk-select" type="checkbox" name="<?= $item['kind'] ?>_ids[]" value="<?= $id ?>" aria-label="Select <?= $name ?>">
                                                </div>
                                            </td>
                                        <?php } ?>
                                        <td class="position-relative itflow-files-name-cell">
                                            <div class="d-flex align-items-center gap-3">
                                                <?= $item['kind'] === 'document' ? documentTile() : fileTypeTile($row['file_ext']) ?>
                                                <div class="min-w-0">
                                                    <div class="d-flex flex-wrap align-items-center column-gap-2">
                                                        <?php if ($item['kind'] === 'document') { ?>
                                                            <a class="fw-semibold text-body stretched-link text-break" href="document.php?client_id=<?= $client_id ?>&document_id=<?= $id ?>"><?= $name ?></a>
                                                            <?php if (!empty($row['document_client_visible'])) { ?>
                                                                <span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal" title="Technical contacts can read this in the client portal">Portal</span>
                                                            <?php } ?>
                                                        <?php } else { ?>
                                                            <a class="fw-semibold text-body stretched-link text-break ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/file/file.php?id=<?= $id ?>"><?= $name ?></a>
                                                        <?php } ?>
                                                        <?php if ($share) { ?>
                                                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal" title="Shared<?= $share['item_recipient'] ? ' with ' . escapeHtml($share['item_recipient']) : '' ?> - link expires <?= timeAgo($share['item_expire_at']) ?>"><i class="fas fa-link me-1"></i>Shared</span>
                                                        <?php } ?>
                                                    </div>
                                                    <?php if ($item['description']) { ?>
                                                        <div class="small text-body-secondary text-truncate itflow-files-desc"><?= escapeHtml($item['description']) ?></div>
                                                    <?php } ?>
                                                    <?= filesFolderNote($item) ?>
                                                    <?php if ($item['kind'] === 'document' && (!empty($documents_tags[$id]) || ($can_edit && !$item['archived']))) { ?>
                                                        <div class="mt-1 itflow-above-stretch"><?= tagPills('document', $id, $documents_tags[$id] ?? [], "files.php?client_id=$client_id&", $can_edit && !$item['archived']) ?></div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small text-nowrap d-none d-sm-table-cell"><?= escapeHtml($item['type']) ?></td>
                                        <td class="small text-nowrap d-none d-md-table-cell"><?= $item['kind'] === 'file' ? formatBytes($item['size']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="small text-nowrap">
                                            <span title="<?= escapeHtml($item['updated']) ?>"><?= timeAgo($item['updated']) ?></span>
                                            <?php if ($item['by']) { ?><div class="text-body-secondary"><?= escapeHtml($item['by']) ?></div><?php } ?>
                                        </td>
                                        <td class="d-none d-lg-table-cell itflow-files-linked"><div class="d-flex flex-wrap gap-1"><?= filesLinkChips($item['kind'], $id) ?></div></td>
                                        <td class="text-end">
                                            <div class="dropdown dropstart">
                                                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-ellipsis-v"></i></button>
                                                <div class="dropdown-menu"><?= filesItemMenu($item) ?></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>

                    <?php } ?>

                </form>

            <?php } ?>

            <?php require_once "../includes/filter_footer.php"; ?>

        </div>
    </div>

    <?php if ($can_edit && !$archived) { ?>
        <!-- Shown while files are dragged over the list -->
        <div class="itflow-files-dropzone" hidden>
            <div><i class="fas fa-cloud-upload-alt fa-3x mb-2"></i><div class="fw-semibold">Drop to upload to <?= $folder_name ?></div></div>
        </div>
    <?php } ?>
</div>

<?php require "modals/file/file_view.php"; ?>
<script>
    window.itflowFilesPreview = <?= json_encode($preview) ?>;
</script>
<script src="js/files.js"></script>
<script src="../js/bulk_actions.js"></script>
<script src="../js/pretty_content.js"></script>

<?php
require_once "../includes/footer.php";
