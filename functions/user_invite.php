<?php

/*
 * Agent invites - how someone new joins your team in ITFlow.
 *
 * An admin invites them from New User (Admin > Users) or Get started > Your
 * team: who they are, their role, whether MFA is required, the clients they
 * see and a picture are kept with the invite, and they get an email with a
 * link (or the admin passes the link on). The link opens /join.php, where
 * they choose their own password - nobody else ever knows it - and only
 * then are they an agent. Until it is used, cancelled or runs out, an invite
 * holds a seat under the agent user limit, but signs nobody in.
 *
 * The link carries a token of which only the SHA-256 is kept. It runs out
 * after USER_INVITE_LINK_DAYS. Sending it again or getting the link makes a
 * new token, so a link given out before stops working; cancelling stops it.
 *
 * Their copy of the credential vault: an agent's password unlocks their own
 * copy of the vault's key. With an invite nobody has their password yet, so
 * the admin's session seals the key under the link's token (invite_vault,
 * the way it is sealed under a password), and accepting opens it with the
 * token in the link and seals it again under the password they chose. Only
 * the token's hash is kept, so the database alone can't open it. It is
 * sealed again with every new token, and wiped once the invite is accepted,
 * cancelled or has run out.
 */

const USER_INVITE_LINK_DAYS = 7;

/** Has the 3.5.1 update run? Until it has, there are no invites and New User sets a password. */
function userInvitesReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.5.1', '>=');
    }
    return $ready;
}

/** Can an invite be emailed - mail set up, with an address to send from? */
function userInviteMailReady() {
    return emailCapturing() || (emailReady() && filter_var(emailFrom()['email'], FILTER_VALIDATE_EMAIL));
}

/*
 * ---------- an invite ----------
 */

/** An invite, with its role and who sent it - or null. */
function userInviteRow($invite_id) {
    global $mysqli;
    $invite_id = intval($invite_id);
    if (!$invite_id || !userInvitesReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_invites.*, role_name, role_is_admin, role_archived_at,
            inviter.user_name AS invite_by_name, inviter.user_status AS invite_by_status, inviter.user_archived_at AS invite_by_archived_at
        FROM user_invites
        LEFT JOIN user_roles ON role_id = invite_role_id
        LEFT JOIN users inviter ON inviter.user_id = invite_created_by AND invite_created_by > 0
        WHERE invite_id = $invite_id LIMIT 1")) ?: null;
}

/** The invite waiting for an address - in date or run out, not accepted or cancelled - or null. */
function userInviteFor($email) {
    global $mysqli;
    if (!userInvitesReady()) {
        return null;
    }
    $email_sql = escapeSql(trim((string) $email));
    $invite_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT invite_id FROM user_invites
        WHERE invite_email = '$email_sql' AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL
        ORDER BY invite_id DESC LIMIT 1"))[0] ?? 0);
    return $invite_id ? userInviteRow($invite_id) : null;
}

/** Where an invite stands: pending, expired, accepted or cancelled. */
function userInviteState(array $row) {
    if (!empty($row['invite_accepted_at'])) {
        return 'accepted';
    }
    if (!empty($row['invite_cancelled_at'])) {
        return 'cancelled';
    }
    if (strtotime((string) $row['invite_expires_at']) <= time()) {
        return 'expired';
    }
    return 'pending';
}

/** How a waiting invite stands, as a badge: Waiting (and how long it has left) or Ran out. ['label', 'title', 'tone'] */
function userInviteStateLabel(array $row) {
    if (userInviteState($row) === 'expired') {
        return ['label' => 'Ran out', 'title' => 'The link ran out on ' . date('M j, Y', strtotime($row['invite_expires_at'])) . ' - send it again for a new one', 'tone' => 'warning'];
    }
    $left = strtotime((string) $row['invite_expires_at']) - time();
    $days = intval(floor($left / 86400));
    return ['label' => 'Waiting', 'title' => 'The link works for ' . ($days >= 1 ? $days . ' more day' . ($days === 1 ? '' : 's') : 'less than a day'), 'tone' => 'info'];
}

/** The client rules an invite gives - ['allow' => [client_id => name], 'deny' => [...]], as userClientAccess(). */
function userInviteClientAccess(array $row) {
    global $mysqli;
    $access = ['allow' => [], 'deny' => []];
    $rules = json_decode((string) ($row['invite_client_access'] ?? ''), true);
    $ids = [];
    foreach (['allow', 'deny'] as $type) {
        foreach ((array) ($rules[$type] ?? []) as $client_id) {
            if (intval($client_id)) {
                $ids[intval($client_id)] = $type;
            }
        }
    }
    if ($ids) {
        $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_id IN (" . implode(',', array_keys($ids)) . ") ORDER BY client_name");
        while ($client = mysqli_fetch_assoc($sql)) {
            $access[$ids[intval($client['client_id'])]][intval($client['client_id'])] = $client['client_name'];
        }
    }
    return $access;
}

/** Who sent an invite, in words - their name, or '' when they are gone. */
function userInviteBy(array $row) {
    return trim((string) ($row['invite_by_name'] ?? ''));
}

/** The link that opens the join page for an invite and its token. */
function userInviteUrl($invite_id, $token) {
    global $config_base_url;
    return "https://$config_base_url/join.php?" . http_build_query(['invite' => intval($invite_id), 'token' => (string) $token]);
}

/*
 * ---------- seats ----------
 */

/**
 * Invites in date and waiting - each holds a seat under the agent user
 * limit until it is used, cancelled or runs out. Leaving out $except_invite_id.
 */
function userInvitesHoldingSeats($except_invite_id = 0) {
    global $mysqli;
    if (!userInvitesReady()) {
        return 0;
    }
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM user_invites
        WHERE invite_accepted_at IS NULL AND invite_cancelled_at IS NULL AND invite_expires_at > NOW() AND invite_id <> " . intval($except_invite_id)))[0]);
}

/** Invites waiting - in date or run out - with a role. For a role that is to be archived. */
function userInvitesWithRole($role_id) {
    global $mysqli;
    if (!userInvitesReady()) {
        return 0;
    }
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM user_invites
        WHERE invite_role_id = " . intval($role_id) . " AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL"))[0]);
}

/*
 * ---------- the vault ----------
 */

/** The vault's key from the signed-in admin's session - '' when their session can't open it. */
function userInviteSessionVaultKey() {
    $ciphertext = (string) ($_SESSION['user_encryption_session_ciphertext'] ?? '');
    $iv = base64_decode((string) ($_SESSION['user_encryption_session_iv'] ?? ''));
    $key = base64_decode((string) ($_COOKIE['user_encryption_session_key'] ?? ''));
    if ($ciphertext === '' || $iv === '' || $key === '') {
        return '';
    }
    $master = openssl_decrypt($ciphertext, 'aes-128-cbc', $key, 0, $iv);
    return is_string($master) ? $master : '';
}

/**
 * A new link for an invite: its token kept as a SHA-256, running out
 * USER_INVITE_LINK_DAYS from now, with the vault's key from the signed-in
 * admin's session sealed under it. The token, for the link - or '' when the
 * session can't open the vault, and nothing is changed.
 */
function userInviteNewToken($invite_id) {
    global $mysqli;
    $master = userInviteSessionVaultKey();
    if ($master === '') {
        return '';
    }
    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);
    $vault = mysqli_real_escape_string($mysqli, setupFirstUserSpecificKey($token, $master));
    mysqli_query($mysqli, "UPDATE user_invites SET invite_token_hash = '$hash', invite_vault = '$vault', invite_expires_at = NOW() + INTERVAL " . USER_INVITE_LINK_DAYS . " DAY
        WHERE invite_id = " . intval($invite_id));
    return $token;
}

/**
 * The invite a link is for - its token right - with where it stands
 * (userInviteState(), or gone for a link that matches nothing):
 * ['row' => row or null, 'state' => ...].
 */
function userInviteByLink($invite_id, $token) {
    $token = is_string($token) ? $token : '';
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return ['row' => null, 'state' => 'gone'];
    }
    $row = userInviteRow($invite_id);
    if (!$row || !hash_equals((string) $row['invite_token_hash'], hash('sha256', $token))) {
        return ['row' => null, 'state' => 'gone'];
    }
    return ['row' => $row, 'state' => userInviteState($row)];
}

/*
 * ---------- the picture ----------
 */

/** Where an invite's picture is kept until they join. */
function userInviteAvatarDir($invite_id) {
    return dirname(__DIR__) . '/uploads/users/invites/' . intval($invite_id);
}

/** Take an uploaded picture for an invite, in place of any it had. True when set, false with $error when not. */
function userInviteSaveAvatar($invite_id, $file, &$error = '') {
    global $mysqli;
    $invite_id = intval($invite_id);
    $dir = userInviteAvatarDir($invite_id);
    $old = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT invite_avatar FROM user_invites WHERE invite_id = $invite_id"))[0] ?? '');
    $new_name = userAvatarStore($file, $dir, $error);
    if ($new_name === '') {
        return false;
    }
    if ($old !== '' && basename($old) === $old && is_file("$dir/$old")) {
        unlink("$dir/$old");
    }
    mysqli_query($mysqli, "UPDATE user_invites SET invite_avatar = '" . escapeSql($new_name) . "' WHERE invite_id = $invite_id");
    return true;
}

/** Remove an invite's picture, and its folder. */
function userInviteRemoveAvatar($invite_id) {
    global $mysqli;
    $invite_id = intval($invite_id);
    $dir = userInviteAvatarDir($invite_id);
    if (is_dir($dir)) {
        foreach (glob("$dir/*") ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
    mysqli_query($mysqli, "UPDATE user_invites SET invite_avatar = NULL WHERE invite_id = $invite_id");
}

/** An invite's picture, or the initials of the name on it - as userAvatar(). */
function userInviteAvatar(array $row, $size = '') {
    $avatar = (string) ($row['invite_avatar'] ?? '');
    if ($avatar !== '' && basename($avatar) === $avatar) {
        return '<img class="itflow-contact-avatar' . ($size ? " itflow-contact-avatar-$size" : '') . '" src="/uploads/users/invites/' . intval($row['invite_id']) . '/' . escapeHtml(rawurlencode($avatar)) . '" alt="" loading="lazy">';
    }
    return userAvatar(intval($row['invite_id'] ?? 0), $row['invite_name'] ?? '', '', $size);
}

/*
 * ---------- inviting ----------
 */

/**
 * Keep an invite for someone, from New User or Get started - $in name and
 * email (checked already), role_id, force_mfa and access (the Client access
 * radios: client_id => allow / deny / ''). One already waiting for the
 * address is changed to this, and keeps its picture unless $avatar has a new
 * one. ['invite_id', 'again' => true when one was waiting, 'avatar_error'].
 */
function userInviteSave(array $in, $avatar = null) {
    global $mysqli, $session_user_id;
    $email = trim((string) $in['email']);
    $name_sql = escapeSql(mb_substr(trim((string) $in['name']), 0, 200));
    $email_sql = escapeSql($email);
    $role_id = intval($in['role_id']);
    $force_mfa = !empty($in['force_mfa']) ? 1 : 0;
    $by = intval($session_user_id);

    // The rules for clients that exist
    $rules = ['allow' => [], 'deny' => []];
    $wanted = [];
    foreach ((array) ($in['access'] ?? []) as $client_id => $type) {
        if (($type === 'allow' || $type === 'deny') && intval($client_id)) {
            $wanted[intval($client_id)] = $type;
        }
    }
    if ($wanted) {
        $sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id IN (" . implode(',', array_keys($wanted)) . ") ORDER BY client_id");
        while ($client = mysqli_fetch_row($sql)) {
            $rules[$wanted[intval($client[0])]][] = intval($client[0]);
        }
    }
    $access_sql = $rules['allow'] || $rules['deny'] ? "'" . mysqli_real_escape_string($mysqli, json_encode($rules)) . "'" : 'NULL';

    $open = userInviteFor($email);
    if ($open) {
        $invite_id = intval($open['invite_id']);
        mysqli_query($mysqli, "UPDATE user_invites SET invite_name = '$name_sql', invite_email = '$email_sql', invite_role_id = $role_id, invite_force_mfa = $force_mfa,
            invite_client_access = $access_sql, invite_created_by = $by WHERE invite_id = $invite_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO user_invites SET invite_name = '$name_sql', invite_email = '$email_sql', invite_role_id = $role_id, invite_force_mfa = $force_mfa,
            invite_client_access = $access_sql, invite_expires_at = NOW(), invite_created_by = $by");
        $invite_id = intval(mysqli_insert_id($mysqli));
    }

    $avatar_error = '';
    if (!empty($avatar['tmp_name'])) {
        userInviteSaveAvatar($invite_id, $avatar, $avatar_error);
    }
    return ['invite_id' => $invite_id, 'again' => (bool) $open, 'avatar_error' => $avatar_error];
}

/**
 * Give an invite a new link - so one given out before stops working - and
 * email it, or with $mail false only make it, for the admin to pass on.
 * ['url', 'mailed'], or null with $error (HTML-safe) when the link can't be
 * made (the admin's session can't open the vault) or the email can't go.
 */
function userInviteSend($invite_id, $mail, &$error = '') {
    $error = '';
    $token = userInviteNewToken($invite_id);
    if ($token === '') {
        $error = 'Your session can\'t open the credential vault, so the invite can\'t carry their copy of it. Sign out and in again, then send it.';
        return null;
    }
    if ($mail && !userInviteMail($invite_id, $token)) {
        $error = 'The invite email could not be queued - check Admin &gt; Mail, or get the link and send it to them yourself.';
        return null;
    }
    return ['url' => userInviteUrl($invite_id, $token), 'mailed' => (bool) $mail];
}

/**
 * Show an invite's link once, at the top of Admin > Users, for the admin to
 * pass on - it is never kept, so it can't be shown again (getting it again
 * makes a new one).
 */
function userInviteShowLink($invite_id, $url) {
    $_SESSION['user_invite_link'] = ['invite_id' => intval($invite_id), 'url' => (string) $url];
}

/** The link to show once, with its invite - and forget it. ['url', 'row'] or null. */
function userInviteTakeLink() {
    $link = $_SESSION['user_invite_link'] ?? null;
    unset($_SESSION['user_invite_link']);
    $row = is_array($link) ? userInviteRow($link['invite_id'] ?? 0) : null;
    if (!$row || userInviteState($row) !== 'pending') {
        return null;
    }
    return ['url' => (string) $link['url'], 'row' => $row];
}

/**
 * Email an invite its link - $token from userInviteNewToken(). Says who sent
 * it, the role, and that they choose their own password. Notes when it went.
 * False when it can't go.
 */
function userInviteMail($invite_id, $token) {
    global $mysqli;
    $row = userInviteRow($invite_id);
    if (!$row || !userInviteMailReady() || !filter_var($row['invite_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $company_name = trim((string) (billingCompany()['company_name'] ?? ''));
    $app = userMailAppName();
    $where = $company_name !== '' ? "$company_name on $app" : $app;
    $by = userInviteBy($row);
    $role = trim((string) ($row['role_name'] ?? ''));
    $name = trim((string) $row['invite_name']);

    $intro = [($by !== '' ? "$by has invited you" : 'You are invited') . " to join $where" . ($role !== '' ? ", as $role" : '') . '.',
        'Accept with the button below - you choose your own password there, and you are ready to sign in. It takes a minute.'];
    if (intval($row['invite_force_mfa'])) {
        $intro[] = 'Have an authenticator app on your phone ready: you set up multi-factor authentication the first time you sign in.';
    }
    $summary = ['Invited' => (string) $row['invite_email']];
    if ($role !== '') {
        $summary['Role'] = $role;
    }
    $sent = (bool) emailQueue([['email' => (string) $row['invite_email'], 'name' => $name, 'greet' => $name !== '' ? $name : null,
        'footnote' => 'You received this because ' . ($by !== '' ? $by : 'an administrator') . " invited this address to $where."]],
        ($by !== '' ? "$by invited you" : 'You are invited') . " to $where", [
            'kind' => 'Your account', 'tone' => 'primary', 'preview' => 'Choose your password and you are in',
            'title' => "Join $where",
            'intro' => $intro,
            'summary' => $summary,
            'button' => ['label' => 'Accept the invite', 'url' => userInviteUrl($row['invite_id'], $token)],
            'after' => ['The invite works for ' . USER_INVITE_LINK_DAYS . ' days. If you were not expecting it, you can ignore this email - nothing is set up unless the link is used.'],
        ]);
    if ($sent && !emailCapturing()) {
        mysqli_query($mysqli, "UPDATE user_invites SET invite_sent_at = NOW(), invite_sent_count = invite_sent_count + 1 WHERE invite_id = " . intval($row['invite_id']));
    }
    return $sent;
}

/** Cancel an invite that is waiting - its link stops working, and what it kept goes. False when it isn't waiting. */
function userInviteCancel($invite_id) {
    global $mysqli;
    $invite_id = intval($invite_id);
    mysqli_query($mysqli, "UPDATE user_invites SET invite_cancelled_at = NOW(), invite_vault = ''
        WHERE invite_id = $invite_id AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL");
    if (mysqli_affected_rows($mysqli) !== 1) {
        return false;
    }
    userInviteRemoveAvatar($invite_id);
    return true;
}

/*
 * ---------- accepting ----------
 */

/**
 * Accept an invite - $invite from userInviteByLink() (pending), $token the
 * link's, $in what they put in: name, password and confirm. Makes them an
 * agent with what the invite set - role, MFA, client access, picture - and
 * their copy of the vault under their password, spends the link and tells
 * whoever sent it. The new agent's user_id, or 0 with $error (plain text).
 */
function userInviteAccept(array $invite, $token, array $in, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    $invite_id = intval($invite['invite_id']);
    $email = trim((string) $invite['invite_email']);
    $by = userInviteBy($invite);
    $ask = $by !== '' ? "ask $by" : 'ask your administrator';

    $name = trim(preg_replace('/\s+/', ' ', strip_tags(mb_substr((string) ($in['name'] ?? ''), 0, 200))));
    $password = (string) ($in['password'] ?? '');
    if ($name === '') {
        $error = 'Put in your name.';
        return 0;
    }
    if (($problem = userPasswordProblem($password)) !== '') {
        $error = $problem;
        return 0;
    }
    if ($password !== (string) ($in['confirm'] ?? '')) {
        $error = 'The two passwords are not the same - type it again in both.';
        return 0;
    }

    // Still theirs to take: the address free, the role there, a seat
    $taken = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_type FROM users WHERE user_email = '" . escapeSql($email) . "' AND user_archived_at IS NULL LIMIT 1"));
    if ($taken) {
        $error = intval($taken['user_type']) === 1
            ? 'This address signs in already - sign in with it. Forgotten the password? ' . ucfirst($ask) . ' to set a new one.'
            : 'This address signs in to the client portal, so it can\'t be an agent\'s too - ' . $ask . ' about it.';
        return 0;
    }
    if (($invite['role_name'] ?? null) === null) {
        $error = 'The role you were invited with has been removed - ' . $ask . ' to invite you again.';
        return 0;
    }
    $limit = getAgentUserLimit();
    if ($limit > 0 && getActiveAgentUserCount() + userInvitesHoldingSeats($invite_id) >= $limit) {
        $error = 'Every agent seat is taken - ' . $ask . ' to free one, then open this link again.';
        return 0;
    }
    $master = decryptUserSpecificKey((string) $invite['invite_vault'], (string) $token);
    if (!is_string($master) || $master === '') {
        $error = 'This invite can\'t be used - ' . $ask . ' to send it again.';
        return 0;
    }

    // Spend the link - one press, or one of two at once, gets through
    mysqli_query($mysqli, "UPDATE user_invites SET invite_accepted_at = NOW(), invite_vault = ''
        WHERE invite_id = $invite_id AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL AND invite_expires_at > NOW()
          AND invite_token_hash = '" . mysqli_real_escape_string($mysqli, (string) $invite['invite_token_hash']) . "'");
    if (mysqli_affected_rows($mysqli) !== 1) {
        $error = 'That link has been used already.';
        return 0;
    }

    // The agent, their copy of the vault under the password they chose
    $name_sql = escapeSql($name);
    $hash = mysqli_real_escape_string($mysqli, password_hash($password, PASSWORD_DEFAULT));
    $vault = mysqli_real_escape_string($mysqli, setupFirstUserSpecificKey($password, $master));
    $role_id = intval($invite['invite_role_id']);
    mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name_sql', user_email = '" . escapeSql($email) . "', user_password = '$hash', user_specific_encryption_ciphertext = '$vault',
        user_auth_method = 'local', user_type = 1, user_status = 1, user_role_id = $role_id");
    $user_id = intval(mysqli_insert_id($mysqli));
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_force_mfa = " . (intval($invite['invite_force_mfa']) ? 1 : 0));
    if (!intval($invite['role_is_admin'])) {
        $access = userInviteClientAccess($invite);
        userSetClientAccess($user_id, array_fill_keys(array_keys($access['allow']), 'allow') + array_fill_keys(array_keys($access['deny']), 'deny'));
    }

    // Their picture, from the invite to their own folder
    $avatar = (string) ($invite['invite_avatar'] ?? '');
    $from = userInviteAvatarDir($invite_id) . "/$avatar";
    if ($avatar !== '' && basename($avatar) === $avatar && is_file($from)) {
        $dir = dirname(__DIR__) . "/uploads/users/$user_id";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (rename($from, "$dir/$avatar")) {
            mysqli_query($mysqli, "UPDATE users SET user_avatar = '" . escapeSql($avatar) . "' WHERE user_id = $user_id");
        }
    }
    userInviteRemoveAvatar($invite_id);

    // The invite is theirs now; any other waiting for the address is done with
    mysqli_query($mysqli, "UPDATE user_invites SET invite_user_id = $user_id WHERE invite_id = $invite_id");
    mysqli_query($mysqli, "UPDATE user_invites SET invite_cancelled_at = NOW(), invite_vault = ''
        WHERE invite_email = '" . escapeSql($email) . "' AND invite_id <> $invite_id AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL");

    // In their name - it was them
    $session_user_id = $user_id;
    $role = (string) $invite['role_name'];
    logAudit("User", "Create", escapeSql("$name ($email) accepted " . ($by !== '' ? "$by's" : 'an') . " invite and joined as $role"), 0, $user_id);
    if (intval($invite['invite_created_by']) && intval($invite['invite_by_status'] ?? 0) === 1 && empty($invite['invite_by_archived_at'])) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'User', notification = '" . escapeSql(mb_substr("$name accepted your invite and joined as $role", 0, 1000)) . "',
            notification_action = '" . escapeSql('/admin/users.php?q=' . rawurlencode($email)) . "', notification_entity_id = $user_id, notification_user_id = " . intval($invite['invite_created_by']));
    }
    return $user_id;
}

/** How an agent joined, when it was by an invite - ['by', 'at'] or null. */
function userInviteJoined($user_id) {
    global $mysqli;
    if (!userInvitesReady() || !intval($user_id)) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invite_accepted_at, inviter.user_name AS invite_by_name FROM user_invites
        LEFT JOIN users inviter ON inviter.user_id = invite_created_by AND invite_created_by > 0
        WHERE invite_user_id = " . intval($user_id) . " AND invite_accepted_at IS NOT NULL ORDER BY invite_accepted_at DESC LIMIT 1"));
    return $row ? ['by' => trim((string) $row['invite_by_name']), 'at' => $row['invite_accepted_at']] : null;
}

/** Invites waiting - in date or run out - newest first, with their role. For Get started. */
function userInvitesWaiting() {
    global $mysqli;
    $rows = [];
    if (!userInvitesReady()) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT user_invites.*, role_name, role_is_admin FROM user_invites LEFT JOIN user_roles ON role_id = invite_role_id
        WHERE invite_accepted_at IS NULL AND invite_cancelled_at IS NULL ORDER BY invite_created_at DESC, invite_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/*
 * ---------- nightly ----------
 */

/**
 * Tidy the invites: one that has run out loses what it kept of the vault
 * (sending it again seals it afresh), and cancelled ones, and ones that ran
 * out, go after 90 days with their picture. How many went.
 */
function userInvitesNightly() {
    global $mysqli;
    if (!userInvitesReady()) {
        return 0;
    }
    mysqli_query($mysqli, "UPDATE user_invites SET invite_vault = '' WHERE invite_vault <> '' AND invite_accepted_at IS NULL AND invite_expires_at < NOW()");
    $gone = 0;
    $sql = mysqli_query($mysqli, "SELECT invite_id FROM user_invites WHERE invite_accepted_at IS NULL AND (
        (invite_cancelled_at IS NOT NULL AND invite_cancelled_at < NOW() - INTERVAL 90 DAY)
        OR (invite_cancelled_at IS NULL AND invite_expires_at < NOW() - INTERVAL 90 DAY))");
    while ($row = mysqli_fetch_row($sql)) {
        userInviteRemoveAvatar($row[0]);
        mysqli_query($mysqli, "DELETE FROM user_invites WHERE invite_id = " . intval($row[0]));
        $gone += max(0, intval(mysqli_affected_rows($mysqli)));
    }
    return $gone;
}
