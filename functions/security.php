<?php

// Cryptography, key management, credential encryption & token validation
// Split from the former monolithic functions.php


// Function to generate both crypto & URL safe random strings
function randomString(int $length = 16): string {
    $bytes = random_bytes((int) ceil($length * 3 / 4));
    return substr(
        rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='),
        0,
        $length
    );
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

// Called during initial setup
// Encrypts the master key with the user's password
function setupFirstUserSpecificKey($user_password, $site_encryption_master_key) {
    $iv = randomString();
    $salt = randomString();

    //Generate 128-bit (16 byte/char) kdhash of the users password
    $user_password_kdhash = hash_pbkdf2('sha256', $user_password, $salt, 100000, 16);

    //Encrypt the master key with the users kdf'd hash and the IV
    $ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_password_kdhash, 0, $iv);

    return $salt . $iv . $ciphertext;
}

/*
 * For additional users / password changes (and now the API)
 * New Users: Requires the admin setting up their account have a Specific/Session key configured
 * Password Changes: Will use the current info in the session.
*/
function encryptUserSpecificKey($user_password) {
    $iv = randomString();
    $salt = randomString();

    // Get the session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'];
    $user_encryption_session_iv = base64_decode($_SESSION['user_encryption_session_iv'] ?? '');
    $user_encryption_session_key = base64_decode($_COOKIE['user_encryption_session_key'] ?? '');

    // Decrypt the session key to get the master key
    $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    // Generate 128-bit (16 byte/char) kdhash of the users (new) password
    $user_password_kdhash = hash_pbkdf2('sha256', $user_password, $salt, 100000, 16);

    // Encrypt the master key with the users kdf'd hash and the IV
    $ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_password_kdhash, 0, $iv);

    return $salt . $iv . $ciphertext;
}

// Given a ciphertext (incl. IV) and the user's (or API key) password, returns the site master key
// Ran at login, to facilitate generateUserSessionKey
function decryptUserSpecificKey($user_encryption_ciphertext, $user_password) {
    //Get the IV, salt and ciphertext
    $salt = substr($user_encryption_ciphertext, 0, 16);
    $iv = substr($user_encryption_ciphertext, 16, 16);
    $ciphertext = substr($user_encryption_ciphertext, 32);

    //Generate 128-bit (16 byte/char) kdhash of the users password
    $user_password_kdhash = hash_pbkdf2('sha256', $user_password, $salt, 100000, 16);

    //Use this hash to get the original/master key
    return openssl_decrypt($ciphertext, 'aes-128-cbc', $user_password_kdhash, 0, $iv);
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
        setcookie("user_encryption_session_key", base64_encode($user_encryption_session_key), 0, "/");
        $_SESSION['alert_message'] = "Unencrypted connection flag set: Using non-secure cookies.";
    }
}

// Decrypts an encrypted password (website/asset credentials), returns it as a string
function decryptCredentialEntry($credential_password_ciphertext) {

    // Split the credential into IV and Ciphertext
    $credential_iv =  substr($credential_password_ciphertext, 0, 16);
    $credential_ciphertext = $salt = substr($credential_password_ciphertext, 16);

    // Get the user session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'];
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
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'];
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
