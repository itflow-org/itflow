<?php

/*
 * Download the file a shared link shares (functions/share.php). Posted by
 * js/guest_share.js with the link's id and secret - never a GET, so the
 * secret stays out of the address and the logs. Uses a view, after the
 * passphrase or code the link needs, unless this browser downloaded it in
 * the last SHARE_GRACE_MINUTES. Anything wrong comes back as JSON.
 */

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";
require_once "../includes/session_init.php";

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;

/** Stop with a reason, as JSON for the page to show. */
function shareDownloadStop($message, $gone = false) {
    header('Content-Type: application/json');
    exit(json_encode($gone ? ['state' => 'gone', 'reason' => $message] : ['error' => $message]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    shareDownloadStop('Open the link you were sent, and download from there.');
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

shareSweep();
$row = shareFind(intval($input['id'] ?? 0), (string) ($input['key'] ?? ''));
if (!$row || $row['item_type'] !== 'File') {
    shareDownloadStop('The link is not right - check you have all of it.', true);
}

$state = shareState($row);
$again = shareOpenedHere($row);
if (!($state === 'active' || ($state === 'used' && $again))) {
    shareDownloadStop($state === 'expired' ? 'It has expired.' : ($state === 'used' ? 'It has been downloaded as many times as it allowed.' : 'It was turned off.'), true);
}
if (!shareVerified($row)) {
    shareDownloadStop('Enter the passphrase or code first.');
}

$file = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name, file_reference_name, file_client_id FROM files
    WHERE file_id = " . intval($row['item_related_id']) . " AND file_client_id = " . intval($row['item_client_id']) . " LIMIT 1"));
$client_dir = realpath(dirname(__DIR__) . '/uploads/clients/' . intval($row['item_client_id']));
$path = $file ? realpath(dirname(__DIR__) . '/uploads/clients/' . intval($row['item_client_id']) . '/' . basename($file['file_reference_name'])) : false;

// Don't use a view on a file that is missing from disk
if (!$file || !$client_dir || !$path || strpos($path, $client_dir . DIRECTORY_SEPARATOR) !== 0 || !is_readable($path)) {
    shareDownloadStop('The file it shared has been removed.', true);
}

if (!$again) {
    // The claim, before a byte is sent - racing requests cannot both win the last view
    if (!shareClaim($row['item_id'])) {
        shareDownloadStop('It has been downloaded as many times as it allowed.', true);
    }
    shareSessionMark($row['item_id'], 'opened');
    $who = shareProtection($row) === 'code' ? ' by ' . $row['item_recipient'] . ' (emailed code)' : (shareProtection($row) === 'passphrase' ? ' with the passphrase' : '');
    logAudit('Share', 'Download', escapeSql("Downloaded$who"), intval($row['item_client_id']), intval($row['item_id']));
    shareNotifyCreator($row, 'Shared link opened', 'File ' . $file['file_name'] . " was downloaded$who - " . $row['client_name']);
}

// What the page says after - whether it downloads again
$after_row = shareRow($row['item_id']);
$left = intval($after_row['item_view_limit']) - intval($after_row['item_views']);
$after = intval($after_row['item_view_limit']) && $left <= 0
    ? 'That was the last download this link allows.'
    : (intval($after_row['item_view_limit']) ? "It can be downloaded $left more " . ($left === 1 ? 'time' : 'times') . '.' : 'The link keeps working until ' . date('M j \a\t g:i A', strtotime($after_row['item_expire_at'])) . '.');

$name = str_replace(["\r", "\n", '"'], ['', '', "'"], (string) $file['file_name']);
$ascii = preg_replace('/[^\x20-\x7E]/', '_', $name);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('X-Share-After: ' . rawurlencode($after));

while (ob_get_level()) {
    ob_end_clean();
}
readfile($path);
exit;
