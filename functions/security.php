<?php

// Cryptography, key management, credential encryption & token validation
// Split from the former monolithic functions.php


// Function to generate both crypto & URL safe random strings
function randomString(int $length = 16): string {
    $bytes = random_bytes(intval(ceil($length * 3 / 4)));
    return substr(
        rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='),
        0,
        $length
    );
}

// Generate a pronounceable password - readable enough to dictate down the phone, while
// still carrying real entropy. Every syllable is one of 90 consonant-vowel pairs, so the
// default three words of three syllables plus the two-digit suffix is roughly 65 bits.
// The floors are deliberate: this feeds real user and contact logins, so a caller asking
// for one short word must not walk away with a guessable password.
function generateReadablePassword(int $word_count = 3, int $syllables_per_word = 3): string {

    $consonants = 'bcdfghjklmnprstvwz';
    $vowels = 'aeiou';

    $word_count = max(2, $word_count);
    $syllables_per_word = max(2, $syllables_per_word);

    $words = [];

    for ($word_index = 0; $word_index < $word_count; $word_index++) {

        $word = '';

        for ($syllable_index = 0; $syllable_index < $syllables_per_word; $syllable_index++) {
            $word .= $consonants[random_int(0, strlen($consonants) - 1)];
            $word .= $vowels[random_int(0, strlen($vowels) - 1)];
        }

        $words[] = ucfirst($word);
    }

    return implode('-', $words) . '-' . random_int(10, 99);
}

// Generate a cryptographically secure 32-char base32 secret for TOTP
function generateTotpSecret() {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    $key = '';
    for ($i = 0; $i < 32; $i++) {
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $key;
}

/*
 * A user's, API key's or invite's copy of the vault's master key, sealed under their password:
 * "v2:", then base64 of a 16 byte salt, a 12 byte nonce, the 16 byte GCM tag and the
 * ciphertext - AES-256-GCM, under PBKDF2-SHA256 of the password at VAULT_WRAP_ITERATIONS with
 * its full 32 bytes. A copy without the prefix was made before (16 hex characters of a 100,000
 * round PBKDF2 under AES-128-CBC - 64 bits): it still opens, and moves to this one at its next
 * sign-in (vaultSignIn()) or API call (apiDecryptPasswordOpens()).
 */
define('VAULT_WRAP_FORMAT', 'v2:');
define('VAULT_WRAP_ITERATIONS', 600000);

// Called during initial setup, for new users, invites and when moving a copy to the current format
// Encrypts the master key with the user's password
function setupFirstUserSpecificKey($user_password, $site_encryption_master_key) {
    $salt = random_bytes(16);
    $nonce = random_bytes(12);
    $key = hash_pbkdf2('sha256', (string) $user_password, $salt, VAULT_WRAP_ITERATIONS, 32, true);
    $tag = '';
    $ciphertext = openssl_encrypt((string) $site_encryption_master_key, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, VAULT_WRAP_FORMAT);
    return VAULT_WRAP_FORMAT . base64_encode($salt . $nonce . $tag . $ciphertext);
}

/*
 * For additional users / password changes (and now the API)
 * New Users: Requires the admin setting up their account have a Specific/Session key configured
 * Password Changes: Will use the current info in the session.
 * False when this session's vault doesn't open - a copy of nothing would lock them out of it.
*/
function encryptUserSpecificKey($user_password) {
    // Get the session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'] ?? '';
    $user_encryption_session_iv = base64_decode($_SESSION['user_encryption_session_iv'] ?? '');
    $user_encryption_session_key = base64_decode($_COOKIE['user_encryption_session_key'] ?? '');

    // Decrypt the session key to get the master key
    $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);
    if (!is_string($site_encryption_master_key) || $site_encryption_master_key === '') {
        return false;
    }

    return setupFirstUserSpecificKey($user_password, $site_encryption_master_key);
}

// Given a ciphertext (incl. IV) and the user's (or API key) password, returns the site master key
// Ran at login, to facilitate generateUserSessionKey - and on API calls, once a call (each key is slow to derive by design)
function decryptUserSpecificKey($user_encryption_ciphertext, $user_password) {
    static $opened = [];
    $user_encryption_ciphertext = (string) $user_encryption_ciphertext;
    $memo = hash('sha256', $user_encryption_ciphertext . "\0" . $user_password);
    if (isset($opened[$memo])) {
        return $opened[$memo];
    }

    if (strpos($user_encryption_ciphertext, VAULT_WRAP_FORMAT) === 0) {
        $raw = base64_decode(substr($user_encryption_ciphertext, strlen(VAULT_WRAP_FORMAT)), true);
        if ($raw === false || strlen($raw) < 45) {
            return false;
        }
        $key = hash_pbkdf2('sha256', (string) $user_password, substr($raw, 0, 16), VAULT_WRAP_ITERATIONS, 32, true);
        $master = openssl_decrypt(substr($raw, 44), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 16, 12), substr($raw, 28, 16), VAULT_WRAP_FORMAT);
    } else {
        //Get the IV, salt and ciphertext
        $salt = substr($user_encryption_ciphertext, 0, 16);
        $iv = substr($user_encryption_ciphertext, 16, 16);
        $ciphertext = substr($user_encryption_ciphertext, 32);

        //Generate 128-bit (16 byte/char) kdhash of the users password
        $user_password_kdhash = hash_pbkdf2('sha256', (string) $user_password, $salt, 100000, 16);

        //Use this hash to get the original/master key
        $master = openssl_decrypt($ciphertext, 'aes-128-cbc', $user_password_kdhash, 0, $iv);
    }

    if (is_string($master) && $master !== '') {
        $opened[$memo] = $master;
    }
    return $master;
}

/** A copy of the master key in the format from before (setupFirstUserSpecificKey()) - '' is none at all. */
function vaultWrapIsLegacy($ciphertext) {
    $ciphertext = (string) $ciphertext;
    return $ciphertext !== '' && strpos($ciphertext, VAULT_WRAP_FORMAT) !== 0;
}

/*
 * Signing in, with the password in hand: open the user's copy of the master key, move it to the
 * current format when it is in the old one - written only once the new copy is checked to open,
 * and only over the copy it read - and start the vault session (generateUserSessionKey()).
 * Whether the vault opened.
 */
function vaultSignIn($user_id, $ciphertext, $password) {
    global $mysqli;
    if (empty($ciphertext)) {
        return false;
    }
    $master = decryptUserSpecificKey($ciphertext, $password);
    if (!is_string($master) || $master === '') {
        return false;
    }
    if (vaultWrapIsLegacy($ciphertext)) {
        $rewrapped = setupFirstUserSpecificKey($password, $master);
        if (decryptUserSpecificKey($rewrapped, $password) === $master) {
            mysqli_query($mysqli, "UPDATE users SET user_specific_encryption_ciphertext = '" . escapeSql($rewrapped) . "'
                WHERE user_id = " . intval($user_id) . " AND user_specific_encryption_ciphertext = '" . escapeSql($ciphertext) . "'");
        }
    }
    generateUserSessionKey($master);
    return true;
}

/*
 * The copies of the master key, by format (Admin > Backup): 'old' - the agents' (id, name, email)
 * and API keys' (id, name) still in the format from before - and how many there are in all and
 * in the current one.
 */
function vaultCopies() {
    global $mysqli;
    $prefix = escapeSql(VAULT_WRAP_FORMAT);
    $copies = ['users' => [], 'keys' => [], 'total' => 0, 'current' => 0];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_email, user_specific_encryption_ciphertext LIKE '$prefix%' AS current FROM users
        WHERE user_type = 1 AND user_specific_encryption_ciphertext <> '' ORDER BY user_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $copies['total']++;
        if (intval($row['current'])) {
            $copies['current']++;
        } else {
            $copies['users'][] = $row;
        }
    }
    $sql = mysqli_query($mysqli, "SELECT api_key_id, api_key_name, api_key_decrypt_hash LIKE '$prefix%' AS current FROM api_keys WHERE api_key_decrypt_hash <> '' ORDER BY api_key_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $copies['total']++;
        if (intval($row['current'])) {
            $copies['current']++;
        } else {
            $copies['keys'][] = $row;
        }
    }
    return $copies;
}

/*
Generates what is probably best described as a session key (ephemeral-ish)
- Allows us to store the master key on the server whilst the user is using the application, without prompting to type their password everytime they want to decrypt a credential
- Ciphertext/IV is stored on the server in the users' session, encryption key is controlled/provided by the user as a cookie
- Only the user can decrypt their session ciphertext to get the master key
- Encryption key never hits the disk in cleartext

*/
function generateUserSessionKey($site_encryption_master_key) {
    $user_encryption_session_key = random_bytes(16);
    $user_encryption_session_iv = random_bytes(16);
    $user_encryption_session_ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    // Store ciphertext in the user's session
    $_SESSION['user_encryption_session_ciphertext'] = $user_encryption_session_ciphertext;
    $_SESSION['user_encryption_session_iv'] = base64_encode($user_encryption_session_iv);

    // Give the user "their" key as a cookie
    include 'config.php';

    if ($config_https_only) {
        setcookie("user_encryption_session_key", base64_encode($user_encryption_session_key), ['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
    } else {
        // Not secure without HTTPS, but still out of reach of page scripts
        setcookie("user_encryption_session_key", base64_encode($user_encryption_session_key), ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        flashAlert("Unencrypted connection flag set: Using non-secure cookies.", 'warning');
    }
}

// Decrypts an encrypted password (website/asset credentials), returns it as a string
function decryptCredentialEntry($credential_password_ciphertext) {

    // Split the credential into IV and Ciphertext
    $credential_iv =  substr($credential_password_ciphertext, 0, 16);
    $credential_ciphertext = $salt = substr($credential_password_ciphertext, 16);

    // Get the user session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'] ?? '';
    $user_encryption_session_iv =  base64_decode($_SESSION['user_encryption_session_iv'] ?? '');
    $user_encryption_session_key = base64_decode($_COOKIE['user_encryption_session_key'] ?? '');

    // Decrypt the session key to get the master key
    $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    // Decrypt the credential password using the master key
    return openssl_decrypt($credential_ciphertext, 'aes-128-cbc', $site_encryption_master_key, 0, $credential_iv);
}

// Encrypts a website/asset credential password
function encryptCredentialEntry($credential_password_cleartext) {
    $iv = randomString();

    // Get the user session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'] ?? '';
    $user_encryption_session_iv =  base64_decode($_SESSION['user_encryption_session_iv'] ?? '');
    $user_encryption_session_key = base64_decode($_COOKIE['user_encryption_session_key'] ?? '');

    //Decrypt the session key to get the master key
    $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    // Never write a credential under an empty key if the session didn't open
    if (empty($site_encryption_master_key)) {
        return false;
    }

    //Encrypt the website/asset credential using the master key
    $ciphertext = openssl_encrypt($credential_password_cleartext, 'aes-128-cbc', $site_encryption_master_key, 0, $iv);

    return $iv . $ciphertext;
}

/*
 * Does this session's vault open to a master key that looks right - the 16 characters an
 * install's master key is? A session cookie that doesn't match decrypts to nothing or, now and
 * then, to rubbish of another length, and anything encrypted under that could never be read
 * back. For work on many credentials at once (credentialOtpVaultStored()), which is left
 * undone rather than risked.
 */
function vaultSessionOpens() {
    $ciphertext = $_SESSION['user_encryption_session_ciphertext'] ?? '';
    $iv = base64_decode($_SESSION['user_encryption_session_iv'] ?? '');
    $key = base64_decode($_COOKIE['user_encryption_session_key'] ?? '');
    if ($ciphertext === '' || strlen($iv) !== 16 || $key === '') {
        return false;
    }
    $master = openssl_decrypt($ciphertext, 'aes-128-cbc', $key, 0, $iv);
    return is_string($master) && strlen($master) === 16;
}

function apiDecryptCredentialEntry($credential_ciphertext, $api_key_decrypt_hash, #[\SensitiveParameter]$api_key_decrypt_password)
{
    // Split the Credential entry (username/password) into IV and Ciphertext
    $credential_iv =  substr($credential_ciphertext, 0, 16);
    $credential_ciphertext = $salt = substr($credential_ciphertext, 16);

    // Decrypt the api hash to get the master key
    $site_encryption_master_key = decryptUserSpecificKey($api_key_decrypt_hash, $api_key_decrypt_password);

    // Decrypt the credential password using the master key
    return openssl_decrypt($credential_ciphertext, 'aes-128-cbc', $site_encryption_master_key, 0, $credential_iv);
}

/**
 * Does this decrypt password open the API key's copy of the master key? A
 * wrong one decrypts to nothing (or rubbish), and a credential saved under
 * that could never be read again.
 */
function apiDecryptPasswordOpens($api_key_decrypt_hash, #[\SensitiveParameter]$api_key_decrypt_password) {
    global $mysqli;
    $key = decryptUserSpecificKey((string) $api_key_decrypt_hash, (string) $api_key_decrypt_password);
    if (!is_string($key) || $key === '') {
        return false;
    }
    // The key's copy of the master key in the format from before - moved to the current one now its password is here,
    // once the new copy is checked to open, and only over the copy it read
    if (vaultWrapIsLegacy($api_key_decrypt_hash)) {
        $rewrapped = setupFirstUserSpecificKey((string) $api_key_decrypt_password, $key);
        if (decryptUserSpecificKey($rewrapped, (string) $api_key_decrypt_password) === $key) {
            mysqli_query($mysqli, "UPDATE api_keys SET api_key_decrypt_hash = '" . escapeSql($rewrapped) . "' WHERE api_key_decrypt_hash = '" . escapeSql((string) $api_key_decrypt_hash) . "'");
        }
    }
    return true;
}

function apiEncryptCredentialEntry(#[\SensitiveParameter]$credential_cleartext, $api_key_decrypt_hash, #[\SensitiveParameter]$api_key_decrypt_password) {
    $iv = randomString();

    // Decrypt the api hash to get the master key
    $site_encryption_master_key = decryptUserSpecificKey($api_key_decrypt_hash, $api_key_decrypt_password);

    // Encrypt the credential using the master key
    $ciphertext = openssl_encrypt($credential_cleartext, 'aes-128-cbc', $site_encryption_master_key, 0, $iv);

    return $iv . $ciphertext;
}

/*
 * Longest cleartext a credential username or password may be.
 * Both encrypt functions above return a 16-char IV followed by base64 AES-128-CBC
 * ciphertext, which expands about 1.37x, so 350 is the most that still fits the
 * varchar(500) columns. Keep in step with the maxlength on the credential/asset forms.
 */
define('CREDENTIAL_ENTRY_MAX_LENGTH', 350);

/*
 * Checks a credential's cleartext fields against what the columns can actually store.
 * Form maxlength is client-side only, so the CSV import, the API and any hand-rolled POST
 * reach the INSERT with nothing stopping an overlong value - and MySQL rejects it outright
 * rather than truncating, taking the request down with it.
 *
 * Returns the name of the first field that is too long, or an empty string when they all
 * fit. Only keys actually present are checked, so partial updates are fine.
 */
function checkCredentialLengths(array $fields) {

    // Encrypted before storage - ciphertext size follows the BYTE length of the cleartext.
    $byte_limits = [
        'username'    => CREDENTIAL_ENTRY_MAX_LENGTH,
        'password'    => CREDENTIAL_ENTRY_MAX_LENGTH,
    ];

    // Stored as given - MySQL measures varchar in CHARACTERS, not bytes.
    $char_limits = [
        'name'        => 200,
        'description' => 500,
        'uri'         => 500,
        'uri_2'       => 500,
        'otp_secret'  => 200,
    ];

    foreach ($byte_limits as $field => $limit) {
        if (isset($fields[$field]) && strlen($fields[$field]) > $limit) {
            return $field;
        }
    }

    foreach ($char_limits as $field => $limit) {
        if (!isset($fields[$field]) || strlen($fields[$field]) <= $limit) {
            continue; // byte length caps character count, so this already fits
        }
        // Only worth counting characters once the cheap check fails. preg keeps this
        // free of an mbstring dependency, which nothing else in the tree relies on.
        if (preg_match_all('/./us', $fields[$field]) > $limit) {
            return $field;
        }
    }

    return '';
}

// Cross-Site Request Forgery check for sensitive functions
// Validates the CSRF token provided matches the one in the users session
function validateCSRFToken(?string $token = null) {
    // Read the token straight from the request when the caller doesn't pass one
    $token ??= $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if ($token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        return true;
    }

    // 403 rather than a redirect. A 302 to index.php means anything following
    // redirects - browsers, curl -L, security scanners - records a final 200
    // and reports the request as accepted.
    if (!headers_sent()) {
        http_response_code(403);
        header("Content-Type: text/html; charset=UTF-8");
        header("X-Content-Type-Options: nosniff");
        header("Cache-Control: no-store");
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<title>403 Forbidden</title></head><body>'
        . '<h1>403 Forbidden</h1>'
        . '<p>CSRF token verification failed. Your session may have expired.</p>'
        . '<p><a href="/">Return to ITFlow</a> and try again.</p>'
        . '</body></html>';

    exit();
}

function validateWhitelabelKey($key) {
    $public_key = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAr0k+4ZJudkdGMCFLx5b9
H/sOozvWphFJsjVIF0vPVx9J0bTdml65UdS+32JagIHfPtEUTohaMnI3IAxxCDzl
655qmtjL7RHHdx9UMIKCmtAZOtd2u6rEyZH7vB7cKA49ysKGIaQSGwTQc8DCgsrK
uxRuX04xq9T7T+zuzROw3Y9WjFy9RwrONqLuG8LqO0j7bk5LKYeLAV7u3E/QiqNx
lEljN2UVJ3FZ/LkXeg8ORkV+IHs/toRIfPs/4VQnjEwk5BU6DX2STOvbeZnTqwP3
zgjRYR/zGN5l+az6RB3+0mJRdZdv/y2aRkBlwTxx2gOrPbQAco4a/IOmkE3EbHe7
6wIDAQAP
-----END PUBLIC KEY-----";

    if (openssl_public_decrypt(base64_decode($key), $decrypted, $public_key)) {
        $key_info = json_decode($decrypted, true);
        if ($key_info['expires'] > date('Y-m-d H:i:s', strtotime('-7 day'))) {
            return $key_info;
        }
    }

    return false;
}

// Atomically claim one view against a shared item's view limit.
// Returns true only if this request won the view; false means the share is
// inactive, expired, or out of views. The UPDATE is the claim, so concurrent
// requests cannot all pass - call this before any shared content is disclosed.
function claimSharedItemView($item_id) {
    global $mysqli;

    $item_id = intval($item_id);

    mysqli_query($mysqli, "UPDATE shared_items
        SET item_views = item_views + 1
        WHERE item_id = $item_id
        AND item_active = 1
        AND item_expire_at > NOW()
        AND (COALESCE(item_view_limit, 0) = 0 OR item_views < item_view_limit)"
    );

    // -1 (query error) and 0 (limit reached / revoked / expired) both deny
    return mysqli_affected_rows($mysqli) === 1;
}
