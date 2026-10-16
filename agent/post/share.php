<?php

/*
 * ITFlow - GET/POST request handler for shared links (functions/share.php)
 *
 * Turning a link off, or every open link for a client at once, and saving
 * or discarding what a client sent back to a request. Links are made by
 * agent/ajax.php share_create. Each handler checks the link's own client and
 * that the agent may make that kind of link; turning one off wipes whatever
 * secret it held.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The link a request names, after the module and client checks - or back with an error. */
function shareForRequest($item_id) {
    global $client_id;
    $row = shareRow($item_id);
    if (!$row) {
        flashAlert('That link no longer exists.', 'error');
        redirect();
    }
    if (!shareCanMake($row['item_type'])) {
        flashAlert("You don't have access to that link.", 'error');
        redirect();
    }
    $client_id = intval($row['item_client_id']);
    enforceClientAccess();
    return $row;
}

// Turn a link off
if (isset($_GET['share_revoke'])) {

    validateCSRFToken();

    $row = shareForRequest($_GET['share_revoke']);
    $name = escapeSql(shareName($row));

    if (shareEnd($row['item_id'], 'Revoked', $session_user_id)) {
        logAudit('Share', 'Revoke', "$session_name turned off the shared link for " . escapeSql(shareTypes()[$row['item_type']]['label'] ?? 'item') . " $name", $client_id, $row['item_id']);
        if ($row['item_type'] === 'Credential') {
            // In the credential's own history too
            logAudit('Credential', 'Unshare', "$session_name turned off a share link" . ($row['item_recipient'] ? ' for ' . escapeSql($row['item_recipient']) : ''), $client_id, $row['item_related_id']);
        }
        flashAlert('The link is turned off - it no longer opens.');
    } else {
        flashAlert('That link had already ended.', 'info');
    }

    redirect();
}

// Turn off every open link for a client - an engineer left, a mailbox was compromised
if (isset($_POST['share_revoke_all'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();

    $types = array_filter(array_keys(shareTypes()), 'shareCanMake');
    $ended = 0;
    if ($types) {
        $sql = mysqli_query($mysqli, "SELECT item_id, item_type, item_related_id FROM shared_items
            WHERE item_client_id = $client_id AND item_type IN ('" . implode("','", $types) . "') AND " . shareOpenSql());
        while ($row = mysqli_fetch_assoc($sql)) {
            if (shareEnd($row['item_id'], 'Revoked', $session_user_id)) {
                $ended++;
                if ($row['item_type'] === 'Credential') {
                    logAudit('Credential', 'Unshare', "$session_name turned off every open link for the client", $client_id, $row['item_related_id']);
                }
            }
        }
    }

    logAudit('Share', 'Revoke', "$session_name turned off all $ended open shared " . ($ended === 1 ? 'link' : 'links') . " for the client", $client_id);
    flashAlert($ended ? "Turned off $ended " . ($ended === 1 ? 'link' : 'links') . ' - none of them open any more.' : 'There were no open links to turn off.', $ended ? 'success' : 'info');

    redirect();
}

// Turn off the links ticked on the Shared Links page
if (isset($_POST['share_bulk_revoke'])) {

    validateCSRFToken();

    $ended = 0;
    foreach (array_map('intval', (array) ($_POST['share_ids'] ?? [])) as $item_id) {
        $row = shareRow($item_id);
        if (!$row || !shareCanMake($row['item_type']) || !hasClientAccess($row['item_client_id'])) {
            continue;
        }
        if (shareEnd($item_id, 'Revoked', $session_user_id)) {
            $ended++;
            logAudit('Share', 'Revoke', "$session_name turned off the shared link for " . escapeSql(shareName($row)), $row['item_client_id'], $item_id);
            if ($row['item_type'] === 'Credential') {
                logAudit('Credential', 'Unshare', "$session_name turned off a share link", $row['item_client_id'], $row['item_related_id']);
            }
        }
    }

    flashAlert($ended ? "Turned off $ended " . ($ended === 1 ? 'link' : 'links') : 'None of those links were still open.', $ended ? 'success' : 'info');

    redirect();
}

// Save what a client sent back as a credential
if (isset($_POST['share_request_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    $row = shareForRequest($_POST['item_id'] ?? 0);
    if ($row['item_type'] !== 'Request' || shareState($row) !== 'received') {
        flashAlert('There is nothing to save on that link.', 'error');
        redirect();
    }

    $credential_id = shareRequestSave($row, $error);
    if (!$credential_id) {
        flashAlert($error, 'error');
        redirect();
    }

    flashAlert('Saved to credentials as <strong>' . escapeHtml(shareName($row)) . '</strong>');

    redirect("credentials.php?client_id=$client_id&q=" . urlencode(shareName($row)));
}

// Throw away what a client sent back, unsaved
if (isset($_GET['share_request_discard'])) {

    validateCSRFToken();

    $row = shareForRequest($_GET['share_request_discard']);
    if ($row['item_type'] !== 'Request') {
        flashAlert('That link is not a request.', 'error');
        redirect();
    }

    if (shareEnd($row['item_id'], 'Discarded', $session_user_id)) {
        logAudit('Share', 'Discard', "$session_name discarded the login request " . escapeSql(shareName($row)) . ($row['item_received_at'] ? ' and what was sent' : ''), $client_id, $row['item_id']);
        flashAlert('Discarded - what was sent is gone.');
    } else {
        flashAlert('That request had already ended.', 'info');
    }

    redirect();
}
