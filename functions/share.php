<?php

/*
 * Shared links - a link for someone outside to open a login, a secure note,
 * a document or a file, or to send a login back to us (a request).
 *
 * A link carries one secret, after the # (guest/share.php?id=12#secret), so
 * it never reaches the server's access log, a proxy or a Referer header -
 * js/guest_share.js reads it and posts it to guest/share_ajax.php. The table
 * keeps only a hash of it (item_key) to find the link. Anything secret in the
 * link - the login, the note, what a client sends back - is encrypted with
 * AES-256-GCM under a key made from that secret (shareContentKey()), which is
 * never stored: the database alone opens nothing.
 *
 * Opening a link takes a click (Reveal), so the link checkers in mail
 * filters and chat apps that fetch every URL cannot use up a one-time link.
 * The browser that opened it may open it again, and keeps getting 2FA codes,
 * for SHARE_GRACE_MINUTES; after that the secret is wiped (shareSweep()).
 * A passphrase, or a code emailed to the recipient, can be asked for first;
 * SHARE_ATTEMPTS wrong answers lock the link.
 *
 * A link made before 2.9.0 (item_version 1) carried key= and ek= in the query
 * string, with the login under aes-128-cbc - those keep working until they
 * expire (at most a month after the update).
 *
 * Every link stays listed after it ends - used up, expired, turned off,
 * locked - for SHARE_KEEP_DAYS, with who opened it and when from the audit
 * log (log_type 'Share', log_entity_id the link).
 */

/** The browser that opened a link may open it again, and keeps getting 2FA codes, for this long. */
const SHARE_GRACE_MINUTES = 30;

/** Wrong passphrases or codes before a link locks. */
const SHARE_ATTEMPTS = 5;

/** An emailed code lasts this long, and another can be sent after SHARE_CODE_RESEND_SECONDS. */
const SHARE_CODE_MINUTES = 10;
const SHARE_CODE_RESEND_SECONDS = 60;

/** Ended links stay listed this long; a login a client sent stays this long unsaved. */
const SHARE_KEEP_DAYS = 90;
const SHARE_RECEIVED_KEEP_DAYS = 30;

/** The longest a secure note may be, and a passphrase the shortest. */
const SHARE_NOTE_MAX = 10000;
const SHARE_PASSPHRASE_MIN = 6;

/** What can be shared: what it is called, its icon, and whether it holds a secret. */
function shareTypes() {
    return [
        'Credential' => ['label' => 'Login', 'icon' => 'fa-key', 'secret' => true],
        'Note' => ['label' => 'Secure note', 'icon' => 'fa-user-secret', 'secret' => true],
        'Document' => ['label' => 'Document', 'icon' => 'fa-file-alt', 'secret' => false],
        'File' => ['label' => 'File', 'icon' => 'fa-paperclip', 'secret' => false],
        'Request' => ['label' => 'Login request', 'icon' => 'fa-inbox', 'secret' => false],
    ];
}

/** How long a link may last, in hours => label, up to the admin's limit (Settings > Security). */
function shareExpiryOptions() {
    global $config_share_max_hours;
    $max = intval($config_share_max_hours ?? 720) ?: 720;
    $options = [1 => '1 hour', 4 => '4 hours', 24 => '1 day', 72 => '3 days', 168 => '1 week', 336 => '2 weeks', 720 => '30 days', 2160 => '90 days'];
    return array_filter($options, function ($hours) use ($max) {
        return $hours <= $max;
    }, ARRAY_FILTER_USE_KEY);
}

/** What the admin may set as the longest a link can last, in hours => label. */
function shareMaxOptions() {
    return [24 => '1 day', 72 => '3 days', 168 => '1 week', 336 => '2 weeks', 720 => '30 days', 2160 => '90 days'];
}

/** How many times a link may be opened => label; 0 is until it expires. */
function shareViewOptions() {
    return [1 => 'Once', 2 => 'Twice', 3 => '3 times', 5 => '5 times', 10 => '10 times', 0 => 'Until it expires'];
}

/** The window's starting choices for a type - a short, single-use link for anything secret. */
function shareDefaults($type) {
    $expiry = ['Credential' => 24, 'Note' => 24, 'Request' => 72, 'Document' => 168, 'File' => 168][$type] ?? 24;
    // The longest choice the admin's limit leaves that is no longer than that
    $hours = array_keys(shareExpiryOptions());
    $fit = array_filter($hours, function ($option) use ($expiry) {
        return $option <= $expiry;
    });
    $expiry = $fit ? max($fit) : min($hours);
    return [
        'expires' => $expiry,
        'views' => in_array($type, ['Document', 'File']) ? 0 : 1,
    ];
}

/** Must a link of this type have a passphrase or an emailed code? (Settings > Security) */
function shareNeedsProtection($type) {
    global $config_share_require_protection;
    return !empty($config_share_require_protection) && !empty(shareTypes()[$type]['secret']);
}

/**
 * May this agent see links of this type (the list, a link's quick look)?
 * Logins and requests are for whoever may see credentials.
 */
function shareCanSee($type) {
    if (lookupUserPermission('module_support') < 1) {
        return false;
    }
    if (in_array($type, ['Credential', 'Request'])) {
        return lookupUserPermission('module_credential') >= 1;
    }
    return true;
}

/**
 * May this agent make, or turn off, a link of this type? Sharing a login
 * hands out what reading one does; asking for one makes a credential.
 */
function shareCanMake($type) {
    if (!isset(shareTypes()[$type]) || lookupUserPermission('module_support') < 2) {
        return false;
    }
    if ($type === 'Credential') {
        return lookupUserPermission('module_credential') >= 1;
    }
    if ($type === 'Request') {
        return lookupUserPermission('module_credential') >= 2;
    }
    return true;
}

/** The types this agent may see, as an SQL list for item_type IN (...). */
function shareVisibleTypesSql() {
    $types = array_filter(array_keys(shareTypes()), 'shareCanSee');
    return $types ? "'" . implode("','", $types) . "'" : "''";
}

/** Is mail set up to send with? */
function shareMailReady() {
    global $config_smtp_provider, $config_mail_from_email;
    return !empty($config_smtp_provider) && filter_var($config_mail_from_email ?? '', FILTER_VALIDATE_EMAIL);
}

/** A new link secret - 256 bits, URL-safe. */
function shareSecret() {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

/** How a link's secret is kept - only its hash. */
function shareKeyHash($secret) {
    return hash('sha256', (string) $secret);
}

/** The key a link's secret content is encrypted with - made from the secret, never stored. */
function shareContentKey($secret) {
    return hash_hmac('sha256', 'itflow share content', (string) $secret, true);
}

/** Encrypt an array for a link: AES-256-GCM, base64 of iv . tag . ciphertext. */
function shareSeal(array $data, $key) {
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(json_encode($data), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ciphertext);
}

/** The array shareSeal() made, or null when the key is wrong or it has been tampered with. */
function shareUnseal($payload, $key) {
    $raw = base64_decode((string) $payload, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($plain === false) {
        return null;
    }
    $data = json_decode($plain, true);
    return is_array($data) ? $data : null;
}

/** The link to send. */
function shareUrl($item_id, $secret) {
    global $config_base_url;
    return "https://$config_base_url/guest/share.php?id=" . intval($item_id) . "#$secret";
}

/** A QR code of the link, as inline SVG - '' without GD, which libs/barcode needs. */
function shareQrSvg($url) {
    if (!extension_loaded('gd')) {
        return '';
    }
    require_once dirname(__DIR__) . '/libs/barcode/barcode.php';
    $generator = new barcode_generator();
    $svg = (string) $generator->render_svg('qr', $url, ['w' => 180, 'h' => 180, 'p' => 0]);
    $svg = preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    return preg_replace('/<svg /', '<svg class="itflow-share-qr" role="img" aria-label="QR code of the link" ', $svg, 1);
}

/** One link by id, with who made it and the client - raw, or null. */
function shareRow($item_id) {
    global $mysqli;
    $item_id = intval($item_id);
    if (!$item_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT shared_items.*, client_name, creator.user_name AS creator_name, ender.user_name AS ender_name
        FROM shared_items
        LEFT JOIN clients ON client_id = item_client_id
        LEFT JOIN users creator ON creator.user_id = item_created_by
        LEFT JOIN users ender ON ender.user_id = item_ended_by
        WHERE item_id = $item_id LIMIT 1"));
    return $row ?: null;
}

/** The link this id and secret open, or null - for the guest side. */
function shareFind($item_id, $secret) {
    global $mysqli;
    $item_id = intval($item_id);
    $secret = (string) $secret;
    if (!$item_id || $secret === '' || strlen($secret) > 200) {
        return null;
    }
    $hash = shareKeyHash($secret);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT shared_items.*, client_name, creator.user_name AS creator_name
        FROM shared_items
        LEFT JOIN clients ON client_id = item_client_id
        LEFT JOIN users creator ON creator.user_id = item_created_by
        WHERE item_id = $item_id AND item_key = '$hash' LIMIT 1"));
    return $row ?: null;
}

/**
 * Where a link is: active, waiting (a request not answered yet), received
 * (answered, not dealt with), used (no views left), expired, or ended - off,
 * locked, destroyed (by the person it was for), saved or discarded (a request).
 */
function shareState($row) {
    $ended = [
        'Revoked' => 'revoked', 'Locked' => 'locked', 'Destroyed' => 'destroyed',
        'Saved' => 'saved', 'Discarded' => 'discarded',
    ];
    if (!empty($row['item_end_reason'])) {
        return $ended[$row['item_end_reason']] ?? 'revoked';
    }
    if (empty($row['item_active'])) {
        return 'revoked';
    }
    if ($row['item_type'] === 'Request' && !empty($row['item_received_at'])) {
        return 'received';
    }
    if (strtotime((string) $row['item_expire_at']) <= time()) {
        return 'expired';
    }
    $limit = intval($row['item_view_limit']);
    if ($limit > 0 && intval($row['item_views']) >= $limit) {
        return 'used';
    }
    return $row['item_type'] === 'Request' ? 'waiting' : 'active';
}

/** Each state's label and badge. */
function shareStates() {
    return [
        'active' => ['Active', 'text-bg-success'],
        'waiting' => ['Waiting', 'text-bg-info'],
        'received' => ['Received', 'text-bg-warning'],
        'used' => ['Used', 'text-bg-secondary'],
        'expired' => ['Expired', 'text-bg-secondary'],
        'revoked' => ['Turned off', 'text-bg-secondary'],
        'locked' => ['Locked', 'text-bg-danger'],
        'destroyed' => ['Destroyed', 'text-bg-secondary'],
        'saved' => ['Saved', 'text-bg-success'],
        'discarded' => ['Discarded', 'text-bg-secondary'],
    ];
}

/** A link's state as a badge. */
function shareStateBadge($row) {
    $state = shareState($row);
    [$label, $class] = shareStates()[$state];
    return '<span class="badge rounded-pill ' . $class . ' fw-normal">' . $label . '</span>';
}

/** SQL for a link that can still be opened (or, for a request, answered). Columns unqualified. */
function shareOpenSql() {
    return "item_active = 1 AND item_ended_at IS NULL AND item_received_at IS NULL AND item_expire_at > NOW() AND (item_view_limit = 0 OR item_views < item_view_limit)";
}

/** SQL for a link still to do something about: open, or a request answered and not dealt with. */
function shareLiveSql() {
    return "item_ended_at IS NULL AND ((" . shareOpenSql() . ") OR (item_type = 'Request' AND item_received_at IS NOT NULL))";
}

/** "2 of 5 views", "Not opened", "Opened 3 times". */
function shareViewsText($row) {
    $views = intval($row['item_views']);
    $limit = intval($row['item_view_limit']);
    if ($row['item_type'] === 'Request') {
        return !empty($row['item_received_at']) ? 'Answered' : 'Not answered';
    }
    if ($limit > 0) {
        return "$views of $limit " . ($limit === 1 ? 'view' : 'views');
    }
    return $views ? "Opened $views " . ($views === 1 ? 'time' : 'times') : 'Not opened';
}

/** What the link asks for before it opens: '', 'passphrase' or 'code'. */
function shareProtection($row) {
    if (!empty($row['item_passphrase'])) {
        return 'passphrase';
    }
    return !empty($row['item_verify_email']) ? 'code' : '';
}

/** The name to show for a link - its own, or its type's. */
function shareName($row) {
    $name = trim((string) ($row['item_name'] ?? ''));
    return $name !== '' ? $name : (shareTypes()[$row['item_type']]['label'] ?? 'Shared item');
}

/** The name of the record a link shares, as it is now - null when it is gone. */
function shareItemLive($row) {
    global $mysqli;
    $id = intval($row['item_related_id']);
    $client_id = intval($row['item_client_id']);
    $table = [
        'Credential' => ['credentials', 'credential_id', 'credential_name', 'credential_client_id'],
        'Document' => ['documents', 'document_id', 'document_name', 'document_client_id'],
        'File' => ['files', 'file_id', 'file_name', 'file_client_id'],
    ][$row['item_type']] ?? null;
    if (!$table || !$id) {
        return null;
    }
    [$name_table, $id_column, $name_column, $client_column] = $table;
    $found = mysqli_fetch_row(mysqli_query($mysqli, "SELECT $name_column FROM $name_table WHERE $id_column = $id AND $client_column = $client_id LIMIT 1"));
    return $found ? (string) $found[0] : null;
}

/**
 * Make a link. $in: client_id, type, related_id (Credential, Document,
 * File), name (Note, Request), text (Note), recipient, send (email it),
 * expires (hours), views, protect ('', 'passphrase', 'code'), passphrase,
 * otp (share the login's 2FA codes), notify, note. The caller has checked
 * the agent may (shareCanMake) and the client (enforceClientAccess).
 *
 * Returns [item_id, url, emailed, expire_at, passphrase, protect, recipient,
 * views] - the url and passphrase only this once - or null with $error set.
 */
function shareCreate(array $in, &$error = '') {
    global $mysqli, $session_user_id, $session_name;

    $error = '';
    $type = (string) ($in['type'] ?? '');
    $client_id = intval($in['client_id'] ?? 0);
    $related_id = intval($in['related_id'] ?? 0);

    if (!isset(shareTypes()[$type])) {
        $error = 'That cannot be shared.';
        return null;
    }
    if (!$client_id || !getFieldById('clients', $client_id, 'client_name')) {
        $error = 'That client no longer exists.';
        return null;
    }

    $expires = intval($in['expires'] ?? 0);
    if (!isset(shareExpiryOptions()[$expires])) {
        $error = 'Pick how long the link lasts.';
        return null;
    }
    $views = $type === 'Request' ? 1 : intval($in['views'] ?? 1);
    if (!isset(shareViewOptions()[$views])) {
        $error = 'Pick how many times the link can be opened.';
        return null;
    }

    $recipient = trim((string) ($in['recipient'] ?? ''));
    if (mb_strlen($recipient) > 250) {
        $error = 'That recipient is too long.';
        return null;
    }
    $recipient_email = filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : '';
    $send = !empty($in['send']);
    if ($send && !$recipient_email) {
        $error = 'To email the link, pick a contact or type an email address.';
        return null;
    }
    if ($send && !shareMailReady()) {
        $error = 'Mail is not set up, so the link cannot be emailed - copy it instead.';
        return null;
    }

    $protect = (string) ($in['protect'] ?? '');
    if (!in_array($protect, ['', 'passphrase', 'code'], true)) {
        $protect = '';
    }
    if ($protect === '' && shareNeedsProtection($type)) {
        $error = 'Your sharing policy needs a passphrase or an emailed code on passwords and secure notes.';
        return null;
    }
    $passphrase = trim((string) ($in['passphrase'] ?? ''));
    if ($protect === 'passphrase' && mb_strlen($passphrase) < SHARE_PASSPHRASE_MIN) {
        $error = 'The passphrase needs at least ' . SHARE_PASSPHRASE_MIN . ' characters.';
        return null;
    }
    if ($protect === 'passphrase' && mb_strlen($passphrase) > 200) {
        $error = 'That passphrase is too long.';
        return null;
    }
    if ($protect === 'code' && !$recipient_email) {
        $error = 'An emailed code needs the recipient\'s email address.';
        return null;
    }
    if ($protect === 'code' && !shareMailReady()) {
        $error = 'Mail is not set up, so a code cannot be emailed - use a passphrase instead.';
        return null;
    }

    $note = trim((string) ($in['note'] ?? ''));
    if (mb_strlen($note) > 1000) {
        $error = 'The note for them is too long - keep it under 1000 characters.';
        return null;
    }

    $secret = shareSecret();
    $key = shareContentKey($secret);
    $payload = null;
    $vault_key = null;
    $name = '';

    if ($type === 'Credential') {
        $credential = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credential_name, credential_uri, credential_username, credential_password, credential_otp_secret
            FROM credentials WHERE credential_id = $related_id AND credential_client_id = $client_id LIMIT 1"));
        if (!$credential) {
            $error = 'That credential no longer exists.';
            return null;
        }
        $username = decryptCredentialEntry($credential['credential_username']);
        $password = decryptCredentialEntry($credential['credential_password']);
        if ($password === false || $username === false || encryptCredentialEntry('probe') === false) {
            $error = 'Your vault session has expired, so the login could not be read - sign out and back in, then try again.';
            return null;
        }
        $name = (string) $credential['credential_name'];
        $otp = !empty($in['otp']) ? (string) credentialOtpSeed($credential['credential_otp_secret']) : '';
        $payload = shareSeal([
            'name' => $name,
            'uri' => (string) $credential['credential_uri'],
            'username' => (string) $username,
            'password' => (string) $password,
            'otp' => $otp,
        ], $key);
    } elseif ($type === 'Document') {
        $name = mysqli_fetch_row(mysqli_query($mysqli, "SELECT document_name FROM documents WHERE document_id = $related_id AND document_client_id = $client_id LIMIT 1"))[0] ?? null;
        if ($name === null) {
            $error = 'That document no longer exists.';
            return null;
        }
    } elseif ($type === 'File') {
        $name = mysqli_fetch_row(mysqli_query($mysqli, "SELECT file_name FROM files WHERE file_id = $related_id AND file_client_id = $client_id LIMIT 1"))[0] ?? null;
        if ($name === null) {
            $error = 'That file no longer exists.';
            return null;
        }
    } elseif ($type === 'Note') {
        $related_id = 0;
        $text = rtrim((string) ($in['text'] ?? ''));
        if (trim($text) === '') {
            $error = 'Type what you want to send.';
            return null;
        }
        if (mb_strlen($text) > SHARE_NOTE_MAX) {
            $error = 'That is too long for a secure note - keep it under ' . number_format(SHARE_NOTE_MAX) . ' characters, or share a document.';
            return null;
        }
        $name = trim((string) ($in['name'] ?? '')) ?: 'Secure note';
        $payload = shareSeal(['text' => $text], $key);
    } elseif ($type === 'Request') {
        $related_id = 0;
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            $error = 'Say what login you are asking for - it is shown to them, and becomes the credential\'s name.';
            return null;
        }
        // What they send is encrypted with the link's key; the vault keeps a
        // copy of that key, so only an agent who can open the vault reads it
        $vault_key = encryptCredentialEntry(base64_encode($key));
        if ($vault_key === false) {
            $error = 'Your vault session has expired - sign out and back in, then try again.';
            return null;
        }
    }

    $name = mb_substr($name, 0, 255);
    $set = [
        "item_active = 1",
        "item_version = 2",
        "item_key = '" . shareKeyHash($secret) . "'",
        "item_type = '$type'",
        "item_related_id = $related_id",
        "item_name = '" . escapeSql($name) . "'",
        "item_encrypted_username = ''",
        "item_encrypted_credential = ''",
        "item_payload = " . ($payload === null ? 'NULL' : "'" . escapeSql($payload) . "'"),
        "item_vault_key = " . ($vault_key === null ? 'NULL' : "'" . escapeSql($vault_key) . "'"),
        "item_passphrase = " . ($protect === 'passphrase' ? "'" . escapeSql(password_hash($passphrase, PASSWORD_DEFAULT)) . "'" : 'NULL'),
        "item_verify_email = " . ($protect === 'code' ? 1 : 0),
        "item_note = '" . escapeSql($note) . "'",
        "item_recipient = '" . escapeSql($recipient) . "'",
        "item_notify = " . (!empty($in['notify']) ? 1 : 0),
        "item_views = 0",
        "item_view_limit = $views",
        "item_created_by = " . intval($session_user_id),
        "item_expire_at = NOW() + INTERVAL $expires HOUR",
        "item_client_id = $client_id",
    ];
    mysqli_query($mysqli, "INSERT INTO shared_items SET " . implode(', ', $set));
    $item_id = mysqli_insert_id($mysqli);
    $url = shareUrl($item_id, $secret);
    $row = shareRow($item_id);

    $emailed = false;
    if ($send) {
        $emailed = shareEmailLink($row, $url, $protect) === true;
    }

    // Logging - on the link, and on the credential, so its history says who it went to
    $what = shareTypes()[$type]['label'];
    $to = $recipient !== '' ? ' for ' . escapeSql($recipient) : '';
    $how = ', ' . strtolower(shareViewOptions()[$views]) . ', for ' . shareExpiryOptions()[$expires]
        . ($protect === 'passphrase' ? ', with a passphrase' : '') . ($protect === 'code' ? ', with an emailed code' : '')
        . ($emailed ? ', emailed' : '');
    if ($type === 'Request') {
        logAudit('Share', 'Create', "$session_name asked " . ($recipient !== '' ? escapeSql($recipient) : 'the client') . " for a login - " . escapeSql($name) . " - by link$how", $client_id, $item_id);
    } else {
        logAudit('Share', 'Create', "$session_name shared $what " . escapeSql($name) . "$to$how", $client_id, $item_id);
    }
    if ($type === 'Credential') {
        logAudit('Credential', 'Share', "$session_name shared credential " . escapeSql($name) . "$to$how", $client_id, $related_id);
    }

    return [
        'item_id' => $item_id,
        'url' => $url,
        'emailed' => $emailed,
        'expire_at' => $row['item_expire_at'],
        'passphrase' => $protect === 'passphrase' ? $passphrase : '',
        'protect' => $protect,
        'recipient' => $recipient,
        'views' => $views,
    ];
}

/** The company sending - its name and how to reach it - for emails and the guest page. */
function shareCompany() {
    global $mysqli;
    static $company = null;
    if ($company === null) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code, company_email, company_website, company_logo FROM companies WHERE company_id = 1")) ?: [];
        $company = [
            'name' => (string) ($row['company_name'] ?? ''),
            'phone' => !empty($row['company_phone']) ? formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']) : '',
            'email' => (string) ($row['company_email'] ?? ''),
            'website' => (string) ($row['company_website'] ?? ''),
            'logo' => (string) ($row['company_logo'] ?? ''),
        ];
    }
    return $company;
}

/**
 * An email in the shared links' style - a heading, paragraphs (HTML, already
 * escaped), a button, and who it is from. Tables and inline styles, so it
 * looks the same in Outlook.
 */
function shareMailHtml($heading, array $paragraphs, $button_label = '', $button_url = '') {
    $company = shareCompany();
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;font-family:Segoe UI,Helvetica,Arial,sans-serif;">'
        . '<tr><td align="center"><table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border:1px solid #dee2e6;border-radius:8px;">'
        . '<tr><td style="padding:20px 28px;border-bottom:1px solid #dee2e6;font-size:16px;font-weight:600;color:#212529;">' . escapeHtml($company['name']) . '</td></tr>'
        . '<tr><td style="padding:24px 28px;font-size:15px;line-height:1.55;color:#212529;">'
        . '<h1 style="font-size:20px;margin:0 0 16px;color:#212529;">' . $heading . '</h1>';
    foreach ($paragraphs as $paragraph) {
        $html .= '<p style="margin:0 0 14px;">' . $paragraph . '</p>';
    }
    if ($button_url !== '') {
        $html .= '<p style="margin:22px 0;"><a href="' . escapeHtml($button_url) . '" style="background:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;display:inline-block;">' . escapeHtml($button_label) . '</a></p>'
            . '<p style="margin:0 0 14px;font-size:12px;color:#6c757d;word-break:break-all;">Or paste this into your browser: ' . escapeHtml($button_url) . '</p>';
    }
    $contact = array_filter([$company['phone'], $company['email']]);
    $html .= '</td></tr><tr><td style="padding:16px 28px;border-top:1px solid #dee2e6;font-size:12px;color:#6c757d;">'
        . escapeHtml($company['name']) . ($contact ? ' &middot; ' . escapeHtml(implode(' · ', $contact)) : '')
        . '<br>This email is for the person it was sent to. If it reached you by mistake, delete it and let us know.'
        . '</td></tr></table></td></tr></table>';
    return $html;
}

/** Queue an email from the system mailbox - values raw, escaped here for addToMailQueue(). */
function shareQueueMail($recipient, $subject, $body_html) {
    global $mysqli, $config_mail_from_email, $config_mail_from_name;
    $escape = function ($value) use ($mysqli) {
        return mysqli_real_escape_string($mysqli, (string) $value);
    };
    return addToMailQueue([[
        'from' => $escape($config_mail_from_email),
        'from_name' => $escape($config_mail_from_name ?: shareCompany()['name']),
        'recipient' => $escape($recipient),
        'recipient_name' => $escape($recipient),
        'subject' => $escape($subject),
        'body' => $escape($body_html),
    ]]);
}

/** "3:00 PM today", "9:00 AM tomorrow", "Oct 20 at 3:00 PM" - when a link stops working, for the emails. */
function shareExpiryText($expire_at) {
    $at = strtotime((string) $expire_at);
    if ($at - time() <= 86400) {
        return date('g:i A', $at) . (date('Y-m-d', $at) === date('Y-m-d') ? ' today' : ' tomorrow');
    }
    return date('M j', $at) . ' at ' . date('g:i A', $at);
}

/** Email the link to its recipient. Never the passphrase - that goes another way. */
function shareEmailLink($row, $url, $protect) {
    global $session_name;
    $company = shareCompany();
    $from = escapeHtml((string) $session_name) . ' at ' . escapeHtml($company['name']);
    $name = escapeHtml(shareName($row));
    $expiry = shareExpiryText($row['item_expire_at']);
    $limit = intval($row['item_view_limit']);
    $note = trim((string) $row['item_note']);
    $paragraphs = [];

    if ($row['item_type'] === 'Request') {
        $subject = "$company[name] is asking for a login - $row[item_name]";
        $heading = 'Please send us a login';
        $paragraphs[] = "$from is asking you for <strong>$name</strong>. Use the secure link below rather than email - what you type is encrypted and goes straight to us.";
        $paragraphs[] = "The link works once, until $expiry.";
        $button = 'Send it securely';
    } else {
        $what = ['Credential' => 'login details', 'Note' => 'a secure note', 'Document' => 'a document', 'File' => 'a file'][$row['item_type']] ?? 'something';
        $subject = "$company[name] sent you " . ($row['item_type'] === 'Credential' ? 'login details' : $what) . ' - secure link';
        $heading = $row['item_type'] === 'Credential' ? 'Login details for you' : 'Something to open securely';
        $paragraphs[] = "$from sent you $what" . ($row['item_type'] === 'Credential' || $row['item_type'] === 'Document' || $row['item_type'] === 'File' ? ": <strong>$name</strong>" : '') . '.';
        $paragraphs[] = 'The link works until ' . $expiry . ($limit === 1 ? ', and only once - copy what you need when you open it.' : ($limit > 1 ? ", and can be opened $limit times." : '.'));
        $button = 'Open it securely';
    }
    if ($protect === 'passphrase') {
        $paragraphs[] = 'You will need a passphrase to open it - ' . escapeHtml((string) $session_name) . ' will give it to you another way, such as by phone or text.';
    } elseif ($protect === 'code') {
        $paragraphs[] = 'To open it, we will email a code to this address.';
    }
    if ($note !== '') {
        $paragraphs[] = '<em>' . nl2br(escapeHtml($note)) . '</em>';
    }
    return shareQueueMail($row['item_recipient'], $subject, shareMailHtml($heading, $paragraphs, $button, $url));
}

/**
 * Tell whoever made the link, in the app - when they asked to be told, or
 * always ($always, a link locking). $email sends it by email too (a login a
 * client sent back, which is waiting on someone).
 */
function shareNotifyCreator($row, $title, $details, $email = false, $always = false) {
    global $mysqli;
    if (empty($row['item_notify']) && !$always) {
        return;
    }
    $user_id = intval($row['item_created_by']);
    $client_id = intval($row['item_client_id']);
    $action = "/agent/shares.php?client_id=$client_id&share=" . intval($row['item_id']);
    $user = $user_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_name, user_email FROM users
        WHERE user_id = $user_id AND user_status = 1 AND user_archived_at IS NULL LIMIT 1")) : null;
    if (!$user) {
        // Made before links had an owner, or the agent has gone - everyone hears
        appNotify(escapeSql($title), escapeSql($details), $action, $client_id, intval($row['item_id']));
        return;
    }
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = '" . escapeSql(mb_substr($title, 0, 200)) . "', notification = '" . escapeSql(mb_substr($details, 0, 1000)) . "',
        notification_action = '" . escapeSql($action) . "', notification_client_id = $client_id, notification_entity_id = " . intval($row['item_id']) . ", notification_user_id = $user_id");
    if ($email && shareMailReady() && filter_var($user['user_email'], FILTER_VALIDATE_EMAIL)) {
        global $config_base_url;
        shareQueueMail($user['user_email'], "$title - " . $row['client_name'], shareMailHtml(escapeHtml($title), [escapeHtml($details), 'Open it in Shared Links to save it to the client\'s credentials, or discard it.'], 'Review it', "https://$config_base_url$action"));
    }
}

/**
 * Use up one of a link's views - true when this request got it. The UPDATE
 * is the claim (claimSharedItemView()), so racing requests cannot both win.
 */
function shareClaim($item_id) {
    global $mysqli;
    $item_id = intval($item_id);
    if (!claimSharedItemView($item_id)) {
        return false;
    }
    mysqli_query($mysqli, "UPDATE shared_items SET item_last_viewed_at = NOW() WHERE item_id = $item_id");
    return true;
}

/**
 * End a link - turned off, locked, destroyed, saved or discarded - and wipe
 * whatever secret it held. True when this call ended it.
 */
function shareEnd($item_id, $reason, $user_id = 0) {
    global $mysqli;
    $item_id = intval($item_id);
    $reason = escapeSql($reason);
    $user_id = intval($user_id);
    mysqli_query($mysqli, "UPDATE shared_items SET item_active = 0, item_ended_at = NOW(), item_ended_by = $user_id, item_end_reason = '$reason',
            item_payload = NULL, item_vault_key = NULL, item_encrypted_username = '', item_encrypted_credential = '', item_code = NULL
        WHERE item_id = $item_id AND item_ended_at IS NULL");
    return mysqli_affected_rows($mysqli) === 1;
}

/**
 * Wipe the secrets links no longer need: once a link ends or expires, and
 * SHARE_GRACE_MINUTES after its last view is used. What a client sent back
 * goes after SHARE_RECEIVED_KEEP_DAYS unsaved. Cheap - run on every guest
 * request, the list page and nightly.
 */
function shareSweep() {
    global $mysqli;
    $grace = SHARE_GRACE_MINUTES;
    mysqli_query($mysqli, "UPDATE shared_items SET item_payload = NULL, item_encrypted_username = '', item_encrypted_credential = '', item_code = NULL
        WHERE item_type <> 'Request'
        AND (item_payload IS NOT NULL OR item_encrypted_credential <> '' OR item_encrypted_username <> '')
        AND (item_ended_at IS NOT NULL OR item_expire_at <= NOW()
            OR (item_view_limit > 0 AND item_views >= item_view_limit AND (item_last_viewed_at IS NULL OR item_last_viewed_at < NOW() - INTERVAL $grace MINUTE)))");
    mysqli_query($mysqli, "UPDATE shared_items SET item_vault_key = NULL
        WHERE item_type = 'Request' AND item_vault_key IS NOT NULL AND item_received_at IS NULL AND (item_ended_at IS NOT NULL OR item_expire_at <= NOW())");
    $keep = SHARE_RECEIVED_KEEP_DAYS;
    mysqli_query($mysqli, "UPDATE shared_items SET item_active = 0, item_ended_at = NOW(), item_end_reason = 'Discarded', item_payload = NULL, item_vault_key = NULL
        WHERE item_type = 'Request' AND item_ended_at IS NULL AND item_received_at < NOW() - INTERVAL $keep DAY");
}

/** Forget links that ended, or expired, more than SHARE_KEEP_DAYS ago - nightly. */
function sharePrune() {
    global $mysqli;
    $keep = SHARE_KEEP_DAYS;
    shareSweep();
    mysqli_query($mysqli, "DELETE FROM shared_items
        WHERE (item_ended_at IS NOT NULL AND item_ended_at < NOW() - INTERVAL $keep DAY)
        OR (item_ended_at IS NULL AND item_received_at IS NULL AND item_expire_at < NOW() - INTERVAL $keep DAY)");
}

/**
 * Who did what with a link - made, opened (when, from where, with what),
 * wrong passphrases, turned off - from the audit log, oldest first.
 */
function shareHistory($row) {
    global $mysqli;
    $item_id = intval($row['item_id']);
    $client_id = intval($row['item_client_id']);
    $since = escapeSql((string) $row['item_created_at']);
    $sql = mysqli_query($mysqli, "SELECT log_action, log_description, log_ip, log_user_agent, log_created_at, user_name FROM logs
        LEFT JOIN users ON user_id = log_user_id
        WHERE log_type = 'Share' AND log_entity_id = $item_id AND log_client_id = $client_id AND log_created_at >= '$since' - INTERVAL 1 MINUTE
        ORDER BY log_id ASC LIMIT 200");
    $rows = [];
    while ($entry = mysqli_fetch_assoc($sql)) {
        $rows[] = $entry;
    }
    return $rows;
}

/** A link's details for the agent - the list and the quick look. */
function shareRows($where, $order = 'item_created_at DESC', $limit = 0, $offset = 0) {
    global $mysqli;
    $limit_sql = $limit ? 'LIMIT ' . intval($offset) . ', ' . intval($limit) : '';
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS shared_items.item_id, item_active, item_type, item_related_id, item_name,
            item_passphrase IS NOT NULL AS item_passphrase, item_verify_email, item_note, item_recipient, item_notify, item_views, item_view_limit,
            item_last_viewed_at, item_created_at, item_created_by, item_expire_at, item_received_at, item_ended_at, item_end_reason,
            item_failed_attempts, item_client_id, client_name, creator.user_name AS creator_name
        FROM shared_items
        LEFT JOIN clients ON client_id = item_client_id
        LEFT JOIN users creator ON creator.user_id = item_created_by
        WHERE $where
        ORDER BY $order $limit_sql");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** What a client sent back to a request, decrypted with the vault - an array, or false when the vault is not open. */
function shareRequestOpen($row) {
    if (empty($row['item_payload']) || empty($row['item_vault_key'])) {
        return null;
    }
    $key = decryptCredentialEntry($row['item_vault_key']);
    if ($key === false || $key === '') {
        return false;
    }
    $data = shareUnseal($row['item_payload'], base64_decode($key));
    return $data === null ? false : $data;
}

/**
 * Save what a client sent back as a new credential for the client - the
 * request's name, and their URL, username, password, 2FA and note - and
 * close the request. The credential's id, or null with $error.
 */
function shareRequestSave($row, &$error = '') {
    global $mysqli, $session_name, $session_user_id;
    $error = '';
    $data = shareRequestOpen($row);
    if ($data === false) {
        $error = 'Your vault session has expired, so it could not be read - sign out and back in, then try again.';
        return null;
    }
    if (!$data) {
        $error = 'There is nothing to save - it may have been saved or discarded already.';
        return null;
    }
    $client_id = intval($row['item_client_id']);
    $from = trim((string) $row['item_recipient']);
    $note = trim((string) ($data['note'] ?? ''));
    $note .= ($note !== '' ? "\n\n" : '') . 'Sent by ' . ($from !== '' ? $from : 'the client') . ' through a request link, ' . date('M j, Y', strtotime((string) $row['item_received_at'])) . '.';
    $post = [
        'name' => shareName($row),
        'uri' => (string) ($data['uri'] ?? ''),
        'username' => (string) ($data['username'] ?? ''),
        'password' => (string) ($data['password'] ?? ''),
        'otp_secret' => (string) ($data['otp'] ?? ''),
        'note' => $note,
    ];
    if (credentialOtpSeed($post['otp_secret']) === false) {
        // Saved without it rather than not at all - it stays in the note to fix
        $post['note'] .= "\n2FA secret as sent (not a valid seed): " . $post['otp_secret'];
        $post['otp_secret'] = '';
    }
    $set = credentialFormSet($post, $client_id, $error);
    if ($set === null) {
        return null;
    }
    mysqli_query($mysqli, "INSERT INTO credentials SET $set, credential_password_changed_at = NOW(), credential_client_id = $client_id");
    $credential_id = mysqli_insert_id($mysqli);
    $item_id = intval($row['item_id']);
    shareEnd($item_id, 'Saved', $session_user_id);
    mysqli_query($mysqli, "UPDATE shared_items SET item_related_id = $credential_id WHERE item_id = $item_id");

    $name = escapeSql($post['name']);
    logAudit('Credential', 'Create', "$session_name saved credential $name from a request link" . ($from !== '' ? ' answered by ' . escapeSql($from) : ''), $client_id, $credential_id);
    logAudit('Share', 'Save', "$session_name saved what was sent as credential $name", $client_id, $item_id);
    return $credential_id;
}

/** The guest's own record of a link in this browser - verified (passphrase or code) and opened, as times. */
function shareSession($item_id) {
    return $_SESSION['itflow_shares'][intval($item_id)] ?? ['verified' => 0, 'opened' => 0];
}

function shareSessionMark($item_id, $what) {
    $item_id = intval($item_id);
    $state = shareSession($item_id);
    $state[$what] = time();
    $_SESSION['itflow_shares'][$item_id] = $state;
}

/** Has this browser passed the link's passphrase or code (recently)? Always for a link without one. */
function shareVerified($row) {
    if (shareProtection($row) === '') {
        return true;
    }
    return shareSession($row['item_id'])['verified'] > time() - SHARE_GRACE_MINUTES * 60;
}

/** Did this browser open the link recently - may it open it again without using a view? */
function shareOpenedHere($row) {
    return shareSession($row['item_id'])['opened'] > time() - SHARE_GRACE_MINUTES * 60;
}

/**
 * Take one passphrase or code try - false when the link is out of tries.
 * The UPDATE is the claim, so a burst of guesses cannot get past the limit.
 */
function shareTakeAttempt($item_id) {
    global $mysqli;
    $item_id = intval($item_id);
    $attempts = SHARE_ATTEMPTS;
    mysqli_query($mysqli, "UPDATE shared_items SET item_failed_attempts = item_failed_attempts + 1
        WHERE item_id = $item_id AND item_ended_at IS NULL AND item_failed_attempts < $attempts");
    return mysqli_affected_rows($mysqli) === 1;
}

/** A menu item that opens what a link shares (or the credential a request was saved as) - '' when it is gone or not this agent's to see. */
function shareItemMenuLink($row) {
    $id = intval($row['item_related_id']);
    if (!$id) {
        return '';
    }
    $type = $row['item_type'];
    if (in_array($type, ['Credential', 'Request'], true)) {
        if (lookupUserPermission('module_credential') < 1 || ($type === 'Request' && ($row['item_end_reason'] ?? '') !== 'Saved')) {
            return '';
        }
        $live = shareItemLive(['item_type' => 'Credential', 'item_related_id' => $id, 'item_client_id' => $row['item_client_id']]);
        return $live === null ? '' : '<a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/credential/credential.php?id=' . $id . '"><i class="fas fa-fw fa-key text-secondary me-2"></i>' . ($type === 'Request' ? 'Open the saved credential' : 'Open the credential') . '</a>';
    }
    if (shareItemLive($row) === null) {
        return '';
    }
    if ($type === 'Document') {
        return '<a class="dropdown-item" href="document.php?client_id=' . intval($row['item_client_id']) . '&amp;document_id=' . $id . '"><i class="fas fa-fw fa-file-alt text-secondary me-2"></i>Open the document</a>';
    }
    if ($type === 'File') {
        return '<a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/file/file.php?id=' . $id . '"><i class="fas fa-fw fa-paperclip text-secondary me-2"></i>Open the file</a>';
    }
    return '';
}
