<?php 


// Security related functions
function nullable_htmlentities($unsanitizedInput)
{
    return htmlentities($unsanitizedInput ?? '');
}

function getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'];
}

function getIP()
{
    if (defined("CONST_GET_IP_METHOD")) {
        if (CONST_GET_IP_METHOD == "HTTP_X_FORWARDED_FOR") {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } else {
            $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER['REMOTE_ADDR'];
        }
    } else {
        $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER['REMOTE_ADDR'];
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        exit("Potential Security Violation");
    }

    return $ip;
}

function getWebBrowser($user_browser)
{
    $browser        =   "Unknown Browser";
    $browser_array  =   array(
        '/msie/i'       =>  "<i class='fab fa-fw fa-internet-explorer text-secondary'></i> Internet Explorer",
        '/firefox/i'    =>  "<i class='fab fa-fw fa-firefox text-secondary'></i> Firefox",
        '/safari/i'     =>  "<i class='fab fa-fw fa-safari text-secondary'></i> Safari",
        '/chrome/i'     =>  "<i class='fab fa-fw fa-chrome text-secondary'></i> Chrome",
        '/edge/i'       =>  "<i class='fab fa-fw fa-edge text-secondary'></i> Edge",
        '/opera/i'      =>  "<i class='fab fa-fw fa-opera text-secondary'></i> Opera"
    );
    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_browser)) {
            $browser    =   $value;
        }
    }
    return $browser;
}

function getOS($user_os)
{
    $os_platform    =   "Unknown OS";
    $os_array       =   array(
        '/windows nt 10/i'      =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows 10",
        '/windows nt 6.3/i'     =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows 8.1",
        '/windows nt 6.2/i'     =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows 8",
        '/windows nt 6.1/i'     =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows 7",
        '/windows nt 6.0/i'     =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows Vista",
        '/windows nt 5.2/i'     =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows Server 2003/XP x64",
        '/windows nt 5.1/i'     =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows XP",
        '/windows xp/i'         =>  "<i class='fab fa-fw fa-windows text-secondary'></i> Windows XP",
        '/macintosh|mac os x/i' =>  "<i class='fab fa-fw fa-apple text-secondary'></i> MacOS",
        '/linux/i'              =>  "<i class='fab fa-fw fa-linux text-secondary'></i> Linux",
        '/ubuntu/i'             =>  "<i class='fab fa-fw fa-ubuntu text-secondary'></i> Ubuntu",
        '/iphone/i'             =>  "<i class='fab fa-fw fa-apple text-secondary'></i> iPhone",
        '/ipod/i'               =>  "<i class='fab fa-fw fa-apple text-secondary'></i> iPod",
        '/ipad/i'               =>  "<i class='fab fa-fw fa-apple text-secondary'></i> iPad",
        '/android/i'            =>  "<i class='fab fa-fw fa-android text-secondary'></i> Android"
    );
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_os)) {
            $os_platform    =   $value;
        }
    }
    return $os_platform;
}

function getDevice()
{
    $tablet_browser = 0;
    $mobile_browser = 0;
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
        $tablet_browser++;
    }
    if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
        $mobile_browser++;
    }
    if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/vnd.wap.xhtml+xml') > 0) || ((isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])))) {
        $mobile_browser++;
    }
    $mobile_ua = strtolower(substr(getUserAgent(), 0, 4));
    $mobile_agents = array(
        'w3c ', 'acs-', 'alav', 'alca', 'amoi', 'audi', 'avan', 'benq', 'bird', 'blac',
        'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'inno',
        'ipaq', 'java', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-',
        'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-',
        'newt', 'noki', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox',
        'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar',
        'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-',
        'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp',
        'wapr', 'webc', 'winw', 'winw', 'xda ', 'xda-'
    );
    if (in_array($mobile_ua, $mobile_agents)) {
        $mobile_browser++;
    }
    if (strpos(strtolower(getUserAgent()), 'opera mini') > 0) {
        $mobile_browser++;
        //Check for tablets on Opera Mini alternative headers
        $stock_ua = strtolower(isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']) ? $_SERVER['HTTP_X_OPERAMINI_PHONE_UA'] : (isset($_SERVER['HTTP_DEVICE_STOCK_UA']) ? $_SERVER['HTTP_DEVICE_STOCK_UA'] : ''));
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $stock_ua)) {
            $tablet_browser++;
        }
    }
    if ($tablet_browser > 0) {
        //do something for tablet devices
        return 'Tablet';
    } else if ($mobile_browser > 0) {
        //do something for mobile devices
        return 'Mobile';
    } else {
        //do something for everything else
        return 'Computer';
    }
}

// Called during initial setup
// Encrypts the master key with the user's password
function setupFirstUserSpecificKey($user_password, $site_encryption_master_key)
{
    $iv = randomString();
    $salt = randomString();

    //Generate 128-bit (16 byte/char) kdhash of the users password
    $user_password_kdhash = hash_pbkdf2('sha256', $user_password, $salt, 100000, 16);

    //Encrypt the master key with the users kdf'd hash and the IV
    $ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_password_kdhash, 0, $iv);

    return $salt . $iv . $ciphertext;
}

/*
 * For additional users / password changes
 * New Users: Requires the admin setting up their account have a Specific/Session key configured
 * Password Changes: Will use the current info in the session.
*/
function encryptUserSpecificKey($user_password)
{
    $iv = randomString();
    $salt = randomString();

    // Get the session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'];
    $user_encryption_session_iv =  $_SESSION['user_encryption_session_iv'];
    $user_encryption_session_key = $_COOKIE['user_encryption_session_key'];

    // Decrypt the session key to get the master key
    $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    // Generate 128-bit (16 byte/char) kdhash of the users (new) password
    $user_password_kdhash = hash_pbkdf2('sha256', $user_password, $salt, 100000, 16);

    // Encrypt the master key with the users kdf'd hash and the IV
    $ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_password_kdhash, 0, $iv);

    return $salt . $iv . $ciphertext;
}

// Given a ciphertext (incl. IV) and the user's password, returns the site master key
// Ran at login, to facilitate generateUserSessionKey
function decryptUserSpecificKey($user_encryption_ciphertext, $user_password)
{
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
function generateUserSessionKey($site_encryption_master_key)
{
    $user_encryption_session_key = randomString();
    $user_encryption_session_iv = randomString();
    $user_encryption_session_ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    // Store ciphertext in the user's session
    $_SESSION['user_encryption_session_ciphertext'] = $user_encryption_session_ciphertext;
    $_SESSION['user_encryption_session_iv'] = $user_encryption_session_iv;

    // Give the user "their" key as a cookie
    include 'config.php';

    if ($config_https_only) {
        setcookie("user_encryption_session_key", "$user_encryption_session_key", ['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
    } else {
        setcookie("user_encryption_session_key", $user_encryption_session_key, 0, "/");
        $_SESSION['alert_message'] = "Unencrypted connection flag set: Using non-secure cookies.";
    }
}

// Decrypts an encrypted password (website/asset login), returns it as a string
function decryptLoginEntry($login_password_ciphertext)
{

    // Split the login into IV and Ciphertext
    $login_iv =  substr($login_password_ciphertext, 0, 16);
    $login_ciphertext = $salt = substr($login_password_ciphertext, 16);

    // Get the user session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'];
    $user_encryption_session_iv =  $_SESSION['user_encryption_session_iv'];
    $user_encryption_session_key = $_COOKIE['user_encryption_session_key'];

    // Decrypt the session key to get the master key
    $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    // Decrypt the login password using the master key
    return openssl_decrypt($login_ciphertext, 'aes-128-cbc', $site_encryption_master_key, 0, $login_iv);
}

// Encrypts a website/asset login password
function encryptLoginEntry($login_password_cleartext)
{
    $iv = randomString();

    // Get the user session info.
    $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'];
    $user_encryption_session_iv =  $_SESSION['user_encryption_session_iv'];
    $user_encryption_session_key = $_COOKIE['user_encryption_session_key'];

    //Decrypt the session key to get the master key
    $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

    //Encrypt the website/asset login using the master key
    $ciphertext = openssl_encrypt($login_password_cleartext, 'aes-128-cbc', $site_encryption_master_key, 0, $iv);

    return $iv . $ciphertext;
}

function strtoAZaz09($string)
{

    // Gets rid of non-alphanumerics
    return preg_replace('/[^A-Za-z0-9_-]/', '', $string);
}

// Cross-Site Request Forgery check for sensitive functions
// Validates the CSRF token provided matches the one in the users session
function validateCSRFToken($token)
{
    if (hash_equals($token, $_SESSION['csrf_token'])) {
        return true;
    } else {
        $_SESSION['alert_type'] = "warning";
        $_SESSION['alert_message'] = "CSRF token verification failed. Try again, or log out to refresh your token.";
        header("Location: index.php");
        exit();
    }
}

/*
 * Role validation
 * Admin - 3
 * Tech - 2
 * Accountant - 1
 */

function validateAdminRole()
{
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 3) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
}

// Validates a user is a tech (or admin). Stops page load and attempts to direct away from the page if not (i.e. user is an accountant)
function validateTechRole()
{
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 1) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
}

// Validates a user is an accountant (or admin). Stops page load and attempts to direct away from the page if not (i.e. user is a tech)
function validateAccountantRole()
{
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 2) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
}

function sanitizeInput($input)
{
    global $mysqli;

    // Remove HTML and PHP tags
    $input = strip_tags((string) $input);

    // Remove white space from beginning and end of input
    $input = trim($input);

    // Escape special characters
    $input = mysqli_real_escape_string($mysqli, $input);

    // Return sanitized input
    return $input;
}

function sanitizeForEmail($data)
{
    $sanitized = htmlspecialchars($data);
    $sanitized = strip_tags($sanitized);
    $sanitized = trim($sanitized);
    return $sanitized;
}

function isMobile()
{
    // Check if the user agent is a mobile device
    return preg_match('/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|opera mini|palm|phone|pie|tablet|up.browser|up.link|webos|wos)/i', $_SERVER['HTTP_USER_AGENT']);
}


