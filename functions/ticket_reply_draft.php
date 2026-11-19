<?php

/*
 * ITFlow - reply drafts. The reply an agent is writing on a ticket is kept as they type
 * (agent/js/ticket_reply_extras.js posting to agent/ajax.php), one per agent per ticket, so leaving
 * the page, closing the browser or being signed out doesn't lose it. The ticket page puts it back
 * in the reply box. It goes when the reply is sent (add_ticket_reply), when the agent discards it,
 * and nightly once it is a month old or its ticket is closed (cron/nightly_tasks.php).
 */

/** Whether the 4.0.1 update has run - the ticket_reply_drafts table. */
function ticketReplyDraftsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.0.1', '>=');
    }
    return $ready;
}

/** An agent's draft on a ticket - ['body' (HTML, as typed), 'updated_at'] - or null. */
function ticketReplyDraft($ticket_id, $user_id) {
    global $mysqli;
    if (!ticketReplyDraftsReady()) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT draft_body, draft_updated_at FROM ticket_reply_drafts
        WHERE ticket_id = " . intval($ticket_id) . " AND user_id = " . intval($user_id) . " LIMIT 1"));
    return $row ? ['body' => (string) $row['draft_body'], 'updated_at' => $row['draft_updated_at']] : null;
}

/** Keep an agent's draft - made safe first. Nothing worth keeping (no words, no picture) drops it. */
function ticketReplyDraftSave($ticket_id, $user_id, $html) {
    global $mysqli;
    if (!ticketReplyDraftsReady()) {
        return false;
    }
    $html = documentHtmlPurify((string) $html);
    $words = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0");
    if ($words === '' && stripos($html, '<img') === false) {
        ticketReplyDraftDelete($ticket_id, $user_id);
        return true;
    }
    $ticket_id = intval($ticket_id);
    $user_id = intval($user_id);
    $body = mysqli_real_escape_string($mysqli, $html);
    mysqli_query($mysqli, "INSERT INTO ticket_reply_drafts SET ticket_id = $ticket_id, user_id = $user_id, draft_body = '$body', draft_updated_at = NOW()
        ON DUPLICATE KEY UPDATE draft_body = '$body', draft_updated_at = NOW()");
    return true;
}

/** Drop an agent's draft on a ticket. */
function ticketReplyDraftDelete($ticket_id, $user_id) {
    global $mysqli;
    if (ticketReplyDraftsReady()) {
        mysqli_query($mysqli, "DELETE FROM ticket_reply_drafts WHERE ticket_id = " . intval($ticket_id) . " AND user_id = " . intval($user_id));
    }
}
