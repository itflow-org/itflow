<?php

/*
 * ITFlow - GET/POST request handler for folders
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['create_folder'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $folder_name = escapeSql(trim($_POST['folder_name'] ?? ''));

    enforceClientAccess();

    // Under one of this client's folders, or at the top level
    $parent_folder = folderOwnedId($_POST['parent_folder'] ?? 0, $client_id);

    if ($folder_name === '') {
        flashAlert('A folder needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO folders SET folder_name = '$folder_name', parent_folder = $parent_folder, folder_location = 0, folder_client_id = $client_id");
    $folder_id = mysqli_insert_id($mysqli);

    logAudit("Folder", "Create", "$session_name created folder $folder_name", $client_id, $folder_id);

    flashAlert("Folder <strong>" . trim($_POST['folder_name']) . "</strong> created");

    redirect("files.php?client_id=$client_id&folder_id=$folder_id");

}

if (isset($_POST['rename_folder'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $folder_id = intval($_POST['folder_id']);
    $folder_name = escapeSql(trim($_POST['folder_name'] ?? ''));

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT folder_name, folder_client_id, parent_folder FROM folders WHERE folder_id = $folder_id"));
    if (!$row) {
        flashAlert('That folder no longer exists.', 'error');
        redirect();
    }
    $old_folder_name = escapeSql($row['folder_name']);
    $client_id = intval($row['folder_client_id']);

    enforceClientAccess();

    if ($folder_name === '') {
        flashAlert('A folder needs a name.', 'error');
        redirect();
    }

    // Moved as well? Only under one of this client's folders, and never into itself or below itself
    $parent_folder = intval($row['parent_folder']);
    if (isset($_POST['parent_folder'])) {
        $new_parent = folderOwnedId($_POST['parent_folder'], $client_id);
        if (!in_array($new_parent, folderSubtree(folderTree($client_id), $folder_id), true)) {
            $parent_folder = $new_parent;
        }
    }

    mysqli_query($mysqli, "UPDATE folders SET folder_name = '$folder_name', parent_folder = $parent_folder WHERE folder_id = $folder_id");

    if ($old_folder_name !== $folder_name) {
        logAudit("Folder", "Rename", "$session_name renamed folder $old_folder_name to $folder_name", $client_id, $folder_id);
    }
    if ($parent_folder !== intval($row['parent_folder'])) {
        logAudit("Folder", "Move", "$session_name moved folder $folder_name into " . escapeSql(folderDisplayName($parent_folder)), $client_id, $folder_id);
    }

    flashAlert("Folder <strong>" . trim($_POST['folder_name']) . "</strong> saved");

    redirect();

}

/*
 * A folder dragged onto another on the Files page (agent/js/files.js), or onto the top level.
 * What is in it goes with it.
 */
if (isset($_POST['move_folder'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $folder_id = intval($_POST['folder_id']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT folder_name, folder_client_id FROM folders WHERE folder_id = $folder_id"));
    if (!$row) {
        flashAlert('That folder no longer exists.', 'error');
        redirect();
    }
    $folder_name = $row['folder_name'];
    $client_id = intval($row['folder_client_id']);

    enforceClientAccess();

    // Only under one of this client's folders - one that has gone isn't taken as the top level
    $wanted = intval($_POST['parent_folder'] ?? 0);
    $parent_folder = folderOwnedId($wanted, $client_id);
    if ($parent_folder !== $wanted) {
        flashAlert('The folder you dropped it on no longer exists.', 'error');
        redirect();
    }

    // Never into itself or below itself
    $tree = folderTree($client_id);
    if (in_array($parent_folder, folderSubtree($tree, $folder_id), true)) {
        flashAlert("A folder can't go inside itself or one of its own folders.", 'error');
        redirect();
    }

    if ($parent_folder === intval($tree[$folder_id]['parent_folder'] ?? -1)) {
        redirect();
    }

    $parent_name = folderDisplayName($parent_folder);

    mysqli_query($mysqli, "UPDATE folders SET parent_folder = $parent_folder WHERE folder_id = $folder_id");

    logAudit("Folder", "Move", "$session_name moved folder " . escapeSql($folder_name) . " into " . escapeSql($parent_name), $client_id, $folder_id);

    flashAlert("Folder <strong>$folder_name</strong> moved into <strong>$parent_name</strong>");
    $parent_before = intval($tree[$folder_id]['parent_folder'] ?? 0);
    undoOffer("Folder <strong>" . escapeHtml($folder_name) . "</strong> is back in <strong>" . escapeHtml(folderDisplayName($parent_before)) . "</strong>",
        [['folder', $folder_id, $parent_before, $parent_folder, $client_id]], 'module_support');

    redirect();

}

/*
 * Deleting a folder never deletes what is in it: files, documents and
 * subfolders move up to the folder it sat in. (Only documents used to be
 * moved - files and subfolders were left pointing at a folder that no
 * longer existed, and dropped out of sight.)
 */
if (isset($_POST['delete_folder']) || isset($_GET['delete_folder'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $folder_id = intval($_POST['folder_id'] ?? $_GET['delete_folder']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT folder_name, folder_client_id, parent_folder FROM folders WHERE folder_id = $folder_id"));
    if (!$row) {
        flashAlert('That folder no longer exists.', 'error');
        redirect();
    }
    $folder_name = escapeSql($row['folder_name']);
    $client_id = intval($row['folder_client_id']);

    enforceClientAccess();

    $parent_folder = folderOwnedId($row['parent_folder'], $client_id);

    mysqli_query($mysqli, "UPDATE files SET file_folder_id = $parent_folder WHERE file_folder_id = $folder_id AND file_client_id = $client_id");
    mysqli_query($mysqli, "UPDATE documents SET document_folder_id = $parent_folder, document_updated_at = document_updated_at WHERE document_folder_id = $folder_id AND document_client_id = $client_id");
    mysqli_query($mysqli, "UPDATE folders SET parent_folder = $parent_folder WHERE parent_folder = $folder_id AND folder_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM folders WHERE folder_id = $folder_id");

    logAudit("Folder", "Delete", "$session_name deleted folder $folder_name, its contents moved to " . escapeSql(folderDisplayName($parent_folder)), $client_id);

    flashAlert("Folder <strong>{$row['folder_name']}</strong> deleted", 'error');

    redirect("files.php?client_id=$client_id&folder_id=$parent_folder");

}
