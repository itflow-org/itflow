<?php

/*
 * Microsoft 365 mail through Microsoft Graph.
 *
 * With Sending or Receiving on Microsoft 365 (Admin > Settings > Mail), the app
 * on the OAuth tab can have Microsoft Graph's Mail.Send and Mail.ReadWrite
 * permissions as well as - or instead of - Exchange's SMTP.Send and
 * IMAP.AccessAsUser.All. Then cron/mail_queue.php sends each email through
 * Graph (mailGraphSend()) and cron/ticket_email_parser.php reads the mailbox
 * through it (mailGraphMailbox(), functions/mail_graph_mailbox.php), so the
 * mailbox needs no SMTP AUTH - which Microsoft has off by default. Each side
 * goes through Graph when the app has its permission, and by SMTP or IMAP when
 * it doesn't: there is nothing to switch on.
 *
 * The mailbox's refresh token (from Connect Microsoft 365) is good for Graph as
 * well as Exchange: mailGraphToken() trades it for a Graph token, kept in
 * settings.config_mail_graph_token(_expires_at) with the permissions it carries
 * (config_mail_graph_scopes, database 3.9.2), and keeps the new refresh token
 * Microsoft hands back. An app that can't use Graph is asked again an hour
 * later - or straight away after the OAuth tab is saved or connected
 * (mailGraphForget()), or the sign-in is tested.
 */

defined('MAIL_GRAPH_URL') || define('MAIL_GRAPH_URL', 'https://graph.microsoft.com/v1.0');
defined('MAIL_MICROSOFT_LOGIN_URL') || define('MAIL_MICROSOFT_LOGIN_URL', 'https://login.microsoftonline.com/');

/*
 * What a renewal of the Exchange (IMAP and SMTP) sign-in asks for - named every
 * time, so a refresh token last renewed for Graph still gives an Exchange token.
 */
const MAIL_MICROSOFT_EXCHANGE_SCOPE = 'offline_access https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send';
/* A Graph token carries every Graph permission the app has been granted */
const MAIL_GRAPH_SCOPE = 'offline_access https://graph.microsoft.com/.default';
/* An app that couldn't use Graph is asked again this long after */
const MAIL_GRAPH_RECHECK_MINUTES = 60;
/* An email bigger than this goes as a draft with its attachments uploaded - Graph takes 4 MB in one request */
const MAIL_GRAPH_MIME_LIMIT = 3000000;
/* Upload-session chunk size - Microsoft wants a multiple of 320 KiB */
const MAIL_GRAPH_UPLOAD_CHUNK = 327680 * 12;

/* Whether the settings have the Graph columns yet - not until the 3.9.2 update has run */
function mailGraphReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        $result = mysqli_query($mysqli, "SHOW COLUMNS FROM settings LIKE 'config_mail_graph_scopes'");
        $ready = $result && mysqli_num_rows($result) > 0;
    }
    return $ready;
}

/* The mail settings Graph works from, read afresh - cron jobs share one process, and each may have renewed something - or null before 3.9.2 */
function mailGraphSettings() {
    global $mysqli;
    if (!mailGraphReady()) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_smtp_provider, config_imap_provider, config_mail_oauth_client_id,
        config_mail_oauth_client_secret, config_mail_oauth_tenant_id, config_mail_oauth_refresh_token, config_mail_graph_token,
        config_mail_graph_token_expires_at, config_mail_graph_scopes FROM settings WHERE company_id = 1"));
    return $row ?: null;
}

/* The Graph permissions in a token's scope, without Graph's address in front - Mail.Send, Mail.ReadWrite ... */
function mailGraphScopesOf($scope) {
    $scopes = [];
    foreach (preg_split('/\s+/', trim((string) $scope)) as $one) {
        if ($one !== '') {
            $scopes[] = preg_replace('#^https://graph\.microsoft\.com/#i', '', $one);
        }
    }
    return $scopes;
}

/*
 * A Graph token for the connected mailbox that carries $permission (Mail.Send,
 * Mail.ReadWrite), or null when it doesn't - the app hasn't the permission, or
 * the mail settings aren't Microsoft's - and SMTP or IMAP are used. Throws, with
 * Microsoft's reason, when mail goes through Graph and Microsoft refuses: that
 * is said where the email or the run stopped, rather than quietly trying SMTP
 * or IMAP, which may well be off. $fresh asks Microsoft again (the tests).
 */
function mailGraphToken($permission, $fresh = false) {
    global $mysqli;

    $row = mailGraphSettings();
    if (!$row) {
        return null;
    }
    $client_id = trim((string) $row['config_mail_oauth_client_id']);
    $secret = trim((string) $row['config_mail_oauth_client_secret']);
    $tenant = trim((string) $row['config_mail_oauth_tenant_id']);
    $refresh_token = trim((string) $row['config_mail_oauth_refresh_token']);
    if ($client_id === '' || $secret === '' || $tenant === '' || $refresh_token === '') {
        return null;
    }

    $had = mailGraphScopesOf($row['config_mail_graph_scopes']);
    $expires = strtotime((string) $row['config_mail_graph_token_expires_at']);
    if (!$fresh && $expires && $expires - 120 > time()) {
        $token = (string) $row['config_mail_graph_token'];
        return $token !== '' && in_array($permission, $had, true) ? $token : null;
    }

    $response = mailGraphHttp('POST', MAIL_MICROSOFT_LOGIN_URL . rawurlencode($tenant) . '/oauth2/v2.0/token', [], [
        'client_id' => $client_id,
        'client_secret' => $secret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
        'scope' => MAIL_GRAPH_SCOPE,
    ]);
    $token = (string) ($response['json']['access_token'] ?? '');

    if ($token === '') {
        if (array_intersect(['Mail.Send', 'Mail.ReadWrite'], $had)) {
            throw new RuntimeException(mailGraphError($response, 'Signing in to Microsoft Graph as the mailbox'));
        }
        // The app can't use Graph - SMTP and IMAP it is, until asked again
        $again = date('Y-m-d H:i:s', time() + MAIL_GRAPH_RECHECK_MINUTES * 60);
        mysqli_query($mysqli, "UPDATE settings SET config_mail_graph_token = NULL, config_mail_graph_token_expires_at = '$again', config_mail_graph_scopes = '' WHERE company_id = 1");
        return null;
    }

    // A token without the mail permissions is kept too - it is the answer until it runs out
    $scope = mb_substr((string) ($response['json']['scope'] ?? ''), 0, 2000);
    $expires_at = date('Y-m-d H:i:s', time() + max(300, intval($response['json']['expires_in'] ?? 3600)));
    $set = "config_mail_graph_token = '" . mysqli_real_escape_string($mysqli, $token) . "', config_mail_graph_token_expires_at = '$expires_at',
        config_mail_graph_scopes = '" . mysqli_real_escape_string($mysqli, $scope) . "'";
    // Microsoft hands back a new refresh token each time - the one to keep
    if (!empty($response['json']['refresh_token'])) {
        $set .= ", config_mail_oauth_refresh_token = '" . mysqli_real_escape_string($mysqli, (string) $response['json']['refresh_token']) . "'";
    }
    mysqli_query($mysqli, "UPDATE settings SET $set WHERE company_id = 1");

    return in_array($permission, mailGraphScopesOf($scope), true) ? $token : null;
}

/* Forget what Graph last said, so it is asked again when next needed - the app or its sign-in changed */
function mailGraphForget() {
    global $mysqli;
    if (mailGraphReady()) {
        mysqli_query($mysqli, "UPDATE settings SET config_mail_graph_token = NULL, config_mail_graph_token_expires_at = NULL, config_mail_graph_scopes = NULL WHERE company_id = 1");
    }
}

/*
 * What the mail settings page says about Graph, from what it last said - no
 * call to Microsoft: ['checked' => asked yet, 'send' => has Mail.Send, 'read'
 * => has Mail.ReadWrite], or null before 3.9.2.
 */
function mailGraphState() {
    $row = mailGraphSettings();
    if (!$row) {
        return null;
    }
    $scopes = mailGraphScopesOf($row['config_mail_graph_scopes']);
    return [
        'checked' => !empty($row['config_mail_graph_token_expires_at']),
        'send' => in_array('Mail.Send', $scopes, true),
        'read' => in_array('Mail.ReadWrite', $scopes, true),
    ];
}

/*
 * How mail goes, in a sentence or two - for each side on Microsoft 365 ($send_ms,
 * $read_ms), whether Graph has its permission.
 */
function mailGraphSays($send_ms, $read_ms, $graph_send, $graph_read) {
    $said = [];
    if ($send_ms) {
        $said[] = $graph_send ? 'Email is sent through Microsoft Graph.' : 'Email is sent by SMTP.';
    }
    if ($read_ms) {
        $said[] = $graph_read ? 'The mailbox is read through Microsoft Graph.' : 'The mailbox is read by IMAP.';
    }
    return implode(' ', $said);
}

/*
 * A request to Microsoft. $body: an array is sent as form fields, a string as
 * it is (JSON unless a Content-Type header says otherwise).
 * ['status' => HTTP status, 'json' => the reply read as JSON, 'body' => the reply, 'error' => curl's].
 */
function mailGraphHttp($method, $url, array $headers = [], $body = null, $timeout = 30) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
    if (is_array($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body, '', '&'));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } elseif (is_string($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        if (!preg_grep('/^Content-Type:/i', $headers)) {
            $headers[] = 'Content-Type: application/json';
        }
    } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, ''); // Microsoft wants a Content-Length even with nothing to send
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return ['status' => $status, 'json' => is_array($json) ? $json : [], 'body' => (string) $raw, 'error' => $error];
}

/* A Graph call with the token - $path from /me or /users/...; an array $body goes as JSON */
function mailGraph($method, $path, $token, $body = null) {
    return mailGraphHttp($method, MAIL_GRAPH_URL . $path, ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        $body === null ? null : json_encode($body));
}

/* Why a call to Microsoft failed, in Microsoft's words - with what to do, where the reason is a familiar one */
function mailGraphError(array $response, $what) {
    $json = $response['json'];
    $error = $json['error'] ?? '';
    $code = is_array($error) ? (string) ($error['code'] ?? '') : (string) $error;
    $message = (string) ($json['error_description'] ?? (is_array($error) ? ($error['message'] ?? '') : $error));
    // A sign-in error goes on with a trace ID and time - the first line is the reason
    $message = trim(preg_split('/\r?\n/', $message)[0]);
    if ($message === '' && $response['error'] !== '') {
        $message = $response['error'];
    }
    $hints = [
        'ErrorAccessDenied' => 'A mailbox other than the one connected needs the app to have Mail.ReadWrite.Shared, and the connected account Full Access to it.',
        'ErrorSendAsDenied' => 'The connected account needs Send As on that address in Exchange, and the app Mail.Send.Shared.',
        'MailboxNotEnabledForRESTAPI' => 'The account has no Exchange Online mailbox.',
        'invalid_grant' => 'Connect Microsoft 365 again on the OAuth tab, and check the app\'s permissions still have admin consent.',
    ];
    $hint = $hints[$code] ?? '';
    return trim("$what failed" . ($response['status'] ? " (HTTP {$response['status']})" : '') . ($message !== '' ? ": $message" : '') . ($hint !== '' ? " $hint" : ''));
}

/*
 * Send an email PHPMailer has put together through Graph, as the signed-in
 * mailbox - the finished message (its attachments, pictures and calendar invite
 * as built), or, too big for one request, as a draft with its attachments
 * uploaded. A copy goes to its Sent Items. Throws with Microsoft's reason.
 */
function mailGraphSend($mail, $token) {
    $mail->preSend();
    $mime = $mail->getSentMIMEMessage();
    if (strlen($mime) > MAIL_GRAPH_MIME_LIMIT) {
        mailGraphSendLarge($mail, $token);
        return;
    }
    $response = mailGraphHttp('POST', MAIL_GRAPH_URL . '/me/sendMail', ['Authorization: Bearer ' . $token, 'Content-Type: text/plain'],
        base64_encode($mime), 120);
    if ($response['status'] !== 202) {
        throw new RuntimeException(mailGraphError($response, 'Sending through Microsoft Graph'));
    }
}

/* A big email: a draft, its attachments (uploaded in chunks when large), then send - the draft is deleted if any step fails */
function mailGraphSendLarge($mail, $token) {
    $people = function (array $list) {
        $out = [];
        foreach ($list as $person) {
            $out[] = ['emailAddress' => ['address' => $person[0], 'name' => $person[1] ?? '']];
        }
        return $out;
    };
    $draft = mailGraph('POST', '/me/messages', $token, [
        'subject' => $mail->Subject,
        'body' => ['contentType' => $mail->ContentType === 'text/plain' ? 'Text' : 'HTML', 'content' => $mail->Body],
        'from' => ['emailAddress' => ['address' => $mail->From, 'name' => $mail->FromName]],
        'toRecipients' => $people($mail->getToAddresses()),
        'ccRecipients' => $people($mail->getCcAddresses()),
        'replyTo' => $people(array_values($mail->getReplyToAddresses())),
    ]);
    if ($draft['status'] !== 201 || empty($draft['json']['id'])) {
        throw new RuntimeException(mailGraphError($draft, 'Sending through Microsoft Graph (making the message)'));
    }
    $message = '/me/messages/' . rawurlencode($draft['json']['id']);
    // PHPMailer's attachments: [0] path or contents, [2] name, [4] type, [5] contents given, [6] disposition, [7] content ID
    $attachments = $mail->getAttachments();
    if (!empty($mail->Ical)) {
        $attachments[] = [$mail->Ical, 'invite.ics', 'invite.ics', 'base64', 'text/calendar', true, 'attachment', 'invite.ics'];
    }
    try {
        foreach ($attachments as $attachment) {
            $content = $attachment[5] ? (string) $attachment[0] : (string) @file_get_contents($attachment[0]);
            $item = ['name' => (string) $attachment[2], 'contentType' => $attachment[4] ?: 'application/octet-stream', 'isInline' => $attachment[6] === 'inline']
                + ($attachment[6] === 'inline' ? ['contentId' => (string) $attachment[7]] : []);
            if (strlen($content) < MAIL_GRAPH_MIME_LIMIT) {
                $added = mailGraph('POST', "$message/attachments", $token, ['@odata.type' => '#microsoft.graph.fileAttachment', 'contentBytes' => base64_encode($content)] + $item);
                if ($added['status'] !== 201) {
                    throw new RuntimeException(mailGraphError($added, 'Sending through Microsoft Graph (attaching ' . $attachment[2] . ')'));
                }
                continue;
            }
            $session = mailGraph('POST', "$message/attachments/createUploadSession", $token, ['AttachmentItem' => ['attachmentType' => 'file', 'size' => strlen($content)] + $item]);
            $upload_url = (string) ($session['json']['uploadUrl'] ?? '');
            if ($session['status'] !== 201 || $upload_url === '') {
                throw new RuntimeException(mailGraphError($session, 'Sending through Microsoft Graph (uploading ' . $attachment[2] . ')'));
            }
            $total = strlen($content);
            for ($offset = 0; $offset < $total; $offset += MAIL_GRAPH_UPLOAD_CHUNK) {
                $chunk = substr($content, $offset, MAIL_GRAPH_UPLOAD_CHUNK);
                // The upload address carries its own authorization - no token with it
                $put = mailGraphHttp('PUT', $upload_url, ['Content-Type: application/octet-stream',
                    'Content-Range: bytes ' . $offset . '-' . ($offset + strlen($chunk) - 1) . '/' . $total], $chunk, 300);
                if ($put['status'] !== 200 && $put['status'] !== 201) {
                    throw new RuntimeException(mailGraphError($put, 'Sending through Microsoft Graph (uploading ' . $attachment[2] . ')'));
                }
            }
        }
        $sent = mailGraph('POST', "$message/send", $token);
        if ($sent['status'] !== 202) {
            throw new RuntimeException(mailGraphError($sent, 'Sending through Microsoft Graph'));
        }
    } catch (Throwable $e) {
        mailGraph('DELETE', $message, $token); // no half-made draft left in the mailbox
        throw $e;
    }
}

/*
 * The mailbox read through Graph, for cron/ticket_email_parser.php - $username
 * is the mailbox on the Receiving tab: the connected account, or one it has
 * full access to.
 */
function mailGraphMailbox($token, $username) {
    require_once dirname(__DIR__) . '/libs/vendor/autoload.php'; // ImapEngine
    require_once __DIR__ . '/mail_graph_mailbox.php';
    return new MailGraphMailbox($token, trim((string) $username));
}

/*
 * Test IMAP on the mail settings' Tests tab, when Graph reads the mailbox:
 * ['ok' => bool, 'message' => what to say], or null when it is read by IMAP.
 */
function mailGraphMailboxTest($username) {
    try {
        $token = mailGraphToken('Mail.ReadWrite', true);
        if ($token === null) {
            return null;
        }
        $username = trim((string) $username);
        $inbox = mailGraphMailbox($token, $username)->call('GET', '/mailFolders/inbox?$select=unreadItemCount,totalItemCount');
        return ['ok' => true, 'message' => 'Connected through Microsoft Graph to ' . ($username !== '' ? $username : 'the mailbox') . ' - '
            . intval($inbox['json']['unreadItemCount'] ?? 0) . ' unread of ' . intval($inbox['json']['totalItemCount'] ?? 0) . ' in the Inbox.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}
