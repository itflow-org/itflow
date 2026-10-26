<?php

/*
 * ITFlow - Database update to version 3.4.8 (from 3.4.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * Whether an agent's reply on a ticket was emailed, and who to - the ticket's
 * conversation says so (functions/ticket.php, ticketReplyEmailStates()).
 *
 *   ticket_replies.ticket_reply_emailed     1 emailed, 0 not, NULL not known -
 *                                           a reply from before this update, or
 *                                           a client's
 *   ticket_replies.ticket_reply_emailed_to  who it went to, as JSON - kept after
 *                                           the mail queue clears its emails
 *   email_queue.email_ticket_reply_id       the reply an email carried, for
 *                                           whether each one went
 *
 * The mail queue keeps 90 days of email, so replies from those days are
 * matched to the update or resolved email queued with them - on the same
 * ticket, within seconds - and marked emailed. Older ones stay not known.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('ticket_replies', 'ticket_reply_emailed')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_replies` ADD `ticket_reply_emailed` tinyint(1) DEFAULT NULL AFTER `ticket_reply_type`");
    }
    if (!$itflow_column('ticket_replies', 'ticket_reply_emailed_to')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_replies` ADD `ticket_reply_emailed_to` text DEFAULT NULL AFTER `ticket_reply_emailed`");
    }
    if (!$itflow_column('email_queue', 'email_ticket_reply_id')) {
        mysqli_query($mysqli, "ALTER TABLE `email_queue` ADD `email_ticket_reply_id` int(11) DEFAULT NULL AFTER `email_sent_at`, ADD KEY `email_ticket_reply_id` (`email_ticket_reply_id`)");
    }

    /*
     * The replies the mail queue still has the emails of. An agent's reply and
     * its email are made in the same request, so the email of an update or a
     * resolve was queued within seconds of the reply - the latest public reply
     * on that ticket at or just before it is the one it carried. An internal
     * note is never emailed, so one made just before a resolve isn't taken.
     */
    $itflow_tickets = [];
    $itflow_replies = [];
    $itflow_sql = mysqli_query($mysqli, "SELECT email_id, email_subject, email_queued_at, email_recipient, email_recipient_name FROM email_queue
        WHERE email_ticket_reply_id IS NULL AND (email_subject LIKE 'Ticket update - [%' OR email_subject LIKE 'Ticket resolved - [%') ORDER BY email_id");
    while ($itflow_sql && ($itflow_email = mysqli_fetch_assoc($itflow_sql))) {
        if (!preg_match('/^Ticket (?:update|resolved) - \[([^\]]+)\]/', (string) $itflow_email['email_subject'], $itflow_match)) {
            continue;
        }
        $itflow_reference = $itflow_match[1];
        if (!array_key_exists($itflow_reference, $itflow_tickets)) {
            $itflow_row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE CONCAT(ticket_prefix, ticket_number) = '" . mysqli_real_escape_string($mysqli, $itflow_reference) . "' LIMIT 1"));
            $itflow_tickets[$itflow_reference] = intval($itflow_row[0] ?? 0);
        }
        $itflow_ticket_id = $itflow_tickets[$itflow_reference];
        if (!$itflow_ticket_id) {
            continue;
        }
        $itflow_queued = mysqli_real_escape_string($mysqli, (string) $itflow_email['email_queued_at']);
        $itflow_row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_reply_id FROM ticket_replies
            WHERE ticket_reply_ticket_id = $itflow_ticket_id AND ticket_reply_type = 'Public'
            AND ticket_reply_created_at BETWEEN '$itflow_queued' - INTERVAL 15 SECOND AND '$itflow_queued'
            ORDER BY ticket_reply_created_at DESC, ticket_reply_id DESC LIMIT 1"));
        $itflow_reply_id = intval($itflow_row[0] ?? 0);
        if (!$itflow_reply_id) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE email_queue SET email_ticket_reply_id = $itflow_reply_id WHERE email_id = " . intval($itflow_email['email_id']));
        $itflow_replies[$itflow_reply_id][strtolower((string) $itflow_email['email_recipient'])] = [
            'email' => (string) $itflow_email['email_recipient'],
            'name' => (string) $itflow_email['email_recipient_name'] !== (string) $itflow_email['email_recipient'] ? (string) $itflow_email['email_recipient_name'] : '',
        ];
    }
    foreach ($itflow_replies as $itflow_reply_id => $itflow_to) {
        $itflow_json = mysqli_real_escape_string($mysqli, json_encode(array_values($itflow_to), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        // ticket_reply_updated_at kept as it is - it says when the reply itself was edited
        mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_emailed = 1, ticket_reply_emailed_to = '$itflow_json', ticket_reply_updated_at = ticket_reply_updated_at WHERE ticket_reply_id = " . intval($itflow_reply_id));
    }

    unset($itflow_column, $itflow_tickets, $itflow_replies, $itflow_sql, $itflow_email, $itflow_match, $itflow_reference, $itflow_row, $itflow_ticket_id, $itflow_queued, $itflow_reply_id, $itflow_to, $itflow_json);
