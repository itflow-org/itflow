<?php
/*
 * API - Files - Download
 * GET /api/v1/files/download.php
 *
 * A file's content - the file itself, not JSON - as Download does. Not found is
 * a 404 with the usual JSON.
 *
 *   file_id    required - The file
 *   client_id  optional - Only if it is this client's
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$file_id = intval($_GET['file_id'] ?? 0);
$sql = $file_id ? mysqli_query($mysqli, "SELECT file_id, file_name, file_reference_name, file_mime_type, file_client_id FROM files WHERE file_id = $file_id " . apiClientScopeSql('file_client_id')) : false;
$row = $sql ? mysqli_fetch_assoc($sql) : null;
$path = $row ? __DIR__ . '/../../../uploads/clients/' . intval($row['file_client_id']) . '/' . basename((string) $row['file_reference_name']) : '';

if (!$row || !is_file($path)) {
    http_response_code(404);
    $return_arr['success'] = "False";
    $return_arr['message'] = "No file with that file_id that the key's user can see.";
    echo json_encode($return_arr);
    exit();
}

mysqli_query($mysqli, "UPDATE files SET file_accessed_at = NOW() WHERE file_id = $file_id");
logAudit("File", "Download", "Downloaded file " . escapeSql($row['file_name']) . " via API ($api_key_name)", intval($row['file_client_id']), $file_id);

// The file as it was stored, never run or shown - a download
header_remove('Content-Type');
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename((string) $row['file_name'])) . '"; filename*=UTF-8\'\'' . rawurlencode(basename((string) $row['file_name'])));
header('Content-Length: ' . filesize($path));
readfile($path);
exit();
