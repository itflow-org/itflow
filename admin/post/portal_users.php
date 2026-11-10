<?php

/*
 * ITFlow - GET/POST request handler for client portal users
 * (Admin > Users > Client portal)
 *
 * The contacts who sign in to the client portal, at every client. What an
 * admin does to one here is what their contact's page does, through the same
 * functions (functions/portal.php): email a link to set a new password or the
 * invite again, turn two-step sign-in off, forget the browsers that skip it,
 * and turn their access off or back on. Never a password - they choose their
 * own from a link. Each is in the audit log against the contact.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The portal user a request is about, or back to the list with why not. */
function portalUserForRequest($user_id) {
    $row = portalUserRow($user_id);
    if (!$row) {
        flashAlert('That portal user no longer exists', 'error');
        redirect();
    }
    return $row;
}

/** The portal users ticked in the list (user_ids[]) - [user_id => row]. */
function portalUsersForBulk() {
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['user_ids'] ?? []))) as $user_id) {
        $row = portalUserRow($user_id);
        if ($row) {
            $rows[$user_id] = $row;
        }
    }
    return $rows;
}

/** "3 people", "1 person". */
function portalUsersCount($count) {
    return "<strong>$count</strong> " . ($count === 1 ? 'person' : 'people');
}

if (isset($_GET['portal_user_reset_link'])) {

    // Email a link to choose a new password - an account that signs in with one
    validateCSRFToken();

    $row = portalUserForRequest($_GET['portal_user_reset_link']);
    $name = escapeHtml($row['contact_name']);

    if (!$row['portal_on'] || $row['user_auth_method'] !== 'local') {
        flashAlert("<strong>$name</strong> " . ($row['portal_on'] ? 'signs in with Microsoft 365 - there is no password here to reset' : "can't sign in - " . lcfirst($row['portal_off_why'])), 'error');
        redirect();
    }
    if (!portalMailPasswordReset($row['user_id'])) {
        flashAlert("The link wasn't sent - " . (portalMailReady() ? 'their email address is not one mail can go to' : 'outgoing mail is not set up'), 'error');
        redirect();
    }

    logAudit("Contact", "Edit", "$session_name emailed " . escapeSql($row['contact_name']) . " a link to reset their client portal password", $row['client_id'], $row['contact_id']);

    flashAlert("<strong>$name</strong> was emailed a link to choose a new password - it works once, for " . PORTAL_RESET_LINK_MINUTES . " minutes");

    redirect();

}

if (isset($_GET['portal_user_invite'])) {

    // The invite again - a link to set their first password, or for Microsoft 365 the sign-in page
    validateCSRFToken();

    $row = portalUserForRequest($_GET['portal_user_invite']);
    $name = escapeHtml($row['contact_name']);

    if (!$row['portal_on']) {
        flashAlert("<strong>$name</strong> can't sign in - " . lcfirst($row['portal_off_why']), 'error');
        redirect();
    }
    if (!portalMailInvite($row['user_id'])) {
        flashAlert("The invite wasn't sent - " . (portalMailReady() ? 'their email address is not one mail can go to' : 'outgoing mail is not set up'), 'error');
        redirect();
    }

    logAudit("Contact", "Edit", "$session_name emailed " . escapeSql($row['contact_name']) . " a client portal invite", $row['client_id'], $row['contact_id']);

    flashAlert("<strong>$name</strong> was emailed their invite to the client portal again");

    redirect();

}

if (isset($_GET['portal_user_reset_mfa'])) {

    // Two-step sign-in off - a lost or replaced phone
    validateCSRFToken();

    $row = portalUserForRequest($_GET['portal_user_reset_mfa']);

    portalMfaReset($row['user_id']);
    logAudit("Contact", "Edit", "$session_name turned off two-step sign-in for " . escapeSql($row['contact_name']) . " in the client portal", $row['client_id'], $row['contact_id']);
    portalMailAccountChange($row['user_email'], $row['contact_name'], 'mfa_off_by_us');

    flashAlert("Two-step sign-in is off for <strong>" . escapeHtml($row['contact_name']) . "</strong> - they sign in with their password alone until they set it up again");

    redirect();

}

if (isset($_GET['portal_user_forget_browsers'])) {

    validateCSRFToken();

    $row = portalUserForRequest($_GET['portal_user_forget_browsers']);
    $user_id = intval($row['user_id']);

    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $user_id");

    logAudit("Contact", "Edit", "$session_name forgot the remembered browsers of " . escapeSql($row['contact_name']) . " in the client portal", $row['client_id'], $row['contact_id']);

    flashAlert("Remembered browsers forgotten for <strong>" . escapeHtml($row['contact_name']) . "</strong> - every browser asks for their code again");

    redirect();

}

if (isset($_GET['portal_user_access_off'])) {

    validateCSRFToken();

    $row = portalUserForRequest($_GET['portal_user_access_off']);

    portalUserAccessOff($row['user_id']);

    logAudit("Contact", "Edit", "$session_name turned off client portal access for " . escapeSql($row['contact_name']), $row['client_id'], $row['contact_id']);

    flashAlert("Portal access is off for <strong>" . escapeHtml($row['contact_name']) . "</strong> - they are signed out and can't sign in", 'error');

    redirect();

}

if (isset($_POST['portal_user_access_on'])) {

    // Back on, signing in the way picked - and the invite, when asked for
    validateCSRFToken();

    $row = portalUserForRequest($_POST['user_id'] ?? 0);
    $name = escapeHtml($row['contact_name']);
    $method = (string) ($_POST['method'] ?? 'local');

    $error = portalUserAccessOn($row['user_id'], $method);
    if ($error !== '') {
        flashAlert("Portal access was not turned on for <strong>$name</strong> - " . escapeHtml($error), 'error');
        redirect();
    }

    $invited = !empty($_POST['send_email']) ? portalMailInvite($row['user_id']) : null;

    logAudit("Contact", "Edit", "$session_name turned on client portal access for " . escapeSql($row['contact_name']) . ($invited ? ' and emailed a client portal invite' : ''), $row['client_id'], $row['contact_id']);

    flashAlert("Portal access is on for <strong>$name</strong>" . ($invited ? ' - they were emailed how to sign in' : ($invited === false ? " - the invite wasn't sent, as " . (portalMailReady() ? 'their email address is not one mail can go to' : 'outgoing mail is not set up') : '')),
        $invited === false ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_portal_reset_links'])) {

    validateCSRFToken();

    $count = 0;
    $skipped = 0;
    foreach (portalUsersForBulk() as $user_id => $row) {
        if (!$row['portal_on'] || $row['user_auth_method'] !== 'local' || !portalMailPasswordReset($user_id)) {
            $skipped++;
            continue;
        }
        logAudit("Contact", "Edit", "$session_name emailed " . escapeSql($row['contact_name']) . " a link to reset their client portal password", $row['client_id'], $row['contact_id']);
        $count++;
    }

    flashAlert("Emailed " . portalUsersCount($count) . " a link to choose a new password" . ($skipped ? " - not $skipped who " . ($skipped === 1 ? 'signs' : 'sign') . " in with Microsoft 365, can't sign in, or can't be emailed" : ''), $skipped ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_portal_reset_mfa'])) {

    validateCSRFToken();

    $count = 0;
    foreach (portalUsersForBulk() as $user_id => $row) {
        if (trim((string) $row['user_token']) === '') {
            continue;
        }
        portalMfaReset($user_id);
        logAudit("Contact", "Edit", "$session_name turned off two-step sign-in for " . escapeSql($row['contact_name']) . " in the client portal", $row['client_id'], $row['contact_id']);
        portalMailAccountChange($row['user_email'], $row['contact_name'], 'mfa_off_by_us');
        $count++;
    }

    flashAlert("Two-step sign-in is off for " . portalUsersCount($count) . ($count ? ' - they sign in with their password alone until they set it up again' : ' - none of them had it on'), $count ? 'success' : 'warning');

    redirect();

}

if (isset($_POST['bulk_portal_access_off'])) {

    validateCSRFToken();

    $count = 0;
    foreach (portalUsersForBulk() as $user_id => $row) {
        if (!in_array($row['user_auth_method'], ['local', 'azure'], true)) {
            continue;
        }
        portalUserAccessOff($user_id);
        logAudit("Contact", "Edit", "$session_name turned off client portal access for " . escapeSql($row['contact_name']), $row['client_id'], $row['contact_id']);
        $count++;
    }

    flashAlert("Portal access is off for " . portalUsersCount($count) . ($count ? " - they are signed out and can't sign in" : ''), $count ? 'error' : 'warning');

    redirect();

}
