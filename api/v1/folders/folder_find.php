<?php

// The folder a request names (folder_id) - of a client the key's user can reach, and
// client_id's when it is sent - as $folder, or null. $client_id becomes its client.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';

$folder_id = intval($_POST['folder_id'] ?? 0);
$folder = $folder_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT folder_id, folder_name, parent_folder, folder_client_id FROM folders WHERE folder_id = $folder_id "
    . clientScopeSql('folder_client_id') . ($client_id ? " AND folder_client_id = $client_id" : '') . " LIMIT 1")) : null;
if ($folder && !apiUserCanAccessClient($folder['folder_client_id'])) {
    $folder = null;
}
if ($folder) {
    $client_id = intval($folder['folder_client_id']);
}
