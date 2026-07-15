<?php

/*
 * ITFlow - GET /agent/file.php
 * Streams a client file for download or inline viewing
 * without exposing the uploads directory on-disk location
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

// Require a file ID
if (!isset($_GET['file_id'])) {
    http_response_code(400);
    exit("File ID required");
}

$file_id = intval($_GET['file_id']);

// Enforce client scope - user must be permitted to access clients
enforceUserPermission('module_client');

// Disposition: download by default, inline only when explicitly requested
$disposition = "attachment";
if (isset($_GET['action']) && $_GET['action'] == "view") {
    $disposition = "inline";
}

// Thumbnail mode - inline image for grid/preview use, not audit logged
$thumb = false;
if (isset($_GET['thumb']) && intval($_GET['thumb']) == 1) {
    $thumb = true;
    $disposition = "inline";
}

// Look up the file
$sql = mysqli_query($mysqli, "SELECT * FROM files WHERE file_id = $file_id LIMIT 1");

if (mysqli_num_rows($sql) !== 1) {
    http_response_code(404);
    exit("File not found");
}

$row = mysqli_fetch_array($sql);
$client_id = intval($row['file_client_id']);
$file_name = $row['file_name'];
$file_name_escaped = escapeSql($row['file_name']);
$file_reference_name = $row['file_reference_name'];
$file_mime_type = $row['file_mime_type'];

// Enforce Client Access
enforceClientAccess();

// MIME types that are safe to render inline in the browser
// Everything else (esp. HTML/SVG - stored XSS risk) falls back to download
$inline_allowed_mime_types = array(
    "application/pdf",
    "image/png",
    "image/jpeg",
    "image/gif",
    "image/webp",
    "text/plain"
);

if ($disposition == "inline" && !in_array($file_mime_type, $inline_allowed_mime_types, true)) {
    $disposition = "attachment";
}

// Build the on-disk path, anchored to this file's directory
$uploads_base = realpath(__DIR__ . "/../uploads");
$file_path = realpath(__DIR__ . "/../uploads/clients/$client_id/$file_reference_name");

// Path traversal guard - resolved path must stay inside uploads
if ($file_path === false || $uploads_base === false || strpos($file_path, $uploads_base) !== 0) {
    http_response_code(404);
    exit("File not found");
}

// Strip characters that would break the Content-Disposition header
$safe_file_name = str_replace(array('"', "\r", "\n"), "", basename($file_name));

// Audit log - skip thumbnail loads so grid views don't flood the log
if (!$thumb) {
    logAudit("File", "Download", "$session_name viewed file $file_name_escaped", $client_id);
}

// Caching - allow the browser to reuse the file privately and revalidate cheaply
$file_mtime = filemtime($file_path);
$etag = '"' . md5($file_mtime . filesize($file_path) . $file_id) . '"';

header("Cache-Control: private, max-age=3600");
header("ETag: $etag");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $file_mtime) . " GMT");

// Conditional GET - if the browser already has this version, send 304 and stop
if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)
    || (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $file_mtime)
) {
    http_response_code(304);
    exit;
}

// Send the file
header("Content-Type: $file_mime_type");
header("Content-Disposition: $disposition; filename=\"$safe_file_name\"");
header("Content-Length: " . filesize($file_path));
header("X-Content-Type-Options: nosniff");

// Clear output buffers so large files stream instead of loading into memory
while (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;
