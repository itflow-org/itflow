<?php

require_once("config.php");
require_once("functions.php");
require_once("check_login.php");

if(isset($_POST['change_records_per_page'])){

    $_SESSION['records_per_page'] = intval($_POST['change_records_per_page']);

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['switch_company'])){
    $company_id = intval($_GET['switch_company']);

    //Get Company Name
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);

    //Check to see if user has Permission to access the company
    if(in_array($company_id,$session_user_company_access_array)){

        mysqli_query($mysqli,"UPDATE user_settings SET user_default_company = $company_id WHERE user_id = $session_user_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Switched Companies. <a href='https://forum.itflow.org/d/74-removing-the-multi-company-feature' target='_blank'>Deprecated!</a>";

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Switch', log_description = '$session_name switched to company $company_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    }else{
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "You do not have permission to switch to this company";

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Switch', log_description = '$session_name attempted to switch to company $company_name but did not have permission', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
    }

    header("Location: dashboard_financial.php");

}

if(isset($_POST['add_user'])){

    require_once('models/user.php');

    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_specific_encryption_ciphertext = encryptUserSpecificKey($_POST['password']);

    mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext'");

    $user_id = mysqli_insert_id($mysqli);

    if(!file_exists("uploads/users/$user_id/")) {
        mkdir("uploads/users/$user_id");
    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/users/$user_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            // Set Avatar
            mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");
            $extended_alert_description = '. File successfully uploaded.';
        } else {
            $_SESSION['alert_type'] = "error";
            $extended_alert_description = '. Error uploading photo. Check upload directory is writable/correct file type/size';
        }
    }

    // Create Settings
    mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = $user_id, user_role = $role, user_default_company = $default_company");

    // Create Company Access Permissions
    mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $user_id, company_id = $default_company");

    // Send user e-mail, if specified
    if(isset($_POST['send_email']) && !empty($config_smtp_host) && filter_var($email, FILTER_VALIDATE_EMAIL)){

        $subject = "Your new $session_company_name ITFlow account";
        $body = "Hello, $name<br><br>An ITFlow account has been setup for you. Please change your password upon login. <br><br>Username: $email <br>Password: $_POST[password]<br>Login URL: https://$config_base_url<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_ticket_from_email, $config_ticket_from_name,
            $email, $name,
            $subject, $body);

        if ($mail !== true) {
            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $email', company_id = $session_company_id");
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, log_entity_id = $user_id, company_id = $session_company_id");
        }

    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Create', log_description = '$session_name created user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, log_entity_id = $user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "User <strong>$name</strong> created" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_user'])){

    require_once('models/user.php');

    validateAdminRole();

    validateCSRFToken($_POST['csrf_token']);

    $user_id = intval($_POST['user_id']);
    $new_password = trim($_POST['new_password']);

    $existing_file_name = sanitizeInput($_POST['existing_file_name']);
    $extended_log_description = '';
    if(!empty($_POST['2fa'])) {
        $two_fa = $_POST['2fa'];
    }

    if(!file_exists("uploads/users/$user_id/")) {
        mkdir("uploads/users/$user_id");
    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/users/$user_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            // Delete old file
            unlink("uploads/users/$user_id/$existing_file_name");

            // Set Avatar
            mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");
            $extended_alert_description = '. File successfully uploaded.';
        } else {
            $_SESSION['alert_type'] = "error";
            $extended_alert_description = '. Error uploading photo. Check upload directory is writable/correct file type/size';
        }
    }

    mysqli_query($mysqli,"UPDATE users SET user_name = '$name', user_email = '$email' WHERE user_id = $user_id");

    if(!empty($new_password)){
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_specific_encryption_ciphertext = encryptUserSpecificKey($_POST['new_password']);
        mysqli_query($mysqli,"UPDATE users SET user_password = '$new_password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext' WHERE user_id = $user_id");
        //Extended Logging
        $extended_log_description .= ", password changed";
    }

    if(!empty($two_fa) && $two_fa == 'disable'){
        mysqli_query($mysqli, "UPDATE users SET user_token = '' WHERE user_id = '$user_id'");
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name disabled 2FA for $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
    }

    //Update User Settings
    mysqli_query($mysqli,"UPDATE user_settings SET user_role = $role, user_default_company = $default_company WHERE user_id = $user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name modified user $name $extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, log_entity_id = $user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "User <strong>$name</strong> updated" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['activate_user'])){

    validateAdminRole();
    validateCSRFToken($_GET['csrf_token']);

    $user_id = intval($_GET['activate_user']);

    // Get User Name
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $user_name = sanitizeInput($row['user_name']);

    mysqli_query($mysqli,"UPDATE users SET user_status = 1 WHERE user_id = $user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name activated user $user_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, log_entity_id = $user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "User <strong>$user_name</strong> activated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['disable_user'])){

    validateAdminRole();
    validateCSRFToken($_GET['csrf_token']);

    $user_id = intval($_GET['disable_user']);

    // Get User Name
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $user_name = sanitizeInput($row['user_name']);

    mysqli_query($mysqli,"UPDATE users SET user_status = 0 WHERE user_id = $user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name disabled user $user_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, log_entity_id = $user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "User <strong>$user_name</strong> disabled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_profile'])){

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $user_id = $session_user_id;
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $existing_file_name = sanitizeInput($_POST['existing_file_name']);
    $logout = false;
    $extended_log_description = '';

    // Email notification when password or email is changed
    $user_old_email_sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_email FROM users WHERE user_id = $user_id"));
    $user_old_email = $user_old_email_sql['user_email'];

    if (!empty($config_smtp_host) && (!empty($new_password) || $user_old_email !== $email)) {

        // Determine exactly what changed
        if ($user_old_email !== $email && !empty($new_password)) {
            $details = "Your e-mail address and password were changed. New email: $email.";
        }
        elseif ($user_old_email !== $email) {
            $details = "Your email address was changed. New email: $email.";
        }
        elseif (!empty($new_password)) {
            $details = "Your password was changed.";
        }

        $subject = "$config_app_name account update confirmation for $name";
        $body = "Hi $name, <br><br>Your $config_app_name account has been updated, details below: <br><br> <b>$details</b> <br><br> If you did not perform this change, contact your $config_app_name administrator immediately. <br><br>Thanks, <br>ITFlow<br>$session_company_name";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_mail_from_email, $config_mail_from_name,
            $user_old_email, $name,
            $subject, $body);
    }

    //Check to see if a file is attached
    if($_FILES['file']['tmp_name'] != ''){

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

        // sanitize file-name
        $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'gif', 'png');

        if(in_array($file_extension,$allowed_file_extensions) === false){
            $file_error = 1;
        }

        //Check File Size
        if($file_size > 2097152){
            $file_error = 1;
        }

        if($file_error == 0){
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/users/$user_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/users/$user_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");

            //Extended Logging
            $extended_log_description .= ", profile picture updated";

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            $_SESSION['alert_type'] = "error";
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    if(!empty($new_password)){
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_specific_encryption_ciphertext = encryptUserSpecificKey($_POST['new_password']);
        mysqli_query($mysqli,"UPDATE users SET user_password = '$new_password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext' WHERE user_id = $user_id");

        $extended_log_description .= ", password changed";
        $logout = true;
    }

    // Enable extension access, only if it isn't already setup (user doesn't have cookie)
    if(isset($_POST['extension']) && $_POST['extension'] == 'Yes'){
        if(!isset($_COOKIE['user_extension_key'])){
            $extension_key = randomString(156);
            mysqli_query($mysqli, "UPDATE users SET user_extension_key = '$extension_key' WHERE user_id = $user_id");

            $extended_log_description .= ", extension access enabled";
            $logout = true;
        }
    }

    // Disable extension access
    if(!isset($_POST['extension'])){
        mysqli_query($mysqli, "UPDATE users SET user_extension_key = '' WHERE user_id = $user_id");
        $extended_log_description .= ", extension access disabled";
    }

    mysqli_query($mysqli,"UPDATE users SET user_name = '$name', user_email = '$email' WHERE user_id = $user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Preferences', log_action = 'Modify', log_description = '$session_name modified their preferences$extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "User preferences updated";

    if ($logout){
        header('Location: post.php?logout');
    }
    else{
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_POST['edit_user_companies'])){

    validateAdminRole();

    $user_id = intval($_POST['user_id']);

    mysqli_query($mysqli,"DELETE FROM user_companies WHERE user_id = $user_id");

    foreach($_POST['companies'] as $company){
        $company = intval($company);
        mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $user_id, company_id = $company");
    }

    //Logging
    //Get User Name
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $name = sanitizeInput($row['user_name']);
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name updated company permissions for user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Company permssions updated for user <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_user'])){

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    // Variables from GET
    $user_id = intval($_GET['archive_user']);
    $password = password_hash(randomString(), PASSWORD_DEFAULT);

    // Get user details
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $name = sanitizeInput($row['user_name']);

    // Archive user query
    mysqli_query($mysqli,"UPDATE users SET user_name = '$name (archived)', user_password = '$password', user_specific_encryption_ciphertext = '', user_archived_at = NOW() WHERE user_id = $user_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Archive', log_description = '$session_name archived user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "User <strong>$name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// API Key
if(isset($_POST['add_api_key'])){

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $secret = sanitizeInput($_POST['key']);
    $name = sanitizeInput($_POST['name']);
    $expire = sanitizeInput($_POST['expire']);
    $client = intval($_POST['client']);

    mysqli_query($mysqli,"INSERT INTO api_keys SET api_key_name = '$name', api_key_secret = '$secret', api_key_expire = '$expire', api_key_client_id = $client, company_id = $session_company_id");

    $api_key_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Create', log_description = '$session_name created API Key $name set to expire on $expire', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client, log_user_id = $session_user_id, log_entity_id = $api_key_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "API Key <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_api_key'])){

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $api_key_id = intval($_GET['delete_api_key']);

    // Get API Key Name
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT * FROM api_keys WHERE api_key_id = $api_key_id AND company_id = $session_company_id"));
    $name = sanitizeInput($row['api_key_name']);

    mysqli_query($mysqli,"DELETE FROM api_keys WHERE api_key_id = $api_key_id AND company_id = $session_company_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API Key', log_action = 'Delete', log_description = '$session_name deleted API key $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $api_key_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "API Key <strong>$name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_company'])){

    require_once('models/company.php');

    validateAdminRole();

    $company_id = intval($_POST['company_id']);
    $existing_file_name = sanitizeInput($_POST['existing_file_name']);

    if(!file_exists("uploads/settings/$company_id/")) {
        mkdir("uploads/settings/$company_id");
    }

    //Check to see if a file is attached
    if($_FILES['file']['tmp_name'] != ''){

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

        // sanitize file-name
        $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'gif', 'png');

        if(in_array($file_extension,$allowed_file_extensions) === false){
            $file_error = 1;
        }

        //Check File Size
        if($file_size > 2097152){
            $file_error = 1;
        }

        if($file_error == 0){
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/settings/$company_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/settings/$company_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = $company_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    mysqli_query($mysqli,"UPDATE companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = $company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Modify', log_description = '$session_name modified company $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_company'])){
    $company_id = intval($_GET['archive_company']);

    mysqli_query($mysqli,"UPDATE companies SET company_archived_at = NOW() WHERE company_id = $company_id");


    //Logging
    //Get Company Name
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Archive', log_description = '$session_name archived company $company_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Company <strong>$company_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_company'])){

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $company_id = intval($_GET['delete_company']);

    // Get Company Name
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);

    // Delete Company and all relational data A-Z

    mysqli_query($mysqli,"DELETE FROM accounts WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM api_keys WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM assets WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM calendars WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM notifications WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM categories WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM certificates WHERE company_id = $company_id");

    $sql = mysqli_query($mysqli,"SELECT client_id FROM clients WHERE company_id = $company_id");
    while($row = mysqli_fetch_array($sql)){
        $client_id = $row['client_id'];
        mysqli_query($mysqli,"DELETE FROM client_tags WHERE client_tag_client_id = $client_id");
        mysqli_query($mysqli,"DELETE FROM shared_items WHERE item_client_id = $client_id");
    }
    mysqli_query($mysqli,"DELETE FROM clients WHERE company_id = $company_id");

    mysqli_query($mysqli,"DELETE FROM contacts WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM documents WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM domains WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM events WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM expenses WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM files WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM folders WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM history WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM invoices WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM locations WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM logins WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM logs WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM networks WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM notifications WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM payments WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM products WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM quotes WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM records WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM recurring WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM revenues WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM scheduled_tickets WHERE company_id = $company_id");

    // Delete Items Associated Services
    $sql = mysqli_query($mysqli,"SELECT service_id FROM services WHERE company_id = $company_id");
    while($row = mysqli_fetch_array($sql)){
        $service_id = $row['service_id'];
        mysqli_query($mysqli,"DELETE FROM service_assets WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_certificates WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_contacts WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_documents WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_domains WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_logins WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_vendors WHERE service_id = $service_id");
    }
    mysqli_query($mysqli,"DELETE FROM services WHERE company_id = $company_id");

    mysqli_query($mysqli,"DELETE FROM settings WHERE company_id = $company_id");

    $sql = mysqli_query($mysqli,"SELECT software_id FROM software WHERE company_id = $company_id");
    while($row = mysqli_fetch_array($sql)){
        $software_id = $row['software_id'];
        mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");
        mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    }
    mysqli_query($mysqli,"DELETE FROM software WHERE company_id = $company_id");

    mysqli_query($mysqli,"DELETE FROM tags WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM taxes WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM tickets WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM ticket_replies WHERE company_id = $company_id");

    mysqli_query($mysqli,"DELETE FROM transfers WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM trips WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM user_companies WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM vendors WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM vendor_templates WHERE company_id = $company_id");

    // Delete Company Files
    removeDirectory('uploads/clients/$company_id');
    removeDirectory('uploads/expenses/$company_id');
    removeDirectory('uploads/settings/$company_id');
    removeDirectory('uploads/tmp/$company_id');

    // Finally Remove the company
    mysqli_query($mysqli,"DELETE FROM companies WHERE company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Delete', log_description = '$session_name deleted company $company_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Company <strong>$company_name</strong> deleted";

    header("Location: post.php?logout");

}

if(isset($_POST['verify'])){

    require_once("rfc6238.php");
    $currentcode = sanitizeInput($_POST['code']);  //code to validate, for example received from device

    if(TokenAuth6238::verify($session_token,$currentcode)){
        $_SESSION['alert_message'] = "VALID!";
    }else{
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "IN-VALID!";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_mail_settings'])){

    validateAdminRole();

    $config_smtp_host = sanitizeInput($_POST['config_smtp_host']);
    $config_smtp_port = intval($_POST['config_smtp_port']);
    $config_smtp_encryption = sanitizeInput($_POST['config_smtp_encryption']);
    $config_smtp_username = sanitizeInput($_POST['config_smtp_username']);
    $config_smtp_password = sanitizeInput($_POST['config_smtp_password']);
    $config_mail_from_email = sanitizeInput($_POST['config_mail_from_email']);
    $config_mail_from_name = sanitizeInput($_POST['config_mail_from_name']);
    $config_imap_host = sanitizeInput($_POST['config_imap_host']);
    $config_imap_port = intval($_POST['config_imap_port']);
    $config_imap_encryption = sanitizeInput($_POST['config_imap_encryption']);

    mysqli_query($mysqli,"UPDATE settings SET config_smtp_host = '$config_smtp_host', config_smtp_port = $config_smtp_port, config_smtp_encryption = '$config_smtp_encryption', config_smtp_username = '$config_smtp_username', config_smtp_password = '$config_smtp_password', config_mail_from_email = '$config_mail_from_email', config_mail_from_name = '$config_mail_from_name', config_imap_host = '$config_imap_host', config_imap_port = $config_imap_port, config_imap_encryption = '$config_imap_encryption' WHERE company_id = $session_company_id");


    //Update From Email and From Name if Invoice/Quote or Ticket fields are blank
    if(empty($config_invoice_from_name)){
        mysqli_query($mysqli,"UPDATE settings SET config_invoice_from_name = '$config_mail_from_name' WHERE company_id = $session_company_id");
    }

    if(empty($config_invoice_from_email)){
        mysqli_query($mysqli,"UPDATE settings SET config_invoice_from_email = '$config_mail_from_email' WHERE company_id = $session_company_id");
    }

    if(empty($config_quote_from_name)){
        mysqli_query($mysqli,"UPDATE settings SET config_quote_from_name = '$config_mail_from_name' WHERE company_id = $session_company_id");
    }

    if(empty($config_quote_from_email)){
        mysqli_query($mysqli,"UPDATE settings SET config_quote_from_email = '$config_mail_from_email' WHERE company_id = $session_company_id");
    }

    if(empty($config_ticket_from_name)){
        mysqli_query($mysqli,"UPDATE settings SET config_ticket_from_name = '$config_mail_from_name' WHERE company_id = $session_company_id");
    }

    if(empty($config_ticket_from_email)){
        mysqli_query($mysqli,"UPDATE settings SET config_ticket_from_email = '$config_mail_from_email' WHERE company_id = $session_company_id");
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified mail settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Mail Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['test_email_smtp'])){

    validateAdminRole();

    $email = sanitizeInput($_POST['email']);
    $subject = "Hi'ya there Chap";
    $body    = "Hello there Chap ;) Don't worry this won't hurt a bit, it's just a test";

    $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
        $config_mail_from_email, $config_mail_from_name,
        $email, $email,
        $subject, $body);

    if($mail === true){
        $_SESSION['alert_message'] = "Test email sent successfully";
    } else {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Test email failed";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['test_email_imap'])){

    validateAdminRole();

    // Prepare connection string with encryption (TLS/SSL/<blank>)
    $imap_mailbox = "$config_imap_host:$config_imap_port/imap/readonly/$config_imap_encryption";

    // Connect
    $imap = imap_open("{{$imap_mailbox}}INBOX", $config_smtp_username, $config_smtp_password);

    if ($imap) {
        $_SESSION['alert_message'] = "Connected successfully";
    } else {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Test IMAP connection failed";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_invoice_settings'])){

    validateAdminRole();

    $config_invoice_prefix = sanitizeInput($_POST['config_invoice_prefix']);
    $config_invoice_next_number = intval($_POST['config_invoice_next_number']);
    $config_invoice_footer = sanitizeInput($_POST['config_invoice_footer']);
    $config_invoice_from_email = sanitizeInput($_POST['config_invoice_from_email']);
    $config_invoice_from_name = sanitizeInput($_POST['config_invoice_from_name']);
    $config_recurring_prefix = sanitizeInput($_POST['config_recurring_prefix']);
    $config_recurring_next_number = intval($_POST['config_recurring_next_number']);

    mysqli_query($mysqli,"UPDATE settings SET config_invoice_prefix = '$config_invoice_prefix', config_invoice_next_number = $config_invoice_next_number, config_invoice_footer = '$config_invoice_footer', config_invoice_from_email = '$config_invoice_from_email', config_invoice_from_name = '$config_invoice_from_name', config_recurring_prefix = '$config_recurring_prefix', config_recurring_next_number = $config_recurring_next_number WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified invoice settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_quote_settings'])){

    validateAdminRole();

    $config_quote_prefix = sanitizeInput($_POST['config_quote_prefix']);
    $config_quote_next_number = intval($_POST['config_quote_next_number']);
    $config_quote_footer = sanitizeInput($_POST['config_quote_footer']);
    $config_quote_from_email = sanitizeInput($_POST['config_quote_from_email']);
    $config_quote_from_name = sanitizeInput($_POST['config_quote_from_name']);

    mysqli_query($mysqli,"UPDATE settings SET config_quote_prefix = '$config_quote_prefix', config_quote_next_number = $config_quote_next_number, config_quote_footer = '$config_quote_footer', config_quote_from_email = '$config_quote_from_email', config_quote_from_name = '$config_quote_from_name' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified quote settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_ticket_settings'])){

    validateAdminRole();

    $config_ticket_prefix = sanitizeInput($_POST['config_ticket_prefix']);
    $config_ticket_next_number = intval($_POST['config_ticket_next_number']);
    $config_ticket_from_email = sanitizeInput($_POST['config_ticket_from_email']);
    $config_ticket_from_name = sanitizeInput($_POST['config_ticket_from_name']);
    $config_ticket_email_parse = intval($_POST['config_ticket_email_parse']);
    $config_ticket_client_general_notifications = intval($_POST['config_ticket_client_general_notifications']);

    mysqli_query($mysqli,"UPDATE settings SET config_ticket_prefix = '$config_ticket_prefix', config_ticket_next_number = $config_ticket_next_number, config_ticket_from_email = '$config_ticket_from_email', config_ticket_from_name = '$config_ticket_from_name', config_ticket_email_parse = '$config_ticket_email_parse', config_ticket_client_general_notifications = $config_ticket_client_general_notifications WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified ticket settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Ticket Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_default_settings'])){

    validateAdminRole();

    $expense_account = intval($_POST['expense_account']);
    $payment_account = intval($_POST['payment_account']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $expense_payment_method = sanitizeInput($_POST['expense_payment_method']);
    $transfer_from_account = intval($_POST['transfer_from_account']);
    $transfer_to_account = intval($_POST['transfer_to_account']);
    $calendar = intval($_POST['calendar']);
    $net_terms = intval($_POST['net_terms']);

    mysqli_query($mysqli,"UPDATE settings SET config_default_expense_account = $expense_account, config_default_payment_account = $payment_account, config_default_payment_method = '$payment_method', config_default_expense_payment_method = '$expense_payment_method', config_default_transfer_from_account = $transfer_from_account, config_default_transfer_to_account = $transfer_to_account, config_default_calendar = $calendar, config_default_net_terms = $net_terms WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified default settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Default settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_theme_settings'])){

    validateAdminRole();

    $theme = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['theme']));

    mysqli_query($mysqli,"UPDATE settings SET config_theme = '$theme' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified theme settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Changed theme to <strong>$theme</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


if(isset($_POST['edit_alert_settings'])){

    validateAdminRole();

    $config_enable_cron = intval($_POST['config_enable_cron']);
    $config_enable_alert_domain_expire = intval($_POST['config_enable_alert_domain_expire']);
    $config_send_invoice_reminders = intval($_POST['config_send_invoice_reminders']);
    $config_invoice_overdue_reminders = sanitizeInput($_POST['config_invoice_overdue_reminders']);

    mysqli_query($mysqli,"UPDATE settings SET config_send_invoice_reminders = $config_send_invoice_reminders, config_invoice_overdue_reminders = '$config_invoice_overdue_reminders', config_enable_cron = $config_enable_cron, config_enable_alert_domain_expire = $config_enable_alert_domain_expire WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified alert settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Alert Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_online_payment_settings'])){

    validateAdminRole();

    $config_stripe_enable = intval($_POST['config_stripe_enable']);
    $config_stripe_publishable = sanitizeInput($_POST['config_stripe_publishable']);
    $config_stripe_secret = sanitizeInput($_POST['config_stripe_secret']);
    $config_stripe_account = intval($_POST['config_stripe_account']);

    mysqli_query($mysqli,"UPDATE settings SET config_stripe_enable = $config_stripe_enable, config_stripe_publishable = '$config_stripe_publishable', config_stripe_secret = '$config_stripe_secret', config_stripe_account = $config_stripe_account WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified online payment settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Online Payment Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_integrations_settings'])){

    validateAdminRole();

    $azure_client_id = sanitizeInput($_POST['azure_client_id']);
    $azure_client_secret = sanitizeInput($_POST['azure_client_secret']);

    mysqli_query($mysqli,"UPDATE settings SET config_azure_client_id = '$azure_client_id', config_azure_client_secret = '$azure_client_secret' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified integrations settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Integrations Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_module_settings'])){

    validateAdminRole();

    $config_module_enable_itdoc = intval($_POST['config_module_enable_itdoc']);
    $config_module_enable_ticketing = intval($_POST['config_module_enable_ticketing']);
    $config_module_enable_accounting = intval($_POST['config_module_enable_accounting']);

    mysqli_query($mysqli,"UPDATE settings SET config_module_enable_itdoc = $config_module_enable_itdoc, config_module_enable_ticketing = $config_module_enable_ticketing, config_module_enable_accounting = $config_module_enable_accounting WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified module settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Module Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_telemetry_settings'])){

    validateAdminRole();

    $config_telemetry = intval($_POST['config_telemetry']);

    mysqli_query($mysqli,"UPDATE settings SET config_telemetry = $config_telemetry WHERE company_id = $session_company_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified telemetry settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Telemetry Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['send_telemetry_data'])){

    validateAdminRole();

    $comments = sanitizeInput($_POST['comments']);

    $sql = mysqli_query($mysqli,"SELECT * FROM companies LIMIT 1");
    $row = mysqli_fetch_array($sql);

    $company_name = sanitizeInput($row['company_name']);
    $city = sanitizeInput($row['company_city']);
    $state = sanitizeInput($row['company_state']);
    $country = sanitizeInput($row['company_country']);
    $currency = sanitizeInput($row['company_currency']);
    $current_version = exec("git rev-parse HEAD");

    // Client Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS num FROM clients"));
    $client_count = $row['num'];

    // Ticket Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM tickets"));
    $ticket_count = $row['num'];

    // Calendar Event Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('event_id') AS num FROM events"));
    $calendar_event_count = $row['num'];

    // Quote Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes"));
    $quote_count = $row['num'];

    // Invoice Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices"));
    $invoice_count = $row['num'];

    // Revenue Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('revenue_id') AS num FROM revenues"));
    $revenue_count = $row['num'];

    // Recurring Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM recurring"));
    $recurring_count = $row['num'];

    // Account Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('account_id') AS num FROM accounts"));
    $account_count = $row['num'];

    // Tax Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tax_id') AS num FROM taxes"));
    $tax_count = $row['num'];

    // Product Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('product_id') AS num FROM products"));
    $product_count = $row['num'];

    // Payment Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments WHERE payment_invoice_id > 0"));
    $payment_count = $row['num'];

    // Company Vendor Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id = 0"));
    $company_vendor_count = $row['num'];

    // Expense Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('expense_id') AS num FROM expenses WHERE expense_vendor_id > 0"));
    $expense_count = $row['num'];

    // Trip Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('trip_id') AS num FROM trips"));
    $trip_count = $row['num'];

    // Transfer Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('transfer_id') AS num FROM transfers"));
    $transfer_count = $row['num'];

    // Contact Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts"));
    $contact_count = $row['num'];

    // Location Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations"));
    $location_count = $row['num'];

    // Asset Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets"));
    $asset_count = $row['num'];

    // Software Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 0"));
    $software_count = $row['num'];

    // Software Template Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 1"));
    $software_template_count = $row['num'];

    // Password Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins"));
    $password_count = $row['num'];

    // Network Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks"));
    $network_count = $row['num'];

    // Certificate Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('certificate_id') AS num FROM certificates"));
    $certificate_count = $row['num'];

    // Domain Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains"));
    $domain_count = $row['num'];

    // Service Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('service_id') AS num FROM services"));
    $service_count = $row['num'];

    // Client Vendor Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id > 0"));
    $client_vendor_count = $row['num'];

    // Vendor Template Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 1"));
    $vendor_template_count = $row['num'];

    // File Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('file_id') AS num FROM files"));
    $file_count = $row['num'];

    // Document Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 0"));
    $document_count = $row['num'];

    // Document Template Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 1"));
    $document_template_count = $row['num'];

    // Shared Item Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('item_id') AS num FROM shared_items"));
    $shared_item_count = $row['num'];

    // Company Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('company_id') AS num FROM companies"));
    $company_count = $row['num'];

    // User Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('user_id') AS num FROM users"));
    $user_count = $row['num'];

    // Category Expense Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Expense'"));
    $category_expense_count = $row['num'];

    // Category Income Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Income'"));
    $category_income_count = $row['num'];

    // Category Referral Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Referral'"));
    $category_referral_count = $row['num'];

    // Category Payment Method Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Payment Method'"));
    $category_payment_method_count = $row['num'];

    // Tag Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tag_id') AS num FROM tags"));
    $tag_count = $row['num'];

    // API Key Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('api_key_id') AS num FROM api_keys"));
    $api_key_count = $row['num'];

    // Log Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('log_id') AS num FROM logs"));
    $log_count = $row['num'];

    $postdata = http_build_query(
        array(
            'installation_id' => "$installation_id",
            'version' => "$current_version",
            'company_name' => "$company_name",
            'city' => "$city",
            'state' => "$state",
            'country' => "$country",
            'currency' => "$currency",
            'comments' => "$comments",
            'client_count' => $client_count,
            'ticket_count' => $ticket_count,
            'calendar_event_count' => $calendar_event_count,
            'quote_count' => $quote_count,
            'invoice_count' => $invoice_count,
            'revenue_count' => $revenue_count,
            'recurring_count' => $recurring_count,
            'account_count' => $account_count,
            'tax_count' => $tax_count,
            'product_count' => $product_count,
            'payment_count' => $payment_count,
            'company_vendor_count' => $company_vendor_count,
            'expense_count' => $expense_count,
            'trip_count' => $trip_count,
            'transfer_count' => $transfer_count,
            'contact_count' => $contact_count,
            'location_count' => $location_count,
            'asset_count' => $asset_count,
            'software_count' => $software_count,
            'software_template_count' => $software_template_count,
            'password_count' => $password_count,
            'network_count' => $network_count,
            'certificate_count' => $certificate_count,
            'domain_count' => $domain_count,
            'service_count' => $service_count,
            'client_vendor_count' => $client_vendor_count,
            'vendor_template_count' => $vendor_template_count,
            'file_count' => $file_count,
            'document_count' => $document_count,
            'document_template_count' => $document_template_count,
            'shared_item_count' => $shared_item_count,
            'company_count' => $company_count,
            'user_count' => $user_count,
            'category_expense_count' => $category_expense_count,
            'category_income_count' => $category_income_count,
            'category_referral_count' => $category_referral_count,
            'category_payment_method_count' => $category_payment_method_count,
            'tag_count' => $tag_count,
            'api_key_count' => $api_key_count,
            'log_count' => $log_count,
            'config_theme' => "$config_theme",
            'config_enable_cron' => $config_enable_cron,
            'config_ticket_email_parse' => $config_ticket_email_parse,
            'config_module_enable_itdoc' => $config_module_enable_itdoc,
            'config_module_enable_ticketing' => $config_module_enable_ticketing,
            'config_module_enable_accounting' => $config_module_enable_accounting,
            'collection_method' => 2
        )
    );

    $opts = array('http' =>
        array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata
        )
    );

    $context = stream_context_create($opts);

    $result = file_get_contents('https://telemetry.itflow.org', false, $context);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Telemetry', log_action = 'Sent', log_description = '$session_name manually sent telemetry results to the ITFlow Developers', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Telemetry data sent to the ITFlow developers";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['enable_2fa'])){

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $token = sanitizeInput($_POST['token']);

    mysqli_query($mysqli,"UPDATE users SET user_token = '$token' WHERE user_id = $session_user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Settings', log_action = 'Modify', log_description = '$session_name enabled 2FA on their account', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Two-factor authentication enabled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['disable_2fa'])){

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    mysqli_query($mysqli,"UPDATE users SET user_token = '' WHERE user_id = $session_user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Settings', log_action = 'Modify', log_description = '$session_name disabled 2FA on their account', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    // Email notification
    if (!empty($config_smtp_host)) {
        $subject = "$config_app_name account update confirmation for $session_name";
        $body = "Hi $session_name, <br><br>Your $config_app_name account has been updated, details below: <br><br> <b>2FA was disabled.</b> <br><br> If you did not perform this change, contact your $config_app_name administrator immediately. <br><br>Thanks, <br>ITFlow<br>$session_company_name";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_mail_from_email, $config_mail_from_name,
            $session_email, $session_name,
            $subject, $body);
    }

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Two-factor authentication disabled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['download_database'])){

    validateAdminRole();

    // Get All Table Names From the Database
    $tables = array();
    $sql = "SHOW TABLES";
    $result = mysqli_query($mysqli, $sql);

    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $sqlScript = "";
    foreach ($tables as $table) {

        // Prepare SQLscript for creating table structure
        $query = "SHOW CREATE TABLE $table";
        $result = mysqli_query($mysqli, $query);
        $row = mysqli_fetch_row($result);

        $sqlScript .= "\n\n" . $row[1] . ";\n\n";


        $query = "SELECT * FROM $table";
        $result = mysqli_query($mysqli, $query);

        $columnCount = mysqli_num_fields($result);

        // Prepare SQLscript for dumping data for each table
        for ($i = 0; $i < $columnCount; $i ++) {
            while ($row = mysqli_fetch_row($result)) {
                $sqlScript .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $columnCount; $j ++) {

                    if (isset($row[$j])) {
                        $sqlScript .= '"' . $row[$j] . '"';
                    } else {
                        $sqlScript .= '""';
                    }
                    if ($j < ($columnCount - 1)) {
                        $sqlScript .= ',';
                    }
                }
                $sqlScript .= ");\n";
            }
        }

        $sqlScript .= "\n";
    }

    if(!empty($sqlScript))
    {
        // Save the SQL script to a backup file
        $backup_file_name = date('Y-m-d') . '_' . $config_company_name . '_backup.sql';
        $fileHandler = fopen($backup_file_name, 'w+');
        $number_of_lines = fwrite($fileHandler, $sqlScript);
        fclose($fileHandler);

        // Download the SQL backup file to the browser
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file_name));
        ob_clean();
        flush();
        readfile($backup_file_name);
        exec('rm ' . $backup_file_name);
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Database', log_action = 'Download', log_description = '$session_name downloaded the database', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Database downloaded";
}

if(isset($_POST['backup_master_key'])){

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $password = $_POST['password'];

    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $session_user_id");
    $userRow = mysqli_fetch_array($sql);

    if(password_verify($password, $userRow['user_password'])) {
        $site_encryption_master_key = decryptUserSpecificKey($userRow['user_specific_encryption_ciphertext'], $password);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Master Key', log_action = 'Download', log_description = '$session_name retrieved the master encryption key', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");
        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Settings', notification = '$session_name retrieved the master encryption key', company_id = $session_company_id");


        echo "==============================";
        echo "<br>Master encryption key:<br>";
        echo "<b>$site_encryption_master_key</b>";
        echo "<br>==============================";
    }

    else {
        //Log the failure
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Master Key', log_action = 'Download', log_description = '$session_name attempted to retrieve the master encryption key (failure)', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "Incorrect password.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['update'])){

    validateAdminRole();

    exec("git pull");

    //FORCE UPDATE FUNCTION (Will be added later as a checkbox)
    //git fetch downloads the latest from remote without trying to merge or rebase anything. Then the git reset resets the master branch to what you just fetched. The --hard option changes all the files in your working tree to match the files in origin/master

    //exec("git fetch --all");
    //exec("git reset --hard origin/master");

    //header("Location: post.php?update_db");


    // Send Telemetry if enabled during update
    if($config_telemetry == 1){

        $sql = mysqli_query($mysqli,"SELECT * FROM companies LIMIT 1");
        $row = mysqli_fetch_array($sql);

        $company_name = sanitizeInput($row['company_name']);
        $city = sanitizeInput($row['company_city']);
        $state = sanitizeInput($row['company_state']);
        $country = sanitizeInput($row['company_country']);
        $currency = sanitizeInput($row['company_currency']);
        $current_version = exec("git rev-parse HEAD");

        // Client Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS num FROM clients"));
        $client_count = $row['num'];

        // Ticket Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM tickets"));
        $ticket_count = $row['num'];

        // Calendar Event Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('event_id') AS num FROM events"));
        $calendar_event_count = $row['num'];

        // Quote Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes"));
        $quote_count = $row['num'];

        // Invoice Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices"));
        $invoice_count = $row['num'];

        // Revenue Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('revenue_id') AS num FROM revenues"));
        $revenue_count = $row['num'];

        // Recurring Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM recurring"));
        $recurring_count = $row['num'];

        // Account Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('account_id') AS num FROM accounts"));
        $account_count = $row['num'];

        // Tax Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tax_id') AS num FROM taxes"));
        $tax_count = $row['num'];

        // Product Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('product_id') AS num FROM products"));
        $product_count = $row['num'];

        // Payment Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments WHERE payment_invoice_id > 0"));
        $payment_count = $row['num'];

        // Company Vendor Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id = 0"));
        $company_vendor_count = $row['num'];

        // Expense Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('expense_id') AS num FROM expenses WHERE expense_vendor_id > 0"));
        $expense_count = $row['num'];

        // Trip Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('trip_id') AS num FROM trips"));
        $trip_count = $row['num'];

        // Transfer Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('transfer_id') AS num FROM transfers"));
        $transfer_count = $row['num'];

        // Contact Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts"));
        $contact_count = $row['num'];

        // Location Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations"));
        $location_count = $row['num'];

        // Asset Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets"));
        $asset_count = $row['num'];

        // Software Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 0"));
        $software_count = $row['num'];

        // Software Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 1"));
        $software_template_count = $row['num'];

        // Password Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins"));
        $password_count = $row['num'];

        // Network Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks"));
        $network_count = $row['num'];

        // Certificate Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('certificate_id') AS num FROM certificates"));
        $certificate_count = $row['num'];

        // Domain Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains"));
        $domain_count = $row['num'];

        // Service Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('service_id') AS num FROM services"));
        $service_count = $row['num'];

        // Client Vendor Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id > 0"));
        $client_vendor_count = $row['num'];

        // Vendor Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 1"));
        $vendor_template_count = $row['num'];

        // File Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('file_id') AS num FROM files"));
        $file_count = $row['num'];

        // Document Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 0"));
        $document_count = $row['num'];

        // Document Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 1"));
        $document_template_count = $row['num'];

        // Shared Item Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('item_id') AS num FROM shared_items"));
        $shared_item_count = $row['num'];

        // Company Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('company_id') AS num FROM companies"));
        $company_count = $row['num'];

        // User Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('user_id') AS num FROM users"));
        $user_count = $row['num'];

        // Category Expense Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Expense'"));
        $category_expense_count = $row['num'];

        // Category Income Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Income'"));
        $category_income_count = $row['num'];

        // Category Referral Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Referral'"));
        $category_referral_count = $row['num'];

        // Category Payment Method Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Payment Method'"));
        $category_payment_method_count = $row['num'];

        // Tag Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tag_id') AS num FROM tags"));
        $tag_count = $row['num'];

        // API Key Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('api_key_id') AS num FROM api_keys"));
        $api_key_count = $row['num'];

        // Log Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('log_id') AS num FROM logs"));
        $log_count = $row['num'];

        $postdata = http_build_query(
            array(
                'installation_id' => "$installation_id",
                'version' => "$current_version",
                'company_name' => "$company_name",
                'city' => "$city",
                'state' => "$state",
                'country' => "$country",
                'currency' => "$currency",
                'comments' => "$comments",
                'client_count' => $client_count,
                'ticket_count' => $ticket_count,
                'calendar_event_count' => $calendar_event_count,
                'quote_count' => $quote_count,
                'invoice_count' => $invoice_count,
                'revenue_count' => $revenue_count,
                'recurring_count' => $recurring_count,
                'account_count' => $account_count,
                'tax_count' => $tax_count,
                'product_count' => $product_count,
                'payment_count' => $payment_count,
                'company_vendor_count' => $company_vendor_count,
                'expense_count' => $expense_count,
                'trip_count' => $trip_count,
                'transfer_count' => $transfer_count,
                'contact_count' => $contact_count,
                'location_count' => $location_count,
                'asset_count' => $asset_count,
                'software_count' => $software_count,
                'software_template_count' => $software_template_count,
                'password_count' => $password_count,
                'network_count' => $network_count,
                'certificate_count' => $certificate_count,
                'domain_count' => $domain_count,
                'service_count' => $service_count,
                'client_vendor_count' => $client_vendor_count,
                'vendor_template_count' => $vendor_template_count,
                'file_count' => $file_count,
                'document_count' => $document_count,
                'document_template_count' => $document_template_count,
                'shared_item_count' => $shared_item_count,
                'company_count' => $company_count,
                'user_count' => $user_count,
                'category_expense_count' => $category_expense_count,
                'category_income_count' => $category_income_count,
                'category_referral_count' => $category_referral_count,
                'category_payment_method_count' => $category_payment_method_count,
                'tag_count' => $tag_count,
                'api_key_count' => $api_key_count,
                'log_count' => $log_count,
                'config_theme' => "$config_theme",
                'config_enable_cron' => $config_enable_cron,
                'config_ticket_email_parse' => $config_ticket_email_parse,
                'config_module_enable_itdoc' => $config_module_enable_itdoc,
                'config_module_enable_ticketing' => $config_module_enable_ticketing,
                'config_module_enable_accounting' => $config_module_enable_accounting,
                'config_telemetry' => $config_telemetry,
                'collection_method' => 4
            )
        );

        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents('https://telemetry.itflow.org', false, $context);

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Update', log_description = '$session_name ran updates', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Update successful";

    sleep(1);

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['update_db'])){

    validateAdminRole();

    // Get the current version
    require_once ('database_version.php');

    // Perform upgrades, if required
    require_once ('database_updates.php');

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Update', log_description = '$session_name updated the database structure', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Database structure update successful";

    sleep(1);

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['add_client'])){

    require_once('models/client.php');

    validateAdminRole();

    $location_phone = preg_replace("/[^0-9]/", '',$_POST['location_phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $country = sanitizeInput($_POST['country']);
    $contact = sanitizeInput($_POST['contact']);
    $title = sanitizeInput($_POST['title']);
    $contact_phone = preg_replace("/[^0-9]/", '',$_POST['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '',$_POST['contact_extension']);
    $contact_mobile = preg_replace("/[^0-9]/", '',$_POST['contact_mobile']);
    $contact_email = sanitizeInput($_POST['contact_email']);

    $extended_log_description = '';

    mysqli_query($mysqli,"INSERT INTO clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_currency_code = '$currency_code', client_net_terms = $net_terms, client_notes = '$notes', client_accessed_at = NOW(), company_id = $session_company_id");

    $client_id = mysqli_insert_id($mysqli);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
        file_put_contents("uploads/clients/$session_company_id/$client_id/index.php", "");
    }

    //Add Location
    if(!empty($location_phone) || !empty($address) || !empty($city) || !empty($state) || !empty($zip)){
        mysqli_query($mysqli,"INSERT INTO locations SET location_name = 'Primary', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$location_phone', location_country = '$country', location_client_id = $client_id, company_id = $session_company_id");

        //Update Primay location in clients
        $location_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");

        //Extended Logging
        $extended_log_description .= ", primary location $address added";
    }


    //Add Contact
    if(!empty($contact) || !empty($title) || !empty($contact_phone) || !empty($contact_mobile) || !empty($contact_email)){
        mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$contact', contact_title = '$title', contact_phone = '$contact_phone', contact_extension = '$contact_extension', contact_mobile = '$contact_mobile', contact_email = '$contact_email', contact_client_id = $client_id, company_id = $session_company_id");

        //Update Primary contact in clients
        $contact_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");

        //Extended Logging
        $extended_log_description .= ", primary contact $contact added";
    }

    //Add Tags
    if(isset($_POST['tags'])){
        foreach($_POST['tags'] as $tag){
            $tag = intval($tag);
            mysqli_query($mysqli,"INSERT INTO client_tags SET client_tag_client_id = $client_id, client_tag_tag_id = $tag");
        }
    }

    //Add domain to domains/certificates
    if(!empty($website) && filter_var($website, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)){
        // Get domain expiry date
        $expire = getDomainExpirationDate($website);

        // NS, MX, A and WHOIS records/data
        $records = getDomainRecords($website);
        $a = sanitizeInput($records['a']);
        $ns = sanitizeInput($records['ns']);
        $mx = sanitizeInput($records['mx']);
        $whois = sanitizeInput($records['whois']);

        // Add domain record
        mysqli_query($mysqli,"INSERT INTO domains SET domain_name = '$website', domain_registrar = '0',  domain_webhost = '0', domain_expire = '$expire', domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_raw_whois = '$whois', domain_client_id = $client_id, company_id = $session_company_id");

        //Extended Logging
        $extended_log_description .= ", domain added";

        // Get inserted ID (for linking certificate, if exists)
        $domain_id = mysqli_insert_id($mysqli);

        // Get SSL cert for domain (if exists)
        $certificate = getSSL($website);
        if($certificate['success'] == "TRUE"){
            $expire = sanitizeInput($certificate['expire']);
            $issued_by = sanitizeInput($certificate['issued_by']);
            $public_key = sanitizeInput($certificate['public_key']);

            mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$website', certificate_domain = '$website', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id, company_id = $session_company_id");

            //Extended Logging
            $extended_log_description .= ", SSL certificate added";
        }

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Create', log_description = '$session_name created client $name$extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Client <strong>$name</strong> created";

    header("Location: clients.php");
    exit;

}

if(isset($_POST['edit_client'])){

    require_once('models/client.php');

    validateAdminRole();

    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli,"UPDATE clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_currency_code = '$currency_code', client_net_terms = $net_terms, client_notes = '$notes' WHERE client_id = $client_id AND company_id = $session_company_id");

    //Tags
    //Delete existing tags
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE client_tag_client_id = $client_id");

    //Add new tags
    foreach($_POST['tags'] as $tag){
        $tag = intval($tag);
        mysqli_query($mysqli,"INSERT INTO client_tags SET client_tag_client_id = $client_id, client_tag_tag_id = $tag");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Modify', log_description = '$session_name modified client $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Client <strong>$client_name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['archive_client'])){

    validateAdminRole();

    $client_id = intval($_GET['archive_client']);

    // Get Client Name
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    mysqli_query($mysqli,"UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Archive', log_description = '$session_name archived client $client_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Client $client_name archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['undo_archive_client'])){

    $client_id = intval($_GET['undo_archive_client']);

    // Get Client Name
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    mysqli_query($mysqli,"UPDATE clients SET client_archived_at = NULL WHERE client_id = $client_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Undo Archive', log_description = '$session_name unarchived client $client_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Client $client_name unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['delete_client'])){

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $client_id = intval($_GET['delete_client']);

    //Get Client Name
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    // Delete Client Data
    mysqli_query($mysqli,"DELETE FROM api_keys WHERE api_key_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM certificates WHERE certificate_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE client_tag_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM documents WHERE document_client_id = $client_id");

    // Delete Domains and associated records
    $sql = mysqli_query($mysqli,"SELECT domain_id FROM domains WHERE domain_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $domain_id = $row['domain_id'];
        mysqli_query($mysqli,"DELETE FROM records WHERE record_domain_id = $domain_id");
    }
    mysqli_query($mysqli,"DELETE FROM domains WHERE domain_client_id = $client_id");

    mysqli_query($mysqli,"DELETE FROM events WHERE event_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM files WHERE file_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM folders WHERE folder_client_id = $client_id");

    //Delete Invoices and Invoice Referencing data
    $sql = mysqli_query($mysqli,"SELECT invoice_id FROM invoices WHERE invoice_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $invoice_id = $row['invoice_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
        mysqli_query($mysqli,"DELETE FROM history WHERE history_invoice_id = $invoice_id");
    }
    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_client_id = $client_id");

    mysqli_query($mysqli,"DELETE FROM locations WHERE location_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM logins WHERE login_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM logs WHERE log_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM networks WHERE network_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM notifications WHERE notification_client_id = $client_id");

    //Delete Quote  and related items
    $sql = mysqli_query($mysqli,"SELECT quote_id FROM quotes WHERE quote_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $quote_id = $row['quote_id'];

        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_quote_id = $quote_id");
    }
    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_client_id = $client_id");

    // Delete Recurring Invoices and associated items
    $sql = mysqli_query($mysqli,"SELECT recurring_id FROM recurring WHERE recurring_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $recurring_id = $row['recurring_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_recurring_id = $recurring_id");
    }
    mysqli_query($mysqli,"DELETE FROM recurring WHERE recurring_client_id = $client_id");

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM scheduled_tickets WHERE scheduled_ticket_client_id = $client_id");

    // Delete Services and items associated with services
    $sql = mysqli_query($mysqli,"SELECT service_id FROM services WHERE service_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $service_id = $row['service_id'];
        mysqli_query($mysqli,"DELETE FROM service_assets WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_certificates WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_contacts WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_documents WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_domains WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_logins WHERE service_id = $service_id");
        mysqli_query($mysqli,"DELETE FROM service_vendors WHERE service_id = $service_id");
    }
    mysqli_query($mysqli,"DELETE FROM services WHERE service_client_id = $client_id");

    mysqli_query($mysqli,"DELETE FROM shared_items WHERE item_client_id = $client_id");

    $sql = mysqli_query($mysqli,"SELECT software_id FROM software WHERE software_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $software_id = $row['software_id'];
        mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");
        mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    }
    mysqli_query($mysqli,"DELETE FROM software WHERE software_client_id = $client_id");

    // Delete tickets and related data
    $sql = mysqli_query($mysqli,"SELECT ticket_id FROM tickets WHERE ticket_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $ticket_id = $row['ticket_id'];
        mysqli_query($mysqli,"DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");
        mysqli_query($mysqli,"DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");
    }
    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM trips WHERE trip_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_client_id = $client_id");

    //Delete Client Files
    removeDirectory('uploads/clients/$client_id');

    //Finally Remove the Client
    mysqli_query($mysqli,"DELETE FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Delete', log_description = '$session_name deleted client $client_name and all associated data', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Client $client_name deleted along with all associated data";

    header("Location: clients.php");
}

if(isset($_POST['add_calendar'])){

    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);

    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = '$name', calendar_color = '$color', company_id = $session_company_id");

    $calendar_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar', log_action = 'Create', log_description = '$session_name created calendar $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $calendar_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Calendar <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_event'])){

    require_once('models/event.php');

    mysqli_query($mysqli,"INSERT INTO events SET event_title = '$title', event_description = '$description', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_calendar_id = $calendar_id, event_client_id = $client, company_id = $session_company_id");

    $event_id = mysqli_insert_id($mysqli);

    //Get Calendar Name
    $sql = mysqli_query($mysqli,"SELECT * FROM calendars WHERE calendar_id = $calendar_id");
    $row = mysqli_fetch_array($sql);
    $calendar_name = sanitizeInput($row['calendar_name']);

    //If email is checked
    if($email_event == 1){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients JOIN companies ON clients.company_id = companies.company_id JOIN contacts ON primary_contact = contact_id WHERE client_id = $client AND companies.company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $client_name = $row['client_name'];
        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];
        $company_name = $row['company_name'];
        $company_country = $row['company_country'];
        $company_address = $row['company_address'];
        $company_city = $row['company_city'];
        $company_state = $row['company_state'];
        $company_zip = $row['company_zip'];
        $company_phone = formatPhoneNumber($row['company_phone']);
        $company_email = $row['company_email'];
        $company_website = $row['company_website'];
        $company_logo = $row['company_logo'];

        $subject = "New Calendar Event";
        $body    = "Hello $contact_name,<br><br>A calendar event has been scheduled: $title at $start<br><br><br>~<br>$company_name<br>$company_phone";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_mail_from_email, $config_mail_from_name,
            $contact_email, $contact_name,
            $subject, $body);

        // Logging for email (success/fail)
        if ($mail === true) {
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar Event', log_action = 'Email', log_description = '$session_name emailed event $title to $contact_name from client $client_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', client_id = $client, log_user_id = $session_user_id, log_entity_id = $event_id, company_id = $session_company_id");
        } else {
            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");
        }

    } // End mail IF

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar Event', log_action = 'Create', log_description = '$session_name created a calendar event titled $title in calendar $calendar_name', log_ip = '$session_ip', log_client_id = $client, log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $event_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Event <strong>$title</strong> created in calendar <strong>$calendar_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_event'])){

    require_once('models/event.php');

    $event_id = intval($_POST['event_id']);

    mysqli_query($mysqli,"UPDATE events SET event_title = '$title', event_description = '$description', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_calendar_id = $calendar_id, event_client_id = $client WHERE event_id = $event_id AND company_id = $session_company_id");

    //If email is checked
    if($email_event == 1){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients JOIN companies ON clients.company_id = companies.company_id JOIN contacts ON primary_contact = contact_id WHERE client_id = $client AND companies.company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $client_name = $row['client_name'];
        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];
        $company_name = $row['company_name'];
        $company_country = $row['company_country'];
        $company_address = $row['company_address'];
        $company_city = $row['company_city'];
        $company_state = $row['company_state'];
        $company_zip = $row['company_zip'];
        $company_phone = formatPhoneNumber($row['company_phone']);
        $company_email = $row['company_email'];
        $company_website = $row['company_website'];
        $company_logo = $row['company_logo'];


        $subject = "Calendar Event Rescheduled";
        $body    = "Hello $contact_name,<br><br>A calendar event has been rescheduled: $title at $start<br><br><br>~<br>$company_name<br>$company_phone";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_mail_from_email, $config_mail_from_name,
            $contact_email, $contact_name,
            $subject, $body);

        // Logging for email (success/fail)
        if ($mail === true) {
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar_Event', log_action = 'Email', log_description = '$session_name Emailed modified event $title to $client_name email $client_email', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
        } else {
            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
        }

    } // End mail IF

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar Event', log_action = 'Modify', log_description = '$session_name modified calendar event $title', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client, log_user_id = $session_user_id, log_entity_id = $event_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Calendar event titled <strong>$title</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_event'])){
    $event_id = intval($_GET['delete_event']);

    // Get Event Title
    $sql = mysqli_query($mysqli,"SELECT * FROM events WHERE event_id = $event_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $event_title = sanitizeInput($row['event_title']);
    $client_id = intval($row['event_client_id']);

    mysqli_query($mysqli,"DELETE FROM events WHERE event_id = $event_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar Event', log_action = 'Delete', log_description = '$session_name deleted calendar event titled $event_title', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Calendar event titled <strong>$event_title</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

//Vendor Templates

if(isset($_POST['add_vendor_template'])){

    require_once('models/vendor.php');

    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_template = 1, vendor_client_id = 0, company_id = $session_company_id");

    $vendor_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor Template', log_action = 'Create', log_description = '$session_name created vendor template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_vendor_template'])){

    require_once('models/vendor.php');

    $vendor_id = intval($_POST['vendor_id']);
    $vendor_template_id = intval($_POST['vendor_template_id']);

    if($_POST['update_base_vendors'] == 1) {
        $sql_update_vendors = "OR vendor_template_id = $vendor_id";
    } else{
        $sql_update_vendors = "";
    }

    //Update the exisiting template and all templates bassed of this vendor template
    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code',vendor_account_number = '$account_number', vendor_notes = '$notes' WHERE (vendor_id = $vendor_id $sql_update_vendors) AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor Template', log_action = 'Modify', log_description = '$session_name modified vendor template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['add_vendor_from_template'])){

    // GET POST Data
    $client_id = intval($_POST['client_id']); //Used if this vendor is under a contact otherwise its 0 for under company and or template
    $vendor_template_id = intval($_POST['vendor_template_id']);

    //GET Vendor Info
    $sql_vendor = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_template_id AND company_id = $session_company_id");

    $row = mysqli_fetch_array($sql_vendor);

    $name = sanitizeInput($row['vendor_name']);
    $description = sanitizeInput($row['vendor_description']);
    $account_number = sanitizeInput($row['vendor_account_number']);
    $contact_name = sanitizeInput($row['vendor_contact_name']);
    $phone = preg_replace("/[^0-9]/", '',$row['vendor_phone']);
    $extension = preg_replace("/[^0-9]/", '',$row['vendor_extension']);
    $email = sanitizeInput($row['vendor_email']);
    $website = sanitizeInput($row['vendor_website']);
    $hours = sanitizeInput($row['vendor_hours']);
    $sla = sanitizeInput($row['vendor_sla']);
    $code = sanitizeInput($row['vendor_code']);
    $notes = sanitizeInput($row['vendor_notes']);

    // Vendor add query
    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id, vendor_template_id = $vendor_template_id, company_id = $session_company_id");

    $vendor_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Create', log_description = 'Vendor created from template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor created from template";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// Vendors

if(isset($_POST['add_vendor'])){

    require_once('models/vendor.php');

    $client_id = intval($_POST['client_id']); // Used if this vendor is under a contact otherwise its 0 for under company

    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id, company_id = $session_company_id");

    $vendor_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Create', log_description = '$session_name created vendor $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Vendor <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_vendor'])){

    require_once('models/vendor.php');

    $vendor_id = intval($_POST['vendor_id']);
    $vendor_template_id = intval($_POST['vendor_template_id']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code',vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_template_id = $vendor_template_id WHERE vendor_id = $vendor_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Modify', log_description = '$session_name modified vendor $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Vendor <strong>$name</strong> modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['archive_vendor'])){
    $vendor_id = intval($_GET['archive_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = sanitizeInput($row['vendor_name']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Archive', log_description = '$session_name archived vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['delete_vendor'])){
    $vendor_id = intval($_GET['delete_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = sanitizeInput($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);
    $vendor_template_id = intval($row['vendor_template_id']);

    // If its a template reset all vendors based off this template to no template base
    if ($vendor_template_id > 0){
        mysqli_query($mysqli,"UPDATE vendors SET vendor_template_id = 0 WHERE vendor_template_id = $vendor_template_id");
    }

    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_id = $vendor_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Delete', log_description = '$session_name deleted vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['export_client_vendors_csv'])){
    $client_id = intval($_GET['export_client_vendors_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id ORDER BY vendor_name ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Vendors-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Contact Name', 'Phone', 'Website', 'Account Number', 'Notes');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['vendor_name'], $row['vendor_description'], $row['vendor_contact_name'], $row['vendor_phone'], $row['vendor_website'], $row['vendor_account_number'], $row['vendor_notes']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Export', log_description = '$session_name exported vendors to CSV', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;
}

// Products
if(isset($_POST['add_product'])){

    require_once('models/product.php');

    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_description = '$description', product_price = '$price', product_currency_code = '$session_company_currency', product_tax_id = $tax, product_category_id = $category, company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Create', log_description = '$session_name created product $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Product <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_product'])){

    require_once('models/product.php');

    $product_id = intval($_POST['product_id']);

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_price = '$price', product_tax_id = $tax, product_category_id = $category WHERE product_id = $product_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Modify', log_description = '$name',  company_id = $session_company_id, log_user_id = $session_user_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Modify', log_description = '$session_name modifyed product $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Product <strong>$name</strong> modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_product'])){
    $product_id = intval($_GET['delete_product']);

    //Get Product Name
    $sql = mysqli_query($mysqli,"SELECT * FROM products WHERE product_id = $product_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $product_name = sanitizeInput($row['product_name']);

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id AND company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Delete', log_description = '$session_name deleted product $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_trip'])){

    require_once('models/trip.php');

    mysqli_query($mysqli,"INSERT INTO trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, round_trip = $roundtrip, trip_purpose = '$purpose', trip_user_id = $user_id, trip_client_id = $client_id, company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Trip', log_action = 'Create', log_description = '$session_name logged trip to $destination', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Trip added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_trip'])){

    require_once('models/trip.php');

    $trip_id = intval($_POST['trip_id']);

    mysqli_query($mysqli,"UPDATE trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, trip_purpose = '$purpose', round_trip = $roundtrip, trip_user_id = $user_id, trip_client_id = $client_id WHERE trip_id = $trip_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Trip', log_action = 'Modify', log_description = '$date', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Trip modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_trip'])){
    $trip_id = intval($_GET['delete_trip']);

    //Get Client ID
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT * FROM trips WHERE trip_id = $trip_id AND company_id = $session_company_id"));
    $client_id = intval($row['trip_client_id']);

    mysqli_query($mysqli,"DELETE FROM trips WHERE trip_id = $trip_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Trip', log_action = 'Delete', log_description = '$trip_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Trip deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_account'])){

    $name = sanitizeInput($_POST['name']);
    $opening_balance = floatval($_POST['opening_balance']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = '$opening_balance', account_currency_code = '$currency_code', account_notes = '$notes', company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Account added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_account'])){

    $account_id = intval($_POST['account_id']);
    $name = sanitizeInput($_POST['name']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name', account_notes = '$notes' WHERE account_id = $account_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Modify', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Account modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_account'])){
    $account_id = intval($_GET['archive_account']);

    mysqli_query($mysqli,"UPDATE accounts SET account_archived_at = NOW() WHERE account_id = $account_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Archive', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

    $_SESSION['alert_message'] = "Account Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_account'])){
    $account_id = intval($_GET['delete_account']);

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Delete', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Account deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_category'])){

    require_once('models/category.php');

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type', category_color = '$color', company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_category'])){

    require_once('models/category.php');

    $category_id = intval($_POST['category_id']);

    mysqli_query($mysqli,"UPDATE categories SET category_name = '$name', category_type = '$type', category_color = '$color' WHERE category_id = $category_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Modify', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Category modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_category'])){
    $category_id = intval($_GET['archive_category']);

    mysqli_query($mysqli,"UPDATE categories SET category_archived_at = NOW() WHERE category_id = $category_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Archive', log_description = '$category_id'");

    $_SESSION['alert_message'] = "Category Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_category'])){
    $category_id = intval($_GET['delete_category']);

    mysqli_query($mysqli,"DELETE FROM categories WHERE category_id = $category_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Delete', log_description = '$category_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category deleted";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}


//Tags

if(isset($_POST['add_tag'])){

    require_once('models/tag.php');

    mysqli_query($mysqli,"INSERT INTO tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon', company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Tag added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_tag'])){

    require_once('models/tag.php');

    $tag_id = intval($_POST['tag_id']);

    mysqli_query($mysqli,"UPDATE tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon' WHERE tag_id = $tag_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Modify', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Tag modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_tag'])){
    $tag_id = intval($_GET['delete_tag']);

    mysqli_query($mysqli,"DELETE FROM tags WHERE tag_id = $tag_id AND company_id = $session_company_id");
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE tag_id = $tag_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Delete', log_description = '$tag_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Tag deleted";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

//Tax

if(isset($_POST['add_tax'])){

    $name = sanitizeInput($_POST['name']);
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"INSERT INTO taxes SET tax_name = '$name', tax_percent = $percent, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Create', log_description = '$name - $percent', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Tax added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_tax'])){

    $tax_id = intval($_POST['tax_id']);
    $name = sanitizeInput($_POST['name']);
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_name = '$name', tax_percent = $percent WHERE tax_id = $tax_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Modify', log_description = '$name - $percent', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Tax modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_tax'])){
    $tax_id = intval($_GET['archive_tax']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_archived_at = NOW() WHERE tax_id = $tax_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Archive', log_description = '$tax_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

    $_SESSION['alert_message'] = "Tax Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_tax'])){
    $tax_id = intval($_GET['delete_tax']);

    mysqli_query($mysqli,"DELETE FROM taxes WHERE tax_id = $tax_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Delete', log_description = '$tax_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Tax deleted";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

//End Tax

if(isset($_GET['dismiss_notification'])){

    $notification_id = intval($_GET['dismiss_notification']);

    mysqli_query($mysqli,"UPDATE notifications SET notification_dismissed_at = NOW(), notification_dismissed_by = $session_user_id WHERE notification_id = $notification_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Notification', log_action = 'Dismiss', log_description = '$session_name dismissed notification', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Notification Dismissed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['dismiss_all_notifications'])){

    $sql = mysqli_query($mysqli,"SELECT * FROM notifications WHERE company_id = $session_company_id AND notification_dismissed_at IS NULL");

    $num_notifications = mysqli_num_rows($sql);

    while($row = mysqli_fetch_array($sql)){
        $notification_id = intval($row['notification_id']);
        $notification_dismissed_at = sanitizeInput($row['notification_dismissed_at']);

        mysqli_query($mysqli,"UPDATE notifications SET notification_dismissed_at = NOW(), notification_dismissed_by = $session_user_id WHERE notification_id = $notification_id");

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Notification', log_action = 'Dismiss', log_description = '$session_name dismissed $num_notifications notifications', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "$num_notifications Notifications Dismissed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_expense'])){

    require_once('models/expense.php');

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', expense_currency_code = '$session_company_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference', company_id = $session_company_id");

    $expense_id = mysqli_insert_id($mysqli);

    // Check for and process attachment
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/$session_company_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        } else {
            $_SESSION['alert_type'] = "error";
            $extended_alert_description = '. Error uploading file. Check upload directory is writable/correct file type/size';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Create', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Expense added" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_expense'])){

    require_once('models/expense.php');

    $expense_id = intval($_POST['expense_id']);
    $existing_file_name = sanitizeInput($_POST['existing_file_name']);


    // Check for and process attachment
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/$session_company_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/expenses/$session_company_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        } else {
            $_SESSION['alert_type'] = "error";
            $extended_alert_description = '. Error uploading file. Check upload directory is writable/correct file type/size';
        }
    }

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', expense_account_id = $account, expense_vendor_id = $vendor, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference' WHERE expense_id = $expense_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Expense modified" . $extended_alert_description;

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Modify', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_expense'])){
    $expense_id = intval($_GET['delete_expense']);

    $sql = mysqli_query($mysqli,"SELECT * FROM expenses WHERE expense_id = $expense_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $expense_receipt = sanitizeInput($row['expense_receipt']);

    unlink("uploads/expenses/$session_company_id/$expense_receipt");

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Delete', log_description = '$epense_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Expense deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['export_expenses_csv'])){
    $date_from = sanitizeInput($_POST['date_from']);
    $date_to = sanitizeInput($_POST['date_to']);
    if(!empty($date_from) && !empty($date_to)){
        $date_query = "AND DATE(expense_date) BETWEEN '$date_from' AND '$date_to'";
        $file_name_date = "$date_from-to-$date_to";
    }else{
        $date_query = "";
        $file_name_date = date('Y-m-d');
    }

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM expenses
      LEFT JOIN categories ON expense_category_id = category_id
      LEFT JOIN vendors ON expense_vendor_id = vendor_id
      LEFT JOIN accounts ON expense_account_id = account_id
      WHERE expenses.company_id = $session_company_id
      AND expense_vendor_id > 0
      $date_query
      ORDER BY expense_date DESC
    ");

    if(mysqli_num_rows($sql) > 0){
        $delimiter = ",";
        $filename = "$session_company_name-Expenses-$file_name_date.csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Amount', 'Vendor', 'Description', 'Category', 'Account');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_assoc($sql)){
            $lineData = array($row['expense_date'], $row['expense_amount'], $row['vendor_name'], $row['expense_description'], $row['category_name'], $row['account_name']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Export', log_description = '$session_name exported expenses to CSV File', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

    exit;
}

if(isset($_POST['add_transfer'])){

    require_once('models/transfer.php');

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', expense_currency_code = '$session_company_currency', expense_vendor_id = 0, expense_category_id = 0, expense_account_id = $account_from, company_id = $session_company_id");
    $expense_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = '$amount', revenue_currency_code = '$session_company_currency', revenue_account_id = $account_to, revenue_category_id = 0, company_id = $session_company_id");
    $revenue_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO transfers SET transfer_expense_id = $expense_id, transfer_revenue_id = $revenue_id, transfer_notes = '$notes', company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Create', log_description = '$date - $amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Transfer complete";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_transfer'])){

    require_once('models/transfer.php');

    $transfer_id = intval($_POST['transfer_id']);
    $expense_id = intval($_POST['expense_id']);
    $revenue_id = intval($_POST['revenue_id']);

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', expense_account_id = $account_from WHERE expense_id = $expense_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = '$amount', revenue_account_id = $account_to WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"UPDATE transfers SET transfer_notes = '$notes' WHERE transfer_id = $transfer_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Modifed', log_description = '$date - $amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Transfer modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_transfer'])){
    $transfer_id = intval($_GET['delete_transfer']);

    //Query the transfer ID to get the Payment and Expense IDs, so we can delete those as well
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT * FROM transfers WHERE transfer_id = $transfer_id AND company_id = $session_company_id"));
    $expense_id = intval($row['transfer_expense_id']);
    $revenue_id = intval($row['transfer_revenue_id']);

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM transfers WHERE transfer_id = $transfer_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Delete', log_description = '$transfer_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Transfer deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_invoice'])){

    require_once('models/invoice.php');

    $client = intval($_POST['client']);

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients WHERE client_id = $client AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $client_net_terms = intval($row['client_net_terms']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client, company_id = $session_company_id");
    $invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'INVOICE added!', history_invoice_id = $invoice_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = '$config_invoice_prefix$invoice_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice added";

    header("Location: invoice.php?invoice_id=$invoice_id");
}

if(isset($_POST['edit_invoice'])){

    require_once('models/invoice.php');

    $invoice_id = intval($_POST['invoice_id']);
    $due = sanitizeInput($_POST['due']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_scope = '$scope', invoice_date = '$date', invoice_due = '$due', invoice_category_id = $category WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Modify', log_description = '$invoice_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_invoice_copy'])){

    $invoice_id = intval($_POST['invoice_id']);
    $date = sanitizeInput($_POST['date']);

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients, invoices WHERE client_id = invoice_client_id AND invoice_id = $invoice_id AND invoices.company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $client_net_terms = intval($row['client_net_terms']);

    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $invoice_note = sanitizeInput($row['invoice_note']);
    $client_id = intval($row['invoice_client_id']);
    $category_id = intval($row['invoice_category_id']);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$invoice_scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_amount = $invoice_amount, invoice_currency_code = '$invoice_currency_code', invoice_note = '$invoice_note', invoice_url_key = '$url_key', invoice_client_id = $client_id, company_id = $session_company_id") or die(mysql_error());

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Copied INVOICE!', history_invoice_id = $new_invoice_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $tax_id = intval($row['item_tax_id']);

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_tax_id = $tax_id, item_invoice_id = $new_invoice_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = 'Copied Invoice', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice copied";

    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if(isset($_POST['add_invoice_recurring'])){

    $invoice_id = intval($_POST['invoice_id']);
    $recurring_frequency = sanitizeInput($_POST['frequency']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_date = sanitizeInput($row['invoice_date']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_note = sanitizeInput($row['invoice_note']); //SQL Escape in case notes have , them
    $client_id = intval($row['invoice_client_id']);
    $category_id = intval($row['invoice_category_id']);

    //Get the last Recurring Number and add 1 for the new Recurring number
    $recurring_number = $config_recurring_next_number;
    $new_config_recurring_next_number = $config_recurring_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_next_number = $new_config_recurring_next_number WHERE company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_prefix = '$config_recurring_prefix', recurring_number = $recurring_number, recurring_scope = '$invoice_scope', recurring_frequency = '$recurring_frequency', recurring_next_date = DATE_ADD('$invoice_date', INTERVAL 1 $recurring_frequency), recurring_status = 1, recurring_amount = $invoice_amount, recurring_currency_code = '$invoice_currency_code', recurring_note = '$invoice_note', recurring_category_id = $category_id, recurring_client_id = $client_id, company_id = $session_company_id");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Recurring Created from INVOICE!', history_recurring_id = $recurring_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $tax_id = intval($row['item_tax_id']);

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_tax_id = $tax_id, item_recurring_id = $recurring_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = 'From recurring invoice', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Created recurring Invoice from this Invoice";

    header("Location: recurring_invoice.php?recurring_id=$recurring_id");

}

if(isset($_POST['add_quote'])){

    require_once('models/quote.php');

    $client = intval($_POST['client']);

    //Get the last Quote Number and add 1 for the new Quote number
    $quote_number = $config_quote_next_number;
    $new_config_quote_next_number = $config_quote_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_quote_next_number = $new_config_quote_next_number WHERE company_id = $session_company_id");

    //Generate a unique URL key for clients to access
    $quote_url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_currency_code = '$session_company_currency', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_client_id = $client, company_id = $session_company_id");

    $quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote created!', history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Create', log_description = '$quote_prefix$quote_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote added";

    header("Location: quote.php?quote_id=$quote_id");

}

if(isset($_POST['add_quote_copy'])){

    $quote_id = intval($_POST['quote_id']);
    $date = sanitizeInput($_POST['date']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $quote_number = $config_quote_next_number;
    $new_config_quote_next_number = $config_quote_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_quote_next_number = $new_config_quote_next_number WHERE company_id = $session_company_id");

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = floatval($row['quote_amount']);
    $quote_currency_code = sanitizeInput($row['quote_currency_code']);
    $quote_scope = sanitizeInput($row['quote_scope']);
    $quote_note = sanitizeInput($row['quote_note']);
    $client_id = intval($row['quote_client_id']);
    $category_id = intval($row['quote_category_id']);

    //Generate a unique URL key for clients to access
    $quote_url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$quote_scope', quote_date = '$date', quote_category_id = $category_id, quote_status = 'Draft', quote_amount = $quote_amount, quote_currency_code = '$quote_currency_code', quote_note = '$quote_note', quote_url_key = '$quote_url_key', quote_client_id = $client_id, company_id = $session_company_id");

    $new_quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote copied!', history_quote_id = $new_quote_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $tax_id = intval($row['item_tax_id']);

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_tax_id = $tax_id, item_quote_id = $new_quote_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Create', log_description = 'Copied Quote', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote copied";

    header("Location: quote.php?quote_id=$new_quote_id");

}

if(isset($_POST['add_quote_to_invoice'])){

    $quote_id = intval($_POST['quote_id']);
    $date = sanitizeInput($_POST['date']);
    $client_net_terms = intval($_POST['client_net_terms']);

    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = floatval($row['quote_amount']);
    $quote_currency_code = sanitizeInput($row['quote_currency_code']);
    $quote_scope = sanitizeInput($row['quote_scope']);
    $quote_note = sanitizeInput($row['quote_note']);

    $client_id = intval($row['quote_client_id']);
    $category_id = intval($row['quote_category_id']);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$quote_scope', invoice_date = '$date', invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_amount = $quote_amount, invoice_currency_code = '$quote_currency_code', invoice_note = '$quote_note', invoice_url_key = '$url_key', invoice_client_id = $client_id, company_id = $session_company_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote copied to Invoice!', history_invoice_id = $new_invoice_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $tax_id = intval($row['item_tax_id']);

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_tax_id = $tax_id, item_invoice_id = $new_invoice_id, company_id = $session_company_id");
    }

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Invoiced' WHERE quote_id = $quote_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Create', log_description = 'Quote copied to Invoice', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote copied to Invoice";

    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if(isset($_POST['add_quote_item'])){

    $quote_id = intval($_POST['quote_id']);

    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $subtotal = $price * $qty;

    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_quote_id = $quote_id, company_id = $session_company_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_quote_amount = floatval($row['quote_amount']) + $total;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Item added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['quote_note'])){

    $quote_id = intval($_POST['quote_id']);
    $note = sanitizeInput($_POST['note']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_note = '$note' WHERE quote_id = $quote_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Notes added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_quote'])){

    require_once('models/quote.php');

    $quote_id = intval($_POST['quote_id']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_scope = '$scope', quote_date = '$date', quote_category_id = $category WHERE quote_id = $quote_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modify', log_description = '$quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_quote'])){
    $quote_id = intval($_GET['delete_quote']);

    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");

    //Delete Items Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    }

    //Delete History Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_quote_id = $quote_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id AND company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Delete', log_description = '$quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quotes deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_quote_item'])){
    $item_id = intval($_GET['delete_quote_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $quote_id = intval($row['item_quote_id']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_quote_amount = floatval($row['quote_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote Item', log_action = 'Delete', log_description = '$item_id from $quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Item deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['mark_quote_sent'])){

    $quote_id = intval($_GET['mark_quote_sent']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'QUOTE marked sent', history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Update', log_description = '$quote_id marked sent', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote marked sent";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['accept_quote'])){

    $quote_id = intval($_GET['accept_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Accepted', history_description = 'Quote accepted!', history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modify', log_description = 'Accepted Quote $quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote accepted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['decline_quote'])){

    $quote_id = intval($_GET['decline_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Cancelled', history_description = 'Quote declined!', history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modify', log_description = 'Declined Quote $quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Quote declined";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['email_quote'])){
    $quote_id = intval($_GET['email_quote']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    LEFT JOIN contacts ON contact_id = primary_contact
    LEFT JOIN companies ON quotes.company_id = companies.company_id
    WHERE quote_id = $quote_id
    AND quotes.company_id = $session_company_id"
    );

    $row = mysqli_fetch_array($sql);
    $quote_id = intval($row['quote_id']);
    $quote_prefix = sanitizeInput($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $quote_scope = sanitizeInput($row['quote_scope']);
    $quote_status = sanitizeInput($row['quote_status']);
    $quote_date = sanitizeInput($row['quote_date']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_note = sanitizeInput($row['quote_note']);
    $quote_url_key = sanitizeInput($row['quote_url_key']);
    $quote_currency_code = sanitizeInput($row['quote_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
    $client_website = sanitizeInput($row['client_website']);
    $company_name = sanitizeInput($row['company_name']);
    $company_country = sanitizeInput($row['company_country']);
    $company_address = sanitizeInput($row['company_address']);
    $company_city = sanitizeInput($row['company_city']);
    $company_state = sanitizeInput($row['company_state']);
    $company_zip = sanitizeInput($row['company_zip']);
    $company_phone = formatPhoneNumber($row['company_phone']);
    $company_email = sanitizeInput($row['company_email']);
    $company_website = sanitizeInput($row['company_website']);
    $company_logo = sanitizeInput($row['company_logo']);

    $subject = "Quote [$quote_scope]";
    $body    = "Hello $contact_name,<br><br>Thank you for your inquiry, we are pleased to provide you with the following estimate.<br><br><br>$quote_scope<br>Total Cost: " . numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) . "<br><br><br>View and accept your estimate online <a href='https://$config_base_url/guest_view_quote.php?quote_id=$quote_id&url_key=$quote_url_key'>here</a><br><br><br>~<br>$company_name<br>Sales<br>$config_quote_from_email<br>$company_phone";

    $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
        $config_quote_from_email, $config_quote_from_name,
        $contact_email, $contact_name,
        $subject, $body);

    // Logging
    if ($mail === true) {
        mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Quote!', history_quote_id = $quote_id, company_id = $session_company_id");
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Email', log_description = '$quote_id emailed to $contact_email', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "Quote has been sent";
    } else {
        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Error sending quote";
    }

    //Don't change the status to sent if the status is anything but draft
    if($quote_status == 'Draft'){
        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id AND company_id = $session_company_id");
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_recurring'])){

    $client = intval($_POST['client']);
    $frequency = sanitizeInput($_POST['frequency']);
    $start_date = sanitizeInput($_POST['start_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);

    //Get the last Recurring Number and add 1 for the new Recurring number
    $recurring_number = $config_recurring_next_number;
    $new_config_recurring_next_number = $config_recurring_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_next_number = $new_config_recurring_next_number WHERE company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_prefix = '$config_recurring_prefix', recurring_number = $recurring_number, recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_next_date = '$start_date', recurring_category_id = $category, recurring_status = 1, recurring_currency_code = '$session_company_currency', recurring_client_id = $client, company_id = $session_company_id");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Active', history_description = 'Recurring Invoice created!', history_recurring_id = $recurring_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Create', log_description = '$start_date - $category', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Recurring Invoice added";

    header("Location: recurring_invoice.php?recurring_id=$recurring_id");

}

if(isset($_POST['edit_recurring'])){

    $recurring_id = intval($_POST['recurring_id']);
    $frequency = sanitizeInput($_POST['frequency']);
    $next_date = sanitizeInput($_POST['next_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);
    $status = intval($_POST['status']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_next_date = '$next_date', recurring_category_id = $category, recurring_status = $status WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$status', history_description = 'Recurring modified', history_recurring_id = $recurring_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Modify', log_description = '$recurring_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Recurring Invoice modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_recurring'])){
    $recurring_id = intval($_GET['delete_recurring']);

    mysqli_query($mysqli,"DELETE FROM recurring WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    //Delete Items Associated with the Recurring
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_recurring_id = $recurring_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id AND company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Delete', log_description = '$recurring_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Recurring Invoice deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_recurring_item'])){

    $recurring_id = intval($_POST['recurring_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $subtotal = $price * $qty;

    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_recurring_id = $recurring_id, company_id = $session_company_id");

    //Update Recurring Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_recurring_amount = floatval($row['recurring_amount']) + $total;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = $new_recurring_amount WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Recurring Invoice Updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['recurring_note'])){

    $recurring_id = intval($_POST['recurring_id']);
    $note = sanitizeInput($_POST['note']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_note = '$note' WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Notes added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_recurring_item'])){
    $item_id = intval($_GET['delete_recurring_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $recurring_id = intval($row['item_recurring_id']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_recurring_amount = floatval($row['recurring_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = $new_recurring_amount WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring Item', log_action = 'Delete', log_description = 'Item ID $item_id from Recurring ID $recurring_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Item deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['mark_invoice_sent'])){

    $invoice_id = intval($_GET['mark_invoice_sent']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'INVOICE marked sent', history_invoice_id = $invoice_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Update', log_description = '$invoice_id marked sent', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice marked sent";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['cancel_invoice'])){

    $invoice_id = intval($_GET['cancel_invoice']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Cancelled' WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Cancelled', history_description = 'INVOICE cancelled!', history_invoice_id = $invoice_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Modify', log_description = 'Cancelled', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice cancelled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice'])){
    $invoice_id = intval($_GET['delete_invoice']);

    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    //Delete Items Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id AND company_id = $session_company_id");
    }

    //Delete Payments Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $payment_id = intval($row['payment_id']);
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id AND company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Delete', log_description = '$invoice_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_invoice_item'])){

    $invoice_id = intval($_POST['invoice_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $subtotal = $price * $qty;

    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_invoice_id = $invoice_id, company_id = $session_company_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_invoice_amount = floatval($row['invoice_amount']) + $total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Item added";


    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['invoice_note'])){

    $invoice_id = intval($_POST['invoice_id']);
    $note = sanitizeInput($_POST['note']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_note = '$note' WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Notes added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_item'])){

    $invoice_id = intval($_POST['invoice_id']);
    $quote_id = intval($_POST['quote_id']);
    $recurring_id = intval($_POST['recurring_id']);
    $item_id = intval($_POST['item_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $subtotal = $price * $qty;

    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"UPDATE invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id WHERE item_id = $item_id");

    if($invoice_id > 0){
        //Update Invoice Balances by tallying up invoice items
        $sql_invoice_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS invoice_total FROM invoice_items WHERE item_invoice_id = $invoice_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_invoice_total);
        $new_invoice_amount = floatval($row['invoice_total']);

        mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    }elseif($quote_id > 0){
        //Update Quote Balances by tallying up items
        $sql_quote_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS quote_total FROM invoice_items WHERE item_quote_id = $quote_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_quote_total);
        $new_quote_amount = floatval($row['quote_total']);

        mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id AND company_id = $session_company_id");

    }else{
        //Update Invoice Balances by tallying up invoice items

        $sql_recurring_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_total FROM invoice_items WHERE item_recurring_id = $recurring_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_recurring_total);
        $new_recurring_amount = floatval($row['recurring_total']);

        mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = $new_recurring_amount WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    }

    $_SESSION['alert_message'] = "Item updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice_item'])){
    $item_id = intval($_GET['delete_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['item_invoice_id']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_invoice_amount = floatval($row['invoice_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice Item', log_action = 'Delete', log_description = '$item_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Item deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_payment'])){

    $invoice_id = intval($_POST['invoice_id']);
    $balance = floatval($_POST['balance']);
    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference = sanitizeInput($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    //Check to see if amount entered is greater than the balance of the invoice
    if($amount > $balance){
        $_SESSION['alert_message'] = "Payment is more than the balance";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }else{
        mysqli_query($mysqli,"INSERT INTO payments SET payment_date = '$date', payment_amount = $amount, payment_currency_code = '$currency_code', payment_account_id = $account, payment_method = '$payment_method', payment_reference = '$reference', payment_invoice_id = $invoice_id, company_id = $session_company_id");

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payments_amount FROM payments WHERE payment_invoice_id = $invoice_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_total_payments_amount);
        $total_payments_amount = floatval($row['payments_amount']);

        //Get the invoice total
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON contact_id = primary_contact
            LEFT JOIN companies ON invoices.company_id = companies.company_id
            WHERE invoice_id = $invoice_id
            AND invoices.company_id = $session_company_id"
        );

        $row = mysqli_fetch_array($sql);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_prefix = sanitizeInput($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_url_key = sanitizeInput($row['invoice_url_key']);
        $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $contact_phone = formatPhoneNumber($row['contact_phone']);
        $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
        $contact_mobile = formatPhoneNumber($row['contact_mobile']);
        $company_name = sanitizeInput($row['company_name']);
        $company_country = sanitizeInput($row['company_country']);
        $company_address = sanitizeInput($row['company_address']);
        $company_city = sanitizeInput($row['company_city']);
        $company_state = sanitizeInput($row['company_state']);
        $company_zip = sanitizeInput($row['company_zip']);
        $company_phone = formatPhoneNumber($row['company_phone']);
        $company_email = sanitizeInput($row['company_email']);
        $company_website = sanitizeInput($row['company_website']);
        $company_logo = sanitizeInput($row['company_logo']);

        //Calculate the Invoice balance
        $invoice_balance = $invoice_amount - $total_payments_amount;

        //Determine if invoice has been paid then set the status accordingly
        if($invoice_balance == 0){
            $invoice_status = "Paid";
            if($email_receipt == 1){


                $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
                $body    = "Hello $contact_name,<br><br>We have received your payment in the amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " for invoice <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "<br>Balance: " . numfmt_format_currency($currency_format, $invoice_balance, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                    $config_invoice_from_email, $config_invoice_from_name,
                    $contact_email, $contact_name,
                    $subject, $body);

                // Email Logging
                if ($mail === true) {
                    $_SESSION['alert_message'] .= "Email receipt sent ";

                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Receipt!', history_invoice_id = $invoice_id, company_id = $session_company_id");
                } else {
                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Email Receipt Failed!', history_invoice_id = $invoice_id, company_id = $session_company_id");
                    $_SESSION['alert_message'] .= "Mailer Error ";

                    mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
                    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
                }

            }
        }else{
            $invoice_status = "Partial";
            if($email_receipt == 1){


                $subject = "Partial Payment Recieved - Invoice $invoice_prefix$invoice_number";
                $body    = "Hello $contact_name,<br><br>We have recieved partial payment in the amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " and it has been applied to invoice <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "<br>Balance: " . numfmt_format_currency($currency_format, $invoice_balance, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                    $config_invoice_from_email, $config_invoice_from_name,
                    $contact_email, $contact_name,
                    $subject, $body);

                // Email Logging
                if ($mail === true) {
                    $_SESSION['alert_message'] .= "Email receipt sent ";

                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Receipt!', history_invoice_id = $invoice_id, company_id = $session_company_id");
                } else {
                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Email Receipt Failed!', history_invoice_id = $invoice_id, company_id = $session_company_id");
                    $_SESSION['alert_message'] .= "Mailer Error ";

                    mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
                    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
                }

            }

        }

        //Update Invoice Status
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

        //Add Payment to History
        mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment added', history_invoice_id = $invoice_id, company_id = $session_company_id");

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Payment', log_action = 'Create', log_description = '$payment_amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] .= "Payment added";

        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['delete_payment'])){
    $payment_id = intval($_GET['delete_payment']);

    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_id = $payment_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['payment_invoice_id']);
    $deleted_payment_amount = floatval($row['payment_amount']);

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments_amount FROM payments WHERE payment_invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql_total_payments_amount);
    $total_payments_amount = floatval($row['total_payments_amount']);

    //Get the invoice total
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_amount = floatval($row['invoice_amount']);

    //Calculate the Invoice balance
    $invoice_balance = $invoice_amount - $total_payments_amount + $deleted_payment_amount;

    //Determine if invoice has been paid
    if($invoice_balance == 0){
        $invoice_status = "Paid";
    }else{
        $invoice_status = "Partial";
    }

    //Update Invoice Status
    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    //Add Payment to History
    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment deleted', history_invoice_id = $invoice_id, company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Payment', log_action = 'Delete', log_description = '$payment_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Payment deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['email_invoice'])){
    $invoice_id = intval($_GET['email_invoice']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON contact_id = primary_contact
        LEFT JOIN companies ON invoices.company_id = companies.company_id
        WHERE invoice_id = $invoice_id"
    );

    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = $row['invoice_prefix'];
    $invoice_number = $row['invoice_number'];
    $invoice_status = $row['invoice_status'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_url_key = $row['invoice_url_key'];
    $invoice_currency_code = $row['invoice_currency_code'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $contact_name = $row['contact_name'];
    $contact_email = $row['contact_email'];
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = $row['contact_extension'];
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
    $client_website = $row['client_website'];

    $company_name = $row['company_name'];
    $company_country = $row['company_country'];
    $company_address = $row['company_address'];
    $company_city = $row['company_city'];
    $company_state = $row['company_state'];
    $company_zip = $row['company_zip'];
    $company_phone = formatPhoneNumber($row['company_phone']);
    $company_email = $row['company_email'];
    $company_website = $row['company_website'];
    $company_logo = $row['company_logo'];

    $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id AND payments.company_id = $session_company_id ORDER BY payment_id DESC");

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = $row['amount_paid'];

    $balance = $invoice_amount - $amount_paid;

    if($invoice_status == 'Paid') {
        $subject = "Invoice $invoice_prefix$invoice_number Copy";
        $body    = "Hello $contact_name,<br><br>Please click on the link below to see your invoice marked <b>paid</b>.<br><br><a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>Invoice Link</a><br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone";

    } else {

        $subject = "Invoice $invoice_prefix$invoice_number";
        $body    = "Hello $contact_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br>Balance Due: " . numfmt_format_currency($currency_format, $balance, $invoice_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice click <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone";
    }

    $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
        $config_invoice_from_email, $config_invoice_from_name,
        $contact_email, $contact_name,
        $subject, $body);

    if ($mail === true) {
        $_SESSION['alert_message'] = "Invoice has been sent";
        mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed invoice', history_invoice_id = $invoice_id, company_id = $session_company_id");

        //Don't chnage the status to sent if the status is anything but draft
        if($invoice_status == 'Draft'){
            mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Email', log_description = 'Invoice $invoice_prefix$invoice_number emailed to $client_email', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    } else {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Invoice Failed to send ";
        mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Email Invoice Failed', history_invoice_id = $invoice_id, company_id = $session_company_id");

        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_revenue'])){

    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$currency_code', revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_category_id = $category, revenue_account_id = $account, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Create', log_description = '$date - $amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Revenue added!";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_revenue'])){

    $revenue_id = intval($_POST['revenue_id']);
    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$currency_code', revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_category_id = $category, revenue_account_id = $account WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Modify', log_description = '$revenue_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Revenue modified!";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_revenue'])){
    $revenue_id = intval($_GET['delete_revenue']);

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Delete', log_description = '$revenue_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Revenue deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// Client Section

if(isset($_POST['add_contact'])){

    validateTechRole();

    require_once('models/contact.php');

    $password = password_hash(randomString(), PASSWORD_DEFAULT);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_auth_method = '$auth_method', contact_password_hash = '$password', contact_department = '$department', contact_location_id = $location_id, contact_client_id = $client_id, company_id = $session_company_id");

    $contact_id = mysqli_insert_id($mysqli);

    //Update Primary contact in clients if primary contact is checked
    if($primary_contact > 0){
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$session_company_id/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");
            $extended_alert_description = '. File successfully uploaded.';
        } else {
            $_SESSION['alert_type'] = "error";
            $extended_alert_description = '. Error uploading file. Check upload directory is writable/correct file type/size';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Create', log_description = '$session_name created contact $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Contact <strong>$name</strong> created" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_contact'])){

    validateTechRole();

    require_once('models/contact.php');

    $contact_id = intval($_POST['contact_id']);
    $existing_file_name = sanitizeInput($_POST['existing_file_name']);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_auth_method = '$auth_method', contact_department = '$department', contact_location_id = $location_id WHERE contact_id = $contact_id AND company_id = $session_company_id");

    // Update Primary contact in clients if primary contact is checked
    if ($primary_contact > 0){
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
    }

    // Set password
    if(!empty($_POST['contact_password'])){
        $password_hash = password_hash($_POST['contact_password'], PASSWORD_DEFAULT);
        mysqli_query($mysqli, "UPDATE contacts SET contact_password_hash = '$password_hash' WHERE contact_id = '$contact_id' AND contact_client_id = '$client_id'");
    }

    // Send contact a welcome e-mail, if specified
    if(isset($_POST['send_email']) && !empty($auth_method) && !empty($config_smtp_host)){

        if($auth_method == 'azure') {
            $password_info = "Login with your Microsoft (Azure AD) account.";
        } else {
            $password_info = $_POST['contact_password'];
        }

        $subject = "Your new $session_company_name ITFlow account";
        $body = "Hello, $name<br><br>An ITFlow account has been set up for you. <br><br>Username: $email <br>Password: $password_info<br><br>Login URL: https://$config_base_url/portal/<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_ticket_from_email, $config_ticket_from_name,
            $email, $name,
            $subject, $body);

        if ($mail !== true) {
            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $email', company_id = $session_company_id");
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
        }

    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            // Set directory in which the uploaded file will be moved
            $file_tmp_path = $_FILES['file']['tmp_name'];
            $upload_file_dir = "uploads/clients/$session_company_id/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/clients/$session_company_id/$client_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");

            $extended_alert_description = '. Photo successfully uploaded. ';
        } else {
            $extended_alert_description = '. Error uploading photo.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = '$session_name modified contact $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Contact <strong>$name</strong> updated" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_contact'])){

    validateTechRole();

    $contact_id = intval($_GET['archive_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NOW() WHERE contact_id = $contact_id AND company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Archive', log_description = '$session_name archived contact $contact_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_contact'])){

    validateAdminRole();

    $contact_id = intval($_GET['delete_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Delete', log_description = '$session_name deleted contact $contact_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> deleted.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['export_client_contacts_csv'])){
    $client_id = intval($_GET['export_client_contacts_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    //Contacts
    $sql = mysqli_query($mysqli,"SELECT * FROM contacts LEFT JOIN locations ON location_id = contact_location_id WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0){
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Contacts-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Ext', 'Mobile', 'Location');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['contact_name'], $row['contact_title'], $row['contact_department'], $row['contact_email'], formatPhoneNumber($row['contact_phone']), $row['contact_extension'], formatPhoneNumber($row['contact_mobile']), $row['location_name']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Export', log_description = '$session_name exported $num_rows contact(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

if(isset($_POST["import_client_contacts_csv"])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = false;

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if(in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif($_FILES["file"]["size"] < 1){
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if(!$error & count($f_columns) != 8) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if(!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false){
            $duplicate_detect = 0;
            if(isset($column[0])){
                $name = sanitizeInput($column[0]);
                if(mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_name = '$name' AND contact_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if(isset($column[1])){
                $title = sanitizeInput($column[1]);
            }
            if(isset($column[2])){
                $department = sanitizeInput($column[2]);
            }
            if(isset($column[3])){
                $email = sanitizeInput($column[3]);
            }
            if(isset($column[4])){
                $phone = preg_replace("/[^0-9]/", '',$column[4]);
            }
            if(isset($column[5])){
                $ext = preg_replace("/[^0-9]/", '',$column[5]);
            }
            if(isset($column[6])){
                $mobile = preg_replace("/[^0-9]/", '',$column[6]);
            }
            if(isset($column[7])){
                $location = sanitizeInput($column[7]);
                $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
                $row = mysqli_fetch_assoc($sql_location);
                $location_id = intval($row['location_id']);
            }
            // Potentially import the rest in the future?


            // Check if duplicate was detected
            if($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_department = '$department', contact_email = '$email', contact_phone = '$phone', contact_extension = '$ext', contact_mobile = '$mobile', contact_location_id = $location_id, contact_client_id = $client_id, company_id = $session_company_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Import', log_description = '$session_name imported $row_count contact(s) via CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "$row_count Contact(s) added, $duplicate_count duplicate(s) detected";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['download_client_contacts_csv_template'])){
    $client_id = intval($_GET['download_client_contacts_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Contacts-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array(
        'Full Name           ',
        'Job Title           ',
        'Department Name     ',
        'Email Address       ',
        'Office Phone        ',
        'Office Extension    ',
        'Mobile Phone        ',
        'Office Location     '
    );
    fputcsv($f, $fields, $delimiter);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}

// 2022-05-14 Johnny Left Off Adding log_entity_id and logs / alert cleanups import / archive etc

if(isset($_POST['add_location'])){

    validateAdminRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $country = sanitizeInput($_POST['country']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $hours = sanitizeInput($_POST['hours']);
    $notes = sanitizeInput($_POST['notes']);
    $contact = intval($_POST['contact']);
    $primary_location = intval($_POST['primary_location']);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact, location_client_id = $client_id, company_id = $session_company_id");

    $location_id = mysqli_insert_id($mysqli);

    //Update Primay location in clients if primary location is checked
    if($primary_location > 0){
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");
    }

    //Check to see if a file is attached
    if($_FILES['file']['tmp_name'] != ''){

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

        // sanitize file-name
        $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'gif', 'png');

        if(in_array($file_extension,$allowed_file_extensions) === false){
            $file_error = 1;
        }

        //Check File Size
        if($file_size > 2097152){
            $file_error = 1;
        }

        if($file_error == 0){
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$session_company_id/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE locations SET location_photo = '$new_file_name' WHERE location_id = $location_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Create', log_description = '$session_name created location $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id, company_id = $session_company_id");

    $_SESSION['alert_message'] .= "Location <strong>$name</strong> created.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_location'])){

    validateAdminRole();

    $location_id = intval($_POST['location_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $country = sanitizeInput($_POST['country']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $hours = sanitizeInput($_POST['hours']);
    $notes = sanitizeInput($_POST['notes']);
    $contact = intval($_POST['contact']);
    $primary_location = intval($_POST['primary_location']);

    $existing_file_name = sanitizeInput($_POST['existing_file_name']);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"UPDATE locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact WHERE location_id = $location_id AND company_id = $session_company_id");

    //Update Primay location in clients if primary location is checked
    if($primary_location > 0){
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");
    }

    //Check to see if a file is attached
    if($_FILES['file']['tmp_name'] != ''){

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

        // sanitize file-name
        $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'gif', 'png');

        if(in_array($file_extension,$allowed_file_extensions) === false){
            $file_error = 1;
        }

        //Check File Size
        if($file_size > 2097152){
            $file_error = 1;
        }

        if($file_error == 0){
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$session_company_id/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/clients/$session_company_id/$client_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE locations SET location_photo = '$new_file_name' WHERE location_id = $location_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Modify', log_description = '$session_name modified location $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id, company_id = $session_company_id");

    $_SESSION['alert_message'] .= "Location <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_location'])){

    validateTechRole();

    $location_id = intval($_GET['archive_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NOW() WHERE location_id = $location_id AND company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Archive', log_description = '$session_name archived location $location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['undo_archive_location'])){

    $location_id = intval($_GET['undo_archive_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NULL WHERE location_id = $location_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Undo Archive', log_description = '$session_name restored location $location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['delete_location'])){

    validateAdminRole();

    $location_id = intval($_GET['delete_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"DELETE FROM locations WHERE location_id = $location_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Delete', log_description = '$session_name deleted location $location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['export_client_locations_csv'])){
    $client_id = intval($_GET['export_client_locations_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = sanitizeInput($row['client_name']);

    //Locations
    $sql = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL AND company_id = $session_company_id ORDER BY location_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Locations-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Hours');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['location_name'], $row['location_address'], $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_phone'], $row['location_hours']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Export', log_description = '$session_name exported $num_rows location(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

if(isset($_POST["import_client_locations_csv"])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = false;

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if(in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif($_FILES["file"]["size"] < 1){
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if(!$error & count($f_columns) != 7) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if(!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false){
            $duplicate_detect = 0;
            if(isset($column[0])){
                $name = sanitizeInput($column[0]);
                if(mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$name' AND location_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if(isset($column[1])){
                $address = sanitizeInput($column[1]);
            }
            if(isset($column[2])){
                $city = sanitizeInput($column[2]);
            }
            if(isset($column[3])){
                $state = sanitizeInput($column[3]);
            }
            if(isset($column[4])){
                $zip = sanitizeInput($column[4]);
            }
            if(isset($column[5])){
                $phone = preg_replace("/[^0-9]/", '',$column[5]);
            }
            if(isset($column[6])){
                $hours = sanitizeInput($column[6]);
            }

            // Check if duplicate was detected
            if($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_client_id = $client_id, company_id = $session_company_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Import', log_description = '$session_name imported $row_count location(s) via CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', company_id = $session_company_id, log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "$row_count Location(s) imported, $duplicate_count duplicate(s) detected and not imported";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['download_client_locations_csv_template'])){
    $client_id = intval($_GET['download_client_locations_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Locations-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Hours');
    fputcsv($f, $fields, $delimiter);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}

if(isset($_POST['add_asset'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $type = sanitizeInput($_POST['type']);
    $make = sanitizeInput($_POST['make']);
    $model = sanitizeInput($_POST['model']);
    $serial = sanitizeInput($_POST['serial']);
    $os = sanitizeInput($_POST['os']);
    $ip = sanitizeInput($_POST['ip']);
    $mac = sanitizeInput($_POST['mac']);
    $status = sanitizeInput($_POST['status']);
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $network = intval($_POST['network']);
    $purchase_date = sanitizeInput($_POST['purchase_date']);
    if(empty($purchase_date)){
        $purchase_date = "NULL";
    } else {
        $purchase_date = "'" . $purchase_date . "'";
    }
    $warranty_expire = sanitizeInput($_POST['warranty_expire']);
    if(empty($warranty_expire)){
        $warranty_expire = "NULL";
    } else {
        $warranty_expire = "'" . $warranty_expire . "'";
    }
    $install_date = sanitizeInput($_POST['install_date']);
    if(empty($install_date)){
        $install_date = "NULL";
    } else {
        $install_date = "'" . $install_date . "'";
    }
    $notes = sanitizeInput($_POST['notes']);

    $alert_extended = "";

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network, asset_client_id = $client_id, company_id = $session_company_id");

    $asset_id = mysqli_insert_id($mysqli);

    if (!empty($_POST['username'])) {
        $username = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['username'])));
        $password = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_asset_id = $asset_id, login_client_id = $client_id, company_id = $session_company_id");

        $login_id = mysqli_insert_id($mysqli);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Create', log_description = '$session_name created login credentials for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id, company_id = $session_company_id");

        $alert_extended = " along with login credentials";

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Create', log_description = '$session_name created asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> created $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_asset'])){

    validateTechRole();

    $asset_id = intval($_POST['asset_id']);
    $login_id = intval($_POST['login_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $type = sanitizeInput($_POST['type']);
    $make = sanitizeInput($_POST['make']);
    $model = sanitizeInput($_POST['model']);
    $serial = sanitizeInput($_POST['serial']);
    $os = sanitizeInput($_POST['os']);
    $ip = sanitizeInput($_POST['ip']);
    $mac = sanitizeInput($_POST['mac']);
    $status = sanitizeInput($_POST['status']);
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $network = intval($_POST['network']);
    $purchase_date = sanitizeInput($_POST['purchase_date']);
    if(empty($purchase_date)){
        $purchase_date = "NULL";
    } else {
        $purchase_date = "'" . $purchase_date . "'";
    }
    $warranty_expire = sanitizeInput($_POST['warranty_expire']);
    if(empty($warranty_expire)){
        $warranty_expire = "NULL";
    } else {
        $warranty_expire = "'" . $warranty_expire . "'";
    }
    $install_date = sanitizeInput($_POST['install_date']);
    if(empty($install_date)){
        $install_date = "NULL";
    } else {
        $install_date = "'" . $install_date . "'";
    }
    $notes = sanitizeInput($_POST['notes']);
    $username = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['username'])));
    $password = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['password'])));

    $alert_extended = "";

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network WHERE asset_id = $asset_id AND company_id = $session_company_id");

    //If login exists then update the login
    if($login_id > 0 && !empty($_POST['username'])){
        mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_username = '$username', login_password = '$password' WHERE login_id = $login_id AND company_id = $session_company_id");

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Modify', log_description = '$session_name updated login credentials for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id, company_id = $session_company_id");

        $alert_extended = " along with updating login credentials";
    }else{
        //If Username is filled in then add a login
        if(!empty($_POST['username'])) {

            mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_asset_id = $asset_id, login_client_id = $client_id, company_id = $session_company_id");

            $login_id = mysqli_insert_id($mysqli);

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Create', log_description = '$session_name created login credentials for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id, company_id = $session_company_id");

            $alert_extended = " along with creating login credentials";

        } else {
            mysqli_query($mysqli,"DELETE FROM logins WHERE login_id = $login_id AND company_id = $session_company_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Delete', log_description = '$session_name deleted login credential for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id, company_id = $session_company_id");

            $alert_extended = " along with deleting login credentials";
        }

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Modify', log_description = '$session_name modified asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> updated $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_asset'])){

    validateTechRole();

    $asset_id = intval($_GET['archive_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id AND company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Archive', log_description = '$session_name archived asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_asset'])){

    validateAdminRole();

    $asset_id = intval($_GET['delete_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Delete', log_description = '$session_name deleted asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST["import_client_assets_csv"])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = false;

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if(in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif($_FILES["file"]["size"] < 1){
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count (name, type, make, model, serial, os)
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if(!$error & count($f_columns) != 8) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if(!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false){
            $duplicate_detect = 0;
            if(isset($column[0])){
                $name = sanitizeInput($column[0]);
                if(mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_name = '$name' AND asset_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if(isset($column[1])){
                $type = sanitizeInput($column[1]);
            }
            if(isset($column[2])){
                $make = sanitizeInput($column[2]);
            }
            if(isset($column[3])){
                $model = sanitizeInput($column[3]);
            }
            if(isset($column[4])){
                $serial = sanitizeInput($column[4]);
            }
            if(isset($column[5])){
                $os = sanitizeInput(column[5]);
            }
            if(isset($column[6])){
                $contact = sanitizeInput($column[6]);
                $sql_contact = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_name = '$contact' AND contact_client_id = $client_id");
                $row = mysqli_fetch_assoc($sql_contact);
                $contact_id = intval($row['contact_id']);
            }
            if(isset($column[7])){
                $location = sanitizeInput($column[7]);
                $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
                $row = mysqli_fetch_assoc($sql_location);
                $location_id = intval($row['location_id']);
            }

            // Check if duplicate was detected
            if($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_contact_id = $contact_id, asset_location_id = $location_id, asset_client_id = $client_id, company_id = $session_company_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Import', log_description = '$session_name imported $row_count asset(s) via CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', company_id = $session_company_id, log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "$row_count Asset(s) added, $duplicate_count duplicate(s) detected";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['download_client_assets_csv_template'])){
    $client_id = intval($_GET['download_client_assets_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Assets-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Type', 'Make', 'Model', 'Serial', 'OS', 'Assigned To', 'Location');
    fputcsv($f, $fields, $delimiter);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}

if(isset($_GET['export_client_assets_csv'])){

    validateTechRole();

    $client_id = intval($_GET['export_client_assets_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN clients ON asset_client_id = client_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0){
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Assets-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Type', 'Make', 'Model', 'Serial Number', 'Operating System', 'Purchase Date', 'Warranty Expire', 'Install Date', 'Assigned To', 'Location', 'Notes');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_array($sql)){
            $lineData = array($row['asset_name'], $row['asset_type'], $row['asset_make'], $row['asset_model'], $row['asset_serial'], $row['asset_os'], $row['asset_purchase_date'], $row['asset_warranty_expire'], $row['asset_install_date'], $row['contact_name'], $row['location_name'], $row['asset_notes']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Export', log_description = '$session_name exported $num_rows asset(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

// Client Software/License

// Templatee

if(isset($_POST['add_software_template'])){

    validateTechRole();

    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_type = '$type', software_license_type = '$license_type', software_notes = '$notes', software_template = 1, software_client_id = 0, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software Template', log_action = 'Create', log_description = '$session_user_name created software template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Software template created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_software_template'])){

    validateTechRole();

    $software_id = intval($_POST['software_id']);
    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_version = '$version', software_type = '$type', software_license_type = '$license_type', software_notes = '$notes' WHERE software_id = $software_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software Teplate', log_action = 'Modify', log_description = '$session_name modified software template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Software template updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_software_from_template'])){

    // GET POST Data
    $client_id = intval($_POST['client_id']);
    $software_template_id = intval($_POST['software_template_id']);

    // GET Software Info
    $sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE software_id = $software_template_id AND company_id = $session_company_id");

    $row = mysqli_fetch_array($sql_software);

    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);

    // Software add query
    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_type = '$type', software_license_type = '$license_type', software_notes = '$notes', software_client_id = $client_id, company_id = $session_company_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Create', log_description = 'Software created from template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Software created from template";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_software'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);
    $key = sanitizeInput($_POST['key']);
    $seats = intval($_POST['seats']);
    $purchase = sanitizeInput($_POST['purchase']);
    if(empty($purchase)){
        $purchase = "NULL";
    } else {
        $purchase = "'" . $purchase . "'";
    }
    $expire = sanitizeInput($_POST['expire']);
    if(empty($expire)){
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_type = '$type', software_key = '$key', software_license_type = '$license_type', software_seats = $seats, software_purchase = $purchase, software_expire = $expire, software_notes = '$notes', software_client_id = $client_id, company_id = $session_company_id");

    $software_id = mysqli_insert_id($mysqli);

    $alert_extended = "";

    // Add Asset Licenses
    if(!empty($_POST['assets'])){
        foreach($_POST['assets'] as $asset){
            $asset_id = intval($asset);
            mysqli_query($mysqli,"INSERT INTO software_assets SET software_id = $software_id, asset_id = $asset_id");
        }
    }

    // Add Contact Licenses
    if(!empty($_POST['contacts'])){
        foreach($_POST['contacts'] as $contact){
            $contact = intval($contact);
            mysqli_query($mysqli,"INSERT INTO software_contacts SET software_id = $software_id, contact_id = $contact");
        }
    }

    if(!empty($_POST['username'])) {
        $username = sanitizeInput(encryptLoginEntry($_POST['username']));
        $password = sanitizeInput(encryptLoginEntry($_POST['password']));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_software_id = $software_id, login_client_id = $client_id, company_id = $session_company_id");

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Create', log_description = '$session_name created software $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $software_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Software <strong>$name</strong> created $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_software'])){

    validateTechRole();

    $software_id = intval($_POST['software_id']);
    $login_id = intval($_POST['login_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);
    $key = sanitizeInput($_POST['key']);
    $seats = intval($_POST['seats']);
    $purchase = sanitizeInput($_POST['purchase']);
    if(empty($purchase)){
        $purchase = "NULL";
    } else {
        $purchase = "'" . $purchase . "'";
    }
    $expire = sanitizeInput($_POST['expire']);
    if(empty($expire)){
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }
    $notes = sanitizeInput($_POST['notes']);
    $username = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['username'])));
    $password = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['password'])));

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_version = '$version', software_type = '$type', software_key = '$key', software_license_type = '$license_type', software_seats = $seats, software_purchase = $purchase, software_expire = $expire, software_notes = '$notes' WHERE software_id = $software_id AND company_id = $session_company_id");


    // Update Asset Licenses
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");
    if(!empty($_POST['assets'])){
        foreach($_POST['assets'] as $asset){
            $asset = intval($asset);
            mysqli_query($mysqli,"INSERT INTO software_assets SET software_id = $software_id, asset_id = $asset");
        }
    }

    // Update Contact Licenses
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    if(!empty($_POST['contacts'])){
        foreach($_POST['contacts'] as $contact){
            $contact = intval($contact);
            mysqli_query($mysqli,"INSERT INTO software_contacts SET software_id = $software_id, contact_id = $contact");
        }
    }

    //If login exists then update the login
    if($login_id > 0){
        mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_username = '$username', login_password = '$password' WHERE login_id = $login_id AND company_id = $session_company_id");
    }else{
        //If Username is filled in then add a login
        if(!empty($username)) {

            mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_software_id = $software_id, login_client_id = $client_id, company_id = $session_company_id");

        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Modify', log_description = '$session_name modified software $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $software_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Software <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_software'])){

    validateTechRole();

    $software_id = intval($_GET['archive_software']);

    // Get Software Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $software_name = sanitizeInput($row['software_name']);
    $client_id = intval($row['software_client_id']);

    mysqli_query($mysqli,"UPDATE software SET software_archived_at = NOW() WHERE software_id = $software_id AND company_id = $session_company_id");

    // Remove Software Relations
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Archive', log_description = '$session_name archived software $software_name and removed all device/user license associations', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $software_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Software <strong>$software_name</strong> archived and removed all device/user license associations";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_software'])){

    validateAdminRole();

    $software_id = intval($_GET['delete_software']);

    // Get Software Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $software_name = sanitizeInput($row['software_name']);
    $client_id = intval($row['software_client_id']);

    mysqli_query($mysqli,"DELETE FROM software WHERE software_id = $software_id AND company_id = $session_company_id");

    // Remove Software Relations
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Delete', log_description = '$session_name deleted software $software_name and removed all device/user license associations', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $software_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Software <strong>$software_name</strong> deleted and removed all device/user license associations";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['export_client_software_csv'])){

    validateTechRole();

    $client_id = intval($_GET['export_client_software_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Software-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Version', 'Type', 'License Type', 'Seats', 'Key', 'Assets', 'Contacts', 'Purchased', 'Expires', 'Notes');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){

            // Generate asset & user license list for this software

            // Asset licenses
            $assigned_to_assets = '';
            $asset_licenses_sql = mysqli_query($mysqli,"SELECT software_assets.asset_id, assets.asset_name 
                                                    FROM software_assets
                                                    LEFT JOIN assets
                                                        ON software_assets.asset_id = assets.asset_id
                                                    WHERE software_id = $row[software_id]");
            while($asset_row = mysqli_fetch_array($asset_licenses_sql)){
                $assigned_to_assets .= $asset_row['asset_name'] . ", ";
            }

            // Contact Licenses
            $assigned_to_contacts = '';
            $contact_licenses_sql = mysqli_query($mysqli,"SELECT software_contacts.contact_id, contacts.contact_name
                                                      FROM software_contacts
                                                      LEFT JOIN contacts
                                                          ON software_contacts.contact_id = contacts.contact_id
                                                      WHERE software_id = $row[software_id]");
            while($contact_row = mysqli_fetch_array($contact_licenses_sql)){
                $assigned_to_contacts .= $contact_row['contact_name'] . ", ";
            }

            $lineData = array($row['software_name'], $row['software_version'], $row['software_type'], $row['software_license_type'], $row['software_seats'], $row['software_key'], $assigned_to_assets, $assigned_to_contacts, $row['software_purchase'], $row['software_expire'], $row['software_notes']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Export', log_description = '$session_name exported $num_rows software license(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

if(isset($_POST['add_login'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $uri = sanitizeInput($_POST['uri']);
    $username = encryptLoginEntry($_POST['username']);
    $password = encryptLoginEntry($_POST['password']);
    $otp_secret = sanitizeInput($_POST['otp_secret']);
    $note = sanitizeInput($_POST['note']);
    $important = intval($_POST['important']);
    $contact_id = intval($_POST['contact']);
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $software_id = intval($_POST['software']);

    mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_uri = '$uri', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_important = $important, login_contact_id = $contact_id, login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id, login_client_id = $client_id, company_id = $session_company_id");

    $login_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Create', log_description = '$session_name created login $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Login <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_login'])){

    validateTechRole();

    $login_id = intval($_POST['login_id']);
    $name = sanitizeInput($_POST['name']);
    $uri = sanitizeInput($_POST['uri']);
    $username = encryptLoginEntry($_POST['username']);
    $password = encryptLoginEntry($_POST['password']);
    $otp_secret = sanitizeInput($_POST['otp_secret']);
    $note = sanitizeInput($_POST['note']);
    $important = intval($_POST['important']);
    $contact_id = intval($_POST['contact']);
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $software_id = intval($_POST['software']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_uri = '$uri', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_important = $important, login_contact_id = $contact_id, login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id WHERE login_id = $login_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Modify', log_description = '$session_name modified login $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Login <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_login'])){

    validateAdminRole();

    $login_id = intval($_GET['delete_login']);

    // Get Login Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    mysqli_query($mysqli,"DELETE FROM logins WHERE login_id = $login_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Delete', log_description = '$session_name deleted login $login_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Login <strong>$login_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['export_client_logins_csv'])){

    validateAdminRole();

    $client_id = intval($_GET['export_client_logins_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM logins LEFT JOIN clients ON client_id = login_client_id WHERE login_client_id = $client_id ORDER BY login_name ASC");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Logins-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Username', 'Password', 'URL');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $login_username = decryptLoginEntry($row['login_username']);
            $login_password = decryptLoginEntry($row['login_password']);
            $lineData = array($row['login_name'], $login_username, $login_password, $row['login_uri']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Export', log_description = '$session_name exported $num_rows login(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

if(isset($_POST["import_client_logins_csv"])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = false;

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if(in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif($_FILES["file"]["size"] < 1){
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if(!$error & count($f_columns) != 4) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if(!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false){
            $duplicate_detect = 0;
            if(isset($column[0])){
                $name = sanitizeInput($column[0]);
                if(mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM logins WHERE login_name = '$name' AND login_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if(isset($column[1])){
                $username = sanitizeInput(encryptLoginEntry($column[1]));
            }
            if(isset($column[2])){
                $password = sanitizeInput(encryptLoginEntry($column[2]));
            }
            if(isset($column[3])){
                $url = sanitizeInput($column[3]);
            }

            // Check if duplicate was detected
            if($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_client_id = $client_id, company_id = $session_company_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Import', log_description = '$session_name imported $row_count login(s) via csv file. $duplicate_count duplicate(s) detected and not imported', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "$row_count Login(s) imported, $duplicate_count duplicate(s) detected and not imported";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['download_client_logins_csv_template'])){
    $client_id = intval($_GET['download_client_logins_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Logins-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Username', 'Password', 'URL');
    fputcsv($f, $fields, $delimiter);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}

if(isset($_POST['add_network'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $vlan = intval($_POST['vlan']);
    $network = sanitizeInput($_POST['network']);
    $gateway = sanitizeInput($_POST['gateway']);
    $dhcp_range = sanitizeInput($_POST['dhcp_range']);
    $location_id = intval($_POST['location']);

    mysqli_query($mysqli,"INSERT INTO networks SET network_name = '$name', network_vlan = $vlan, network = '$network', network_gateway = '$gateway', network_dhcp_range = '$dhcp_range', network_location_id = $location_id, network_client_id = $client_id, company_id = $session_company_id");

    $network_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Create', log_description = '$session name created network $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Network <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_network'])){

    validateTechRole();

    $network_id = intval($_POST['network_id']);
    $name = sanitizeInput($_POST['name']);
    $vlan = intval($_POST['vlan']);
    $network = sanitizeInput($_POST['network']);
    $gateway = sanitizeInput($_POST['gateway']);
    $dhcp_range = sanitizeInput($_POST['dhcp_range']);
    $location_id = intval($_POST['location']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli,"UPDATE networks SET network_name = '$name', network_vlan = $vlan, network = '$network', network_gateway = '$gateway', network_dhcp_range = '$dhcp_range', network_location_id = $location_id WHERE network_id = $network_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Modify', log_description = '$session_name modified network $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Network <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_network'])){
    validateAdminRole();

    $network_id = intval($_GET['delete_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $network_name = sanitizeInput($row['network_name']);
    $client_id = intval($row['network_client_id']);

    mysqli_query($mysqli,"DELETE FROM networks WHERE network_id = $network_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Delete', log_description = '$session_name deleted network $network_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Network <strong>$network_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['export_client_networks_csv'])){

    validateTechRole();

    $client_id = intval($_GET['export_client_networks_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_client_id = $client_id ORDER BY network_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Networks-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'vLAN', 'Network', 'Gateway', 'DHCP Range');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['network_name'], $row['network_vlan'], $row['network'], $row['network_gateway'], $row['network_dhcp_range']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Export', log_description = '$session_name exported $num_rows network(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

if(isset($_POST['add_certificate'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $domain = sanitizeInput($_POST['domain']);
    $issued_by = sanitizeInput($_POST['issued_by']);
    $expire = sanitizeInput($_POST['expire']);
    $public_key = sanitizeInput($_POST['public_key']);
    $domain_id = intval($_POST['domain_id']);

    // Parse public key data for a manually provided public key
    if(!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = sanitizeInput($public_key_obj['issuer']['O']);
        }
    }

    if(empty($expire)){
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id, company_id = $session_company_id");

    $certificate_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Create', log_description = '$session_name created certificate $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $certificate_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Certificate <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_certificate'])){

    validateTechRole();

    $certificate_id = intval($_POST['certificate_id']);
    $name = sanitizeInput($_POST['name']);
    $domain = sanitizeInput($_POST['domain']);
    $issued_by = sanitizeInput($_POST['issued_by']);
    $expire = sanitizeInput($_POST['expire']);
    $public_key = sanitizeInput($_POST['public_key']);
    $domain_id = intval($_POST['domain_id']);
    $client_id = intval($_POST['client_id']);

    // Parse public key data for a manually provided public key
    if(!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = sanitizeInput($public_key_obj['issuer']['O']);
        }
    }

    if(empty($expire)){
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    mysqli_query($mysqli,"UPDATE certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_domain_id = '$domain_id' WHERE certificate_id = $certificate_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Modify', log_description = '$session_name modified certificate $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $certificate_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Certificate <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_certificate'])){

    validateAdminRole();

    $certificate_id = intval($_GET['delete_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $certificate_name = sanitizeInput($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    mysqli_query($mysqli,"DELETE FROM certificates WHERE certificate_id = $certificate_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Delete', log_description = '$session_name deleted certificate $certificate_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $certificate_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Certificate <strong>$certificate_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_certificates'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $certificate_ids = $_POST['certificate_ids']; // Get array of scheduled tickets IDs to be deleted

    if (!empty($certificate_ids)) {

        // Cycle through array and delete each scheduled ticket
        foreach ($certificate_ids as $certificate_id) {

            $certificate_id = intval($certificate_id);
            mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_id = $certificate_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Certificate', log_action = 'Delete', log_description = '$session_name deleted certificate (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $certificate_id, company_id = $session_company_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Certificate', log_action = 'Delete', log_description = '$session_name bulk deleted $count certificates', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "Deleted $count certificate(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['export_client_certificates_csv'])){

    validateTechRole();

    $client_id = intval($_GET['export_client_certificates_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_client_id = $client_id ORDER BY certificate_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Certificates-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Domain', 'Issuer', 'Expiration Date');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['certificate_name'], $row['certificate_domain'], $row['certificate_issued_by'], $row['certificate_expire']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Export', log_description = '$session_name exported $num_rows certificate(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

if(isset($_POST['add_domain'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $registrar = intval($_POST['registrar']);
    $webhost = intval($_POST['webhost']);
    $extended_log_description = '';
    $expire = sanitizeInput($_POST['expire']);
    if(empty($expire)){
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    // Get domain expiry date - if not specified
    if($expire == 'NULL'){
        $expire = getDomainExpirationDate($name);
        $expire = "'" . $expire . "'";
    }

    // NS, MX, A and WHOIS records/data
    $records = getDomainRecords($name);
    $a = sanitizeInput($records['a']);
    $ns = sanitizeInput($records['ns']);
    $mx = sanitizeInput($records['mx']);
    $txt = sanitizeInput($records['txt']);
    $whois = sanitizeInput($records['whois']);

    // Add domain record
    mysqli_query($mysqli,"INSERT INTO domains SET domain_name = '$name', domain_registrar = $registrar,  domain_webhost = $webhost, domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois', domain_client_id = $client_id, company_id = $session_company_id");


    // Get inserted ID (for linking certificate, if exists)
    $domain_id = mysqli_insert_id($mysqli);

    // Get SSL cert for domain (if exists)
    $certificate = getSSL($name);
    if($certificate['success'] == "TRUE"){
        $expire = sanitizeInput($certificate['expire']);
        $issued_by = sanitizeInput($certificate['issued_by']);
        $public_key = sanitizeInput($certificate['public_key']);

        mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_domain = '$name', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id, company_id = $session_company_id");
        $extended_log_description = ', with associated SSL cert';
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Create', log_description = '$session_name created domain $name$extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Domain <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_domain'])){

    validateTechRole();

    $domain_id = intval($_POST['domain_id']);
    $name = sanitizeInput($_POST['name']);
    $registrar = intval($_POST['registrar']);
    $webhost = intval($_POST['webhost']);
    $expire = sanitizeInput($_POST['expire']);
    if(empty($expire)){
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }
    $client_id = intval($_POST['client_id']);

    // Update domain expiry date
    $expire = getDomainExpirationDate($name);

    // Update NS, MX, A and WHOIS records/data
    $records = getDomainRecords($name);
    $a = sanitizeInput($records['a']);
    $ns = sanitizeInput($records['ns']);
    $mx = sanitizeInput($records['mx']);
    $txt = sanitizeInput($records['txt']);
    $whois = sanitizeInput($records['whois']);

    mysqli_query($mysqli,"UPDATE domains SET domain_name = '$name', domain_registrar = $registrar,  domain_webhost = $webhost, domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois' WHERE domain_id = $domain_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Modify', log_description = '$session_name modified domain $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Domain <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_domain'])){

    validateAdminRole();

    $domain_id = intval($_GET['delete_domain']);

    // Get Domain Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $domain_name = sanitizeInput($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    mysqli_query($mysqli,"DELETE FROM domains WHERE domain_id = $domain_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Delete', log_description = '$session_name deleted domain $domain_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Domain <strong>$domain_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['export_client_domains_csv'])){

    validateTechRole();

    $client_id = intval($_GET['export_client_domains_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_client_id = $client_id ORDER BY domain_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Domains-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Domain', 'Registrar', 'Web Host', 'Expiration Date');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['domain_name'], $row['domain_registrar'], $row['domain_webhost'], $row['domain_expire']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Export', log_description = '$session_name exported $num_rows domain(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;

}

if(isset($_POST['add_ticket'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    $client_id = intval($_POST['client']);
    $assigned_to = intval($_POST['assigned_to']);
    $contact = intval($_POST['contact']);
    $subject = sanitizeInput($_POST['subject']);
    $priority = sanitizeInput($_POST['priority']);
    $details = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['details']))));
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);

    // If no contact is selected automatically choose the primary contact for the client
    if($client_id > 0 && $contact == 0){
        $sql = mysqli_query($mysqli,"SELECT primary_contact FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $contact = intval($row['primary_contact']);
    }

    //Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 'Open', ticket_vendor_id = $vendor_id, ticket_asset_id = $asset_id, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_client_id = $client_id, company_id = $session_company_id");

    $ticket_id = mysqli_insert_id($mysqli);

    // E-mail client
    if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

        // Get contact/ticket details
        $sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, company_phone FROM tickets 
              LEFT JOIN clients ON ticket_client_id = client_id 
              LEFT JOIN contacts ON ticket_contact_id = contact_id
              LEFT JOIN companies ON tickets.company_id = companies.company_id
              WHERE ticket_id = $ticket_id AND tickets.company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);

        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];
        $ticket_prefix = $row['ticket_prefix'];
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = $row['ticket_subject'];
        $company_phone = formatPhoneNumber($row['company_phone']);

        // Verify contact email is valid
        if(filter_var($contact_email, FILTER_VALIDATE_EMAIL)){

            $subject = "Ticket created - [$ticket_prefix$ticket_number] - $ticket_subject";
            $body    = "<i style='color: #808080'>#--itflow--#</i><br><br>Hello, $contact_name<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: https://$config_base_url/portal/ticket.php?id=$id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone";

            $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                $config_ticket_from_email, $config_ticket_from_name,
                $contact_email, $contact_name,
                $subject, $body);

            if ($mail !== true) {
                mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email rearding ticket $config_ticket_prefix$ticket_number - $ticket_subject', notification_client_id = $client_id, notification_user_id = $session_user_id, company_id = $session_company_id");
                mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject relating to ticket $config_ticket_prefix$ticket_number. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id, company_id = $session_company_id");
            }

        }
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = '$session_name created ticket $config_ticket_prefix$ticket_number - $subject', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_number, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Ticket <strong>$config_ticket_prefix$ticket_number</strong> created";

    header("Location: ticket.php?ticket_id=" . $ticket_id);

}

if(isset($_POST['edit_ticket'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);
    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = intval($_POST['assigned_to']);
    $contact_id = intval($_POST['contact']);
    $subject = sanitizeInput($_POST['subject']);
    $priority = sanitizeInput($_POST['priority']);
    $details = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['details']))));
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = intval($_POST['ticket_number']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_subject = '$subject', ticket_priority = '$priority', ticket_details = '$details', ticket_assigned_to = $assigned_to, ticket_contact_id = $contact_id, ticket_vendor_id = $vendor_id, ticket_asset_id = $asset_id WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name modified ticket $ticket_number - $subject', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['assign_ticket'])){

    // Role check
    validateTechRole();

    // POST variables
    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = intval($_POST['assigned_to']);

    // Allow for un-assigning tickets
    if($assigned_to == 0){
        $ticket_reply = "Ticket unassigned.";
        $agent_name = "No One";

    } else {
        // Get & verify assigned agent details
        $agent_details_sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users LEFT JOIN user_settings ON users.user_id = user_settings.user_id WHERE users.user_id = '$assigned_to' AND user_settings.user_role > 1");
        $agent_details = mysqli_fetch_array($agent_details_sql);
        $agent_name = sanitizeInput($agent_details['user_name']);
        $agent_email = sanitizeInput($agent_details['user_email']);
        $ticket_reply = "Ticket re-assigned to $agent_name.";

        if(!$agent_name){
            $_SESSION['alert_type'] = "error";
            $_SESSION['alert_message'] = "Invalid agent!";
            header("Location: " . $_SERVER["HTTP_REFERER"]);
            exit();
        }
    }

    // Get & verify ticket details
    $ticket_details_sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_client_id FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_status != 'Closed'");
    $ticket_details = mysqli_fetch_array($ticket_details_sql);
    $ticket_prefix = sanitizeInput($ticket_details['ticket_prefix']);
    $ticket_number = intval($ticket_details['ticket_number']);
    $ticket_subject = sanitizeInput($ticket_details['ticket_subject']);
    $client_id = intval($ticket_details['ticket_client_id']);

    if(!$ticket_subject){
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Invalid ticket!";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    // Update ticket & insert reply
    mysqli_query($mysqli,"UPDATE tickets SET ticket_assigned_to = $assigned_to WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name reassigned ticket $ticket_prefix$ticket_number - $ticket_subject to $agent_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id, company_id = $session_company_id");

    // Email notification
    if (intval($session_user_id) !== $assigned_to || $assigned_to !== 0) {

        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject has been assigned to you by $session_name', notification_client_id = $client_id, notification_user_id = $assigned_to, company_id = $session_company_id");

        $subject = "$config_app_name ticket $ticket_prefix$ticket_number assigned to you";
        $body = "Hi $agent_name, <br><br>A ticket has been assigned to you!<br><br>Ticket Number: $ticket_prefix$ticket_number<br> Subject: $ticket_subject <br><br>Thanks, <br>$session_name<br>$session_company_name";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_ticket_from_email, $config_ticket_from_name,
            $agent_email, $agent_name,
            $subject, $body);
    }

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> assigned to <strong>$agent_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_ticket'])){

    validateAdminRole();

    $ticket_id = intval($_GET['delete_ticket']);

    // Get Ticket and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = sanitizeInput($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_status = sanitizeInput($row['ticket_status']);
    $client_id = intval($row['ticket_client_id']);

    if ($ticket_status !== 'Closed') {
        mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

        // Delete all ticket replies
        mysqli_query($mysqli,"DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id AND company_id = $session_company_id");

        // Delete all ticket views
        mysqli_query($mysqli,"DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");

        // Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Delete', log_description = '$session_name deleted ticket $ticket_prefix$ticket_number - $ticket_subject along with all replies', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id, company_id = $session_company_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> along with all replies deleted";

        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }

}

if(isset($_POST['add_ticket_reply'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    $ticket_id = intval($_POST['ticket_id']);
    $ticket_reply = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['ticket_reply']))));
    $ticket_status = sanitizeInput($_POST['status']);
    $ticket_reply_time_worked = sanitizeInput($_POST['time']);

    $client_id = intval($_POST['client_id']);

    if(isset($_POST['public_reply_type'])){
        $ticket_reply_type = 'Public';
    } else {
        $ticket_reply_type = 'Internal';
    }

    // Add reply
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Update Ticket Last Response Field
    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = '$ticket_status' WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    if ($ticket_status == 'Closed') {
        mysqli_query($mysqli,"UPDATE tickets SET ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND company_id = $session_company_id");
    }

    // Get Ticket Details
    $ticket_sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, company_phone, ticket_client_id, ticket_created_by, ticket_assigned_to FROM tickets 
        LEFT JOIN clients ON ticket_client_id = client_id 
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN companies ON tickets.company_id = companies.company_id
        WHERE ticket_id = $ticket_id AND tickets.company_id = $session_company_id
    ");

    $row = mysqli_fetch_array($ticket_sql);

    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $client_id = intval($row['ticket_client_id']);
    $ticket_created_by = intval($row['ticket_created_by']);
    $ticket_assigned_to = intval($row['ticket_assigned_to']);
    $company_phone = formatPhoneNumber($row['company_phone']);

    // Send e-mail to client if public update & email is set up
    if($ticket_reply_type == 'Public' && !empty($config_smtp_host)){

        if(filter_var($contact_email, FILTER_VALIDATE_EMAIL)){

            // Slightly different email subject/text depending on if this update closed the ticket or not

            if($ticket_status == 'Closed') {
                $subject = "Ticket closed - [$ticket_prefix$ticket_number] - $ticket_subject | (do not reply)";
                $body    = "Hello, $contact_name<br><br>Your ticket regarding \"$ticket_subject\" has been closed.<br><br>--------------------------------<br>$ticket_reply--------------------------------<br><br>We hope the issue was resolved to your satisfaction. If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone";

            } else {
                $subject = "Ticket update - [$ticket_prefix$ticket_number] - $ticket_subject";
                $body    = "<i style='color: #808080'>#--itflow--#</i><br><br>Hello, $contact_name<br><br>Your ticket regarding \"$ticket_subject\" has been updated.<br><br>--------------------------------<br>$ticket_reply--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone";

            }

            $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                $config_ticket_from_email, $config_ticket_from_name,
                $contact_email, $contact_name,
                $subject, $body);

            if ($mail !== true) {
                mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
                mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
            }
        }
    }
    //End Mail IF

    // Notification for assigned ticket user
    if (intval($session_user_id) !== $ticket_assigned_to || $ticket_assigned_to !== 0) {

        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that is assigned to you', notification_client_id = $client_id, notification_user_id = $ticket_assigned_to, company_id = $session_company_id");
    }

    // Notification for user that opened the ticket
    if (intval($session_user_id) !== $ticket_created_by || $ticket_created_by !== 0) {

        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that you opened', notification_client_id = $client_id, notification_user_id = $ticket_created_by, company_id = $session_company_id");
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Create', log_description = '$session_name replied to ticket $ticket_prefix$ticket_number - $ticket_subject and was a $ticket_reply_type reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Ticket <strong>$prefix$ticket_number</strong> has been updated with your reply and was <strong>$ticket_reply_type</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_ticket_reply'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    $ticket_reply_id = intval($_POST['ticket_reply_id']);
    $ticket_reply = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['ticket_reply']))));
    $ticket_reply_time_worked = sanitizeInput($_POST['time']);

    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked' WHERE ticket_reply_id = $ticket_reply_id AND ticket_reply_type != 'Client' AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Modify', log_description = '$session_name modified ticket reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Ticket reply updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_ticket_reply'])){

    validateAdminRole();

    $ticket_reply_id = intval($_GET['archive_ticket_reply']);

    mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply_archived_at = NOW() WHERE ticket_reply_id = $ticket_reply_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Archive', log_description = '$session_name arhived ticket reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Ticket reply archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['merge_ticket'])){

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $merge_into_ticket_number = intval($_POST['merge_into_ticket_number']);
    $merge_comment = sanitizeInput($_POST['merge_comment']);
    $ticket_reply_type = 'Internal';

    //Get current ticket details
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_details FROM tickets WHERE ticket_id = '$ticket_id'");
    if(mysqli_num_rows($sql) == 0){
        $_SESSION['alert_message'] = "No ticket with that ID found.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $row = mysqli_fetch_array($sql);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_details = sanitizeInput($row['ticket_details']);

    //Get merge into ticket id (as it may differ from the number)
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_number = '$merge_into_ticket_number'");
    if(mysqli_num_rows($sql) == 0){
        $_SESSION['alert_message'] = "Cannot merge into that ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $merge_row = mysqli_fetch_array($sql);
    $merge_into_ticket_id = intval($merge_row['ticket_id']);

    if($ticket_number == $merge_into_ticket_number){
        $_SESSION['alert_message'] = "Cannot merge into the same ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    //Update current ticket
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number merged into $ticket_prefix$merge_into_ticket_number. Comment: $merge_comment', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));
    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Closed', ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Update new ticket
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number was merged into this ticket with comment: $merge_comment.<br><b>$ticket_subject</b><br>$ticket_details', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $merge_into_ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Merged', log_description = 'Merged ticket $ticket_prefix$ticket_number into $ticket_prefix$merge_into_ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Ticket merged into $ticket_prefix$merge_into_ticket_number";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['close_ticket'])){

    validateTechRole();

    $ticket_id = intval($_GET['close_ticket']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Closed', ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Closed', log_description = '$ticket_id Closed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    // Client notification email
    if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

        // Get details
        $ticket_sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, company_phone FROM tickets 
            LEFT JOIN clients ON ticket_client_id = client_id 
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            LEFT JOIN companies ON tickets.company_id = companies.company_id
            WHERE ticket_id = $ticket_id AND tickets.company_id = $session_company_id
        ");
        $row = mysqli_fetch_array($ticket_sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $company_phone = formatPhoneNumber($row['company_phone']);

        // Check email valid
        if(filter_var($contact_email, FILTER_VALIDATE_EMAIL)){

            $subject = "Ticket closed - [$ticket_prefix$ticket_number] - $ticket_subject | (do not reply)";
            $body    = "Hello, $contact_name<br><br>Your ticket regarding \"$ticket_subject\" has been closed. <br><br> We hope the issue was resolved to your satisfaction. If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone";

            $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                $config_ticket_from_email, $config_ticket_from_name,
                $contact_email, $contact_name,
                $subject, $body);

            if ($mail !== true) {
                mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', company_id = $session_company_id");
                mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
            }

        }

    }
    //End Mail IF

    $_SESSION['alert_message'] = "Ticket Closed, this cannot not be reopened but you may start another one";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_invoice_from_ticket'])){

    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);
    $date = sanitizeInput($_POST['date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets 
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id 
        LEFT JOIN assets ON ticket_asset_id = asset_id
        LEFT JOIN locations ON ticket_location_id = location_id
        WHERE ticket_id = $ticket_id
        AND tickets.company_id = $session_company_id"
    );

    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['client_id']);
    $client_net_terms = intval($row['client_net_terms']);
    if($client_net_terms == 0){
        $client_net_terms = $config_default_net_terms;
    }

    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_category = sanitizeInput($row['ticket_category']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_created_at = sanitizeInput($row['ticket_created_at']);
    $ticket_updated_at = sanitizeInput($row['ticket_updated_at']);
    $ticket_closed_at = sanitizeInput($row['ticket_closed_at']);

    $contact_id = intval($row['contact_id']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);

    $asset_id = intval($row['asset_id']);

    $location_name = sanitizeInput($row['location_name']);

    if($invoice_id == 0){

        //Get the last Invoice Number and add 1 for the new invoice number
        $invoice_number = $config_invoice_next_number;
        $new_config_invoice_next_number = $config_invoice_next_number + 1;
        mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

        //Generate a unique URL key for clients to access
        $url_key = randomString(156);

        mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id, company_id = $session_company_id");
        $invoice_id = mysqli_insert_id($mysqli);
    }

    //Add Item
    $item_name = sanitizeInput($_POST['item_name']);
    $item_description = sanitizeInput($_POST['item_description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $subtotal = $price * $qty;

    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_invoice_id = $invoice_id, company_id = $session_company_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_invoice_amount = floatval($row['invoice_amount']) + $total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created from Ticket $ticket_prefix$ticket_number', history_invoice_id = $invoice_id, company_id = $session_company_id");

    // Add internal note to ticket
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Created invoice <a href=\"invoice.php?invoice_id=$invoice_id\">$config_invoice_prefix$invoice_number</a> for this ticket.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = '$config_invoice_prefix$invoice_number created from Ticket $ticket_prefix$ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice created from ticket";

    header("Location: invoice.php?invoice_id=$invoice_id");
}

if(isset($_GET['export_client_tickets_csv'])){

    validateTechRole();

    $client_id = intval($_GET['export_client_tickets_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM tickets WHERE ticket_client_id = $client_id ORDER BY ticket_number ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Tickets-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Ticket Number', 'Priority', 'Status', 'Subject', 'Date Opened', 'Date Closed');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['ticket_number'], $row['ticket_priority'], $row['ticket_status'], $row['ticket_subject'], $row['ticket_created_at'], $row['ticket_closed_at']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if (isset($_POST['add_scheduled_ticket'])) {

    validateTechRole();

    require_once('models/scheduled_ticket.php');
    $start_date = sanitizeInput($_POST['start_date']);

    if ($client_id > 0 && $contact_id == 0) {
        $sql = mysqli_query($mysqli, "SELECT primary_contact FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $contact_id = intval($row['primary_contact']);
    }

    // Add scheduled ticket
    mysqli_query($mysqli, "INSERT INTO scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_start_date = '$start_date', scheduled_ticket_next_run = '$start_date', scheduled_ticket_created_by = $session_user_id, scheduled_ticket_client_id = $client_id, scheduled_ticket_contact_id = $contact_id, scheduled_ticket_asset_id = $asset_id, company_id = $session_company_id");

    $scheduled_ticket_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Create', log_description = '$session_name created scheduled ticket for $subject - $frequency', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Scheduled ticket <strong>$subject - $frequency</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_scheduled_ticket'])) {

    validateTechRole();

    require_once('models/scheduled_ticket.php');
    $scheduled_ticket_id = intval($_POST['scheduled_ticket_id']);
    $next_run_date = sanitizeInput($_POST['next_date']);

    // Edit scheduled ticket
    mysqli_query($mysqli, "UPDATE scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_next_run = '$next_run_date', scheduled_ticket_asset_id = $asset_id, company_id = $session_company_id WHERE scheduled_ticket_id = $scheduled_ticket_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Modify', log_description = '$session_name modified scheduled ticket for $subject - $frequency', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Scheduled ticket <strong>$subject - $frequency</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_scheduled_ticket'])) {

    validateAdminRole();

    $scheduled_ticket_id = intval($_GET['delete_scheduled_ticket']);

    // Get Scheduled Ticket Subject Ticket Prefix, Number and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $scheduled_ticket_subject = sanitizeInput($row['scheduled_ticket_subject']);
    $scheduled_ticket_frequency = sanitizeInput($row['scheduled_ticket_frequency']);

    $client_id = intval($row['scheduled_ticket_client_id']);

    // Delete
    mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name deleted scheduled ticket for $subject - $frequency', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Scheduled ticket <strong>$subject - $frequency</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_scheduled_tickets'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $scheduled_ticket_ids = $_POST['scheduled_ticket_ids']; // Get array of scheduled tickets IDs to be deleted

    if (!empty($scheduled_ticket_ids)) {

        // Cycle through array and delete each scheduled ticket
        foreach ($scheduled_ticket_ids as $scheduled_ticket_id) {

            $scheduled_ticket_id = intval($scheduled_ticket_id);
            mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name deleted scheduled ticket (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id, company_id = $session_company_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name bulk deleted $count scheduled tickets', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "Deleted $count scheduled ticket(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['add_service'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $service_name = sanitizeInput($_POST['name']);
    $service_description = sanitizeInput($_POST['description']);
    $service_category = sanitizeInput($_POST['category']); //TODO: Needs integration with company categories
    $service_importance = sanitizeInput($_POST['importance']);
    $service_backup = sanitizeInput($_POST['backup']);
    $service_notes = sanitizeInput($_POST['note']);

    // Create Service
    $service_sql = mysqli_query($mysqli, "INSERT INTO services SET service_name = '$service_name', service_description = '$service_description', service_category = '$service_category', service_importance = '$service_importance', service_backup = '$service_backup', service_notes = '$service_notes', service_client_id = '$client_id', company_id = '$session_company_id'");

    // Create links to assets
    if($service_sql){
        $service_id = $mysqli->insert_id;

        if(!empty($_POST['contacts'])){
            $service_contact_ids = $_POST['contacts'];
            foreach($service_contact_ids as $contact_id){
                $contact_id = intval($contact_id);
                if($contact_id > 0){
                    mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = '$service_id', contact_id = '$contact_id'");
                }
            }
        }

        if(!empty($_POST['vendors'])){
            $service_vendor_ids = $_POST['vendors'];
            foreach($service_vendor_ids as $vendor_id){
                $vendor_id = intval($vendor_id);
                if($vendor_id > 0){
                    mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = '$service_id', vendor_id = '$vendor_id'");
                }
            }
        }

        if(!empty($_POST['documents'])){
            $service_document_ids = $_POST['documents'];
            foreach($service_document_ids as $document_id){
                $document_id = intval($document_id);
                if($document_id > 0){
                    mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = '$service_id', document_id = '$document_id'");
                }
            }
        }

        if(!empty($_POST['assets'])){
            $service_asset_ids = $_POST['assets'];
            foreach($service_asset_ids as $asset_id){
                $asset_id = intval($asset_id);
                if($asset_id > 0){
                    mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = '$service_id', asset_id = '$asset_id'");
                }
            }
        }

        if(!empty($_POST['logins'])){
            $service_login_ids = $_POST['logins'];
            foreach($service_login_ids as $login_id){
                $login_id = intval($login_id);
                if($login_id > 0){
                    mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = '$service_id', login_id = '$login_id'");
                }
            }
        }

        if(!empty($_POST['domains'])){
            $service_domain_ids = $_POST['domains'];
            foreach($service_domain_ids as $domain_id){
                $domain_id = intval($domain_id);
                if($domain_id > 0){
                    mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = '$service_id', domain_id = '$domain_id'");
                }
            }
        }

        if(!empty($_POST['certificates'])){
            $service_cert_ids = $_POST['certificates'];
            foreach($service_cert_ids as $cert_id){
                $cert_id = intval($cert_id);
                if($cert_id > 0){
                    mysqli_query($mysqli, "INSERT INTO service_certificates SET service_id = '$service_id', certificate_id = '$cert_id'");
                }
            }
        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Create', log_description = '$session_name created service $service_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Service added";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    }
    else{
        $_SESSION['alert_message'] = "Something went wrong (SQL)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_POST['edit_service'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $service_id = intval($_POST['service_id']);
    $service_name = sanitizeInput($_POST['name']);
    $service_description = sanitizeInput($_POST['description']);
    $service_category = sanitizeInput($_POST['category']); //TODO: Needs integration with company categories
    $service_importance = sanitizeInput($_POST['importance']);
    $service_backup = sanitizeInput($_POST['backup']);
    $service_notes = sanitizeInput($_POST['note']);

    // Update main service details
    mysqli_query($mysqli, "UPDATE services SET service_name = '$service_name', service_description = '$service_description', service_category = '$service_category', service_importance = '$service_importance', service_backup = '$service_backup', service_notes = '$service_notes' WHERE service_id = '$service_id' AND company_id = '$session_company_id'");

    // Unlink existing relations/assets
    mysqli_query($mysqli, "DELETE FROM service_contacts WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_assets WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_logins WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = '$service_id'");

    // Relink
    if(!empty($_POST['contacts'])){
        $service_contact_ids = $_POST['contacts'];
        foreach($service_contact_ids as $contact_id){
            $contact_id = intval($contact_id);
            if($contact_id > 0){
                mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = '$service_id', contact_id = '$contact_id'");
            }
        }
    }

    if(!empty($_POST['vendors'])){
        $service_vendor_ids = $_POST['vendors'];
        foreach($service_vendor_ids as $vendor_id){
            $vendor_id = intval($vendor_id);
            if($vendor_id > 0){
                mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = '$service_id', vendor_id = '$vendor_id'");
            }
        }
    }

    if(!empty($_POST['documents'])){
        $service_document_ids = $_POST['documents'];
        foreach($service_document_ids as $document_id){
            $document_id = intval($document_id);
            if($document_id > 0){
                mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = '$service_id', document_id = '$document_id'");
            }
        }
    }

    if(!empty($_POST['assets'])){
        $service_asset_ids = $_POST['assets'];
        foreach($service_asset_ids as $asset_id){
            $asset_id = intval($asset_id);
            if($asset_id > 0){
                mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = '$service_id', asset_id = '$asset_id'");
            }
        }
    }

    if(!empty($_POST['logins'])){
        $service_login_ids = $_POST['logins'];
        foreach($service_login_ids as $login_id){
            $login_id = intval($login_id);
            if($login_id > 0){
                mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = '$service_id', login_id = '$login_id'");
            }
        }
    }

    if(!empty($_POST['domains'])){
        $service_domain_ids = $_POST['domains'];
        foreach($service_domain_ids as $domain_id){
            $domain_id = intval($domain_id);
            if($domain_id > 0){
                mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = '$service_id', domain_id = '$domain_id'");
            }
        }
    }

    if(!empty($_POST['certificates'])){
        $service_cert_ids = $_POST['certificates'];
        foreach($service_cert_ids as $cert_id){
            $cert_id = intval($cert_id);
            if($cert_id > 0){
                mysqli_query($mysqli, "INSERT INTO service_certificates SET service_id = '$service_id', certificate_id = '$cert_id'");
            }
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Modify', log_description = '$session_name modified service $service_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Service updated";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_service'])){

    validateAdminRole();

    $service_id = intval($_GET['delete_service']);

    // Delete service
    $delete_sql = mysqli_query($mysqli, "DELETE FROM services WHERE service_id = '$service_id' AND company_id = '$session_company_id'");

    // Delete relations
    // TODO: Convert this to a join delete
    if($delete_sql){
        mysqli_query($mysqli, "DELETE FROM service_contacts WHERE service_id = '$service_id'");
        mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = '$service_id'");
        mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = '$service_id'");
        mysqli_query($mysqli, "DELETE FROM service_assets WHERE service_id = '$service_id'");
        mysqli_query($mysqli, "DELETE FROM service_logins WHERE service_id = '$service_id'");
        mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = '$service_id'");
        mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = '$service_id'");

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Delete', log_description = '$session_name deleted service $service_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "Service deleted";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    else{
        $_SESSION['alert_message'] = "Something went wrong (SQL)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_POST['add_file'])){
    $client_id = intval($_POST['client_id']);
    $file_name = sanitizeInput($_POST['new_name']);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    //Check to see if a file is attached
    if($_FILES['file']['tmp_name'] != ''){

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        if(empty($file_name)) {
            $file_name = sanitizeInput($_FILES['file']['name']);
        }
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

        // sanitize file-name
        $file_reference_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'xlsm', 'zip', 'tar', 'gz');

        if(in_array($file_extension,$allowed_file_extensions) === false){
            $file_error = 1;
        }

        //Check File Size
        if($file_size > 20097152){
            $file_error = 1;
        }

        if($file_error == 0){
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$session_company_id/$client_id/";
            $dest_path = $upload_file_dir . $file_reference_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$file_name', file_ext = '$file_extension', file_client_id = $client_id, company_id = $session_company_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Upload', log_description = '$path', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "File uploaded";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_file'])){

    validateAdminRole();

    $file_id = intval($_GET['delete_file']);

    $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql_file);
    $client_id = intval($row['file_client_id']);
    $file_name = sanitizeInput($row['file_name']);
    $file_reference_name = sanitizeInput($row['file_reference_name']);

    unlink("uploads/clients/$session_company_id/$client_id/$file_reference_name");

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Delete', log_description = '$file_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "File <strong>$file_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_document'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $content = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['content']))));
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    $folder = intval($_POST['folder']);

    // Document add query
    $add_document = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_client_id = $client_id, company_id = $session_company_id");
    $document_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Create', log_description = 'Created $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = '$client_id', company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Document <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_document_template'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $content = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['content']))));
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    // Document add query
    $add_document = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_content = '$content', document_content_raw = '$content_raw', document_template = 1, document_folder_id = 0, document_client_id = 0, company_id = $session_company_id");
    $document_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document Template', log_action = 'Create', log_description = '$session_name created document template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Document template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_document_from_template'])){

    // ROLE Check
    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    // GET POST Data
    $client_id = intval($_POST['client_id']);
    $document_name = sanitizeInput($_POST['name']);
    $document_template_id = intval($_POST['document_template_id']);
    $folder = intval($_POST['folder']);

    //GET Document Info
    $sql_document = mysqli_query($mysqli,"SELECT * FROM documents WHERE document_id = $document_template_id AND company_id = $session_company_id");

    $row = mysqli_fetch_array($sql_document);

    $document_template_name = sanitizeInput($row['document_name']);
    $content = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($row['document_content']))));
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $row['document_content']));

    // Document add query
    $add_document = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$document_name', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_client_id = $client_id, company_id = $session_company_id");

    $document_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Create', log_description = 'Document $document_name created from template $document_template_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> created from template";

    header("Location: client_document_details.php?client_id=$client_id&document_id=$document_id");

}

if(isset($_POST['edit_document'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    $document_id = intval($_POST['document_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $content = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['content']))));
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.
    $folder = intval($_POST['folder']);

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name', document_content = '$content', document_content_raw = '$content_raw', document_folder_id = $folder WHERE document_id = $document_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Modify', log_description = '$session_name updated document $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id, company_id = $session_company_id");


    $_SESSION['alert_message'] = "Document <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_document_template'])){

    validateTechRole();

    // HTML Purifier
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
    $purifier = new HTMLPurifier($purifier_config);

    $document_id = intval($_POST['document_id']);
    $name = sanitizeInput($_POST['name']);
    $content = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode($_POST['content']))));
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name', document_content = '$content', document_content_raw = '$content_raw' WHERE document_id = $document_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document Template', log_action = 'Modify', log_description = '$session_name modified document template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $document_id, company_id = $session_company_id");


    $_SESSION['alert_message'] = "Document Template <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_document'])){

    validateAdminRole();

    $document_id = intval($_GET['delete_document']);

    mysqli_query($mysqli,"DELETE FROM documents WHERE document_id = $document_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Delete', log_description = '$document_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Document deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_folder'])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $folder_name = sanitizeInput($_POST['folder_name']);

    // Document folder add query
    $add_folder = mysqli_query($mysqli,"INSERT INTO folders SET folder_name = '$folder_name', folder_client_id = $client_id, company_id = $session_company_id");
    $folder_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Folder', log_action = 'Create', log_description = '$session_name created folder $folder_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $folder_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Folder <strong>$folder_name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['rename_folder'])){

    validateTechRole();

    $folder_id = intval($_POST['folder_id']);
    $client_id = intval($_POST['client_id']);
    $folder_name = sanitizeInput($_POST['folder_name']);

    // Folder edit query
    mysqli_query($mysqli,"UPDATE folders SET folder_name = '$folder_name' WHERE folder_id = $folder_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Folder', log_action = 'Modify', log_description = '$session_name renamed folder to $folder_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $folder_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Folder <strong>$folder_name</strong> renamed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_folder'])){

    validateAdminRole();

    $folder_id = intval($_GET['delete_folder']);

    mysqli_query($mysqli,"DELETE FROM folders WHERE folder_id = $folder_id AND company_id = $session_company_id");

    // Move files in deleted folder back to the root folder /
    $sql_documents = mysqli_query($mysqli,"SELECT * FROM documents WHERE document_folder_id = $folder_id");
    while($row = mysqli_fetch_array($sql_documents)){
        $document_id = intval($row['document_id']);

        mysqli_query($mysqli,"UPDATE documents SET document_folder_id = 0 WHERE document_id = $document_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Folder', log_action = 'Delete', log_description = '$folder_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Folder deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['deactivate_shared_item'])){
    if($session_user_role != 3){
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    $item_id = intval($_GET['deactivate_shared_item']);

    // Get details of the shared link
    $sql = mysqli_query($mysqli, "SELECT item_type, item_related_id, item_client_id FROM shared_items WHERE item_id = '$item_id'");
    $row = mysqli_fetch_array($sql);
    $item_type = sanitizeInput($row['item_type']);
    $item_related_id = intval($row['item_related_id']);
    $item_client_id = intval($row['item_client_id']);

    // Deactivate item id
    mysqli_query($mysqli, "UPDATE shared_items SET item_active = '0' WHERE item_id = '$item_id'");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Sharing', log_action = 'Delete', log_description = '$session_name deactivated shared $item_type link. Item ID: $item_related_id. Share ID $item_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $item_client_id, log_user_id = $session_user_id, log_entity_id = $item_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Link deactivated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['force_recurring'])){
    $recurring_id = intval($_GET['force_recurring']);

    $sql_recurring = mysqli_query($mysqli,"SELECT * FROM recurring, clients WHERE client_id = recurring_client_id AND recurring_id = $recurring_id AND recurring.company_id = $session_company_id");

    $row = mysqli_fetch_array($sql_recurring);
    $recurring_id = intval($row['recurring_id']);
    $recurring_scope = sanitizeInput($row['recurring_scope']);
    $recurring_frequency = sanitizeInput($row['recurring_frequency']);
    $recurring_status = sanitizeInput($row['recurring_status']);
    $recurring_last_sent = sanitizeInput($row['recurring_last_sent']);
    $recurring_next_date = sanitizeInput($row['recurring_next_date']);
    $recurring_amount = floatval($row['recurring_amount']);
    $recurring_currency_code = sanitizeInput($row['recurring_currency_code']);
    $recurring_note = sanitizeInput($row['recurring_note']);
    $category_id = intval($row['recurring_category_id']);
    $client_id = intval($row['recurring_client_id']);
    $client_net_terms = intval($row['client_net_terms']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $new_invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = '$new_invoice_number', invoice_scope = '$recurring_scope', invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_amount = $recurring_amount, invoice_currency_code = '$recurring_currency_code', invoice_note = '$recurring_note', invoice_category_id = $category_id, invoice_status = 'Sent', invoice_url_key = '$url_key', invoice_client_id = $client_id, company_id = $session_company_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    //Copy Items from original invoice to new invoice
    $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id AND company_id = $session_company_id ORDER BY item_id ASC");

    while($row = mysqli_fetch_array($sql_invoice_items)){
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $tax_id = intval($row['item_tax_id']);

        //Recalculate Item Tax since Tax percents can change.
        if($tax_id > 0){
            $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id AND company_id = $session_company_id");
            $row = mysqli_fetch_array($sql);
            $tax_percent = floatval($row['tax_percent']);
            $item_tax_amount = $item_subtotal * $tax_percent / 100;
        }else{
            $item_tax_amount = 0;
        }

        $item_total = $item_subtotal + $item_tax_amount;

        //Update Recurring Items with new tax
        mysqli_query($mysqli,"UPDATE invoice_items SET item_tax = $item_tax_amount, item_total = $item_total, item_tax_id = $tax_id WHERE item_id = $item_id");

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax_amount, item_total = $item_total, item_tax_id = $tax_id, item_invoice_id = $new_invoice_id, company_id = $session_company_id");
    }

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice Generated from Recurring!', history_invoice_id = $new_invoice_id, company_id = $session_company_id");

    //Update Recurring Balances by tallying up recurring items also update recurring dates
    $sql_recurring_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_total FROM invoice_items WHERE item_recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql_recurring_total);
    $new_recurring_amount = floatval($row['recurring_total']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = '$new_recurring_amount', recurring_last_sent = CURDATE(), recurring_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_frequency) WHERE recurring_id = $recurring_id");

    //Also update the newly created invoice with the new amounts
    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = '$new_recurring_amount' WHERE invoice_id = $new_invoice_id");

    if($config_recurring_auto_send_invoice == 1){
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON contact_id = primary_contact
            LEFT JOIN companies ON invoices.company_id = companies.company_id
            WHERE invoice_id = $new_invoice_id"
        );

        $row = mysqli_fetch_array($sql);
        $invoice_prefix = $row['invoice_prefix'];
        $invoice_number = $row['invoice_number'];
        $invoice_scope = $row['invoice_scope'];
        $invoice_date = $row['invoice_date'];
        $invoice_due = $row['invoice_due'];
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_url_key = $row['invoice_url_key'];
        $client_id = $row['client_id'];
        $client_name = $row['client_name'];
        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];
        $contact_phone = formatPhoneNumber($row['contact_phone']);
        $contact_extension = $row['contact_extension'];
        $contact_mobile = formatPhoneNumber($row['contact_mobile']);
        $company_id = $row['company_id'];
        $company_name = $row['company_name'];
        $company_phone = formatPhoneNumber($row['company_phone']);
        $company_email = $row['company_email'];
        $company_website = $row['company_website'];


        // Email to client

        $subject = "Invoice $invoice_prefix$invoice_number";
        $body    = "Hello $contact_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: $$invoice_amount<br>Due Date: $invoice_due<br><br><br>To view your invoice click <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$new_invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>$company_phone";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_invoice_from_email, $config_invoice_from_name,
            $contact_email, $contact_name,
            $subject, $body);

        if ($mail === true) {
            // Add send history
            mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Force Emailed Invoice!', history_invoice_id = $new_invoice_id, company_id = $session_company_id");

            // Update Invoice Status to Sent
            mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', invoice_client_id = $client_id WHERE invoice_id = $new_invoice_id AND company_id = $session_company_id");

        } else {
            // Error reporting
            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', notification_client_id = $client_id, company_id = $company_id");
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id, company_id = $session_company_id");
        }

    } //End Recurring Invoices Loop

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = '$session_name forced recurring invoice into an invoice', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $new_invoice_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Recurring Invoice Forced";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

} //End Force Recurring

if(isset($_POST['export_trips_csv'])){
    $date_from = sanitizeInput($_POST['date_from']);
    $date_to = sanitizeInput($_POST['date_to']);
    if(!empty($date_from) && !empty($date_to)){
        $date_query = "AND DATE(trip_date) BETWEEN '$date_from' AND '$date_to'";
        $file_name_date = "$date_from-to-$date_to";
    }else{
        $date_query = "";
        $file_name_date = date('Y-m-d');
    }

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM trips 
        LEFT JOIN clients ON trip_client_id = client_id
        WHERE trips.company_id = $session_company_id
        $date_query
        ORDER BY trip_date DESC"
    );

    if(mysqli_num_rows($sql) > 0){
        $delimiter = ",";
        $filename = "$session_company_name-Trips-$file_name_date.csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Purpose', 'Source', 'Destination', 'Miles');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_assoc($sql)){
            $lineData = array($row['trip_date'], $row['trip_purpose'], $row['trip_source'], $row['trip_destination'], $row['trip_miles']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if(isset($_GET['export_client_invoices_csv'])){
    $client_id = intval($_GET['export_client_invoices_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_client_id = $client_id ORDER BY invoice_number ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Invoices-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Invoice Number', 'Scope', 'Amount', 'Issued Date', 'Due Date', 'Status');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['invoice_prefix'] . $row['invoice_number'], $row['invoice_scope'], $row['invoice_amount'], $row['invoice_date'], $row['invoice_due'], $row['invoice_status']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if(isset($_GET['export_client_recurring_csv'])){
    $client_id = intval($_GET['export_client_recurring_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_client_id = $client_id ORDER BY recurring_number ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Recurring Invoices-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Recurring Number', 'Scope', 'Amount', 'Frequency', 'Date Created');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['recurring_prefix'] . $row['recurring_number'], $row['recurring_scope'], $row['recurring_amount'], ucwords($row['recurring_frequency'] . "ly"), $row['recurring_created_at']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if(isset($_GET['export_client_quotes_csv'])){
    $client_id = intval($_GET['export_client_quotes_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_client_id = $client_id ORDER BY quote_number ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Quotes-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Quote Number', 'Scope', 'Amount', 'Date', 'Status');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['quote_prefix'] . $row['quote_number'], $row['quote_scope'], $row['quote_amount'], $row['quote_date'], $row['quote_status']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if(isset($_GET['export_client_payments_csv'])){
    $client_id = intval($_GET['export_client_payments_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM payments, invoices WHERE invoice_client_id = $client_id AND payment_invoice_id = invoice_id ORDER BY payment_date ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Payments-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Payment Date', 'Invoice Date', 'Invoice Number', 'Invoice Amount', 'Payment Amount', 'Payment Method', 'Referrence');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['payment_date'], $row['invoice_date'], $row['invoice_prefix'] . $row['invoice_number'], $row['invoice_amount'], $row['payment_amount'], $row['payment_method'], $row['payment_reference']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if(isset($_GET['export_client_trips_csv'])){
    $client_id = intval($_GET['export_client_trips_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM trips WHERE trip_client_id = $client_id ORDER BY trip_date ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Trips-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Purpose', 'Source', 'Destination', 'Miles');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['trip_date'], $row['trip_purpose'], $row['trip_source'], $row['trip_destination'], $row['trip_miles']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;

}

if(isset($_POST['export_client_pdf'])){

    validateAdminRole();

    $client_id = intval($_POST['client_id']);
    $export_contacts = intval($_POST['export_contacts']);
    $export_locations = intval($_POST['export_locations']);
    $export_assets = intval($_POST['export_assets']);
    $export_software = intval($_POST['export_software']);
    $export_logins = intval($_POST['export_logins']);
    $export_networks = intval($_POST['export_networks']);
    $export_certificates = intval($_POST['export_certificates']);
    $export_domains = intval($_POST['export_domains']);
    $export_tickets = intval($_POST['export_tickets']);
    $export_scheduled_tickets = intval($_POST['export_scheduled_tickets']);
    $export_vendors = intval($_POST['export_vendors']);
    $export_invoices = intval($_POST['export_invoices']);
    $export_recurring = intval($_POST['export_recurring']);
    $export_quotes = intval($_POST['export_quotes']);
    $export_payments = intval($_POST['export_payments']);
    $export_trips = intval($_POST['export_trips']);
    $export_logs = intval($_POST['export_logs']);


    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients 
        LEFT JOIN contacts ON primary_contact = contact_id 
        LEFT JOIN locations ON primary_location = location_id 
        WHERE client_id = $client_id 
        AND clients.company_id = $session_company_id
    ");

    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    $location_address = $row['location_address'];
    $location_city = $row['location_city'];
    $location_state = $row['location_state'];
    $location_zip = $row['location_zip'];
    $contact_name = $row['contact_name'];
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_email = $row['contact_email'];
    $client_website = $row['client_website'];

    $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
    $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_client_id = $client_id ORDER BY location_name ASC");
    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id ORDER BY vendor_name ASC");
    if(isset($_GET['passwords'])){
        $sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_client_id = $client_id ORDER BY login_name ASC");
    }
    $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE asset_client_id = $client_id ORDER BY asset_type ASC");
    $sql_asset_workstations = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE asset_client_id = $client_id AND (asset_type = 'desktop' OR asset_type = 'laptop') ORDER BY asset_name ASC");
    $sql_asset_servers = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id AND asset_type = 'server' ORDER BY asset_name ASC");
    $sql_asset_vms = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id AND asset_type = 'virtual machine' ORDER BY asset_name ASC");
    $sql_asset_network = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id AND (asset_type = 'Firewall/Router' OR asset_type = 'Switch' OR asset_type = 'Access Point') ORDER BY asset_type ASC");
    $sql_asset_other = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE asset_client_id = $client_id AND (asset_type NOT LIKE 'laptop' AND asset_type NOT LIKE 'desktop' AND asset_type NOT LIKE 'server' AND asset_type NOT LIKE 'virtual machine' AND asset_type NOT LIKE 'firewall/router' AND asset_type NOT LIKE 'switch' AND asset_type NOT LIKE 'access point') ORDER BY asset_type ASC");
    $sql_networks = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_client_id = $client_id ORDER BY network_name ASC");
    $sql_domains = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_client_id = $client_id ORDER BY domain_name ASC");
    $sql_certficates = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_client_id = $client_id ORDER BY certificate_name ASC");
    $sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC");

    ?>

    <script src='plugins/pdfmake/pdfmake.min.js'></script>
    <script src='plugins/pdfmake/vfs_fonts.js'></script>
    <script>

        var docDefinition = {
            info: {
                title: '<?php echo strtoAZaz09($client_name); ?>- IT Documentation',
                author: <?php echo json_encode($session_company_name); ?>
            },

            pageMargins: [ 15, 15, 15, 15 ],

            content: [
                {
                    text: <?php echo json_encode($client_name); ?>,
                    style: 'title'
                },

                {
                    layout: 'lightHorizontalLines',
                    table: {
                        body: [
                            [
                                {
                                    text: 'Address',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($location_address); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'City State Zip',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode("$location_city $location_state $location_zip"); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_phone); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Website',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($client_website); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Contact',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Email',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_email); ?>,
                                    style: 'item'
                                }
                            ]
                        ]
                    }
                },

                //Contacts Start
                <?php if(mysqli_num_rows($sql_contacts) > 0 && $export_contacts == 1){ ?>
                {
                    text: 'Contacts',
                    style: 'title'
                },

                {
                    table: {
                        // headers are automatically repeated if the table spans over multiple pages
                        // you can declare how many rows should be treated as headers
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Title',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Department',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Email',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Mobile',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_contacts)){
                            $contact_name = $row['contact_name'];
                            $contact_title = $row['contact_title'];
                            $contact_phone = formatPhoneNumber($row['contact_phone']);
                            $contact_extension = $row['contact_extension'];
                            if(!empty($contact_extension)){
                                $contact_extension = "x$contact_extension";
                            }
                            $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                            $contact_email = $row['contact_email'];
                            $contact_department = $row['contact_department'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_title); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_department); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_email); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$contact_phone $contact_extension"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_mobile); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Contact END

                //Locations Start
                <?php if(mysqli_num_rows($sql_locations) > 0 && $export_locations == 1){ ?>
                {
                    text: 'Locations',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Address',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_locations)){
                            $location_name = $row['location_name'];
                            $location_address = $row['location_address'];
                            $location_city = $row['location_city'];
                            $location_state = $row['location_state'];
                            $location_zip = $row['location_zip'];
                            $location_phone = formatPhoneNumber($row['location_phone']);
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$location_address $location_city $location_state $location_zip"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_phone); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Locations END

                //Vendors Start
                <?php if(mysqli_num_rows($sql_vendors) > 0 && $export_vendors == 1){ ?>
                {
                    text: 'Vendors',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Description',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Website',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Account Number',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_vendors)){
                            $vendor_name = $row['vendor_name'];
                            $vendor_description = $row['vendor_description'];
                            $vendor_account_number = $row['vendor_account_number'];
                            $vendor_contact_name = $row['vendor_contact_name'];
                            $vendor_phone = formatPhoneNumber($row['vendor_phone']);
                            $vendor_email = $row['vendor_email'];
                            $vendor_website = $row['vendor_website'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($vendor_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_description); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_phone); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_website); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_account_number); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Vendors END

                //Logins Start
                <?php if(isset($_GET['passwords'])){ ?>
                <?php if(mysqli_num_rows($sql_logins) > 0 && $export_logins == 1){ ?>
                {
                    text: 'Logins',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Username',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Password',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'URL',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Notes',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_logins)){
                            $login_name = $row['login_name'];
                            $login_username = decryptLoginEntry($row['login_username']);
                            $login_password = decryptLoginEntry($row['login_password']);
                            $login_uri = $row['login_uri'];
                            $login_note = $row['login_note'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($login_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_username); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_password); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_uri); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_note); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php

                }
                }

                ?>
                //Logins END

                //Assets Start
                <?php if(mysqli_num_rows($sql_assets) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Assets',
                    style: 'assetTitle'
                },
                <?php } ?>
                //Assets END

                //Asset Workstations Start
                <?php if(mysqli_num_rows($sql_asset_workstations) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Workstations',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Assigned To',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_workstations)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $contact_name = $row['contact_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Workstation END

                //Assets Servers Start
                <?php if(mysqli_num_rows($sql_asset_servers) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Servers',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_servers)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Servers END

                //Asset VMs Start
                <?php if(mysqli_num_rows($sql_asset_vms) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Virtual Machines',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_vms)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset VMs END

                //Assets Network Devices Start
                <?php if(mysqli_num_rows($sql_asset_network) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Network Devices',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_network)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Network Devices END

                //Asset Other Start
                <?php if(mysqli_num_rows($sql_asset_other) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Other Devices',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_other)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['asset_ip'];
                            $asset_mac = $row['asset_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Other END

                //Software Start
                <?php if(mysqli_num_rows($sql_software) > 0 && $export_software == 1){ ?>
                {
                    text: 'Software',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'License',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Notes',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_software)){
                            $software_name = $row['software_name'];
                            $software_type = $row['software_type'];
                            $software_license_type = $row['software_license_type'];
                            $software_notes = $row['software_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($software_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_license_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_notes); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Software END

                //Networks Start
                <?php if(mysqli_num_rows($sql_networks) > 0 && $export_networks == 1){ ?>
                {
                    text: 'Networks',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'vLAN',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Network Subnet',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Gateway',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'DHCP Range',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_networks)){
                            $network_name = $row['network_name'];
                            $network_vlan = $row['network_vlan'];
                            $network = $row['network'];
                            $network_gateway = $row['network_gateway'];
                            $network_dhcp_range = $row['network_dhcp_range'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($network_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_vlan); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_gateway); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_dhcp_range); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Networks END

                //Domains Start
                <?php if(mysqli_num_rows($sql_domains) > 0 && $export_domains == 1){ ?>
                {
                    text: 'Domains',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Domain Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Expire',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_domains)){
                            $domain_name = $row['domain_name'];
                            $domain_expire = $row['domain_expire'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($domain_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($domain_expire); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Domains END

                //Certificates Start
                <?php if(mysqli_num_rows($sql_certficates) > 0 && $export_certificates == 1){ ?>
                {
                    text: 'Certificates',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Certificate Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Domain Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Issuer',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Expiration Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_certficates)){
                            $certificate_name = $row['certificate_name'];
                            $certificate_domain = $row['certificate_domain'];
                            $certificate_issued_by = $row['certificate_issued_by'];
                            $certificate_expire = $row['certificate_expire'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($certificate_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_domain); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_issued_by); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_expire); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Certificates END



            ], //End Content,
            styles: {
                //Title
                title: {
                    fontSize: 15,
                    margin: [0,20,0,5],
                    bold: true
                },
                assetTitle: {
                    fontSize: 15,
                    margin: [0,20,0,0],
                    bold: true
                },
                //Asset Subtitle
                assetSubTitle: {
                    fontSize: 10,
                    margin: [0,10,0,5],
                    bold: true
                },
                //Item Header
                itemHeader: {
                    fontSize: 9,
                    margin: [0,1,0,1],
                    bold: true
                },
                //item
                item: {
                    fontSize: 9,
                    margin: [0,1,0,1]
                }
            }
        };


        pdfMake.createPdf(docDefinition).download('<?php echo strtoAZaz09($client_name); ?>-IT_Documentation-<?php echo date('Y-m-d'); ?>.pdf');

    </script>


    <?php

}

?>

<?php

if(isset($_GET['logout'])){
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Logout', log_action = 'Success', log_description = '$session_name logged out', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
    mysqli_query($mysqli, "UPDATE users SET user_php_session = '' WHERE user_id = $session_user_id");

    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);

    setcookie("user_encryption_session_key", '', time() - 3600, "/");
    unset($_COOKIE['user_encryption_session_key']);

    setcookie("user_extension_key", '', time() - 3600, "/");
    unset($_COOKIE['user_extension_key']);

    session_unset();
    session_destroy();

    header('Location: login.php');
}

?>
