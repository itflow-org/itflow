<?php

/*
 * API keys (Admin > API Keys) - making, changing, revoking and deleting
 * them. The checks are in functions/api_keys.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_api_key'])) {

    validateCSRFToken();

    [$fields, $error] = apiKeyFromInput($_POST);
    $secret = (string) ($_POST['key'] ?? '');
    $password = trim((string) ($_POST['password'] ?? ''));
    if ($error === '' && (!preg_match('/^[A-Za-z0-9_-]{32}$/', $secret) || $password === '')) {
        $error = 'The window was missing its key - open New API Key again.';
    }
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    // Credential decryption password
    $apikey_specific_encryption_ciphertext = encryptUserSpecificKey($password);

    mysqli_query($mysqli, "INSERT INTO api_keys SET api_key_name = '" . escapeSql($fields['name']) . "', api_key_secret = '" . escapeSql($secret) . "',
        api_key_decrypt_hash = '$apikey_specific_encryption_ciphertext', api_key_expire = '" . escapeSql($fields['expire']) . "', api_key_user_id = " . intval($fields['user_id']));

    $api_key_id = mysqli_insert_id($mysqli);
    $agent = apiKeyAgents()[$fields['user_id']]['user_name'];

    logAudit("API Key", "Create", "$session_name created API key " . escapeSql($fields['name']) . " acting as " . escapeSql($agent) . ", expiring " . $fields['expire'], 0, $api_key_id);

    flashAlert("API key <strong>" . escapeHtml($fields['name']) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_api_key'])) {

    validateCSRFToken();

    $row = apiKeyRow($_POST['api_key_id'] ?? 0);
    if (!$row) {
        flashAlert("That key is gone - it may have been deleted", 'error');
        redirect();
    }
    $api_key_id = intval($row['api_key_id']);

    [$fields, $error] = apiKeyFromInput($_POST, $row['api_key_expire']);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE api_keys SET api_key_name = '" . escapeSql($fields['name']) . "', api_key_expire = '" . escapeSql($fields['expire']) . "',
        api_key_user_id = " . intval($fields['user_id']) . " WHERE api_key_id = $api_key_id");

    // What changed, for the audit log
    $changes = [];
    if ($row['api_key_name'] !== $fields['name']) {
        $changes[] = 'renamed from ' . $row['api_key_name'];
    }
    if ((string) $row['api_key_expire'] !== $fields['expire']) {
        $changes[] = 'expiring ' . $fields['expire'] . ' (was ' . $row['api_key_expire'] . ')';
    }
    if (intval($row['api_key_user_id']) !== $fields['user_id']) {
        $changes[] = 'acting as ' . apiKeyAgents()[$fields['user_id']]['user_name'] . ' (was ' . ($row['user_name'] ?? 'nobody') . ')';
    }
    if ($changes) {
        logAudit("API Key", "Edit", "$session_name edited API key " . escapeSql($fields['name']) . " - " . escapeSql(implode(', ', $changes)), 0, $api_key_id);
    }

    flashAlert("API key <strong>" . escapeHtml($fields['name']) . "</strong> " . ($changes ? 'saved' : 'unchanged'));

    redirect();

}

if (isset($_GET['revoke_api_key'])) {

    validateCSRFToken();

    $row = apiKeyRow($_GET['revoke_api_key']);
    if (!$row) {
        flashAlert("That key is gone - it may have been deleted", 'error');
        redirect();
    }

    // Expires today - it works until the start of its expiry day
    mysqli_query($mysqli, "UPDATE api_keys SET api_key_expire = CURDATE() WHERE api_key_id = " . intval($row['api_key_id']));

    logAudit("API Key", "Revoke", "$session_name revoked API key " . escapeSql($row['api_key_name']), 0, $row['api_key_id']);

    flashAlert("API key <strong>" . escapeHtml($row['api_key_name']) . "</strong> revoked", 'error');

    redirect();

}

if (isset($_GET['delete_api_key'])) {

    validateCSRFToken();

    $row = apiKeyRow($_GET['delete_api_key']);
    if (!$row) {
        flashAlert("That key is gone - it may have been deleted", 'error');
        redirect();
    }

    mysqli_query($mysqli, "DELETE FROM api_keys WHERE api_key_id = " . intval($row['api_key_id']));

    logAudit("API Key", "Delete", "$session_name deleted API key " . escapeSql($row['api_key_name']), 0, $row['api_key_id']);

    flashAlert("API key <strong>" . escapeHtml($row['api_key_name']) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_delete_api_keys'])) {

    validateCSRFToken();

    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['api_key_ids'] ?? []))) as $api_key_id) {
        $row = apiKeyRow($api_key_id);
        if (!$row) {
            continue;
        }
        mysqli_query($mysqli, "DELETE FROM api_keys WHERE api_key_id = $api_key_id");
        logAudit("API Key", "Delete", "$session_name deleted API key " . escapeSql($row['api_key_name']), 0, $api_key_id);
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> API key" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}
