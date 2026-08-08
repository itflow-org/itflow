<?php

/*
 * ITFlow - GET /admin/backup_download.php
 * Streams a backup archive to an administrator
 *
 * Deliberately NOT agent/file.php: that gates on module_client and resolves paths under
 * uploads/clients/<id>/, which would hand a full database dump to any agent with client
 * read access. A backup is an admin artifact and gets an admin-only path of its own.
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

enforceAdminPermission();
validateCSRFToken();

if (!isset($_GET['backup_id'])) {
    http_response_code(400);
    exit("Backup ID required");
}

$backup_id = intval($_GET['backup_id']);

$sql = mysqli_query($mysqli, "SELECT backup_file_name FROM backups WHERE backup_id = $backup_id AND backup_status = 'Complete' LIMIT 1");

if (mysqli_num_rows($sql) !== 1) {
    http_response_code(404);
    exit("Backup not found");
}

$row = mysqli_fetch_assoc($sql);

$file_path = backupResolvePath($row['backup_file_name']);

if ($file_path === false || !is_file($file_path)) {
    mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Missing' WHERE backup_id = $backup_id");
    http_response_code(404);
    exit("Backup file is no longer on disk");
}

$file_name = basename($file_path);

logAudit("Backup", "Download", ($session_name ?? 'Unknown User') . " downloaded backup " . escapeSql($file_name));
mysqli_query($mysqli, "UPDATE backups SET backup_downloaded_at = NOW() WHERE backup_id = $backup_id");

header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=\"$file_name\"");
header("Content-Length: " . filesize($file_path));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");
header("Pragma: no-cache");

// Clear output buffers so a multi-gigabyte archive streams instead of loading into memory
while (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;
