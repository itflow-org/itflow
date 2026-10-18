<?php

/*
 * Emails - one look for every email ITFlow sends: the company's logo (or
 * name), a small coloured heading saying what it is, a title, what it has to
 * say, a summary box, a message quoted as it was written (a ticket reply,
 * say), a button, and the company's details at the foot, with a line under
 * the card saying why they got it.
 *
 * emailHtml() draws one; emailQueue() queues it for each recipient, greeting
 * each by name. Billing (functions/billing_email.php), tickets
 * (functions/ticket.php) and the rest say what goes in it.
 */

/** The colour of each tone - the card's top edge, its heading and its button. */
const EMAIL_TONES = ['primary' => '#0d6efd', 'success' => '#198754', 'warning' => '#b35c00', 'danger' => '#c42b37', 'secondary' => '#5c636a'];

/*
 * The line at the top of an email people reply to. cron/ticket_email_parser.php
 * keeps only what is above it in a reply, so it goes first - before anything
 * a mail program quotes back.
 */
const EMAIL_REPLY_MARKER = '##- Please type your reply above this line -##';

/**
 * One email as HTML. $email, every value raw - it is escaped here, but for
 * message.html, which is HTML:
 *   marker   true for an email people reply to - the reply line goes first
 *   preview  the line an inbox shows beside the subject (hidden in the email)
 *   kind     the small heading - Invoice, Ticket update, Resolved
 *   tone     its colour - primary, success, warning, danger or secondary
 *   title    the heading
 *   name     who it is to - "Hello Name,"; '' greets nobody by name ("Hello,"), null not at all
 *   intro    paragraphs before the summary
 *   content  HTML someone wrote that is the email itself - a bulk email, say - made safe
 *            (emailMessageHtml()) and shown as it is, not quoted
 *   note     the agent's own words, kept as typed
 *   message  ['html' => what someone wrote, as HTML, 'by' => who, 'when' => when, 'label' => the line over it]
 *   summary  label => value; strong names the label to make stand out
 *   lines    a table - ['head' => [...], 'align' => [...], 'rows' => [[cell, ...]]], a cell
 *            plain text or ['text' => ..., 'url' => ..., 'tone' => danger]
 *   callout  a paragraph that stands out, in the tone's colour
 *   button   ['label' => ..., 'url' => ..., 'show_url' => true to print the address under it,
 *            for a link that has to be copied whole]
 *   after    paragraphs after the button, smaller
 *   contact  the foot's contact line, in place of the company's phone, email and website
 *   footnote a line under the card - why they got it
 */
function emailHtml(array $email) {
    global $config_base_url;
    $e = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $tones = EMAIL_TONES;
    $accent = $tones[$email['tone'] ?? 'primary'] ?? $tones['primary'];
    $text = '#212529';
    $muted = '#6c757d';
    $line = '#e5e7eb';
    $font = "font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
    $company = billingCompany();
    $company_name = (string) ($company['company_name'] ?? '');
    $paragraph = function ($text_value) use ($e, $text) {
        return '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:' . $text . ';">' . nl2br($e($text_value)) . '</p>';
    };

    $logo = !empty($company['logo_url']) && !empty($config_base_url)
        ? '<img src="' . $e('https://' . $config_base_url . $company['logo_url']) . '" alt="' . $e($company_name) . '" style="display:block;max-height:48px;max-width:220px;border:0;">'
        : '<div style="font-size:18px;font-weight:700;color:' . $text . ';">' . $e($company_name) . '</div>';

    $body = '';
    if (array_key_exists('name', $email) && $email['name'] !== null) {
        $body .= $paragraph(trim((string) $email['name']) !== '' ? 'Hello ' . $email['name'] . ',' : 'Hello,');
    }
    foreach ((array) ($email['intro'] ?? []) as $text_value) {
        $body .= $paragraph($text_value);
    }
    if (trim(strip_tags((string) ($email['content'] ?? ''), '<img>')) !== '') {
        $body .= '<div style="margin:0 0 18px;font-size:15px;line-height:1.55;color:' . $text . ';overflow-wrap:anywhere;word-break:break-word;' . $font . '">' . emailMessageHtml($email['content']) . '</div>';
    }
    if (trim((string) ($email['note'] ?? '')) !== '') {
        $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;"><tr>'
            . '<td style="border-left:3px solid ' . $accent . ';background:#f8f9fa;padding:12px 16px;font-size:15px;line-height:1.55;color:' . $text . ';white-space:pre-line;">' . $e(trim($email['note'])) . '</td></tr></table>';
    }
    if (!empty($email['message']['html']) && trim(strip_tags((string) $email['message']['html'], '<img>')) !== '') {
        $by = trim((string) ($email['message']['by'] ?? ''));
        $label = (string) ($email['message']['label'] ?? ($by !== '' ? "$by wrote" : ''));
        $when = trim((string) ($email['message']['when'] ?? ''));
        $head = $label !== '' || $when !== ''
            ? '<div style="margin:0 0 8px;font-size:13px;color:' . $muted . ';"><strong style="color:' . $text . ';">' . $e($label) . '</strong>' . ($when !== '' ? ($label !== '' ? ' &middot; ' : '') . $e($when) : '') . '</div>'
            : '';
        $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;"><tr>'
            . '<td style="border-left:3px solid ' . $accent . ';background:#f8f9fa;padding:14px 16px;font-size:15px;line-height:1.55;color:' . $text . ';' . $font . '">'
            . $head . '<div style="overflow-wrap:anywhere;word-break:break-word;">' . emailMessageHtml($email['message']['html']) . '</div></td></tr></table>';
    }
    if (!empty($email['summary'])) {
        $rows = '';
        $strong = (string) ($email['strong'] ?? '');
        $first = true;
        foreach ($email['summary'] as $label => $value) {
            $big = $label === $strong;
            // The figure that matters stands out - with a rule above it when it closes the box
            $rule = $big && !$first ? 'border-top:1px solid ' . $line . ';padding-top:11px;' : '';
            $rows .= '<tr><td valign="top" style="padding:7px 12px 7px 0;font-size:14px;color:' . $muted . ';white-space:nowrap;' . $rule . '">' . $e($label) . '</td>'
                . '<td align="right" style="padding:7px 0;font-size:' . ($big ? '18px;font-weight:700' : '14px') . ';color:' . $text . ';' . $rule . '">' . $e($value) . '</td></tr>';
            $first = false;
        }
        $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 20px;background:#f8f9fa;border:1px solid ' . $line . ';border-radius:8px;">'
            . '<tr><td style="padding:10px 18px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table></td></tr></table>';
    }
    if (!empty($email['lines']['rows'])) {
        $align = (array) ($email['lines']['align'] ?? []);
        $head = '';
        foreach ((array) $email['lines']['head'] as $i => $heading) {
            $head .= '<th align="' . ($align[$i] ?? 'left') . '" style="padding:8px 6px;font-size:12px;font-weight:600;color:' . $muted . ';border-bottom:1px solid ' . $line . ';text-transform:uppercase;letter-spacing:.04em;">' . $e($heading) . '</th>';
        }
        $rows = '';
        foreach ($email['lines']['rows'] as $cells) {
            $rows .= '<tr>';
            foreach (array_values($cells) as $i => $cell) {
                $cell = is_array($cell) ? $cell : ['text' => $cell];
                $color = !empty($cell['tone']) ? ($tones[$cell['tone']] ?? $text) : $text;
                $content = !empty($cell['url'])
                    ? '<a href="' . $e($cell['url']) . '" style="color:' . $tones['primary'] . ';text-decoration:none;font-weight:600;">' . $e($cell['text']) . '</a>'
                    : $e($cell['text']);
                $rows .= '<td align="' . ($align[$i] ?? 'left') . '" style="padding:8px 6px;font-size:14px;color:' . $color . ';border-bottom:1px solid ' . $line . ';">' . $content . '</td>';
            }
            $rows .= '</tr>';
        }
        $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-collapse:collapse;"><tr>' . $head . '</tr>' . $rows . '</table>';
    }
    if (trim((string) ($email['callout'] ?? '')) !== '') {
        $body .= '<p style="margin:0 0 16px;padding:12px 16px;border-radius:6px;background:#fff8e6;font-size:15px;line-height:1.55;color:' . $text . ';border:1px solid #f3d9a4;">' . nl2br($e(trim($email['callout']))) . '</p>';
    }
    if (!empty($email['button']['url'])) {
        $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:4px 0 22px;"><tr><td bgcolor="' . $accent . '" style="border-radius:6px;">'
            . '<a href="' . $e($email['button']['url']) . '" style="display:inline-block;padding:12px 26px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;' . $font . '">' . $e($email['button']['label']) . '</a>'
            . '</td></tr></table>';
        if (!empty($email['button']['show_url'])) {
            $body .= '<p style="margin:-10px 0 18px;font-size:12px;line-height:1.5;color:' . $muted . ';word-break:break-all;">Or copy this into your browser: ' . $e($email['button']['url']) . '</p>';
        }
    }
    foreach ((array) ($email['after'] ?? []) as $text_value) {
        $body .= '<p style="margin:0 0 12px;font-size:13px;line-height:1.55;color:' . $muted . ';">' . nl2br($e($text_value)) . '</p>';
    }

    $contact = array_key_exists('contact', $email)
        ? (string) $email['contact']
        : implode('  ·  ', array_filter([$company['phone_display'] ?? '', $company['company_email'] ?? '', $company['company_website'] ?? '']));
    $foot = array_filter([
        $company_name,
        formatAddress($company['company_address'] ?? '', $company['company_city'] ?? '', $company['company_state'] ?? '', $company['company_zip'] ?? '', $company['company_country'] ?? '', ', '),
        $contact,
    ]);

    // The reply line - first, so a reply quoting this email is cut off cleanly
    $marker = !empty($email['marker'])
        ? '<div style="padding:0 0 10px;font-size:12px;color:#808080;' . $font . '"><i style="color:#808080">' . EMAIL_REPLY_MARKER . '</i></div>'
        : '';
    $preview = trim((string) ($email['preview'] ?? '')) !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f1f3f5;">' . $e($email['preview']) . str_repeat('&#8204;&nbsp;', 40) . '</div>'
        : '';

    return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . $e($email['title'] ?? '') . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f1f3f5;' . $font . '">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f1f3f5"><tr><td align="center" style="padding:20px 12px 28px;">'
        . ($marker !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;"><tr><td align="left">' . $marker . '</td></tr></table>' : '')
        . $preview
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid ' . $line . ';border-radius:10px;border-top:4px solid ' . $accent . ';">'
        . '<tr><td style="padding:26px 32px 6px;">' . $logo . '</td></tr>'
        . '<tr><td style="padding:14px 32px 10px;' . $font . '">'
        . (trim((string) ($email['kind'] ?? '')) !== '' ? '<div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:' . $accent . ';">' . $e($email['kind']) . '</div>' : '')
        . '<h1 style="margin:4px 0 18px;font-size:24px;line-height:1.25;font-weight:700;color:' . $text . ';">' . $e($email['title'] ?? '') . '</h1>'
        . $body
        . '</td></tr>'
        . '<tr><td style="padding:18px 32px 24px;border-top:1px solid ' . $line . ';font-size:12px;line-height:1.6;color:' . $muted . ';' . $font . '">' . implode('<br>', array_map($e, $foot)) . '</td></tr>'
        . '</table>'
        . (!empty($email['footnote']) ? '<div style="max-width:600px;padding:12px 8px 0;font-size:11px;line-height:1.5;color:#8a9199;' . $font . '">' . $e($email['footnote']) . '</div>' : '')
        . '</td></tr></table></body></html>';
}

/** The mail settings, read once, as saved - cron scripts load their own copies, or none. */
function emailSettings() {
    global $mysqli;
    static $row = null;
    if ($row === null) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_smtp_provider, config_mail_from_email, config_mail_from_name, config_ticket_from_email, config_ticket_from_name
            FROM settings WHERE company_id = 1")) ?: [];
    }
    return $row;
}

/** Can mail go out - is a mail provider set up (Admin > Mail)? Always, while emails are being captured for a preview. */
function emailReady() {
    return emailCapturing() || !empty(emailSettings()['config_smtp_provider']);
}

/*
 * Capturing - for the previews in Admin > Mail (functions/email_preview.php).
 * While it is on, emailQueue() keeps what it would have queued instead of
 * queuing it, as if mail were set up.
 */
function emailCaptureStart() {
    $GLOBALS['itflow_email_capture'] = [];
}

function emailCapturing() {
    return isset($GLOBALS['itflow_email_capture']) && is_array($GLOBALS['itflow_email_capture']);
}

/** Stop capturing - what was captured: [['to', 'to_name', 'from', 'from_name', 'subject', 'html', 'attachments', 'cal_str'], ...]. */
function emailCaptureStop() {
    $captured = $GLOBALS['itflow_email_capture'] ?? [];
    unset($GLOBALS['itflow_email_capture']);
    return is_array($captured) ? $captured : [];
}

/**
 * The mailboxes ITFlow sends from (Admin > Mail), as saved - for a pick of
 * which one an email comes from. key => ['label', 'email', 'name'], the ones
 * with an address.
 */
function emailMailboxes() {
    $system = emailFrom();
    $invoice = billingEmailFrom('invoice');
    $quote = billingEmailFrom('quote');
    $ticket = emailFrom('ticket');
    $boxes = [
        'system' => ['label' => 'System'] + $system,
        'ticket' => ['label' => 'Tickets'] + $ticket,
        'invoice' => ['label' => 'Invoices'] + $invoice,
        'quote' => ['label' => 'Quotes'] + $quote,
    ];
    $seen = [];
    foreach ($boxes as $key => $box) {
        $same = strtolower($box['email']) . '|' . $box['name'];
        if (!filter_var($box['email'], FILTER_VALIDATE_EMAIL) || isset($seen[$same])) {
            unset($boxes[$key]);
        }
        $seen[$same] = true;
    }
    return $boxes;
}

/**
 * Who an email comes from, read from the settings as saved (cron scripts keep
 * SQL-escaped copies): system - the mailbox for everything else (Admin >
 * Mail), or ticket - the ticket mailbox. ['email' => ..., 'name' => ...].
 */
function emailFrom($which = 'system') {
    $row = emailSettings();
    if ($which === 'ticket') {
        return ['email' => (string) ($row['config_ticket_from_email'] ?? ''), 'name' => (string) ($row['config_ticket_from_name'] ?? '')];
    }
    return ['email' => (string) ($row['config_mail_from_email'] ?? ''), 'name' => (string) ($row['config_mail_from_name'] ?? '')];
}

/*
 * HTML someone wrote - a ticket reply, the details of a ticket - made safe to
 * put in an email (documentHtmlPurify()), its images and links pointed at
 * this install when they were saved without it, and the reply line taken out
 * so it can't cut a later reply short.
 */
function emailMessageHtml($html) {
    global $config_base_url;
    $html = str_replace(EMAIL_REPLY_MARKER, '', (string) $html);
    $html = documentHtmlPurify($html);
    if (!empty($config_base_url)) {
        $base = 'https://' . $config_base_url;
        $html = preg_replace('/\b(src|href)="\/(?!\/)/i', '$1="' . $base . '/', $html);
    }
    // Images no wider than the card, paragraphs closer together than a mail program's default
    $html = preg_replace('/<img\b(?![^>]*\bstyle=)/i', '<img style="max-width:100%;height:auto;"', $html);
    return preg_replace('/<p(?=[\s>])(?![^>]*\bstyle=)/i', '<p style="margin:0 0 10px;"', $html);
}

/**
 * Queue an email - one per recipient, each greeted by name, each address once.
 * $recipients: [['email' => ..., 'name' => ..., 'greet' => name to greet (or
 * '' for "Hello,", null for none), 'footnote' => why they get it], ...].
 * $email is emailHtml()'s, its values raw. $opts:
 *   from         ['email' => ..., 'name' => ...] - the system mailbox (emailFrom()) when not given
 *   attachments  [['path' => app-relative, 'name' => ...], ...]
 *   cal_str      a calendar file (iCalendar) to go with it - an invite or its cancellation
 *   queued_at    when to send it, 'Y-m-d H:i:s' - now when not given
 *   always       queue it even without mail set up, to go once it is - billing's Send
 *                windows have always done that (billingEmailQueue())
 * Who it was queued to, "Name (email)". Nothing is queued without mail set up.
 */
function emailQueue(array $recipients, $subject, array $email, array $opts = []) {
    global $mysqli;
    if (!emailReady() && empty($opts['always'])) {
        return [];
    }
    $from = $opts['from'] ?? emailFrom();
    $queued_at = !empty($opts['queued_at']) && strtotime((string) $opts['queued_at']) ? date('Y-m-d H:i:s', strtotime((string) $opts['queued_at'])) : '';
    $attachments = array_values(array_filter((array) ($opts['attachments'] ?? [])));
    $data = [];
    $sent = [];
    foreach ($recipients as $recipient) {
        $address = trim((string) ($recipient['email'] ?? ''));
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || isset($sent[strtolower($address)])) {
            continue;
        }
        $name = (string) ($recipient['name'] ?? '');
        $html = emailHtml(array_merge($email, [
            'name' => array_key_exists('greet', $recipient) ? $recipient['greet'] : $name,
        ], isset($recipient['footnote']) ? ['footnote' => $recipient['footnote']] : []));
        $sent[strtolower($address)] = $name !== '' ? "$name ($address)" : $address;
        if (emailCapturing()) {
            $GLOBALS['itflow_email_capture'][] = ['to' => $address, 'to_name' => $name, 'from' => (string) $from['email'], 'from_name' => (string) $from['name'],
                'subject' => (string) $subject, 'html' => $html, 'attachments' => $attachments, 'cal_str' => (string) ($opts['cal_str'] ?? '')];
            continue;
        }
        $data[] = [
            'from' => mysqli_real_escape_string($mysqli, (string) $from['email']),
            'from_name' => mysqli_real_escape_string($mysqli, (string) $from['name']),
            'recipient' => mysqli_real_escape_string($mysqli, $address),
            'recipient_name' => mysqli_real_escape_string($mysqli, $name !== '' ? $name : $address),
            'subject' => mysqli_real_escape_string($mysqli, (string) $subject),
            'body' => mysqli_real_escape_string($mysqli, $html),
            'attachments' => $attachments,
        ] + (!empty($opts['cal_str']) ? ['cal_str' => (string) $opts['cal_str']] : [])
          + ($queued_at !== '' ? ['queued_at' => $queued_at] : []);
    }
    if ($data) {
        addToMailQueue($data);
    }
    return array_values($sent);
}

/** The test email in Admin > Mail, from one of the mailboxes - system, ticket, invoice or quote. Who it went to - [] when it didn't. */
function emailTestSend($to, $mailbox) {
    $box = ['ticket' => emailFrom('ticket'), 'invoice' => billingEmailFrom('invoice'), 'quote' => billingEmailFrom('quote')][$mailbox] ?? emailFrom();
    $which = ['system' => 'the system mailbox', 'ticket' => 'the ticket mailbox', 'invoice' => 'the invoice mailbox', 'quote' => 'the quote mailbox'][$mailbox] ?? 'the system mailbox';
    return emailQueue([['email' => trim((string) $to), 'greet' => '', 'footnote' => 'You get this because someone sent a test from Admin > Mail in ITFlow.']], 'Test email from ITFlow', [
        'kind' => 'Test', 'tone' => 'success', 'preview' => "A test from $which",
        'title' => 'Mail is working',
        'intro' => ["This is a test email from ITFlow, sent from $which. If you are reading this, it worked."],
        'summary' => ['From' => trim($box['name'] . ' <' . $box['email'] . '>'), 'To' => trim((string) $to), 'Sent' => date('D j M Y, g:i A')],
    ], ['from' => ['email' => $box['email'], 'name' => $box['name']]]);
}

/*
 * Send an email from the contacts or clients list (agent/includes/bulk_email_fields.php)
 * to $recipients (emailBulkSend()'s), $picked how many were asked for - then
 * say what happened and go back. Refuses without mail set up, a mailbox, a
 * subject or a message.
 */
function emailBulkPost(array $recipients, $picked) {
    global $session_name;
    $mailbox = (string) ($_POST['mailbox'] ?? '');
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $body = (string) ($_POST['body'] ?? '');
    $at = trim((string) ($_POST['queued_at'] ?? ''));
    $queued_at = $at !== '' && strtotime($at) ? date('Y-m-d H:i:s', strtotime($at)) : '';
    if (!emailReady()) {
        flashAlert('Outgoing mail is not set up, so nothing was sent', 'error');
        redirect();
    }
    if (!isset(emailMailboxes()[$mailbox])) {
        flashAlert('Pick the mailbox it comes from', 'error');
        redirect();
    }
    if ($subject === '' || trim(strip_tags($body, '<img>')) === '') {
        flashAlert('Give it a subject and a message - nothing was sent', 'error');
        redirect();
    }
    $sent = emailBulkSend($recipients, $mailbox, $_POST['mail_from_name'] ?? '', $subject, $body, !empty($_POST['greet']), $queued_at);
    $count = count($sent);
    if (!$count) {
        flashAlert('None of them has an email address, so nothing was sent', 'error');
        redirect();
    }
    $later = $queued_at !== '' && strtotime($queued_at) > time() ? ' to go ' . date('M j \a\t g:i A', strtotime($queued_at)) : '';
    logAudit("Bulk Mail", "Send", "$session_name sent " . escapeSql($subject) . " to $count " . ($count === 1 ? 'contact' : 'contacts') . " via bulk mail" . ($later !== '' ? escapeSql($later) : ''));
    $left_out = max(0, intval($picked) - $count);
    flashAlert("<strong>$count</strong> email" . ($count === 1 ? '' : 's') . " queued$later" . ($left_out ? " - $left_out left out with no email address, or the same one" : ''));
    redirect();
}

/*
 * An email an agent writes to many contacts - Email on the contacts or clients
 * list - in the email look, with the logo and the company's details. Each
 * $recipients entry: email, name and footnote (why they get it). $body is the
 * agent's HTML; $greet starts it "Hello Name,"; $from_name '' keeps the
 * mailbox's own; $queued_at when to send ('' for now). Who it was queued to.
 */
function emailBulkSend(array $recipients, $mailbox, $from_name, $subject, $body, $greet = false, $queued_at = '') {
    $boxes = emailMailboxes();
    // A preview shows it before any mailbox is set up
    $box = $boxes[$mailbox] ?? (emailCapturing() ? emailFrom() : null);
    if (!$box) {
        return [];
    }
    $from_name = trim((string) $from_name);
    if (!$greet) {
        $recipients = array_map(function ($recipient) {
            return ['greet' => null] + $recipient;
        }, $recipients);
    }
    return emailQueue($recipients, trim((string) $subject), [
        'title' => trim((string) $subject),
        'preview' => mb_substr(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $body), ENT_QUOTES, 'UTF-8'))), 0, 140),
        'content' => (string) $body,
    ], ['from' => ['email' => $box['email'], 'name' => $from_name !== '' ? $from_name : $box['name']], 'queued_at' => $queued_at]);
}
