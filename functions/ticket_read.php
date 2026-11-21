<?php

/*
 * ITFlow - what each agent has seen. A ticket the client has written on since an agent last
 * opened it is shown in bold on that agent's tickets list, like an unread email.
 *
 * ticket_reads keeps one row per agent per ticket - when they last opened it (agent/ticket.php
 * or its quick look). The row with ticket_id 0 is the agent's starting point: anything from
 * before it counts as seen, so the list doesn't open with every ticket in bold. It is made the
 * first time the agent's tickets list loads. Rows for closed or deleted tickets go nightly
 * (cron/nightly_tasks.php).
 *
 * "Written on" is a reply from the client, or the ticket itself when the client raised it - by
 * email, in the portal or by text.
 */

/** Whether the 4.0.2 update has run - the ticket_reads table. */
function ticketReadsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.0.2', '>=');
    }
    return $ready;
}

/** An agent has opened these tickets. */
function ticketMarkRead($ticket_ids, $user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    $ticket_ids = array_values(array_unique(array_filter(array_map('intval', (array) $ticket_ids))));
    if (!ticketReadsReady() || !$user_id || !$ticket_ids) {
        return;
    }
    $values = implode(',', array_map(function ($id) use ($user_id) {
        return "($id, $user_id, NOW())";
    }, $ticket_ids));
    mysqli_query($mysqli, "INSERT INTO ticket_reads (ticket_id, user_id, read_at) VALUES $values ON DUPLICATE KEY UPDATE read_at = NOW()");
}

/** The agent's starting point - made now on the first call. */
function ticketReadsSince($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT read_at FROM ticket_reads WHERE ticket_id = 0 AND user_id = $user_id"));
    if ($row) {
        return $row[0];
    }
    mysqli_query($mysqli, "INSERT IGNORE INTO ticket_reads SET ticket_id = 0, user_id = $user_id, read_at = NOW()");
    return mysqli_fetch_row(mysqli_query($mysqli, "SELECT read_at FROM ticket_reads WHERE ticket_id = 0 AND user_id = $user_id"))[0];
}

/**
 * SQL: 1 when the client has written on a ticket that isn't closed since the agent last opened
 * it (or since their starting point). Correlated on tickets.
 */
function ticketUnreadSql($user_id, $since) {
    global $mysqli;
    $user_id = intval($user_id);
    $since = mysqli_real_escape_string($mysqli, (string) $since);
    return "(tickets.ticket_closed_at IS NULL AND GREATEST(
            COALESCE((SELECT MAX(cr.ticket_reply_created_at) FROM ticket_replies cr WHERE cr.ticket_reply_ticket_id = tickets.ticket_id
                AND cr.ticket_reply_type = 'Client' AND cr.ticket_reply_archived_at IS NULL), '1000-01-01'),
            IF(tickets.ticket_source IN ('Email', 'Portal', 'Text'), tickets.ticket_created_at, '1000-01-01')
        ) > COALESCE((SELECT trd.read_at FROM ticket_reads trd WHERE trd.ticket_id = tickets.ticket_id AND trd.user_id = $user_id), '$since'))";
}

/** Nightly: rows for tickets that are closed or gone, and for agents who are gone. */
function ticketReadsCleanup() {
    global $mysqli;
    if (!ticketReadsReady()) {
        return;
    }
    mysqli_query($mysqli, "DELETE ticket_reads FROM ticket_reads LEFT JOIN tickets ON tickets.ticket_id = ticket_reads.ticket_id
        WHERE ticket_reads.ticket_id > 0 AND (tickets.ticket_id IS NULL OR tickets.ticket_closed_at IS NOT NULL)");
    mysqli_query($mysqli, "DELETE ticket_reads FROM ticket_reads LEFT JOIN users ON users.user_id = ticket_reads.user_id
        WHERE users.user_id IS NULL OR users.user_archived_at IS NOT NULL");
}
