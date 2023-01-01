<?php

/*
 * ITFlow browser extension
 *
 * Fills login forms, matching on the site URL:
 * After installation and configuration of the extension, users can simply click the key to fill the form on the page
 * If the URL of the page matches a configured login URL in ITFlow, the username and password is filled.
 *
 * Technical details:-
 * First, review how ITFlow handles password encryption: https://itflow.org/docs.php?doc=logins
 * Users must enable the extension via their profile/settings.
 * An extension key is generated and stored in the users table, and provided to the user as a cookie every time they log in. Additionally, their PHP Session ID is also stored in the users table.
 * The extension passes this cookie on all requests it makes (to this page). We use the cookie/key to identify/verify the user.
 * We can then access the users PHP session data. This, alongside the user_encryption_session_key cookie they provide, allows login passwords to be decrypted.
 *
 */

// Headers to allow extensions access (CORS)
$chrome_id = "chrome-extension://afgpakhonllnmnomchjhidealcpmnegc";

if (isset($_SERVER['HTTP_ORIGIN'])) {
    if ($_SERVER['HTTP_ORIGIN'] == $chrome_id) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
    }
}

include_once("config.php");
include_once("functions.php");

// IP & User Agent for logging
$ip = strip_tags(mysqli_real_escape_string($mysqli,get_ip()));
$user_agent = strip_tags(mysqli_real_escape_string($mysqli,$_SERVER['HTTP_USER_AGENT']));

// Define wording for the user
DEFINE("WORDING_ROLECHECK_FAILED", "ITFlow - You are not permitted to use this application!");
DEFINE("WORDING_BAD_EXT_COOKIE_KEY", "ITFlow - You are not logged into ITFlow, do not have, or did not send the correct extension key cookie.");


// Check user is logged in & has extension access
// We're not using the PHP session as we don't want to potentially expose the session cookie with SameSite None
if (!isset($_COOKIE['user_extension_key'])) {
    $data['found'] = "FALSE";
    $data['message'] = WORDING_BAD_EXT_COOKIE_KEY;
    echo json_encode($data);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent'");

    exit();
}

// User has a cookie set with that name, let's verify it.
$user_extension_key = $_COOKIE['user_extension_key'];

// Check the key isn't empty, less than 17 characters or the word "disabled".
if (empty($user_extension_key) || strlen($user_extension_key) < 16 || strtolower($user_extension_key) == "disabled") {
    $data['found'] = "FALSE";
    $data['message'] = WORDING_BAD_EXT_COOKIE_KEY;
    echo json_encode($data);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent'");

    exit();
}


// Cookie seems valid, see if we can associate it with a user ID
$user_extension_key = mysqli_real_escape_string($mysqli, $_COOKIE['user_extension_key']);
$auth_user = mysqli_query($mysqli, "SELECT * FROM users LEFT JOIN user_settings on users.user_id = user_settings.user_id WHERE user_extension_key = '$user_extension_key' LIMIT 1");
$row = mysqli_fetch_array($auth_user);

// Check SQL query state
if (mysqli_num_rows($auth_user) < 1 || !$auth_user) {
    $data['found'] = "FALSE";
    $data['message'] = WORDING_BAD_EXT_COOKIE_KEY;
    echo json_encode($data);

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent'");

    exit();
}

// Sanity check
if (hash('sha256', $row['user_extension_key']) !== hash('sha256', $_COOKIE['user_extension_key'])) {
    $data['found'] = "FALSE";
    $data['message'] = WORDING_BAD_EXT_COOKIE_KEY;
    echo json_encode($data);

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent'");

    exit();
}

// Success - validated user cookie

// Get the current session from the database, so we can decrypt passwords
session_id($row['user_php_session']);
session_start();

$session_user_id = $row['user_id'];
$session_name = $row['user_name'];
$session_email = $row['user_email'];
$session_company_id = $row['user_default_company'];
$session_user_role = $row['user_role'];

// Check user access level is correct (not an accountant)
if ($session_user_role < 1) {
    $data['found'] = "FALSE";
    $data['message'] = WORDING_ROLECHECK_FAILED;
    echo json_encode($data);

    //Logging
    $user_name = mysqli_real_escape_string($mysqli, $session_name);
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = '$user_name not authorised to use extension', log_ip = '$ip', log_user_agent = '$user_agent', log_user_id = $session_user_id");

    exit();
}

// Lets go!

if (isset($_GET['host'])) {

    if (!empty($_GET['host'])) {
        $url = trim(strip_tags(mysqli_real_escape_string($mysqli, $_GET['host'])));

        $sql_logins = mysqli_query($mysqli, "SELECT * FROM logins WHERE (login_uri = '$url' AND company_id = '$session_company_id') LIMIT 1");

        if (mysqli_num_rows($sql_logins) > 0) {
            $row = mysqli_fetch_array($sql_logins);
            $data['found'] = "TRUE";
            $data['username'] = htmlentities($row['login_username']);
            $data['password'] = decryptLoginEntry($row['login_password']); // Uses the PHP Session info and the session key cookie
            echo json_encode($data);

            // Logging
            $login_name = mysqli_real_escape_string($mysqli, $row['login_name']);
            $login_user = mysqli_real_escape_string($mysqli, $row['login_username']);
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Login', log_action = 'Extension requested', log_description = 'Credential $login_name, username $login_user', log_ip = '$ip', log_user_agent = '$user_agent', company_id = $session_company_id, log_user_id = $session_user_id");

        }
    }
}

//TODO: Future work:-
// - Showing multiple logins for a single URL