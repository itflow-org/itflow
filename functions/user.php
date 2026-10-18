<?php

/*
 * ITFlow - agents (users with user_type 1): how one is shown, what an admin
 * may change about them, and the checks every change goes through.
 *
 * Shared by Administration > Users (admin/users.php, admin/modals/user/*,
 * admin/post/users.php) and the agent's own account pages (agent/user/*).
 * An agent's password also unlocks the credential vault (their copy of the
 * master key, user_specific_encryption_ciphertext), so a password is only
 * ever set through userSetPassword(), which writes both together.
 */

/** What a user's status is: [status => label, tone]. 2 is the old invite state - shown, never set. */
const USER_STATUSES = [
    1 => ['Active', 'success'],
    0 => ['Disabled', 'secondary'],
    2 => ['Invited', 'info'],
];

/** The shortest password ITFlow takes for an agent. */
const USER_PASSWORD_MIN = 8;

/** Their picture, or their initials on a colour that stays the same for them. $size as contactAvatar(). */
function userAvatar($user_id, $name, $avatar, $size = '') {
    $class = 'itflow-contact-avatar' . ($size ? " itflow-contact-avatar-$size" : '');
    $avatar = html_entity_decode((string) $avatar, ENT_QUOTES, 'UTF-8');
    if ($avatar !== '') {
        return '<img class="' . $class . '" src="/uploads/users/' . intval($user_id) . '/' . escapeHtml(rawurlencode($avatar)) . '" alt="" loading="lazy">';
    }
    $tone = clientInitialsTone($user_id);
    return '<span class="' . $class . ' bg-' . $tone . '-subtle text-' . $tone . '-emphasis" aria-hidden="true">' . escapeHtml(personInitials($name) ?: '?') . '</span>';
}

/** A user's status as a pill. */
function userStatusPill($status, $archived = false) {
    if ($archived) {
        return '<span class="badge rounded-pill text-bg-secondary fw-normal"><i class="fas fa-archive me-1"></i>Archived</span>';
    }
    [$label, $tone] = USER_STATUSES[intval($status)] ?? USER_STATUSES[0];
    $icon = ['success' => 'fa-check-circle', 'secondary' => 'fa-ban', 'info' => 'fa-paper-plane'][$tone];
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis fw-normal"><i class="fas ' . $icon . ' me-1"></i>' . $label . '</span>';
}

/**
 * Their MFA as a pill: on, required but not set up yet (they are asked at
 * their next sign in), or off.
 */
function userMfaPill($token, $forced) {
    if (trim((string) $token) !== '') {
        return '<span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal" title="Signs in with a code from an authenticator app' . (intval($forced) ? ' - required' : '') . '"><i class="fas fa-lock me-1"></i>MFA' . (intval($forced) ? ' · required' : '') . '</span>';
    }
    if (intval($forced)) {
        return '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal" title="Required - they set it up at their next sign in"><i class="fas fa-hourglass-half me-1"></i>MFA pending</span>';
    }
    return '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis fw-normal" title="Password only"><i class="fas fa-unlock me-1"></i>No MFA</span>';
}

/** One agent by id, with their settings and role - raw, or null. */
function userRow($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    if (!$user_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT users.user_id, user_name, user_email, user_auth_method, user_type, user_status, user_token, user_avatar,
            user_created_at, user_updated_at, user_archived_at, user_role_id, role_name, role_is_admin, role_archived_at,
            user_config_force_mfa, user_config_calendar_first_day, user_config_theme_dark, user_config_records_per_page, user_config_signature
        FROM users
        LEFT JOIN user_settings ON user_settings.user_id = users.user_id
        LEFT JOIN user_roles ON role_id = user_role_id
        WHERE users.user_id = $user_id AND user_type = 1 LIMIT 1"));
    return $row ?: null;
}

/**
 * For admin post handlers: the agent a request names - or back with an error
 * when there is none. With $not_self, the admin's own account is refused:
 * that is changed from their Account pages, so nobody locks themselves out
 * from here.
 */
function userForRequest($user_id, $not_self = true) {
    global $session_user_id;
    $row = userRow($user_id);
    if (!$row) {
        flashAlert('That user no longer exists.', 'error');
        redirect();
    }
    if ($not_self && intval($row['user_id']) === intval($session_user_id)) {
        flashAlert('Change your own account from <strong>Account</strong> in the top right.', 'error');
        redirect();
    }
    return $row;
}

/** For admin modals: the same, or the modal's error. */
function userForModal($user_id) {
    $row = userRow($user_id);
    if (!$row) {
        echo json_encode(['error' => 'That user no longer exists.']);
        exit;
    }
    return $row;
}

/** The agents a bulk action names (user_ids[]), never the admin themselves - [id => row]. */
function usersForBulk() {
    global $session_user_id;
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['user_ids'] ?? []))) as $user_id) {
        $row = userRow($user_id);
        if ($row && $user_id !== intval($session_user_id)) {
            $rows[$user_id] = $row;
        }
    }
    return $rows;
}

/** The roles an agent can have - [role_id => row], admins first. */
function userRoles($include_role_id = 0) {
    global $mysqli;
    $roles = [];
    $sql = mysqli_query($mysqli, "SELECT role_id, role_name, role_description, role_is_admin, role_archived_at FROM user_roles
        WHERE role_type = 1 AND (role_archived_at IS NULL OR role_id = " . intval($include_role_id) . ")
        ORDER BY role_is_admin DESC, role_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $roles[intval($row['role_id'])] = $row;
    }
    return $roles;
}

/** A role in a picker - a shield for an admin role, and what it is for underneath. */
function selectOptionRole($role) {
    return selectOptionData([
        'icon' => 'fas ' . (intval($role['role_is_admin']) ? 'fa-user-shield' : 'fa-user-tag'),
        'description' => implode(' · ', array_filter([intval($role['role_is_admin']) ? 'Administrator - every client, every setting' : '', $role['role_description']])),
    ]);
}

/** How many active agents with an admin role there are, leaving out $except_user_id. */
function userActiveAdminCount($except_user_id = 0) {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM users JOIN user_roles ON role_id = user_role_id
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL AND role_is_admin = 1 AND user_id <> " . intval($except_user_id)))[0]);
}

/** Would taking this agent out of the admins (a new role, disabled, archived) leave nobody to run the place? */
function userIsLastAdmin($row) {
    return intval($row['role_is_admin']) === 1 && intval($row['user_status']) === 1 && empty($row['user_archived_at'])
        && userActiveAdminCount($row['user_id']) === 0;
}

/**
 * The name and email of New User / Edit User / the agent's own details,
 * checked - [name, email] raw, or null with $error. The email signs them in,
 * so no other account that can sign in may have it.
 */
function userCheckIdentity($post, $user_id, &$error = '') {
    global $mysqli;
    $name = trim((string) ($post['name'] ?? ''));
    $email = trim((string) ($post['email'] ?? ''));
    if ($name === '') {
        $error = 'A user needs a name.';
        return null;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '<strong>' . ($email ?: 'Nothing') . '</strong> is not an email address.';
        return null;
    }
    $email_sql = escapeSql($email);
    $taken = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_type FROM users WHERE user_email = '$email_sql' AND user_archived_at IS NULL AND user_id <> " . intval($user_id) . " LIMIT 1"));
    if ($taken) {
        $error = '<strong>' . $email . '</strong> already signs in to ' . (intval($taken['user_type']) === 1 ? 'another agent account' : 'a client portal account') . ' - each account needs its own email.';
        return null;
    }
    return [mb_substr($name, 0, 200), mb_substr($email, 0, 200)];
}

/** A role an admin may give: one that exists and is not archived (or the one they have). Its row, or null with $error. */
function userCheckRole($role_id, $current_role_id, &$error = '') {
    $roles = userRoles($current_role_id);
    $role_id = intval($role_id);
    if (!isset($roles[$role_id]) || ($roles[$role_id]['role_archived_at'] && $role_id !== intval($current_role_id))) {
        $error = 'Pick a role for them.';
        return null;
    }
    return $roles[$role_id];
}

/** Is this a password ITFlow takes? '' when it is, the reason when not. */
function userPasswordProblem($password) {
    if (strlen((string) $password) < USER_PASSWORD_MIN) {
        return 'A password is at least ' . USER_PASSWORD_MIN . ' characters.';
    }
    return '';
}

/**
 * Set an agent's password - the hash they sign in with, and their copy of
 * the vault key under it. Exactly what was typed, as the sign in page checks
 * exactly what is typed.
 */
function userSetPassword($user_id, $password) {
    global $mysqli;
    $hash = escapeSql(password_hash((string) $password, PASSWORD_DEFAULT));
    $ciphertext = escapeSql(encryptUserSpecificKey((string) $password));
    mysqli_query($mysqli, "UPDATE users SET user_password = '$hash', user_specific_encryption_ciphertext = '$ciphertext' WHERE user_id = " . intval($user_id));
}

/** Turn an agent's MFA off - and forget the browsers it trusted, which have to sign in afresh. */
function userResetMfa($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    mysqli_query($mysqli, "UPDATE users SET user_token = '' WHERE user_id = $user_id");
    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $user_id");
}

/**
 * Take an uploaded picture as an agent's avatar - a real image (checked,
 * not trusted by its name), up to 5 MB - replacing the old one. True when
 * set, false with $error when not.
 */
function userSaveAvatar($user_id, $file, &$error = '') {
    global $mysqli;
    $user_id = intval($user_id);
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return false;
    }
    $new_name = checkFileUpload($file, ['jpg', 'jpeg', 'gif', 'png', 'webp']);
    $image = $new_name ? @getimagesize($file['tmp_name']) : false;
    if (!$new_name || !$image || !in_array($image[2], [IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        $error = 'The avatar has to be a JPG, PNG, GIF or WebP picture.';
        return false;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        $error = 'The avatar can be up to 5 MB.';
        return false;
    }
    $dir = dirname(__DIR__) . "/uploads/users/$user_id";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], "$dir/$new_name")) {
        $error = 'The avatar could not be saved.';
        return false;
    }
    userRemoveAvatar($user_id);
    mysqli_query($mysqli, "UPDATE users SET user_avatar = '" . escapeSql($new_name) . "' WHERE user_id = $user_id");
    return true;
}

/** Remove an agent's avatar - the file and the reference to it. */
function userRemoveAvatar($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    $old = (string) getFieldById('users', $user_id, 'user_avatar');
    if ($old !== '' && basename($old) === $old && is_file(dirname(__DIR__) . "/uploads/users/$user_id/$old")) {
        unlink(dirname(__DIR__) . "/uploads/users/$user_id/$old");
    }
    mysqli_query($mysqli, "UPDATE users SET user_avatar = NULL WHERE user_id = $user_id");
}

/**
 * The clients an agent is limited to or kept from, from the Client access
 * tab's radios (client_permission[client_id] = allow / deny / '') - replaces
 * whatever they had. Only clients that exist are written.
 */
function userSetClientAccess($user_id, $permissions) {
    global $mysqli;
    $user_id = intval($user_id);
    mysqli_query($mysqli, "DELETE FROM user_client_permissions WHERE user_id = $user_id");
    foreach ((array) $permissions as $client_id => $type) {
        $client_id = intval($client_id);
        if (($type === 'allow' || $type === 'deny') && $client_id) {
            mysqli_query($mysqli, "INSERT INTO user_client_permissions (user_id, client_id, permission_type)
                SELECT $user_id, client_id, '$type' FROM clients WHERE client_id = $client_id");
        }
    }
}

/**
 * The client rules of some agents - [user_id => ['allow' => [client_id =>
 * name], 'deny' => [...]]].
 */
function userClientAccess(array $user_ids) {
    global $mysqli;
    $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
    $access = [];
    foreach ($user_ids as $id) {
        $access[$id] = ['allow' => [], 'deny' => []];
    }
    if ($user_ids) {
        $sql = mysqli_query($mysqli, "SELECT user_id, user_client_permissions.client_id, permission_type, client_name FROM user_client_permissions
            JOIN clients ON clients.client_id = user_client_permissions.client_id
            WHERE user_id IN (" . implode(',', $user_ids) . ") ORDER BY client_name");
        while ($row = mysqli_fetch_assoc($sql)) {
            $access[intval($row['user_id'])][$row['permission_type'] === 'deny' ? 'deny' : 'allow'][intval($row['client_id'])] = $row['client_name'];
        }
    }
    return $access;
}

/** What an agent can see, in words: "All clients", "3 clients", "All but 1". An admin sees everything. */
function userClientAccessText($access, $is_admin) {
    if ($is_admin) {
        return 'All clients (admin)';
    }
    $allow = count($access['allow']);
    $deny = count($access['deny']);
    if ($allow) {
        return $allow . ' client' . ($allow === 1 ? '' : 's') . ($deny ? ", $deny kept out" : '');
    }
    return $deny ? "All but $deny client" . ($deny === 1 ? '' : 's') : 'All clients';
}

/**
 * The last successful sign in of some agents - [user_id => log row with
 * log_created_at, log_ip, log_user_agent], or nothing for one never signed in.
 */
function userLastLogins(array $user_ids) {
    global $mysqli;
    $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
    $logins = [];
    if ($user_ids) {
        $sql = mysqli_query($mysqli, "SELECT log_user_id, log_created_at, log_ip, log_user_agent FROM logs
            WHERE log_id IN (SELECT MAX(log_id) FROM logs WHERE log_user_id IN (" . implode(',', $user_ids) . ") AND log_type = 'Login' AND log_action = 'Success' GROUP BY log_user_id)");
        while ($row = mysqli_fetch_assoc($sql)) {
            $logins[intval($row['log_user_id'])] = $row;
        }
    }
    return $logins;
}

/**
 * An agent's latest sign ins and failed MFA codes, newest first - log rows
 * with log_action, log_created_at, log_ip, log_user_agent. Theirs only.
 */
function userRecentLogins($user_id, $limit = 10) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT log_id, log_action, log_description, log_created_at, log_ip, log_user_agent FROM logs
        WHERE log_user_id = " . intval($user_id) . " AND log_type IN ('Login', 'Login 2FA')
        ORDER BY log_id DESC LIMIT " . max(1, intval($limit)));
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** One of those as a line - a tick for a sign in, a cross for a failed code - with when and from where. Raw in, HTML out. */
function userLoginLineHtml($log) {
    $ok = $log['log_action'] === 'Success';
    return '<div class="d-flex gap-2 align-items-start">'
        . '<i class="fas fa-fw ' . ($ok ? 'fa-check-circle text-success' : 'fa-times-circle text-danger') . ' mt-1" title="' . escapeHtml($log['log_action']) . '"></i>'
        . '<div class="min-w-0"><span title="' . escapeHtml($log['log_created_at']) . '">' . escapeHtml(timeAgo($log['log_created_at'])) . '</span>'
        . ($ok ? '' : ' <span class="text-danger-emphasis">' . escapeHtml($log['log_action'] === 'MFA Failed' ? 'wrong MFA code' : $log['log_action']) . '</span>')
        . '<div class="small text-body-secondary">' . userDeviceHtml($log['log_user_agent']) . ' · <span class="font-monospace">' . escapeHtml($log['log_ip']) . '</span></div></div>'
        . '</div>';
}

/** The browsers that skip an agent's MFA code (remember me) - [user_id => count]. */
function userRememberCounts(array $user_ids) {
    global $mysqli, $config_login_remember_me_expire;
    $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
    $counts = array_fill_keys($user_ids, 0);
    if ($user_ids) {
        $days = max(1, intval($config_login_remember_me_expire ?? 30));
        $sql = mysqli_query($mysqli, "SELECT remember_token_user_id, COUNT(*) FROM remember_tokens
            WHERE remember_token_user_id IN (" . implode(',', $user_ids) . ") AND remember_token_created_at > NOW() - INTERVAL $days DAY GROUP BY remember_token_user_id");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])] = intval($row[1]);
        }
    }
    return $counts;
}

/**
 * The browsers that skip an agent's MFA code, newest first - each with when
 * it was remembered, until when, whether it is the browser asking, and the
 * sign in it was remembered at (for the device and address - the token
 * itself records neither).
 */
function userRememberedBrowsers($user_id) {
    global $mysqli, $config_login_remember_me_expire;
    $user_id = intval($user_id);
    $days = max(1, intval($config_login_remember_me_expire ?? 30));
    $cookie = (string) ($_COOKIE['rememberme'] ?? '');
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT remember_token_id, remember_token_token, remember_token_created_at,
            remember_token_created_at + INTERVAL $days DAY AS remember_token_expires_at
        FROM remember_tokens
        WHERE remember_token_user_id = $user_id AND remember_token_created_at > NOW() - INTERVAL $days DAY
        ORDER BY remember_token_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $created = escapeSql($row['remember_token_created_at']);
        $row['login'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT log_created_at, log_ip, log_user_agent FROM logs
            WHERE log_user_id = $user_id AND log_type = 'Login' AND log_action = 'Success'
            AND log_created_at BETWEEN '$created' - INTERVAL 2 MINUTE AND '$created' + INTERVAL 2 MINUTE
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, log_created_at, '$created')) LIMIT 1"));
        $row['is_current'] = $cookie !== '' && hash_equals((string) $row['remember_token_token'], $cookie);
        unset($row['remember_token_token']);
        $rows[] = $row;
    }
    return $rows;
}

/** The device a sign in came from - "Windows Chrome" with their icons - from its user agent. HTML. */
function userDeviceHtml($user_agent) {
    $parts = array_filter([getOS((string) $user_agent), getWebBrowser((string) $user_agent)], function ($part) {
        return $part !== '-';
    });
    return $parts ? implode(' ', $parts) : 'Unknown device';
}

/** A sign in, in words: when, and from what - "2 hours ago · Windows · Chrome · 203.0.113.9". Raw values in, HTML out. */
function userLoginHtml($log, $with_ip = true) {
    if (!$log) {
        return '<span class="text-body-secondary">Never signed in</span>';
    }
    return '<span title="' . escapeHtml($log['log_created_at']) . '">' . escapeHtml(timeAgo($log['log_created_at'])) . '</span>'
        . '<div class="small text-body-secondary text-nowrap">' . userDeviceHtml($log['log_user_agent'])
        . ($with_ip ? ' · <span class="font-monospace">' . escapeHtml($log['log_ip']) . '</span>' : '') . '</div>';
}

/**
 * The QR code an authenticator app scans to set up MFA, drawn here as an
 * SVG - the secret is never put in an image URL, where it would land in the
 * web server's access log.
 */
function userMfaQrSvg($secret, $email) {
    global $config_app_name;
    // libs/barcode/barcode.php stops the page outright without GD - then it is the secret to type in
    if (!extension_loaded('gd')) {
        return '';
    }
    require_once dirname(__DIR__) . '/libs/barcode/barcode.php';
    $issuer = (string) ($config_app_name ?: 'ITFlow');
    $uri = 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode((string) $email) . '?secret=' . rawurlencode((string) $secret) . '&issuer=' . rawurlencode($issuer);
    $generator = new barcode_generator();
    $svg = $generator->render_svg('qr', $uri, ['w' => 200, 'h' => 200, 'p' => 0]);
    // Shown inline in the page, not as an image - the XML prolog has to go, and it scales with its box
    $svg = preg_replace('/^<\?xml[^>]*>\s*/', '', (string) $svg);
    return preg_replace('/<svg /', '<svg class="itflow-mfa-qr" role="img" aria-label="QR code for your authenticator app" ', $svg, 1);
}

/** An agent's work not finished - [tickets not closed, recurring tickets] assigned to them. */
function userOpenWork($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    return [
        intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tickets WHERE ticket_assigned_to = $user_id AND ticket_closed_at IS NULL"))[0]),
        intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM recurring_tickets WHERE recurring_ticket_assigned_to = $user_id"))[0]),
    ];
}

/**
 * Hand an agent's tickets that are not closed, and their recurring tickets,
 * to another agent - or to nobody ($to_user_id 0) - each ticket's history
 * saying so and why ("when Sam was archived").
 */
function userHandOverWork($user_id, $to_user_id, $why) {
    global $mysqli, $session_name;
    $user_id = intval($user_id);
    $to_user_id = intval($to_user_id);
    $to_name = $to_user_id ? (string) getFieldById('users', $to_user_id, 'user_name') : '';
    $ticket_ids = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_assigned_to = $user_id AND ticket_closed_at IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        $ticket_ids[] = intval($row[0]);
    }
    mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $to_user_id WHERE ticket_assigned_to = $user_id AND ticket_closed_at IS NULL");
    mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_assigned_to = $to_user_id WHERE recurring_ticket_assigned_to = $user_id");
    foreach ($ticket_ids as $ticket_id) {
        logTicketHistory($ticket_id, $to_name !== '' ? "$session_name reassigned the ticket to " . escapeSql($to_name) . " $why" : "$session_name unassigned the ticket $why");
    }
}

/* ------------------------------------------------------------ Emails to an agent about their account */

/** Where an agent signs in - with the login key when one is required. */
function userSignInUrl() {
    global $mysqli, $config_base_url;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_login_key_required, config_login_key_secret FROM settings WHERE company_id = 1")) ?: [];
    return "https://$config_base_url/login.php" . (!empty($row['config_login_key_required']) ? '?key=' . rawurlencode((string) $row['config_login_key_secret']) : '');
}

/** The app's name for these emails - Admin > Settings, ITFlow by default. */
function userMailAppName() {
    global $config_app_name;
    return trim((string) ($config_app_name ?? '')) !== '' ? (string) $config_app_name : 'ITFlow';
}

/*
 * A new agent's sign in details - their email, the password as it was set
 * (theirs to change) and where to sign in. True when queued.
 */
function userMailNewAccount($name, $email, $password, $force_mfa) {
    $company_name = (string) (billingCompany()['company_name'] ?? '');
    $app = userMailAppName();
    return (bool) emailQueue([['email' => $email, 'name' => $name, 'footnote' => "You get this because an account was set up for you in $company_name's $app."]],
        "Your new $company_name $app account", [
            'kind' => 'Your account', 'tone' => 'primary', 'preview' => "Your $app sign in details",
            'title' => "Your $app account is ready",
            'intro' => ["An $app account has been set up for you. Sign in with these details, then change your password."
                . ($force_mfa ? ' Have an authenticator app ready - you will set up multi-factor authentication the first time.' : '')],
            'summary' => ['Email' => (string) $email, 'Password' => (string) $password],
            'button' => ['label' => 'Sign in', 'url' => userSignInUrl()],
            'after' => ['This email has your password in it - delete it once you have changed it.'],
        ]);
}

/** An agent's own email, sent from the system - their account was changed ($what, raw). Queued; nothing when mail is not set up. */
function userNotifyChange($name, $email, $what) {
    $app = userMailAppName();
    emailQueue([['email' => $email, 'name' => $name, 'footnote' => "You get this about your own $app account."]], "$app account update for $name", [
        'kind' => 'Your account', 'tone' => 'warning', 'preview' => "Your $app account was changed",
        'title' => 'Your account was changed',
        'intro' => ["Your $app account was changed: " . $what . '.'],
        'callout' => "If this was not you, tell your $app administrator straight away.",
        'button' => ['label' => "Sign in to $app", 'url' => userSignInUrl()],
    ]);
}

/*
 * Tell an agent about a sign in to their account - $kind new (a device and
 * address they hadn't signed in from) or mfa (the right password, a wrong
 * code). Raw values in.
 */
function userMailSignInAlert($name, $email, $kind, $ip, $user_agent) {
    global $config_base_url;
    $app = userMailAppName();
    $mfa = $kind === 'mfa';
    $device = trim(strip_tags(userDeviceHtml((string) $user_agent)));
    emailQueue([['email' => $email, 'name' => $name, 'footnote' => "You get this about your own $app account."]],
        $mfa ? "Important: $app failed 2FA login attempt for $name" : "$app new login for $name", [
            'kind' => 'Security', 'tone' => $mfa ? 'danger' : 'warning',
            'preview' => $mfa ? 'Your password was used, but the 2FA code was wrong' : 'A sign in from a new device',
            'title' => $mfa ? 'Someone used your password' : 'A new sign in to your account',
            'intro' => [$mfa
                ? "Someone signed in to your $app account with your password, but the multi-factor code was wrong, so they were stopped."
                : "Someone signed in to your $app account from a device and address it hasn't seen before. If that was you, there is nothing to do."],
            'summary' => ['When' => date('D j M Y, g:i A'), 'Device' => $device !== '' ? $device : 'Unknown device', 'IP address' => (string) $ip],
            'callout' => $mfa ? 'If that was not you, your password is known to someone else - change it now.' : 'If that was not you, change your password now and tell your administrator.',
            'button' => ['label' => 'Review your sign in security', 'url' => "https://$config_base_url/agent/user/user_security.php"],
        ]);
}
