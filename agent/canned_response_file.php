<?php

/*
 * ITFlow - GET /agent/canned_response_file.php
 * Streams a file that goes with a canned response, for download or inline
 * viewing, without exposing the uploads directory on-disk location. A shared
 * response's files are anyone's who works tickets; an agent's own are theirs
 * (and admins').
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

if (!isset($_GET['file_id'])) {
    http_response_code(400);
    exit("File ID required");
}

enforceUserPermission('module_support');

$file_id = intval($_GET['file_id']);
$disposition = isset($_GET['action']) && $_GET['action'] === 'view' ? 'inline' : 'attachment';

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT canned_response_file_id, canned_response_file_name, canned_response_file_reference_name, canned_response_id, canned_response_user_id
    FROM canned_response_files JOIN canned_responses ON canned_response_id = canned_response_file_canned_response_id
    WHERE canned_response_file_id = $file_id LIMIT 1"));

if (!$row || (!$session_is_admin && !cannedResponseUsable($row, $session_user_id))) {
    http_response_code(404);
    exit("File not found");
}

// Path traversal guard - the resolved path must stay inside uploads
$uploads_base = realpath(__DIR__ . "/../uploads");
$file_path = realpath(cannedResponseFileDir($row['canned_response_id']) . basename($row['canned_response_file_reference_name']));
if ($file_path === false || $uploads_base === false || strpos($file_path, $uploads_base) !== 0) {
    http_response_code(404);
    exit("File not found");
}

// The type from what is in it, not what it was called
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

// HTML, SVG and the like are never shown inline - stored XSS
if ($disposition === 'inline' && !in_array($mime_type, getInlineViewableMimeTypes(), true)) {
    $disposition = 'attachment';
}

$safe_name = str_replace(['"', "\r", "\n"], '', basename($row['canned_response_file_name']));

header("Content-Type: $mime_type");
header("Content-Disposition: $disposition; filename=\"$safe_name\"");
header("Content-Length: " . filesize($file_path));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, max-age=3600");

while (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;
