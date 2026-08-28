<?php
/*
 * Client Portal
 * Functions
 */

/*
 * Verifies a contact has access to a particular ticket ID, and that the ticket is in the correct state (open/closed) to perform an action
 */
function verifyContactTicketAccess($requested_ticket_id, $expected_ticket_state) {

    // Access the global variables
    global $mysqli, $session_contact_id, $session_contact_primary, $session_contact_is_technical_contact, $session_client_id;

    // Setup
    if ($expected_ticket_state == "Closed") {
        // Closed tickets
        $ticket_state_snippet = "ticket_status = 5";
    } else {
        // Open (working/hold) tickets
        $ticket_state_snippet = "ticket_status != 5";
    }

    // Verify the contact has access to the provided ticket ID
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $requested_ticket_id AND $ticket_state_snippet AND ticket_client_id = $session_client_id LIMIT 1"));
    if ($row) {
        $ticket_id = $row['ticket_id'];

        if (intval($ticket_id) && ($session_contact_id == $row['ticket_contact_id'] || $session_contact_primary == 1 || $session_contact_is_technical_contact)) {
            // Client is ticket owner, primary contact, or a technical contact
            return true;
        }
    }

    // Client is NOT ticket owner or primary/tech contact
    return false;

}

/*
 * Portal access control - single source of truth for what a logged-in contact can do.
 * Primary contacts have full access; others are gated by their billing / technical flags.
 * Capabilities are named by area so the rule for one can change without touching callers.
 */
function contactCan($capability) {
    global $session_contact_primary, $session_contact_is_billing_contact, $session_contact_is_technical_contact;

    // Primary contacts can do everything in the portal
    if ($session_contact_primary == 1) {
        return true;
    }

    switch ($capability) {
        case 'accounting':   // invoices, quotes, recurring invoices, saved payment methods
            return (bool) $session_contact_is_billing_contact;

        case 'itdoc':        // assets, certificates, domains, documents, files
        case 'contacts':     // view / manage contacts
            return (bool) $session_contact_is_technical_contact;

        default:             // unknown capability -> deny (fail closed)
            return false;
    }
}

/*
 * Enforce a capability at the top of a page or handler - bounce the contact out if they lack it.
 */
function enforceContactCan($capability) {
    if (!contactCan($capability)) {
        redirect("post.php?logout");
    }
}

/*
 * Confirms the person at the keyboard is the account holder, before a change
 * that would let someone who hijacked a session take the account over or
 * defeat phone verification.
 *
 * Returns true for SSO contacts without checking anything: there is no local
 * password to compare against, and the identity provider has already done this
 * work. Gating them on a password they do not have would just lock them out.
 */
function portalReauthenticate($current_password) {
    global $mysqli, $session_user_id;

    if (($_SESSION['login_method'] ?? 'local') !== 'local') {
        return true;
    }

    if (empty($current_password)) {
        return false;
    }

    $sql = mysqli_query($mysqli, "SELECT user_password FROM users WHERE user_id = $session_user_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    if (!$row || empty($row['user_password'])) {
        return false;
    }

    return password_verify($current_password, $row['user_password']);
}

/*
 * A timestamp a person can read, in the company's configured date and time
 * format rather than the raw DATETIME the database hands back.
 *
 * Today and yesterday are named instead of dated, because on an activity list
 * the recent rows are the ones being scanned and "Today at 4:12 PM" answers
 * "was that just now?" faster than a date does. Anything older gets the full
 * date, since by then the date is the useful part.
 *
 * Returns HTML-escaped output - callers print it directly.
 */
function portalDateTime($datetime) {
    global $config_date_format, $config_time_format;

    if (empty($datetime)) {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return escapeHtml($datetime);
    }

    $time = date($config_time_format, $timestamp);
    $day = date('Y-m-d', $timestamp);

    if ($day === date('Y-m-d')) {
        return escapeHtml("Today at $time");
    }

    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return escapeHtml("Yesterday at $time");
    }

    return escapeHtml(date($config_date_format, $timestamp) . " at $time");
}

/*
 * The empty state for the portal's list pages.
 *
 * Every list page here renders a header row and then a while loop, so a client
 * with no assets, no quotes or no documents used to get a table with nothing
 * under it - indistinguishable from a page that failed to load. This says so
 * instead.
 *
 * Default is neutral, for a list that simply has nothing in it yet. Pass
 * 'success' where empty is genuinely good news (nothing owed, nothing unpaid).
 */
function portalEmptyState($message, $icon = 'fa-inbox', $type = 'secondary') {
    return '<div class="alert alert-' . $type . '"><i class="fa fa-fw ' . $icon . ' me-2"></i>' . $message . '</div>';
}

