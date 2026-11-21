<?php
/*
 * API - Files - Update
 * POST /api/v1/files/update.php
 *
 * Rename a file, change its description, or move it to another of the client's
 * folders, as Rename and Move do - only what is sent.
 *
 * Parameters (POST, JSON body):
 *   api_key           required - Your API key
 *   file_id           required - The file
 *   client_id         its client - needed when the key's user is limited to certain clients
 *   file_name, file_description   optional
 *   folder_id         optional - One of the client's folders, or 0 for the top level
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'file_find.php';

$update_count = false;

if ($file) {
    $set = [];
    if (array_key_exists('file_name', $_POST)) {
        $name = trim((string) (is_scalar($_POST['file_name']) ? $_POST['file_name'] : ''));
        if ($name === '') {
            apiFormRefuse('A file needs a name');
        }
        $set[] = "file_name = '" . escapeSql(mb_substr($name, 0, 200)) . "'";
    }
    if (array_key_exists('file_description', $_POST)) {
        $set[] = "file_description = '" . escapeSql((string) (is_scalar($_POST['file_description']) ? $_POST['file_description'] : '')) . "'";
    }
    if (array_key_exists('folder_id', $_POST)) {
        $folder_id = folderOwnedId($_POST['folder_id'], $client_id);
        if (intval($_POST['folder_id']) && !$folder_id) {
            apiFormRefuse("folder_id isn't one of the client's folders");
        }
        $set[] = "file_folder_id = $folder_id";
    }
    if (!$set) {
        apiFormRefuse('Send at least one of file_name, file_description or folder_id');
    }
    if (mysqli_query($mysqli, "UPDATE files SET " . implode(', ', $set) . " WHERE file_id = $file_id")) {
        $update_count = 1;
        logAudit("File", "Edit", "Changed file $file_label via API ($api_key_name)", $client_id, $file_id);
        logAudit("API", "Success", "Changed file $file_label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
