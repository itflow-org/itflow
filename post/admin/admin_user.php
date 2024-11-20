<?php

/*
 * ITFlow - GET/POST request handler for user (agent) management
 */

if (isset($_POST['add_user'])) {

    validateCSRFToken($_POST['csrf_token']);

    require_once 'post/admin/admin_user_model.php';

    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $user_specific_encryption_ciphertext = encryptUserSpecificKey(trim($_POST['password']));

    mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext'");

    $user_id = mysqli_insert_id($mysqli);

    // Add Client Access Permissions if set
    if (isset($_POST['clients'])) {
        foreach($_POST['clients'] as $client_id) {
            $client_id = intval($client_id);
            mysqli_query($mysqli,"INSERT INTO user_permissions SET user_id = $user_id, client_id = $client_id");
        }
    }

    if (!file_exists("uploads/users/$user_id/")) {
        mkdir("uploads/users/$user_id");
    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/users/$user_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            // Set Avatar
            mysqli_query($mysqli, "UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");
            $extended_alert_description = '. File successfully uploaded.';
        }
    }

    // Create Settings
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_role = $role, user_config_force_mfa = $force_mfa");

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);

    // Sanitize Config vars from get_settings.php
    $config_mail_from_name = sanitizeInput($config_mail_from_name);
    $config_mail_from_email = sanitizeInput($config_mail_from_email);
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_login_key_secret = mysqli_real_escape_string($mysqli, $config_login_key_secret);
    $config_base_url = sanitizeInput($config_base_url);

    // Send user e-mail, if specified
    if (isset($_POST['send_email']) && !empty($config_smtp_host) && filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $password = mysqli_real_escape_string($mysqli, $_POST['password']);

        $subject = "Your new $company_name ITFlow account";
        $body = "Hello $name,<br><br>An ITFlow account has been setup for you. Please change your password upon login. <br><br>Username: $email <br>Password: $password<br>Login URL: https://$config_base_url/login.php?key=$config_login_key_secret<br><br>--<br>$company_name - Support<br>$config_ticket_from_email";

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body' => $body
            ]
        ];
        $mail = addToMailQueue($mysqli, $data);

        if ($mail !== true) {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $email'");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, log_entity_id = $user_id");
        }

    }

    // Logging
    logAction("User", "Create", "$session_name created user $name", 0, $user_id);

    $_SESSION['alert_message'] = "User <strong>$name</strong> created" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_user'])) {

    validateCSRFToken($_POST['csrf_token']);

    require_once 'post/admin/admin_user_model.php';

    $user_id = intval($_POST['user_id']);
    $new_password = trim($_POST['new_password']);

    // Update Client Access
    mysqli_query($mysqli,"DELETE FROM user_permissions WHERE user_id = $user_id");
    if (isset($_POST['clients'])) {
        foreach($_POST['clients'] as $client_id) {
            $client_id = intval($client_id);
            mysqli_query($mysqli,"INSERT INTO user_permissions SET user_id = $user_id, client_id = $client_id");
        }
    }

    // Get current Avatar
    $sql = mysqli_query($mysqli, "SELECT user_avatar FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['user_avatar']);

    $extended_log_description = '';
    if (!empty($_POST['2fa'])) {
        $two_fa = $_POST['2fa'];
    }

    if (!file_exists("uploads/users/$user_id/")) {
        mkdir("uploads/users/$user_id");
    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/users/$user_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            // Delete old file
            unlink("uploads/users/$user_id/$existing_file_name");

            // Set Avatar
            mysqli_query($mysqli, "UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");
            $extended_alert_description = '. File successfully uploaded.';
        
        }
    }

    mysqli_query($mysqli, "UPDATE users SET user_name = '$name', user_email = '$email' WHERE user_id = $user_id");

    if (!empty($new_password)) {
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_specific_encryption_ciphertext = encryptUserSpecificKey(trim($_POST['new_password']));
        mysqli_query($mysqli, "UPDATE users SET user_password = '$new_password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext' WHERE user_id = $user_id");
        //Extended Logging
        $extended_log_description .= ", password changed";
    }

    if (!empty($two_fa) && $two_fa == 'disable') {
        mysqli_query($mysqli, "UPDATE users SET user_token = '' WHERE user_id = '$user_id'");
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name disabled 2FA for $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
    }

    //Update User Settings
    mysqli_query($mysqli, "UPDATE user_settings SET user_role = $role, user_config_force_mfa = $force_mfa WHERE user_id = $user_id");

    // Logging
    logAction("User", "Edit", "$session_name edited user $name", 0, $user_id);

    $_SESSION['alert_message'] = "User <strong>$name</strong> updated" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['activate_user'])) {

    validateCSRFToken($_GET['csrf_token']);

    $user_id = intval($_GET['activate_user']);

    // Get User Name
    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $user_name = sanitizeInput($row['user_name']);

    mysqli_query($mysqli, "UPDATE users SET user_status = 1 WHERE user_id = $user_id");

    // Logging
    logAction("User", "Activate", "$session_name activated user $user_name", 0, $user_id);

    $_SESSION['alert_message'] = "User <strong>$user_name</strong> activated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['disable_user'])) {

    validateCSRFToken($_GET['csrf_token']);

    $user_id = intval($_GET['disable_user']);

    // Get User Name
    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $user_name = sanitizeInput($row['user_name']);

    mysqli_query($mysqli, "UPDATE users SET user_status = 0 WHERE user_id = $user_id");

    // Un-assign tickets
    mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = 0 WHERE ticket_assigned_to = $user_id AND ticket_closed_at IS NULL");
    mysqli_query($mysqli, "UPDATE scheduled_tickets SET scheduled_ticket_assigned_to = 0 WHERE scheduled_ticket_assigned_to = $user_id");

    // Logging
    logAction("User", "Disable", "$session_name disabled user $name", 0, $user_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "User <strong>$user_name</strong> disabled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['revoke_remember_me'])) {

    validateCSRFToken($_GET['csrf_token']);

    $user_id = intval($_GET['revoke_remember_me']);

    // Get User Name
    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $user_id"));
    $user_name = sanitizeInput($row['user_name']);

    mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $user_id");

    // Logging
    logAction("User", "Edit", "$session_name revoked all remember me tokens for user $user_name", 0, $user_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "User <strong>$user_name</strong> remember me tokens revoked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_user'])) {

    validateCSRFToken($_GET['csrf_token']);

    // Variables from GET
    $user_id = intval($_GET['archive_user']);
    $password = password_hash(randomString(), PASSWORD_DEFAULT);

    // Get user details
    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $name = sanitizeInput($row['user_name']);

    // Archive user query
    mysqli_query($mysqli, "UPDATE users SET user_name = '$name (archived)', user_password = '$password', user_status = 0, user_specific_encryption_ciphertext = '', user_archived_at = NOW() WHERE user_id = $user_id");

    // Logging
    logAction("User", "Archive", "$session_name archived user $name", 0, $user_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "User <strong>$name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['export_users_csv'])) {

    //get records from database
    $sql = mysqli_query($mysqli, "SELECT * FROM users ORDER BY user_name ASC");

    $count = mysqli_num_rows($sql);

    if ($count > 0) {
        $delimiter = ", ";
        $filename = $session_company_name . "-Users-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Email', 'Role', 'Status', 'Creation Date');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {

            $user_status = intval($row['user_status']);
            if ($user_status == 2) {
                $user_status_display = "Invited";
            } elseif ($user_status == 1) {
                $user_status_display = "Active";
            } else{
                $user_status_display = "Disabled";
            }
            $user_role = $row['user_role'];
            if ($user_role == 3) {
                $user_role_display = "Administrator";
            } elseif ($user_role == 2) {
                $user_role_display = "Technician";
            } else {
                $user_role_display = "Accountant";
            }

            $lineData = array($row['user_name'], $row['user_email'], $user_role_display, $user_status_display, $row['user_created_at']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);

        // Logging
        logAction("User", "Export", "$session_name exported $count user(s) to a CSV file");
    }
    exit;

}

if (isset($_POST['ir_reset_user_password'])) {

    // Incident response: allow mass reset of agent passwords

    validateCSRFToken($_POST['csrf_token']);

    // Confirm logged-in user password, for security
    $admin_password = $_POST['admin_password'];
    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $session_user_id");
    $userRow = mysqli_fetch_array($sql);
    if (!password_verify($admin_password, $userRow['user_password'])) {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Incorrect password.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
    }

    // Get agents/users, other than the current user
    $sql_users = mysqli_query($mysqli, "SELECT * FROM users WHERE (user_archived_at IS NULL AND user_id != $session_user_id)");

    // Reset passwords
    while ($row = mysqli_fetch_array($sql_users)) {
        $user_id = intval($row['user_id']);
        $user_email = sanitizeInput($row['user_email']);
        $new_password = randomString();
        $user_specific_encryption_ciphertext = encryptUserSpecificKey(trim($new_password));

        echo $user_email . " -- " . $new_password; // Show
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);

        mysqli_query($mysqli, "UPDATE users SET user_password = '$new_password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext' WHERE user_id = $user_id");

        echo "<br><br>";
    }

    // Logging
    logAction("User", "Edit", "$session_name reset ALL user passwords");

    exit; // Stay on the plain text password page

}
