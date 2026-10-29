<?php

/*
 * ITFlow - GET /agent/expense_receipt.php?expense_id=
 * Streams an expense's receipt - downloaded by default, shown in the page
 * with &action=view (photos and PDFs only). Needs Financial, and access to
 * the expense's client when it has one. uploads/expenses/ itself is closed
 * to the web.
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

enforceUserPermission('module_financial');

$row = expenseRow(intval($_GET['expense_id'] ?? 0));
$path = $row ? expenseReceiptPath($row['expense_receipt']) : '';
if (!$row || $path === '') {
    http_response_code(404);
    exit("Receipt not found");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
finfo_close($finfo);

// Only photos and PDFs show in the page - anything else downloads
$disposition = ($_GET['action'] ?? '') === 'view' && in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) ? 'inline' : 'attachment';
$name = str_replace(['"', "\r", "\n", '\\'], '', expenseReceiptName($row));

$mtime = filemtime($path);
$etag = '"' . md5($mtime . filesize($path) . $row['expense_id']) . '"';
header("Cache-Control: private, max-age=3600");
header("ETag: $etag");
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

header("Content-Type: $mime");
header("Content-Disposition: $disposition; filename=\"$name\"; filename*=UTF-8''" . rawurlencode($name));
header("Content-Length: " . filesize($path));
header("X-Content-Type-Options: nosniff");

while (ob_get_level()) {
    ob_end_clean();
}
readfile($path);
exit;
