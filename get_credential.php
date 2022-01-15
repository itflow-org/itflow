<?php

// Headers to allow extensions access (CORS)
$chrome_id = "chrome-extension://afgpakhonllnmnomchjhidealcpmnegc";
$firefox_id = "moz-extension://857479e9-3992-4e99-9a5e-b514d2ad0a82";

if (isset($_SERVER['HTTP_ORIGIN'])) {
    if($_SERVER['HTTP_ORIGIN'] == $chrome_id OR $_SERVER['HTTP_ORIGIN'] == $firefox_id){
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
    }
}

include("config.php");
include("functions.php");

//SESSION FINGERPRINT
$ip = strip_tags(mysqli_real_escape_string($mysqli,get_ip()));
$os = strip_tags(mysqli_real_escape_string($mysqli,get_os()));
$browser = strip_tags(mysqli_real_escape_string($mysqli,get_web_browser()));
$user_agent = "$os - $browser";

// Check user is logged in & has extension access
// We're not using the PHP session as we don't want to potentially expose the session cookie with SameSite None
if(!isset($_COOKIE['user_extension_key'])){
    $data['found'] = "FALSE";
    $data['message'] = "ITFlow - You are not logged into ITFlow, do not have, or did not send the correct extension key cookie.";
    echo(json_encode($data));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW()");

    exit();
}

// User has a cookie set with that name, let's verify it.
$user_extension_key = $_COOKIE['user_extension_key'];

// Check the key isn't empty, less than 17 characters or the word "disabled".
if(empty($user_extension_key) OR strlen($user_extension_key) < 16 OR strtolower($user_extension_key) == "disabled"){
    $data['found'] = "FALSE";
    $data['message'] = "ITFlow - You are not logged into ITFlow, do not have, or did not send the correct extension key cookie.";
    echo(json_encode($data));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW()");

    exit();
}


// Cookie seems valid, see if we can associate it with a user ID
$user_extension_key = mysqli_real_escape_string($mysqli, $_COOKIE['user_extension_key']);
$auth_user = mysqli_query($mysqli, "SELECT * FROM users LEFT JOIN user_settings on users.user_id = user_settings.user_id WHERE user_extension_key = '$user_extension_key' LIMIT 1");
$row = mysqli_fetch_array($auth_user);

// Check SQL query state
if(mysqli_num_rows($auth_user) < 1 OR !$auth_user){
    $data['found'] = "FALSE";
    $data['message'] = "ITFlow - You are not logged into ITFlow, do not have, or did not send the correct extension key cookie.";
    echo(json_encode($data));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW()");

    exit();
}

// Sanity check
if(hash('sha256', $row['user_extension_key']) !== hash('sha256', $_COOKIE['user_extension_key'])){
    $data['found'] = "FALSE";
    $data['message'] = "ITFlow - Validation failed.";
    echo(json_encode($data));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = 'Failed login attempt using extension (get_credential.php)', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW()");

    exit();
}

// Success - validated user cookie

// Get the current session from the database so we can decrypt passwords
session_id($row['user_php_session']);
session_start();

$session_user_id = $row['user_id'];
$session_name = $row['user_name'];
$session_email = $row['user_email'];
$session_avatar = $row['user_avatar'];
$session_token = $row['user_token'];
$session_company_id = $row['user_default_company'];
$session_user_role = $row['user_role'];
if($session_user_role == 6){
    $session_user_role_display = "Global Administrator";
}elseif($session_user_role == 5){
    $session_user_role_display = "Administrator";
}elseif($session_user_role == 4){
    $session_user_role_display = "Technician";
}elseif($session_user_role == 3){
    $session_user_role_display = "IT Contractor";
}elseif($session_user_role == 2){
    $session_user_role_display = "Client";
}else{
    $session_user_role_display = "Accountant";
}

// Check user access level is correct
if($session_user_role < 4){
    $data['found'] = "FALSE";
    $data['message'] = "ITFlow - You are not authorised to use this application.";
    echo(json_encode($data));

    //Logging
    $user_name = mysqli_real_escape_string($mysqli, $session_name);
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Extension Failed', log_description = '$user_name not authorised to use extension', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW(), log_user_id = $session_user_id");

    exit();
}

// Lets go!

if(isset($_GET['host'])){

    if(!empty($_GET['host'])){
        $url = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['host'])));

        $sql_logins = mysqli_query($mysqli, "SELECT * FROM logins WHERE (login_uri = '$url' AND company_id = '$session_company_id') LIMIT 1");

        if(mysqli_num_rows($sql_logins) > 0){
            $row = mysqli_fetch_array($sql_logins);
            $data['found'] = "TRUE";
            $data['username'] = htmlentities($row['login_username']);
            $data['password'] = decryptLoginEntry($row['login_password']);
            echo json_encode($data);

            // Logging
            $login_name = mysqli_real_escape_string($mysqli, $row['login_name']);
            $login_user = mysqli_real_escape_string($mysqli, $row['login_username']);
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Login', log_action = 'Extension requested', log_description = 'Credential $login_name, username $login_user' , log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

        }
    }
}

//TODO: Future work:-
// - Check user has permission to this client
// - Showing multiple logins for a single URL