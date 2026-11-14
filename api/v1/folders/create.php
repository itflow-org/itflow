<?php
/*
 * API - Folders - Create
 * POST /api/v1/folders/create.php
 *
 * Add a folder to a client's Files, as New Folder does - at the top level, or in
 * one of the client's folders.
 *
 * Parameters (POST, JSON body): api_key, client_id (required), folder_name (required),
 * parent_folder (optional - one of the client's folders)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once '../form_input.php';

$insert_id = false;

if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id"))) {
    apiFormRefuse('client_id is required - the client the folder is for');
}
$name = trim((string) (is_scalar($_POST['folder_name'] ?? '') ? ($_POST['folder_name'] ?? '') : ''));
if ($name === '') {
    apiFormRefuse('A folder needs a name - folder_name');
}
$parent = folderOwnedId($_POST['parent_folder'] ?? 0, $client_id);
if (intval($_POST['parent_folder'] ?? 0) && !$parent) {
    apiFormRefuse("parent_folder isn't one of the client's folders");
}

$name_sql = escapeSql(mb_substr($name, 0, 200));
mysqli_query($mysqli, "INSERT INTO folders SET folder_name = '$name_sql', parent_folder = $parent, folder_location = 0, folder_client_id = $client_id");
$insert_id = intval(mysqli_insert_id($mysqli)) ?: false;

if ($insert_id) {
    logAudit("Folder", "Create", "Created folder $name_sql via API ($api_key_name)", $client_id, $insert_id);
    logAudit("API", "Success", "Created folder $name_sql via API ($api_key_name)", $client_id);
}

// Output
require_once '../create_output.php';
