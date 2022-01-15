<?php
/*

// Headers to allow extensions access (CORS)
$chrome_id = "chrome-extension://afgpakhonllnmnomchjhidealcpmnegc";
$firefox_id = "moz-extension://857479e9-3992-4e99-9a5e-b514d2ad0a82";

if (isset($_SERVER['HTTP_ORIGIN'])) {
    if($_SERVER['HTTP_ORIGIN'] == $chrome_id OR $_SERVER['HTTP_ORIGIN'] == $firefox_id){
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
    }
}
// Additionally, will require cookies set to SameSite None.

include("config.php");
include("functions.php");

session_start();

// Check user is logged in
// We do this manually, using check_login will break CORS due to the redirect.
if(!(isset($_SESSION['logged']))){
    $data['found'] = "FALSE";
    $data['message'] = "ITFlow - You are not logged into ITFlow.";
    echo(json_encode($data));
    exit();
}

// User is logged in!

// Get user info:
$session_user_id = $_SESSION['user_id'];

$sql = mysqli_query($mysqli,"SELECT * FROM users, user_settings WHERE users.user_id = user_settings.user_id AND users.user_id = $session_user_id");
$row = mysqli_fetch_array($sql);
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

// Check user access level
if($session_user_role < 4){
    $data['found'] = "FALSE";
    $data['message'] = "ITFlow - You are not authorised to use this application.";
    echo(json_encode($data));
    exit();
}

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
        }
    }
}

//TODO: Future work:-
// - Check user has permission to this client
// - Showing multiple logins for a single URL