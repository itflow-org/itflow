<?php
/*
 * API - Files - Create
 * POST /api/v1/files/create.php
 *
 * Upload a file to a client's Files, as Upload does - one of the kinds Files take
 * (fileUploadExtensions()), not empty - into one of the client's folders, linked
 * to its contact, asset, license, location or vendor if asked. The content comes
 * base64 in the JSON body, so the server's post_max_size caps how big.
 *
 * Parameters (POST, JSON body):
 *   api_key           required - Your API key
 *   client_id         required - The client
 *   file_name         required - Its name, with its extension - such as firewall-backup.cfg
 *   file_data         required - Its content, base64
 *   file_description  optional
 *   folder_id         optional - One of the client's folders; the top level if left out
 *   contact_id, asset_id, software_id, location_id, vendor_id   optional - The client's, to link it to
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once '../form_input.php';

$insert_id = false;

if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id"))) {
    apiFormRefuse('client_id is required - the client the file is for');
}

$original_name = basename(str_replace('\\', '/', trim((string) (is_scalar($_POST['file_name'] ?? '') ? ($_POST['file_name'] ?? '') : ''))));
$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if ($original_name === '' || $extension === '' || !in_array($extension, fileUploadExtensions(), true)) {
    apiFormRefuse('file_name is its name with its extension - one of: ' . implode(', ', fileUploadExtensions()));
}
$data = base64_decode((string) (is_scalar($_POST['file_data'] ?? '') ? ($_POST['file_data'] ?? '') : ''), true);
if ($data === false || $data === '') {
    apiFormRefuse('file_data is the file, base64 - and not empty');
}
if (strlen($data) > 500 * 1024 * 1024) {
    apiFormRefuse('A file is up to 500 MB');
}

// Only the client's own folder, contact, asset, license, location and vendor
$folder_id = folderOwnedId($_POST['folder_id'] ?? 0, $client_id);
$links = [];
foreach (['contact' => 'contact_id', 'location' => 'location_id', 'vendor' => 'vendor_id'] as $kind => $field) {
    $links[$kind] = intval(assetClientOwnedId($kind, $_POST[$field] ?? 0, $client_id));
}
foreach (['asset' => 'asset_id', 'software' => 'software_id'] as $kind => $field) {
    $record = linkRecord($kind, $_POST[$field] ?? 0);
    $links[$kind] = $record && intval($record['client_id']) === $client_id ? intval($record['id']) : 0;
}
foreach (['folder_id' => $folder_id, 'contact_id' => $links['contact'], 'asset_id' => $links['asset'], 'software_id' => $links['software'], 'location_id' => $links['location'], 'vendor_id' => $links['vendor']] as $field => $found) {
    if (intval($_POST[$field] ?? 0) && !$found) {
        apiFormRefuse("$field isn't one of the client's");
    }
}

$client_dir = __DIR__ . "/../../../uploads/clients/$client_id";
if (!is_dir($client_dir)) {
    mkdir($client_dir, 0755, true);
}
// An unguessable name, as an upload gets (checkFileUpload())
$file_reference_name = randomString(32) . '.' . $extension;
$dest_path = "$client_dir/$file_reference_name";
if (file_put_contents($dest_path, $data) === false) {
    apiFormRefuse("The file couldn't be saved - check the uploads folder can be written to");
}

// What the file is, read from the file
$mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $dest_path) : '';
$mime = $mime !== '' ? $mime : 'application/octet-stream';
$description = escapeSql(trim((string) (is_scalar($_POST['file_description'] ?? '') ? ($_POST['file_description'] ?? '') : '')));
$name_sql = escapeSql(mb_substr($original_name, 0, 200));

mysqli_query($mysqli, "INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$name_sql', file_description = '$description', file_ext = '" . escapeSql($extension) . "',
    file_mime_type = '" . escapeSql($mime) . "', file_size = " . strlen($data) . ", file_created_by = " . intval($session_user_id) . ", file_folder_id = $folder_id, file_client_id = $client_id");
$file_id = intval(mysqli_insert_id($mysqli));

if ($file_id) {
    $insert_id = $file_id;
    foreach (['contact' => 'contact_files', 'asset' => 'asset_files', 'software' => 'software_files', 'location' => 'location_files', 'vendor' => 'vendor_files'] as $kind => $table) {
        if ($links[$kind]) {
            mysqli_query($mysqli, "INSERT IGNORE INTO $table SET {$kind}_id = {$links[$kind]}, file_id = $file_id");
        }
    }
    logAudit("File", "Upload", "Uploaded file $name_sql via API ($api_key_name)", $client_id, $file_id);
    logAudit("API", "Success", "Uploaded file $name_sql via API ($api_key_name)", $client_id);
} else {
    unlink($dest_path);
}

// Output
require_once '../create_output.php';
