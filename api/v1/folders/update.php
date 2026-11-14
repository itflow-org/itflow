<?php
/*
 * API - Folders - Update
 * POST /api/v1/folders/update.php
 *
 * Rename a folder, or move it into another of the client's folders - never into
 * itself or one inside it - as Rename does. Only what is sent.
 *
 * Parameters (POST, JSON body): api_key, folder_id, client_id (its client - needed when
 * the key's user is limited to certain clients), folder_name, parent_folder (0: the top level)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'folder_find.php';

$update_count = false;

if ($folder) {
    $name = array_key_exists('folder_name', $_POST) ? trim((string) (is_scalar($_POST['folder_name']) ? $_POST['folder_name'] : '')) : (string) $folder['folder_name'];
    if ($name === '') {
        apiFormRefuse('A folder needs a name');
    }
    $parent = intval($folder['parent_folder']);
    if (array_key_exists('parent_folder', $_POST)) {
        $parent = folderOwnedId($_POST['parent_folder'], $client_id);
        if (intval($_POST['parent_folder']) && !$parent) {
            apiFormRefuse("parent_folder isn't one of the client's folders");
        }
        if (in_array($parent, folderSubtree(folderTree($client_id), $folder_id), true)) {
            apiFormRefuse("A folder can't go inside itself, or a folder inside it");
        }
    }
    $name_sql = escapeSql(mb_substr($name, 0, 200));
    if (mysqli_query($mysqli, "UPDATE folders SET folder_name = '$name_sql', parent_folder = $parent WHERE folder_id = $folder_id")) {
        $update_count = 1;
        logAudit("Folder", "Edit", "Changed folder $name_sql via API ($api_key_name)", $client_id, $folder_id);
        logAudit("API", "Success", "Changed folder $name_sql via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
