<?php

/*
 * A ticket's file, from its guest page (guest_view_ticket.php) - one on the
 * ticket itself or on a reply the client can read, never an internal note's,
 * an archived reply's, or the email the ticket was parsed from. The same
 * files the client portal shows (client/ticket_attachment.php), with the
 * ticket's link key in place of a sign-in (ticketByGuestKey()).
 *
 * Downloads by default; action=view opens it in the browser when it is a
 * type that is safe to (getInlineViewableMimeTypes()) - never HTML or SVG.
 * Anything not found, or not this ticket's, is the same 404.
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";
require_once "../includes/inc_set_timezone.php";

// logAudit() reads these
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$ticket = ticketByGuestKey($_GET['ticket_id'] ?? 0, $_GET['url_key'] ?? '');
$ticket_id = $ticket ? intval($ticket['ticket_id']) : 0;
$attachment_id = intval($_GET['attachment_id'] ?? 0);

$row = $ticket_id && $attachment_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_attachment_name, ticket_attachment_reference_name FROM ticket_attachments
    LEFT JOIN ticket_replies ON ticket_reply_id = ticket_attachment_reply_id
    WHERE ticket_attachment_id = $attachment_id AND ticket_attachment_ticket_id = $ticket_id AND ticket_attachment_name != 'Original-parsed-email.eml'
    AND (ticket_attachment_reply_id IS NULL OR (ticket_reply_type != 'Internal' AND ticket_reply_archived_at IS NULL))
    LIMIT 1")) : null;

$folder = realpath(__DIR__ . "/../uploads/tickets/$ticket_id");
$path = $row && $folder ? realpath("$folder/" . basename((string) $row['ticket_attachment_reference_name'])) : false;

if (!$row || !$folder || !$path || strpos($path, $folder . DIRECTORY_SEPARATOR) !== 0 || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('That file is not available.');
}

// No type is stored - read it from the file, as the senders of emailed files can't be trusted to say
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
finfo_close($finfo);

$inline = ($_GET['action'] ?? '') === 'view' && in_array($mime, getInlineViewableMimeTypes(), true);
$name = str_replace(['"', "\r", "\n"], '', basename((string) $row['ticket_attachment_name']));
$ascii = preg_replace('/[^\x20-\x7E]/', '_', $name);

logAudit("Ticket", "Download", "Guest " . ($inline ? 'viewed' : 'downloaded') . " ticket attachment " . escapeSql($name) . " on " . escapeSql(ticketReference($ticket)) . " via its guest link", intval($ticket['ticket_client_id']), $ticket_id);

header("Content-Type: $mime");
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

while (ob_get_level()) {
    ob_end_clean();
}
readfile($path);
exit;
