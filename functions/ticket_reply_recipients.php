<?php

/*
 * Who an email reply on a ticket goes to - the To line in its reply box
 * (agent/ticket.php), sent by add_ticket_reply (agent/post/ticket.php).
 *
 * An email reply has always gone to the contact, the watchers and the client's
 * colleagues who asked for every update (ticketMailSend()). The To line starts
 * with those same people; the agent it's assigned to, the client's other
 * contacts and any address typed in can be added, and anyone taken out. Whoever
 * is in it gets the email, each told why. What was changed is kept for the
 * ticket (ticket_mail_choices, 3.7.5), so its next email reply starts from the
 * same people - an address typed in stays while it stays in To. Moving the
 * ticket to another client forgets it (ticketReplyRecipientsForget()).
 *
 * Bulk replies, the API, and every other email about the ticket still go to
 * the usual people.
 */

/** The most one email reply goes to. */
const TICKET_REPLY_TO_MOST = 50;

/** What each kind of person in the To line is - shown under their name. */
const TICKET_REPLY_TO_GROUPS = [
    'contact' => 'Contact on the ticket',
    'watcher' => 'Watcher',
    'colleague' => 'Asked for every update',
    'agent' => 'Assigned agent',
    'client' => 'Contact at the client',
    'added' => 'Added before',
];

/** Whether the 3.7.5 update has run - ticket_mail_choices. Until it has, an email reply goes to the usual people. */
function ticketReplyRecipientsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.7.5', '>=');
    }
    return $ready;
}

/**
 * Everyone the To line offers on a ticket, in order: [email (lower case) => [
 *   'name'     their name, or ''
 *   'group'    contact, watcher, colleague, agent, client or added (TICKET_REPLY_TO_GROUPS)
 *   'default'  in To unless someone changed it - the contact, watchers and colleagues
 *   'send'     in To now - the default, or what was kept for the ticket
 *   'look'     their picture or initials, as picker option attributes (selectOptionData())
 * ]]. Each address once, under the first thing it is.
 */
function ticketReplyRecipients($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_client_id, ticket_contact_id, ticket_assigned_to FROM tickets WHERE ticket_id = $ticket_id"));
    if (!$ticket) {
        return [];
    }
    $client_id = intval($ticket['ticket_client_id']);
    $list = [];
    $add = function ($email, $name, $group, $default, $look = '') use (&$list) {
        $email = strtolower(trim((string) $email));
        if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($list[$email])) {
            return;
        }
        $list[$email] = ['name' => trim((string) $name), 'group' => $group, 'default' => $default, 'send' => $default, 'look' => $look];
    };

    // The client's contacts, for their names and pictures wherever they turn up
    $contacts = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_photo FROM contacts
        WHERE " . ($client_id ? "contact_client_id = $client_id AND contact_archived_at IS NULL" : "0") . " AND contact_email != ''
           OR contact_id = " . intval($ticket['ticket_contact_id']) . "
        ORDER BY contact_primary DESC, contact_name LIMIT 1000");
    while ($row = mysqli_fetch_assoc($sql)) {
        $contacts[strtolower(trim((string) $row['contact_email']))] = $row + ['look' => selectOptionContact($client_id, $row['contact_name'], $row['contact_photo'], '', $row['contact_id'])];
    }
    $contact_named = function ($email, $name, $group, $default) use ($add, $contacts) {
        $known = $contacts[strtolower(trim((string) $email))] ?? null;
        $add($email, $known ? $known['contact_name'] : $name, $group, $default, $known ? $known['look'] : '');
    };

    foreach ($contacts as $email => $contact) {
        if (intval($contact['contact_id']) === intval($ticket['ticket_contact_id'])) {
            $add($email, $contact['contact_name'], 'contact', true, $contact['look']);
        }
    }
    $sql = mysqli_query($mysqli, "SELECT watcher_name, watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id ORDER BY watcher_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $contact_named($row['watcher_email'], (string) $row['watcher_name'], 'watcher', true);
    }
    foreach (ticketColleagueRecipients($ticket_id, 'update') as $email => $colleague) {
        $contact_named($email, $colleague['name'], 'colleague', true);
    }
    $assigned = intval($ticket['ticket_assigned_to']);
    if ($assigned) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_name, user_email, user_avatar FROM users
            WHERE user_id = $assigned AND user_type = 1 AND user_status = 1 AND user_archived_at IS NULL LIMIT 1"));
        if ($row) {
            $add($row['user_email'], $row['user_name'], 'agent', false, selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar']));
        }
    }
    foreach ($contacts as $email => $contact) {
        $add($email, $contact['contact_name'], 'client', false, $contact['look']);
    }

    // What was changed for this ticket - and the addresses typed in, still in To
    if (ticketReplyRecipientsReady()) {
        $sql = mysqli_query($mysqli, "SELECT mail_choice_email, mail_choice_send FROM ticket_mail_choices WHERE mail_choice_ticket_id = $ticket_id ORDER BY mail_choice_updated_at, mail_choice_email");
        while ($row = mysqli_fetch_assoc($sql)) {
            $email = strtolower((string) $row['mail_choice_email']);
            $send = intval($row['mail_choice_send']) === 1;
            if (isset($list[$email])) {
                $list[$email]['send'] = $send;
            } elseif ($send) {
                $add($email, '', 'added', false);
                if (isset($list[$email])) {
                    $list[$email]['send'] = true;
                }
            }
        }
    }
    return $list;
}

/**
 * The people posted in a ticket's To line ($posted, the addresses), kept for
 * the ticket where that differs from the usual, as emailQueue() takes them -
 * each greeted by name and told why they get it. Anything that isn't an email
 * address is left out, and past TICKET_REPLY_TO_MOST.
 */
function ticketReplyRecipientsPick($ticket_id, array $posted) {
    global $mysqli, $session_user_id;
    $ticket_id = intval($ticket_id);
    $list = ticketReplyRecipients($ticket_id);
    $picked = [];
    foreach ($posted as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '' && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) && count($picked) < TICKET_REPLY_TO_MOST) {
            $picked[$email] = true;
        }
    }

    // Kept only where it differs from the usual - so an address typed in is kept while it's in To.
    // Nobody in To sends nothing, and changes nothing
    if (!$picked) {
        return [];
    }
    $keep = [];
    foreach (array_keys($list + $picked) as $email) {
        $send = isset($picked[$email]);
        if ($send !== !empty($list[$email]['default'])) {
            $keep[] = "($ticket_id, '" . escapeSql($email) . "', " . ($send ? 1 : 0) . ", " . intval($session_user_id) . ")";
        }
    }
    mysqli_query($mysqli, "DELETE FROM ticket_mail_choices WHERE mail_choice_ticket_id = $ticket_id");
    if ($keep) {
        mysqli_query($mysqli, "INSERT INTO ticket_mail_choices (mail_choice_ticket_id, mail_choice_email, mail_choice_send, mail_choice_by) VALUES " . implode(', ', array_slice($keep, 0, 200)));
    }

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, client_name FROM tickets LEFT JOIN clients ON client_id = ticket_client_id WHERE ticket_id = $ticket_id"));
    $reference = $row ? ticketReference($row) : '';
    $client_name = trim((string) ($row['client_name'] ?? '')) !== '' ? (string) $row['client_name'] : 'your company';
    $company_name = (string) (billingCompany()['company_name'] ?? 'us');
    $why = [
        'contact' => "You get this as the contact on ticket $reference with $company_name.",
        'watcher' => "You get this because you are watching ticket $reference - ask $company_name if you would like to stop.",
        'colleague' => "You get this because you asked for every update on $client_name's tickets - change that under Your account in the client portal.",
        'agent' => "You get this because ticket $reference is assigned to you.",
    ];
    $recipients = [];
    foreach (array_keys($picked) as $email) {
        $person = $list[$email] ?? ['name' => '', 'group' => 'added'];
        $recipients[] = [
            'email' => $email,
            'name' => $person['name'],
            'greet' => in_array($person['group'], ['watcher', 'added'], true) ? '' : $person['name'],
            'footnote' => $why[$person['group']] ?? "You get this because $company_name copied you in on ticket $reference.",
        ];
    }
    return $recipients;
}

/** A ticket moved to another client - what its To line kept goes, so it starts again from the new client's people. */
function ticketReplyRecipientsForget($ticket_id) {
    global $mysqli;
    if (ticketReplyRecipientsReady()) {
        mysqli_query($mysqli, "DELETE FROM ticket_mail_choices WHERE mail_choice_ticket_id = " . intval($ticket_id));
    }
}
