<?php

// Role check failed wording
DEFINE("WORDING_ROLECHECK_FAILED", "You are not permitted to do that!");

// PHP Mailer Libs
require_once("plugins/PHPMailer/src/Exception.php");
require_once("plugins/PHPMailer/src/PHPMailer.php");
require_once("plugins/PHPMailer/src/SMTP.php");
// Initiate PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to generate both crypto & URL safe random strings
function randomString($length = 16)
{
    // Generate some cryptographically safe random bytes
    //  Generate a little more than requested as we'll lose some later converting
    $random_bytes = random_bytes($length + 5);

    // Convert the bytes to something somewhat human-readable
    $random_base_64 = base64_encode($random_bytes);

    // Replace the nasty characters that come with base64
    $bad_chars = array("/", "+", "=");
    $random_string = str_replace($bad_chars, random_int(0, 9), $random_base_64);

    // Truncate the string to the requested $length and return
    return substr($random_string, 0, $length);
}

// Older keygen function - only used for TOTP currently
function key32gen()
{
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $chars .= "234567";
    while (1) {
        $key = '';
        srand((double) microtime() * 1000000);
        for ($i = 0; $i < 32; $i++) {
            $key .= substr($chars, (rand() % (strlen($chars))), 1);
        }
        break;
    }
    return $key;
}

function nullable_htmlentities($unsanitizedInput) {
    return nullable_htmlentities($unsanitizedInput ?? '');
}

function initials($str) {
    if (!empty($str)) {
        $ret = '';
        foreach (explode(' ', $str) as $word)
            $ret .= strtoupper($word[0]);
        $ret = substr($ret, 0, 2);
        return $ret;
    }
}

function removeDirectory($path) {
    if (!file_exists($path)) {
        return;
    }

    $files = glob($path . '/*');
    foreach ($files as $file) {
        is_dir($file) ? removeDirectory($file) : unlink($file);
    }
    rmdir($path);
}

function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'];
}

function getIP() {
    if (defined("CONST_GET_IP_METHOD")) {
        if (CONST_GET_IP_METHOD == "HTTP_X_FORWARDED_FOR") {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } else {
            $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER['REMOTE_ADDR'];
        }
    } else {
        $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
}

function getWebBrowser($user_browser) {
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

function getOS($user_os) {
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

function getDevice() {
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
        'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
        'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
        'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
        'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
        'newt','noki','palm','pana','pant','phil','play','port','prox',
        'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
        'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
        'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
        'wapr','webc','winw','winw','xda ','xda-');
    if (in_array($mobile_ua, $mobile_agents)) {
        $mobile_browser++;
    }
    if (strpos(strtolower(getUserAgent()), 'opera mini') > 0) {
        $mobile_browser++;
        //Check for tablets on Opera Mini alternative headers
        $stock_ua = strtolower(isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA'])?$_SERVER['HTTP_X_OPERAMINI_PHONE_UA']:(isset($_SERVER['HTTP_DEVICE_STOCK_UA'])?$_SERVER['HTTP_DEVICE_STOCK_UA']:''));
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $stock_ua)) {
            $tablet_browser++;
        }
    }
    if ($tablet_browser > 0) {
        //do something for tablet devices
        return 'Tablet';
    }
    else if ($mobile_browser > 0) {
        //do something for mobile devices
        return 'Mobile';
    }
    else {
        //do something for everything else
        return 'Computer';
    }
}

function truncate($text, $chars) {
    if (strlen($text) <= $chars) {
        return $text;
    }
    $text = $text." ";
    $text = substr($text, 0, $chars);
    $text = substr($text, 0, strrpos($text, ' '));
    return $text."...";
}

function formatPhoneNumber($phoneNumber) {
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

    if (strlen($phoneNumber) > 10) {
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);

        $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }
    else if (strlen($phoneNumber) == 10) {
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);

        $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }
    else if (strlen($phoneNumber) == 7) {
        $nextThree = substr($phoneNumber, 0, 3);
        $lastFour = substr($phoneNumber, 3, 4);

        $phoneNumber = $nextThree.'-'.$lastFour;
    }

    return $phoneNumber;
}

function mkdirMissing($dir) {
    if (!is_dir($dir)) {
        mkdir($dir);
    }
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
    include('config.php');
    if ($config_https_only) {
        setcookie("user_encryption_session_key", "$user_encryption_session_key", ['path' => '/','secure' => true,'httponly' => true,'samesite' => 'None']);
    } else{
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

// Get domain expiration date
function getDomainExpirationDate($name) {

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        return "NULL";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://lookup.itflow.org:8080/$name");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = json_decode(curl_exec($ch), 1);

    if ($response) {
        if (is_array($response['expiration_date'])) {
            $expiry = new DateTime($response['expiration_date'][1]);
        }
        else{
            $expiry = new DateTime($response['expiration_date']);
        }

        return $expiry->format('Y-m-d');
    }

    // Default return
    return "NULL";
}

// Get domain general info (whois + NS/A/MX records)
function getDomainRecords($name) {

    $records = array();

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $records['a'] = '';
        $records['ns'] = '';
        $records['mx'] = '';
        $records['whois'] = '';
        return $records;
    }

    $domain = escapeshellarg(str_replace('www.', '', $name));
    $records['a'] = substr(trim(strip_tags(shell_exec("dig +short $domain"))), 0, 254);
    $records['ns'] = substr(trim(strip_tags(shell_exec("dig +short NS $domain"))), 0, 254);
    $records['mx'] = substr(trim(strip_tags(shell_exec("dig +short MX $domain"))), 0, 254);
    $records['txt'] = substr(trim(strip_tags(shell_exec("dig +short TXT $domain"))), 0, 254);
    $records['whois'] = substr(trim(strip_tags(shell_exec("whois -H $domain | sed 's/   //g' | head -30"))), 0, 254);

    return $records;
}

// Used to automatically attempt to get SSL certificates as part of adding domains
// The logic for the fetch (sync) button on the client_certificates page is in ajax.php, and allows ports other than 443
function getSSL($name) {

    $certificate = array();
    $certificate['success'] = false;

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $certificate['expire'] = '';
        $certificate['issued_by'] = '';
        $certificate['public_key'] = '';
        return $certificate;
    }

    // Get SSL/TSL certificate (using verify peer false to allow for self-signed certs) for domain on default port
    $socket = "ssl://$name:443";
    $get = stream_context_create(array("ssl" => array("capture_peer_cert" => true, "verify_peer" => false,)));
    $read = stream_socket_client($socket, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $get);

    // If the socket connected
    if ($read) {
        $cert = stream_context_get_params($read);
        $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
        openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

        if ($cert_public_key_obj) {
            $certificate['success'] = true;
            $certificate['expire'] = date('Y-m-d', $cert_public_key_obj['validTo_time_t']);
            $certificate['issued_by'] = strip_tags($cert_public_key_obj['issuer']['O']);
            $certificate['public_key'] = $export;
        }
    }

    return $certificate;
}

function strtoAZaz09($string) {

    // Gets rid of non-alphanumerics
    return preg_replace('/[^A-Za-z0-9_-]/', '', $string);
}

// Cross-Site Request Forgery check for sensitive functions
// Validates the CSRF token provided matches the one in the users session
function validateCSRFToken($token) {
    if (hash_equals($token, $_SESSION['csrf_token'])) {
        return true;
    }
    else{
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

function validateAdminRole() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 3) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
}

// Validates a user is a tech (or admin). Stops page load and attempts to direct away from the page if not (i.e. user is an accountant)
function validateTechRole() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 1) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
}

// Validates a user is an accountant (or admin). Stops page load and attempts to direct away from the page if not (i.e. user is a tech)
function validateAccountantRole() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 2) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
}

// Send a single email to a single recipient
function sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port, $from_email, $from_name, $to_email, $to_name, $subject, $body) {

    $mail = new PHPMailer(true);

    try{
        // Mail Server Settings
        $mail->CharSet = "UTF-8";                                   // Specify UTF-8 charset to ensure symbols ($/£) load correctly
        $mail->SMTPDebug = 0;                                       // No Debugging
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;                      // Specify SMTP server
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                  // SMTP username
        $mail->Password   = $config_smtp_password;                  // SMTP password
        $mail->SMTPSecure = $config_smtp_encryption;                // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                      // TCP port to connect to

        //Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress("$to_email", "$to_name");    // Add a recipient

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = "$subject";                                // Subject
        $mail->Body    = "$body";                                   // Content

        // Attachments - todo
        //$mail->addAttachment('/var/tmp/file.tar.gz');             // Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');        // Optional name

        // Send
        $mail->send();

        // Return true if this was successful
        return true;
    }

    catch(Exception $e) {
        // If we couldn't send the message return the error, so we can log it in the database (truncated)
        error_log("ITFlow - Failed to send email: " . $mail->ErrorInfo);
        return substr("Mailer Error: $mail->ErrorInfo", 0, 150)."...";
    }
}

function roundUpToNearestMultiple($n, $increment = 1000)
{
    return (int) ($increment * ceil($n / $increment));
}

function getAssetIcon($asset_type)
{
    if ($asset_type == 'Laptop') {
        $device_icon = "laptop";
    } elseif ($asset_type == 'Desktop') {
        $device_icon = "desktop";
    } elseif ($asset_type == 'Server') {
        $device_icon = "server";
    } elseif ($asset_type == 'Printer') {
        $device_icon = "print";
    } elseif ($asset_type == 'Camera') {
        $device_icon = "video";
    } elseif ($asset_type == 'Switch' || $asset_type == 'Firewall/Router') {
        $device_icon = "network-wired";
    } elseif ($asset_type == 'Access Point') {
        $device_icon = "wifi";
    } elseif ($asset_type == 'Phone') {
        $device_icon = "phone";
    } elseif ($asset_type == 'Mobile Phone') {
        $device_icon = "mobile-alt";
    } elseif ($asset_type == 'Tablet') {
        $device_icon = "tablet-alt";
    } elseif ($asset_type == 'TV') {
        $device_icon = "tv";
    } elseif ($asset_type == 'Virtual Machine') {
        $device_icon = "cloud";
    } else {
        $device_icon = "tag";
    }

    return $device_icon;
}

function getInvoiceBadgeColor($invoice_status)
{
    if ($invoice_status == "Sent") {
        $invoice_badge_color = "warning text-white";
    } elseif ($invoice_status == "Viewed") {
        $invoice_badge_color = "info";
    } elseif ($invoice_status == "Partial") {
        $invoice_badge_color = "primary";
    } elseif ($invoice_status == "Paid") {
        $invoice_badge_color = "success";
    } elseif ($invoice_status == "Cancelled") {
        $invoice_badge_color = "danger";
    } else{
        $invoice_badge_color = "secondary";
    }

    return $invoice_badge_color;
}

// Pass $_FILE['file'] to check an uploaded file before saving it
function checkFileUpload($file, $allowed_extensions) {
    // Variables
    $name = $file['name'];
    $tmp = $file['tmp_name'];
    $size = $file['size'];

    $extarr = explode('.', $name);
    $extension = strtolower(end($extarr));

    // Check a file is actually attached/uploaded
    if ($tmp == '') {
        return false;
    }

    // Check the size is under 20 MB
    if ($size > 20 * 1024 * 1024) {
        return false;
    }

    // Check the extension is allowed
    if (in_array($extension, $allowed_extensions) === false){
        return false;
    }

    // Sanitize & return name
    return md5(time() . $name) . '.' . $extension;

}

function sanitizeInput($input) {
    global $mysqli;

    // Remove HTML and PHP tags
    $input = strip_tags($input);

    // Remove white space from beginning and end of input
    $input = trim($input);

    // Escape special characters
    $input = mysqli_real_escape_string($mysqli, $input);

    // Return sanitized input
    return $input;
}

function sanitizeForEmail($data) {
  $sanitized = htmlspecialchars($data);
  $sanitized = strip_tags($sanitized);
  $sanitized = trim($sanitized);
  return $sanitized;
}
