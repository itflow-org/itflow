<?php

/*
 * ITFlow - GET /client/file.php
 * Client Portal - streams a document-attached file for download or
 * inline viewing without exposing the uploads directory location
 *
 * Files are only client-accessible through their link to a visible,
 * unarchived document - matching the scoping in client/document.php
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

// Documents section is for primary / technical contacts only -
// same gate as client/document.php
if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    http_response_code(404);
    exit("File not found");
}

// Require a file ID
if (!isset($_GET['file_id'])) {
    http_response_code(400);
    exit("File ID required");
}

$file_id = intval($_GET['file_id']);

// Disposition: download by default, inline only when explicitly requested
$disposition = "attachment";
if (isset($_GET['action']) && $_GET['action'] == "view") {
    $disposition = "inline";
}

// Look up the file, enforcing the full visibility chain in the query:
// the file must belong to this client AND be attached to a document
// that is client-visible, this client's, and not archived.
// An out-of-scope file is indistinguishable from a nonexistent one.
$sql = mysqli_query($mysqli,
    "SELECT f.file_name, f.file_reference_name
     FROM files f
     INNER JOIN document_files df ON f.file_id = df.file_id
     INNER JOIN documents d ON df.document_id = d.document_id
     WHERE f.file_id = $file_id
     AND f.file_client_id = $session_client_id
     AND d.document_client_id = $session_client_id
     AND d.document_client_visible = 1
     AND d.document_archived_at IS NULL
     LIMIT 1"
);

if (mysqli_num_rows($sql) !== 1) {
    http_response_code(404);
    exit("File not found");
}

$row = mysqli_fetch_array($sql);
$file_name = $row['file_name'];
$file_name_escaped = escapeSql($row['file_name']);
$file_reference_name = $row['file_reference_name'];

// Build the on-disk path
$uploads_base = realpath(__DIR__ . "/../uploads");
$file_path = realpath(__DIR__ . "/../uploads/clients/$session_client_id/$file_reference_name");

// Path traversal guard - resolved path must stay inside uploads
if ($file_path === false || $uploads_base === false || strpos($file_path, $uploads_base) !== 0) {
    http_response_code(404);
    exit("File not found");
}

// Detect MIME from file content - files can be uploaded by portal
// contacts, so the stored MIME type is not trustworthy
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$file_mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

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

// Strip characters that would break the Content-Disposition header
$safe_file_name = str_replace(array('"', "\r", "\n"), "", basename($file_name));

// Caching - allow the browser to reuse the file privately and revalidate cheaply
$file_mtime = filemtime($file_path);
$etag = '"' . md5($file_mtime . filesize($file_path) . $file_id) . '"';

header("Cache-Control: private, max-age=3600");
header("ETag: $etag");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $file_mtime) . " GMT");

// Conditional GET - browser already has this version, send 304 and stop
if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)
    || (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $file_mtime)
) {
    http_response_code(304);
    exit;
}

// Audit log
logAudit("File", "Download", "Client contact $session_contact_name viewed file $file_name_escaped via portal", $session_client_id);

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