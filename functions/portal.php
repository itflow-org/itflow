<?php

/*
 * The client portal's accounts, shared by the portal, the sign-in pages, the
 * agent side and cron: links to set a password (an invite, Forgot password),
 * two-step sign-in, the ticket emails a contact asked for, announcements on
 * the portal's home page, and View as contact for agents.
 */

const PORTAL_RESET_LINK_MINUTES = 60;          // Forgot password
const PORTAL_INVITE_LINK_DAYS = 7;             // an invite to set a first password
const PORTAL_RESET_REQUEST_GAP_MINUTES = 5;    // one Forgot password email per account in this long

/** The ticket emails a contact can ask for about their own tickets (contacts.contact_ticket_emails). */
const PORTAL_TICKET_EMAILS = [
    'all' => ['label' => 'Every email', 'text' => 'When a ticket is raised for you, every reply and update, when it is fixed and when it is closed'],
    'replies' => ['label' => 'Replies and fixes only', 'text' => 'Our replies and when it is fixed - not the emails saying a ticket was raised or closed'],
];

/** About the company's other tickets - primary and technical contacts (contacts.contact_company_ticket_emails). */
const PORTAL_COMPANY_TICKET_EMAILS = [
    'none' => ['label' => 'Only the tickets I am on', 'text' => 'Nothing about tickets other people raise'],
    'new' => ['label' => 'New tickets', 'text' => 'An email when anyone raises one - in the portal, by email or by calling us'],
    'all' => ['label' => 'New tickets and every update', 'text' => 'As if you were copied in on all of them'],
];

/** How an announcement looks - its badge and icon. */
const PORTAL_ANNOUNCEMENT_TONES = [
    'info' => ['label' => 'Notice', 'icon' => 'fa-info-circle'],
    'warning' => ['label' => 'Heads up', 'icon' => 'fa-exclamation-triangle'],
    'danger' => ['label' => 'Urgent', 'icon' => 'fa-exclamation-circle'],
];

/*
 * ---------- password links ----------
 *
 * The token goes out in the link and only its SHA-256 is kept, with when it
 * runs out (user_password_reset_token_expire), so a copy of the database holds
 * no link that works. Using a link spends it; a new one replaces the last.
 */

/** A link for a portal user to set their password, working for $minutes. '' for no such portal user. */
function portalPasswordLink($user_id, $minutes) {
    global $mysqli, $config_base_url;
    $user_id = intval($user_id);
    $minutes = max(1, intval($minutes));
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_email, contact_client_id FROM users JOIN contacts ON contact_user_id = user_id
        WHERE user_id = $user_id AND user_type = 2 LIMIT 1"));
    if (!$row) {
        return '';
    }
    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);
    mysqli_query($mysqli, "UPDATE users SET user_password_reset_token = '$hash', user_password_reset_token_expire = NOW() + INTERVAL $minutes MINUTE WHERE user_id = $user_id");
    return "https://$config_base_url/client/login_reset.php?" . http_build_query(['email' => $row['user_email'], 'token' => $token, 'client' => intval($row['contact_client_id'])]);
}

/** The portal user a link is for - its token right and not run out, a password account still in use - or null. */
function portalPasswordLinkUser($email, $token, $client_id) {
    global $mysqli;
    $email = trim((string) $email);
    $token = (string) $token;
    $client_id = intval($client_id);
    if (!$client_id || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9]{16,128}$/', $token)) {
        return null;
    }
    $email_sql = escapeSql($email);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_email, user_password_reset_token, contact_id, contact_name
        FROM users JOIN contacts ON contact_user_id = user_id
        WHERE user_email = '$email_sql' AND contact_client_id = $client_id AND contact_archived_at IS NULL
          AND user_type = 2 AND user_auth_method = 'local' AND user_status = 1 AND user_archived_at IS NULL
          AND user_password_reset_token IS NOT NULL AND user_password_reset_token_expire > NOW()
        LIMIT 1"));
    if (!$row || !hash_equals((string) $row['user_password_reset_token'], hash('sha256', $token))) {
        return null;
    }
    return $row;
}

/** Spend a portal user's password link - used, or no longer wanted (access turned off, a Microsoft account). */
function portalPasswordLinkClear($user_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE users SET user_password_reset_token = NULL, user_password_reset_token_expire = NULL WHERE user_id = " . intval($user_id));
}

/** When a portal user last signed in, or null for never. */
function portalContactLastSignIn($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    if (!$user_id) {
        return null;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT MAX(log_created_at) FROM logs WHERE log_user_id = $user_id AND log_type = 'Client Login' AND log_action = 'Success'"));
    return $row[0] ?? null;
}

/*
 * ---------- two-step sign-in ----------
 *
 * A portal user's authenticator secret is users.user_token, as an agent's is,
 * and a browser ticked to be remembered is a remember_tokens row - so sign-in
 * (login.php) treats both alike. Microsoft 365 accounts are left to Microsoft.
 */

/** How many browsers this user ticked to be remembered, still in date. */
function portalRememberedBrowsers($user_id) {
    global $mysqli, $config_login_remember_me_expire;
    $days = max(1, intval($config_login_remember_me_expire));
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM remember_tokens
        WHERE remember_token_user_id = " . intval($user_id) . " AND remember_token_created_at > NOW() - INTERVAL $days DAY"))[0]);
}

/** Turn two-step sign-in off for a portal user and forget their remembered browsers. */
function portalMfaReset($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    mysqli_query($mysqli, "UPDATE users SET user_token = '' WHERE user_id = $user_id AND user_type = 2");
    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $user_id");
}

/** The QR code an authenticator app scans for a portal user - an SVG, '' without GD (then the secret is typed in). */
function portalMfaQrSvg($secret, $email) {
    global $mysqli;
    if (!extension_loaded('gd')) {
        return '';
    }
    require_once dirname(__DIR__) . '/libs/barcode/barcode.php';
    $company = mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"));
    $issuer = trim((string) ($company[0] ?? '')) ?: 'Client portal';
    $uri = 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode((string) $email) . '?secret=' . rawurlencode((string) $secret) . '&issuer=' . rawurlencode($issuer);
    $svg = (new barcode_generator())->render_svg('qr', $uri, ['w' => 200, 'h' => 200, 'p' => 0]);
    $svg = preg_replace('/^<\?xml[^>]*>\s*/', '', (string) $svg);
    return preg_replace('/<svg /', '<svg class="itflow-mfa-qr" role="img" aria-label="QR code for your authenticator app" ', $svg, 1);
}

/*
 * ---------- emails to contacts ----------
 */

/**
 * An email to a contact about their portal account - a password changed,
 * two-step sign-in turned on or off - in the billing emails' look. Nothing
 * without a mail provider. $intro is plain text.
 */
function portalAccountEmail($email, $name, $subject, $intro, $button = null) {
    global $mysqli, $config_smtp_provider, $config_mail_from_email, $config_mail_from_name;
    if (empty($config_smtp_provider) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($config_mail_from_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $html = billingEmailHtml(array_filter([
        'kind' => 'Client portal', 'tone' => 'primary', 'name' => (string) $name, 'title' => (string) $subject,
        'intro' => (array) $intro,
        'button' => $button,
        'footnote' => 'You received this because of a change to your client portal account. If it was not you, reply to this email straight away.',
    ]));
    addToMailQueue([[
        'from' => mysqli_real_escape_string($mysqli, (string) $config_mail_from_email),
        'from_name' => mysqli_real_escape_string($mysqli, (string) $config_mail_from_name),
        'recipient' => mysqli_real_escape_string($mysqli, (string) $email),
        'recipient_name' => mysqli_real_escape_string($mysqli, (string) $name),
        'subject' => mysqli_real_escape_string($mysqli, (string) $subject),
        'body' => mysqli_real_escape_string($mysqli, $html),
    ]]);
    return true;
}

/*
 * ---------- announcements ----------
 */

/** The announcements a client's portal shows now - to everyone and to this client - newest first. */
function portalAnnouncementsFor($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT announcement_id, announcement_title, announcement_body, announcement_tone, announcement_starts_at, announcement_created_at
        FROM portal_announcements
        WHERE announcement_archived_at IS NULL AND announcement_client_id IN (0, $client_id)
          AND (announcement_starts_at IS NULL OR announcement_starts_at <= NOW())
          AND (announcement_ends_at IS NULL OR announcement_ends_at > NOW())
        ORDER BY COALESCE(announcement_starts_at, announcement_created_at) DESC, announcement_id DESC
        LIMIT 5");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** An announcement's text as HTML - escaped, its lines kept, and web addresses made links. */
function portalAnnouncementBodyHtml($text) {
    $html = escapeHtml(trim((string) $text));
    $html = preg_replace_callback('~\bhttps?://[^\s<]+~i', function ($m) {
        $url = $m[0];
        $tail = '';
        while ($url !== '' && strpos('.,;:!?)', substr($url, -1)) !== false) {
            $tail = substr($url, -1) . $tail;
            $url = substr($url, 0, -1);
        }
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $tail;
    }, $html);
    return nl2br($html);
}

/** Where an announcement stands - ['key' => scheduled|showing|ended|archived, 'label' => ...]. */
function portalAnnouncementState(array $row) {
    $now = time();
    if (!empty($row['announcement_archived_at'])) {
        return ['key' => 'archived', 'label' => 'Archived'];
    }
    if (!empty($row['announcement_ends_at']) && strtotime($row['announcement_ends_at']) <= $now) {
        return ['key' => 'ended', 'label' => 'Ended'];
    }
    if (!empty($row['announcement_starts_at']) && strtotime($row['announcement_starts_at']) > $now) {
        return ['key' => 'scheduled', 'label' => 'Scheduled'];
    }
    return ['key' => 'showing', 'label' => 'Showing'];
}

/*
 * ---------- View as contact ----------
 *
 * An agent opens the portal as one of a client's contacts to see what they
 * see - read only, from the contact's page. Allowed for an agent who may see
 * that client and has read access to clients and support; billing shows only
 * when they also have read access to sales and financial. Checked again on
 * every portal page (client/includes/check_login.php), so taking an agent's
 * access away ends it.
 */

/** What an agent may see of the portal as a contact, or null when they may not. */
function portalViewAsAccess($agent_user_id, $contact_id) {
    global $mysqli, $config_client_portal_enable;
    $agent_user_id = intval($agent_user_id);
    $contact_id = intval($contact_id);
    if (intval($config_client_portal_enable) !== 1 || !$agent_user_id || !$contact_id) {
        return null;
    }
    $agent = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_type, user_status, user_archived_at, user_role_id, role_is_admin
        FROM users LEFT JOIN user_roles ON role_id = user_role_id WHERE user_id = $agent_user_id LIMIT 1"));
    if (!$agent || intval($agent['user_type']) !== 1 || intval($agent['user_status']) !== 1 || $agent['user_archived_at'] !== null) {
        return null;
    }
    $admin = intval($agent['role_is_admin'] ?? 0) === 1;
    $level = function ($module) use ($mysqli, $admin, $agent) {
        if ($admin) {
            return 3;
        }
        $module = escapeSql($module);
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_role_permission_level FROM user_role_permissions
            JOIN modules ON modules.module_id = user_role_permissions.module_id
            WHERE module_name = '$module' AND user_role_id = " . intval($agent['user_role_id']) . " LIMIT 1"));
        return intval($row[0] ?? 0);
    };
    if ($level('module_client') < 1 || $level('module_support') < 1) {
        return null;
    }
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, contact_user_id, client_name
        FROM contacts JOIN clients ON client_id = contact_client_id
        WHERE contact_id = $contact_id AND contact_archived_at IS NULL AND client_archived_at IS NULL LIMIT 1"));
    if (!$contact) {
        return null;
    }
    $client_id = intval($contact['contact_client_id']);
    if (!$admin) {
        $allow = $deny = [];
        $sql = mysqli_query($mysqli, "SELECT client_id, permission_type FROM user_client_permissions WHERE user_id = $agent_user_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            if ($row['permission_type'] === 'deny') {
                $deny[] = intval($row['client_id']);
            } else {
                $allow[] = intval($row['client_id']);
            }
        }
        if (($allow && !in_array($client_id, $allow, true)) || in_array($client_id, $deny, true)) {
            return null;
        }
    }
    return [
        'contact_id' => $contact_id,
        'contact_name' => (string) $contact['contact_name'],
        'client_id' => $client_id,
        'client_name' => (string) $contact['client_name'],
        'user_id' => intval($contact['contact_user_id']),
        'agent_user_id' => $agent_user_id,
        'agent_name' => (string) $agent['user_name'],
        'billing' => $level('module_sales') >= 1 && $level('module_financial') >= 1,
    ];
}
