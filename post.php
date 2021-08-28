<?php

include("config.php");
include("check_login.php");
include("functions.php");

require("vendor/PHPMailer-6.4.0/src/PHPMailer.php");
require("vendor/PHPMailer-6.4.0/src/SMTP.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if(isset($_POST['change_records_per_page'])){

    $_SESSION['records_per_page'] = intval($_POST['change_records_per_page']);
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['switch_company'])){
    $company_id = intval($_GET['switch_company']);

    mysqli_query($mysqli,"UPDATE permissions SET permission_default_company = $company_id WHERE user_id = $session_user_id");

    $_SESSION['alert_type'] = "info";
    $_SESSION['alert_message'] = "Switched Companies!";
    
    header("Location: dashboard.php");
  
}

if(isset($_POST['add_user'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $password = md5($_POST['password']);
    $company = intval($_POST['company']);
    $level = intval($_POST['level']);

    mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_created_at = NOW()");

    $user_id = mysqli_insert_id($mysqli);

    if(!file_exists("uploads/users/$user_id/")) {
        mkdir("uploads/users/$user_id");
    }

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/users/$user_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }
    //Set Avatar
    mysqli_query($mysqli,"UPDATE users SET user_avatar = '$path' WHERE user_id = $user_id");

    //Create Permissions
    mysqli_query($mysqli,"INSERT INTO permissions SET permission_level = $level, permission_default_company = $company, permission_companies = $company, user_id = $user_id");
    
    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Created', log_description = '$user_name', log_created_at = NOW()");

    $_SESSION['alert_message'] = "User <strong>$user_name</strong> created!";
    
    header("Location: users.php");

}

if(isset($_POST['edit_user'])){

    $user_id = intval($_POST['user_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $new_password = trim($_POST['new_password']);
    $company = intval($_POST['company']);
    $level = intval($_POST['level']);
    $path = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['current_avatar_path'])));

    if($_FILES['file']['tmp_name']!='') {
        //delete old avatar file
        unlink($path);
        //Update with new path
        $path = "uploads/users/$user_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);   
    }
    
    mysqli_query($mysqli,"UPDATE users SET user_name = '$name', user_email = '$email', user_password = '$password', user_avatar = '$path', user_updated_at = NOW() WHERE user_id = $user_id");

    if(!empty($new_password)){
        $new_password = md5($new_password);
        mysqli_query($mysqli,"UPDATE users SET user_password = '$new_password' WHERE user_id = $user_id");
    }

    //Create Permissions
    mysqli_query($mysqli,"UPDATE permissions SET permission_level = $level, permission_default_company = $company WHERE user_id = $user_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modified', log_description = '$user_name', log_created_at = NOW()");

    $_SESSION['alert_message'] = "User <strong>$user_name</strong> updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_profile'])){

    $user_id = intval($_POST['user_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $new_password = trim($_POST['new_password']);
    $path = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['current_avatar_path'])));

    if($_FILES['file']['tmp_name']!='') {
        //delete old avatar file
        unlink($path);
        //Update with new path
        $path = "uploads/users/$user_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);   
    }
    
    mysqli_query($mysqli,"UPDATE users SET user_name = '$name', user_email = '$email', user_avatar = '$path', user_updated_at = NOW() WHERE user_id = $user_id");

    if(!empty($new_password)){
        $new_password = md5($new_password);
        mysqli_query($mysqli,"UPDATE users SET user_password = '$new_password' WHERE user_id = $user_id");
    }

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modified', log_description = '$user_name', log_created_at = NOW()");

    $_SESSION['alert_message'] = "User <strong>$user_name</strong> updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_user_companies'])){

    $user_id = intval($_POST['user_id']);
    $companies = $_POST['companies'];
    
    //Turn the Array into a string with , seperation
    $companies_imploded = implode(",",$companies);

    mysqli_query($mysqli,"UPDATE permissions SET permission_companies = '$companies_imploded' WHERE user_id = $user_id");
    
    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modified', log_description = '$name', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Companies <strong>$company</strong> added to user $user_id!";
    
    header("Location: users.php");

}

if(isset($_POST['edit_user_clients'])){

    $user_id = intval($_POST['user_id']);
    $clients = $_POST['clients'];
    
    //Turn the Array into a string with , seperation
    $clients_imploded = implode(",",$clients);

    mysqli_query($mysqli,"UPDATE permissions SET permission_clients = '$clients_imploded' WHERE user_id = $user_id");
    
    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Modified', log_description = '$name', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Client <strong>$client_imploded</strong> added to user $user_id!";
    
    header("Location: users.php");

}

if(isset($_GET['archive_user'])){
    $user_id = intval($_GET['archive_user']);

    mysqli_query($mysqli,"UPDATE users SET user_archived_at = NOW() WHERE user_id = $user_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Archived', log_description = '$user_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "User Archived!";
    
    header("Location: users.php");

}

if(isset($_GET['delete_user'])){
    $user_id = intval($_GET['delete_user']);

    mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $user_id");
    mysqli_query($mysqli,"DELETE FROM permissions WHERE user_id = $user_id");
    mysqli_query($mysqli,"DELETE FROM logs WHERE log_user_id = $user_id");
    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_created_by = $user_id");
    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_closed_by = $user_id");
    mysqli_query($mysqli,"DELETE FROM ticket_replies WHERE ticket_reply_by = $user_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User', log_action = 'Deleted', log_description = '$user_id', log_created_at = NOW()");

    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = "User deleted!";
    
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

    mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_created_at = NOW()");

    $config_api_key = keygen();
    $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    $company_id = mysqli_insert_id($mysqli);

    mkdir("uploads/clients/$company_id");
    mkdir("uploads/expenses/$company_id");
    mkdir("uploads/settings/$company_id");
    mkdir("uploads/tmp/$company_id");

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/settings/$company_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    
        mysqli_query($mysqli,"UPDATE companies SET company_logo = '$path' WHERE company_id = $company_id");

    }

    mysqli_query($mysqli,"INSERT INTO settings SET company_id = $company_id, config_default_country = '$country', config_default_currency = '$currency_code', config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_prefix = 'REC-', config_recurring_next_number = 1, config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_api_key = '$config_api_key', config_recurring_auto_send_invoice = 1, config_default_net_terms = 7, config_send_invoice_reminders = 0, config_enable_cron = 0, config_ticket_next_number = 1, config_base_url = '$config_base_url'");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Create', log_description = '$name', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> created!";
    
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

    $path = strip_tags(mysqli_real_escape_string($mysqli,$_POST['current_file_path']));

    if(!file_exists("uploads/settings/$company_id/")) {
        mkdir("uploads/settings/$company_id");
    }

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/settings/$company_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"UPDATE companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_logo = '$path', company_updated_at = NOW() WHERE company_id = $company_id");

    mysqli_query($mysqli,"UPDATE settings SET config_default_currency = '$currency_code', config_default_country = '$country' WHERE company_id = $company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Modified', log_description = '$name', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> updated!";
    
    header("Location: companies.php");

}

if(isset($_GET['archive_company'])){
    $company_id = intval($_GET['archive_company']);

    mysqli_query($mysqli,"UPDATE companies SET company_archived_at = NOW() WHERE company_id = $company_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Archived', log_description = '$company_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Company Archived";
    
    header("Location: companies.php");

}

if(isset($_GET['delete_company'])){
    $company_id = intval($_GET['delete_company']);

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
    
    //Delete Company Files
    removeDirectory('uploads/clients/$company_id');
    removeDirectory('uploads/expenses/$company_id');
    removeDirectory('uploads/settings/$company_id');
    removeDirectory('uploads/tmp/$company_id');

    //Finally Remove the company
    mysqli_query($mysqli,"DELETE FROM companies WHERE company_id = $company_id");
    
    header("Location: logout.php");
  
}

if(isset($_POST['verify'])){

    require_once("rfc6238.php");
    $currentcode = $_POST['code'];  //code to validate, for example received from device

    if(TokenAuth6238::verify($session_token,$currentcode)){
        $_SESSION['alert_message'] = "VALID!";
    }else{
        $_SESSION['alert_message'] = "IN-VALID!";
    } 

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_general_settings'])){

    $config_api_key = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_api_key'])));
    $old_aes_key = $config_aes_key;
    $config_aes_key = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_aes_key'])));
    $config_base_url = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_base_url'])));

    mysqli_query($mysqli,"UPDATE settings SET config_api_key = '$config_api_key', config_aes_key = '$config_aes_key', config_base_url = '$config_base_url' WHERE company_id = $session_company_id");

    //Update AES key on client_logins if changed
    if($old_aes_key != $config_aes_key){
        $sql = mysqli_query($mysqli,"SELECT login_id, AES_DECRYPT(login_password, '$old_aes_key') AS old_login_password FROM logins 
          WHERE company_id = $session_company_id");

        while($row = mysqli_fetch_array($sql)){
            $login_id = $row['login_id'];
            $old_login_password = $row['old_login_password'];
          
            mysqli_query($mysqli,"UPDATE logins SET login_password = AES_ENCRYPT('$old_login_password','$config_aes_key') WHERE login_id = $login_id");
        }
    }

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = 'General', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Settings updated";

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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = 'Mail', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Mail Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['test_email'])){
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));

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
        $mail->addAddress("$email");     // Add a recipient

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        
        $mail->Subject = "Hi'ya there Chap";
        $mail->Body    = "Hello there Chap ;) Don't worry this won't hurt a bit, it's just a test. ${$email}";

        
        $mail->send();
        echo 'Message has been sent';

        $_SESSION['alert_message'] = "Test Email has been sent!";

        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } catch (Exception $e) {
        echo "poop";
    }
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = 'Invoice', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice / Quote Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_ticket_settings'])){

    $config_ticket_prefix = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_ticket_prefix'])));
    $config_ticket_next_number = intval($_POST['config_ticket_next_number']);

    mysqli_query($mysqli,"UPDATE settings SET config_ticket_prefix = '$config_ticket_prefix', config_ticket_next_number = $config_ticket_next_number WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = 'Ticket', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_default_settings'])){

    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $expense_account = intval($_POST['expense_account']);
    $payment_account = intval($_POST['payment_account']);
    $payment_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['payment_method'])));
    $expense_payment_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expense_payment_method'])));
    $transfer_from_account = intval($_POST['transfer_from_account']);
    $transfer_to_account = intval($_POST['transfer_to_account']);
    $calendar = intval($_POST['calendar']);
    $net_terms = intval($_POST['net_terms']);

    mysqli_query($mysqli,"UPDATE settings SET config_default_country = '$country', config_default_currency = '$currency_code', config_default_expense_account = $expense_account, config_default_payment_account = $payment_account, config_default_payment_method = '$payment_method', config_default_expense_payment_method = '$expense_payment_method', config_default_transfer_from_account = $transfer_from_account, config_default_transfer_to_account = $transfer_to_account, config_default_calendar = $calendar, config_default_net_terms = $net_terms WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = 'Defaults', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Default Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['edit_alert_settings'])){

    $config_enable_cron = intval($_POST['config_enable_cron']);
    $config_enable_alert_domain_expire = intval($_POST['config_enable_alert_domain_expire']);
    $config_enable_alert_low_balance = intval($_POST['config_enable_alert_low_balance']);
    $config_send_invoice_reminders = intval($_POST['config_send_invoice_reminders']);
    $config_invoice_overdue_reminders = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_invoice_overdue_reminders']));
    $config_account_balance_threshold = preg_replace("/[^0-9]/", '',$_POST['config_account_balance_threshold']);

    mysqli_query($mysqli,"UPDATE settings SET config_send_invoice_reminders = $config_send_invoice_reminders, config_invoice_overdue_reminders = '$config_invoice_overdue_reminders', config_enable_cron = $config_enable_cron, config_enable_alert_domain_expire = $config_enable_alert_domain_expire, config_enable_alert_low_balance = $config_enable_alert_low_balance, config_account_balance_threshold = '$config_account_balance_threshold' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = 'Alerts', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Alert Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_online_payment_settings'])){

    $config_stripe_enable = intval($_POST['config_stripe_enable']);
    $config_stripe_publishable = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_stripe_publishable'])));
    $config_stripe_secret = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_stripe_secret'])));

    mysqli_query($mysqli,"UPDATE settings SET config_stripe_enable = $config_stripe_enable, config_stripe_publishable = '$config_stripe_publishable', config_stripe_secret = '$config_stripe_secret' WHERE company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modified', log_description = 'Online Payment', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Online Payment Settings Updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_POST['enable_2fa'])){

    $token = mysqli_real_escape_string($mysqli,$_POST['token']);

    mysqli_query($mysqli,"UPDATE users SET user_token = '$token' WHERE user_id = $session_user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Settings', log_action = 'Modified', log_description = '2FA Enabled', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Two Factor Authentication Enabled and Token Updated, don't lose your code you will need this additionally to login";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['disable_2fa'])){

    mysqli_query($mysqli,"UPDATE users SET user_token = '' WHERE user_id = $session_user_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'User Settings', log_action = 'Modified', log_description = '2FA Disabled', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Two Factor Authentication Disabled you can now login without TOTP Code";

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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Downloaded', log_description = 'Database', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");
}

if(isset($_POST['add_client'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $support = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['support'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $contact = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact'])));
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '',$_POST['extension']);
    $mobile = preg_replace("/[^0-9]/", '',$_POST['mobile']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $referral = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['referral'])));
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $net_terms = intval($_POST['net_terms']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_currency_code = '$currency_code', client_net_terms = $net_terms, client_support = '$support', client_notes = '$notes', client_created_at = NOW(), client_accessed_at = NOW(), company_id = $session_company_id");

    $client_id = mysqli_insert_id($mysqli);

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    //Log Add Client
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Created', log_description = '$name', log_created_at = NOW(), client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    //Add Location
    if(!empty($address) OR !empty($city) OR !empty($state) OR !empty($zip)){
        mysqli_query($mysqli,"INSERT INTO locations SET location_name = 'Primary', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_country = '$country', location_created_at = NOW(), location_client_id = $client_id, company_id = $session_company_id");
        
        //Update Primay location in clients
        $location_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Create', log_description = 'Pimary Location $address', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");
    }

    
    //Add Contact
    if(!empty($contact) OR !empty($title) OR !empty($phone) OR !empty($mobile) OR !empty($email)){
        mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$contact', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_photo = '$path', contact_notes = '$notes', contact_created_at = NOW(), contact_client_id = $client_id, company_id = $session_company_id");
        
        //Update Primay contact in clients
        $contact_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
    
        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Create', log_description = 'Primary Contact $contact', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    }

    $_SESSION['alert_message'] = "Client added";
    
    header("Location: clients.php");

}

if(isset($_POST['edit_client'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['type'])));
    $support = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['support'])));
    $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
    $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
    $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
    $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
    $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
    $contact = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact'])));
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '',$_POST['extension']);
    $mobile = preg_replace("/[^0-9]/", '',$_POST['mobile']);
    $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
    $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
    $referral = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['referral'])));
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $net_terms = intval($_POST['net_terms']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"UPDATE clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_currency_code = '$currency_code', client_net_terms = $net_terms, client_support = '$support', client_notes = '$notes', client_updated_at = NOW() WHERE client_id = $client_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Client $name updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client'])){
    $client_id = intval($_GET['delete_client']);

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
    
    $sql = mysqli_query($mysqli,"SELECT invoice_id FROM invoices WHERE invoice_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)){
        $invoice_id = $row['invoice_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Deleted', log_description = '$client_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Client deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_calendar'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $color = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['color'])));

    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = '$name', calendar_color = '$color', calendar_created_at = NOW(), company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Calendar created, now lets add some events!";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_event'])){

    $calendar_id = intval($_POST['calendar']);
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $start = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['start'])));
    $end = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['end'])));
    $repeat = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['repeat'])));
    $client = intval($_POST['client']);
    $email_event = intval($_POST['email_event']);

    mysqli_query($mysqli,"INSERT INTO events SET event_title = '$title', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_created_at = NOW(), event_calendar_id = $calendar_id, event_client_id = $client, company_id = $session_company_id");

    //If email is checked
    if($email_event == 1){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients JOIN companies ON clients.company_id = companies.company_id JOIN contacts ON primary_contact = contact_id WHERE client_id = $client AND companies.company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];
        $company_name = $row['company_name'];
        $company_country = $row['company_country'];
        $company_address = $row['company_address'];
        $company_city = $row['company_city'];
        $company_state = $row['company_state'];
        $company_zip = $row['company_zip'];
        $company_phone = $row['company_phone'];
        if(strlen($company_phone)>2){ 
          $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
        }
        $company_email = $row['company_email'];
        $company_website = $row['company_website'];
        $company_logo = $row['company_logo'];

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
            $mail->Subject = "New Calendar Event";
            $mail->Body    = "Hello $contact_name,<br><br>A calendar event has been scheduled: $title at $start<br><br><br>~<br>$company_name<br>$company_phone";

            $mail->send();
            echo 'Message has been sent';

        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        //Logging of email sent
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar Event', log_action = 'Emailed', log_description = 'Emailed $client_name to email $client_email - $title', log_created_at = NOW(), log_client_id = $client, company_id = $session_company_id, log_user_id = $session_user_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar Event', log_action = 'Created', log_description = '$title', log_created_at = NOW(), log_client_id = $client, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Event added to the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_event'])){

    $event_id = intval($_POST['event_id']);
    $calendar_id = intval($_POST['calendar']);
    $title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['title'])));
    $start = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['start'])));
    $end = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['end'])));
    $repeat = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['repeat'])));
    $client = intval($_POST['client']);
    $email_event = intval($_POST['email_event']);

    mysqli_query($mysqli,"UPDATE events SET event_title = '$title', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_updated_at = NOW(), event_calendar_id = $calendar_id, event_client_id = $client WHERE event_id = $event_id AND company_id = $session_company_id");

    //If email is checked
    if($email_event == 1){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients JOIN companies ON clients.company_id = companies.company_id JOIN contacts ON primary_contact = contact_id WHERE client_id = $client AND companies.company_id = $session_company_id");
        $row = mysqli_fetch_array($sql);
        $contact_name = $row['contact_name'];
        $contact_email = $row['contact_email'];
        $company_name = $row['company_name'];
        $company_country = $row['company_country'];
        $company_address = $row['company_address'];
        $company_city = $row['company_city'];
        $company_state = $row['company_state'];
        $company_zip = $row['company_zip'];
        $company_phone = $row['company_phone'];
        if(strlen($company_phone)>2){ 
          $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
        }
        $company_email = $row['company_email'];
        $company_website = $row['company_website'];
        $company_logo = $row['company_logo'];

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
            $mail->Subject = "Calendar Event Rescheduled";
            $mail->Body    = "Hello $contact_name,<br><br>A calendar event has been rescheduled: $title at $start<br><br><br>~<br>$company_name<br>$company_phone";

            $mail->send();
            echo 'Message has been sent';

        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        //Logging of email sent
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar Event', log_action = 'Emailed', log_description = 'Emailed $client_name to email $client_email - $title', log_created_at = NOW(), log_client_id = $client, company_id = $session_company_id, log_user_id = $session_user_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar', log_action = 'Modified', log_description = '$title', log_created_at = NOW(), log_client_id = $client, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Event modified on the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_event'])){
    $event_id = intval($_GET['delete_event']);

    mysqli_query($mysqli,"DELETE FROM events WHERE event_id = $event_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Calendar', log_action = 'Deleted', log_description = '$event_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Event deleted on the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor added";
    
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_vendor'])){
    $vendor_id = intval($_GET['archive_vendor']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Archived', log_description = '$vendor_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Vendor Archived!";
    
    header("Location: vendors.php");

}

if(isset($_GET['delete_vendor'])){
    $vendor_id = intval($_GET['delete_vendor']);

    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_id = $vendor_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Deleted', log_description = '$vendor_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor deleted";
    
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
    exit;
  
}

if(isset($_POST['add_product'])){

    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $cost = floatval($_POST['cost']);
    $category = intval($_POST['category']);
    $tax = intval($_POST['tax']);

    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_description = '$description', product_cost = '$cost', product_currency_code = '$config_default_currency', product_created_at = NOW(), product_tax_id = $tax, product_category_id = $category, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Product added";
    
    header("Location: products.php");

}

if(isset($_POST['edit_product'])){

    $product_id = intval($_POST['product_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $description = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['description'])));
    $cost = floatval($_POST['cost']);
    $category = intval($_POST['category']);
    $tax = intval($_POST['tax']);

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_cost = '$cost', product_updated_at = NOW(), product_tax_id = $tax, product_category_id = $category WHERE product_id = $product_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Product modified";
    
    header("Location: products.php");

}

if(isset($_GET['delete_product'])){
    $product_id = intval($_GET['delete_product']);

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Deleted', log_description = '$product_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Product deleted";
    
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

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Trip', log_action = 'Created', log_description = '$date', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

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
    
    header("Location: categories.php");

}

if(isset($_GET['archive_category'])){
    $category_id = intval($_GET['archive_category']);

    mysqli_query($mysqli,"UPDATE categories SET category_archived_at = NOW() WHERE category_id = $category_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Archive', log_description = '$category_id', log_created_at = NOW()");

    $_SESSION['alert_message'] = "Category Archived";
    
    header("Location: categories.php");

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

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/expenses/$session_company_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', expense_currency_code = '$config_default_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference', expense_receipt = '$path', expense_created_at = NOW(), company_id = $session_company_id");

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
    $path = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['expense_receipt'])));

    if($_FILES['file']['tmp_name']!='') {
        //remove old receipt
        unlink($path);
        $path = "uploads/expenses/$session_company_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', expense_account_id = $account, expense_vendor_id = $vendor, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference', expense_receipt = '$path', expense_updated_at = NOW() WHERE expense_id = $expense_id AND company_id = $session_company_id");

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

    unlink($expense_receipt);

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Deleted', log_description = '$epense_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Expense deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_transfer'])){

    $date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['date'])));
    $amount = floatval($_POST['amount']);
    $account_from = intval($_POST['account_from']);
    $account_to = intval($_POST['account_to']);
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', expense_currency_code = '$config_default_currency', expense_vendor_id = 0, expense_category_id = 0, expense_account_id = $account_from, expense_created_at = NOW(), company_id = $session_company_id");
    $expense_id = mysqli_insert_id($mysqli);
    
    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = '$amount', revenue_currency_code = '$config_default_currency', revenue_account_id = $account_to, revenue_category_id = 0, revenue_created_at = NOW(), company_id = $session_company_id");
    $revenue_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO transfers SET expense_id = $expense_id, revenue_id = $revenue_id, transfer_notes = '$notes', transfer_created_at = NOW(), company_id = $session_company_id");

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
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
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

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$currency_code', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_created_at = NOW(), invoice_client_id = $client, company_id = $session_company_id");
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
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));

    mysqli_query($mysqli,"UPDATE invoices SET invoice_scope = '$scope', invoice_date = '$date', invoice_due = '$due', invoice_currency_code = '$currency_code', invoice_updated_at = NOW(), invoice_category_id = $category WHERE invoice_id = $invoice_id AND company_id = $session_company_id");

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

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_currency_code = '$currency_code', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_created_at = NOW(), quote_client_id = $client, company_id = $session_company_id");

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
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));

     mysqli_query($mysqli,"UPDATE quotes SET quote_scope = '$scope', quote_date = '$date', quote_currency_code = '$currency_code', quote_category_id = $category, quote_updated_at = NOW() WHERE quote_id = $quote_id AND company_id = $session_company_id");

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
    $client_id = $row['client_id'];
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
    $client_website = $row['client_website'];
    $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    $company_name = $row['company_name'];
    $company_country = $row['company_country'];
    $company_address = $row['company_address'];
    $company_city = $row['company_city'];
    $company_state = $row['company_state'];
    $company_zip = $row['company_zip'];
    $company_phone = $row['company_phone'];
    if(strlen($company_phone)>2){ 
      $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
    }
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
        $mail->Body    = "Hello $contact_name,<br><br>Thank you for your inquiry, we are pleased to provide you with the following estimate.<br><br><br>Total Cost: $$quote_amount<br><br><br>View and accept your estimate online <a href='https://$base_url/guest_view_quote.php?quote_id=$quote_id&url_key=$quote_url_key'>here</a><br><br><br>~<br>$company_name<br>$company_phone";
        
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

        header("Location: " . $_SERVER["HTTP_REFERER"]);


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

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_prefix = '$config_recurring_prefix', recurring_number = $recurring_number, recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_next_date = '$start_date', recurring_category_id = $category, recurring_status = 1, recurring_currency_code = '$currency_code', recurring_created_at = NOW(), recurring_client_id = $client, company_id = $session_company_id");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_description = 'Recurring Invoice created!', history_created_at = NOW(), history_recurring_id = $recurring_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Created', log_description = '$start_date - $category', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Invoice added";
    
    header("Location: recurring_invoice.php?recurring_id=$recurring_id");

}

if(isset($_POST['edit_recurring'])){

    $recurring_id = intval($_POST['recurring_id']);
    $frequency = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency'])));
    $category = intval($_POST['category']);
    $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));
    $scope = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['scope'])));
    $status = intval($_POST['status']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_category_id = $category, recurring_status = $status, recurring_currency_code = '$currency_code', recurring_updated_at = NOW() WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_description = 'Recurring modified', history_created_at = NOW(), history_recurring_id = $recurring_id, company_id = $session_company_id");

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

if(isset($_GET['recurring_activate'])){

    $recurring_id = intval($_GET['recurring_activate']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_status = 1 WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Modified', log_description = 'Activated', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Invoice Activated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['recurring_deactivate'])){

    $recurring_id = intval($_GET['recurring_deactivate']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_status = 0 WHERE recurring_id = $recurring_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring', log_action = 'Modified', log_description = 'Deactivated', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Invoice Deactivated";
    
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
    $invoice_id = $row['invoice_id'];
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
        $company_phone = $row['company_phone'];
        if(strlen($company_phone)>2){ 
          $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
        }
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
                  $mail->Body    = "Hello $contact_name,<br><br>We have recieved your payment in the amount of $$formatted_amount for invoice <a href='https://$base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: $$formatted_amount<br>Balance: $$formatted_invoice_balance<br><br>Thank you for your business!<br><br><br>~<br>$company_name<br>$company_phone";

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
                  $mail->Body    = "Hello $contact_name,<br><br>We have recieved partial payment in the amount of $$formatted_amount and it has been applied to invoice <a href='https://$base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: $$formatted_amount<br>Balance: $$formatted_invoice_balance<br><br>Thank you for your business!<br><br><br>~<br>$company_name<br>$company_phone";

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
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
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
    $client_website = $row['client_website'];
    $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    $company_name = $row['company_name'];
    $company_country = $row['company_country'];
    $company_address = $row['company_address'];
    $company_city = $row['company_city'];
    $company_state = $row['company_state'];
    $company_zip = $row['company_zip'];
    $company_phone = $row['company_phone'];
    if(strlen($company_phone)>2){ 
      $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
    }
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
            $mail->Body    = "Hello $contact_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: $$invoice_amount<br>Balance Due: $$balance<br>Due Date: $invoice_due<br><br><br>To view your invoice online click <a href='https://$base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>$company_phone";
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

        header("Location: " . $_SERVER["HTTP_REFERER"]);


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

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/clients/$session_company_id/$client_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_photo = '$path', contact_notes = '$notes', contact_created_at = NOW(), contact_client_id = $client_id, company_id = $session_company_id");


    //Update Primay contact in clients if primary contact is checked
    if($primary_contact > 0){
        $contact_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Create', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact added";
    
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

    $path = strip_tags(mysqli_real_escape_string($mysqli,$_POST['current_avatar_path']));

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/clients/$session_company_id/$client_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_photo = '$path', contact_notes = '$notes', contact_updated_at = NOW() WHERE contact_id = $contact_id AND company_id = $session_company_id");

    //Update Primay contact in clients if primary contact is checked
    if($primary_contact > 0){
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact updated";
    
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

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/clients/$session_company_id/$client_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_photo = '$path', location_notes = '$notes', location_contact_id = $contact, location_created_at = NOW(), location_client_id = $client_id, company_id = $session_company_id");

    //Update Primay location in clients if primary location is checked
    if($primary_location > 0){
        $location_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Created', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Location added";
    
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

    $path = strip_tags(mysqli_real_escape_string($mysqli,$_POST['current_file_path']));

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/clients/$session_company_id/$client_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"UPDATE locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_photo = '$path', location_notes = '$notes', location_contact_id = $contact, location_updated_at = NOW() WHERE location_id = $location_id AND company_id = $session_company_id");

    //Update Primay location in clients if primary location is checked
    if($primary_location > 0){
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Location updated";
    
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
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_notes = '$notes', asset_created_at = NOW(), asset_network_id = $network, asset_client_id = $client_id, company_id = $session_company_id");

    if(!empty($_POST['username'])) {
        $asset_id = mysqli_insert_id($mysqli);
        $username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['username'])));
        $password = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_created_at = NOW(), login_asset_id = $asset_id, login_client_id = $client_id, company_id = $session_company_id");

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
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['notes'])));
    $username = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['username'])));
    $password = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['password'])));

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_notes = '$notes', asset_updated_at = NOW(), asset_network_id = $network WHERE asset_id = $asset_id AND company_id = $session_company_id");

    //If login exists then update the login
    if($login_id > 0){
        mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_updated_at = NOW() WHERE login_id = $login_id AND company_id = $session_company_id");
    }else{
    //If Username is filled in then add a login
        if(!empty($username)) {
            
            mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_created_at = NOW(), login_asset_id = $asset_id, login_client_id = $client_id, company_id = $session_company_id");

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

if(isset($_GET['export_client_assets_csv'])){
    $client_id = intval($_GET['export_client_assets_csv']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    
    $sql = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id ORDER BY asset_name ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Assets-" . date('Y-m-d') . ".csv";
        
        //create a file pointer
        $f = fopen('php://memory', 'w');
        
        //set column headers
        $fields = array('Name', 'Type', 'Make', 'Model', 'Serial Number', 'MAC Address', 'IP Address', 'Operating System', 'Purchase Date', 'Warranty Expiration Date', 'Notes');
        fputcsv($f, $fields, $delimiter);
        
        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['asset_name'], $row['asset_type'], $row['asset_make'], $row['asset_model'], $row['asset_serial'], $row['asset_mac'], $row['asset_ip'], $row['asset_os'], $row['asset_purchase_date'], $row['asset_warranty_expire'], $row['asset_notes']);
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
        $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_software_id = $software_id, login_created_at = NOW(), login_client_id = $client_id, company_id = $session_company_id");

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
    $password = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['password'])));

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_type = '$type', software_license = '$license', software_notes = '$notes', software_updated_at = NOW() WHERE software_id = $software_id AND company_id = $session_company_id");

    //If login exists then update the login
    if($login_id > 0){
        mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_updated_at = NOW() WHERE login_id = $login_id AND company_id = $session_company_id");
    }else{
    //If Username is filled in then add a login
        if(!empty($username)) {
            
            mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_created_at = NOW(), login_software_id = $software_id, login_client_id = $client_id, company_id = $session_company_id");

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
    $password = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['password'])));
    $otp_secret = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['otp_secret'])));
    $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $software_id = intval($_POST['software']);

    mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_uri = '$uri', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_otp_secret = '$otp_secret', login_note = '$note', login_created_at = NOW(), login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id, login_client_id = $client_id, company_id = $session_company_id");

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
    $password = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['password'])));
    $otp_secret = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['otp_secret'])));
    $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['note'])));
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $software_id = intval($_POST['software']);

    mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_uri = '$uri', login_username = '$username', login_password = AES_ENCRYPT('$password','$config_aes_key'), login_otp_secret = '$otp_secret', login_note = '$note', login_updated_at = NOW(), login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id WHERE login_id = $login_id AND company_id = $session_company_id");

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
    
    $sql = mysqli_query($mysqli,"SELECT *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins WHERE login_client_id = $client_id ORDER BY login_name ASC");
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
            $lineData = array($row['login_name'], $row['login_username'], $row['login_password'], $row['login_uri'], $row['login_note']);
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
    if(empty($expire)){
        $expire = "0000-00-00";
    }

    mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_created_at = NOW(), certificate_client_id = $client_id, company_id = $session_company_id");

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
    if(empty($expire)){
        $expire = "0000-00-00";
    }

    mysqli_query($mysqli,"UPDATE certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_updated_at = NOW() WHERE certificate_id = $certificate_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Certificate updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    $client_id = intval($_POST['client']);
    $assigned_to = intval($_POST['assigned_to']);
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $priority = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['priority'])));
    $details = trim(mysqli_real_escape_string($mysqli,$_POST['details']));

    //Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = $session_company_id");

    mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 'Open', ticket_created_at = NOW(), ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = '$subject', log_created_at = NOW(), client_id = $client_id, company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket created";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_ticket'])){

    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = intval($_POST['assigned_to']);
    $contact_id = intval($_POST['contact']);
    $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
    $priority = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['priority'])));
    $details = trim(mysqli_real_escape_string($mysqli,$_POST['details']));

    mysqli_query($mysqli,"UPDATE tickets SET ticket_subject = '$subject', ticket_priority = '$priority', ticket_details = '$details', ticket_updated_at = NOW(), ticket_assigned_to = $assigned_to, ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modified', log_description = '$subject', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_ticket'])){
    $ticket_id = intval($_GET['delete_ticket']);

    mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Deleted', log_description = '$ticket_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_ticket_reply'])){

    $ticket_id = intval($_POST['ticket_id']);
    $ticket_reply = trim(mysqli_real_escape_string($mysqli,$_POST['ticket_reply']));

    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_created_at = NOW(), ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id, company_id = $session_company_id") or die(mysqli_error($mysqli));

    //UPDATE Ticket Last Response Field 
    mysqli_query($mysqli,"UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

    //Logging
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

if(isset($_GET['close_ticket'])){

    $ticket_id = intval($_GET['close_ticket']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Closed', ticket_updated_at = NOW(), ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id AND company_id = $session_company_id") or die(mysqli_error($mysqli));

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

if(isset($_POST['add_file'])){
    $client_id = intval($_POST['client_id']);
    $new_name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['new_name'])));

    if(!file_exists("uploads/clients/$session_company_id/$client_id")) {
        mkdir("uploads/clients/$session_company_id/$client_id");
    }

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/clients/$session_company_id/$client_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
        $ext = pathinfo($path);
        $ext = $ext['extension'];

    }

    mysqli_query($mysqli,"INSERT INTO files SET file_name = '$path', file_ext = '$ext', file_created_at = NOW(), file_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Uploaded', log_description = '$path', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "File uploaded";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_file'])){
    $file_id = intval($_GET['delete_file']);

    $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id AND company_id = $session_company_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = $row['file_name'];

    unlink($file_name);

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Deleted', log_description = '$file_name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "File deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_document'])){

    $client_id = intval($_POST['client_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $details = trim(mysqli_real_escape_string($mysqli,$_POST['details']));

    mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_details = '$details', document_created_at = NOW(), document_client_id = $client_id, company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Created', log_description = '$details', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Document added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_document'])){

    $document_id = intval($_POST['document_id']);
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
    $details = trim(mysqli_real_escape_string($mysqli,$_POST['details']));

    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name', document_details = '$details', document_updated_at = NOW() WHERE document_id = $document_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Note', log_action = 'Modified', log_description = '$name', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Document updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_document'])){
    $document_id = intval($_GET['delete_document']);

    mysqli_query($mysqli,"DELETE FROM documents WHERE document_id = $document_id AND company_id = $session_company_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Deleted', log_description = '$document_id', log_created_at = NOW(), company_id = $session_company_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Document deleted";
    
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
        $tax_id = $row['tax_id'];

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
        sql = mysqli_query($mysqli,"SELECT * FROM invoices
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
        $contact_phone = $row['contact_phone'];
        if(strlen($contact_phone)>2){ 
            $contact_phone = substr($row['contact_phone'],0,3)."-".substr($row['contact_phone'],3,3)."-".substr($row['contact_phone'],6,4);
        }
        $contact_extension = $row['contact_extension'];
        $contact_mobile = $row['contact_mobile'];
        if(strlen($contact_mobile)>2){ 
            $contact_mobile = substr($row['contact_mobile'],0,3)."-".substr($row['contact_mobile'],3,3)."-".substr($row['contact_mobile'],6,4);
        }
        $company_id = $row['company_id'];
        $company_name = $row['company_name'];
        $company_phone = $row['company_phone'];
        if(strlen($company_phone)>2){ 
            $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
        }
        $company_email = $row['company_email'];
        $company_website = $row['company_website'];
        $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

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

if(isset($_GET['export_trips_csv'])){
    //get records from database
    $query = mysqli_query($mysqli,"SELECT * FROM trips WHERE company_id = $session_company_id ORDER BY trip_date DESC");

    if($query->num_rows > 0){
        $delimiter = ",";
        $filename = "trips_" . date('Y-m-d') . ".csv";
        
        //create a file pointer
        $f = fopen('php://memory', 'w');
        
        //set column headers
        $fields = array('Date', 'Purpose', 'Source', 'Destination', 'Miles');
        fputcsv($f, $fields, $delimiter);
        
        //output each row of the data, format line as csv and write to file pointer
        while($row = $query->fetch_assoc()){
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
    $contact_phone = $row['contact_phone'];
    $contact_email = $row['contact_email'];
    $client_website = $row['client_website'];

    $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
    $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_client_id = $client_id ORDER BY location_name ASC");
    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id ORDER BY vendor_name ASC");
    $sql_logins = mysqli_query($mysqli,"SELECT *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins WHERE login_client_id = $client_id ORDER BY login_name ASC");
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
            author: <?php echo json_encode($company_name); ?>
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
                            $contact_phone = $row['contact_phone'];
                            if(strlen($contact_phone)>2){ 
                              $contact_phone = substr($row['contact_phone'],0,3)."-".substr($row['contact_phone'],3,3)."-".substr($row['contact_phone'],6,4);
                            }
                            $contact_extension = $row['contact_extension'];
                            if(!empty($contact_extension)){
                              $contact_extension = "x$contact_extension";
                            }
                            $contact_mobile = $row['contact_mobile'];
                            if(strlen($contact_mobile)>2){ 
                              $contact_mobile = substr($row['contact_mobile'],0,3)."-".substr($row['contact_mobile'],3,3)."-".substr($row['contact_mobile'],6,4);
                            }
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
                          $location_phone = $row['location_phone'];
                          if(strlen($location_phone)>2){ 
                            $location_phone = substr($row['location_phone'],0,3)."-".substr($row['location_phone'],3,3)."-".substr($row['location_phone'],6,4);
                          }
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
                          $vendor_phone = $row['vendor_phone'];
                          if(strlen($vendor_phone)>2){ 
                            $vendor_phone = substr($row['vendor_phone'],0,3)."-".substr($row['vendor_phone'],3,3)."-".substr($row['vendor_phone'],6,4);
                          }
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
                            $login_password = $row['login_password'];
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
                                text: 'OS', 
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
                                text: 'MAC', 
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
                                text: 'Notes', 
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
                                text: <?php echo json_encode($asset_os); ?>,
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
                                text: <?php echo json_encode($asset_mac); ?>,
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
                                text: <?php echo json_encode($asset_notes); ?>,
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
