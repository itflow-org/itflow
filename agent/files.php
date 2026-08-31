<?php

// Unified sort: "name" is logical field, not DB column
$sort = "name";
$order = "ASC";

require_once "includes/inc_all_client.php";

// Folder
if (!empty($_GET['folder_id'])) {
    $folder_id = intval($_GET['folder_id']);
} else {
    $folder_id = 0;
}

// Folder ID (used in forms/etc)
$get_folder_id = $folder_id;

/*
 * Type filter -- '' all, 'file' uploads only, 'document' created documents only.
 *
 * Whitelisted rather than trusted: it decides which of the two queries below run
 * at all, so an unexpected value should mean "everything", not an empty page.
 *
 * Like the view mode, it rides along on every folder link - a filter that
 * silently clears itself the moment you open a folder is worse than no filter.
 */
$type_filter = $_GET['type'] ?? '';
if (!in_array($type_filter, ['file', 'document'], true)) {
    $type_filter = '';
}

/*
 * Links for the filter buttons. Built from a copy with 'type' removed so the
 * new value does not land next to the old one, and with 'page' removed because
 * switching filter while on page 4 of the files would otherwise open page 4 of
 * a shorter list and show nothing.
 */
$type_get_copy = $_GET;
unset($type_get_copy['type'], $type_get_copy['page'], $type_get_copy['sort'], $type_get_copy['order']);
$url_query_strings_type = http_build_query($type_get_copy);
if ($url_query_strings_type !== '') {
    $url_query_strings_type .= '&';
}

/*
 * View Mode -- 0 List, 1 Grid
 *
 * Carried on every folder link below. Without it, opening a folder dropped the
 * parameter and the page fell back to list view, so picking grid only lasted
 * until the next click.
 */
if (!empty($_GET['view'])) {
    $view = intval($_GET['view']);
} else {
    $view = 0;
}

// Folder tree expanded state: 1 = expand all, 0 = collapsed (default)
if (isset($_GET['folders_expanded'])) {
    $folders_expanded = intval($_GET['folders_expanded']);
} else {
    $folders_expanded = 0;
}

if (!isset($q)) {
    $q = '';
}

// ---------------------------------------------
// Breadcrumbs: build full folder path
// ---------------------------------------------
$folder_path = [];
$breadcrumb_folder_id = $get_folder_id;

while ($breadcrumb_folder_id > 0) {
    $sql_folder = mysqli_query($mysqli, "SELECT folder_name, parent_folder FROM folders WHERE folder_id = $breadcrumb_folder_id AND folder_client_id = $client_id");
    if ($row_folder = mysqli_fetch_assoc($sql_folder)) {
        $folder_name = escapeHtml($row_folder['folder_name']);
        $parent_folder = intval($row_folder['parent_folder']);

        array_unshift($folder_path, [
            'folder_id'   => $breadcrumb_folder_id,
            'folder_name' => $folder_name
        ]);

        $breadcrumb_folder_id = $parent_folder;
    } else {
        break;
    }
}

// ---------------------------------------------
// Helper: unified folder tree (no folder_location)
// ---------------------------------------------
function isAncestorFolder($folder_id, $current_folder_id, $client_id) {
    global $mysqli;

    if ($current_folder_id == 0) {
        return false;
    }
    if ($current_folder_id == $folder_id) {
        return true;
    }

    $result = mysqli_query($mysqli, "SELECT parent_folder FROM folders WHERE folder_id = $current_folder_id AND folder_client_id = $client_id");
    if ($row = mysqli_fetch_assoc($result)) {
        $parent_folder_id = intval($row['parent_folder']);
        return isAncestorFolder($folder_id, $parent_folder_id, $client_id);
    } else {
        return false;
    }
}

function displayFolders($parent_folder_id, $client_id, $indent = 0, $render_root = false) {
    global $mysqli, $get_folder_id, $session_user_role, $archive_query, $archived, $num_root_items, $folders_expanded, $view, $type_filter;

    // Always render root (only once)
    if ($parent_folder_id == 0 && $indent == 0) {
        echo '<li class="nav-item">';
        echo '<a class="nav-link ' . ($get_folder_id == 0 ? 'active' : '') . '"';
        echo ' href="?client_id=' . $client_id . '&folder_id=0&view=' . $view . '&type=' . $type_filter . '&archived=' . $archived . '&folders_expanded=' . $folders_expanded . '">';
        echo '/';

        if ($num_root_items > 0) {
            echo "<span class='badge rounded-pill bg-dark float-end mt-1'>$num_root_items</span>";
        }

        echo '</a>';
        echo '</li>';
    }

    $sql_folders = mysqli_query(
        $mysqli,
        "SELECT folder_id, folder_name FROM folders
         WHERE parent_folder = $parent_folder_id
         AND folder_client_id = $client_id
         ORDER BY folder_name ASC"
    );

    while ($row = mysqli_fetch_assoc($sql_folders)) {
        $folder_id   = intval($row['folder_id']);
        $folder_name = escapeHtml($row['folder_name']);

        $row_files = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('file_id') AS num
             FROM files
             WHERE file_folder_id = $folder_id
             AND file_client_id = $client_id
             AND file_$archive_query"
        ));
        $num_files = intval($row_files['num']);

        $row_docs = mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT COUNT('document_id') AS num
             FROM documents
             WHERE document_folder_id = $folder_id
             AND document_client_id = $client_id
             AND document_$archive_query"
        ));
        $num_docs = intval($row_docs['num']);

        $num_total = $num_files + $num_docs;

        $subfolder_result = mysqli_query(
            $mysqli,
            "SELECT COUNT(*) AS count
             FROM folders
             WHERE parent_folder = $folder_id
             AND folder_client_id = $client_id"
        );
        $subfolder_count  = intval(mysqli_fetch_assoc($subfolder_result)['count']);

        // Active or ancestor of active folder = on active path
        $on_active_path = ($get_folder_id == $folder_id) || isAncestorFolder($folder_id, $get_folder_id, $client_id);

        // Option C: indent with padding (no AdminLTE sidebar CSS required)
        // Tune these numbers if you want tighter/looser indent
        $indent_px = 12 * $indent; // 12px per level

        echo '<li class="nav-item">';
        echo '<div class="row">';
        echo '<div class="col-10">';

        echo '<a class="nav-link ' . ($get_folder_id == $folder_id ? 'active' : '') . '"';
        echo ' style="padding-left: ' . (12 + $indent_px) . 'px;"';
        echo ' href="?client_id=' . $client_id . '&folder_id=' . $folder_id . '&view=' . $view . '&type=' . $type_filter . '&archived=' . $archived . '&folders_expanded=' . $folders_expanded . '">';

        if ($on_active_path) {
            echo '<i class="fas fa-fw fa-folder-open"></i>';
        } else {
            echo '<i class="fas fa-fw fa-folder"></i>';
        }

        echo ' ' . $folder_name;

        if ($subfolder_count > 0) {
            $is_expanded = $folders_expanded || $on_active_path;

            echo '<i class="fas fa-chevron-' . ($is_expanded ? 'down' : 'right') . ' text-muted ms-2"></i>';
        }

        if ($num_total > 0) {
            echo "<span class='badge rounded-pill bg-dark float-end mt-1'>$num_total</span>";
        }

        echo '</a>';
        echo '</div>'; // col-10

        echo '<div class="col-2">';
        ?>
        <div class="dropdown">
            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item ajax-modal" href="#"
                   data-modal-url="modals/folder/folder_rename.php?id=<?= $folder_id ?>">
                    <i class="fas fa-fw fa-edit me-2"></i>Rename
                </a>
                <?php if ($session_user_role == 3 && $num_total == 0 && $subfolder_count == 0) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_folder=<?= $folder_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                    </a>
                <?php } ?>
            </div>
        </div>
        <?php
        echo '</div>'; // col-2
        echo '</div>'; // row

        // Collapsed by default: ONLY render children if folder is on active path
        if ($subfolder_count > 0 && ($folders_expanded || $on_active_path)) {
            echo '<ul class="nav nav-pills flex-column bg-light">';
            displayFolders($folder_id, $client_id, $indent + 1);
            echo '</ul>';
        }

        echo '</li>';
    }
}


// ---------------------------------------------
// DATA LOAD
// view=1 (grid) - every file in the folder, thumbnail or file-type icon
// view=0 (list) loads ALL files+documents, merges, sorts in PHP
// ---------------------------------------------

$items = [];
$num_rows = [0];

// Both views decide previewability from this, so it is set before the branch -
// it used to be assigned inside the grid arm only, which left the list arm
// passing null to in_array()
$inline_viewable_mime_types = getInlineViewableMimeTypes();

if ($view == 1) {

    /*
     * GRID VIEW: files AND documents, same as the list view beside it.
     *
     * This used to be a single files-only query with SQL_CALC_FOUND_ROWS and a
     * LIMIT. Two sources cannot be paginated by one LIMIT, so it now mirrors
     * exactly what the list view does: fetch both unpaginated, merge, sort by
     * name, and slice in PHP. That also fixes the grid quietly omitting every
     * created document - they were only ever visible in list view.
     */
    $safe_q = mysqli_real_escape_string($mysqli, $q);

    if ($get_folder_id == 0 && isset($_GET["q"])) {
        $file_folder_snippet = "";         // search across all folders
        $doc_folder_snippet  = "";
    } else {
        $file_folder_snippet = "AND file_folder_id = $folder_id";
        $doc_folder_snippet  = "AND document_folder_id = $folder_id";
    }

    $file_search_snippet = "";
    $doc_search_snippet = "";
    if (!empty($q)) {
        $file_search_snippet = "AND (file_name LIKE '%$safe_q%' OR file_ext LIKE '%$safe_q%' OR file_description LIKE '%$safe_q%')";
        $doc_search_snippet = "AND (MATCH(document_content_raw) AGAINST ('$safe_q') OR document_name LIKE '%$safe_q%')";
    }

    // Skipped outright when the type filter excludes them
    $sql_grid_files = false;
    if ($type_filter !== 'document') {
        $sql_grid_files = mysqli_query(
            $mysqli,
            "SELECT files.*, users.user_name
             FROM files
             LEFT JOIN users ON file_created_by = user_id
             WHERE file_client_id = $client_id
             AND file_$archive_query
             $file_folder_snippet
             $file_search_snippet"
        );
    }

    $sql_grid_documents = false;
    if ($type_filter !== 'file') {
        $sql_grid_documents = mysqli_query(
            $mysqli,
            "SELECT documents.*, users.user_name
             FROM documents
             LEFT JOIN users ON document_created_by = user_id
             WHERE document_client_id = $client_id
             AND document_$archive_query
             $doc_folder_snippet
             $doc_search_snippet"
        );
    }

    while ($sql_grid_files && ($row = mysqli_fetch_assoc($sql_grid_files))) {
        $row['grid_kind'] = 'file';
        $items[] = $row;
    }

    while ($sql_grid_documents && ($row = mysqli_fetch_assoc($sql_grid_documents))) {
        $row['grid_kind'] = 'document';
        $items[] = $row;
    }

    usort($items, function ($a, $b) {
        $a_name = strtolower($a['grid_kind'] === 'file' ? $a['file_name'] : $a['document_name']);
        $b_name = strtolower($b['grid_kind'] === 'file' ? $b['file_name'] : $b['document_name']);
        return strcmp($a_name, $b_name);
    });

    $num_rows = [count($items)];
    $items = array_slice($items, $record_from, $record_to);

} else {

    // -------- LIST VIEW: build unified items[] --------

    // Folder filter
    if ($get_folder_id == 0 && isset($_GET["q"])) {
        $file_folder_snippet = "";         // search across all folders
        $doc_folder_snippet  = "";
    } else {
        $file_folder_snippet = "AND file_folder_id = $folder_id";
        $doc_folder_snippet  = "AND document_folder_id = $folder_id";
    }

    // Search filters
    $safe_q = mysqli_real_escape_string($mysqli, $q);

    $file_search_snippet = "";
    if (!empty($q)) {
        $file_search_snippet = "AND (file_name LIKE '%$safe_q%' OR file_ext LIKE '%$safe_q%' OR file_description LIKE '%$safe_q%')";
    }

    $doc_search_snippet = "";
    if (!empty($q)) {
        $doc_search_snippet = "AND (MATCH(document_content_raw) AGAINST ('$safe_q') OR document_name LIKE '%$safe_q%')";
    }

    // Files query (NO limit - we'll paginate in PHP). Skipped outright when the
    // type filter excludes files, rather than fetching rows to throw away.
    $sql_files = false;
    if ($type_filter !== 'document') {
        $sql_files = mysqli_query(
        $mysqli,
        "SELECT files.*, users.user_name
         FROM files
         LEFT JOIN users ON file_created_by = user_id
         WHERE file_client_id = $client_id
         AND file_$archive_query
         $file_folder_snippet
         $file_search_snippet"
        );
    }

    // Documents query (NO limit - paginate in PHP)
    $sql_documents = false;
    if ($type_filter !== 'file') {
        $sql_documents = mysqli_query(
        $mysqli,
        "SELECT documents.*, users.user_name
         FROM documents
         LEFT JOIN users ON document_created_by = user_id
         WHERE document_client_id = $client_id
         AND document_$archive_query
         $doc_folder_snippet
         $doc_search_snippet"
        );
    }

    // Normalize FILES into $items
    while ($sql_files && ($row = mysqli_fetch_assoc($sql_files))) {
        $file_id            = intval($row['file_id']);
        $file_name          = escapeHtml($row['file_name']);
        $file_description   = escapeHtml($row['file_description']);
        $file_reference_name= escapeHtml($row['file_reference_name']);
        $file_ext           = escapeHtml($row['file_ext']);
        $file_size          = intval($row['file_size']);
        $file_mime_type     = escapeHtml($row['file_mime_type']);
        $file_uploaded_by   = escapeHtml($row['user_name']);
        $file_created_at    = escapeHtml($row['file_created_at']);
        $file_archived_at     = $row['file_archived_at'];

        // determine icon
        if ($file_ext == 'pdf') {
            $file_icon = "file-pdf";
        } elseif (in_array($file_ext, ['gz','tar','zip','7z','rar'])) {
            $file_icon = "file-archive";
        } elseif (in_array($file_ext, ['txt','md'])) {
            $file_icon = "file-alt";
        } elseif ($file_ext == 'msg') {
            $file_icon = "envelope";
        } elseif (in_array($file_ext, ['doc','docx','odt'])) {
            $file_icon = "file-word";
        } elseif (in_array($file_ext, ['xls','xlsx','ods'])) {
            $file_icon = "file-excel";
        } elseif (in_array($file_ext, ['pptx','odp'])) {
            $file_icon = "file-powerpoint";
        } elseif (in_array($file_ext, ['mp3','wav','ogg'])) {
            $file_icon = "file-audio";
        } elseif (in_array($file_ext, ['mov','mp4','av1'])) {
            $file_icon = "file-video";
        } elseif (in_array($file_ext, ['jpg','jpeg','png','gif','webp','bmp','tif'])) {
            $file_icon = "file-image";
        } else {
            $file_icon = "file";
        }

        $items[] = [
            'kind'              => 'file',
            'id'                => $file_id,
            'name'              => $file_name,
            'description'       => $file_description,
            'reference_name'    => $file_reference_name,
            'icon'              => $file_icon,
            'ext'               => $file_ext,
            'mime'              => $file_mime_type,
            'size'              => $file_size,
            'created_at'        => $file_created_at,
            'created_by'        => $file_uploaded_by,
            'archived_at'       => $file_archived_at,
        ];
    }

    // Normalize DOCUMENTS into $items
    while ($sql_documents && ($row = mysqli_fetch_assoc($sql_documents))) {
        $document_id              = intval($row['document_id']);
        $document_name            = escapeHtml($row['document_name']);
        $document_description     = escapeHtml($row['document_description']);
        $document_created_by_name = escapeHtml($row['user_name']);
        $document_created_at      = $row['document_created_at'];
        $document_updated_at      = $row['document_updated_at'];
        $document_archived_at     = $row['document_archived_at'];

        $items[] = [
            'kind'              => 'document',
            'id'                => $document_id,
            'name'              => $document_name,
            'description'       => $document_description,
            'mime'              => 'Document',
            'size'              => null,
            'updated_at'        => $document_updated_at,
            'created_by'        => $document_created_by_name,
            'archived_at'       => $document_archived_at,
        ];
    }

    // Sort combined items
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

    usort($items, function($a, $b) use ($sort, $order) {
        $direction = ($order === 'DESC') ? -1 : 1;

        if ($sort == 'created') {
            $valA = strtotime($a['created_at']);
            $valB = strtotime($b['created_at']);
        } elseif ($sort == 'type') {
            $valA = strtolower($a['mime']);
            $valB = strtolower($b['mime']);
        } elseif ($sort == 'size') {
            $valA = (int)($a['size'] ?? 0);
            $valB = (int)($b['size'] ?? 0);
        } else {
            // default: name
            $valA = strtolower($a['name']);
            $valB = strtolower($b['name']);
        }

        if ($valA == $valB) {
            return 0;
        }

        return ($valA < $valB) ? -1 * $direction : 1 * $direction;
    });

    // Total items (for pagination footer)
    $total_items = count($items);
    $num_rows = [$total_items];

    // Apply pagination slice
    $items = array_slice($items, $record_from, $record_to);
}

// ---------------------------------------------
// Root folder count (for "/" badge)
// ---------------------------------------------
$row_root_files = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('file_id') AS num FROM files WHERE file_folder_id = 0 AND file_client_id = $client_id AND file_$archive_query"));
$row_root_docs  = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_id') AS num FROM documents WHERE document_folder_id = 0 AND document_client_id = $client_id AND document_$archive_query"));
$num_root_items = intval($row_root_files['num']) + intval($row_root_docs['num']);

?>

<div class="card card-dark">

    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-folder me-2"></i>Files</h3>

        <div class="card-tools">

            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-plus me-2"></i>New
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                       data-modal-url="modals/file/file_upload.php?client_id=<?= $client_id ?>&folder_id=<?= $get_folder_id ?>">
                        <i class="fas fa-fw fa-cloud-upload-alt me-2"></i>Upload File
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&folder_id=<?= $get_folder_id ?>"
                        data-modal-size="lg">
                        <i class="fas fa-fw fa-file-alt me-2"></i>Document
                    </a>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="modals/document/document_add_from_template.php?client_id=<?= $client_id ?>&folder_id=<?= $get_folder_id ?>">
                        <i class="fas fa-fw fa-file me-2"></i>Document from Template
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                       data-modal-url="modals/folder/folder_add.php?client_id=<?= $client_id ?>&current_folder_id=<?= $get_folder_id ?>">
                        <i class="fa fa-fw fa-folder-plus me-2"></i>Folder
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="row">

            <!-- Folders -->
            <div class="col-md-3 border-end mb-3">
                <div class="d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Folders</h4>

                    <?php
                    $toggle_value = $folders_expanded ? 0 : 1;
                    $toggle_title = $folders_expanded
                        ? 'Collapse all folders'
                        : 'Expand all folders';
                    ?>

                    <a href="?<?= http_build_query(array_merge($_GET, ['folders_expanded' => $toggle_value])) ?>"
                       class="btn btn-tool"
                       title="<?= $toggle_title ?>"
                       aria-label="<?= $toggle_title ?>">
                           <i class="fas <?= $folders_expanded ? 'fa-chevron-down' : 'fa-chevron-right' ?>"></i>
                    </a>
                </div>
                <hr>
                <ul class="nav nav-pills flex-column bg-light">

                    <?php
                    // Start folder tree from root
                    displayFolders(0, $client_id);
                    ?>
                </ul>
            </div>

            <!-- Main content -->
            <div class="col-md-9">

                <!-- Search + view toggle -->
                <form autocomplete="off">
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                    <input type="hidden" name="view" value="<?= $view ?>">
                    <input type="hidden" name="type" value="<?= escapeHtml($type_filter) ?>">
                    <input type="hidden" name="folder_id" value="<?= $get_folder_id ?>">
                    <input type="hidden" name="archived" value="<?= $archived ?>">
                    <input type="hidden" name="folders_expanded" value="<?= $folders_expanded ?>">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="input-group mb-3 mb-md-0">
                                <input type="search" class="form-control" name="q"
                                       value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>"
                                       placeholder="Search files and documents in <?= ($get_folder_id == 0 ? 'all folders' : 'current folder') ?>">
                                    <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="float-end">
                                <div class="btn-group me-2">
                                    <a href="?<?= $url_query_strings_type ?>type=" class="btn <?php if($type_filter === ''){ echo "btn-primary"; } else { echo "btn-outline-secondary"; } ?>" title="Files and documents">All</a>
                                    <a href="?<?= $url_query_strings_type ?>type=file" class="btn <?php if($type_filter === 'file'){ echo "btn-primary"; } else { echo "btn-outline-secondary"; } ?>" title="Uploaded files only"><i class="fas fa-fw fa-file me-1"></i>Files</a>
                                    <a href="?<?= $url_query_strings_type ?>type=document" class="btn <?php if($type_filter === 'document'){ echo "btn-primary"; } else { echo "btn-outline-secondary"; } ?>" title="Created documents only"><i class="fas fa-fw fa-file-alt me-1"></i>Docs</a>
                                </div>
                                <div class="btn-group">
                                    <a href="?<?= $url_query_strings_sort ?>&view=0&folder_id=<?= $get_folder_id ?>" class="btn <?php if($view == 0){ echo "btn-primary"; } else { echo "btn-outline-secondary"; } ?>" title="List View"><i class="fas fa-list-ul"></i></a>
                                    <a href="?<?= $url_query_strings_sort ?>&view=1&folder_id=<?= $get_folder_id ?>" class="btn <?php if($view == 1){ echo "btn-primary"; } else { echo "btn-outline-secondary"; } ?>" title="Grid View"><i class="fas fa-th-large"></i></a>
                                </div>
                                <div class="btn-group">
                                    <a href="?<?= $url_query_strings_sort ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
                                        class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                                        <i class="fa fa-fw fa-archive me-2"></i>Archived
                                    </a>
                                </div>
                                <div class="btn-group">
                                    <div class="dropdown ms-2" id="bulkActionButton" hidden>
                                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/file/file_bulk_move.php?client_id=<?= $client_id ?>&current_folder_id=<?= $get_folder_id ?>"
                                            data-bulk="true">
                                                <i class="fas fa-fw fa-exchange-alt me-2"></i>Move Files
                                            </a>
                                            <?php if ($archived) { ?>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-info"
                                                type="submit" form="bulkActions" name="bulk_restore_files">
                                                <i class="fas fa-fw fa-redo me-2"></i>Restore Files
                                            </button>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-danger text-bold"
                                                type="submit" form="bulkActions" name="bulk_delete_files">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete Files
                                            </button>
                                            <?php } else { ?>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-danger"
                                                type="submit" form="bulkActions" name="bulk_archive_files">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive Files
                                            </button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Breadcrumb -->
                <nav class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="?client_id=<?= $client_id ?>&folder_id=0&view=<?= $view ?>&type=<?= escapeHtml($type_filter) ?>&archived=<?= $archived ?>">
                                <i class="fas fa-fw fa-folder me-2"></i>Root
                            </a>
                        </li>
                        <?php foreach ($folder_path as $folder) {
                            $bread_crumb_folder_id   = $folder['folder_id'];
                            $bread_crumb_folder_name = $folder['folder_name']; ?>
                            <li class="breadcrumb-item">
                                <a href="?client_id=<?= $client_id ?>&folder_id=<?= $bread_crumb_folder_id ?>&view=<?= $view ?>&type=<?= escapeHtml($type_filter) ?>&archived=<?= $archived ?>&folders_expanded=<?= $folders_expanded ?>">
                                    <i class="fas fa-fw fa-folder-open me-2"></i><?= $bread_crumb_folder_name ?>
                                </a>
                            </li>
                        <?php } ?>
                    </ol>
                </nav>

                <hr>

                <?php
                /*
                 * Payload for the file previewer, filled by whichever view is
                 * rendering below. Both build it: the grid from its tiles, the
                 * list from its file rows, so a file opens the same previewer
                 * either way. Documents are deliberately absent - they open the
                 * document viewer, not this.
                 */
                $files = [];
                ?>

                <?php if ($view == 1) { ?>

                    <!-- THUMBNAIL VIEW (files only) -->
                    <?php if ($num_rows[0] == 0) { ?>
                        <p class="text-muted">
                            <i class="fa fa-fw fa-folder-open me-2"></i>
                            <?php if ($type_filter === 'file') { ?>
                                No files in this folder.
                            <?php } elseif ($type_filter === 'document') { ?>
                                No documents in this folder.
                            <?php } else { ?>
                                Nothing in this folder.
                            <?php } ?>
                        </p>
                    <?php } ?>

                    <div class="row">
                        <?php
                        foreach ($items as $row) {

                            $item_kind = $row['grid_kind'];

                            if ($item_kind === 'document') {

                                /*
                                 * A created document has no file on disk, so the
                                 * preview comes from document_content_raw.
                                 *
                                 * That column is NOT clean plain text: it is the
                                 * editor's HTML run through strip_tags(), which
                                 * drops the tags and leaves every entity behind,
                                 * so it arrives full of &nbsp; and &amp;.
                                 * cleanTextExcerpt() decodes those before the
                                 * value is escaped for output - escaping first
                                 * printed a literal &amp;nbsp; in every tile.
                                 *
                                 * document_model.php also builds the column as
                                 * name . " " . content, so the excerpt opens with
                                 * the document's own title, which the tile prints
                                 * underneath anyway. Drop that prefix.
                                 */
                                $file_id            = intval($row['document_id']);
                                $file_name          = escapeHtml($row['document_name']);
                                $file_ext           = 'DOC';
                                $file_size_human    = '';
                                $file_archived_at   = escapeHtml($row['document_archived_at']);
                                $file_icon          = 'file-alt';
                                $file_preview_kind  = 'document';
                                $document_excerpt   = cleanTextExcerpt($row['document_content_raw']);
                                $document_title     = trim($row['document_name']);
                                if ($document_title !== '' && stripos($document_excerpt, $document_title) === 0) {
                                    $document_excerpt = ltrim(mb_substr($document_excerpt, mb_strlen($document_title)));
                                }
                                $file_excerpt       = escapeHtml($document_excerpt);
                                $file_open_url      = "ajax.php?get_document_content=$file_id";
                                $file_download_url  = "document.php?client_id=$client_id&document_id=$file_id";

                            } else {

                                $file_id            = intval($row['file_id']);
                                $file_name          = escapeHtml($row['file_name']);
                                $file_reference_name= escapeHtml($row['file_reference_name']);
                                $file_ext           = escapeHtml($row['file_ext']);
                                $file_size          = intval($row['file_size']);
                                $file_size_human    = formatBytes($file_size);
                                $file_mime_type     = escapeHtml($row['file_mime_type']);
                                $file_uploaded_by   = escapeHtml($row['user_name']);
                                $file_archived_at   = escapeHtml($row['file_archived_at']);
                                $file_icon          = getFileIcon($file_ext);
                                $file_excerpt       = '';
                                $file_open_url      = "file.php?file_id=$file_id&action=view";
                                $file_download_url  = "file.php?file_id=$file_id";

                                /*
                                 * What the preview can show is decided by file.php,
                                 * which serves inline only for getInlineViewableMimeTypes()
                                 * and forces a download for everything else. Ask the same
                                 * list rather than guessing: a prefix test on "image/"
                                 * would call an SVG previewable, and file.php refuses to
                                 * serve those inline on purpose, so the modal would frame
                                 * a URL that answers with an attachment.
                                 */
                                if (!in_array($file_mime_type, $inline_viewable_mime_types, true)) {
                                    $file_preview_kind = 'none';
                                } elseif ($file_mime_type === 'application/pdf') {
                                    $file_preview_kind = 'pdf';
                                } elseif ($file_mime_type === 'text/plain' || $file_mime_type === 'application/json') {
                                    $file_preview_kind = 'text';
                                    $file_excerpt = escapeHtml(getFileTextExcerpt($client_id, $row['file_reference_name']));
                                } else {
                                    $file_preview_kind = 'image';
                                }
                            }

                            /*
                             * Documents sit in the same payload as files so the
                             * previewer can page across everything in the folder.
                             * They carry no preview URL to frame - their content is
                             * fetched from ajax.php when opened - and a "full page"
                             * link to document.php for the real thing.
                             */
                            $tile_index = count($files);
                            $files[] = [
                                'id'       => $file_id,
                                'name'     => $file_name,
                                'kind'     => $file_preview_kind,
                                'icon'     => $file_icon,
                                'ext'      => strtoupper($file_ext),
                                'size'     => $file_size_human,
                                'excerpt'  => $file_excerpt,
                                'preview'  => $file_open_url,
                                'download' => $file_download_url,
                                'action'   => ($item_kind === 'document') ? 'Open full page' : 'Download'
                            ];
                            ?>

                            <div class="col-xl-2 col-lg-2 col-md-6 col-sm-6 mb-3 text-center">

                                <?php
                                /*
                                 * Documents take a coloured border and badge so a
                                 * created document is not mistaken for an uploaded
                                 * file at a glance - they behave differently and
                                 * open in different places.
                                 */
                                $tile_border = ($item_kind === 'document') ? 'border-primary' : '';
                                ?>

                                <a href="#" onclick="openModal(<?= $tile_index ?>); return false;"
                                    class="d-block text-decoration-none position-relative">

                                    <?php if ($item_kind === 'document') { ?>
                                        <span class="badge text-bg-primary position-absolute top-0 start-0 m-1">Document</span>
                                    <?php } else { ?>
                                        <span class="badge text-bg-secondary position-absolute top-0 start-0 m-1"><?= strtoupper($file_ext) ?></span>
                                    <?php } ?>

                                    <?php if ($file_preview_kind === 'image') { ?>

                                        <img class="img-thumbnail <?= $tile_border ?>" src="file.php?file_id=<?= $file_id ?>&thumb=1" alt="<?= $file_name ?>">

                                    <?php } elseif ($file_preview_kind === 'pdf') { ?>

                                        <?php
                                        /*
                                         * The browser's own PDF viewer renders the first
                                         * page. loading="lazy" keeps a folder of 50 PDFs
                                         * from spawning 50 viewers at once, and
                                         * pointer-events:none stops the viewer swallowing
                                         * the click meant for the preview modal.
                                         *
                                         * #toolbar=0 is honoured by Firefox and ignored by
                                         * Chrome, which draws its viewer chrome regardless.
                                         * So the iframe is made taller than its box and
                                         * pulled up by that much: the toolbar strip lands
                                         * above the overflow-hidden edge and is clipped,
                                         * whichever browser is rendering. The full-size
                                         * preview in the modal keeps its toolbar - that one
                                         * is meant to be used.
                                         */
                                        ?>
                                        <div class="img-thumbnail overflow-hidden p-0 <?= $tile_border ?>" style="height:150px;">
                                            <iframe src="file.php?file_id=<?= $file_id ?>&action=view#toolbar=0&navpanes=0&scrollbar=0&view=FitH"
                                                loading="lazy" title="<?= $file_name ?>" tabindex="-1"
                                                style="width:100%; height:calc(100% + 56px); margin-top:-56px; border:0; pointer-events:none;"></iframe>
                                        </div>

                                    <?php } elseif (($file_preview_kind === 'text' || $file_preview_kind === 'document') && $file_excerpt !== '') { ?>

                                        <div class="img-thumbnail overflow-hidden text-start <?= $tile_border ?>" style="height:150px;">
                                            <pre class="small text-body-secondary mb-0" style="white-space:pre-wrap; word-break:break-word;"><?= $file_excerpt ?></pre>
                                        </div>

                                    <?php } else { ?>

                                        <div class="img-thumbnail d-flex flex-column justify-content-center align-items-center text-secondary <?= $tile_border ?>" style="height:150px;">
                                            <i class="fas fa-<?= $file_icon ?> fa-3x"></i>
                                            <small class="mt-2 text-uppercase"><?= $file_ext ?></small>
                                        </div>

                                    <?php } ?>
                                </a>

                                <div>
                                    <div class="dropdown float-end">
                                        <button class="btn btn-link btn-sm" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                        <?php if ($item_kind === 'document') { ?>

                                            <a class="dropdown-item" href="document.php?client_id=<?= $client_id ?>&document_id=<?= $file_id ?>">
                                                <i class="fas fa-fw fa-eye me-2"></i>Open
                                            </a>
                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#shareModal" onclick="populateShareModal(<?= "$client_id, 'Document', $file_id" ?>)">
                                                <i class="fas fa-fw fa-share me-2"></i>Share
                                            </a>

                                        <?php } else { ?>
                                                <a class="dropdown-item" href="file.php?file_id=<?= $file_id; ?>">
                                                    <i class="fas fa-fw fa-cloud-download-alt me-2"></i>Download
                                                </a>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#shareModal" onclick="populateShareModal(<?= "$client_id, 'File', $file_id" ?>)">
                                                    <i class="fas fa-fw fa-share me-2"></i>Share
                                                </a>
                                                <a class="dropdown-item ajax-modal" href="#"
                                                   data-modal-url="modals/file/file_rename.php?id=<?= $file_id ?>">
                                                    <i class="fas fa-fw fa-edit me-2"></i>Rename
                                                </a>
                                                <a class="dropdown-item ajax-modal" href="#"
                                                   data-modal-url="modals/file/file_move.php?id=<?= $file_id ?>">
                                                    <i class="fas fa-fw fa-exchange-alt me-2"></i>Move
                                                </a>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#linkAssetToFileModal<?= $file_id ?>">
                                                    <i class="fas fa-fw fa-desktop me-2"></i>Link Asset
                                                </a>
                                                <?php if ($file_archived_at) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-info" href="post.php?restore_file=<?= $file_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                                    </a>
                                                    <?php if ($session_user_role == 3) { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger text-bold" href="#" data-bs-toggle="modal" data-bs-target="#deleteFileModal" onclick="populateFileDeleteModal(<?= "$file_id , '$file_name'" ?>)">
                                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                        </a>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_file=<?= $file_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                    </a>
                                                <?php } ?>

                                        <?php } ?>
                                        </div>
                                    </div>
                                    <small class="text-secondary"><?= $file_name ?></small>
                                </div>
                            </div>

                            <?php
                        }

                        // Once, after the loop. This used to sit inside it, so a
                        // folder of 30 files emitted 30 copies of the same modal
                        // with the same element ids - getElementById found the
                        // first and it happened to work.
                        ?>
                    </div>

                <?php } else { ?>

                    <!-- LIST VIEW: unified Files + Documents -->
                    <form id="bulkActions" action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="table-responsive-sm">
                            <table class="table border mb-0">
                                <thead class="table-light <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                                <tr>
                                    <td class="checkbox-column border-end">
                                        <div class="form-check">
                                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                        </div>
                                    </td>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=name&order=<?= $disp ?>">
                                            Name <?php if ($sort == 'name') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=type&order=<?= $disp ?>">
                                            Type <?php if ($sort == 'type') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=size&order=<?= $disp ?>">
                                            Size <?php if ($sort == 'size') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=created&order=<?= $disp ?>">
                                            Updated <?php if ($sort == 'created') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th></th>
                                    <th class="text-center">Action</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php
                                foreach ($items as $item) {

                                    if ($item['kind'] === 'file') {
                                        $file_id            = $item['id'];
                                        $file_name          = $item['name'];
                                        $file_description   = $item['description'];
                                        $file_reference_name= $item['reference_name'];
                                        $file_icon          = $item['icon'];
                                        $file_ext           = $item['ext'];
                                        $file_size          = $item['size'];
                                        $file_size_human    = formatBytes($file_size);
                                        $file_mime_type     = $item['mime'];
                                        $file_uploaded_by   = $item['created_by'];
                                        $file_created_at    = $item['created_at'];
                                        $file_archived_at   = $item['archived_at'];

                                        // Shared?
                                        $sql_shared = mysqli_query(
                                            $mysqli,
                                            "SELECT item_expire_at, item_recipient FROM shared_items
                                             WHERE item_client_id = $client_id
                                             AND item_active = 1
                                             AND (COALESCE(item_view_limit, 0) = 0 OR item_views < item_view_limit)
                                             AND item_expire_at > NOW()
                                             AND item_type = 'File'
                                             AND item_related_id = $file_id
                                             LIMIT 1"
                                        );
                                        $file_shared = (mysqli_num_rows($sql_shared) > 0);
                                        if ($file_shared) {
                                            $row_shared = mysqli_fetch_assoc($sql_shared);
                                            $item_recipient       = escapeHtml($row_shared['item_recipient']);
                                            $item_expire_at_human = timeAgo($row_shared['item_expire_at']);
                                        }
                                        ?>
                                        <tr>
                                            <td class="checkbox-column bg-light border-end">
                                                <div class="form-check">
                                                    <input class="form-check-input bulk-select" type="checkbox" name="file_ids[]" value="<?= $file_id ?>">
                                                </div>
                                            </td>
                                            <?php
                                            /*
                                             * A file the previewer can actually show opens
                                             * it; anything else keeps the direct link, which
                                             * downloads. Sending a .docx to the previewer
                                             * would only show "cannot be previewed" and make
                                             * the download a second click.
                                             *
                                             * Documents are untouched below - they go to
                                             * document.php, which is where a document
                                             * belongs.
                                             */
                                            $file_preview_index = -1;
                                            if (in_array($file_mime_type, $inline_viewable_mime_types, true)) {
                                                $file_preview_index = count($files);
                                                $files[] = [
                                                    'id'       => $file_id,
                                                    'name'     => basename($file_name),
                                                    'kind'     => ($file_mime_type === 'application/pdf') ? 'pdf'
                                                                  : (($file_mime_type === 'text/plain' || $file_mime_type === 'application/json') ? 'text' : 'image'),
                                                    'icon'     => $file_icon,
                                                    'ext'      => strtoupper($file_ext),
                                                    'size'     => $file_size_human,
                                                    'excerpt'  => '',
                                                    'preview'  => "file.php?file_id=$file_id&action=view",
                                                    'download' => "file.php?file_id=$file_id",
                                                    'action'   => 'Download'
                                                ];
                                            }
                                            ?>
                                            <td>
                                                <?php if ($file_preview_index >= 0) { ?>
                                                    <a href="#" onclick="openModal(<?= $file_preview_index ?>); return false;">
                                                <?php } else { ?>
                                                    <a href="file.php?file_id=<?= $file_id; ?>&action=view" target="_blank">
                                                <?php } ?>
                                                    <div class="d-flex">
                                                        <i class="fa fa-fw fa-2x fa-<?= $file_icon ?> text-dark me-3"></i>
                                                        <div class="flex-grow-1">
                                                            <p>
                                                                <?= basename($file_name) ?>
                                                                <br>
                                                                <small class="text-secondary"><?= $file_description ?></small>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td><?= $file_mime_type ?></td>
                                            <td><?= $file_size_human ?></td>
                                            <td>
                                                <?= $file_created_at ?>
                                                <div class="text-secondary mt-1"><?= $file_uploaded_by ?></div>
                                            </td>
                                            <td>
                                                <?php if ($file_shared) { ?>
                                                    <div class="d-flex" title="Expires <?= $item_expire_at_human ?>">
                                                        <i class="fas fa-link me-2 mt-1"></i>
                                                        <div class="flex-grow-1">Shared
                                                            <br>
                                                            <small class="text-secondary"><?= $item_recipient ?></small>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <div class="dropdown dropstart text-center">
                                                    <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="file.php?file_id=<?= $file_id ?>">
                                                            <i class="fas fa-fw fa-cloud-download-alt me-2"></i>Download
                                                        </a>
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#shareModal" onclick="populateShareModal(<?= "$client_id, 'File', $file_id" ?>)">
                                                            <i class="fas fa-fw fa-share me-2"></i>Share
                                                        </a>
                                                        <a class="dropdown-item ajax-modal" href="#"
                                                           data-modal-url="modals/file/file_rename.php?id=<?= $file_id ?>">
                                                            <i class="fas fa-fw fa-edit me-2"></i>Rename
                                                        </a>
                                                        <a class="dropdown-item ajax-modal" href="#"
                                                           data-modal-url="modals/file/file_move.php?id=<?= $file_id ?>">
                                                            <i class="fas fa-fw fa-exchange-alt me-2"></i>Move
                                                        </a>
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#linkAssetToFileModal<?= $file_id ?>">
                                                            <i class="fas fa-fw fa-desktop me-2"></i>Link Asset
                                                        </a>
                                                        <?php if ($file_archived_at) { ?>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-info" href="post.php?restore_file=<?= $file_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                <i class="fas fa-fw fa-redo me-2"></i>Restore
                                                            </a>
                                                            <?php if ($session_user_role == 3) { ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger text-bold" href="#" data-bs-toggle="modal" data-bs-target="#deleteFileModal" onclick="populateFileDeleteModal(<?= "$file_id , '$file_name'" ?>)">
                                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                                </a>
                                                            <?php } ?>
                                                        <?php } else { ?>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_file=<?= $file_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                            </a>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                        require "modals/file/file_link_asset.php";

                                    } else {
                                        // DOCUMENT ROW
                                        $document_id              = $item['id'];
                                        $document_name            = $item['name'];
                                        $document_description     = $item['description'];
                                        $document_created_by_name = $item['created_by'];
                                        $document_created_at      = date("m/d/Y", strtotime($item['updated_at']));
                                        //$document_updated_at      = date("m/d/Y", strtotime($item['updated_at']));
                                        $document_archived_at     = $item['archived_at'];

                                        $sql_shared = mysqli_query(
                                            $mysqli,
                                            "SELECT item_expire_at, item_recipient FROM shared_items
                                             WHERE item_client_id = $client_id
                                             AND item_active = 1
                                             AND (COALESCE(item_view_limit, 0) = 0 OR item_views < item_view_limit)
                                             AND item_expire_at > NOW()
                                             AND item_type = 'Document'
                                             AND item_related_id = $document_id
                                             LIMIT 1"
                                        );
                                        $doc_shared = (mysqli_num_rows($sql_shared) > 0);
                                        if ($doc_shared) {
                                            $row_shared = mysqli_fetch_assoc($sql_shared);
                                            $item_recipient       = escapeHtml($row_shared['item_recipient']);
                                            $item_expire_at_human = timeAgo($row_shared['item_expire_at']);
                                        }
                                        ?>
                                        <tr>
                                            <td class="checkbox-column bg-light border-end">
                                                <div class="form-check">
                                                    <input class="form-check-input bulk-select" type="checkbox" name="document_ids[]" value="<?= $document_id ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <?php /* Blue icon and badge, same as the grid tile - a created
                                                         document and an uploaded file behave differently and open
                                                         in different places, so they should not look alike. */ ?>
                                                <a href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>">
                                                    <div class="d-flex">
                                                        <i class="fa fa-fw fa-2x fa-file-alt text-primary me-3"></i>
                                                        <div class="flex-grow-1">
                                                            <p>
                                                                <?= $document_name ?>
                                                                <br>
                                                                <small class="text-secondary"><?= $document_description ?></small>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td><span class="badge text-bg-primary">Document</span></td>
                                            <td>-</td>
                                            <td>
                                                <?= $document_created_at ?>
                                                <div class="text-secondary mt-1"><?= $document_created_by_name ?></div>
                                            </td>
                                            <td>
                                                <?php if ($doc_shared) { ?>
                                                    <div class="d-flex" title="Expires <?= $item_expire_at_human ?>">
                                                        <i class="fas fa-link me-2 mt-1"></i>
                                                        <div class="flex-grow-1">Shared
                                                            <br>
                                                            <small class="text-secondary"><?= $item_recipient ?></small>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <div class="dropdown dropstart text-center">
                                                    <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item ajax-modal" href="#"
                                                           data-modal-size="lg"
                                                           data-modal-url="modals/document/document_view.php?id=<?= $document_id ?>">
                                                            <i class="fas fa-fw fa-eye me-2"></i>Quick View
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item ajax-modal" href="#"
                                                           data-modal-size="lg"
                                                           data-modal-url="modals/document/document_edit.php?id=<?= $document_id ?>">
                                                            <i class="fas fa-fw fa-pencil-alt me-2"></i>Edit
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#shareModal" onclick="populateShareModal(<?= "$client_id, 'Document', $document_id" ?>)">
                                                            <i class="fas fa-fw fa-share me-2"></i>Share
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item ajax-modal" href="#"
                                                           data-modal-url="modals/document/document_rename.php?id=<?= $document_id ?>">
                                                            <i class="fas fa-fw fa-pencil-alt me-2"></i>Rename
                                                        </a>
                                                        <a class="dropdown-item ajax-modal" href="#"
                                                           data-modal-url="modals/document/document_move.php?id=<?= $document_id ?>">
                                                            <i class="fas fa-fw fa-exchange-alt me-2"></i>Move
                                                        </a>
                                                        <?php if ($document_archived_at) { ?>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-info" href="post.php?restore_document=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                <i class="fas fa-fw fa-redo me-2"></i>Restore
                                                            </a>
                                                            <?php if ($session_user_role == 3) { ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger text-bold" href="post.php?delete_document=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                                </a>
                                                            <?php } ?>
                                                        <?php } else { ?>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger" href="post.php?archive_document=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                            </a>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                <?php } ?>

                <?php
                // After both branches - the previewer serves the grid and the
                // list, and $files is whichever view just filled it
                require "modals/file/file_view.php";
                ?>
                <script>
                    var files = <?= json_encode($files) ?>;
                    var currentIndex = 0;
                </script>

                <?php require_once "../includes/filter_footer.php"; ?>

            </div>
        </div>
    </div>
</div>

<script>
function openModal(index) {
    currentIndex = index;
    updateModalContent();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewFileModal')).show();
}

function updateModalContent() {
    var file = files[currentIndex];
    var image = document.getElementById('modalImage');
    var frame = document.getElementById('modalFrame');
    var fallback = document.getElementById('modalFallback');
    var text = document.getElementById('modalText');
    var docPanel = document.getElementById('modalDocument');

    document.getElementById('modalTitle').innerText = file.name;
    document.getElementById('modalMeta').innerText = file.size ? (file.ext + ' \u00b7 ' + file.size) : file.ext;
    var action = document.getElementById('modalDownload');
    action.href = file.download;
    action.innerHTML = '<i class="fas fa-fw fa-' + (file.kind === 'document' ? 'external-link-alt' : 'cloud-download-alt') + ' me-2"></i>' + file.action;
    document.getElementById('modalPosition').innerText = (currentIndex + 1) + ' of ' + files.length;

    // Blank the previous file's source before switching panels, or a heavy PDF
    // keeps rendering behind the next image until the browser gets round to it
    image.classList.add('d-none');
    image.removeAttribute('src');
    frame.classList.add('d-none');
    frame.removeAttribute('src');
    fallback.classList.add('d-none');
    text.classList.add('d-none');
    docPanel.classList.add('d-none');
    docPanel.innerHTML = '';

    if (file.kind === 'document') {

        /*
         * Fetched rather than carried in the page payload - see the endpoint's
         * note in ajax.php. The index is captured so a fast click through to the
         * next file does not get overwritten by a response that arrives late.
         */
        var requestedIndex = currentIndex;
        docPanel.innerHTML = '<p class="text-secondary mb-0">Loading...</p>';
        docPanel.classList.remove('d-none');

        fetch(file.preview, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (requestedIndex !== currentIndex) {
                    return;
                }
                // The endpoint returns HTMLPurifier output, which is what
                // document.php renders too
                docPanel.innerHTML = data.content || '<p class="text-secondary mb-0">This document is empty.</p>';
            })
            .catch(function () {
                if (requestedIndex === currentIndex) {
                    docPanel.innerHTML = '<p class="text-danger mb-0">Could not load this document.</p>';
                }
            });

    } else if (file.kind === 'image') {
        image.src = file.preview;
        image.alt = file.name;
        image.classList.remove('d-none');
    } else if (file.kind === 'pdf' || file.kind === 'text') {
        frame.src = file.preview;
        frame.classList.remove('d-none');
    } else {
        document.getElementById('modalFallbackIcon').className = 'fas fa-' + file.icon + ' fa-4x text-secondary';
        fallback.classList.remove('d-none');
    }

    // One file in the folder means nothing to page through
    var single = files.length < 2;
    document.getElementById('modalPrev').classList.toggle('d-none', single);
    document.getElementById('modalNext').classList.toggle('d-none', single);
}

function nextFile() {
    currentIndex = (currentIndex + 1) % files.length;
    updateModalContent();
}

function prevFile() {
    currentIndex = (currentIndex - 1 + files.length) % files.length;
    updateModalContent();
}

// Arrow keys page the gallery while the preview is open
document.addEventListener('keydown', function (e) {
    var modal = document.getElementById('viewFileModal');
    if (!modal || !modal.classList.contains('show') || files.length < 2) {
        return;
    }
    if (e.key === 'ArrowRight') {
        nextFile();
    } else if (e.key === 'ArrowLeft') {
        prevFile();
    }
});
</script>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "modals/share_modal.php";
require_once "modals/file/file_delete.php";
//require_once "modals/document/document_add_from_template.php";
require_once "../includes/footer.php";
