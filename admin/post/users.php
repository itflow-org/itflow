<?php

/*
 * ITFlow - GET/POST request handler for user (agent) management
 *
 * Every change goes through functions/user.php: an email no other account
 * signs in with, a role that exists, a password of at least
 * USER_PASSWORD_MIN characters set together with the agent's vault key, a
 * picture that really is one. An admin changes their own account from their
 * Account pages, not here, and the last active admin cannot be demoted,
 * disabled or archived - there would be nobody left to undo it.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** Refuse a change that would leave no active admin. */
function userGuardLastAdmin($row, $what) {
    if (userIsLastAdmin($row)) {
        flashAlert("<strong>" . $row['user_name'] . "</strong> is the only active admin - $what would leave nobody to run ITFlow. Make someone else an admin first.", 'error');
        redirect();
    }
}

if (isset($_POST['add_user'])) {

    validateCSRFToken();

    if (agentUserLimitReached()) {
        flashAlert(agentUserLimitMessage(), 'error');
        redirect();
    }

    $error = '';
    $identity = userCheckIdentity($_POST, 0, $error);
    $role = $identity ? userCheckRole($_POST['role'] ?? 0, 0, $error) : null;
    $password = (string) ($_POST['password'] ?? '');
    if ($role && ($problem = userPasswordProblem($password))) {
        $error = $problem;
    }
    if (!$identity || !$role || $error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    [$name, $email] = $identity;
    $role_id = intval($role['role_id']);

    mysqli_query($mysqli, "INSERT INTO users SET user_name = '" . escapeSql($name) . "', user_email = '" . escapeSql($email) . "', user_password = '', user_role_id = $role_id");
    $user_id = mysqli_insert_id($mysqli);
    userSetPassword($user_id, $password);

    $force_mfa = !empty($_POST['force_mfa']) ? 1 : 0;
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_force_mfa = $force_mfa");

    if (!intval($role['role_is_admin'])) {
        userSetClientAccess($user_id, $_POST['client_permission'] ?? []);
    }

    $notes = [];
    $avatar_error = '';
    if (!empty($_FILES['avatar']['tmp_name']) && !userSaveAvatar($user_id, $_FILES['avatar'], $avatar_error)) {
        $notes[] = $avatar_error . ' The user was created without one';
    }

    // Their sign in details by email - the password goes as typed, so it is theirs to change
    if (!empty($_POST['send_email']) && !empty($config_smtp_host)) {
        $company_name = (string) mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"))[0];
        $login_url = "https://$config_base_url/login.php" . ($config_login_key_required == 1 ? "?key=$config_login_key_secret" : '');
        $mail = userQueueMail($email, $name, "Your new $company_name ITFlow account",
            'Hello ' . escapeHtml($name) . ',<br><br>An ITFlow account has been set up for you. Please change your password after you sign in'
            . ($force_mfa ? ', and have an authenticator app ready - you will set up multi-factor authentication the first time' : '') . '.<br><br>'
            . 'Email: ' . escapeHtml($email) . '<br>Password: ' . escapeHtml($password) . '<br>Sign in at: ' . escapeHtml($login_url)
            . '<br><br>--<br>' . escapeHtml($company_name) . ' - Support<br>' . escapeHtml($config_ticket_from_email));
        if ($mail === true) {
            $notes[] = 'their sign in details were emailed to them';
        } else {
            $notes[] = 'the email with their sign in details could not be queued';
            logApp('Mail', 'error', 'Failed to queue the new account email for ' . $email . ": $mail");
        }
    }

    logAudit("User", "Create", "$session_name created user " . escapeSql($name), 0, $user_id);

    flashAlert("User <strong>$name</strong> created" . ($notes ? ' - ' . implode('; ', $notes) : ''), $avatar_error ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['edit_user'])) {

    validateCSRFToken();

    $row = userForRequest($_POST['user_id'] ?? 0);
    $user_id = intval($row['user_id']);

    $error = '';
    $identity = userCheckIdentity($_POST, $user_id, $error);
    $role = $identity ? userCheckRole($_POST['role'] ?? 0, $row['user_role_id'], $error) : null;
    $new_password = (string) ($_POST['new_password'] ?? '');
    if ($role && $new_password !== '' && ($problem = userPasswordProblem($new_password))) {
        $error = $problem;
    }
    if (!$identity || !$role || $error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    [$name, $email] = $identity;
    if (intval($row['role_is_admin']) && !intval($role['role_is_admin'])) {
        userGuardLastAdmin($row, 'a role without admin');
    }

    mysqli_query($mysqli, "UPDATE users SET user_name = '" . escapeSql($name) . "', user_email = '" . escapeSql($email) . "', user_role_id = " . intval($role['role_id']) . " WHERE user_id = $user_id");
    $force_mfa = !empty($_POST['force_mfa']) ? 1 : 0;
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_force_mfa = $force_mfa ON DUPLICATE KEY UPDATE user_config_force_mfa = $force_mfa");

    // An admin sees every client - their rules are left as they were, in case the role changes back
    if (!intval($role['role_is_admin']) && isset($_POST['client_permission_shown'])) {
        userSetClientAccess($user_id, $_POST['client_permission'] ?? []);
    }

    $changes = [];
    if ($new_password !== '') {
        userSetPassword($user_id, $new_password);
        $changes[] = 'password changed';
        userNotifyChange($name, $email, 'an administrator set a new password');
    }
    if (!empty($_POST['reset_mfa']) && trim((string) $row['user_token']) !== '') {
        userResetMfa($user_id);
        $changes[] = 'MFA reset';
        userNotifyChange($name, $email, 'an administrator turned off multi-factor authentication' . ($force_mfa ? ' - you will set it up again at your next sign in' : ''));
    }
    if (strcasecmp($email, $row['user_email']) !== 0) {
        $changes[] = 'email changed';
        userNotifyChange($name, $row['user_email'], 'its email address is now ' . $email);
    }
    $avatar_error = '';
    if (!empty($_POST['remove_avatar'])) {
        userRemoveAvatar($user_id);
        $changes[] = 'avatar removed';
    } elseif (!empty($_FILES['avatar']['tmp_name'])) {
        if (userSaveAvatar($user_id, $_FILES['avatar'], $avatar_error)) {
            $changes[] = 'avatar updated';
        }
    }

    logAudit("User", "Edit", "$session_name edited user " . escapeSql($name) . ($changes ? ' - ' . escapeSql(implode(', ', $changes)) : ''), 0, $user_id);

    flashAlert("User <strong>$name</strong> saved" . ($changes ? ' - ' . implode(', ', $changes) : '') . ($avatar_error ? ". $avatar_error" : ''), $avatar_error ? 'warning' : 'success');

    redirect();

}

if (isset($_GET['activate_user'])) {

    validateCSRFToken();

    $row = userForRequest($_GET['activate_user']);
    $user_id = intval($row['user_id']);

    // Only a user who isn't already using a seat can push the count over the limit
    if ((intval($row['user_status']) !== 1 || $row['user_archived_at']) && agentUserLimitReached()) {
        flashAlert(agentUserLimitMessage(), 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE users SET user_status = 1 WHERE user_id = $user_id");

    logAudit("User", "Activate", "$session_name activated user " . escapeSql($row['user_name']), 0, $user_id);

    flashAlert("User <strong>" . $row['user_name'] . "</strong> can sign in again");

    redirect();

}

if (isset($_GET['disable_user'])) {

    validateCSRFToken();

    $row = userForRequest($_GET['disable_user']);
    $user_id = intval($row['user_id']);
    userGuardLastAdmin($row, 'disabling them');

    mysqli_query($mysqli, "UPDATE users SET user_status = 0 WHERE user_id = $user_id");
    userHandOverWork($user_id, 0, 'when ' . escapeSql($row['user_name']) . ' was disabled');

    logAudit("User", "Disable", "$session_name disabled user " . escapeSql($row['user_name']), 0, $user_id);

    flashAlert("User <strong>" . $row['user_name'] . "</strong> disabled - they are signed out, and their open tickets are unassigned", 'error');

    redirect();

}

if (isset($_GET['revoke_remember_me'])) {

    validateCSRFToken();

    $row = userForRequest($_GET['revoke_remember_me']);
    $user_id = intval($row['user_id']);

    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $user_id");

    logAudit("User", "Edit", "$session_name revoked all remember me tokens for user " . escapeSql($row['user_name']), 0, $user_id);

    flashAlert("Remembered browsers forgotten for <strong>" . $row['user_name'] . "</strong> - every browser asks for their MFA code again");

    redirect();

}

if (isset($_GET['reset_user_mfa'])) {

    validateCSRFToken();

    $row = userForRequest($_GET['reset_user_mfa']);
    $user_id = intval($row['user_id']);

    userResetMfa($user_id);
    userNotifyChange($row['user_name'], $row['user_email'], 'an administrator turned off multi-factor authentication' . (intval($row['user_config_force_mfa']) ? ' - you will set it up again at your next sign in' : ''));

    logAudit("User", "Edit", "$session_name reset MFA for user " . escapeSql($row['user_name']), 0, $user_id);

    flashAlert("MFA reset for <strong>" . $row['user_name'] . "</strong>" . (intval($row['user_config_force_mfa']) ? ' - they set it up again at their next sign in' : ' - they sign in with their password alone until they turn it on'));

    redirect();

}

if (isset($_POST['archive_user'])) {

    validateCSRFToken();

    $row = userForRequest($_POST['user_id'] ?? 0);
    $user_id = intval($row['user_id']);
    userGuardLastAdmin($row, 'archiving them');

    // Their work to an active agent, or to nobody
    $to_user_id = intval($_POST['ticket_assign'] ?? 0);
    if ($to_user_id && ($to_user_id === $user_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM users WHERE user_id = $to_user_id AND user_type = 1 AND user_status = 1 AND user_archived_at IS NULL")))) {
        $to_user_id = 0;
    }
    userHandOverWork($user_id, $to_user_id, 'when ' . escapeSql($row['user_name']) . ' was archived');

    // They can no longer sign in, and their copy of the vault key is gone - restoring sets a new password
    $archived_name = escapeSql(mb_substr($row['user_name'], 0, 189) . ' (archived)');
    $scrambled = escapeSql(password_hash(randomString(), PASSWORD_DEFAULT));
    mysqli_query($mysqli, "UPDATE users SET user_name = '$archived_name', user_password = '$scrambled', user_status = 0, user_specific_encryption_ciphertext = '', user_archived_at = NOW() WHERE user_id = $user_id");
    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $user_id");

    logAudit("User", "Archive", "$session_name archived user " . escapeSql($row['user_name']), 0, $user_id);

    flashAlert("User <strong>" . $row['user_name'] . "</strong> archived" . ($to_user_id ? ' - their open tickets went to ' . getFieldById('users', $to_user_id, 'user_name') : ''), 'error');

    redirect();

}

if (isset($_POST['restore_user'])) {

    validateCSRFToken();

    $row = userForRequest($_POST['user_id'] ?? 0);
    $user_id = intval($row['user_id']);

    if (agentUserLimitReached()) {
        flashAlert(agentUserLimitMessage(), 'error');
        redirect();
    }

    $error = '';
    $role = userCheckRole($_POST['role'] ?? 0, $row['user_role_id'], $error);
    $password = (string) ($_POST['new_password'] ?? '');
    if ($role && ($problem = userPasswordProblem($password))) {
        $error = $problem;
    }
    $name = trim(str_replace(' (archived)', '', $row['user_name']));
    if ($role && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM users WHERE user_email = '" . escapeSql($row['user_email']) . "' AND user_archived_at IS NULL AND user_id <> $user_id"))) {
        $error = '<strong>' . $row['user_email'] . '</strong> signs in to another account now - change that one\'s email first.';
    }
    if (!$role || $error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE users SET user_name = '" . escapeSql($name) . "', user_status = 1, user_role_id = " . intval($role['role_id']) . ", user_archived_at = NULL WHERE user_id = $user_id");
    userSetPassword($user_id, $password);

    logAudit("User", "Restore", "$session_name restored user " . escapeSql($name), 0, $user_id);

    flashAlert("User <strong>$name</strong> restored - give them their new password");

    redirect();

}

if (isset($_POST['bulk_role_users'])) {

    validateCSRFToken();

    $error = '';
    $role = userCheckRole($_POST['role'] ?? 0, 0, $error);
    if (!$role) {
        flashAlert($error, 'error');
        redirect();
    }

    $count = 0;
    $kept = [];
    foreach (usersForBulk() as $user_id => $row) {
        if (intval($row['role_is_admin']) && !intval($role['role_is_admin']) && userIsLastAdmin($row)) {
            $kept[] = $row['user_name'];
            continue;
        }
        mysqli_query($mysqli, "UPDATE users SET user_role_id = " . intval($role['role_id']) . " WHERE user_id = $user_id");
        logAudit("User", "Edit", "$session_name set the role of user " . escapeSql($row['user_name']) . " to " . escapeSql($role['role_name']), 0, $user_id);
        $count++;
    }

    flashAlert("<strong>$count</strong> user" . ($count === 1 ? '' : 's') . " now " . ($count === 1 ? 'has' : 'have') . " the <strong>" . $role['role_name'] . "</strong> role" . ($kept ? ' - ' . implode(', ', $kept) . ' kept theirs, as the only admin' : ''), $kept ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_require_mfa_users']) || isset($_POST['bulk_unrequire_mfa_users'])) {

    validateCSRFToken();

    $force = isset($_POST['bulk_require_mfa_users']) ? 1 : 0;
    $count = 0;
    foreach (usersForBulk() as $user_id => $row) {
        mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_force_mfa = $force ON DUPLICATE KEY UPDATE user_config_force_mfa = $force");
        logAudit("User", "Edit", "$session_name " . ($force ? 'required' : 'stopped requiring') . " MFA for user " . escapeSql($row['user_name']), 0, $user_id);
        $count++;
    }

    flashAlert(($force ? "MFA required for" : "MFA no longer required for") . " <strong>$count</strong> user" . ($count === 1 ? '' : 's') . ($force ? ' - anyone without it sets it up at their next sign in' : ''));

    redirect();

}

if (isset($_POST['bulk_revoke_remember_users'])) {

    validateCSRFToken();

    $count = 0;
    foreach (usersForBulk() as $user_id => $row) {
        mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $user_id");
        logAudit("User", "Edit", "$session_name revoked all remember me tokens for user " . escapeSql($row['user_name']), 0, $user_id);
        $count++;
    }

    flashAlert("Remembered browsers forgotten for <strong>$count</strong> user" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_disable_users']) || isset($_POST['bulk_activate_users'])) {

    validateCSRFToken();

    $activate = isset($_POST['bulk_activate_users']);
    $count = 0;
    $kept = [];
    foreach (usersForBulk() as $user_id => $row) {
        if ($row['user_archived_at'] || intval($row['user_status']) === ($activate ? 1 : 0)) {
            continue;
        }
        if ($activate && agentUserLimitReached()) {
            $kept[] = $row['user_name'];
            continue;
        }
        if (!$activate && userIsLastAdmin($row)) {
            $kept[] = $row['user_name'];
            continue;
        }
        mysqli_query($mysqli, "UPDATE users SET user_status = " . ($activate ? 1 : 0) . " WHERE user_id = $user_id");
        if (!$activate) {
            userHandOverWork($user_id, 0, 'when ' . escapeSql($row['user_name']) . ' was disabled');
        }
        logAudit("User", $activate ? "Activate" : "Disable", "$session_name " . ($activate ? 'activated' : 'disabled') . " user " . escapeSql($row['user_name']), 0, $user_id);
        $count++;
    }

    $why = $activate ? 'the agent user limit is reached' : 'the only admin';
    flashAlert(($activate ? 'Activated' : 'Disabled') . " <strong>$count</strong> user" . ($count === 1 ? '' : 's') . ($kept ? ' - not ' . implode(', ', $kept) . " ($why)" : ''), $kept ? 'warning' : ($activate ? 'success' : 'error'));

    redirect();

}

if (isExportRequest('export_users')) {

    validateCSRFToken();

    enforceAdminPermission();

    $format = resolveExportFormat($_POST['export_users']);

    // Filters inherited from the users page - mirrors admin/users.php
    $filter_summary = [];

    if (isset($_POST['archived']) && $_POST['archived'] == 1) {
        $archive_query = "user_archived_at IS NOT NULL";
        $filter_summary['Archived'] = 'Archived only';
    } else {
        $archive_query = "user_archived_at IS NULL";
    }

    $q = escapeSql($_POST['q'] ?? '');
    if ($q !== '') {
        $filter_summary['Search'] = $_POST['q'];
    }

    $role_query = '';
    $role = userRoles()[intval($_POST['role'] ?? 0)] ?? null;
    if ($role) {
        $role_query = 'AND user_role_id = ' . intval($role['role_id']);
        $filter_summary['Role'] = $role['role_name'];
    }

    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT users.user_id, role_name, role_is_admin, user_created_at, user_email, user_name, user_status, user_token, user_config_force_mfa FROM users
        LEFT JOIN user_roles ON user_role_id = role_id
        LEFT JOIN user_settings ON user_settings.user_id = users.user_id
        WHERE (user_name LIKE '%$q%' OR user_email LIKE '%$q%' OR role_name LIKE '%$q%')
        AND user_type = 1
        AND $archive_query $role_query
        ORDER BY user_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['user_id'])] = $row;
    }
    $logins = userLastLogins(array_keys($rows));
    $access = userClientAccess(array_keys($rows));

    $num_rows = count($rows);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('users', $format, "$session_company_name-Users", 'Users', summarizeExportFilters($filter_summary));

        foreach ($rows as $user_id => $row) {
            $row['user_status_display'] = USER_STATUSES[intval($row['user_status'])][0] ?? 'Disabled';
            $row['user_mfa_display'] = trim((string) $row['user_token']) !== '' ? 'On' : (intval($row['user_config_force_mfa']) ? 'Required, not set up' : 'Off');
            $row['user_last_login'] = $logins[$user_id]['log_created_at'] ?? 'Never';
            $row['user_client_access'] = userClientAccessText($access[$user_id], intval($row['role_is_admin']));
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("User", "Export", "$session_name exported $num_rows user(s) to a " . strtoupper($format) . " file");

    exit;

}

if (isset($_POST['ir_reset_user_password'])) {

    // Incident response: every other agent gets a new password, shown once here

    validateCSRFToken();

    // Confirm logged-in user password, for security
    $user_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_password FROM users WHERE user_id = $session_user_id"));
    if (!password_verify((string) ($_POST['admin_password'] ?? ''), $user_row['user_password'])) {
        flashAlert("Incorrect password - nothing was reset.", 'error');
        redirect();
    }

    // Agents only - client portal logins have their own reset, and no vault key to rewrite
    $reset = [];
    $sql_users = mysqli_query($mysqli, "SELECT user_id, user_name, user_email FROM users WHERE user_type = 1 AND user_archived_at IS NULL AND user_id <> $session_user_id ORDER BY user_name");
    while ($row = mysqli_fetch_assoc($sql_users)) {
        $new_password = generateReadablePassword(4);
        userSetPassword($row['user_id'], $new_password);
        // A remembered browser would skip their MFA code - in an incident, it must not
        mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = " . intval($row['user_id']));
        $reset[] = $row + ['password' => $new_password];
    }

    logAudit("User", "Edit", "$session_name reset the passwords of " . count($reset) . " agent(s) and forgot their remembered browsers (incident response)");

    // Shown once, not kept - a plain page to read out or copy, and nothing else on it
    header('Cache-Control: no-store');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Incident response - new agent passwords</title>
        <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
        <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    </head>
    <body class="bg-body-tertiary">
    <div class="container py-4" style="max-width: 52rem">
        <div class="alert alert-danger d-flex gap-3">
            <i class="fas fa-skull-crossbones fa-2x"></i>
            <div>
                <div class="fw-semibold">New passwords for <?= count($reset) ?> agent<?= count($reset) === 1 ? '' : 's' ?> - shown once</div>
                <div class="small">Give each one to its agent out of band (a phone call, not email), and have them change it. Their remembered browsers were forgotten, so MFA is asked for again. Leave this page and the passwords are gone.</div>
            </div>
        </div>
        <div class="card">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Agent</th><th>Email</th><th>New password</th></tr></thead>
                <tbody>
                <?php foreach ($reset as $row) { ?>
                    <tr>
                        <td class="fw-semibold"><?= escapeHtml($row['user_name']) ?></td>
                        <td><?= escapeHtml($row['user_email']) ?></td>
                        <td class="font-monospace user-select-all"><?= escapeHtml($row['password']) ?></td>
                    </tr>
                <?php } ?>
                <?php if (!$reset) { ?>
                    <tr><td colspan="3" class="text-body-secondary">There are no other agents.</td></tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
            <a class="btn btn-primary" href="/admin/users.php"><i class="fas fa-arrow-left me-2"></i>Back to Users</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;

}
