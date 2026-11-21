<?php
/*
 * API - Folders - Delete
 * POST /api/v1/folders/delete.php
 *
 * Delete a folder, as Delete does - never what is in it: its files, documents and
 * folders move up to the folder it was in.
 *
 * Parameters (POST, JSON body): api_key, folder_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'folder_find.php';

$delete_count = false;

if ($folder) {
    $parent = folderOwnedId($folder['parent_folder'], $client_id);
    mysqli_query($mysqli, "UPDATE files SET file_folder_id = $parent WHERE file_folder_id = $folder_id AND file_client_id = $client_id");
    mysqli_query($mysqli, "UPDATE documents SET document_folder_id = $parent, document_updated_at = document_updated_at WHERE document_folder_id = $folder_id AND document_client_id = $client_id");
    mysqli_query($mysqli, "UPDATE folders SET parent_folder = $parent WHERE parent_folder = $folder_id AND folder_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM folders WHERE folder_id = $folder_id");
    $delete_count = mysqli_affected_rows($mysqli);
    if ($delete_count > 0) {
        $label = escapeSql($folder['folder_name']);
        logAudit("Folder", "Delete", "Deleted folder $label, its contents moved to " . escapeSql(folderDisplayName($parent)) . " via API ($api_key_name)", $client_id);
        logAudit("API", "Success", "Deleted folder $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
