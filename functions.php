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

function keygen()
{
  $chars = "abcdefghijklmnopqrstuvwxyz";
  $chars .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  $chars .= "0123456789";
  while (1) {
    $key = '';
    srand((double) microtime() * 1000000);
    for ($i = 0; $i < 16; $i++) {
      $key .= substr($chars, (rand() % (strlen($chars))), 1);
    }
    break;
  }
  return $key;
}

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

function initials($str) {
  if(!empty($str)){
    $ret = '';
    foreach (explode(' ', $str) as $word)
      $ret .= strtoupper($word[0]);
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

function get_user_agent() {
  return $_SERVER['HTTP_USER_AGENT'];
}

function get_ip() {
   
  if(defined("CONST_GET_IP_METHOD")){
    if(CONST_GET_IP_METHOD == "HTTP_X_FORWARDED_FOR"){
      $ip = getenv('HTTP_X_FORWARDED_FOR');
    }
  
    else{
     
      $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER['REMOTE_ADDR'];
    }
  }
  else{
    $ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER['REMOTE_ADDR'];
  }

  return $ip;
}

function get_web_browser() {
  $user_agent = get_user_agent();
  $browser        =   "Unknown Browser";
  $browser_array  =   array(
    '/msie/i'       =>  'Internet Explorer',
    '/Trident/i'    =>  'Internet Explorer',
    '/firefox/i'    =>  'Firefox',
    '/safari/i'     =>  'Safari',
    '/chrome/i'     =>  'Chrome',
    '/edge/i'       =>  'Edge',
    '/opera/i'      =>  'Opera',
    '/netscape/i'   =>  'Netscape',
    '/maxthon/i'    =>  'Maxthon',
    '/konqueror/i'  =>  'Konqueror',
    '/ubrowser/i'   =>  'UC Browser',
  );
  foreach ($browser_array as $regex => $value) {
    if (preg_match($regex, $user_agent)) {
      $browser    =   $value;
    }
  }
  return $browser;
}

function get_os() {
  $user_agent = get_user_agent();
  $os_platform    =   "Unknown OS";
  $os_array       =   array(
    '/windows nt 10/i'      =>  'Windows 10',
    '/windows nt 6.3/i'     =>  'Windows 8.1',
    '/windows nt 6.2/i'     =>  'Windows 8',
    '/windows nt 6.1/i'     =>  'Windows 7',
    '/windows nt 6.0/i'     =>  'Windows Vista',
    '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
    '/windows nt 5.1/i'     =>  'Windows XP',
    '/windows xp/i'         =>  'Windows XP',
    '/macintosh|mac os x/i' =>  'MacOS',
    '/linux/i'              =>  'Linux',
    '/ubuntu/i'             =>  'Ubuntu',
    '/iphone/i'             =>  'iPhone',
    '/ipod/i'               =>  'iPod',
    '/ipad/i'               =>  'iPad',
    '/android/i'            =>  'Android',
    '/blackberry/i'         =>  'BlackBerry',
    '/webos/i'              =>  'Mobile'
  );
  foreach ($os_array as $regex => $value) {
    if (preg_match($regex, $user_agent)) {
      $os_platform    =   $value;
    }
  }
  return $os_platform;
}

function get_device(){
  $tablet_browser = 0;
  $mobile_browser = 0;
  if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
    $tablet_browser++;
  }
  if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
    $mobile_browser++;
  }
  if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') > 0) || ((isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])))) {
    $mobile_browser++;
  }
  $mobile_ua = strtolower(substr(get_user_agent(), 0, 4));
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
  if (in_array($mobile_ua,$mobile_agents)) {
    $mobile_browser++;
  }
  if (strpos(strtolower(get_user_agent()),'opera mini') > 0) {
    $mobile_browser++;
    //Check for tablets on opera mini alternative headers
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
  $text = substr($text,0,$chars);
  $text = substr($text,0,strrpos($text,' '));
  $text = $text."...";
  return $text;
}

function formatPhoneNumber($phoneNumber) {
  $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber);

  if(strlen($phoneNumber) > 10) {
    $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
    $areaCode = substr($phoneNumber, -10, 3);
    $nextThree = substr($phoneNumber, -7, 3);
    $lastFour = substr($phoneNumber, -4, 4);

    $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
  }
  else if(strlen($phoneNumber) == 10) {
    $areaCode = substr($phoneNumber, 0, 3);
    $nextThree = substr($phoneNumber, 3, 3);
    $lastFour = substr($phoneNumber, 6, 4);

    $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
  }
  else if(strlen($phoneNumber) == 7) {
    $nextThree = substr($phoneNumber, 0, 3);
    $lastFour = substr($phoneNumber, 3, 4);

    $phoneNumber = $nextThree.'-'.$lastFour;
  }

  return $phoneNumber;
}

function mkdir_missing($dir) {
  if (!is_dir($dir)) {
    mkdir($dir);
  }
}

// Called during initial setup
// Encrypts the master key with the user's password
function setupFirstUserSpecificKey($user_password, $site_encryption_master_key){
  $iv = bin2hex(random_bytes(8));
  $salt = bin2hex(random_bytes(8));

  //Generate 128-bit (16 byte/char) kdhash of the users password
  $user_password_kdhash = hash_pbkdf2('sha256', $user_password, $salt, 100000, 16);

  //Encrypt the master key with the users kdf'd hash and the IV
  $ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_password_kdhash, 0, $iv);

  $user_encryption_ciphertext = $salt . $iv . $ciphertext;

  return $user_encryption_ciphertext;
}

/*
 * For additional users / password changes
 * New Users: Requires the admin setting up their account have a Specific/Session key configured
 * Password Changes: Will use the current info in the session.
*/
function encryptUserSpecificKey($user_password){
  $iv = bin2hex(random_bytes(8));
  $salt = bin2hex(random_bytes(8));

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

  $user_encryption_ciphertext = $salt . $iv . $ciphertext;

  return $user_encryption_ciphertext;

}

// Given a ciphertext (incl. IV) and the user's password, returns the site master key
// Ran at login, to facilitate generateUserSessionKey
function decryptUserSpecificKey($user_encryption_ciphertext, $user_password){
  //Get the IV, salt and ciphertext
  $salt = substr($user_encryption_ciphertext, 0, 16);
  $iv = substr($user_encryption_ciphertext, 16, 16);
  $ciphertext = substr($user_encryption_ciphertext, 32);

  //Generate 128-bit (16 byte/char) kdhash of the users password
  $user_password_kdhash = hash_pbkdf2('sha256', $user_password, $salt, 100000, 16);

  //Use this hash to get the original/master key
  $site_encryption_master_key = openssl_decrypt($ciphertext, 'aes-128-cbc', $user_password_kdhash, 0, $iv);
  return $site_encryption_master_key;
}

/*
Generates what is probably best described as a session key (ephemeral-ish)
- Allows us to store the master key on the server whilst the user is using the application, without prompting to type their password everytime they want to decrypt a credential
- Ciphertext/IV is stored on the server in the users session, encryption key is controlled/provided by the user as a cookie
- Only the user can decrypt their session ciphertext to get the master key
- Encryption key never hits the disk in cleartext
*/
function generateUserSessionKey($site_encryption_master_key){

  // Generate both of these using bin2hex(random_bytes(8))
  $user_encryption_session_key = bin2hex(random_bytes(8));
  $user_encryption_session_iv = bin2hex(random_bytes(8));
  $user_encryption_session_ciphertext = openssl_encrypt($site_encryption_master_key, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

  // Store ciphertext in the user's session
  $_SESSION['user_encryption_session_ciphertext'] = $user_encryption_session_ciphertext;
  $_SESSION['user_encryption_session_iv'] = $user_encryption_session_iv;

  // Give the user "their" key as a cookie
  include('config.php');
  if($config_https_only){
    setcookie("user_encryption_session_key", "$user_encryption_session_key", ['path' => '/','secure' => true,'httponly' => true,'samesite' => 'None']);
  } else{
    setcookie("user_encryption_session_key", $user_encryption_session_key, 0, "/");
    $_SESSION['alert_message'] = "Unencrypted connection flag set: Using non-secure cookies.";
  }
}

// Decrypts an encrypted password (website/asset login), returns it as a string
function decryptLoginEntry($login_password_ciphertext){

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
  $login_password_cleartext = openssl_decrypt($login_ciphertext, 'aes-128-cbc', $site_encryption_master_key, 0, $login_iv);
  return $login_password_cleartext;

}

// Encrypts a website/asset login password
function encryptLoginEntry($login_password_cleartext){
  $iv = bin2hex(random_bytes(8));

  // Get the user session info.
  $user_encryption_session_ciphertext = $_SESSION['user_encryption_session_ciphertext'];
  $user_encryption_session_iv =  $_SESSION['user_encryption_session_iv'];
  $user_encryption_session_key = $_COOKIE['user_encryption_session_key'];

  //Decrypt the session key to get the master key
  $site_encryption_master_key = openssl_decrypt($user_encryption_session_ciphertext, 'aes-128-cbc', $user_encryption_session_key, 0, $user_encryption_session_iv);

  //Encrypt the website/asset login using the master key
  $ciphertext = openssl_encrypt($login_password_cleartext, 'aes-128-cbc', $site_encryption_master_key, 0, $iv);

  $login_password_ciphertext = $iv . $ciphertext;
  return $login_password_ciphertext;
}

// Get domain expiration date
function getDomainExpirationDate($name){

  // Only run if we think the domain is valid
  if(!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    return '0000-00-00';
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "http://lookup.itflow.org:8080/$name");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
  $response = json_decode(curl_exec($ch),1);

  if($response){
    if(is_array($response['expiration_date'])){
      $expiry = new DateTime($response['expiration_date'][1]);
    }
    else{
      $expiry = new DateTime($response['expiration_date']);
    }

    return $expiry->format('Y-m-d');
  }

  // Default return
  return '0000-00-00';
}

// Get domain general info (whois + NS/A/MX records)
function getDomainRecords($name){

  $records = array();

  // Only run if we think the domain is valid
  if(!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    $records['a'] = '';
    $records['ns'] = '';
    $records['mx'] = '';
    $records['whois'] = '';
    return $records;
  }

  $domain = escapeshellarg($name);
  $records['a'] = substr(trim(strip_tags(shell_exec("dig +short $domain"))), 0, 254);
  $records['ns'] = substr(trim(strip_tags(shell_exec("dig +short NS $domain"))), 0, 254);
  $records['mx'] = substr(trim(strip_tags(shell_exec("dig +short MX $domain"))), 0, 254);
  $records['txt'] = substr(trim(strip_tags(shell_exec("dig +short TXT $domain"))), 0, 254);
  $records['whois'] = substr(trim(strip_tags(shell_exec("whois -H $domain | sed 's/   //g' | head -30"))), 0, 254);

  return $records;
}

// Used to automatically attempt to get SSL certificates as part of adding domains
// The logic for the fetch (sync) button on the client_certificates page is in ajax.php, and allows ports other than 443
function getSSL($name){

  $certificate = array();
  $certificate['success'] = FALSE;

  // Only run if we think the domain is valid
  if(!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    $certificate['expire'] = '';
    $certificate['issued_by'] = '';
    $certificate['public_key'] = '';
    return $certificate;
  }

  // Get SSL/TSL certificate (using verify peer false to allow for self-signed certs) for domain on default port
  $socket = "ssl://$name:443";
  $get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE, "verify_peer" => FALSE,)));
  $read = stream_socket_client($socket, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $get);

  // If the socket connected
  if($read){
    $cert = stream_context_get_params($read);
    $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

    if($cert_public_key_obj){
      $certificate['success'] = TRUE;
      $certificate['expire'] = date('Y-m-d', $cert_public_key_obj['validTo_time_t']);
      $certificate['issued_by'] = strip_tags($cert_public_key_obj['issuer']['O']);
      $certificate['public_key'] = $export;
    }
  }

  return $certificate;
}

function strto_AZaz09($string){
  $string = ucwords(strtolower($string));
  
  // Replace spaces with _
  //$string = str_replace(' ', '_', $string);

  // Gets rid of non-alphanumerics
  $strto_AZaz09 = preg_replace( '/[^A-Za-z0-9_]/', '', $string );

  return $strto_AZaz09;
}

// Cross-Site Request Forgery check for sensitive functions
// Validates the CSRF token provided matches the one in the users session
function validateCSRFToken($token){
  if(hash_equals($token, $_SESSION['csrf_token'])){
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

function validateAdminRole(){
  if(!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 3){
    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit();
  }
}

function validateTechRole(){
  if(!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 1){
    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit();
  }
}

function validateAccountantRole(){
  if(!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 2){
    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit();
  }
}

// Send a single email to a single recipient
function sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port, $from_email, $from_name, $to_email, $to_name, $subject, $body){

  $mail = new PHPMailer(true);

  try{
    // Mail Server Settings
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
    $mail->isHTML(true);                                  // Set email format to HTML
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

  catch(Exception $e){
    // If we couldn't send the message return the error so we can log it
    return "Message not sent. Mailer Error: {$mail->ErrorInfo}";
  }
}

?>
