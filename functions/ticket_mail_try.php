<?php

/*
 * ITFlow - Try an email (Admin > Settings > Tickets, Email to ticket): what the
 * Ticket Email Parser (cron/ticket_email_parser.php) would do with an email from
 * this address with this subject - without doing any of it. It asks the same
 * questions in the same order, against the same records:
 *
 *   1. an automatic reply (out of office) is skipped;
 *   2. a ticket number in [brackets] in the subject - a reply on that ticket,
 *      as whoever sent it is to the ticket (its contact or another of the
 *      client's, an agent, a watcher, or a stranger), or a closed one turned away;
 *   3. a subject almost the same as an open ticket of the sender's client from
 *      the last 7 days - a reply on that ticket;
 *   4. a contact's address - a new ticket for their client;
 *   5. an address at a client's domain - a new contact, and a ticket;
 *   6. anyone else - a ticket with no client when unknown senders are let in
 *      (a mail server's bounce is read as one instead), else left in the
 *      mailbox, flagged.
 *
 * Kept in step with the parser by hand - change one, change the other.
 */

/*
 * The steps it went through, each [question, answer], and where it ended up:
 * ['steps', 'outcome' => [icon, tone, title, text]]. $auto_reply is the email
 * saying it is an automatic reply (Auto-Submitted: auto-replied).
 */
function ticketMailTry($from_email, $subject, $auto_reply = false) {
    global $mysqli, $config_ticket_email_parse, $config_ticket_email_parse_unknown_senders, $config_ticket_prefix;

    $from_email = strtolower(trim((string) $from_email));
    $subject = mb_substr(trim((string) $subject) !== '' ? trim((string) $subject) : 'No Subject', 0, 500);
    $from_sql = escapeSql($from_email);
    $domain = substr(strrchr($from_email, '@') ?: '', 1);
    $steps = [];
    $end = function ($icon, $tone, $title, $text = '') use (&$steps) {
        return ['steps' => $steps, 'outcome' => [$icon, $tone, $title, $text]];
    };

    if (!$config_ticket_email_parse) {
        return $end('fa-power-off', 'secondary', 'Nothing - the mailbox isn\'t read', 'Email to ticket is off, so the parser leaves the mailbox alone.');
    }

    if ($auto_reply) {
        $steps[] = ['An automatic reply?', 'Yes'];
        return $end('fa-forward', 'secondary', 'Skipped', 'Out-of-office and other automatic replies are moved to the ITFlow folder without a ticket, so two systems can\'t answer each other forever. Agents get a notice.');
    }
    $steps[] = ['An automatic reply?', 'No'];

    // 2. A ticket number in the subject
    if (preg_match("/\[" . preg_quote($config_ticket_prefix, '/') . "(\d+)\]/", $subject, $m)) {
        $reply = ticketMailTryReply($from_email, intval($m[1]));
        if ($reply) {
            $steps[] = ['A ticket number in the subject?', 'Yes - ' . $config_ticket_prefix . intval($m[1])];
            return ['steps' => $steps, 'outcome' => $reply];
        }
        $steps[] = ['A ticket number in the subject?', $config_ticket_prefix . intval($m[1]) . ', but there is no such ticket - carries on'];
    } else {
        $steps[] = ['A ticket number in the subject?', 'No - it would be [' . $config_ticket_prefix . '123] somewhere in it'];
    }

    // The sender: a contact, or the client whose domain it is
    $contact = $from_email !== '' ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, client_name FROM contacts
        LEFT JOIN clients ON client_id = contact_client_id WHERE contact_email = '$from_sql' AND contact_archived_at IS NULL LIMIT 1")) : null;
    $domain_row = $domain !== '' ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT domain_client_id, domain_name, client_name FROM domains
        LEFT JOIN clients ON client_id = domain_client_id WHERE domain_name = '" . escapeSql($domain) . "' AND domain_archived_at IS NULL LIMIT 1")) : null;
    if ($domain_row && $domain_row['domain_name'] !== $domain) {
        $domain_row = null;
    }

    // 3. Almost the same subject as an open ticket of theirs from the last week
    $client_id = $contact ? intval($contact['contact_client_id']) : ($domain_row ? intval($domain_row['domain_client_id']) : 0);
    $similar = null;
    if (strlen(escapeSql($subject)) > 10 && $client_id) {
        $sql = mysqli_query($mysqli, "SELECT ticket_number, ticket_subject FROM tickets WHERE ticket_client_id = $client_id AND ticket_resolved_at IS NULL
            AND ticket_created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        while ($row = mysqli_fetch_assoc($sql)) {
            similar_text(strtolower(escapeSql($subject)), strtolower($row['ticket_subject']), $percent);
            if ($percent >= 95) {
                $similar = $row;
                break;
            }
        }
    }
    if ($similar && ($reply = ticketMailTryReply($from_email, intval($similar['ticket_number'])))) {
        $steps[] = ['The same subject as an open ticket of theirs from the last week?', 'Yes - ' . $config_ticket_prefix . intval($similar['ticket_number'])];
        return ['steps' => $steps, 'outcome' => $reply];
    }
    $steps[] = ['The same subject as an open ticket of theirs from the last week?', $client_id ? 'No' : 'No - they aren\'t a client\'s'];

    // 4. A contact
    if ($contact) {
        $steps[] = ['A contact\'s address?', 'Yes - ' . $contact['contact_name'] . ($contact['client_name'] !== null ? ', ' . $contact['client_name'] : '')];
        return ['steps' => $steps, 'outcome' => ['fa-plus-circle', 'success', 'A new ticket for ' . ($contact['client_name'] ?? 'no client'),
            'From ' . $contact['contact_name'] . ', with the subject as its subject and the email as its details. ' . ticketMailTryNoReply($from_email)]];
    }
    $steps[] = ['A contact\'s address?', 'No'];

    // 5. A client's domain
    if ($domain_row) {
        $steps[] = ['At a client\'s domain?', 'Yes - ' . $domain . ', ' . $domain_row['client_name']];
        return ['steps' => $steps, 'outcome' => ['fa-plus-circle', 'success', 'A new contact and ticket for ' . $domain_row['client_name'],
            'The sender is added as a contact of ' . $domain_row['client_name'] . ' with the name in their email, and the ticket is theirs. ' . ticketMailTryNoReply($from_email)]];
    }
    $steps[] = ['At a client\'s domain?', $domain !== '' ? 'No - no client has ' . $domain : 'No'];

    // 6. Anyone else
    if (!$config_ticket_email_parse_unknown_senders) {
        $steps[] = ['Tickets from unknown senders?', 'Off'];
        return ['steps' => $steps, 'outcome' => ['fa-flag', 'warning', 'Left in the mailbox, flagged',
            'Unread and flagged for someone to look at. Turn on Tickets from unknown senders to make them tickets with no client.']];
    }
    $steps[] = ['Tickets from unknown senders?', 'On'];
    if (preg_match('/daemon|postmaster|bounce|mta/i', $from_email)) {
        return ['steps' => $steps, 'outcome' => ['fa-envelope-open-text', 'info', 'Read as a bounce',
            'From a mail server, so it is read as a message that couldn\'t be delivered: agents get a notice, the contacts with the address it bounced from are marked, and a ticket number in the original subject gets the bounce as a note. No ticket is made.']];
    }
    return ['steps' => $steps, 'outcome' => ['fa-plus-circle', 'success', 'A new ticket with no client',
        'Open for an agent to give it a client. The sender is added as a watcher, so replies reach them. ' . ticketMailTryNoReply($from_email)]];
}

/*
 * What a reply by email to ticket $ticket_number from $from_email would do (addReply()
 * in the parser) - [icon, tone, title, text] - or null when there is no such ticket.
 */
function ticketMailTryReply($from_email, $ticket_number) {
    global $mysqli, $config_ticket_prefix;
    $from_sql = escapeSql($from_email);
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_number, ticket_subject, ticket_status, ticket_client_id, contact_email, contact_name, client_name
        FROM tickets LEFT JOIN contacts ON contact_id = ticket_contact_id LEFT JOIN clients ON client_id = ticket_client_id
        WHERE ticket_number = " . intval($ticket_number) . " LIMIT 1"));
    if (!$ticket) {
        return null;
    }
    $reference = $config_ticket_prefix . intval($ticket['ticket_number']);
    $on = $reference . ' - ' . $ticket['ticket_subject'] . ($ticket['client_name'] !== null ? ' (' . $ticket['client_name'] . ')' : '');
    if (intval($ticket['ticket_status']) === 5) {
        return ['fa-ban', 'warning', "Not added - $reference is closed",
            'Closed tickets aren\'t reopened by email. Agents get a notice to look at it in the mailbox' . (preg_match('/daemon|postmaster|bounce|mta/i', $from_email) ? '' : ', and the sender is told and how to raise a new one') . '.'];
    }
    $client_id = intval($ticket['ticket_client_id']);
    $reopens = ticketMailTryReopens($ticket['ticket_status']);
    if (strtolower((string) $ticket['contact_email']) === $from_email && $from_email !== '') {
        return ['fa-reply', 'success', "A reply on $on", 'From its contact, ' . $ticket['contact_name'] . ', as the client\'s reply. ' . $reopens];
    }
    $other = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name FROM contacts WHERE contact_email = '$from_sql' AND contact_client_id = $client_id LIMIT 1"));
    if ($other) {
        return ['fa-reply', 'success', "A reply on $on", 'From ' . $other['contact_name'] . ', another of the client\'s contacts, as the client\'s reply. ' . $reopens];
    }
    $agent = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_email = '$from_sql' AND user_type = 1 AND user_status = 1 AND user_archived_at IS NULL LIMIT 1"));
    if ($agent) {
        return ['fa-sticky-note', 'info', "An internal note on $on", 'From ' . $agent['user_name'] . ', one of your agents - as their own note. Nothing is reopened.'];
    }
    $watcher = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM ticket_watchers WHERE watcher_ticket_id = " . intval($ticket['ticket_id']) . " AND watcher_email = '$from_sql' LIMIT 1"));
    if ($watcher) {
        return ['fa-sticky-note', 'info', "An internal note on $on", 'From one of its watchers, marked as not from its contact. ' . $reopens];
    }
    return ['fa-user-secret', 'warning', "An internal note on $on, with a warning",
        'From someone who isn\'t a contact, a watcher or an agent - anyone can put a ticket number in a subject - so it is kept as a note marked as from a stranger, the ticket isn\'t reopened, and its attachments aren\'t added.'];
}

/** What a client's reply by email does to the ticket's status, as a sentence. */
function ticketMailTryReopens($status) {
    global $mysqli;
    $status = intval($status);
    $after = intval(ticketStatusAfterClientReply($status));
    if ($after === $status) {
        return 'It stays ' . ticketMailTryStatusName($status) . '.';
    }
    return 'It goes from ' . ticketMailTryStatusName($status) . ' to ' . ticketMailTryStatusName($after) . ($status === TICKET_STATUS_RESOLVED ? ', and its resolution clock runs again' : '') . '.';
}

function ticketMailTryStatusName($status_id) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_status_name FROM ticket_statuses WHERE ticket_status_id = " . intval($status_id)));
    return $row ? $row[0] : 'status ' . intval($status_id);
}

/** Whether the sender is emailed the new ticket - not a no-reply address, and only while that email is on. */
function ticketMailTryNoReply($from_email) {
    global $config_ticket_client_general_notifications;
    if (preg_match("/do[\W_]*not[\W_]*reply|no[\W_]*reply/i", $from_email)) {
        return 'It\'s a no-reply address, so nobody is emailed back.';
    }
    return intval($config_ticket_client_general_notifications) === 1 ? 'They\'re emailed its number.' : 'Emails to clients about new tickets are off, so they aren\'t emailed.';
}
