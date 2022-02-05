<?php

include("config.php");
include("functions.php");
include("check_login.php");

require("vendor/PHPMailer-6.5.1/src/PHPMailer.php");
require("vendor/PHPMailer-6.5.1/src/SMTP.php");

// Initiate PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if(isset($_POST['change_records_per_page'])){

    $_SESSION['records_per_page'] = intval($_POST['change_records_per_page']);
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['switch_company'])){
    $company_id = intval($_GET['switch_company']);

    //Get Company Name
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
    $company_name = $row['company_name'];

    //Check to see if user has Permission to access the company
    if(in_array($company_id,$session_user_company_access_array)){
        
        mysqli_query($mysqli,"UPDATE user_settings SET user_default_company = $company_id WHERE user_id = $session_user_id");

        $_SESSION['alert_message'] = "Switched Companies!";

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Switch', log_description = '$session_name switched to company $company_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");
    
    }else{
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "What are you trying to DO! WHy did you do this? WHYYY??";

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Switch', log_description = '$session_name tried to switch to company $company_name but does not have permission', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");
    }
    
    header("Location: dashboard_financial.php");
  
}

if(isset($_POST['add_user'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_specific_encryption_ciphertext = encryptUserSpecificKey($_POST['password']); //TODO: Consider this users role - if they don't need access to logins, potentially don't set this -- just know it's a pain to add afterwards (you'd need to reset their password).
    $default_company = intval($_POST['default_company']);
    $role = intval($_POST['role']);

    mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext', user_created_at = NOW()");

    $user_id = mysqli_insert_id($mysqli);

    if(!file_exists("uploads/users/$user_id/")) {
        mkdir("uploads/users/$user_id");
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

            //Set Avatar
            mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            $_SESSION['alert_type'] = "danger";
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Create Settings
    mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = $user_id, user_role = $role, user_default_company = $default_company");

    //Create Company Access Permissions
    mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $user_id, company_id = $default_company");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Create', log_description = '$session_name created user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "User <strong>$user_name</strong> created!";
    
    header("Location: users.php");

}

if(isset($_POST['edit_user'])){

    $user_id = intval($_POST['user_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $new_password = trim($_POST['new_password']);
    $default_company = intval($_POST['default_company']);
    $role = intval($_POST['role']);
    $existing_file_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['existing_file_name'])));

    if(!file_exists("uploads/users/$user_id/")) {
        mkdir("uploads/users/$user_id");
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
            $_SESSION['alert_type'] = "danger";
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }
    
    mysqli_query($mysqli,"UPDATE users SET user_name = '$name', user_email = '$email', user_updated_at = NOW() WHERE user_id = $user_id");

    if(!empty($new_password)){
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_specific_encryption_ciphertext = encryptUserSpecificKey($_POST['new_password']);
        mysqli_query($mysqli,"UPDATE users SET user_password = '$new_password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext' WHERE user_id = $user_id");
        //Extended Logging
        $extended_log_description .= ", password changed";
    }

    //Update User Settings
    mysqli_query($mysqli,"UPDATE user_settings SET user_role = $role, user_default_company = $default_company WHERE user_id = $user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name modified user $name $extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "User <strong>$name</strong> updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_profile'])){

    $user_id = intval($_POST['user_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $new_password = trim($_POST['new_password']);
    $existing_file_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['existing_file_name'])));
    $logout = FALSE;

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
            $_SESSION['alert_type'] = "danger";
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }
    
    mysqli_query($mysqli,"UPDATE users SET user_name = '$name', user_email = '$email', user_updated_at = NOW() WHERE user_id = $user_id");

    if(!empty($new_password)){
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_specific_encryption_ciphertext = encryptUserSpecificKey($_POST['new_password']);
        mysqli_query($mysqli,"UPDATE users SET user_password = '$new_password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext' WHERE user_id = $user_id");

        $extended_log_description .= ", password changed";
        $logout = TRUE;
    }

    // Enable extension access, only if it isn't already setup (user doesn't have cookie)
    if(isset($_POST['extension']) && $_POST['extension'] == 'Yes'){
        if(!isset($_COOKIE['user_extension_key'])){
            $extension_key = keygen();
            mysqli_query($mysqli, "UPDATE users SET user_extension_key = '$extension_key' WHERE user_id = $user_id");

            $extended_log_description .= ", extension access enabled";
            $logout = TRUE;
        }
    }

    // Disable extension access
    if(!isset($_POST['extension'])){
        mysqli_query($mysqli, "UPDATE users SET user_extension_key = '' WHERE user_id = $user_id");
        $extended_log_description .= ", extension access disabled";
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Preferences', log_action = 'Modify', log_description = '$session_name modified their preferences$extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "User preferences updated";

    if ($logout){
        header('Location: post.php?logout');
    }
    else{
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_POST['edit_user_companies'])){

    $user_id = intval($_POST['user_id']);

    mysqli_query($mysqli,"DELETE FROM user_companies WHERE user_id = $user_id");

    foreach($_POST['companies'] as $company){
        intval($company);
        mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $user_id, company_id = $company");
    }

    //Logging
    //Get User Name
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $name = $row['user_name'];
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name updated company permissions for user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Company permssions updated for user <strong>$name</strong>";
    
    header("Location: users.php");

}

if(isset($_POST['edit_user_clients'])){

    $user_id = intval($_POST['user_id']);
    
    mysqli_query($mysqli,"DELETE FROM user_clients WHERE user_id = $user_id");

    foreach($_POST['clients'] as $client){
        intval($client);
        mysqli_query($mysqli,"INSERT INTO user_clients SET user_id = $user_id, client_id = $client");
    }
    
    //Logging
    //Get User Name
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $name = $row['user_name'];
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modify', log_description = '$session_name updated client permissions for user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Client <strong>$client_imploded</strong> added to user $user_id!";
    
    header("Location: users.php");

}

if(isset($_GET['archive_user'])){
    $user_id = intval($_GET['archive_user']);

    mysqli_query($mysqli,"UPDATE users SET user_archived_at = NOW() WHERE user_id = $user_id");

    //Logging
    //Get User Name
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $name = $row['user_name'];
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Archive', log_description = '$session_name archived user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "<strong>$name</strong> archived";
    
    header("Location: users.php");

}

if(isset($_GET['delete_user'])){
    $user_id = intval($_GET['delete_user']);

    mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $user_id");
    mysqli_query($mysqli,"DELETE FROM user_settings WHERE user_id = $user_id");
    mysqli_query($mysqli,"DELETE FROM logs WHERE log_user_id = $user_id");
    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_created_by = $user_id");
    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_closed_by = $user_id");
    mysqli_query($mysqli,"DELETE FROM ticket_replies WHERE ticket_reply_by = $user_id");
    mysqli_query($mysqli,"DELETE FROM user_companies WHERE user_id = $user_id");
    mysqli_query($mysqli,"DELETE FROM user_clients WHERE user_id = $user_id");

    //Logging
    //Get User Name
    $sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $user_id");
    $row = mysqli_fetch_array($sql);
    $name = $row['user_name'];
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Delete', log_description = '$session_name deleted user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "User <strong>$name</strong> deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}
// API Key
if(isset($_POST['add_api_key'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire'])));
    // Gen a Key
    $secret = keygen();

    mysqli_query($mysqli,"INSERT INTO api_keys SET api_key_name = '$name', api_key_secret = '$secret', api_key_expire = '$expire', api_key_created_at = NOW(), company_id = $session_company_id");

    $api_key_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API Key', log_action = 'Create', log_description = '$session_name created API Key $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "API Key <strong>$name</strong> created";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_api_key'])){

    $api_key_id = intval($_POST['api_key_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire'])));

    mysqli_query($mysqli,"UPDATE api_keys SET api_key_name = '$name', api_key_expire = '$expire', api_key_updated_at = NOW() WHERE api_key_id = $api_key_id AND company_id = $session_company_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API Key', log_action = 'Modify', log_description = '$session_name modified API Key $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "API Key <strong>$name</strong> updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_api_key'])){
    $api_key_id = intval($_GET['delete_api_key']);

    // Get API Key Name
    $sql = mysqli_query($mysqli,"SELECT * FROM api_keys WHERE api_key_id = $api_key_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $name = $row['api_key_name'];

    mysqli_query($mysqli,"DELETE FROM api_keys WHERE api_key_id = $api_key_id AND company_id = $session_company_id");

    // Logging   
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API Key', log_action = 'Delete', log_description = '$session_name deleted user $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "API Key <strong>$name</strong> deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_company'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));

    mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_currency = '$currency_code', company_created_at = NOW()");

    $company_id = mysqli_insert_id($mysqli);
    $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    $config_api_key = keygen();
    
    mkdir("uploads/clients/$company_id");
    mkdir("uploads/expenses/$company_id");
    mkdir("uploads/settings/$company_id");
    mkdir("uploads/tmp/$company_id");

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

            mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = $company_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Set User Company Permissions
    mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $session_user_id, company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO settings SET company_id = $company_id, config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_prefix = 'REC-', config_recurring_next_number = 1, config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_api_key = '$config_api_key', config_recurring_auto_send_invoice = 1, config_default_net_terms = 7, config_send_invoice_reminders = 1, config_enable_cron = 0, config_ticket_next_number = 1, config_base_url = '$config_base_url'");

    //Create Some Data

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', opening_balance = 0, account_currency_code = '$currency_code', account_created_at = NOW(), company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Office Supplies', category_type = 'Expense', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Travel', category_type = 'Expense', category_color = 'red', category_created_at = NOW(), company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Advertising', category_type = 'Expense', category_color = 'green', category_created_at = NOW(), company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Service', category_type = 'Income', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Friend', category_type = 'Referral', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Search Engine', category_type = 'Referral', category_color = 'red', category_created_at = NOW(), company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Cash', category_type = 'Payment Method', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Check', category_type = 'Payment Method', category_color = 'red', category_created_at = NOW(), company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = 'Default', calendar_color = 'blue', calendar_created_at = NOW(), company_id = $company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Create', log_description = '$session_name created company $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> created";
    
    header("Location: companies.php");

}

if(isset($_POST['edit_company'])){
    $company_id = intval($_POST['company_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));

    $existing_file_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['existing_file_name']));

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

    mysqli_query($mysqli,"UPDATE companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_currency = '$currency_code', company_updated_at = NOW() WHERE company_id = $company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Modify', log_description = '$session_name modified company $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> updated";
    
    header("Location: companies.php");

}

if(isset($_GET['archive_company'])){
    $company_id = intval($_GET['archive_company']);

    mysqli_query($mysqli,"UPDATE companies SET company_archived_at = NOW() WHERE company_id = $company_id");


    //Logging
    //Get Company Name
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
    $company_name = $row['company_name'];
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Archive', log_description = '$session_name archived company $company_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Company <strong>$company_name</strong> archived";
    
    header("Location: companies.php");

}

if(isset($_GET['delete_company'])){
    $company_id = intval($_GET['delete_company']);

    //Get Company Name
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $company_id");
    $row = mysqli_fetch_array($sql);
    $company_name = $row['company_name'];

    //Delete Company and all relational data A-Z

    mysqli_query($mysqli,"DELETE FROM accounts WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM alerts WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM assets WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM calendars WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM categories WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM certificates WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM clients WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM contacts WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM documents WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM domains WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM events WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM expenses WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM files WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM history WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM invoices WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM locations WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM logins WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM logs WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM networks WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM payments WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM products WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM quotes WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM records WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM recurring WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM revenues WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM software WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM taxes WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM tickets WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM ticket_updates WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM transfers WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM trips WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM vendors WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM settings WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM api_keys WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM campaigns WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM messages WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM custom_links WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM user_companies WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM tags WHERE company_id = $company_id");
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE company_id = $company_id");

    //Delete Company Files
    removeDirectory('uploads/clients/$company_id');
    removeDirectory('uploads/expenses/$company_id');
    removeDirectory('uploads/settings/$company_id');
    removeDirectory('uploads/tmp/$company_id');

    //Finally Remove the company
    mysqli_query($mysqli,"DELETE FROM companies WHERE company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Delete', log_description = '$session_name deleted company $company_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Company <strong>$company_name</strong> deleted";
    
    header("Location: logout.php");
  
}

if(isset($_POST['verify'])){

    require_once("rfc6238.php");
    $currentcode = mysqli_real_escape_string($mysqli,$_POST['code']);  //code to validate, for example received from device

    if(TokenAuth6238::verify($session_token,$currentcode)){
        $_SESSION['alert_message'] = "VALID!";
    }else{
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "IN-VALID!";
    } 

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_general_settings'])){

    $config_base_url = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_base_url'])));

    mysqli_query($mysqli,"UPDATE settings SET config_base_url = '$config_base_url' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified general settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "General settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_mail_settings'])){

    $config_smtp_host = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_smtp_host'])));
    $config_smtp_port = intval($_POST['config_smtp_port']);
    $config_smtp_username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_smtp_username'])));
    $config_smtp_password = trim(mysqli_real_escape_string($mysqli,$_POST['config_smtp_password']));
    $config_mail_from_email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_mail_from_email'])));
    $config_mail_from_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_mail_from_name'])));

    mysqli_query($mysqli,"UPDATE settings SET config_smtp_host = '$config_smtp_host', config_smtp_port = $config_smtp_port, config_smtp_username = '$config_smtp_username', config_smtp_password = '$config_smtp_password', config_mail_from_email = '$config_mail_from_email', config_mail_from_name = '$config_mail_from_name' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified mail settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Mail settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['test_email'])){
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));

    $mail = new PHPMailer(true);

    //Mail Server Settings

    $mail->SMTPDebug = 2;                                       // Enable verbose debug output
    $mail->isSMTP();                                            // Set mailer to use SMTP
    $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    $mail->Username   = $config_smtp_username;                     // SMTP username
    $mail->Password   = $config_smtp_password;                               // SMTP password
    $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
    $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

    //Recipients
    $mail->setFrom($config_mail_from_email, $config_mail_from_name);
    $mail->addAddress("$email");     // Add a recipient

    // Content
    $mail->isHTML(true);                                  // Set email format to HTML
    
    $mail->Subject = "Hi'ya there Chap";
    $mail->Body    = "Hello there Chap ;) Don't worry this won't hurt a bit, it's just a test";

    if($mail->send()){
        $_SESSION['alert_message'] = "Test email sent successfully";
    }else{
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Test email failed";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_invoice_quote_settings'])){

    $config_invoice_prefix = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_invoice_prefix'])));
    $config_invoice_next_number = intval($_POST['config_invoice_next_number']);
    $config_invoice_footer = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_invoice_footer'])));
    $config_recurring_prefix = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_recurring_prefix'])));
    $config_recurring_next_number = intval($_POST['config_recurring_next_number']);
    $config_quote_prefix = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_quote_prefix'])));
    $config_quote_next_number = intval($_POST['config_quote_next_number']);
    $config_quote_footer = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_quote_footer'])));

    mysqli_query($mysqli,"UPDATE settings SET config_invoice_prefix = '$config_invoice_prefix', config_invoice_next_number = $config_invoice_next_number, config_invoice_footer = '$config_invoice_footer', config_recurring_prefix = '$config_recurring_prefix', config_recurring_next_number = $config_recurring_next_number, config_quote_prefix = '$config_quote_prefix', config_quote_next_number = $config_quote_next_number, config_quote_footer = '$config_quote_footer' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified invoice / quote settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Invoice / Quote Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_ticket_settings'])){

    $config_ticket_prefix = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_ticket_prefix'])));
    $config_ticket_next_number = intval($_POST['config_ticket_next_number']);

    mysqli_query($mysqli,"UPDATE settings SET config_ticket_prefix = '$config_ticket_prefix', config_ticket_next_number = $config_ticket_next_number WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified ticket settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Ticket Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_default_settings'])){

    $expense_account = intval($_POST['expense_account']);
    $payment_account = intval($_POST['payment_account']);
    $payment_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['payment_method'])));
    $expense_payment_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expense_payment_method'])));
    $transfer_from_account = intval($_POST['transfer_from_account']);
    $transfer_to_account = intval($_POST['transfer_to_account']);
    $calendar = intval($_POST['calendar']);
    $net_terms = intval($_POST['net_terms']);

    mysqli_query($mysqli,"UPDATE settings SET config_default_expense_account = $expense_account, config_default_payment_account = $payment_account, config_default_payment_method = '$payment_method', config_default_expense_payment_method = '$expense_payment_method', config_default_transfer_from_account = $transfer_from_account, config_default_transfer_to_account = $transfer_to_account, config_default_calendar = $calendar, config_default_net_terms = $net_terms WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified default settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Default Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_alert_settings'])){

    $config_enable_cron = intval($_POST['config_enable_cron']);
    $config_enable_alert_domain_expire = intval($_POST['config_enable_alert_domain_expire']);
    $config_send_invoice_reminders = intval($_POST['config_send_invoice_reminders']);
    $config_invoice_overdue_reminders = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_invoice_overdue_reminders']));

    mysqli_query($mysqli,"UPDATE settings SET config_send_invoice_reminders = $config_send_invoice_reminders, config_invoice_overdue_reminders = '$config_invoice_overdue_reminders', config_enable_cron = $config_enable_cron, config_enable_alert_domain_expire = $config_enable_alert_domain_expire WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified alert settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Alert Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_online_payment_settings'])){

    $config_stripe_enable = intval($_POST['config_stripe_enable']);
    $config_stripe_publishable = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_stripe_publishable'])));
    $config_stripe_secret = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_stripe_secret'])));

    mysqli_query($mysqli,"UPDATE settings SET config_stripe_enable = $config_stripe_enable, config_stripe_publishable = '$config_stripe_publishable', config_stripe_secret = '$config_stripe_secret' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = '$session_name modified online payment settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Online Payment Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['enable_2fa'])){

    $token = mysqli_real_escape_string($mysqli,$_POST['token']);

    mysqli_query($mysqli,"UPDATE users SET user_token = '$token' WHERE user_id = $session_user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Settings', log_action = 'Modify', log_description = '$session_name enabled 2FA on their account', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Two-factor authentication enabled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['disable_2fa'])){

    mysqli_query($mysqli,"UPDATE users SET user_token = '' WHERE user_id = $session_user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Settings', log_action = 'Modify', log_description = '$session_name disabled 2FA on their account', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Two-factor authentication disabled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['download_database'])){

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
                    $row[$j] = $row[$j];
                    
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Download', log_description = '$session_name downloaded the database', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Database downloaded";
}

if(isset($_POST['backup_master_key'])){

    //TODO: Verify the user is authorised to view the key?

    $password = $_POST['password'];

    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = '$session_user_id'");
    $userRow = mysqli_fetch_array($sql);

    if(password_verify($password, $userRow['user_password'])) {
        $site_encryption_master_key = decryptUserSpecificKey($userRow['user_specific_encryption_ciphertext'], $password);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Download', log_description = '$session_name retrieved the master encryption key', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");
        mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Settings', alert_message = '$session_name retrieved the master encryption key', alert_date = NOW(), company_id = $session_company_id");


        echo "==============================";
        echo "<br>Master encryption key:<br>";
        echo "<b>$site_encryption_master_key</b>";
        echo "<br>==============================";
    }

    else {
        //Log the failure
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Download', log_description = '$session_name attempted to retrieve the master encryption key (failure)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

        $_SESSION['alert_message'] = "Incorrect password.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['update'])){
    //also check to make sure someone has admin before running this function
    exec("git pull");

    //FORCE UPDATE FUNCTION (Will be added later as a checkbox)
    //git fetch downloads the latest from remote without trying to merge or rebase anything. Then the git reset resets the master branch to what you just fetched. The --hard option changes all the files in your working tree to match the files in origin/master

    //exec("git fetch --all");
    //exec("git reset --hard origin/master");

    //header("Location: post.php?update_db");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Update', log_description = '$session_name ran updates', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Updates successful";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['update_db'])){

    //Alter SQL Structure

    //Put ID Here
    //mysqli_query($mysqli,"ALTER TABLE logs ADD log_ip VARCHAR(200) NULL AFTER log_description");
    //mysqli_query($mysqli,"ALTER TABLE logs ADD log_user_agent VARCHAR(250) NULL AFTER log_ip");

    //85cdc42d0f15e36de5cab00d7f3c799a056e85ef
    //mysqli_query($mysqli,"ALTER TABLE assets ADD asset_install_date DATE NULL AFTER asset_warranty_expire");

    //c88e6b851aadfbde173f7cfe7155dd1ed31adece
    //mysqli_query($mysqli,"ALTER TABLE settings DROP config_enable_alert_low_balance");
    //mysqli_query($mysqli,"ALTER TABLE settings DROP config_account_balance_threshold");
    //mysqli_query($mysqli,"ALTER TABLE clients DROP client_support");
    //mysqli_query($mysqli,"ALTER TABLE tags DROP tag_archived_at");

    //Update 2
    //mysqli_query($mysqli,"ALTER TABLE tags ADD tag_type INT(11) NOT NULL AFTER tag_name");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Update', log_description = '$session_name updated the database structure', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Database structure update successful";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['add_client'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $location_phone = preg_replace("/[^0-9]/", '',$_POST['location_phone']);
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $contact = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact'])));
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $contact_phone = preg_replace("/[^0-9]/", '',$_POST['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '',$_POST['contact_extension']);
    $contact_mobile = preg_replace("/[^0-9]/", '',$_POST['contact_mobile']);
    $contact_email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_email'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $referral = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['referral'])));
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $net_terms = intval($_POST['net_terms']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_currency_code = '$currency_code', client_net_terms = $net_terms, client_notes = '$notes', client_created_at = NOW(), client_accessed_at = NOW(), company_id = $session_company_id");

    $client_id = mysqli_insert_id($mysqli);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
        file_put_contents("uploads/clients/$session_company_id/$client_id/index.php", "");
    }

    //Add Location
    if(!empty($location_phone) OR !empty($address) OR !empty($city) OR !empty($state) OR !empty($zip)){
        mysqli_query($mysqli,"INSERT INTO locations SET location_name = 'Primary', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$location_phone', location_country = '$country', location_created_at = NOW(), location_client_id = $client_id, company_id = $session_company_id");
        
        //Update Primay location in clients
        $location_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");

        //Extended Logging
        $extended_log_description .= ", primary location $address added";
    }

    
    //Add Contact
    if(!empty($contact) OR !empty($title) OR !empty($contact_phone) OR !empty($contact_mobile) OR !empty($contact_email)){
        mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$contact', contact_title = '$title', contact_phone = '$contact_phone', contact_extension = '$contact_extension', contact_mobile = '$contact_mobile', contact_email = '$contact_email', contact_created_at = NOW(), contact_client_id = $client_id, company_id = $session_company_id");
        
        //Update Primay contact in clients
        $contact_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
    
        //Extended Logging
        $extended_log_description .= ", primary contact $contact added";
    }

    //Add Tags
    if(isset($_POST['tags'])){
        foreach($_POST['tags'] as $tag){
            intval($tag);
            mysqli_query($mysqli,"INSERT INTO client_tags SET client_id = $client_id, tag_id = $tag");
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Create', log_description = '$session_name created $name$extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Client <strong>$name</strong> created";
    
    header("Location: clients.php");
    exit;
    
}

if(isset($_POST['edit_client'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $referral = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['referral'])));
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $net_terms = intval($_POST['net_terms']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"UPDATE clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_currency_code = '$currency_code', client_net_terms = $net_terms, client_notes = '$notes', client_updated_at = NOW() WHERE client_id = $client_id AND company_id = $session_company_id");

    //Tags
    //Delete existing tags
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE client_id = $client_id");
    
    //Add new tags
    foreach($_POST['tags'] as $tag){
        intval($tag);
        mysqli_query($mysqli,"INSERT INTO client_tags SET client_id = $client_id, tag_id = $tag");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Modify', log_description = '$session_name modified client $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Client <strong>$name</strong> updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['delete_client'])){
    $client_id = intval($_GET['delete_client']);

    //Get Client Name
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = $row['client_name'];

    //Delete Client Data
    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM certificates WHERE certificate_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM documents WHERE document_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM domains WHERE domain_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM events WHERE event_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM files WHERE file_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM locations WHERE location_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM logins WHERE login_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM networks WHERE network_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM software WHERE software_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE client_id = $client_id");
    mysqli_query($mysqli,"DELETE FROM user_clients WHERE client_id = $client_id");

    $sql = mysqli_query($mysqli,"SELECT recurring_id FROM recurring WHERE recurring_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $recurring_id = $row['recurring_id'];

        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_recurring_id = $recurring_id");
    }
    mysqli_query($mysqli,"DELETE FROM recurring WHERE recurring_client_id = $client_id");
    
    //Delete Quote Items
    $sql = mysqli_query($mysqli,"SELECT quote_id FROM quotes WHERE quote_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $quote_id = $row['quote_id'];

        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_quote_id = $quote_id");
    }
    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_client_id = $client_id");


    //Delete Financial Data this will affect the accounting
    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_client_id = $client_id");
    
    //Delete Invoices and Invoice Referencing data
    $sql = mysqli_query($mysqli,"SELECT invoice_id FROM invoices WHERE invoice_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $invoice_id = $row['invoice_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
        mysqli_query($mysqli,"DELETE FROM history WHERE history_invoice_id = $invoice_id");
    }
    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_client_id = $client_id");

    mysqli_query($mysqli,"DELETE FROM trips WHERE trip_client_id = $client_id");
    
    //Delete Tickets and log Data
    mysqli_query($mysqli,"DELETE FROM logs WHERE log_client_id = $client_id");
    
    $sql = mysqli_query($mysqli,"SELECT ticket_id FROM tickets WHERE ticket_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $ticket_id = $row['ticket_id'];

        mysqli_query($mysqli,"DELETE FROM ticket_replies WHERE reply_ticket_id = $ticket_id");
    }
    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_client_id = $client_id");

    //Delete Client Files
    removeDirectory('uploads/clients/$client_id');

    //Finally Remove the Client
    mysqli_query($mysqli,"DELETE FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Delete', log_description = '$session_name deleted client $client_name and all referring data', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Client $client_name deleted along with all referring data";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]); 
}

if(isset($_POST['add_calendar'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $color = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['color'])));

    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = '$name', calendar_color = '$color', calendar_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar', log_action = 'Create', log_description = '$session_name created calendar $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Calendar created, now lets add some events!";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_event'])){

    $calendar_id = intval($_POST['calendar']);
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $start = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['start'])));
    $end = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['end'])));
    $repeat = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['repeat'])));
    $client = intval($_POST['client']);
    $email_event = intval($_POST['email_event']);

    mysqli_query($mysqli,"INSERT INTO events SET event_title = '$title', event_description = '$description', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_created_at = NOW(), event_calendar_id = $calendar_id, event_client_id = $client, company_id = $session_company_id");

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

        $mail = new PHPMailer(true);

        try {

            //Mail Server Settings

            $mail->SMTPDebug = 2;                                       // Enable verbose debug output
            $mail->isSMTP();                                            // Set mailer to use SMTP
            $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = $config_smtp_username;                     // SMTP username
            $mail->Password   = $config_smtp_password;                               // SMTP password
            $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
            $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($config_mail_from_email, $config_mail_from_name);
            $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = "New Calendar Event";
            $mail->Body    = "Hello $contact_name,<br><br>A calendar event has been scheduled: $title at $start<br><br><br>~<br>$company_name<br>$company_phone";

            $mail->send();
            echo 'Message has been sent';

        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar_Event', log_action = 'Email', log_description = '$session_name emailed event $event_title to $contact_name from client $client_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar_Event', log_action = 'Create', log_description = '$session_name created event $title in calendar', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Event added to the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_event'])){

    $event_id = intval($_POST['event_id']);
    $calendar_id = intval($_POST['calendar']);
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $start = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['start'])));
    $end = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['end'])));
    $repeat = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['repeat'])));
    $client = intval($_POST['client']);
    $email_event = intval($_POST['email_event']);

    mysqli_query($mysqli,"UPDATE events SET event_title = '$title', event_description = '$description', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_updated_at = NOW(), event_calendar_id = $calendar_id, event_client_id = $client WHERE event_id = $event_id AND company_id = $session_company_id");

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

        $mail = new PHPMailer(true);

        try {

            //Mail Server Settings

            $mail->SMTPDebug = 2;                                       // Enable verbose debug output
            $mail->isSMTP();                                            // Set mailer to use SMTP
            $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = $config_smtp_username;                     // SMTP username
            $mail->Password   = $config_smtp_password;                               // SMTP password
            $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
            $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($config_mail_from_email, $config_mail_from_name);
            $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = "Calendar Event Rescheduled";
            $mail->Body    = "Hello $contact_name,<br><br>A calendar event has been rescheduled: $title at $start<br><br><br>~<br>$company_name<br>$company_phone";

            $mail->send();
            echo 'Message has been sent';

        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar_Event', log_action = 'Email', log_description = '$session_name Emailed modified event $title to $client_name email $client_email', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar_Event', log_action = 'Modify', log_description = '$session_name modified event $title in calendar', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Event modified on the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_event'])){
    $event_id = intval($_GET['delete_event']);

    //Get Event Title
    $sql = mysqli_query($mysqli,"SELECT * FROM events WHERE event_id = $event_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $event_title = $row['event_title'];

    mysqli_query($mysqli,"DELETE FROM events WHERE event_id = $event_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar_Event', log_action = 'Delete', log_description = '$session_name deleted calendar event titled $event_title', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Event <strong>$event_title</strong> deleted on the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

// Vendors

if(isset($_POST['add_vendor'])){

    $client_id = intval($_POST['client_id']); //Used if this vendor is under a contact otherwise its 0 for under company
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $account_number = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['account_number'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $contact_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_name'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '',$_POST['extension']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    
    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_country = '$country', vendor_address = '$address', vendor_city = '$city', vendor_state = '$state', vendor_zip = '$zip', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_created_at = NOW(), vendor_client_id = $client_id, company_id = $session_company_id");

    $vendor_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Create', log_description = '$session_name created vendor $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Vendor <strong>$name</strong> created";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_vendor'])){

    $vendor_id = intval($_POST['vendor_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $account_number = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['account_number'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $contact_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_name'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '',$_POST['extension']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_country = '$country', vendor_address = '$address', vendor_city = '$city', vendor_state = '$state', vendor_zip = '$zip', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_updated_at = NOW() WHERE vendor_id = $vendor_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Modify', log_description = '$session_name modified vendor $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Vendor <strong>$name</strong> modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['archive_vendor'])){
    $vendor_id = intval($_GET['archive_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = $row['vendor_name'];

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Archive', log_description = '$session_name archived vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name archived";
    
    header("Location: vendors.php");
}

if(isset($_GET['delete_vendor'])){
    $vendor_id = intval($_GET['delete_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = $row['vendor_name'];

    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_id = $vendor_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Delete', log_description = '$session_name deleted vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_type'] = "danger";
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Export', log_description = '$session_name exported vendors to CSV', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_client_id = $client_id, log_user_id = $session_user_id, company_id = $session_company_id");

    exit;
}

// Campaigns
if(isset($_POST['add_campaign'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $from_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['from_name'])));
    $from_email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['from_email'])));
    $content = trim(mysqli_real_escape_string($mysqli,$_POST['content']));
    $status = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['status'])));
    $scheduled_at = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scheduled_at'])));
    
    mysqli_query($mysqli,"INSERT INTO campaigns SET campaign_name = '$name', campaign_subject = '$subject', campaign_from_name = '$from_name', campaign_from_email = '$from_email', campaign_content = '$content', campaign_status = '$status', campaign_scheduled_at = '$scheduled_at', campaign_created_at = NOW(), company_id = $session_company_id");

    $campaign_id = mysqli_insert_id($mysqli);

    //Create Recipient List based off tags selected
    if(isset($_POST['tags'])){
        foreach($_POST['tags'] as $tag){
            intval($tag);
            
            $sql = mysqli_query($mysqli,"SELECT * FROM clients
                LEFT JOIN contacts ON contacts.contact_id = clients.primary_contact
                LEFT JOIN client_tags ON clients.client_id = client_tags.client_id   
                WHERE client_tags.tag_id = $tag 
                AND clients.company_id = $session_company_id 
            ");

            while($row = mysqli_fetch_array($sql)){
                $client_id = $row['client_id'];
                $client_name = $row['client_name'];
                $contact_id = $row['contact_id'];
                $contact_name = $row['contact_name'];
                $contact_email = $row['contact_email'];
                
                //Check to see if the email has already been added if so don't add it
                $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT(message_id) AS count FROM campaign_messages WHERE message_contact_id = $contact_id AND message_campaign_id = $campaign_id"));
                $count = $row['count'];
                if($count == 0){
                    //Generate Unique hash 
                    $message_hash = keygen();
                    mysqli_query($mysqli,"INSERT INTO campaign_messages SET message_hash = '$message_hash', message_created_at = NOW(), message_client_tag_id = $tag, message_contact_id = $contact_id, message_campaign_id = $campaign_id, company_id = $session_company_id");
                }
            }
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Campaign', log_action = 'Create', log_description = '$session_name created mail campaign $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Campaign <strong>$name</strong> created";
    
    //header("Location: campaign_details.php?campaign_id=$campaign_id");
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_campaign'])){

    $campaign_id = intval($_POST['campaign_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $from_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['from_name'])));
    $from_email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['from_email'])));
    $content = trim(mysqli_real_escape_string($mysqli,$_POST['content']));
    $status = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['status'])));
    $scheduled_at = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scheduled_at'])));

    mysqli_query($mysqli,"UPDATE campaigns SET campaign_name = '$name', campaign_subject = '$subject', campaign_from_name = '$from_name', campaign_from_email = '$from_email', campaign_content = '$content', campaign_status = '$status', campaign_scheduled_at = '$scheduled_at', campaign_updated_at = NOW() WHERE campaign_id = $campaign_id AND company_id = $session_company_id");

    //Create Recipient List based off tags selected
    if(isset($_POST['tags'])){
        foreach($_POST['tags'] as $tag){
            intval($tag);
            
            $sql = mysqli_query($mysqli,"SELECT * FROM clients
                LEFT JOIN contacts ON contacts.contact_id = clients.primary_contact
                LEFT JOIN client_tags ON clients.client_id = client_tags.client_id   
                WHERE client_tags.tag_id = $tag 
                AND clients.company_id = $session_company_id 
            ");

            while($row = mysqli_fetch_array($sql)){
                $client_id = $row['client_id'];
                $client_name = $row['client_name'];
                $contact_id = $row['contact_id'];
                $contact_name = $row['contact_name'];
                $contact_email = $row['contact_email'];
                
                //Check to see if the email has already been added if so don't add it
                $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT(message_id) AS count FROM campaign_messages WHERE message_contact_id = $contact_id AND message_campaign_id = $campaign_id"));
                $count = $row['count'];
                if($count == 0){
                    //Generate Unique hash 
                    $message_hash = keygen();
                    mysqli_query($mysqli,"INSERT INTO campaign_messages SET message_hash = '$message_hash', message_created_at = NOW(), message_client_tag_id = $tag, message_contact_id = $contact_id, message_campaign_id = $campaign_id, company_id = $session_company_id");
                }
            }
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Campaign', log_action = 'Modify', log_description = '$session_name modified mail campaign $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Campaign <strong>$name</strong> modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['copy_campaign'])){

    $campaign_id = intval($_GET['copy_campaign']);
    
    $sql = mysqli_query($mysqli,"SELECT * FROM campaigns WHERE campaign_id = $campaign_id AND company_id = $session_company_id");

    $row = mysqli_fetch_array($sql);

    $name = $row['campaign_name'];
    $subject = $row['campaign_subject'];
    $from_name = $row['campaign_from_name'];
    $from_email = $row['campaign_from_email'];
    $content = $row['campaign_content'];
    $status = $row['campaign_status'];
    $scheduled_at = $row['campaign_scheduled_at'];

    mysqli_query($mysqli,"INSERT INTO campaigns SET campaign_name = '$name (COPY)', campaign_subject = '$subject', campaign_from_name = '$from_name', campaign_from_email = '$from_email', campaign_content = '$content', campaign_status = 'Draft', campaign_scheduled_at = '$scheduled_at', campaign_created_at = NOW(), company_id = $session_company_id");

    $new_campaign_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Campaign', log_action = 'Copy', log_description = '$session_name copied mail campaign $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    $_SESSION['alert_message'] = "Campaign <strong>$campaign_name</strong> copied";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['archive_campaign'])){
    $campaign_id = intval($_GET['archive_campaign']);

    //Get Campaign Name
    $sql = mysqli_query($mysqli,"SELECT * FROM campaigns WHERE campaign_id = $campaign_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $campaign_name = $row['campaign_name'];

    mysqli_query($mysqli,"UPDATE campaigns SET campaign_archived_at = NOW() WHERE campaign_id = $campaign_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Campaign', log_action = 'Archive', log_description = '$session_name archived mail campaign $campaign_name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Campaign <strong>$campaign_name</strong> archived";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['delete_campaign'])){
    $campaign_id = intval($_GET['delete_campaign']);

    //Get Campaign Name
    $sql = mysqli_query($mysqli,"SELECT * FROM campaigns WHERE campaign_id = $campaign_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $campaign_name = $row['campaign_name'];

    //Delete Campaign
    mysqli_query($mysqli,"DELETE FROM campaigns WHERE campaign_id = $campaign_id AND company_id = $session_company_id");
    //Delete Messages Related to the Campaign
    mysqli_query($mysqli,"DELETE FROM campaign_messages WHERE message_campaign_id = $campaign_id AND company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Campaign', log_action = 'Delete', log_description = '$session_name deleted mail campaign $campaign_name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Campaign <strong>$campaign_name</strong> deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['test_campaign'])){
    $campaign_id = intval($_POST['campaign_id']);
    $name_to = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name_to'])));
    $email_to = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email_to'])));

    $sql = mysqli_query($mysqli,"SELECT * FROM campaigns WHERE campaign_id = $campaign_id AND company_id = $session_company_id");

    $row = mysqli_fetch_array($sql);
    
    $campaign_name = $row['campaign_name'];
    $campaign_subject = $row['campaign_subject'];
    $campaign_from_name = $row['campaign_from_name'];
    $campaign_from_email = $row['campaign_from_email'];
    $campaign_content = $row['campaign_content'];

    $mail = new PHPMailer(true);

    //Mail Server Settings

    //$mail->SMTPDebug = 2;                                       // Enable verbose debug output
    $mail->isSMTP();                                            // Set mailer to use SMTP
    $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    $mail->Username   = $config_smtp_username;                     // SMTP username
    $mail->Password   = $config_smtp_password;                               // SMTP password
    $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
    $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

    //Recipients
    $mail->setFrom("$campaign_from_email", "$campaign_from_name");
    $mail->addAddress("$email_to", "$name_to");     // Add a recipient

    // Content
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = "[Test] $campaign_subject";
    $mail->Body    = "Hi $name_to,<br><br>$campaign_content";

    $mail->send();
    echo 'Message has been sent';

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Campaign', log_action = 'Test', log_description = '$session_name sent a test campaign named $campaign_name to $email_to', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Test email to <strong>$email_to</strong> for <strong>$campaign_name</strong> sent successfully";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// Products
if(isset($_POST['add_product'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $price = floatval($_POST['price']);
    $category = intval($_POST['category']);
    $tax = intval($_POST['tax']);

    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_description = '$description', product_price = '$price', product_currency_code = '$session_company_currency', product_created_at = NOW(), product_tax_id = $tax, product_category_id = $category, company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Create', log_description = '$session_name created product $name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Product <strong>$name</strong> created";
    
    header("Location: products.php");

}

if(isset($_POST['edit_product'])){

    $product_id = intval($_POST['product_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $price = floatval($_POST['price']);
    $category = intval($_POST['category']);
    $tax = intval($_POST['tax']);

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_price = '$price', product_updated_at = NOW(), product_tax_id = $tax, product_category_id = $category WHERE product_id = $product_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Modify', log_description = '$session_name modifyed product $name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Product <strong>$name</strong> modified";
    
    header("Location: products.php");

}

if(isset($_GET['delete_product'])){
    $product_id = intval($_GET['delete_product']);

    //Get Product Name
    $sql = mysqli_query($mysqli,"SELECT * FROM products WHERE product_id = $product_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $product_name = $row['product_name'];

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id AND company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Delete', log_description = '$session_name deleted product $name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_trip'])){

    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $source = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['source'])));
    $destination = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['destination'])));
    $miles = floatval($_POST['miles']);
    $roundtrip = intval($_POST['roundtrip']);
    $purpose = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['purpose'])));
    $client_id = intval($_POST['client']);

    mysqli_query($mysqli,"INSERT INTO trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, round_trip = $roundtrip, trip_purpose = '$purpose', trip_created_at = NOW(), trip_client_id = $client_id, company_id = $session_company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Trip', log_action = 'Create', log_description = '$session_name logged trip to $destination', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Trip added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_trip'])){

    $trip_id = intval($_POST['trip_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $source = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['source'])));
    $destination = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['destination'])));
    $miles = floatval($_POST['miles']);
    $roundtrip = intval($_POST['roundtrip']);
    $purpose = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['purpose'])));
    $client_id = intval($_POST['client']);

    mysqli_query($mysqli,"UPDATE trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, trip_purpose = '$purpose', round_trip = $roundtrip, trip_updated_at = NOW(), trip_client_id = $client_id WHERE trip_id = $trip_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Trip', log_action = 'Modified', log_description = '$date', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Trip modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_trip'])){
    $trip_id = intval($_GET['delete_trip']);

    mysqli_query($mysqli,"DELETE FROM trips WHERE trip_id = $trip_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Trip', log_action = 'Deleted', log_description = '$trip_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Trip deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_account'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $opening_balance = floatval($_POST['opening_balance']);
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = '$opening_balance', account_currency_code = '$currency_code', account_notes = '$notes', account_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account added";
    
    header("Location: accounts.php");

}

if(isset($_POST['edit_account'])){

    $account_id = intval($_POST['account_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name', account_notes = '$notes', account_updated_at = NOW() WHERE account_id = $account_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account modified";
    
    header("Location: accounts.php");

}

if(isset($_GET['archive_account'])){
    $account_id = intval($_GET['archive_account']);

    mysqli_query($mysqli,"UPDATE accounts SET account_archived_at = NOW() WHERE account_id = $account_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Archive', log_description = '$account_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Account Archived";
    
    header("Location: accounts.php");

}

if(isset($_GET['delete_account'])){
    $account_id = intval($_GET['delete_account']);

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Deleted', log_description = '$account_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_category'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $color = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['color'])));

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type', category_color = '$color', category_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_category'])){

    $category_id = intval($_POST['category_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $color = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['color'])));

    mysqli_query($mysqli,"UPDATE categories SET category_name = '$name', category_type = '$type', category_color = '$color', category_updated_at = NOW() WHERE category_id = $category_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_category'])){
    $category_id = intval($_GET['archive_category']);

    mysqli_query($mysqli,"UPDATE categories SET category_archived_at = NOW() WHERE category_id = $category_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Archive', log_description = '$category_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Category Archived";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_category'])){
    $category_id = intval($_GET['delete_category']);

    mysqli_query($mysqli,"DELETE FROM categories WHERE category_id = $category_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Deleted', log_description = '$category_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category deleted";
    $_SESSION['alert_type'] = "danger";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}


//Tags

if(isset($_POST['add_tag'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = intval($_POST['type']);
    $color = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['color'])));
    $icon = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['icon'])));

    mysqli_query($mysqli,"INSERT INTO tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon', tag_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tag added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_tag'])){

    $tag_id = intval($_POST['tag_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = intval($_POST['type']);
    $color = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['color'])));
    $icon = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['icon'])));

    mysqli_query($mysqli,"UPDATE tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon', tag_updated_at = NOW() WHERE tag_id = $tag_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tag modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_tag'])){
    $tag_id = intval($_GET['delete_tag']);

    mysqli_query($mysqli,"DELETE FROM tags WHERE tag_id = $tag_id AND company_id = $session_company_id");
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE tag_id = $tag_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Deleted', log_description = '$tag_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tag deleted";
    $_SESSION['alert_type'] = "danger";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

//Tax

if(isset($_POST['add_tax'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"INSERT INTO taxes SET tax_name = '$name', tax_percent = $percent, tax_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Created', log_description = '$name - $percent', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tax added";
    
    header("Location: taxes.php");

}

if(isset($_POST['edit_tax'])){

    $tax_id = intval($_POST['tax_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_name = '$name', tax_percent = $percent, tax_updated_at = NOW() WHERE tax_id = $tax_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Modified', log_description = '$name - $percent', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tax modified";
    
    header("Location: taxes.php");

}

if(isset($_GET['archive_tax'])){
    $tax_id = intval($_GET['archive_tax']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_archived_at = NOW() WHERE tax_id = $tax_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Archive', log_description = '$tax_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Tax Archived";
    
    header("Location: taxes.php");

}

if(isset($_GET['delete_tax'])){
    $tax_id = intval($_GET['delete_tax']);

    mysqli_query($mysqli,"DELETE FROM taxes WHERE tax_id = $tax_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Delete', log_description = '$tax_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tax deleted";
    $_SESSION['alert_type'] = "danger";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

//End Tax

//Custom Link
if(isset($_POST['add_custom_link'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $icon = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['icon'])));
    $url = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['url'])));

    mysqli_query($mysqli,"INSERT INTO custom_links SET custom_link_name = '$name', custom_link_icon = '$icon', custom_link_url = '$url', custom_link_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Link', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Custom link added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_custom_link'])){

    $custom_link_id = intval($_POST['custom_link_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $icon = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['icon'])));
    $url = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['url'])));

    mysqli_query($mysqli,"UPDATE custom_links SET custom_link_name = '$name', custom_link_icon = '$icon', custom_link_url = '$url' WHERE custom_link_id = $custom_link_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Link', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Custom link modified";
    
    header("Location: custom_links.php");

}

if(isset($_GET['delete_custom_link'])){
    $custom_link_id = intval($_GET['delete_custom_link']);

    mysqli_query($mysqli,"DELETE FROM custom_links WHERE custom_link_id = $custom_link_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Link', log_action = 'Deleted', log_description = '$custom_link_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Custom link deleted";
    $_SESSION['alert_type'] = "danger";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}
//End Custom Link

if(isset($_GET['alert_ack'])){

    $alert_id = intval($_GET['alert_ack']);

    mysqli_query($mysqli,"UPDATE alerts SET alert_ack_date = CURDATE() WHERE alert_id = $alert_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Alerts', log_action = 'Modify', log_description = '$alert_id Acknowledged', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Alert Acknowledged";
    
    header("Location: alerts.php");

}

if(isset($_GET['ack_all_alerts'])){

    $sql = mysqli_query($mysqli,"SELECT * FROM alerts WHERE company_id = $session_company_id AND alert_ack_date IS NULL");

    $num_alerts = mysqli_num_rows($sql);
    
    while($row = mysqli_fetch_array($sql)){
        $alert_id = $row['alert_id'];
        $alert_ack_date = $row['alert_ack_date'];

        mysqli_query($mysqli,"UPDATE alerts SET alert_ack_date = CURDATE() WHERE alert_id = $alert_id");
    
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Alerts', log_action = 'Modifed', log_description = 'Acknowledged all alerts', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");
    
    $_SESSION['alert_message'] = "$num_alerts Alerts Acknowledged";
    
    header("Location: alerts.php");

}

if(isset($_POST['add_expense'])){

    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $category = intval($_POST['category']);
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $reference = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference'])));

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', expense_currency_code = '$session_company_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference', expense_created_at = NOW(), company_id = $session_company_id");

    $expense_id = mysqli_insert_id($mysqli);

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
        $allowed_file_extensions = array('jpg', 'gif', 'png', 'pdf');
     
        if(in_array($file_extension,$allowed_file_extensions) === false){
            $file_error = 1;
        }

        //Check File Size
        if($file_size > 9097152){
            $file_error = 1;
        }

        if($file_error == 0){
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/$session_company_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);
            
            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Created', log_description = '$description', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Expense added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_expense'])){

    $expense_id = intval($_POST['expense_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $category = intval($_POST['category']);
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $reference = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference'])));
    $existing_file_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['existing_file_name'])));

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
        $allowed_file_extensions = array('jpg', 'gif', 'png', 'pdf');
     
        if(in_array($file_extension,$allowed_file_extensions) === false){
            $file_error = 1;
        }

        //Check File Size
        if($file_size > 9097152){
            $file_error = 1;
        }

        if($file_error == 0){
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/$session_company_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/expenses/$session_company_id/$existing_file_name");
            
            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', expense_account_id = $account, expense_vendor_id = $vendor, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference', expense_updated_at = NOW() WHERE expense_id = $expense_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Expense modified";

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Modified', log_description = '$description', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_expense'])){
    $expense_id = intval($_GET['delete_expense']);

    $sql = mysqli_query($mysqli,"SELECT * FROM expenses WHERE expense_id = $expense_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $expense_receipt = $row['expense_receipt'];

    unlink("uploads/expenses/$session_company_id/$expense_receipt");

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Deleted', log_description = '$epense_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Expense deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['export_expenses_csv'])){
    $date_from = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_from'])));
    $date_to = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_to'])));
    if(!empty($date_from) AND !empty($date_to)){
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Export', log_description = '$session_name exported expenses to CSV File', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id, company_id = $session_company_id");

    exit;
}

if(isset($_POST['add_transfer'])){

    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $account_from = intval($_POST['account_from']);
    $account_to = intval($_POST['account_to']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', expense_currency_code = '$session_company_currency', expense_vendor_id = 0, expense_category_id = 0, expense_account_id = $account_from, expense_created_at = NOW(), company_id = $session_company_id");
    $expense_id = mysqli_insert_id($mysqli);
    
    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = '$amount', revenue_currency_code = '$session_company_currency', revenue_account_id = $account_to, revenue_category_id = 0, revenue_created_at = NOW(), company_id = $session_company_id");
    $revenue_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO transfers SET transfer_expense_id = $expense_id, transfer_revenue_id = $revenue_id, transfer_notes = '$notes', transfer_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Created', log_description = '$date - $amount', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Transfer added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_transfer'])){

    $transfer_id = intval($_POST['transfer_id']);
    $expense_id = intval($_POST['expense_id']);
    $revenue_id = intval($_POST['revenue_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $account_from = intval($_POST['account_from']);
    $account_to = intval($_POST['account_to']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', expense_account_id = $account_from, expense_updated_at = NOW() WHERE expense_id = $expense_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = '$amount', revenue_account_id = $account_to, revenue_updated_at = NOW() WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"UPDATE transfers SET transfer_notes = '$notes', transfer_updated_at = NOW() WHERE transfer_id = $transfer_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Modifed', log_description = '$date - $amount', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Transfer modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_transfer'])){
    $transfer_id = intval($_GET['delete_transfer']);

    //Query the transfer ID to get the Pyament and Expense IDs so we can delete those as well
    $sql = mysqli_query($mysqli,"SELECT * FROM transfers WHERE transfer_id = $transfer_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $expense_id = $row['transfer_expense_id'];
    $revenue_id = $row['transfer_revenue_id'];

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM transfers WHERE transfer_id = $transfer_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Deleted', log_description = '$transfer_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Transfer deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_invoice'])){
    $client = intval($_POST['client']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $category = intval($_POST['category']);
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));
    
    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients WHERE client_id = $client AND company_id = $session_company_id"); 
    $row = mysqli_fetch_array($sql);
    $client_net_terms = $row['client_net_terms'];
    
    //Get the last Invoice Number and add 1 for the new invoice number
    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    //Generate a unique URL key for clients to access
    $url_key = keygen();

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_created_at = NOW(), invoice_client_id = $client, company_id = $session_company_id");
    $invoice_id = mysqli_insert_id($mysqli);
    
    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'INVOICE added!', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");
    
    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Created', log_description = '$config_invoice_prefix$invoice_number', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice added";
    
    header("Location: invoice.php?invoice_id=$invoice_id");
}

if(isset($_POST['edit_invoice'])){

    $invoice_id = intval($_POST['invoice_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $due = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['due'])));
    $category = intval($_POST['category']);
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));

    mysqli_query($mysqli,"UPDATE invoices SET invoice_scope = '$scope', invoice_date = '$date', invoice_due = '$due', invoice_updated_at = NOW(), invoice_category_id = $category WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Modified', log_description = '$invoice_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_invoice_copy'])){

    $invoice_id = intval($_POST['invoice_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients, invoices WHERE client_id = invoice_client_id AND invoice_id = $invoice_id AND invoices.company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $client_net_terms = $row['client_net_terms'];

    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_scope = $row['invoice_scope'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_currency_code = $row['invoice_currency_code'];
    $invoice_note = mysqli_real_escape_string($mysqli,$row['invoice_note']);
    $client_id = $row['invoice_client_id'];
    $category_id = $row['invoice_category_id'];

    //Generate a unique URL key for clients to access
    $url_key = keygen();

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$invoice_scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_amount = '$invoice_amount', invoice_currency_code = '$invoice_currency_code', invoice_note = '$invoice_note', invoice_url_key = '$url_key', invoice_created_at = NOW(), invoice_client_id = $client_id, company_id = $session_company_id") or die(mysql_error());

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Copied INVOICE!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = mysqli_real_escape_string($mysqli,$row['item_name']);
        $item_description = mysqli_real_escape_string($mysqli,$row['item_description']);
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $tax_id = $row['item_tax_id'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', item_created_at = NOW(), item_tax_id = $tax_id, item_invoice_id = $new_invoice_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Created', log_description = 'Copied Invoice', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice copied";
    
    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if(isset($_POST['add_invoice_recurring'])){

    $invoice_id = intval($_POST['invoice_id']);
    $recurring_frequency = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency'])));

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_date = $row['invoice_date'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_currency_code = $row['invoice_currency_code'];
    $invoice_scope = mysqli_real_escape_string($mysqli,$row['invoice_scope']);
    $invoice_note = mysqli_real_escape_string($mysqli,$row['invoice_note']); //SQL Escape in case notes have , them
    $client_id = $row['invoice_client_id'];
    $category_id = $row['invoice_category_id'];

    //Get the last Recurring Number and add 1 for the new Recurring number
    $recurring_number = $config_recurring_next_number;
    $new_config_recurring_next_number = $config_recurring_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_next_number = $new_config_recurring_next_number WHERE company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_prefix = '$config_recurring_prefix', recurring_number = $recurring_number, recurring_scope = '$invoice_scope', recurring_frequency = '$recurring_frequency', recurring_next_date = DATE_ADD('$invoice_date', INTERVAL 1 $recurring_frequency), recurring_status = 1, recurring_amount = '$invoice_amount', recurring_currency_code = '$invoice_currency_code', recurring_note = '$invoice_note', recurring_created_at = NOW(), recurring_category_id = $category_id, recurring_client_id = $client_id, company_id = $session_company_id");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Recurring Created from INVOICE!', history_created_at = NOW(), history_recurring_id = $recurring_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = mysqli_real_escape_string($mysqli,$row['item_name']);
        $item_description = mysqli_real_escape_string($mysqli,$row['item_description']);
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $tax_id = $row['item_tax_id'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', item_created_at = NOW(), item_tax_id = $tax_id, item_recurring_id = $recurring_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Created', log_description = 'From recurring invoice', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Created recurring Invoice from this Invoice";
    
    header("Location: recurring.php?recurring_id=$recurring_id");

}

if(isset($_POST['add_quote'])){

    $client = intval($_POST['client']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $category = intval($_POST['category']);
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));
    
    //Get the last Quote Number and add 1 for the new Quote number
    $quote_number = $config_quote_next_number;
    $new_config_quote_next_number = $config_quote_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_quote_next_number = $new_config_quote_next_number WHERE company_id = $session_company_id");

    //Generate a unique URL key for clients to access
    $quote_url_key = keygen();

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_currency_code = '$session_company_currency', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_created_at = NOW(), quote_client_id = $client, company_id = $session_company_id");

    $quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Quote created!', history_created_at = NOW(), history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Created', log_description = '$quote_prefix$quote_number', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote added";
    
    header("Location: quote.php?quote_id=$quote_id");

}

if(isset($_POST['add_quote_copy'])){

    $quote_id = intval($_POST['quote_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    
    //Get the last Invoice Number and add 1 for the new invoice number
    $quote_number = $config_quote_next_number;
    $new_config_quote_next_number = $config_quote_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_quote_next_number = $new_config_quote_next_number WHERE company_id = $session_company_id");

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = $row['quote_amount'];
    $quote_currency_code = $row['quote_currency_code'];
    $quote_scope = mysqli_real_escape_string($mysqli,$row['quote_scope']);
    $quote_note = mysqli_real_escape_string($mysqli,$row['quote_note']);
    $client_id = $row['quote_client_id'];
    $category_id = $row['quote_category_id'];

    //Generate a unique URL key for clients to access
    $quote_url_key = keygen();

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$quote_scope', quote_date = '$date', quote_category_id = $category_id, quote_status = 'Draft', quote_amount = '$quote_amount', quote_currency_code = '$quote_currency_code', quote_note = '$quote_note', quote_url_key = '$quote_url_key', quote_created_at = NOW(), quote_client_id = $client_id, company_id = $session_company_id");

    $new_quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Quote copied!', history_created_at = NOW(), history_quote_id = $new_quote_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = mysqli_real_escape_string($mysqli,$row['item_name']);
        $item_description = mysqli_real_escape_string($mysqli,$row['item_description']);
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $tax_id = $row['item_tax_id'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', item_created_at = NOW(), item_tax_id = $tax_id, item_quote_id = $new_quote_id, company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Created', log_description = 'Copied Quote', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote copied";
    
    header("Location: quote.php?quote_id=$new_quote_id");

}

if(isset($_POST['add_quote_to_invoice'])){

    $quote_id = intval($_POST['quote_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $client_net_terms = intval($_POST['client_net_terms']);
    
    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = $row['quote_amount'];
    $quote_currency_code = $row['quote_currency_code'];
    $quote_scope = mysqli_real_escape_string($mysqli,$row['quote_scope']);
    $quote_note = mysqli_real_escape_string($mysqli,$row['quote_note']);
    
    $client_id = $row['quote_client_id'];
    $category_id = $row['quote_category_id'];

    //Generate a unique URL key for clients to access
    $url_key = keygen();

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$quote_scope', invoice_date = '$date', invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_amount = '$quote_amount', invoice_currency_code = '$quote_currency_code', invoice_note = '$quote_note', invoice_url_key = '$url_key', invoice_created_at = NOW(), invoice_client_id = $client_id, company_id = $session_company_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Quote copied to Invoice!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $session_company_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = mysqli_real_escape_string($mysqli,$row['item_name']);
        $item_description = mysqli_real_escape_string($mysqli,$row['item_description']);
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $tax_id = $row['item_tax_id'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', item_created_at = NOW(), item_tax_id = $tax_id, item_invoice_id = $new_invoice_id, company_id = $session_company_id");
    }

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Invoiced' WHERE quote_id = $quote_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Created', log_description = 'Quote copied to Invoice', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote copied to Invoice";
    
    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if(isset($_POST['add_quote_item'])){

    $quote_id = intval($_POST['quote_id']);
    
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    
    $subtotal = $price * $qty;
    
    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = $row['tax_percent'];
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }
    
    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = '$price', item_subtotal = '$subtotal', item_tax = '$tax_amount', item_total = '$total', item_created_at = NOW(), item_tax_id = $tax_id, item_quote_id = $quote_id, company_id = $session_company_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_quote_amount = $row['quote_amount'] + $total;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = '$new_quote_amount', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Item added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['quote_note'])){
    
    $quote_id = intval($_POST['quote_id']);
    $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));

    mysqli_query($mysqli,"UPDATE quotes SET quote_note = '$note', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "<i class='fa fa-2x fa-check-circle'></i> <strong>Notes added</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_quote'])){

    $quote_id = intval($_POST['quote_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $category = intval($_POST['category']);
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));

     mysqli_query($mysqli,"UPDATE quotes SET quote_scope = '$scope', quote_date = '$date', quote_category_id = $category, quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

     //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modified', log_description = '$quote_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_quote'])){
    $quote_id = intval($_GET['delete_quote']);

    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");

    //Delete Items Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = $row['item_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    }

    //Delete History Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_quote_id = $quote_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = $row['history_id'];
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id AND company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Deleted', log_description = '$quote_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quotes deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['delete_quote_item'])){
    $item_id = intval($_GET['delete_quote_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $quote_id = $row['item_quote_id'];
    $item_subtotal = $row['item_subtotal'];
    $item_tax = $row['item_tax'];
    $item_total = $row['item_total'];

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    
    $new_quote_amount = $row['quote_amount'] - $item_total;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = '$new_quote_amount', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote Item', log_action = 'Deleted', log_description = '$item_id from $quote_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Item deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['mark_quote_sent'])){

    $quote_id = intval($_GET['mark_quote_sent']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'QUOTE marked sent', history_created_at = NOW(), history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Updated', log_description = '$quote_id marked sent', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "<i class='fa fa-2x fa-check-circle'></i> Quote marked sent";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['accept_quote'])){

    $quote_id = intval($_GET['accept_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Accepted', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Accepted', history_description = 'Quote accepted!', history_created_at = NOW(), history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modified', log_description = 'Accepted Quote $quote_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "<i class='fa fa-2x fa-check-circle'></i> Quote accepted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['decline_quote'])){

    $quote_id = intval($_GET['decline_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Declined', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Cancelled', history_description = 'Quote declined!', history_created_at = NOW(), history_quote_id = $quote_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modified', log_description = 'Declined Quote $quote_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

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
    $quote_id = $row['quote_id'];
    $quote_prefix = $row['quote_prefix'];
    $quote_number = $row['quote_number'];
    $quote_status = $row['quote_status'];
    $quote_date = $row['quote_date'];
    $quote_amount = $row['quote_amount'];
    $quote_note = $row['quote_note'];
    $quote_url_key = $row['quote_url_key'];
    $quote_currency_code = $row['quote_currency_code'];
    $quote_currency_symbol = htmlentities(get_currency_symbol($quote_currency_code)); //Needs HTML entities due to encoding (Â was showing up)
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $contact_name = $row['contact_name'];
    $contact_email = $row['contact_email'];
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = $row['contact_extension'];
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
    $client_website = $row['client_website'];
    $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
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

    $mail = new PHPMailer(true);

    try{

        //Mail Server Settings

        //$mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                     // SMTP username
        $mail->Password   = $config_smtp_password;                               // SMTP password
        $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

        //Recipients
        $mail->setFrom($config_mail_from_email, $config_mail_from_name);
        $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

        // Attachments
        //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
        //$mail->addAttachment("uploads/$quote_date-$config_company_name-Quote$quote_number.pdf");    // Optional name

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = "Quote";
        $mail->Body    = "Hello $contact_name,<br><br>Thank you for your inquiry, we are pleased to provide you with the following estimate.<br><br><br>Total Cost: $quote_currency_symbol$quote_amount<br><br><br>View and accept your estimate online <a href='https://$base_url/guest_view_quote.php?quote_id=$quote_id&url_key=$quote_url_key'>here</a><br><br><br>~<br>$company_name<br>$company_phone";
        
        $mail->send();
        echo 'Message has been sent';

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed Quote!', history_created_at = NOW(), history_quote_id = $quote_id, company_id = $session_company_id");

        //Don't change the status to sent if the status is anything but draft
        if($quote_status == 'Draft'){

            mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Emailed', log_description = '$quote_id emailed to $contact_email', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Quote has been sent";

        header("Location: quotes.php");


    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

if(isset($_POST['add_recurring'])){

    $client = intval($_POST['client']);
    $frequency = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency'])));
    $start_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['start_date'])));
    $category = intval($_POST['category']);
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));

    //Get the last Recurring Number and add 1 for the new Recurring number
    $recurring_number = $config_recurring_next_number;
    $new_config_recurring_next_number = $config_recurring_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_next_number = $new_config_recurring_next_number WHERE company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_prefix = '$config_recurring_prefix', recurring_number = $recurring_number, recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_next_date = '$start_date', recurring_category_id = $category, recurring_status = 1, recurring_currency_code = '$session_company_currency', recurring_created_at = NOW(), recurring_client_id = $client, company_id = $session_company_id");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Active', history_description = 'Recurring Invoice created!', history_created_at = NOW(), history_recurring_id = $recurring_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Created', log_description = '$start_date - $category', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Invoice added";
    
    header("Location: recurring_invoice.php?recurring_id=$recurring_id");

}

if(isset($_POST['edit_recurring'])){

    $recurring_id = intval($_POST['recurring_id']);
    $frequency = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency'])));
    $category = intval($_POST['category']);
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));
    $status = intval($_POST['status']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_category_id = $category, recurring_status = $status, recurring_updated_at = NOW() WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$status', history_description = 'Recurring modified', history_created_at = NOW(), history_recurring_id = $recurring_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Modified', log_description = '$recurring_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Invoice modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_recurring'])){
    $recurring_id = intval($_GET['delete_recurring']);

    mysqli_query($mysqli,"DELETE FROM recurring WHERE recurring_id = $recurring_id AND company_id = $session_company_id");
    
    //Delete Items Associated with the Recurring
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = $row['item_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_recurring_id = $recurring_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = $row['history_id'];
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id AND company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Deleted', log_description = '$recurring_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Invoice deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_recurring_item'])){

    $recurring_id = intval($_POST['recurring_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    
    $subtotal = $price * $qty;
    
    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = $row['tax_percent'];
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }
    
    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = '$price', item_subtotal = '$subtotal', item_tax = '$tax_amount', item_total = '$total', item_created_at = NOW(), item_tax_id = $tax_id, item_recurring_id = $recurring_id, company_id = $session_company_id");

    //Update Recurring Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_recurring_amount = $row['recurring_amount'] + $total;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = '$new_recurring_amount', recurring_updated_at = NOW() WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Recurring Invoice Updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['recurring_note'])){
    
    $recurring_id = intval($_POST['recurring_id']);
    $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));

    mysqli_query($mysqli,"UPDATE recurring SET recurring_note = '$note', recurring_updated_at = NOW() WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "<i class='fa fa-2x fa-check-circle'></i> <strong>Notes added</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_recurring_item'])){
    $item_id = intval($_GET['delete_recurring_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $recurring_id = $row['item_recurring_id'];
    $item_subtotal = $row['item_subtotal'];
    $item_tax = $row['item_tax'];
    $item_total = $row['item_total'];

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    
    $new_recurring_amount = $row['recurring_amount'] - $item_total;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = '$new_recurring_amount', recurring_updated_at = NOW() WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring Item', log_action = 'Deleted', log_description = 'Item ID $item_id from Recurring ID $recurring_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Item deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['mark_invoice_sent'])){

    $invoice_id = intval($_GET['mark_invoice_sent']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'INVOICE marked sent', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Updated', log_description = '$invoice_id marked sent', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice marked sent";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['cancel_invoice'])){

    $invoice_id = intval($_GET['cancel_invoice']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Cancelled', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Cancelled', history_description = 'INVOICE cancelled!', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Modified', log_description = 'Cancelled', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice cancelled";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice'])){
    $invoice_id = intval($_GET['delete_invoice']);

    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    //Delete Items Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = $row['item_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = $row['history_id'];
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id AND company_id = $session_company_id");
    }

    //Delete Payments Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_invoice_id = $invoice_id AND company_id = $session_company_id");
    while($row = mysqli_fetch_array($sql)){;
        $payment_id = $row['payment_id'];
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id AND company_id = $session_company_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Deleted', log_description = '$invoice_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_invoice_item'])){

    $invoice_id = intval($_POST['invoice_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    
    $subtotal = $price * $qty;
    
    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = $row['tax_percent'];
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }
    
    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = '$price', item_subtotal = '$subtotal', item_tax = '$tax_amount', item_total = '$total', item_created_at = NOW(), item_tax_id = $tax_id, item_invoice_id = $invoice_id, company_id = $session_company_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $new_invoice_amount = $row['invoice_amount'] + $total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = '$new_invoice_amount', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Item added";


    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['invoice_note'])){

    $invoice_id = intval($_POST['invoice_id']);
    $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));

    mysqli_query($mysqli,"UPDATE invoices SET invoice_note = '$note', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    $_SESSION['alert_message'] = "Notes added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_item'])){

    $invoice_id = intval($_POST['invoice_id']);
    $quote_id = intval($_POST['quote_id']);
    $recurring_id = intval($_POST['recurring_id']);
    $item_id = intval($_POST['item_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    
    $subtotal = $price * $qty;
    
    if($tax_id > 0){
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = $row['tax_percent'];
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }
    
    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"UPDATE invoice_items SET item_name = '$name', item_description = '$description', item_quantity = '$qty', item_price = '$price', item_subtotal = '$subtotal', item_tax = '$tax_amount', item_total = '$total', item_tax_id = $tax_id WHERE item_id = $item_id");

    if($invoice_id > 0){
        //Update Invoice Balances by tallying up invoice items
        $sql_invoice_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS invoice_total FROM invoice_items WHERE item_invoice_id = $invoice_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_invoice_total);
        $new_invoice_amount = $row['invoice_total'];

        mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = '$new_invoice_amount', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    
    }elseif($quote_id > 0){
        //Update Quote Balances by tallying up items
        $sql_quote_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS quote_total FROM invoice_items WHERE item_quote_id = $quote_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_quote_total);
        $new_quote_amount = $row['quote_total'];

        mysqli_query($mysqli,"UPDATE quotes SET quote_amount = '$new_quote_amount', quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

    }else{
        //Update Invoice Balances by tallying up invoice items

        $sql_recurring_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_total FROM invoice_items WHERE item_recurring_id = $recurring_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_recurring_total);
        $new_recurring_amount = $row['recurring_total'];

        mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = '$new_recurring_amount', recurring_updated_at = NOW() WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    }

    $_SESSION['alert_message'] = "Item updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice_item'])){
    $item_id = intval($_GET['delete_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['item_invoice_id'];
    $item_subtotal = $row['item_subtotal'];
    $item_tax = $row['item_tax'];
    $item_total = $row['item_total'];

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    
    $new_invoice_amount = $row['invoice_amount'] - $item_total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = '$new_invoice_amount', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice Item', log_action = 'Deleted', log_description = '$item_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Item deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_payment'])){

    $invoice_id = intval($_POST['invoice_id']);
    $balance = floatval($_POST['balance']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $currency_symbol = htmlentities(get_currency_symbol($currency_code)); //Needs HTML entities due to encoding (Â was showing up)
    $payment_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['payment_method'])));
    $reference = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference'])));
    $email_receipt = intval($_POST['email_receipt']);
    $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

    //Check to see if amount entered is greater than the balance of the invoice
    if($amount > $balance){
        $_SESSION['alert_message'] = "Payment is more than the balance";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }else{
        mysqli_query($mysqli,"INSERT INTO payments SET payment_date = '$date', payment_amount = '$amount', payment_currency_code = '$currency_code', payment_account_id = $account, payment_method = '$payment_method', payment_reference = '$reference', payment_created_at = NOW(), payment_invoice_id = $invoice_id, company_id = $session_company_id");

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payments_amount FROM payments WHERE payment_invoice_id = $invoice_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql_total_payments_amount);
        $total_payments_amount = $row['payments_amount'];
        
        //Get the invoice total
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON contact_id = primary_contact
            LEFT JOIN companies ON invoices.company_id = companies.company_id
            WHERE invoice_id = $invoice_id
            AND invoices.company_id = $session_company_id"
        );

        $row = mysqli_fetch_array($sql);
        $invoice_amount = $row['invoice_amount'];
        $invoice_prefix = $row['invoice_prefix'];
        $invoice_number = $row['invoice_number'];
        $invoice_url_key = $row['invoice_url_key'];
        $client_name = $row['client_name'];
        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];
        $contact_phone = $row['contact_phone'];
        if(strlen($contact_phone)>2){ 
            $contact_phone = substr($row['contact_phone'],0,3)."-".substr($row['contact_phone'],3,3)."-".substr($row['contact_phone'],6,4);
        }
        $contact_extension = $row['contact_extension'];
        $contact_mobile = $row['contact_mobile'];
        if(strlen($contact_mobile)>2){ 
            $contact_mobile = substr($row['contact_mobile'],0,3)."-".substr($row['contact_mobile'],3,3)."-".substr($row['contact_mobile'],6,4);
        }
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

        //Calculate the Invoice balance
        $invoice_balance = $invoice_amount - $total_payments_amount;

        //Format Amount
        $formatted_amount = number_format($amount,2);
        $formatted_invoice_balance = number_format($invoice_balance,2);  
        
        //Determine if invoice has been paid then set the status accordingly
        if($invoice_balance == 0){
            $invoice_status = "Paid";        
            if($email_receipt == 1){
                $mail = new PHPMailer(true);

                try {

                  //Mail Server Settings

                  //$mail->SMTPDebug = 2;                                       // Enable verbose debug output
                  $mail->isSMTP();                                            // Set mailer to use SMTP
                  $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
                  $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
                  $mail->Username   = $config_smtp_username;                     // SMTP username
                  $mail->Password   = $config_smtp_password;                               // SMTP password
                  $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
                  $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

                  //Recipients
                  $mail->setFrom($config_mail_from_email, $config_mail_from_name);
                  $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

                  // Content
                  $mail->isHTML(true);                                  // Set email format to HTML
                  $mail->Subject = "Payment Recieved - Invoice $invoice_prefix$invoice_number";
                  $mail->Body    = "Hello $contact_name,<br><br>We have recieved your payment in the amount of $currency_symbol$formatted_amount for invoice <a href='https://$base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: $currency_symbol$formatted_amount<br>Balance: $currency_symbol$formatted_invoice_balance<br><br>Thank you for your business!<br><br><br>~<br>$company_name<br>$company_phone";

                  $mail->send();
                  echo 'Message has been sent';

                  mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed Receipt!', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");

                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            }
        }else{
            $invoice_status = "Partial";
            if($email_receipt == 1){
                $mail = new PHPMailer(true);

                try {

                  //Mail Server Settings

                  //$mail->SMTPDebug = 2;                                       // Enable verbose debug output
                  $mail->isSMTP();                                            // Set mailer to use SMTP
                  $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
                  $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
                  $mail->Username   = $config_smtp_username;                     // SMTP username
                  $mail->Password   = $config_smtp_password;                               // SMTP password
                  $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
                  $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

                  //Recipients
                  $mail->setFrom($config_mail_from_email, $config_mail_from_name);
                  $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

                  // Content
                  $mail->isHTML(true);                                  // Set email format to HTML
                  $mail->Subject = "Partial Payment Recieved - Invoice $invoice_prefix$invoice_number";
                  $mail->Body    = "Hello $contact_name,<br><br>We have recieved partial payment in the amount of $currency_symbol$formatted_amount and it has been applied to invoice <a href='https://$base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: $currency_symbol$formatted_amount<br>Balance: $currency_symbol$formatted_invoice_balance<br><br>Thank you for your business!<br><br><br>~<br>$company_name<br>$company_phone";

                  $mail->send();
                  echo 'Message has been sent';

                  mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed Receipt!', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");

                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            }

        }

        //Update Invoice Status
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

        //Add Payment to History
        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$invoice_status', history_description = 'Payment added', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Payment', log_action = 'Created', log_description = '$payment_amount', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Payment added";
        
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['delete_payment'])){
    $payment_id = intval($_GET['delete_payment']);

    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_id = $payment_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['payment_invoice_id'];
    $deleted_payment_amount = $row['payment_amount'];

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments_amount FROM payments WHERE payment_invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql_total_payments_amount);
    $total_payments_amount = $row['total_payments_amount'];
    
    //Get the invoice total
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);
    $invoice_amount = $row['invoice_amount'];

    //Calculate the Invoice balance
    $invoice_balance = $invoice_amount - $total_payments_amount + $deleted_payment_amount;

    //Determine if invoice has been paid
    if($invoice_balance == 0){
        $invoice_status = "Paid";
    }else{
        $invoice_status = "Partial";
    }

    //Update Invoice Status
    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

    //Add Payment to History
    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$invoice_status', history_description = 'Payment deleted', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Payment', log_action = 'Deleted', log_description = '$payment_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

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
    $invoice_id = $row['invoice_id'];
    $invoice_prefix = $row['invoice_prefix'];
    $invoice_number = $row['invoice_number'];
    $invoice_status = $row['invoice_status'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_url_key = $row['invoice_url_key'];
    $invoice_currency_code = $row['invoice_currency_code'];
    $invoice_currency_symbol = htmlentities(get_currency_symbol($invoice_currency_code)); //Needs HTML entities due to encoding (Â was showing up)
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_name = $row['client_name'];
    $contact_name = $row['contact_name'];
    $contact_email = $row['contact_email'];
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = $row['contact_extension'];
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
    $client_website = $row['client_website'];
    
    $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
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

    $mail = new PHPMailer(true);

    try{

        //Mail Server Settings

        //$mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                     // SMTP username
        $mail->Password   = $config_smtp_password;                               // SMTP password
        $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

        //Recipients
        $mail->setFrom($config_mail_from_email, $config_mail_from_name);
        $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        
        if($invoice_status == 'Paid'){

            $mail->Subject = "Invoice $invoice_prefix$invoice_number Copy";
            $mail->Body    = "Hello $contact_name,<br><br>Please click on the link below to see your invoice marked <b>paid</b>.<br><br><a href='https://$base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>Invoice Link</a><br><br><br>~<br>$company_name<br>Automated Billing Department<br>$company_phone";

        }else{

            $mail->Subject = "Invoice $invoice_prefix$invoice_number";
            $mail->Body    = "Hello $contact_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: $invoice_currency_symbol$invoice_amount<br>Balance Due: $invoice_currency_symbol$balance<br>Due Date: $invoice_due<br><br><br>To view your invoice online click <a href='https://$base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>$company_phone";
            //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
        }
        
        $mail->send();
        echo 'Message has been sent';

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed invoice', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $session_company_id");

        //Don't chnage the status to sent if the status is anything but draf
        if($invoice_status == 'Draft'){

            mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', invoice_updated_at = NOW() WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Emailed', log_description = 'Invoice $invoice_prefix$invoice_number emailed to $client_email', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Invoice has been sent";

        header("Location: invoices.php");


    } catch (Exception $e) {
        echo "poop";
    }
}

if(isset($_POST['add_revenue'])){

    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['payment_method'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $reference = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference'])));

    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = '$amount', revenue_currency_code = '$currency_code', revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_created_at = NOW(), revenue_category_id = $category, revenue_account_id = $account, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Created', log_description = '$date - $amount', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Revenue added!";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_revenue'])){

    $revenue_id = intval($_POST['revenue_id']);
    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['payment_method'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $reference = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference'])));

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = '$amount', revenue_currency_code = '$currency_code', revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_updated_at = NOW(), revenue_category_id = $category, revenue_account_id = $account WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Modified', log_description = '$revenue_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Revenue modified!";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    
}

if(isset($_GET['delete_revenue'])){
    $revenue_id = intval($_GET['delete_revenue']);

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Deleted', log_description = '$revenue_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Revenue deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_contact'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '',$_POST['extension']);
    $mobile = preg_replace("/[^0-9]/", '',$_POST['mobile']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $primary_contact = intval($_POST['primary_contact']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    $location_id = intval($_POST['location']);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_notes = '$notes', contact_created_at = NOW(), contact_location_id = $location_id, contact_client_id = $client_id, company_id = $session_company_id");

    $contact_id = mysqli_insert_id($mysqli);

    //Update Primay contact in clients if primary contact is checked
    if($primary_contact > 0){   
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
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
            
            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Create', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] .= "Contact added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_contact'])){

    $contact_id = intval($_POST['contact_id']);
    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '',$_POST['extension']);
    $mobile = preg_replace("/[^0-9]/", '',$_POST['mobile']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $primary_contact = intval($_POST['primary_contact']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    $location_id = intval($_POST['location']);

    $existing_file_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['existing_file_name']));

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_notes = '$notes', contact_location_id = $location_id, contact_updated_at = NOW() WHERE contact_id = $contact_id AND company_id = $session_company_id");

    //Update Primay contact in clients if primary contact is checked
    if($primary_contact > 0){
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
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
            
            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] .= "Contact updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_contact'])){
    $contact_id = intval($_GET['archive_contact']);

    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NOW() WHERE contact_id = $contact_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Archive', log_description = '$contact_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Contact Archived!";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_contact'])){
    $contact_id = intval($_GET['delete_contact']);

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Delete', log_description = '$contact_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['export_client_contacts_csv'])){
    $client_id = intval($_GET['export_client_contacts_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    //Contacts
    $sql = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Contacts-" . date('Y-m-d') . ".csv";
        
        //create a file pointer
        $f = fopen('php://memory', 'w');
        
        //set column headers
        $fields = array('Name', 'Title', 'Email', 'Phone', 'Mobile', 'Notes');
        fputcsv($f, $fields, $delimiter);
        
        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['contact_name'], $row['contact_title'], $row['contact_email'], $row['contact_phone'], $row['contact_mobile'], $row['contact_notes']);
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

if(isset($_POST['add_location'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $hours = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['hours'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    $contact = intval($_POST['contact']);
    $primary_location = intval($_POST['primary_location']);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact, location_created_at = NOW(), location_client_id = $client_id, company_id = $session_company_id");

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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] .= "Location added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_location'])){

    $location_id = intval($_POST['location_id']);
    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $hours = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['hours'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    $contact = intval($_POST['contact']);
    $primary_location = intval($_POST['primary_location']);

    $existing_file_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['existing_file_name']));

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    mysqli_query($mysqli,"UPDATE locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact, location_updated_at = NOW() WHERE location_id = $location_id AND company_id = $session_company_id");

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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] .= "Location updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_location'])){
    $location_id = intval($_GET['delete_location']);

    mysqli_query($mysqli,"DELETE FROM locations WHERE location_id = $location_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'location', log_action = 'Deleted', log_description = '$location_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Location deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['export_client_locations_csv'])){
    $client_id = intval($_GET['export_client_locations_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    //Locations
    $sql = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_client_id = $client_id ORDER BY location_name ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Locations-" . date('Y-m-d') . ".csv";
        
        //create a file pointer
        $f = fopen('php://memory', 'w');
        
        //set column headers
        $fields = array('Name', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Notes');
        fputcsv($f, $fields, $delimiter);
        
        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['location_name'], $row['location_address'], $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_phone'], $row['location_notes']);
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

if(isset($_POST['add_asset'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $make = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['make'])));
    $model = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['model'])));
    $serial = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['serial'])));
    $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['os'])));
    $ip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['ip'])));
    $mac = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['mac'])));
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $network = intval($_POST['network']);
    $purchase_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['purchase_date'])));
    if(empty($purchase_date)){
        $purchase_date = "0000-00-00";
    }
    $warranty_expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['warranty_expire'])));
    if(empty($warranty_expire)){
        $warranty_expire = "0000-00-00";
    }
    $install_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['install_date'])));
    if(empty($install_date)){
        $install_date = "0000-00-00";
    }
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_install_date = '$install_date', asset_notes = '$notes', asset_created_at = NOW(), asset_network_id = $network, asset_client_id = $client_id, company_id = $session_company_id");

    if(!empty($_POST['username'])) {
        $asset_id = mysqli_insert_id($mysqli);
        $username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['username'])));
        $password = trim(mysqli_real_escape_string($mysqli,encryptLoginEntry($_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_created_at = NOW(), login_asset_id = $asset_id, login_client_id = $client_id, company_id = $session_company_id");

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Asset added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_asset'])){

    $asset_id = intval($_POST['asset_id']);
    $login_id = intval($_POST['login_id']);
    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $make = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['make'])));
    $model = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['model'])));
    $serial = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['serial'])));
    $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['os'])));
    $ip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['ip'])));
    $mac = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['mac'])));
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $network = intval($_POST['network']);
    $purchase_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['purchase_date'])));
    if(empty($purchase_date)){
        $purchase_date = "0000-00-00";
    }
    $warranty_expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['warranty_expire'])));
    if(empty($warranty_expire)){
        $warranty_expire = "0000-00-00";
    }
    $install_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['install_date'])));
    if(empty($install_date)){
        $install_date = "0000-00-00";
    }
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    $username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['username'])));
    $password = trim(mysqli_real_escape_string($mysqli,encryptLoginEntry($_POST['password'])));

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_install_date = '$install_date', asset_notes = '$notes', asset_updated_at = NOW(), asset_network_id = $network WHERE asset_id = $asset_id AND company_id = $session_company_id");

    //If login exists then update the login
    if($login_id > 0){
        mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_updated_at = NOW() WHERE login_id = $login_id AND company_id = $session_company_id");
    }else{
    //If Username is filled in then add a login
        if(!empty($username)) {
            
            mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_created_at = NOW(), login_asset_id = $asset_id, login_client_id = $client_id, company_id = $session_company_id");

        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Asset updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_asset'])){
    $asset_id = intval($_GET['delete_asset']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Deleted', log_description = '$asset_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Asset deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST["import_client_assets_csv"])){
    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = FALSE;

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if(in_array($file_extension,$allowed_file_extensions) === false){
        $error = TRUE;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif($_FILES["file"]["size"] < 1){
        $error = TRUE;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count (name, type, make, model, serial, os)
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if(!$error & count($f_columns) != 8) {
        $error = TRUE;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if(!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $asset_count = 0;
        $duplicate_count = 0;
        $duplicate_detect = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== FALSE){
            if(isset($column[0])){
                $name = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[0])));
                if(mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_name = '$name' AND asset_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if(isset($column[1])){
                $type = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[1])));
            }
            if(isset($column[2])){
                $make = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[2])));
            }
            if(isset($column[3])){
                $model = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[3])));
            }
            if(isset($column[4])){
                $serial = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[4])));
            }
            if(isset($column[5])){
                $os = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[5])));
            }
            if(isset($column[6])){
                $contact = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[6])));
                $sql_contact = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_name = '$contact' AND contact_client_id = $client_id");
                $row = mysqli_fetch_assoc($sql_contact);
                $contact_id = $row['contact_id'];
                $contact = intval($contact_id);
            }
            if(isset($column[7])){
                $location = trim(strip_tags(mysqli_real_escape_string($mysqli, $column[7])));
                $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
                $row = mysqli_fetch_assoc($sql_location);
                $location_id = $row['location_id'];
                $location = intval($location_id);
            }
            // Potentially import the rest in the future?

            
            // Check if duplicate was detected
            if($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_created_at = NOW(), asset_contact_id = $contact, asset_location_id = $location, asset_client_id = $client_id, company_id = $session_company_id");
                $asset_count = $asset_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }  
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Import', log_description = '$session_name imported $asset_count asset(s) via CSV file', log_created_at = NOW(), company_id = $session_company_id, log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "$asset_count Asset(s) with added $duplicate_count duplicate(s)";
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
    $filename = $client_name . "-Assets-Template.csv";
    
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
    $client_id = intval($_GET['export_client_assets_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    $sql = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id WHERE asset_client_id = $client_id ORDER BY asset_name ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Assets-" . date('Y-m-d') . ".csv";
        
        //create a file pointer
        $f = fopen('php://memory', 'w');
        
        //set column headers
        $fields = array('Name', 'Type', 'Make', 'Model', 'Serial Number', 'Operating System', 'Purchase Date', 'Warranty Expire', 'Install Date', 'Assigned To', 'Location', 'Notes');
        fputcsv($f, $fields, $delimiter);
        
        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
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
    exit;
  
}

if(isset($_POST['add_software'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $license = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['license'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_type = '$type', software_license = '$license', software_notes = '$notes', software_created_at = NOW(), software_client_id = $client_id, company_id = $session_company_id");

    if(!empty($_POST['username'])) {
        $software_id = mysqli_insert_id($mysqli);
        $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
        $password = trim(mysqli_real_escape_string($mysqli,encryptLoginEntry($_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_software_id = $software_id, login_created_at = NOW(), login_client_id = $client_id, company_id = $session_company_id");

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Software added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_software'])){

    $software_id = intval($_POST['software_id']);
    $login_id = intval($_POST['login_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $license = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['license'])));
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    $username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['username'])));
    $password = trim(mysqli_real_escape_string($mysqli,encryptLoginEntry($_POST['password'])));

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_type = '$type', software_license = '$license', software_notes = '$notes', software_updated_at = NOW() WHERE software_id = $software_id AND company_id = $session_company_id");

    //If login exists then update the login
    if($login_id > 0){
        mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_updated_at = NOW() WHERE login_id = $login_id AND company_id = $session_company_id");
    }else{
    //If Username is filled in then add a login
        if(!empty($username)) {
            
            mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_created_at = NOW(), login_software_id = $software_id, login_client_id = $client_id, company_id = $session_company_id");

        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Software updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_software'])){
    $software_id = intval($_GET['delete_software']);

    mysqli_query($mysqli,"DELETE FROM software WHERE software_id = $software_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software', log_action = 'Deleted', log_description = '$software_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Software deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['export_client_software_csv'])){
    $client_id = intval($_GET['export_client_software_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    $sql = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Software-" . date('Y-m-d') . ".csv";
        
        //create a file pointer
        $f = fopen('php://memory', 'w');
        
        //set column headers
        $fields = array('Name', 'Type', 'License', 'Notes');
        fputcsv($f, $fields, $delimiter);
        
        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['software_name'], $row['software_type'], $row['software_license'], $row['software_notes']);
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

if(isset($_POST['add_login'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $uri = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['uri'])));
    $username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['username'])));
    $password = trim(mysqli_real_escape_string($mysqli,encryptLoginEntry($_POST['password'])));
    $otp_secret = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['otp_secret'])));
    $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $software_id = intval($_POST['software']);

    mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_uri = '$uri', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_created_at = NOW(), login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id, login_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Login added";
    
    header("Location: client.php?client_id=$client_id&tab=logins");

}

if(isset($_POST['edit_login'])){

    $login_id = intval($_POST['login_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $uri = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['uri'])));
    $username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['username'])));
    $password = trim(mysqli_real_escape_string($mysqli,encryptLoginEntry($_POST['password'])));
    $otp_secret = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['otp_secret'])));
    $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $software_id = intval($_POST['software']);

    mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_uri = '$uri', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_updated_at = NOW(), login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id WHERE login_id = $login_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Login updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_login'])){
    $login_id = intval($_GET['delete_login']);

    mysqli_query($mysqli,"DELETE FROM logins WHERE login_id = $login_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Deleted', log_description = '$login_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Login deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['export_client_logins_csv'])){
    $client_id = intval($_GET['export_client_logins_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    $sql = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_client_id = $client_id ORDER BY login_name ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Logins-" . date('Y-m-d') . ".csv";
        
        //create a file pointer
        $f = fopen('php://memory', 'w');
        
        //set column headers
        $fields = array('Name', 'Username', 'Password', 'URL', 'Notes');
        fputcsv($f, $fields, $delimiter);
        
        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $login_password = decryptLoginEntry($row['login_password']);
            $lineData = array($row['login_name'], $row['login_username'], $login_password, $row['login_uri'], $row['login_note']);
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

if(isset($_POST['add_network'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $vlan = intval($_POST['vlan']);
    $network = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['network'])));
    $gateway = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['gateway'])));
    $dhcp_range = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['dhcp_range'])));
    $location_id = intval($_POST['location']);

    mysqli_query($mysqli,"INSERT INTO networks SET network_name = '$name', network_vlan = $vlan, network = '$network', network_gateway = '$gateway', network_dhcp_range = '$dhcp_range', network_created_at = NOW(), network_location_id = $location_id, network_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Network added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_network'])){

    $network_id = intval($_POST['network_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $vlan = intval($_POST['vlan']);
    $network = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['network'])));
    $gateway = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['gateway'])));
    $dhcp_range = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['dhcp_range'])));
    $location_id = intval($_POST['location']);

    mysqli_query($mysqli,"UPDATE networks SET network_name = '$name', network_vlan = $vlan, network = '$network', network_gateway = '$gateway', network_dhcp_range = '$dhcp_range', network_updated_at = NOW(), network_location_id = $location_id WHERE network_id = $network_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Modifed', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Network updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_network'])){
    $network_id = intval($_GET['delete_network']);

    mysqli_query($mysqli,"DELETE FROM networks WHERE network_id = $network_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Deleted', log_description = '$network_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Network deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['export_client_networks_csv'])){
    $client_id = intval($_GET['export_client_networks_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    $sql = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_client_id = $client_id ORDER BY network_name ASC");
    if($sql->num_rows > 0){
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
    exit;
  
}

if(isset($_POST['add_certificate'])){
 
    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $domain = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['domain'])));
    $issued_by = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['issued_by'])));
    $expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire'])));
    $public_key = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['public_key'])));
    $domain_id = intval($_POST['domain_id']);

    // Parse public key data for a manually provided public key
    if(!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = strip_tags($public_key_obj['issuer']['O']);
        }
    }

    if(empty($expire)){
        $expire = "0000-00-00";
    }

    mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_created_at = NOW(), certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Certificate added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_certificate'])){

    $certificate_id = intval($_POST['certificate_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $domain = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['domain'])));
    $issued_by = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['issued_by'])));
    $expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire'])));
    $public_key = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['public_key'])));
    $domain_id = intval($_POST['domain_id']);

    // Parse public key data for a manually provided public key
    if(!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = strip_tags($public_key_obj['issuer']['O']);
        }
    }

    if(empty($expire)){
        $expire = "0000-00-00";
    }

    mysqli_query($mysqli,"UPDATE certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_updated_at = NOW(), certificate_public_key = '$public_key', certificate_domain_id = '$domain_id' WHERE certificate_id = $certificate_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Certificate updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['fetch_certificate'])){
    $domain = $_GET['domain'];

    // FQDNs in database shouldn't have a URL scheme, adding one
    $domain = "https://".$domain;

    // Parse host and port
    $url = parse_url($domain, PHP_URL_HOST);
    $port = parse_url($domain, PHP_URL_PORT);
    // Default port
    if(!$port){
        $port = "443";
    }

    // Get certificate
    // Using verify peer false to allow for self-signed / internal CA certs
    $socket = "ssl://$url:$port";
    $get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE, "verify_peer" => FALSE,)));
    $read = stream_socket_client($socket, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
    $cert = stream_context_get_params($read);
    $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

    // Process data
    if($cert_public_key_obj){
        $cert_data['success'] = "TRUE";
        $cert_data['expire'] = date('Y-m-d', $cert_public_key_obj['validTo_time_t']);
        $cert_data['issued_by'] = strip_tags($cert_public_key_obj['issuer']['O']);
        $cert_data['public_key'] = $export; //nl2br
    }
    else{
        $cert_data['success'] = "FALSE";
    }

    // Return as JSON
    echo json_encode($cert_data);

}

if(isset($_GET['delete_certificate'])){
    $certificate_id = intval($_GET['delete_certificate']);

    mysqli_query($mysqli,"DELETE FROM certificates WHERE certificate_id = $certificate_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Deleted', log_description = '$certificate_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Certificate deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['export_client_certificates_csv'])){
    $client_id = intval($_GET['export_client_certificates_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    $sql = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_client_id = $client_id ORDER BY certificate_name ASC");
    if($sql->num_rows > 0){
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
    exit;
  
}

if(isset($_POST['add_domain'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $registrar = intval($_POST['registrar']);
    $webhost = intval($_POST['webhost']);
    $expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire'])));
    if(empty($expire)){
        $expire = "0000-00-00";
    }

    mysqli_query($mysqli,"INSERT INTO domains SET domain_name = '$name', domain_registrar = $registrar,  domain_webhost = $webhost, domain_expire = '$expire', domain_created_at = NOW(), domain_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Domain added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_domain'])){

    $domain_id = intval($_POST['domain_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $registrar = intval($_POST['registrar']);
    $webhost = intval($_POST['webhost']);
    $expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire'])));
    if(empty($expire)){
        $expire = "0000-00-00";
    }

    mysqli_query($mysqli,"UPDATE domains SET domain_name = '$name', domain_registrar = $registrar,  domain_webhost = $webhost, domain_expire = '$expire', domain_updated_at = NOW() WHERE domain_id = $domain_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Domain updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_domain'])){
    $domain_id = intval($_GET['delete_domain']);

    mysqli_query($mysqli,"DELETE FROM domains WHERE domain_id = $domain_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Deleted', log_description = '$domain_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Domain deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['export_client_domains_csv'])){
    $client_id = intval($_GET['export_client_domains_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    $sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_client_id = $client_id ORDER BY domain_name ASC");
    
    if($sql->num_rows > 0){
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
    exit;
  
}

if(isset($_POST['add_ticket'])){
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");

    // Initiate HTML Purifier
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier = new HTMLPurifier($purifier_config);

    $client_id = intval($_POST['client']);
    $assigned_to = intval($_POST['assigned_to']);
    $contact = intval($_POST['contact']);
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $priority = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['priority'])));
    $dirty_details = mysqli_real_escape_string($mysqli,$_POST['details']);
    $details = $purifier->purify($dirty_details);
    $asset_id = intval($_POST['asset']);

    if($client_id > 0 AND $contact == 0){
        $sql = mysqli_query($mysqli,"SELECT primary_contact FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $contact = $row['primary_contact'];
    } 

    //Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 'Open', ticket_asset_id = $asset_id, ticket_created_at = NOW(), ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = '$session_name created ticket $subject', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket created";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_scheduled_ticket'])){
    $client_id = intval($_POST['client']);
    $contact = intval($_POST['contact']);
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $priority = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['priority'])));
    $details = trim(mysqli_real_escape_string($mysqli,$_POST['details']));
    $asset_id = intval($_POST['asset']);
    $frequency = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency'])));
    $start_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['start_date'])));

    if($client_id > 0 AND $contact == 0){
        $sql = mysqli_query($mysqli,"SELECT primary_contact FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $contact = $row['primary_contact'];
    }

    // Add scheduled ticket
    mysqli_query($mysqli, "INSERT INTO scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_start_date = '$start_date', scheduled_ticket_next_run = '$start_date', scheduled_ticket_created_at = NOW(), scheduled_ticket_created_by = '$session_user_id', scheduled_ticket_client_id = '$client_id', scheduled_ticket_contact_id = '$contact', scheduled_ticket_asset_id = '$asset_id', company_id = '$session_company_id'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'Created scheduled ticket for $subject - $frequency', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Scheduled ticket created.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_scheduled_ticket'])){
    $client_id = intval($_POST['client_id']);
    $ticket_id = intval($_POST['ticket_id']);
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $priority = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['priority'])));
    $details = trim(mysqli_real_escape_string($mysqli,$_POST['details']));
    $asset_id = intval($_POST['asset']);
    $frequency = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency'])));
    $next_run_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['next_date'])));

    // Edit scheduled ticket
    mysqli_query($mysqli, "UPDATE scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_next_run = '$next_run_date', scheduled_ticket_updated_at = NOW(), scheduled_ticket_asset_id = '$asset_id', company_id = '$session_company_id' WHERE scheduled_ticket_id = '$ticket_id'");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Updated scheduled ticket for $subject - $frequency', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Scheduled ticket updated.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_scheduled_ticket'])){
    $scheduled_ticket_id = intval($_GET['delete_scheduled_ticket']);

    // Delete
    mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_id = '$scheduled_ticket_id'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Deleted', log_description = 'Deleted scheduled ticket $scheduled_ticket_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Scheduled ticket deleted.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_ticket'])){

    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = intval($_POST['assigned_to']);
    $contact_id = intval($_POST['contact']);
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $priority = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['priority'])));
    $details = trim(mysqli_real_escape_string($mysqli,$_POST['details']));
    $asset_id = intval($_POST['asset']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_subject = '$subject', ticket_priority = '$priority', ticket_details = '$details', ticket_updated_at = NOW(), ticket_assigned_to = $assigned_to, ticket_contact_id = $contact_id, ticket_asset_id = $asset_id WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modified', log_description = '$subject', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['assign_ticket'])){

    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = intval($_POST['assigned_to']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_updated_at = NOW(), ticket_assigned_to = $assigned_to WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket re-assigned', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_created_at = NOW(), ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modified', log_description = '$subject', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket re-assigned";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_ticket'])){
    $ticket_id = intval($_GET['delete_ticket']);

    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Deleted', log_description = '$ticket_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket deleted";
    
    header("Location: tickets.php");
  
}

if(isset($_POST['add_ticket_reply'])){
    require("plugins/htmlpurifier/HTMLPurifier.standalone.php");

    // Initiate HTML Purifier
    $purifier_config = HTMLPurifier_Config::createDefault();
    $purifier = new HTMLPurifier($purifier_config);

    $ticket_id = intval($_POST['ticket_id']);
    $dirty = trim(mysqli_real_escape_string($mysqli,$_POST['ticket_reply']));
    $ticket_reply = $purifier->purify($dirty);
    $ticket_status = trim(mysqli_real_escape_string($mysqli,$_POST['status']));
    $ticket_reply_time_worked = trim(mysqli_real_escape_string($mysqli,$_POST['time']));

    if(isset($_POST['public_reply_type'])){
        $ticket_reply_type = 'Public';
    }
    else{
        $ticket_reply_type = 'Internal';
    }

    // Add reply
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_type = '$ticket_reply_type', ticket_reply_created_at = NOW(), ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    // Update Ticket Last Response Field
    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = '$ticket_status', ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    // Send e-mail to client if public update & email is setup
    if($ticket_reply_type == 'Public' && !empty($config_smtp_host)){

        $ticket_sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject FROM tickets 
                                                    LEFT JOIN clients ON ticket_client_id = client_id 
                                                    LEFT JOIN contacts ON ticket_contact_id = contact_id 
                                                  WHERE ticket_id = $ticket_id AND tickets.company_id = $session_company_id");
        $ticket_details = mysqli_fetch_array($ticket_sql);

        $contact_name = $ticket_details['contact_name'];
        $contact_email = $ticket_details['contact_email'];

        $ticket_prefix = $ticket_details['ticket_prefix'];
        $ticket_number = $ticket_details['ticket_number'];
        $ticket_subject = $ticket_details['ticket_subject'];

        if(filter_var($contact_email, FILTER_VALIDATE_EMAIL)){

            $mail = new PHPMailer(true);

            try{
                //Mail Server Settings
                $mail->SMTPDebug = 2;                                       // Enable verbose debug output
                $mail->isSMTP();                                            // Set mailer to use SMTP
                $mail->Host       = $config_smtp_host;                      // Specify main and backup SMTP servers
                $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
                $mail->Username   = $config_smtp_username;                  // SMTP username
                $mail->Password   = $config_smtp_password;                  // SMTP password
                $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
                $mail->Port       = $config_smtp_port;                      // TCP port to connect to

                //Recipients
                $mail->setFrom($config_mail_from_email, $config_mail_from_name);
                $mail->addAddress("$contact_email", "$contact_name");       // Add a recipient

                // Content
                $mail->isHTML(true);                                        // Set email format to HTML

                $mail->Subject = "Ticket update - [$ticket_prefix$ticket_number] - $ticket_subject";
                $mail->Body    = "Hello, $contact_name<br><br>Your ticket regarding \"$ticket_subject\" has been updated.<br><br>--------------------------------<br>$ticket_reply--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br><br>~<br>$session_company_name";
                $mail->send();
            }
            catch(Exception $e){
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }
    }
    //End Mail IF Try-Catch

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Created', log_description = '$ticket_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Posted an update";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    
}

if(isset($_POST['edit_ticket_reply'])){

    $ticket_reply_id = intval($_POST['ticket_reply_id']);
    $ticket_reply = trim(mysqli_real_escape_string($mysqli,$_POST['ticket_reply']));

    mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_updated_at = NOW() WHERE ticket_reply_id = $ticket_reply_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Update Modify', log_action = 'Modified', log_description = '$ticket_update_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket update modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    
}

if(isset($_GET['archive_ticket_reply'])){
    $ticket_reply_id = intval($_GET['archive_ticket_reply']);

    mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply_archived_at = NOW() WHERE ticket_reply_id = $ticket_reply_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Update', log_action = 'Archived', log_description = '$ticket_update_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket update archived";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['merge_ticket_get_json_details'])){
    $merge_into_ticket_number = intval($_GET['merge_into_ticket_number']);

    $sql = mysqli_query($mysqli,"SELECT * FROM tickets
      LEFT JOIN clients ON ticket_client_id = client_id 
      LEFT JOIN contacts ON ticket_contact_id = contact_id
      WHERE ticket_number = '$merge_into_ticket_number' AND tickets.company_id = '$session_company_id'");

    if(mysqli_num_rows($sql) == 0){
        //Do nothing.
    }
    else {
        //Return ticket, client and contact details for the given ticket number
        $row = mysqli_fetch_array($sql);
        echo json_encode($row);
    }
}

if(isset($_POST['merge_ticket'])){
    $ticket_id = intval($_POST['ticket_id']);
    $merge_into_ticket_number = intval($_POST['merge_into_ticket_number']);
    $merge_comment = trim(mysqli_real_escape_string($mysqli,$_POST['merge_comment']));
    $ticket_reply_type = 'Internal';

    //Get current ticket details
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_details FROM tickets WHERE ticket_id = '$ticket_id'");
    if(mysqli_num_rows($sql) == 0){
        $_SESSION['alert_message'] = "No ticket with that ID found.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $row = mysqli_fetch_array($sql);
    $ticket_prefix = trim(mysqli_real_escape_string($mysqli,$row['ticket_prefix']));
    $ticket_number = trim(mysqli_real_escape_string($mysqli,$row['ticket_number']));
    $ticket_subject = trim(mysqli_real_escape_string($mysqli,$row['ticket_subject']));
    $ticket_details = trim(mysqli_real_escape_string($mysqli,$row['ticket_details']));

    //Get merge into ticket id (as it may differ from the number)
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_number = '$merge_into_ticket_number'");
    if(mysqli_num_rows($sql) == 0){
        $_SESSION['alert_message'] = "Cannot merge into that ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $merge_row = mysqli_fetch_array($sql);
    $merge_into_ticket_id = trim(mysqli_real_escape_string($mysqli,$merge_row['ticket_id']));

    if($ticket_number == $merge_into_ticket_number){
        $_SESSION['alert_message'] = "Cannot merge into the same ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    //Update current ticket
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number merged into $ticket_prefix$merge_into_ticket_number. Comment: $merge_comment', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_created_at = NOW(), ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));
    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Closed', ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Update new ticket
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number was merged into this ticket with comment: $merge_comment.<br><b>$ticket_subject</b><br>$ticket_details', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_created_at = NOW(), ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $merge_into_ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Merged', log_description = 'Merged ticket $ticket_prefix$ticket_number into $ticket_prefix$merge_into_ticket_number', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket merged into $ticket_prefix$merge_into_ticket_number.";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['close_ticket'])){

    $ticket_id = intval($_GET['close_ticket']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Closed', ticket_updated_at = NOW(), ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_created_at = NOW(), ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Closed', log_description = '$ticket_id Closed', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket Closed, this cannot not be reopened but you may start another one";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    
}

if(isset($_GET['export_client_tickets_csv'])){
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

if(isset($_POST['add_service'])){
    $client_id = intval($_POST['client_id']);
    $service_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $service_description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $service_category = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['category']))); //TODO: Needs integration with company categories
    $service_importance = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['importance'])));
    $service_backup = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['backup'])));
    $service_notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));

    // Create Service
    $service_sql = mysqli_query($mysqli, "INSERT INTO services SET service_name = '$service_name', service_description = '$service_description', service_category = '$service_category', service_importance = '$service_importance', service_backup = '$service_backup', service_notes = '$service_notes', service_created_at = NOW(), service_client_id = '$client_id', company_id = '$session_company_id'");

    // TODO: Support for URLs

    // Create links to assets
    if($service_sql){
        $service_id = $mysqli->insert_id;

        if(!empty($_POST['contacts'])){
            $service_contact_ids = $_POST['contacts'];
            foreach($service_contact_ids as $contact_id){
                if(intval($contact_id)){
                    mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = '$service_id', contact_id = '$contact_id'");
                }
            }
        }

        if(!empty($_POST['vendors'])){
            $service_vendor_ids = $_POST['vendors'];
            foreach($service_vendor_ids as $vendor_id){
                if(intval($vendor_id)){
                    mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = '$service_id', vendor_id = '$vendor_id'");
                }
            }
        }

        if(!empty($_POST['documents'])){
            $service_document_ids = $_POST['documents'];
            foreach($service_document_ids as $document_id){
                if(intval($document_id)){
                    mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = '$service_id', document_id = '$document_id'");
                }
            }
        }

        if(!empty($_POST['assets'])){
            $service_asset_ids = $_POST['assets'];
            foreach($service_asset_ids as $asset_id){
                if(intval($asset_id)){
                    mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = '$service_id', asset_id = '$asset_id'");
                }
            }
        }

        if(!empty($_POST['logins'])){
            $service_login_ids = $_POST['logins'];
            foreach($service_login_ids as $login_id){
                if(intval($login_id)){
                    mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = '$service_id', login_id = '$login_id'");
                }
            }
        }

        if(!empty($_POST['logins'])){
            $service_domain_ids = $_POST['domains'];
            foreach($service_domain_ids as $domain_id){
                if(intval($domain_id)){
                    mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = '$service_id', domain_id = '$domain_id'");
                }
            }
        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Create', log_description = '$session_name created service $service_name', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Service added";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    }
    else{
        $_SESSION['alert_message'] = "Something went wrong (SQL)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_POST['edit_service'])){
    $client_id = intval($_POST['client_id']);
    $service_id = intval($_POST['service_id']);
    $service_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $service_description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $service_category = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['category']))); //TODO: Needs integration with company categories
    $service_importance = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['importance'])));
    $service_backup = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['backup'])));
    $service_notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));

    // Update main service details
    mysqli_query($mysqli, "UPDATE services SET service_name = '$service_name', service_description = '$service_description', service_category = '$service_category', service_importance = '$service_importance', service_backup = '$service_backup', service_notes = '$service_notes', service_updated_at = NOW() WHERE service_id = '$service_id' AND company_id = '$session_company_id'");

    // Unlink existing relations/assets
    mysqli_query($mysqli, "DELETE FROM service_contacts WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_assets WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_logins WHERE service_id = '$service_id'");
    mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = '$service_id'");

    // Relink
    if(!empty($_POST['contacts'])){
        $service_contact_ids = $_POST['contacts'];
        foreach($service_contact_ids as $contact_id){
            if(intval($contact_id)){
                mysqli_query($mysqli, "INSERT INTO service_contacts SET service_id = '$service_id', contact_id = '$contact_id'");
            }
        }
    }

    if(!empty($_POST['vendors'])){
        $service_vendor_ids = $_POST['vendors'];
        foreach($service_vendor_ids as $vendor_id){
            if(intval($vendor_id)){
                mysqli_query($mysqli, "INSERT INTO service_vendors SET service_id = '$service_id', vendor_id = '$vendor_id'");
            }
        }
    }

    if(!empty($_POST['documents'])){
        $service_document_ids = $_POST['documents'];
        foreach($service_document_ids as $document_id){
            if(intval($document_id)){
                mysqli_query($mysqli, "INSERT INTO service_documents SET service_id = '$service_id', document_id = '$document_id'");
            }
        }
    }

    if(!empty($_POST['assets'])){
        $service_asset_ids = $_POST['assets'];
        foreach($service_asset_ids as $asset_id){
            if(intval($asset_id)){
                mysqli_query($mysqli, "INSERT INTO service_assets SET service_id = '$service_id', asset_id = '$asset_id'");
            }
        }
    }

    if(!empty($_POST['logins'])){
        $service_login_ids = $_POST['logins'];
        foreach($service_login_ids as $login_id){
            if(intval($login_id)){
                mysqli_query($mysqli, "INSERT INTO service_logins SET service_id = '$service_id', login_id = '$login_id'");
            }
        }
    }

    if(!empty($_POST['logins'])){
        $service_domain_ids = $_POST['domains'];
        foreach($service_domain_ids as $domain_id){
            if(intval($domain_id)){
                mysqli_query($mysqli, "INSERT INTO service_domains SET service_id = '$service_id', domain_id = '$domain_id'");
            }
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Modified', log_description = '$session_name modified service $service_name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Service updated";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_service'])){
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

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Service', log_action = 'Deleted', log_description = '$session_name deleted service $service_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

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
    $new_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['new_name'])));

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
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
        $file_reference_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'gif', 'png', 'pdf', 'txt', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'zip', 'tar', 'gz');
     
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
            
            mysqli_query($mysqli,"INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$file_name', file_ext = '$file_extension', file_created_at = NOW(), file_client_id = $client_id, company_id = $session_company_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{
            
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Uploaded', log_description = '$path', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "File uploaded";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_file'])){
    $file_id = intval($_GET['delete_file']);

    $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql_file);
    $client_id = $row['file_client_id'];
    $file_reference_name = $row['file_reference_name'];

    unlink("uploads/clients/$session_company_id/$client_id/$file_reference_name");

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Deleted', log_description = '$file_name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "File deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_document'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $tags_ids = $_POST['tags_ids'];
    $content = trim(mysqli_real_escape_string($mysqli,$_POST['content']));

    // Document add query
    $add_document = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_content = '$content', document_created_at = NOW(), document_client_id = $client_id, company_id = $session_company_id");
    $document_id = $mysqli->insert_id;

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Created', log_description = '$details', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    // Add tags
    foreach($tags_ids as $tag_id) {
        if (intval($tag_id)) {
            mysqli_query($mysqli, "INSERT INTO documents_tagged SET document_id = '$document_id', tag_id = '$tag_id'");
        }
    }

    $_SESSION['alert_message'] = "Document added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_document'])){

    $document_id = intval($_POST['document_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $tags_ids = $_POST['tags_ids'];
    $content = trim(mysqli_real_escape_string($mysqli,$_POST['content']));

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name', document_content = '$content', document_updated_at = NOW() WHERE document_id = $document_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Note', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    // Remove any old tags
    mysqli_query($mysqli, "DELETE FROM documents_tagged WHERE document_id = $document_id");

    // Add tags
    foreach($tags_ids as $tag_id) {
        if (intval($tag_id)) {
            mysqli_query($mysqli, "INSERT INTO documents_tagged SET document_id = '$document_id', tag_id = '$tag_id'");
        }
    }

    $_SESSION['alert_message'] = "Document updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_document'])){
    $document_id = intval($_GET['delete_document']);

    mysqli_query($mysqli,"DELETE FROM documents WHERE document_id = $document_id AND company_id = $session_company_id");

    // Delete the tag associations to documents
    mysqli_query($mysqli, "DELETE FROM documents_tagged WHERE document_id = '$document_id'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Deleted', log_description = '$document_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Document deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if (isset($_POST['add_document_tag'])) {
    $client_id = intval($_POST['client_id']);
    $tag_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['tag_name'])));

    mysqli_query($mysqli,"INSERT INTO document_tags SET client_id = '$client_id', tag_name = '$tag_name'");

    $_SESSION['alert_message'] = "Document tag added";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['delete_document_tag'])) {
    $tag_id = intval($_POST['tag_id']);

    // Delete the tag ID
    mysqli_query($mysqli, "DELETE FROM document_tags WHERE tag_id = '$tag_id'");

    // Delete the associations to documents
    mysqli_query($mysqli, "DELETE FROM documents_tagged WHERE tag_id = '$tag_id'");

    $_SESSION['alert_message'] = "Document tag deleted";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['rename_document_tag'])) {
    $tag_id = intval($_POST['tag_id']);
    $tag_new_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['tag_new_name'])));

    // Rename tag in db
    mysqli_query($mysqli, "UPDATE document_tags SET tag_name = '$tag_new_name' WHERE tag_id = '$tag_id'");

    $_SESSION['alert_message'] = "Document tag updated";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['force_recurring'])){
    $recurring_id = intval($_GET['force_recurring']);

    $sql_recurring = mysqli_query($mysqli,"SELECT * FROM recurring, clients WHERE client_id = recurring_client_id AND recurring_id = $recurring_id AND recurring.company_id = $session_company_id");

    $row = mysqli_fetch_array($sql_recurring);
    $recurring_id = $row['recurring_id'];
    $recurring_scope = $row['recurring_scope'];
    $recurring_frequency = $row['recurring_frequency'];
    $recurring_status = $row['recurring_status'];
    $recurring_last_sent = $row['recurring_last_sent'];
    $recurring_next_date = $row['recurring_next_date'];
    $recurring_amount = $row['recurring_amount'];
    $recurring_currency_code = $row['recurring_currency_code'];
    $recurring_note = mysqli_real_escape_string($mysqli,$row['recurring_note']);
    $category_id = $row['recurring_category_id'];
    $client_id = $row['recurring_client_id'];
    $client_net_terms = $row['client_net_terms'];

    //Get the last Invoice Number and add 1 for the new invoice number
    $new_invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $session_company_id");

    //Generate a unique URL key for clients to access
    $url_key = keygen();

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = '$new_invoice_number', invoice_scope = '$recurring_scope', invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_amount = '$recurring_amount', invoice_currency_code = '$recurring_currency_code', invoice_note = '$recurring_note', invoice_category_id = $category_id, invoice_status = 'Sent', invoice_url_key = '$url_key', invoice_created_at = NOW(), invoice_client_id = $client_id, company_id = $session_company_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    //Copy Items from original invoice to new invoice
    $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id AND company_id = $session_company_id ORDER BY item_id ASC");

    while($row = mysqli_fetch_array($sql_invoice_items)){
        $item_id = $row['item_id'];
        $item_name = mysqli_real_escape_string($mysqli,$row['item_name']);
        $item_description = mysqli_real_escape_string($mysqli,$row['item_description']);
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $tax_id = $row['item_tax_id'];

        //Recalculate Item Tax since Tax percents can change.
        if($tax_id > 0){
            $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id AND company_id = $session_company_id");
            $row = mysqli_fetch_array($sql);
            $tax_percent = $row['tax_percent'];
            $item_tax_amount = $item_subtotal * $tax_percent / 100;
        }else{
            $item_tax_amount = 0;
        }
        
        $item_total = $item_subtotal + $item_tax_amount;

        //Update Recurring Items with new tax
        mysqli_query($mysqli,"UPDATE invoice_items SET item_tax = '$item_tax_amount', item_total = '$item_total', item_updated_at = NOW(), item_tax_id = $tax_id WHERE item_id = $item_id");

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax_amount', item_total = '$item_total', item_created_at = NOW(), item_tax_id = $tax_id, item_invoice_id = $new_invoice_id, company_id = $session_company_id");
    }

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Invoice Generated from Recurring!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $session_company_id");

    //Update Recurring Balances by tallying up recurring items also update recurring dates
    $sql_recurring_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_total FROM invoice_items WHERE item_recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql_recurring_total);
    $new_recurring_amount = $row['recurring_total'];

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = '$new_recurring_amount', recurring_last_sent = CURDATE(), recurring_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_frequency), recurring_updated_at = NOW() WHERE recurring_id = $recurring_id");

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
        $invoice_amount = $row['invoice_amount'];
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
        $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

        $mail = new PHPMailer(true);

        try{

            //Mail Server Settings

            $mail->SMTPDebug = 2;                                       // Enable verbose debug output
            $mail->isSMTP();                                            // Set mailer to use SMTP
            $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = $config_smtp_username;                     // SMTP username
            $mail->Password   = $config_smtp_password;                               // SMTP password
            $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
            $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($config_mail_from_email, $config_mail_from_name);
            $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML

            $mail->Subject = "Invoice $invoice_prefix$invoice_number";
            $mail->Body    = "Hello $contact_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: $$invoice_amount<br>Due Date: $invoice_due<br><br><br>To view your invoice online click <a href='https://$base_url/guest_view_invoice.php?invoice_id=$new_invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>$company_phone";

            $mail->send();

            mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Auto Emailed Invoice!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $session_company_id");

            //Update Invoice Status to Sent
            mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', invoice_updated_at = NOW(), invoice_client_id = $client_id WHERE invoice_id = $new_invoice_id AND company_id = $session_company_id");

        }catch(Exception $e){
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Failed to send Invoice!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $session_company_id");
        } //End Mail Try
    } //End Recurring Invoices Loop

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Created', log_description = 'Recurring Forced to an Invoice', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Invoice Forced";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

} //End Force Recurring

if(isset($_POST['export_trips_csv'])){
    $date_from = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_from'])));
    $date_to = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date_to'])));
    if(!empty($date_from) AND !empty($date_to)){
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

if(isset($_GET['export_client_pdf'])){
    $client_id = intval($_GET['export_client_pdf']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN contacts ON primary_contact = contact_id LEFT JOIN locations ON primary_location = location_id WHERE client_id = $client_id AND clients.company_id = $session_company_id");
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
    $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id ORDER BY asset_type ASC");
    $sql_networks = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_client_id = $client_id ORDER BY network_name ASC");
    $sql_domains = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_client_id = $client_id ORDER BY domain_name ASC");
    $sql_certficates = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_client_id = $client_id ORDER BY certificate_name ASC");
    $sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC");

?>

    <script src='plugins/pdfmake/pdfmake.js'></script>
    <script src='plugins/pdfmake/vfs_fonts.js'></script>
    <script>

    var docDefinition = {
        info: {
            title: '<?php echo $client_name; ?>- IT Documentation',
            author: <?php echo json_encode($session_company_name); ?>
        },
        footer: {
            columns: [
                { 
                    text: <?php echo json_encode($client_name); ?>,
                    style: 'documentFooterCenter' 
                },
            ]
        },

        pageMargins: [ 15, 15, 15, 15 ],

        content: [
            { 
                text: <?php echo json_encode($client_name); ?>, 
                style: 'title' 
            },

            {
                //layout: 'lightHorizontalLines', // optional
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
                                text: <?php echo json_encode($contact_email); ?>,
                                style: 'item'
                            },
                            {
                                text: <?php echo json_encode($contact_phone); ?>,
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
            //Contact END

            //Locations Start
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
            //Locations END

            //Vendors Start
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
            //Vendors END

            //Logins Start
            <?php if(isset($_GET['passwords'])){ ?>
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
                            $login_username = $row['login_username'];
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
            <?php } ?>
            //Logins END

            //Assets Start
            { 
                text: 'Assets', 
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
                            }
                        ],
                        
                        <?php
                        while($row = mysqli_fetch_array($sql_assets)){
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
                            }
                        ],

                        <?php
                        }
                        ?>
                    ]
                }
            },
            //Assets END

            //Software Start
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
                            $software_license = $row['software_license'];
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
                                text: <?php echo json_encode($software_license); ?>,
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
            //Software END

            //Networks Start
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
            //Networks END

            //Domains Start
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
            //Domains END

            //Certificates Start
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
            //Certificates END



        ], //End Content,
        styles: {
            //Title
            title: {
                fontSize: 15,
                margin: [0,20,0,5],
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
    

    pdfMake.createPdf(docDefinition).download('<?php echo $client_name; ?>-IT_Documentation-<?php echo date('Y-m-d'); ?>.pdf');

    </script>


<?php

}

?>

<?php

if(isset($_GET['logout'])){
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Logout', log_action = 'Success', log_description = '$session_name logged out', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_created_at = NOW(), log_user_id = $session_user_id");
    mysqli_query($mysqli, "UPDATE users SET user_php_session = '' WHERE user_id = '$session_user_id'");

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