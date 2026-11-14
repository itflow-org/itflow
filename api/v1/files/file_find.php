<?php

// The file a request names (file_id) - of a client the key's user can reach, and
// client_id's when it is sent - as $file, or null. $client_id becomes its client.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';

$file_id = intval($_POST['file_id'] ?? 0);
$file = $file_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_id, file_name, file_reference_name, file_archived_at, file_folder_id, file_client_id FROM files WHERE file_id = $file_id "
    . clientScopeSql('file_client_id') . ($client_id ? " AND file_client_id = $client_id" : '') . " LIMIT 1")) : null;
if ($file && !apiUserCanAccessClient($file['file_client_id'])) {
    $file = null;
}
if ($file) {
    $client_id = intval($file['file_client_id']);
    $file_label = escapeSql($file['file_name']);
}
