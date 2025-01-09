<?php

/*
 * ITFlow - GET/POST request handler for user profiles (tech/agent)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_your_user_details'])) {

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);

    $sql = mysqli_query($mysqli,"SELECT user_avatar FROM users WHERE user_id = $session_user_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['user_avatar']);

    $logout = false;
    $extended_log_description = '';

    // Email notification when password or email is changed
    $user_old_email_sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_email FROM users WHERE user_id = $session_user_id"));
    $user_old_email = sanitizeInput($user_old_email_sql['user_email']);

    // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
    $config_mail_from_name = sanitizeInput($config_mail_from_name);
    $config_mail_from_email = sanitizeInput($config_mail_from_email);
    $config_app_name = sanitizeInput($config_app_name);

    if (!empty($config_smtp_host) && ($user_old_email !== $email)) {

        $details = "Your email address was changed. New email: $email.";

        $subject = "$config_app_name account update confirmation for $name";
        $body = "Hi $name, <br><br>Your $config_app_name account has been updated, details below: <br><br> <b>$details</b> <br><br> If you did not perform this change, contact your $config_app_name administrator immediately. <br><br>Thanks, <br>ITFlow<br>$session_company_name";

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $user_old_email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body' => $body
            ]
        ];
        $mail = addToMailQueue($mysqli, $data);
    }

    // Photo
    if (isset($_FILES['avatar']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['avatar'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            $file_tmp_path = $_FILES['avatar']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/users/$session_user_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            // Delete old file
            unlink("uploads/users/$session_user_id/$existing_file_name");

            // Set Avatar
            mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $session_user_id");

            // Extended Logging
            $extended_log_description .= ", avatar updated";

        }
    }

    mysqli_query($mysqli,"UPDATE users SET user_name = '$name', user_email = '$email' WHERE user_id = $session_user_id");

    //Logging
    logAction("User Account", "Edit", "$session_name edited their account $extended_log_description");

    $_SESSION['alert_message'] = "User details updated";

    if ($logout) {
        header('Location: post.php?logout');
    }
    else{
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['clear_your_user_avatar'])) {
    validateCSRFToken($_GET['csrf_token']);

    mysqli_query($mysqli,"UPDATE users SET user_avatar = NULL WHERE user_id = $session_user_id");

    logAction("User Account", "Edit", "$session_name cleared their avatar");

    $_SESSION['alert_message'] = "Avatar cleared";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_your_user_password'])) {

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $new_password = trim($_POST['new_password']);

    if (empty($new_password)) {
        header('Location: user_security.php');
        exit;
    }

    // Email notification when password or email is changed
    $user_sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = $session_user_id"));
    $name = sanitizeInput($user_sql['user_name']);
    $user_email = sanitizeInput($user_sql['user_email']);

    // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
    $config_mail_from_name = sanitizeInput($config_mail_from_name);
    $config_mail_from_email = sanitizeInput($config_mail_from_email);
    $config_app_name = sanitizeInput($config_app_name);

    if (!empty($config_smtp_host)){

        $details = "Your password was changed.";

        $subject = "$config_app_name account update confirmation for $name";
        $body = "Hi $name, <br><br>Your $config_app_name account has been updated, details below: <br><br> <b>$details</b> <br><br> If you did not perform this change, contact your $config_app_name administrator immediately. <br><br>Thanks, <br>$config_app_name";

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $user_email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body' => $body
            ]
        ];
        $mail = addToMailQueue($mysqli, $data);
    }

    $new_password = password_hash($new_password, PASSWORD_DEFAULT);
    $user_specific_encryption_ciphertext = encryptUserSpecificKey($_POST['new_password']);
    mysqli_query($mysqli,"UPDATE users SET user_password = '$new_password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext' WHERE user_id = $session_user_id");

    // Logging
    logAction("User Account", "Edit", "$session_name changed their password");

    $_SESSION['alert_message'] = "Your password was updated";

    header('Location: post.php?logout');
}

if (isset($_POST['edit_your_user_preferences'])) {

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $calendar_first_day = intval($_POST['calendar_first_day']);

    // Calendar
    if (isset($calendar_first_day)) {
        mysqli_query($mysqli, "UPDATE user_settings SET user_config_calendar_first_day = $calendar_first_day WHERE user_id = $session_user_id");
    }

    // Enable extension access, only if it isn't already setup (user doesn't have cookie)
    if (isset($_POST['extension']) && $_POST['extension'] == 'Yes') {
        if (!isset($_COOKIE['user_extension_key'])) {
            $extension_key = randomString(156);
            mysqli_query($mysqli, "UPDATE users SET user_extension_key = '$extension_key' WHERE user_id = $session_user_id");

            $extended_log_description .= "enabled browser extension access";
            $logout = true;
        }
    }

    // Disable extension access
    if (!isset($_POST['extension'])) {
        mysqli_query($mysqli, "UPDATE users SET user_extension_key = '' WHERE user_id = $session_user_id");
        $extended_log_description .= "disabled browser extension access";
    }

    // Logging
    logAction("User Account", "Edit", "$session_name $extended_log_description");

    $_SESSION['alert_message'] = "User preferences updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


if (isset($_POST['verify'])) {

    require_once "rfc6238.php";

    $currentcode = intval($_POST['code']);  //code to validate, for example received from device

    if (TokenAuth6238::verify($session_token, $currentcode)) {
        $_SESSION['alert_message'] = "VALID!";
    }else{
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "IN-VALID!";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['enable_2fa']) || isset($_GET['enable_2fa_force'])) {

    // CSRF Check
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCSRFToken($_POST['csrf_token']);

        $extended_log_description = "";
        $token = sanitizeInput($_POST['token']);
    } else {
        // If this is a GET request then we forced MFA as part of login
        validateCSRFToken($_GET['csrf_token']);

        $extended_log_description = "(forced)";
        $token = sanitizeInput($_GET['token']);
    }



    mysqli_query($mysqli,"UPDATE users SET user_token = '$token' WHERE user_id = $session_user_id");

    // Delete any existing 2FA tokens - these browsers should be re-validated
    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $session_user_id");

    // Logging
    logAction("User Account", "Edit", "$session_name enabled MFA on their account $extended_log_description");

    $_SESSION['alert_message'] = "Two-factor authentication enabled $extended_log_description";

    header("Location: user_security.php");

}

if (isset($_POST['disable_2fa'])){

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    mysqli_query($mysqli,"UPDATE users SET user_token = '' WHERE user_id = $session_user_id");

    // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
    $config_mail_from_name = sanitizeInput($config_mail_from_name);
    $config_mail_from_email = sanitizeInput($config_mail_from_email);
    $config_app_name = sanitizeInput($config_app_name);

    // Email notification
    if (!empty($config_smtp_host)) {
        $subject = "$config_app_name account update confirmation for $session_name";
        $body = "Hi $session_name, <br><br>Your $config_app_name account has been updated, details below: <br><br> <b>2FA was disabled.</b> <br><br> If you did not perform this change, contact your $config_app_name administrator immediately. <br><br>Thanks, <br>ITFlow<br>$session_company_name";

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $session_email,
                'recipient_name' => $session_name,
                'subject' => $subject,
                'body' => $body
            ]
            ];
        $mail = addToMailQueue($mysqli, $data);
    }

    // Logging
    logAction("User Account", "Edit", "$session_name disabled MFA on their account");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Two-factor authentication disabled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['revoke_your_2fa_remember_tokens'])) {

    // CSRF
    validateCSRFToken($_POST['csrf_token']);

    // Delete tokens
    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $session_user_id");

    //Logging
    logAction("User Account", "Edit", "$session_name revoked all their remember-me tokens");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Remember me tokens revoked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
