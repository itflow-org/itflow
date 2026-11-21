<?php

/*
 * ITFlow - API / service accounts
 *
 * An API key acts as an agent (api_keys.api_key_user_id - an active agent with a role decides
 * what the key may do), so a key for an integration needs a login of its own - which then showed
 * up in the calendar, every assign picker, the reports... An admin marks such a login an "API /
 * service account" in Admin > Users (users.user_service_account, database 3.8.1). It stays an
 * active agent with its role, so its keys keep working, but:
 *   - the agent lists and pickers leave it out - serviceAccountsOutSql() in their queries, and the
 *     notifications and alert emails that go to every agent or admin
 *   - it can't sign in to ITFlow in a browser (login.php; includes/load_user_session.php ends a
 *     session it already had)
 *   - Admin > Users lists it in a section of its own; Roles and API Keys show it as before
 */

/** Whether the 3.8.1 update has run. */
function serviceAccountsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.1', '>=');
    }
    return $ready;
}

/*
 * " AND <column> = 0" - leaves the API / service accounts out of an agent list. '' before the
 * update. $column is the query's, with its table where it needs one ('u.user_service_account').
 */
function serviceAccountsOutSql($column = 'user_service_account') {
    if (!serviceAccountsReady() || !preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $column)) {
        return '';
    }
    return " AND $column = 0";
}

/** Is this login an API / service account? */
function serviceAccountIs($user_id) {
    global $mysqli;
    if (!serviceAccountsReady() || !intval($user_id)) {
        return false;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_service_account FROM users WHERE user_id = " . intval($user_id)));
    return intval($row[0] ?? 0) === 1;
}

/*
 * Set it from the New / Edit user window's switch - a no-op when the form didn't have it (before
 * the update). Returns what changed, for the audit log, or ''.
 */
function serviceAccountSave($user_id, array $post) {
    global $mysqli;
    $user_id = intval($user_id);
    if (!$user_id || !isset($post['service_account_present']) || !serviceAccountsReady()) {
        return '';
    }
    $want = !empty($post['service_account']);
    if ($want === serviceAccountIs($user_id)) {
        return '';
    }
    mysqli_query($mysqli, "UPDATE users SET user_service_account = " . ($want ? 1 : 0) . " WHERE user_id = $user_id AND user_type = 1");
    return $want ? 'now an API / service account' : 'no longer an API / service account';
}
