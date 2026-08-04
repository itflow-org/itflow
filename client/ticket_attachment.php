<?php

/*
 * ITFlow - GET /client/ticket_attachment.php
 * Client Portal - streams a ticket attachment for download or inline
 * viewing without exposing the uploads directory on-disk location
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

// Require an attachment ID
if (!isset($_GET['attachment_id'])) {
    http_response_code(400);
    exit("Attachment ID required");
}

$attachment_id = intval($_GET['attachment_id']);

// Disposition: download by default, inline only when explicitly requested
$disposition = "attachment";
if (isset($_GET['action']) && $_GET['action'] == "view") {
    $disposition = "inline";
}

// Look up the attachment
$sql = mysqli_query($mysqli, "SELECT * FROM ticket_attachments WHERE ticket_attachment_id = $attachment_id LIMIT 1");

if (mysqli_num_rows($sql) !== 1) {
    http_response_code(404);
    exit("Attachment not found");
}

$row = mysqli_fetch_array($sql);
$ticket_id = intval($row['ticket_attachment_ticket_id']);
$attachment_name = $row['ticket_attachment_name'];
$attachment_name_escaped = escapeSql($row['ticket_attachment_name']);
$attachment_reference_name = $row['ticket_attachment_reference_name'];

// Verify the contact can access this ticket (any state - attachments
// on closed tickets remain downloadable)
// 404 (not 403) so out-of-scope attachments are indistinguishable from
// nonexistent ones
if (!verifyContactTicketAccess($ticket_id, "Any")) {
    http_response_code(404);
    exit("Attachment not found");
}

// Build the on-disk path
$uploads_base = realpath(__DIR__ . "/../uploads");
$file_path = realpath(__DIR__ . "/../uploads/tickets/$ticket_id/$attachment_reference_name");

// Path traversal guard - resolved path must stay inside uploads
if ($file_path === false || $uploads_base === false || strpos($file_path, $uploads_base) !== 0) {
    http_response_code(404);
    exit("Attachment not found");
}

// No MIME column on ticket_attachments - detect from file content.
// Attachments may come from email senders, so never trust a declared type.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$attachment_mime_type = finfo_file($finfo, $file_path);
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

if ($disposition == "inline" && !in_array($attachment_mime_type, $inline_allowed_mime_types, true)) {
    $disposition = "attachment";
}

// Strip characters that would break the Content-Disposition header
$safe_attachment_name = str_replace(array('"', "\r", "\n"), "", basename($attachment_name));

// Caching - allow the browser to reuse the file privately and revalidate cheaply
$file_mtime = filemtime($file_path);
$etag = '"' . md5($file_mtime . filesize($file_path) . $attachment_id) . '"';

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
logAudit("Ticket", "Download", "Client contact $session_contact_name viewed ticket attachment $attachment_name_escaped via portal", $session_client_id);

// Send the file
header("Content-Type: $attachment_mime_type");
header("Content-Disposition: $disposition; filename=\"$safe_attachment_name\"");
header("Content-Length: " . filesize($file_path));
header("X-Content-Type-Options: nosniff");

// Clear output buffers so large files stream instead of loading into memory
while (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;