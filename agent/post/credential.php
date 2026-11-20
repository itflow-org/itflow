<?php

/*
 * ITFlow - GET/POST request handler for client credentials
 *
 * Every handler checks the credential module and the credential's own client;
 * anything a form names - the asset, the contact, the tags, a vendor to link
 * to - must be that client's. Usernames and passwords are encrypted with the
 * session's vault key (credentialFormSet() in functions/credential.php), and
 * nothing is saved when the vault did not open, rather than writing an empty
 * password over the real one. Vendors and licenses link through post/link.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The credential a request names, after the module and client checks - or back with an error if it is gone. */
function credentialForRequest($credential_id, $level) {
    global $client_id;
    enforceUserPermission('module_credential', $level);
    $row = credentialRow($credential_id);
    if (!$row) {
        flashAlert('That credential no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['credential_client_id']);
    enforceClientAccess();
    return $row;
}

/** The credentials a bulk action names that this agent may act on - [id => row]. */
function credentialsForBulk($level) {
    global $client_id;
    enforceUserPermission('module_credential', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['credential_ids'] ?? []))) as $credential_id) {
        $row = credentialRow($credential_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['credential_client_id']);
        enforceClientAccess();
        $rows[$credential_id] = $row;
    }
    return $rows;
}

if (isset($_POST['add_credential'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    if (!$client_id || !getFieldById('clients', $client_id, 'client_name')) {
        flashAlert('Pick the client this credential is for.', 'error');
        redirect();
    }

    $set = credentialFormSet($_POST, $client_id, $error);
    if ($set === null) {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO credentials SET $set, credential_password_changed_at = NOW(), credential_client_id = $client_id");
    $credential_id = mysqli_insert_id($mysqli);

    credentialSetTags($credential_id, $_POST['tags'] ?? []);

    // Made from a vendor's page - linked to that vendor, if it is the same client's
    $vendor_id = assetClientOwnedId('vendor', $_POST['vendor_id'] ?? 0, $client_id);
    if ($vendor_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO vendor_credentials SET vendor_id = $vendor_id, credential_id = $credential_id");
    }

    $name = trim($_POST['name']);
    logAudit("Credential", "Create", "$session_name created credential " . escapeSql($name), $client_id, $credential_id);

    flashAlert("Credential <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_credential'])) {

    validateCSRFToken();

    $row = credentialForRequest($_POST['credential_id'] ?? 0, 2);
    $credential_id = intval($row['credential_id']);

    $set = credentialFormSet($_POST, $client_id, $error);
    if ($set === null) {
        flashAlert($error, 'error');
        redirect();
    }

    // Changed password - record when, for the rotation report and the age shown everywhere,
    // and keep the one it had under Earlier passwords
    $old_password = (string) decryptCredentialEntry($row['credential_password']);
    $password_changed = trim((string) ($_POST['password'] ?? '')) !== $old_password;
    if ($password_changed && $old_password !== '') {
        credentialPasswordKeep($row);
    }

    mysqli_query($mysqli, "UPDATE credentials SET $set" . ($password_changed ? ", credential_password_changed_at = NOW()" : '') . " WHERE credential_id = $credential_id");

    // Only a form that showed the tags changes them
    if (isset($_POST['tags_form'])) {
        credentialSetTags($credential_id, $_POST['tags'] ?? []);
    }

    $name = trim($_POST['name']);
    logAudit("Credential", "Edit", "$session_name edited credential " . escapeSql($name) . ($password_changed ? ' and changed its password' : ''), $client_id, $credential_id);

    flashAlert("Credential <strong>$name</strong> updated");

    redirect();

}

// A new password and nothing else - the rotation
if (isset($_POST['rotate_credential'])) {

    validateCSRFToken();

    $row = credentialForRequest($_POST['credential_id'] ?? 0, 2);
    $credential_id = intval($row['credential_id']);

    $password = trim((string) ($_POST['password'] ?? ''));
    if ($password === '') {
        flashAlert('Type or make up the new password.', 'error');
        redirect();
    }
    if (checkCredentialLengths(['password' => $password])) {
        flashAlert('That password is too long to store.', 'error');
        redirect();
    }
    $encrypted = encryptCredentialEntry($password);
    if ($encrypted === false) {
        flashAlert('Your vault session has expired, so the password could not be encrypted - sign out and back in, then try again.', 'error');
        redirect();
    }

    // The one it had goes under Earlier passwords
    $old_password = (string) decryptCredentialEntry($row['credential_password']);
    if ($old_password !== '' && $old_password !== $password) {
        credentialPasswordKeep($row);
    }

    mysqli_query($mysqli, "UPDATE credentials SET credential_password = '" . escapeSql($encrypted) . "', credential_password_changed_at = NOW() WHERE credential_id = $credential_id");

    logAudit("Credential", "Rotate", "$session_name changed the password on credential " . escapeSql($row['credential_name']), $client_id, $credential_id);

    flashAlert("Password changed on <strong>{$row['credential_name']}</strong>");

    redirect();

}

// An earlier password made the password again - when a change broke something.
// The one it has now goes under Earlier passwords in its place
if (isset($_GET['restore_credential_password'])) {

    validateCSRFToken();

    $history = credentialPasswordHistoryRow($_GET['restore_credential_password']);
    if (!$history) {
        flashAlert('That earlier password is no longer kept.', 'error');
        redirect();
    }
    $row = credentialForRequest($history['credential_id'], 2);
    $credential_id = intval($row['credential_id']);

    if (!empty($row['credential_archived_at'])) {
        flashAlert('Restore the credential first.', 'error');
        redirect();
    }

    $current = decryptCredentialEntry($row['credential_password']);
    if ($current === false) {
        flashAlert('Your vault session has expired - sign out and back in, then try again.', 'error');
        redirect();
    }
    if ((string) $current !== '' && $current !== decryptCredentialEntry($history['password_history_password'])) {
        credentialPasswordKeep($row);
    }

    mysqli_query($mysqli, "UPDATE credentials SET credential_password = '" . escapeSql($history['password_history_password']) . "', credential_password_changed_at = NOW() WHERE credential_id = $credential_id");
    mysqli_query($mysqli, "DELETE FROM password_history WHERE password_history_id = " . intval($history['password_history_id']));

    logAudit("Credential", "Rotate", "$session_name put back an earlier password on credential " . escapeSql($row['credential_name']), $client_id, $credential_id);

    flashAlert("The earlier password is back on <strong>{$row['credential_name']}</strong>");

    redirect();

}

// An earlier password that shouldn't be kept, gone for good
if (isset($_GET['delete_credential_password_history'])) {

    validateCSRFToken();

    $history = credentialPasswordHistoryRow($_GET['delete_credential_password_history']);
    if (!$history) {
        flashAlert('That earlier password is no longer kept.', 'error');
        redirect();
    }
    $row = credentialForRequest($history['credential_id'], 3);
    $credential_id = intval($row['credential_id']);

    mysqli_query($mysqli, "DELETE FROM password_history WHERE password_history_id = " . intval($history['password_history_id']));

    logAudit("Credential", "Delete", "$session_name deleted an earlier password of credential " . escapeSql($row['credential_name']), $client_id, $credential_id);

    flashAlert("Earlier password deleted from <strong>{$row['credential_name']}</strong>", 'error');

    redirect();

}

if (isset($_GET['favorite_credential']) || isset($_GET['unfavorite_credential'])) {

    validateCSRFToken();

    $favorite = isset($_GET['favorite_credential']) ? 1 : 0;
    $row = credentialForRequest($favorite ? $_GET['favorite_credential'] : $_GET['unfavorite_credential'], 2);
    $credential_id = intval($row['credential_id']);

    mysqli_query($mysqli, "UPDATE credentials SET credential_favorite = $favorite WHERE credential_id = $credential_id");

    logAudit("Credential", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " credential " . escapeSql($row['credential_name']), $client_id, $credential_id);

    redirect();

}

if (isset($_GET['archive_credential'])) {

    validateCSRFToken();

    $row = credentialForRequest($_GET['archive_credential'], 2);
    $credential_id = intval($row['credential_id']);

    mysqli_query($mysqli, "UPDATE credentials SET credential_archived_at = NOW() WHERE credential_id = $credential_id");

    logAudit("Credential", "Archive", "$session_name archived credential " . escapeSql($row['credential_name']), $client_id, $credential_id);

    flashAlert("Credential <strong>{$row['credential_name']}</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_credential'])) {

    validateCSRFToken();

    $row = credentialForRequest($_GET['restore_credential'], 2);
    $credential_id = intval($row['credential_id']);

    mysqli_query($mysqli, "UPDATE credentials SET credential_archived_at = NULL WHERE credential_id = $credential_id");

    logAudit("Credential", "Restore", "$session_name restored credential " . escapeSql($row['credential_name']), $client_id, $credential_id);

    flashAlert("Credential <strong>{$row['credential_name']}</strong> restored");

    redirect();

}

if (isset($_GET['delete_credential'])) {

    validateCSRFToken();

    $row = credentialForRequest($_GET['delete_credential'], 3);

    credentialDeleteForGood($row['credential_id']);

    logAudit("Credential", "Delete", "$session_name deleted credential " . escapeSql($row['credential_name']), $client_id);

    flashAlert("Credential <strong>{$row['credential_name']}</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_favorite_credentials']) || isset($_POST['bulk_unfavorite_credentials'])) {

    validateCSRFToken();

    $favorite = isset($_POST['bulk_favorite_credentials']) ? 1 : 0;
    $count = 0;
    foreach (credentialsForBulk(2) as $credential_id => $row) {
        mysqli_query($mysqli, "UPDATE credentials SET credential_favorite = $favorite WHERE credential_id = $credential_id");
        logAudit("Credential", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " credential " . escapeSql($row['credential_name']), intval($row['credential_client_id']), $credential_id);
        $count++;
    }

    flashAlert(($favorite ? 'Starred' : 'Unstarred') . " <strong>$count</strong> credential" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_archive_credentials'])) {

    validateCSRFToken();

    $count = 0;
    foreach (credentialsForBulk(2) as $credential_id => $row) {
        mysqli_query($mysqli, "UPDATE credentials SET credential_archived_at = NOW() WHERE credential_id = $credential_id");
        logAudit("Credential", "Archive", "$session_name archived credential " . escapeSql($row['credential_name']), intval($row['credential_client_id']), $credential_id);
        $count++;
    }

    flashAlert("Archived <strong>$count</strong> credential" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_credentials'])) {

    validateCSRFToken();

    $count = 0;
    foreach (credentialsForBulk(2) as $credential_id => $row) {
        mysqli_query($mysqli, "UPDATE credentials SET credential_archived_at = NULL WHERE credential_id = $credential_id");
        logAudit("Credential", "Restore", "$session_name restored credential " . escapeSql($row['credential_name']), intval($row['credential_client_id']), $credential_id);
        $count++;
    }

    flashAlert("Restored <strong>$count</strong> credential" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_credentials'])) {

    validateCSRFToken();

    $count = 0;
    foreach (credentialsForBulk(3) as $credential_id => $row) {
        credentialDeleteForGood($credential_id);
        logAudit("Credential", "Delete", "$session_name deleted credential " . escapeSql($row['credential_name']), intval($row['credential_client_id']));
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> credential" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_credentials')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_credential');

    $format = resolveExportFormat($_POST['export_credentials']);

    // Filters inherited from the credentials page - mirrors agent/credentials.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND credential_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "credential_archived_at IS NOT NULL" : "credential_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (credential_client_id = $filter_client_id)";
            $filter_summary['Client'] = exportClientName($filter_client_id);
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR credential_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND credential_archived_at IS NULL)";
    }

    // Tags Filter - a credential with any of them
    $tag_query = '';
    if (isset($_POST['tags']) && is_array($_POST['tags']) && !empty($_POST['tags'])) {
        $tag_filter = implode(",", array_map('intval', $_POST['tags']));
        $tag_query = "AND c.credential_id IN (SELECT credential_id FROM credential_tags WHERE tag_id IN ($tag_filter))";

        $tag_names = [];
        $sql_tags = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id IN ($tag_filter) ORDER BY tag_name ASC");
        while ($tag_row = mysqli_fetch_assoc($sql_tags)) {
            $tag_names[] = $tag_row['tag_name'];
        }
        $filter_summary['Tags'] = implode(', ', $tag_names);
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT c.*, client_name, contact_name, asset_name,
            (SELECT GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') FROM credential_tags ct JOIN tags t ON t.tag_id = ct.tag_id WHERE ct.credential_id = c.credential_id) AS credential_tags,
            DATE(c.credential_password_changed_at) AS credential_password_changed
        FROM credentials c
        LEFT JOIN clients ON client_id = credential_client_id
        LEFT JOIN contacts ON contact_id = credential_contact_id
        LEFT JOIN assets ON asset_id = credential_asset_id
        WHERE $archive_query
        $tag_query
        AND (c.credential_name LIKE '%$q%' OR c.credential_description LIKE '%$q%' OR c.credential_uri LIKE '%$q%' OR c.credential_note LIKE '%$q%' OR client_name LIKE '%$q%'
            OR c.credential_id IN (SELECT credential_id FROM credential_tags JOIN tags USING (tag_id) WHERE tag_name LIKE '%$q%'))
        " . clientScopeSql('credential_client_id') . "
        $client_query
        ORDER BY client_name ASC, c.credential_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    guardExportHasRows($num_rows, 'credentials');

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('credentials', $format, $file_name_prepend . 'Credentials', 'Credentials', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['credential_username'] = decryptCredentialEntry($row['credential_username']);
            $row['credential_password'] = decryptCredentialEntry($row['credential_password']);
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Credential", "Export", "$session_name exported $num_rows credential(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
