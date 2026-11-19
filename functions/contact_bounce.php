<?php

/*
 * ITFlow - contacts whose email bounces. When a bounce comes back to the ticket mailbox
 * (cron/ticket_email_parser.php), the contacts with that address are marked, with what the mail
 * server said, and every contact list and page shows "Email bounced". It clears when mail comes
 * in from the address, when the address is changed (edit_contact), and with Clear on the contact.
 */

/** Whether the 4.0.3 update has run - contact_email_bounced_at and contact_email_bounce. */
function contactBounceReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.0.3', '>=');
    }
    return $ready;
}

/** Mail to $email bounced - mark the contacts with it. Returns [contact_id => [name, client_id]]. */
function contactBounceMark($email, $why) {
    global $mysqli;
    $email = strtolower(trim((string) $email));
    if (!contactBounceReady() || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [];
    }
    $email_sql = escapeSql($email);
    $why_sql = escapeSql(mb_substr(trim((string) $why), 0, 255));
    $marked = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id FROM contacts WHERE LOWER(contact_email) = '$email_sql' AND contact_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $marked[intval($row['contact_id'])] = [$row['contact_name'], intval($row['contact_client_id'])];
    }
    if ($marked) {
        mysqli_query($mysqli, "UPDATE contacts SET contact_email_bounced_at = NOW(), contact_email_bounce = '$why_sql', contact_updated_at = contact_updated_at
            WHERE contact_id IN (" . implode(',', array_keys($marked)) . ")");
    }
    return $marked;
}

/** Mail came in from $email - it works again. */
function contactBounceClearEmail($email) {
    global $mysqli;
    $email = strtolower(trim((string) $email));
    if (!contactBounceReady() || $email === '') {
        return;
    }
    mysqli_query($mysqli, "UPDATE contacts SET contact_email_bounced_at = NULL, contact_email_bounce = NULL, contact_updated_at = contact_updated_at
        WHERE contact_email_bounced_at IS NOT NULL AND LOWER(contact_email) = '" . escapeSql($email) . "'");
}

/** One contact's mark taken off. */
function contactBounceClear($contact_id) {
    global $mysqli;
    if (contactBounceReady()) {
        mysqli_query($mysqli, "UPDATE contacts SET contact_email_bounced_at = NULL, contact_email_bounce = NULL, contact_updated_at = contact_updated_at WHERE contact_id = " . intval($contact_id));
    }
}

/** The contact's bounce, from a row: ['at', 'why'] or null. */
function contactBounce(array $row) {
    if (!contactBounceReady() || empty($row['contact_email_bounced_at'])) {
        return null;
    }
    return ['at' => $row['contact_email_bounced_at'], 'why' => (string) ($row['contact_email_bounce'] ?? '')];
}

/** The "Email bounced" badge for a contact row - '' when it hasn't. */
function contactBounceBadge(array $row) {
    $bounce = contactBounce($row);
    if (!$bounce) {
        return '';
    }
    $title = 'Mail to this address bounced ' . date('M j, Y', strtotime($bounce['at'])) . ($bounce['why'] !== '' ? ' - ' . $bounce['why'] : '');
    return '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal" title="' . escapeHtml($title) . '"><i class="fas fa-exclamation-circle me-1"></i>Email bounced</span>';
}

/** The columns for a contact query - NULLs before the update. */
function contactBounceColumns($alias = '') {
    $prefix = $alias !== '' ? "$alias." : '';
    return contactBounceReady() ? "{$prefix}contact_email_bounced_at, {$prefix}contact_email_bounce" : 'NULL AS contact_email_bounced_at, NULL AS contact_email_bounce';
}
