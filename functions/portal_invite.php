<?php

/*
 * Client portal invites and sign-ups.
 *
 * An invite asks someone by email to join a client's portal - an agent sends
 * it from the client's Contacts (the Invites tab), a primary contact from
 * Contacts in the portal. Nobody is a contact until they accept: the link
 * opens client/join.php, where they put in their name, title, phone and a
 * password (or choose their Microsoft 365 account), and they are made a
 * contact with portal access - or, when the client already has a contact
 * with that address, that contact is given access.
 *
 * A sign-up is the same, asked for by the person. Create an account on the
 * sign-in page (client/signup.php) takes an address at one of a client's
 * Domains, for a client that lets people sign up (clients.client_portal_signup,
 * with settings.config_client_portal_signup on), and emails a link to finish.
 * The address is all that is checked - they join as a plain contact, who sees
 * the tickets they raise, and the agents and the client's primary contact
 * are told. What the page says never depends on the address, so it can't be
 * used to find out who has an account.
 *
 * Both are portal_invites rows (invite_kind invite or signup). The link
 * carries a token of which only the SHA-256 is kept, and it runs out - an
 * invite after PORTAL_INVITE_LINK_DAYS, a sign-up after
 * PORTAL_SIGNUP_LINK_HOURS. Using it spends it; sending it again makes a new
 * token, so the old link stops working; cancelling stops it.
 */

const PORTAL_SIGNUP_LINK_HOURS = 24;           // a link to finish signing up
const PORTAL_INVITE_MAX = 50;                  // addresses in one go
const PORTAL_SIGNUP_IP_LIMIT = 20;             // sign-ups asked for from one address in 10 minutes - an office signing up together

/** Has the 3.4.6 update run? Until it has, nothing here is shown or done. */
function portalInvitesReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.4.6', '>=');
    }
    return $ready;
}

/*
 * ---------- who an address is ----------
 */

/**
 * The people in what was typed - addresses one to a line or separated by
 * commas or semicolons, each with an optional name: jamie@acme.com, Jamie Lee
 * <jamie@acme.com>, "Lee, Jamie" <jamie@acme.com>. ['people' => [[email,
 * name]], 'bad' => [what wasn't an address]], each address once.
 */
function portalInviteParse($text) {
    $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
    $entries = [];
    $current = '';
    $quoted = false;
    $angled = false;
    $length = strlen($text);
    for ($i = 0; $i < $length; $i++) {
        $char = $text[$i];
        if ($char === '"' && !$angled) {
            $quoted = !$quoted;
        } elseif ($char === '<' && !$quoted) {
            $angled = true;
        } elseif ($char === '>' && !$quoted) {
            $angled = false;
        } elseif (($char === "\n" || $char === ',' || $char === ';') && !$quoted && !$angled) {
            $entries[] = $current;
            $current = '';
            continue;
        }
        $current .= $char;
    }
    $entries[] = $current;

    $people = [];
    $bad = [];
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        $name = '';
        if (preg_match('/^(.*)<\s*([^<>\s]+)\s*>$/s', $entry, $m)) {
            $name = trim(trim($m[1]), "\"' \t");
            $email = $m[2];
        } elseif (preg_match('/^(.*\s)?(\S+@\S+)$/s', $entry, $m)) {
            $name = trim(trim((string) $m[1]), "\"' \t");
            $email = $m[2];
        } else {
            $email = $entry;
        }
        $email = trim($email, " \t\"'");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
            $bad[] = mb_substr($entry, 0, 100);
            continue;
        }
        $key = strtolower($email);
        if (!isset($people[$key])) {
            $people[$key] = ['email' => $email, 'name' => mb_substr(preg_replace('/\s+/', ' ', $name), 0, 200)];
        } elseif ($people[$key]['name'] === '' && $name !== '') {
            $people[$key]['name'] = mb_substr($name, 0, 200);
        }
    }
    return ['people' => array_values($people), 'bad' => $bad];
}

/**
 * Where an address stands at a client: state new (nobody here has it),
 * contact (a contact without portal access - or with it turned off; contact_id,
 * name, user_id, access, primary), member (a contact who signs in already), elsewhere
 * (another account signs in with it - an agent, or a contact at another
 * client; type 1 or 2, and on for a portal account that can sign in).
 */
function portalInviteWho($email, $client_id) {
    global $mysqli;
    $email_sql = escapeSql(trim((string) $email));
    $client_id = intval($client_id);
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_primary, user_id, user_auth_method, user_status, user_archived_at
        FROM contacts LEFT JOIN users ON user_id = contact_user_id AND user_type = 2
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_email = '$email_sql'
        ORDER BY (user_id IS NOT NULL) DESC, contact_id ASC LIMIT 1"));
    $access = $contact ? portalContactAccess($contact) : null;
    if ($access && in_array($access['key'], ['local', 'azure'], true)) {
        return ['state' => 'member', 'contact_id' => intval($contact['contact_id']), 'name' => (string) $contact['contact_name'], 'user_id' => intval($contact['user_id']), 'access' => $access['key']];
    }
    $own_user = intval($contact['user_id'] ?? 0);
    $other = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_type, user_auth_method, user_status FROM users
        WHERE user_email = '$email_sql' AND user_archived_at IS NULL AND user_id <> $own_user LIMIT 1"));
    if ($other) {
        $type = intval($other['user_type']);
        return ['state' => 'elsewhere', 'user_id' => intval($other['user_id']), 'type' => $type,
            'on' => $type === 2 && intval($other['user_status']) === 1 && in_array($other['user_auth_method'], ['local', 'azure'], true), 'access' => (string) $other['user_auth_method']];
    }
    if ($contact) {
        return ['state' => 'contact', 'contact_id' => intval($contact['contact_id']), 'name' => (string) $contact['contact_name'], 'user_id' => $own_user, 'access' => $access['key'],
            'primary' => intval($contact['contact_primary']) === 1];
    }
    return ['state' => 'new'];
}

/*
 * ---------- invites ----------
 */

/** An invite or sign-up, with the client and who sent it - or null. */
function portalInviteRow($invite_id) {
    global $mysqli;
    $invite_id = intval($invite_id);
    if (!$invite_id) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT portal_invites.*, client_name, client_archived_at, client_portal_signup,
            agent.user_name AS invite_by_agent, agent.user_status AS invite_by_agent_status, agent.user_archived_at AS invite_by_agent_archived_at,
            by_contact.contact_name AS invite_by_contact, by_contact.contact_email AS invite_by_contact_email
        FROM portal_invites
        JOIN clients ON client_id = invite_client_id
        LEFT JOIN users agent ON agent.user_id = invite_created_by AND invite_created_by > 0
        LEFT JOIN contacts by_contact ON by_contact.contact_id = invite_created_by_contact_id AND invite_created_by_contact_id > 0
        WHERE invite_id = $invite_id LIMIT 1")) ?: null;
}

/** Who sent an invite, in words - an agent's name, a primary contact's, or '' for a sign-up. */
function portalInviteBy(array $row) {
    if (($row['invite_kind'] ?? '') === 'signup') {
        return '';
    }
    if (intval($row['invite_created_by_contact_id'] ?? 0)) {
        return (string) ($row['invite_by_contact'] ?? '');
    }
    return (string) ($row['invite_by_agent'] ?? '');
}

/** Where an invite stands: pending, expired, accepted or cancelled. */
function portalInviteState(array $row) {
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

/** The link that opens the join page for an invite and its token. */
function portalInviteUrl($invite_id, $token) {
    global $config_base_url;
    return "https://$config_base_url/client/join.php?" . http_build_query(['invite' => intval($invite_id), 'token' => (string) $token]);
}

/** A new token for an invite or sign-up - kept as its SHA-256, running out again from now. The token, for the link. */
function portalInviteNewToken($invite_id) {
    global $mysqli;
    $invite_id = intval($invite_id);
    $kind = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT invite_kind FROM portal_invites WHERE invite_id = $invite_id"))[0] ?? 'invite');
    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);
    $expires = $kind === 'signup' ? 'INTERVAL ' . PORTAL_SIGNUP_LINK_HOURS . ' HOUR' : 'INTERVAL ' . PORTAL_INVITE_LINK_DAYS . ' DAY';
    mysqli_query($mysqli, "UPDATE portal_invites SET invite_token_hash = '$hash', invite_expires_at = NOW() + $expires WHERE invite_id = $invite_id");
    return $token;
}

/**
 * The invite or sign-up a link is for - its token right - with where it
 * stands (portalInviteState(), or gone for a link that matches nothing or a
 * client archived since): ['row' => row or null, 'state' => ...].
 */
function portalInviteByLink($invite_id, $token) {
    $token = is_string($token) ? $token : '';
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return ['row' => null, 'state' => 'gone'];
    }
    $row = portalInviteRow($invite_id);
    if (!$row || !hash_equals((string) $row['invite_token_hash'], hash('sha256', $token))) {
        return ['row' => null, 'state' => 'gone'];
    }
    if (!empty($row['client_archived_at'])) {
        return ['row' => null, 'state' => 'gone'];
    }
    return ['row' => $row, 'state' => portalInviteState($row)];
}

/**
 * Email an invite or a sign-up its link - $token from portalInviteNewToken().
 * An invite says who sent it; a sign-up that someone - probably them - asked
 * for it. Notes when it went. False when it can't go.
 */
function portalInviteMail($invite_id, $token) {
    global $mysqli;
    $row = portalInviteRow($invite_id);
    if (!$row || !portalMailReady() || !filter_var($row['invite_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $company_name = (string) (billingCompany()['company_name'] ?? '');
    $client_name = (string) $row['client_name'];
    $url = portalInviteUrl($row['invite_id'], $token);
    $greet = trim((string) $row['invite_name']);

    if ($row['invite_kind'] === 'signup') {
        $subject = "Finish signing up for the $company_name client portal";
        $email = [
            'kind' => 'Client portal', 'tone' => 'primary', 'preview' => 'One more step to your client portal account',
            'title' => $subject,
            'intro' => ["Someone - probably you - asked to sign up for the $company_name client portal with this address, for $client_name.", 'Finish with the button below - you choose your password there.'],
            'summary' => ['Signing up as' => (string) $row['invite_email']],
            'button' => ['label' => 'Finish signing up', 'url' => $url],
            'after' => ['The link works once, for the next ' . PORTAL_SIGNUP_LINK_HOURS . ' hours. If you did not ask for it, ignore this email - nothing is set up unless the link is used.'],
        ];
        $footnote = 'You received this because someone asked to sign up for the client portal with this address.';
    } else {
        $by = portalInviteBy($row);
        $who = $by !== '' ? $by : $company_name;
        $subject = ($by !== '' ? "$by invited you" : 'You are invited') . " to the $company_name client portal";
        $email = [
            'kind' => 'Client portal', 'tone' => 'primary', 'preview' => "Join $client_name on the $company_name client portal",
            'title' => "Join $client_name on the $company_name client portal",
            'intro' => [($by !== '' ? "$by has" : 'We have') . " invited you to the $company_name client portal for $client_name - where you can raise and follow support tickets.", 'Accept with the button below - you put in your name and choose a password there. It takes a minute.'],
            'summary' => ['Invited' => (string) $row['invite_email']],
            'button' => ['label' => 'Accept the invite', 'url' => $url],
            'after' => ['The invite works for ' . PORTAL_INVITE_LINK_DAYS . ' days. If you were not expecting it, you can ignore this email.'],
        ];
        $footnote = "You received this because $who invited this address to the $company_name client portal.";
    }
    $sent = (bool) emailQueue([['email' => (string) $row['invite_email'], 'name' => $greet, 'greet' => $greet !== '' ? $greet : null, 'footnote' => $footnote]], $subject, $email);
    if ($sent && !emailCapturing()) {
        mysqli_query($mysqli, "UPDATE portal_invites SET invite_sent_at = NOW(), invite_sent_count = invite_sent_count + 1 WHERE invite_id = " . intval($row['invite_id']));
    }
    return $sent;
}

/**
 * Invite people to a client's portal - $people from portalInviteParse(), each
 * given the roles in $roles (billing, technical). $by: user_id (an agent) or
 * contact_id (a primary contact in the portal). An address already invited is
 * sent its invite again, with a new link; one that signs in already, here or
 * to another account, is left out with the reason. ['invited' => [emails],
 * 'resent' => [emails], 'skipped' => [email => why]].
 */
function portalInviteSend($client_id, array $people, array $roles, array $by) {
    global $mysqli;
    $client_id = intval($client_id);
    $billing = !empty($roles['billing']) ? 1 : 0;
    $technical = !empty($roles['technical']) ? 1 : 0;
    $by_user = intval($by['user_id'] ?? 0);
    $by_contact = intval($by['contact_id'] ?? 0);
    $done = ['invited' => [], 'resent' => [], 'skipped' => []];

    foreach (array_slice($people, 0, PORTAL_INVITE_MAX) as $person) {
        $email = trim((string) $person['email']);
        $name = trim((string) ($person['name'] ?? ''));
        $who = portalInviteWho($email, $client_id);
        if ($who['state'] === 'member') {
            $done['skipped'][$email] = intval($who['contact_id']) === $by_contact ? "that's you" : 'already signs in';
            continue;
        }
        if ($who['state'] === 'elsewhere') {
            $done['skipped'][$email] = $who['type'] === 1 ? 'an agent signs in with it' : 'it signs in to another client portal account';
            continue;
        }
        // In the portal, as with giving access there, another primary contact is ours to set up
        if ($by_contact && $who['state'] === 'contact' && $who['primary']) {
            $done['skipped'][$email] = 'a primary contact - ask us';
            continue;
        }
        if ($name === '' && $who['state'] === 'contact') {
            $name = (string) $who['name'];
        }
        $email_sql = escapeSql($email);
        $name_sql = $name !== '' ? "'" . escapeSql($name) . "'" : 'NULL';
        $open = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT invite_id FROM portal_invites
            WHERE invite_client_id = $client_id AND invite_kind = 'invite' AND invite_email = '$email_sql' AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL
            ORDER BY invite_id DESC LIMIT 1"))[0] ?? 0);
        if ($open) {
            mysqli_query($mysqli, "UPDATE portal_invites SET invite_name = COALESCE($name_sql, invite_name), invite_billing = $billing, invite_technical = $technical,
                invite_created_by = $by_user, invite_created_by_contact_id = $by_contact WHERE invite_id = $open");
            $invite_id = $open;
        } else {
            mysqli_query($mysqli, "INSERT INTO portal_invites SET invite_kind = 'invite', invite_email = '$email_sql', invite_name = $name_sql, invite_billing = $billing,
                invite_technical = $technical, invite_expires_at = NOW(), invite_created_by = $by_user, invite_created_by_contact_id = $by_contact, invite_client_id = $client_id");
            $invite_id = intval(mysqli_insert_id($mysqli));
        }
        if (!portalInviteMail($invite_id, portalInviteNewToken($invite_id))) {
            if (!$open) {
                mysqli_query($mysqli, "DELETE FROM portal_invites WHERE invite_id = $invite_id");
            }
            $done['skipped'][$email] = 'the email could not be sent';
            continue;
        }
        $done[$open ? 'resent' : 'invited'][] = $email;
    }
    return $done;
}

/** Send an invite that is waiting again, with a new link (the old one stops working). False when it isn't waiting or can't go. */
function portalInviteResend($invite_id, $client_id) {
    $row = portalInviteRow($invite_id);
    if (!$row || intval($row['invite_client_id']) !== intval($client_id) || $row['invite_kind'] !== 'invite'
        || !in_array(portalInviteState($row), ['pending', 'expired'], true)) {
        return false;
    }
    return portalInviteMail($row['invite_id'], portalInviteNewToken($row['invite_id']));
}

/** Cancel an invite that is waiting - its link stops working. False when it isn't waiting. */
function portalInviteCancel($invite_id, $client_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE portal_invites SET invite_cancelled_at = NOW()
        WHERE invite_id = " . intval($invite_id) . " AND invite_client_id = " . intval($client_id) . " AND invite_kind = 'invite'
          AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL");
    return mysqli_affected_rows($mysqli) === 1;
}

/** A client's invites waiting to be accepted - in date or run out - newest first, with who sent them. */
function portalInvitesWaiting($client_id) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT invite_id, invite_email, invite_name, invite_billing, invite_technical, invite_expires_at, invite_sent_at, invite_sent_count,
            invite_created_at, invite_created_by, invite_created_by_contact_id, invite_kind, invite_accepted_at, invite_cancelled_at,
            agent.user_name AS invite_by_agent, by_contact.contact_name AS invite_by_contact
        FROM portal_invites
        LEFT JOIN users agent ON agent.user_id = invite_created_by AND invite_created_by > 0
        LEFT JOIN contacts by_contact ON by_contact.contact_id = invite_created_by_contact_id AND invite_created_by_contact_id > 0
        WHERE invite_client_id = " . intval($client_id) . " AND invite_kind = 'invite' AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL
        ORDER BY invite_created_at DESC, invite_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** How an invite stands, as a badge: Waiting (and how long it has left) or Ran out. */
function portalInviteStateLabel(array $row) {
    if (portalInviteState($row) === 'expired') {
        return ['label' => 'Ran out', 'title' => 'The link ran out on ' . date('M j, Y', strtotime($row['invite_expires_at'])) . ' - send it again for a new one', 'tone' => 'warning'];
    }
    $left = strtotime((string) $row['invite_expires_at']) - time();
    $days = intval(floor($left / 86400));
    return ['label' => 'Waiting', 'title' => 'The link works for ' . ($days >= 1 ? $days . ' more day' . ($days === 1 ? '' : 's') : 'less than a day'), 'tone' => 'info'];
}

/** How a contact joined the portal, when it was by an invite or signing up - ['kind', 'by', 'at'] or null. */
function portalContactJoined($contact_id) {
    global $mysqli;
    if (!portalInvitesReady() || !intval($contact_id)) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invite_kind, invite_accepted_at, invite_created_by, invite_created_by_contact_id,
            agent.user_name AS invite_by_agent, by_contact.contact_name AS invite_by_contact
        FROM portal_invites
        LEFT JOIN users agent ON agent.user_id = invite_created_by AND invite_created_by > 0
        LEFT JOIN contacts by_contact ON by_contact.contact_id = invite_created_by_contact_id AND invite_created_by_contact_id > 0
        WHERE invite_contact_id = " . intval($contact_id) . " AND invite_accepted_at IS NOT NULL
        ORDER BY invite_accepted_at DESC LIMIT 1"));
    if (!$row) {
        return null;
    }
    return ['kind' => $row['invite_kind'], 'by' => portalInviteBy($row), 'at' => $row['invite_accepted_at']];
}

/*
 * ---------- accepting ----------
 */

/**
 * Accept an invite or finish a sign-up - $invite from portalInviteByLink()
 * (still pending), $in what they put in: name, title, phone, method (local or
 * azure), password and confirm. Makes the contact (or uses the client's
 * contact with that address) and their portal account, spends the link and
 * any other waiting for the address, and tells whoever should know. The
 * contact's id, or 0 with $error (plain text).
 */
function portalInviteAccept(array $invite, array $in, &$error = '') {
    global $mysqli;
    $error = '';
    $invite_id = intval($invite['invite_id']);
    $client_id = intval($invite['invite_client_id']);
    $email = trim((string) $invite['invite_email']);
    $signup = $invite['invite_kind'] === 'signup';

    $name = trim(preg_replace('/\s+/', ' ', mb_substr((string) ($in['name'] ?? ''), 0, 200)));
    $title = trim(mb_substr((string) ($in['title'] ?? ''), 0, 200));
    $phone = substr(preg_replace('/[^0-9]/', '', (string) ($in['phone'] ?? '')), 0, 200);
    $method = (string) ($in['method'] ?? 'local');
    if (!isset(portalSignInMethods()[$method])) {
        $method = 'local';
    }
    $password = (string) ($in['password'] ?? '');

    if ($name === '') {
        $error = 'Put in your name.';
        return 0;
    }
    if ($method === 'local') {
        if (($problem = userPasswordProblem($password)) !== '') {
            $error = $problem;
            return 0;
        }
        if ($password !== (string) ($in['confirm'] ?? '')) {
            $error = 'The two passwords are not the same - type it again in both.';
            return 0;
        }
    }

    $who = portalInviteWho($email, $client_id);
    if ($who['state'] === 'member' || ($who['state'] === 'elsewhere' && $who['type'] === 2 && $who['on'])) {
        $error = 'This address already has a client portal account - sign in with it, or choose Forgot password on the sign-in page.';
        return 0;
    }
    if ($who['state'] === 'elsewhere') {
        $error = 'This address signs in to another account here, so it can\'t be used for the client portal too - ask us about it.';
        return 0;
    }
    if ($signup && ($who['state'] === 'contact' && $who['access'] === 'off')) {
        $error = 'This address can\'t sign up - ask your company\'s primary contact or us to give you access.';
        return 0;
    }
    if ($signup && portalSignupClientFor($email) !== $client_id) {
        $error = 'Signing up has been turned off for ' . $invite['client_name'] . ' - ask your company\'s primary contact or us to invite you.';
        return 0;
    }

    // Spend the link - one press, or one of two at once, gets through
    mysqli_query($mysqli, "UPDATE portal_invites SET invite_accepted_at = NOW()
        WHERE invite_id = $invite_id AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL AND invite_expires_at > NOW()
          AND invite_token_hash = '" . escapeSql((string) $invite['invite_token_hash']) . "'");
    if (mysqli_affected_rows($mysqli) !== 1) {
        $error = 'That link has been used already.';
        return 0;
    }

    $name_sql = escapeSql($name);
    $title_sql = escapeSql($title);
    $phone_sql = escapeSql($phone);
    $email_sql = escapeSql($email);
    $billing = $signup ? 0 : intval($invite['invite_billing']);
    $technical = $signup ? 0 : intval($invite['invite_technical']);

    // The contact - the client's own with this address, given what was left empty, or a new one
    $new_contact = $who['state'] !== 'contact';
    if ($new_contact) {
        mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$name_sql', contact_title = '$title_sql', contact_email = '$email_sql', contact_phone = '$phone_sql',
            contact_billing = $billing, contact_technical = $technical, contact_client_id = $client_id, contact_user_id = 0");
        $contact_id = intval(mysqli_insert_id($mysqli));
    } else {
        $contact_id = intval($who['contact_id']);
        mysqli_query($mysqli, "UPDATE contacts SET contact_name = '$name_sql',
            contact_title = IF(COALESCE(contact_title, '') = '', '$title_sql', contact_title),
            contact_phone = IF(COALESCE(contact_phone, '') = '', '$phone_sql', contact_phone),
            contact_billing = GREATEST(COALESCE(contact_billing, 0), $billing), contact_technical = GREATEST(COALESCE(contact_technical, 0), $technical)
            WHERE contact_id = $contact_id AND contact_client_id = $client_id");
    }

    // Their portal account - a password, or their Microsoft 365 account
    $hash = escapeSql(password_hash($method === 'local' ? $password : randomString(32), PASSWORD_DEFAULT));
    $user_id = intval($who['user_id'] ?? 0);
    if ($user_id) {
        mysqli_query($mysqli, "UPDATE users SET user_name = '$name_sql', user_email = '$email_sql', user_password = '$hash', user_auth_method = '$method',
            user_status = 1, user_archived_at = NULL, user_password_reset_token = NULL, user_password_reset_token_expire = NULL WHERE user_id = $user_id AND user_type = 2");
    } else {
        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name_sql', user_email = '$email_sql', user_password = '$hash', user_auth_method = '$method', user_type = 2, user_status = 1");
        $user_id = intval(mysqli_insert_id($mysqli));
        mysqli_query($mysqli, "UPDATE contacts SET contact_user_id = $user_id WHERE contact_id = $contact_id");
    }

    // The link is theirs now; any other waiting for the address here is done with
    mysqli_query($mysqli, "UPDATE portal_invites SET invite_contact_id = $contact_id WHERE invite_id = $invite_id");
    mysqli_query($mysqli, "UPDATE portal_invites SET invite_cancelled_at = NOW()
        WHERE invite_client_id = $client_id AND invite_email = '$email_sql' AND invite_id <> $invite_id AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL");

    // Who should know
    $client_name = (string) $invite['client_name'];
    $by = portalInviteBy($invite);
    $agent_link = "/agent/contact.php?client_id=$client_id&contact_id=$contact_id";
    $how = $signup ? 'signed up in the client portal' : 'accepted ' . ($by !== '' ? "$by's" : 'an') . ' invite to the client portal';
    logAudit("Contact", $new_contact ? "Create" : "Edit", escapeSql("$name ($email) $how for $client_name" . ($new_contact ? '' : ' - an existing contact, now with portal access')), $client_id, $contact_id);
    if ($new_contact) {
        triggerCustomAction('contact_create', $contact_id);
    }
    if ($signup || intval($invite['invite_created_by_contact_id'])) {
        appNotify("Contact", escapeSql("$name $how for $client_name"), $agent_link, $client_id, $contact_id);
    } elseif (intval($invite['invite_created_by']) && intval($invite['invite_by_agent_status'] ?? 0) === 1 && empty($invite['invite_by_agent_archived_at'])) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Contact', notification = '" . escapeSql(mb_substr("$name accepted your invite to the client portal for $client_name", 0, 1000)) . "',
            notification_action = '" . escapeSql($agent_link) . "', notification_client_id = $client_id, notification_entity_id = $contact_id, notification_user_id = " . intval($invite['invite_created_by']));
    }
    if ($signup) {
        portalMailSignupJoined($contact_id);
    } elseif (intval($invite['invite_created_by_contact_id'])) {
        portalMailInviteAccepted($invite_id);
    }
    return $contact_id;
}

/** Tell a client's primary contacts who can sign in that someone signed up. False when nobody could be told. */
function portalMailSignupJoined($contact_id) {
    global $mysqli, $config_base_url;
    $contact_id = intval($contact_id);
    $joined = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email, contact_client_id, client_name FROM contacts
        JOIN clients ON client_id = contact_client_id WHERE contact_id = $contact_id LIMIT 1"));
    if (!$joined || !portalMailReady()) {
        return false;
    }
    $client_id = intval($joined['contact_client_id']);
    $company_name = (string) (billingCompany()['company_name'] ?? '');
    $to = [];
    $sql = mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts JOIN users ON user_id = contact_user_id
        WHERE contact_client_id = $client_id AND contact_primary = 1 AND contact_archived_at IS NULL AND contact_id <> $contact_id
          AND user_type = 2 AND user_status = 1 AND user_archived_at IS NULL AND user_auth_method IN ('local', 'azure')");
    while ($row = mysqli_fetch_assoc($sql)) {
        $to[] = ['email' => $row['contact_email'], 'name' => $row['contact_name'],
            'footnote' => 'You received this because you are the primary contact for ' . $joined['client_name'] . ' in the client portal.'];
    }
    if (!$to) {
        return false;
    }
    $name = (string) $joined['contact_name'];
    return (bool) emailQueue($to, "$name joined the $company_name client portal", [
        'kind' => 'Client portal', 'tone' => 'primary', 'preview' => "$name signed up with their work email",
        'title' => "$name joined the client portal",
        'intro' => ["$name signed up for the $company_name client portal for " . $joined['client_name'] . ' with their work email address. They see the tickets they raise.',
            'If they should not have access, turn it off from their page under Contacts.'],
        'summary' => ['Who' => $name, 'Email' => (string) $joined['contact_email']],
        'button' => ['label' => 'See them', 'url' => "https://$config_base_url/client/contact.php?id=$contact_id"],
    ]);
}

/** Tell the primary contact who sent an invite that it was accepted. False when they can't be told. */
function portalMailInviteAccepted($invite_id) {
    global $mysqli, $config_base_url;
    $row = portalInviteRow($invite_id);
    if (!$row || !intval($row['invite_contact_id']) || !intval($row['invite_created_by_contact_id']) || !portalMailReady()) {
        return false;
    }
    $by = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts JOIN users ON user_id = contact_user_id
        WHERE contact_id = " . intval($row['invite_created_by_contact_id']) . " AND contact_client_id = " . intval($row['invite_client_id']) . " AND contact_archived_at IS NULL
          AND user_type = 2 AND user_status = 1 AND user_archived_at IS NULL AND user_auth_method IN ('local', 'azure') LIMIT 1"));
    if (!$by) {
        return false;
    }
    $joined = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = " . intval($row['invite_contact_id'])));
    $name = (string) ($joined['contact_name'] ?? $row['invite_email']);
    $company_name = (string) (billingCompany()['company_name'] ?? '');
    return (bool) emailQueue([['email' => $by['contact_email'], 'name' => $by['contact_name'],
        'footnote' => 'You received this because you invited ' . $row['invite_email'] . ' to the client portal.']], "$name accepted your invite", [
        'kind' => 'Client portal', 'tone' => 'success', 'preview' => "$name can sign in to the client portal now",
        'title' => "$name accepted your invite",
        'intro' => ["$name has joined the $company_name client portal for " . $row['client_name'] . ' and can sign in now.'],
        'summary' => ['Who' => $name, 'Email' => (string) ($joined['contact_email'] ?? $row['invite_email'])],
        'button' => ['label' => 'See them', 'url' => "https://$config_base_url/client/contact.php?id=" . intval($row['invite_contact_id'])],
    ]);
}

/*
 * ---------- signing up ----------
 */

/** Is signing up on for the portal as a whole (Admin > Settings > Client portal)? */
function portalSignupOn() {
    global $mysqli;
    static $on = null;
    if ($on === null) {
        $on = false;
        if (portalInvitesReady()) {
            $on = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_client_portal_signup FROM settings WHERE company_id = 1"))[0] ?? 0) === 1;
        }
    }
    return $on;
}

/** A client's Domains, lowercased - the addresses people there sign up with. */
function portalSignupDomains($client_id) {
    global $mysqli;
    $domains = [];
    $sql = mysqli_query($mysqli, "SELECT domain_name FROM domains WHERE domain_client_id = " . intval($client_id) . " AND domain_archived_at IS NULL ORDER BY domain_name ASC");
    while ($row = mysqli_fetch_row($sql)) {
        $domain = strtolower(trim((string) $row[0]));
        if ($domain !== '' && !in_array($domain, $domains, true)) {
            $domains[] = $domain;
        }
    }
    return $domains;
}

/** The clients that let people sign up, not archived - client_id => [name, domains]. Before 3.4.6, none. */
function portalSignupClients() {
    global $mysqli;
    $clients = [];
    if (!portalInvitesReady()) {
        return $clients;
    }
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_portal_signup = 1 AND client_archived_at IS NULL ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])] = ['name' => (string) $row['client_name'], 'domains' => portalSignupDomains($row['client_id'])];
    }
    return $clients;
}

/** Can anyone sign up now - the portal and signing up on, mail working, and a client that lets people with a domain to do it? */
function portalSignupOpen() {
    global $config_client_portal_enable;
    if (intval($config_client_portal_enable) !== 1 || !portalSignupOn() || !portalMailReady()) {
        return false;
    }
    foreach (portalSignupClients() as $client) {
        if ($client['domains']) {
            return true;
        }
    }
    return false;
}

/**
 * The client an address signs up to - the one client that lets people sign up
 * and has its domain among its Domains - or 0: none does, or more than one
 * (then nobody can, until it is put right). $why says which.
 */
function portalSignupClientFor($email, &$why = '') {
    global $mysqli, $config_client_portal_enable;
    $why = '';
    $email = trim((string) $email);
    if (intval($config_client_portal_enable) !== 1 || !portalSignupOn()) {
        $why = 'signing up is off';
        return 0;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $why = 'not an email address';
        return 0;
    }
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    $domain_sql = escapeSql($domain);
    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT DISTINCT client_id FROM domains JOIN clients ON client_id = domain_client_id
        WHERE LOWER(domain_name) = '$domain_sql' AND domain_archived_at IS NULL AND client_archived_at IS NULL AND client_portal_signup = 1");
    while ($row = mysqli_fetch_row($sql)) {
        $clients[] = intval($row[0]);
    }
    if (count($clients) > 1) {
        $why = "more than one client that lets people sign up has $domain among its domains";
        return 0;
    }
    if (!$clients) {
        $why = "no client that lets people sign up has $domain among its domains";
        return 0;
    }
    return $clients[0];
}

/**
 * Someone asks to sign up with $email (on client/signup.php). Whatever the
 * address, the page says the same; what happens is: a link to finish, for an
 * address at a client that lets people sign up; a note that they have an
 * account already, with a link to set a new password, for one that signs in
 * here; nothing otherwise. At most one email per address every
 * PORTAL_RESET_REQUEST_GAP_MINUTES. Each ask is logged (Client Login). What
 * happened, for the tests and the log: sent, exists, refused or quiet.
 */
function portalSignupRequest($email, $ip, $user_agent) {
    global $mysqli;
    $email = trim(mb_substr((string) $email, 0, 200));
    $email_sql = escapeSql($email);
    $ip_sql = escapeSql((string) $ip);
    $user_agent_sql = escapeSql(mb_substr((string) $user_agent, 0, 255));
    $log = function ($action, $description, $user_id = 0, $client_id = 0) use ($mysqli, $ip_sql, $user_agent_sql) {
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = '" . escapeSql($action) . "', log_description = '" . escapeSql(mb_substr($description, 0, 1000)) . "',
            log_ip = '$ip_sql', log_user_agent = '$user_agent_sql', log_client_id = " . intval($client_id) . ", log_user_id = " . intval($user_id));
        logForwardQueue('audit', mysqli_insert_id($mysqli));
    };

    // From one address - an office behind one, say - PORTAL_SIGNUP_IP_LIMIT asks in ten minutes; after that, nothing is sent
    $asked = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE log_type = 'Client Login' AND log_action = 'Sign Up Requested'
        AND log_ip = '$ip_sql' AND log_created_at > NOW() - INTERVAL 10 MINUTE"))[0]);
    $client_id = portalSignupClientFor($email, $why);
    $log('Sign Up Requested', 'Asked to sign up to the client portal as ' . $email, 0, $client_id);
    if ($asked >= PORTAL_SIGNUP_IP_LIMIT) {
        $log('Sign Up Refused', "Did not answer a sign-up for $email - too many from this address", 0, $client_id);
        return 'refused';
    }

    // Already signs in - with a password, a link to set a new one; with Microsoft, how to sign in
    $who = $client_id ? portalInviteWho($email, $client_id) : null;
    $account = null;
    if ($who && $who['state'] === 'member') {
        $account = ['user_id' => $who['user_id'], 'method' => $who['access']];
    } elseif (!$client_id && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Signing up isn't open to the address, but it may sign in already - say so, as Forgot password would
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_auth_method, contact_client_id FROM users
            JOIN contacts ON contact_user_id = user_id JOIN clients ON client_id = contact_client_id
            WHERE user_email = '$email_sql' AND user_type = 2 AND user_status = 1 AND user_archived_at IS NULL AND user_auth_method IN ('local', 'azure')
              AND contact_archived_at IS NULL AND client_archived_at IS NULL LIMIT 1"));
        if ($row && portalSignupOn()) {
            $account = ['user_id' => intval($row['user_id']), 'method' => (string) $row['user_auth_method']];
            $client_id = intval($row['contact_client_id']);
        }
    } elseif ($who && $who['state'] === 'elsewhere' && $who['type'] === 2 && $who['on']) {
        $account = ['user_id' => $who['user_id'], 'method' => $who['access']];
    }
    if ($account) {
        $recent = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE log_type = 'Client Login' AND log_action IN ('Sign Up Sent', 'Reset Sent')
            AND log_user_id = " . intval($account['user_id']) . " AND log_created_at > NOW() - INTERVAL " . PORTAL_RESET_REQUEST_GAP_MINUTES . " MINUTE"))[0]);
        if ($recent) {
            return 'quiet';
        }
        portalMailSignupExists($account['user_id']);
        $log('Sign Up Sent', "Told $email they have a client portal account already", $account['user_id'], $client_id);
        return 'exists';
    }

    if (!$client_id) {
        $log('Sign Up Refused', "Did not sign up $email - $why");
        return 'refused';
    }
    if ($who['state'] === 'elsewhere') {
        $log('Sign Up Refused', "Did not sign up $email - " . ($who['type'] === 1 ? 'an agent signs in with it' : 'its portal account is turned off'), 0, $client_id);
        return 'refused';
    }
    if ($who['state'] === 'contact' && $who['access'] === 'off') {
        $log('Sign Up Refused', "Did not sign up $email - their portal access was turned off", 0, $client_id);
        return 'refused';
    }

    // One link at a time for an address, and not another within a few minutes
    $open = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invite_id, invite_sent_at > NOW() - INTERVAL " . PORTAL_RESET_REQUEST_GAP_MINUTES . " MINUTE AS just_sent FROM portal_invites
        WHERE invite_client_id = $client_id AND invite_kind = 'signup' AND invite_email = '$email_sql' AND invite_accepted_at IS NULL AND invite_cancelled_at IS NULL
        ORDER BY invite_id DESC LIMIT 1"));
    if ($open && intval($open['just_sent'])) {
        return 'quiet';
    }
    if ($open) {
        $invite_id = intval($open['invite_id']);
    } else {
        mysqli_query($mysqli, "INSERT INTO portal_invites SET invite_kind = 'signup', invite_email = '$email_sql', invite_expires_at = NOW(), invite_client_id = $client_id");
        $invite_id = intval(mysqli_insert_id($mysqli));
    }
    if (!portalInviteMail($invite_id, portalInviteNewToken($invite_id))) {
        $log('Sign Up Refused', "Could not email $email a link to finish signing up", 0, $client_id);
        return 'refused';
    }
    $log('Sign Up Sent', "Emailed $email a link to finish signing up", 0, $client_id);
    return 'sent';
}

/** Tell someone who tried to sign up that they have an account - with a link to set a new password, or for Microsoft how to sign in. */
function portalMailSignupExists($user_id) {
    global $mysqli, $config_base_url;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_email, user_auth_method, contact_name FROM users JOIN contacts ON contact_user_id = user_id
        WHERE user_id = " . intval($user_id) . " AND user_type = 2 LIMIT 1"));
    if (!$row || !portalMailReady()) {
        return false;
    }
    $company_name = (string) (billingCompany()['company_name'] ?? '');
    $local = $row['user_auth_method'] === 'local';
    return (bool) emailQueue([['email' => $row['user_email'], 'name' => $row['contact_name'],
        'footnote' => 'You received this because someone tried to sign up for the client portal with this address.']], "You already have a $company_name client portal account", [
        'kind' => 'Client portal', 'tone' => 'primary', 'preview' => 'Sign in with the account you have',
        'title' => "You already have a $company_name client portal account",
        'intro' => ["Someone - probably you - tried to sign up for the $company_name client portal with this address, but it has an account already.",
            $local ? 'Forgot your password? Choose a new one with the button below.' : 'Sign in with your Microsoft 365 account - choose Login with Microsoft on the sign-in page.'],
        'summary' => ['Account' => (string) $row['user_email']],
        'button' => $local ? ['label' => 'Set a new password', 'url' => portalPasswordLink($user_id, PORTAL_RESET_LINK_MINUTES)] : ['label' => 'Sign in', 'url' => "https://$config_base_url/login.php"],
        'after' => [$local ? 'The link works once, for the next ' . PORTAL_RESET_LINK_MINUTES . ' minutes. If you did not ask, ignore this email - your account stays as it is.' : 'If you did not ask, ignore this email - your account stays as it is.'],
    ]);
}

/** Set whether people at a client can sign up. True when it changed. */
function portalSignupSet($client_id, $on) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE clients SET client_portal_signup = " . ($on ? 1 : 0) . " WHERE client_id = " . intval($client_id));
    return mysqli_affected_rows($mysqli) === 1;
}

/*
 * ---------- nightly ----------
 */

/** Clear out the links nobody used - sign-ups a week after they ran out, cancelled invites after 90 days. How many went. */
function portalInvitesNightly() {
    global $mysqli;
    if (!portalInvitesReady()) {
        return 0;
    }
    mysqli_query($mysqli, "DELETE FROM portal_invites WHERE invite_accepted_at IS NULL AND (
        (invite_kind = 'signup' AND invite_expires_at < NOW() - INTERVAL 7 DAY)
        OR (invite_cancelled_at IS NOT NULL AND invite_cancelled_at < NOW() - INTERVAL 90 DAY))");
    return max(0, intval(mysqli_affected_rows($mysqli)));
}
