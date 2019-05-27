<?php

include("config.php");
include("check_login.php");
include("functions.php");

require("vendor/PHPMailer-6.0.7/src/PHPMailer.php");
require("vendor/PHPMailer-6.0.7/src/SMTP.php");

$mpdf_path = (getenv('MPDF_ROOT')) ? getenv('MPDF_ROOT') : __DIR__;
require_once $mpdf_path . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$todays_date = date('Y-m-d');

if(isset($_POST['edit_general_settings'])){

    $config_start_page = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_start_page']));
    $config_account_balance_threshold = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_account_balance_threshold']));

    mysqli_query($mysqli,"UPDATE settings SET config_start_page = '$config_start_page', config_account_balance_threshold = '$config_account_balance_threshold'");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_company_settings'])){

    $config_company_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_name']));
    $config_company_address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_address']));
    $config_company_city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_city']));
    $config_company_state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_state']));
    $config_company_zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_zip']));
    $config_company_phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_phone']));
    $config_company_site = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_company_site']));
   


    mysqli_query($mysqli,"UPDATE settings SET config_company_name = '$config_company_name', config_company_address = '$config_company_address', config_company_city = '$config_company_city', config_company_state = '$config_company_state', config_company_zip = '$config_company_zip', config_company_phone = '$config_company_phone', config_company_site = '$config_company_site'");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_mail_settings'])){

    $config_smtp_host = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_smtp_host']));
    $config_smtp_port = intval($_POST['config_smtp_port']);
    $config_smtp_username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_smtp_username']));
    $config_smtp_password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_smtp_password']));

    mysqli_query($mysqli,"UPDATE settings SET config_smtp_host = '$config_smtp_host', config_smtp_port = $config_smtp_port, config_smtp_username = '$config_smtp_username', config_smtp_password = '$config_smtp_password'");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_invoice_settings'])){

    $config_next_invoice_number = intval($_POST['config_next_invoice_number']);
    $config_mail_from_email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_mail_from_email']));
    $config_mail_from_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_mail_from_name']));
    $config_invoice_footer = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_invoice_footer']));
    $config_quote_footer = strip_tags(mysqli_real_escape_string($mysqli,$_POST['config_quote_footer']));

    mysqli_query($mysqli,"UPDATE settings SET config_next_invoice_number = '$config_next_invoice_number', config_mail_from_email = '$config_mail_from_email', config_mail_from_name = '$config_mail_from_name', config_invoice_footer = '$config_invoice_footer', config_quote_footer = '$config_quote_footer'");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_logo_settings'])){

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/settings";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
        $ext = pathinfo($path);
        $ext = $ext['extension'];

        mysqli_query($mysqli,"UPDATE settings SET config_invoice_logo = '$path'");

    } 

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

}

if(isset($_POST['add_user'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $password = md5(mysqli_real_escape_string($mysqli,$_POST['password']));
    $client_id = intval($_POST['client']);

    mysqli_query($mysqli,"INSERT INTO users SET name = '$name', email = '$email', password = '$password', created_at = NOW(), client_id = $client_id");

    $user_id = mysqli_insert_id($mysqli);

    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if($check !== false) {
        $avatar_path = "uploads/users/";
        //$avatar_path = $avatar_path . $user_id . '_' . time() . '_' . basename( $_FILES['avatar']['name']);
        $avatar_path = $avatar_path . basename( $_FILES['file']['name']);
        move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path);
    }

    mysqli_query($mysqli,"UPDATE users SET avatar = '$avatar_path' WHERE user_id = $user_id");

    $_SESSION['alert_message'] = "User added";
    
    header("Location: users.php");

}

if(isset($_POST['edit_user'])){

    $user_id = intval($_POST['user_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $current_password_hash = mysqli_real_escape_string($mysqli,$_POST['current_password_hash']);
    $password = mysqli_real_escape_string($mysqli,$_POST['password']);
    if($current_password_hash == $password){
        $password = $current_password_hash;
    }else{
        $password = md5($password);
    }
    $path = strip_tags(mysqli_real_escape_string($mysqli,$_POST['current_avatar_path']));

    if($_FILES['file']['tmp_name']!='') {
        //delete old avatar file
        unlink($path);
        //Update with new path
        $path = "uploads/users/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);   
    }
    
    mysqli_query($mysqli,"UPDATE users SET name = '$name', email = '$email', password = '$password', avatar = '$path', updated_at = NOW() WHERE user_id = $user_id");

    $_SESSION['alert_message'] = "User updated";
    
    header("Location: users.php");

}

if(isset($_POST['add_client'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));
    $net_terms = intval($_POST['net_terms']);

    mysqli_query($mysqli,"INSERT INTO clients SET client_name = '$name', client_type = '$type', client_address = '$address', client_city = '$city', client_state = '$state', client_zip = '$zip', client_phone = '$phone', client_email = '$email', client_website = '$website', client_net_terms = $net_terms, client_created_at = NOW()");

    $client_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = 'Main Contact', contact_phone = '$phone', contact_email = '$email', contact_created_at = NOW(), client_id = $client_id");

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = 'Main', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_created_at = NOW(), client_id = $client_id");

    if(!empty($_POST['website'])) {
        mysqli_query($mysqli,"INSERT INTO domains SET domain_name = '$website', domain_created_at = NOW(), client_id = $client_id");
    }

    mkdir("uploads/clients/$client_id");

    $_SESSION['alert_message'] = "Client added";
    
    header("Location: clients.php");

}

if(isset($_POST['edit_client'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));
    $net_terms = intval($_POST['net_terms']);

    mysqli_query($mysqli,"UPDATE clients SET client_name = '$name', client_type = '$type', client_address = '$address', client_city = '$city', client_state = '$state', client_zip = '$zip', client_phone = '$phone', client_email = '$email', client_website = '$website', client_net_terms = $net_terms, client_updated_at = NOW() WHERE client_id = $client_id");

    $_SESSION['alert_message'] = "Client updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_client'])){
    $client_id = intval($_GET['delete_client']);

    mysqli_query($mysqli,"DELETE FROM clients WHERE client_id = $client_id");

    $_SESSION['alert_message'] = "Client deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_calendar_event'])){

    $calendar_id = intval($_POST['calendar']);
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $start = strip_tags(mysqli_real_escape_string($mysqli,$_POST['start']));
    $end = strip_tags(mysqli_real_escape_string($mysqli,$_POST['end']));

    mysqli_query($mysqli,"INSERT INTO calendar_events SET calendar_event_title = '$title', calendar_event_start = '$start', calendar_event_end = '$end', calendar_id = $calendar_id");

    $_SESSION['alert_message'] = "Event added to the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_calendar_event'])){

    $calendar_event_id = intval($_POST['calendar_event_id']);
    $calendar_id = intval($_POST['calendar']);
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $start = strip_tags(mysqli_real_escape_string($mysqli,$_POST['start']));
    $end = strip_tags(mysqli_real_escape_string($mysqli,$_POST['end']));

    mysqli_query($mysqli,"UPDATE calendar_events SET calendar_event_title = '$title', calendar_event_start = '$start', calendar_event_end = '$end', calendar_id = $calendar_id WHERE calendar_event_id = $calendar_event_id");

    $_SESSION['alert_message'] = "Event modified on the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_calendar_event'])){
    $calendar_event_id = intval($_GET['delete_calendar_event']);

    mysqli_query($mysqli,"DELETE FROM calendar_events WHERE calendar_event_id = $calendar_event_id");

    $_SESSION['alert_message'] = "Event deleted on the calendar";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_ticket'])){

    $client_id = intval($_POST['client']);
    $subject = strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject']));
    $details = strip_tags(mysqli_real_escape_string($mysqli,$_POST['details']));

    mysqli_query($mysqli,"INSERT INTO tickets SET ticket_subject = '$subject', ticket_details = '$details', ticket_status = 'Open', ticket_created_at = NOW(), client_id = $client_id");

    $_SESSION['alert_message'] = "Ticket created";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_ticket'])){

    $ticket_id = intval($_POST['ticket_id']);
    $subject = strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject']));
    $details = strip_tags(mysqli_real_escape_string($mysqli,$_POST['details']));

    mysqli_query($mysqli,"UPDATE tickets SET ticket_subject = '$subject', ticket_details = '$details' ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_vendor'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $account_number = strip_tags(mysqli_real_escape_string($mysqli,$_POST['account_number']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));
    
    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_address = '$address', vendor_city = '$city', vendor_state = '$state', vendor_zip = '$zip', vendor_phone = '$phone', vendor_email = '$email', vendor_website = '$website', vendor_account_number = '$account_number', vendor_created_at = NOW(), client_id = $client_id");

    $vendor_id = mysqli_insert_id($mysqli);

    $_SESSION['alert_message'] = "Vendor added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_vendor'])){

    $vendor_id = intval($_POST['vendor_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $account_number = strip_tags(mysqli_real_escape_string($mysqli,$_POST['account_number']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));
    $website = strip_tags(mysqli_real_escape_string($mysqli,$_POST['website']));

    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_address = '$address', vendor_city = '$city', vendor_state = '$state', vendor_zip = '$zip', vendor_phone = '$phone', vendor_email = '$email', vendor_website = '$website', vendor_account_number = '$account_number', vendor_updated_at = NOW() WHERE vendor_id = $vendor_id");

    $_SESSION['alert_message'] = "Vendor modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_vendor'])){
    $vendor_id = intval($_GET['delete_vendor']);

    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_id = $vendor_id");

    $_SESSION['alert_message'] = "Vendor deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_product'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $cost = strip_tags(mysqli_real_escape_string($mysqli,$_POST['cost']));

    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_description = '$description', product_cost = '$cost', product_created_at = NOW()");

    $_SESSION['alert_message'] = "Product added";
    
    header("Location: products.php");

}

if(isset($_POST['edit_product'])){

    $product_id = intval($_POST['product_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $cost = strip_tags(mysqli_real_escape_string($mysqli,$_POST['cost']));

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_cost = '$cost', product_updated_at = NOW() WHERE product_id = $product_id");

    $_SESSION['alert_message'] = "Product modified";
    
    header("Location: products.php");

}

if(isset($_GET['delete_product'])){
    $product_id = intval($_GET['delete_product']);

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id");

    $_SESSION['alert_message'] = "Product deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_trip'])){

    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $starting_location = strip_tags(mysqli_real_escape_string($mysqli,$_POST['starting_location']));
    $destination = strip_tags(mysqli_real_escape_string($mysqli,$_POST['destination']));
    $miles = intval($_POST['miles']);
    $roundtrip = intval($_POST['roundtrip']);
    $purpose = strip_tags(mysqli_real_escape_string($mysqli,$_POST['purpose']));
    $client_id = intval($_POST['client']);
    $invoice_id = intval($_POST['invoice']);
    $location_id = intval($_POST['location']);
    $vendor_id = intval($_POST['vendor']);

    mysqli_query($mysqli,"INSERT INTO trips SET trip_date = '$date', trip_starting_location = '$starting_location', trip_destination = '$destination', trip_miles = $miles, trip_purpose = '$purpose', trip_created_at = NOW(), client_id = $client_id, invoice_id = $invoice_id, location_id = $location_id, vendor_id = $vendor_id");

    if($roundtrip == 1){
        mysqli_query($mysqli,"INSERT INTO trips SET trip_date = '$date', trip_starting_location = '$destination', trip_destination = '$starting_location', trip_miles = $miles, trip_purpose = '$purpose', trip_created_at = NOW(), client_id = $client_id, invoice_id = $invoice_id, location_id = $location_id, vendor_id = $vendor_id");
    }

    $_SESSION['alert_message'] = "Trip added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_trip'])){

    $trip_id = intval($_POST['trip_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $starting_location = strip_tags(mysqli_real_escape_string($mysqli,$_POST['starting_location']));
    $destination = strip_tags(mysqli_real_escape_string($mysqli,$_POST['destination']));
    $miles = intval($_POST['miles']);
    $purpose = strip_tags(mysqli_real_escape_string($mysqli,$_POST['purpose']));

    mysqli_query($mysqli,"UPDATE trips SET trip_date = '$date', trip_starting_location = '$starting_location', trip_destination = '$destination', trip_miles = $miles, trip_purpose = '$purpose' trip_updated_at = NOW() WHERE trip_id = $trip_id");

    $_SESSION['alert_message'] = "Trip modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_trip'])){
    $trip_id = intval($_GET['delete_trip']);

    mysqli_query($mysqli,"DELETE FROM trips WHERE trip_id = $trip_id");

    $_SESSION['alert_message'] = "Trip deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_account'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $opening_balance = $_POST['opening_balance'];

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = '$opening_balance', account_created_at = NOW()");

    $_SESSION['alert_message'] = "Account added";
    
    header("Location: accounts.php");

}

if(isset($_POST['edit_account'])){

    $account_id = intval($_POST['account_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name', account_updated_at = NOW() WHERE account_id = $account_id");

    $_SESSION['alert_message'] = "Account modified";
    
    header("Location: accounts.php");

}

if(isset($_GET['delete_account'])){
    $account_id = intval($_GET['delete_account']);

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id");

    $_SESSION['alert_message'] = "Account deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_category'])){

    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $color = strip_tags(mysqli_real_escape_string($mysqli,$_POST['color']));

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type', category_color = '$color', category_created_at = NOW()");

    $_SESSION['alert_message'] = "Category added";
    
    header("Location: categories.php");

}

if(isset($_POST['edit_category'])){

    $category_id = intval($_POST['category_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $color = strip_tags(mysqli_real_escape_string($mysqli,$_POST['color']));

    mysqli_query($mysqli,"UPDATE categories SET category_name = '$name', category_type = '$type', category_color = '$color', category_updated_at = NOW() WHERE category_id = $category_id");

    $_SESSION['alert_message'] = "Category modified";
    
    header("Location: categories.php");

}

if(isset($_GET['delete_category'])){
    $category_id = intval($_GET['delete_category']);

    mysqli_query($mysqli,"DELETE FROM categories WHERE category_id = $category_id");

    $_SESSION['alert_message'] = "Category deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['alert_ack'])){

    $alert_id = intval($_GET['alert_ack']);

    mysqli_query($mysqli,"UPDATE alerts SET alert_ack_date = CURDATE() WHERE alert_id = $alert_id");

    $_SESSION['alert_message'] = "Alert Acknowledged";
    
    header("Location: alerts.php");

}

if(isset($_GET['ack_all_alerts'])){

    $sql = mysqli_query($mysqli,"SELECT * FROM alerts ORDER BY alert_id DESC");
    
    while($row = mysqli_fetch_array($sql)){
        $alert_id = $row['alert_id'];
        $alert_ack_date = $row['alert_ack_date'];

        if($alert_ack_date = 0 ){
            mysqli_query($mysqli,"UPDATE alerts SET alert_ack_date = CURDATE() WHERE alert_id = $alert_id");
        }
    }
    
    $_SESSION['alert_message'] = "Alerts Acknowledged";
    
    header("Location: alerts.php");

}

if(isset($_POST['add_expense'])){

    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $category = intval($_POST['category']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $reference = strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference']));

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/expenses/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', account_id = $account, vendor_id = $vendor, category_id = $category, expense_description = '$description', expense_reference = '$reference', expense_receipt = '$path', expense_created_at = NOW()");

    $_SESSION['alert_message'] = "Expense added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_expense'])){

    $expense_id = intval($_POST['expense_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $category = intval($_POST['category']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $reference = strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference']));
    $path = strip_tags(mysqli_real_escape_string($mysqli,$_POST['expense_receipt']));

    if($_FILES['file']['tmp_name']!='') {
        //remove old receipt
        unlink($path);
        $path = "uploads/expenses/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', account_id = $account, vendor_id = $vendor, category_id = $category, expense_description = '$description', expense_reference = '$reference', expense_receipt = '$path', expense_updated_at = NOW() WHERE expense_id = $expense_id");

    $_SESSION['alert_message'] = "Expense modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_expense'])){
    $expense_id = intval($_GET['delete_expense']);

    $sql = mysqli_query($mysqli,"SELECT * FROM expenses WHERE expense_id = $expense_id");
    $row = mysqli_fetch_array($sql);
    $expense_receipt = $row['expense_receipt'];

    unlink($expense_receipt);

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    $_SESSION['alert_message'] = "Expense deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_transfer'])){

    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account_from = intval($_POST['account_from']);
    $account_to = intval($_POST['account_to']);

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = '$amount', vendor_id = 0, account_id = $account_from, expense_created_at = NOW()");
    $expense_id = mysqli_insert_id($mysqli);
    
    mysqli_query($mysqli,"INSERT INTO payments SET payment_date = '$date', payment_amount = '$amount', account_id = $account_to, invoice_id = 0, payment_created_at = NOW()");
    $payment_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO transfers SET transfer_date = '$date', transfer_amount = '$amount', transfer_account_from = $account_from, transfer_account_to = $account_to, expense_id = $expense_id, payment_id = $payment_id, transfer_created_at = NOW()");

    $_SESSION['alert_message'] = "Transfer added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_transfer'])){

    $transfer_id = intval($_POST['transfer_id']);
    $expense_id = intval($_POST['expense_id']);
    $payment_id = intval($_POST['payment_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account_from = intval($_POST['account_from']);
    $account_to = intval($_POST['account_to']);

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = '$amount', account_id = $account_from, expense_updated_at = NOW() WHERE expense_id = $expense_id");

    mysqli_query($mysqli,"UPDATE payments SET payment_date = '$date', payment_amount = '$amount', account_id = $account_to, payment_updated_at = NOW() WHERE payment_id = $payment_id");

    mysqli_query($mysqli,"UPDATE transfers SET transfer_date = '$date', transfer_amount = '$amount', transfer_account_from = $account_from, transfer_account_to = $account_to, transfer_updated_at = NOW() WHERE transfer_id = $transfer_id");

    $_SESSION['alert_message'] = "Transfer modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_transfer'])){
    $transfer_id = intval($_GET['delete_transfer']);

    //Query the transfer ID to get the Pyament and Expense IDs so we can delete those as well
    $sql = mysqli_query($mysqli,"SELECT * FROM transfers WHERE transfer_id = $transfer_id");
    $row = mysqli_fetch_array($sql);
    $expense_id = $row['expense_id'];
    $payment_id = $row['payment_id'];

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");

    mysqli_query($mysqli,"DELETE FROM transfers WHERE transfer_id = $transfer_id");

    $_SESSION['alert_message'] = "Transfer deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_invoice'])){
    $client = intval($_POST['client']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $client_net_terms = intval($_POST['client_net_terms']);
    $category = intval($_POST['category']);
    
    //Get the last Invoice Number and add 1 for the new invoice number
    $sql = mysqli_query($mysqli,"SELECT invoice_number FROM invoices ORDER BY invoice_number DESC LIMIT 1");
    $row = mysqli_fetch_array($sql);
    $invoice_number = $row['invoice_number'] + 1;
    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_number = $invoice_number, invoice_date = '$date', invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), category_id = $category, invoice_status = 'Draft', client_id = $client");
    $invoice_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'INVOICE added!', invoice_id = $invoice_id");
    $_SESSION['alert_message'] = "Invoice added";
    
    header("Location: invoice.php?invoice_id=$invoice_id");
}

if(isset($_POST['edit_invoice'])){

    $invoice_id = intval($_POST['invoice_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $due = strip_tags(mysqli_real_escape_string($mysqli,$_POST['due']));
    $category = intval($_POST['category']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_date = '$date', invoice_due = '$due', category_id = $category WHERE invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Invoice modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['add_invoice_copy'])){

    $invoice_id = intval($_POST['invoice_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $client_net_terms = intval($_POST['client_net_terms']);
    
    //Get the last Invoice Number and add 1 for the new invoice number
    $sql = mysqli_query($mysqli,"SELECT invoice_number FROM invoices ORDER BY invoice_number DESC LIMIT 1");
    $row = mysqli_fetch_array($sql);
    $invoice_number = $row['invoice_number'] + 1;

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_amount = $row['invoice_amount'];
    $invoice_note = $row['invoice_note'];
    $client_id = $row['client_id'];
    $category_id = $row['category_id'];

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_number = $invoice_number, invoice_date = '$date', invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), category_id = $category_id, invoice_status = 'Draft', invoice_amount = '$invoice_amount', invoice_note = '$invoice_note', client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'INVOICE added!', invoice_id = $new_invoice_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', invoice_id = $new_invoice_id");
    }

    $_SESSION['alert_message'] = "Invoice copied";
    
    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if(isset($_POST['add_invoice_recurring'])){

    $invoice_id = intval($_POST['invoice_id']);
    $recurring_frequency = strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency']));

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_date = $row['invoice_date'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_note = $row['invoice_note'];
    $client_id = $row['client_id'];
    $category_id = $row['category_id'];

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_frequency = '$recurring_frequency', recurring_next_date = DATE_ADD('$invoice_date', INTERVAL 1 $recurring_frequency), recurring_status = 1, recurring_amount = '$invoice_amount', recurring_note = '$invoice_note', recurring_created_at = NOW(), category_id = $category_id, client_id = $client_id");

    $recurring_id = mysqli_insert_id($mysqli);

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', recurring_id = $recurring_id");
    }

    $_SESSION['alert_message'] = "Created recurring Invoice from this Invoice";
    
    header("Location: recurring.php?recurring_id=$recurring_id");

}

if(isset($_POST['add_quote'])){

    $client = intval($_POST['client']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $category = intval($_POST['category']);
    
    //Get the last Invoice Number and add 1 for the new invoice number
    $sql = mysqli_query($mysqli,"SELECT quote_number FROM quotes ORDER BY quote_number DESC LIMIT 1");
    $row = mysqli_fetch_array($sql);
    $quote_number = $row['quote_number'] + 1;

    //Generate a unique URL key for clients to access
    $quote_url_key = keygen();


    mysqli_query($mysqli,"INSERT INTO quotes SET quote_number = $quote_number, quote_date = '$date', category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_created_at = NOW(), client_id = $client");

    $quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Quote created!', quote_id = $quote_id");

    $_SESSION['alert_message'] = "Quote added";
    
    header("Location: quote.php?quote_id=$quote_id");

}

if(isset($_POST['save_quote'])){

    $quote_id = intval($_POST['quote_id']);
    
    if(isset($_POST['name'])){
        $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
        $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
        $qty = $_POST['qty'];
        $price = $_POST['price'];
        $tax = $_POST['tax'];
        
        $subtotal = $price * $qty;
        $tax = $subtotal * $tax;
        $total = $subtotal + $tax;

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = '$price', item_subtotal = '$subtotal', item_tax = '$tax', item_total = '$total', quote_id = $quote_id");

        //Update Invoice Balances

        $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
        $row = mysqli_fetch_array($sql);

        $new_quote_amount = $row['quote_amount'] + $total;

        mysqli_query($mysqli,"UPDATE quotes SET quote_amount = '$new_quote_amount' WHERE quote_id = $quote_id");

        $_SESSION['alert_message'] = "Item added";

    }


    if(isset($_POST['quote_note'])){
        $quote_note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['quote_note']));

        mysqli_query($mysqli,"UPDATE quotes SET quote_note = '$quote_note' WHERE quote_id = $quote_id");

        $_SESSION['alert_message'] = "Notes added";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_quote'])){

    $quote_id = intval($_POST['quote_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $category = intval($_POST['category']);

     mysqli_query($mysqli,"UPDATE quotes SET quote_date = '$date', category_id = $category, quote_updated_at = NOW() WHERE quote_id = $quote_id");

    $_SESSION['alert_message'] = "Quote modified";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_quote'])){
    $quote_id = intval($_GET['delete_quote']);

    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_id = $quote_id");

    //Delete Items Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = $row['item_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = $row['history_id'];
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    $_SESSION['alert_message'] = "Quotes deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_quote_copy'])){

    $quote_id = intval($_POST['quote_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    
    //Get the last Invoice Number and add 1 for the new invoice number
    $sql = mysqli_query($mysqli,"SELECT quote_number FROM quotes ORDER BY quote_number DESC LIMIT 1");
    $row = mysqli_fetch_array($sql);
    $quote_number = $row['quote_number'] + 1;

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = $row['quote_amount'];
    $quote_note = $row['quote_note'];
    $client_id = $row['client_id'];
    $category_id = $row['category_id'];

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_number = $quote_number, quote_date = '$date', category_id = $category_id, quote_status = 'Draft', quote_amount = '$quote_amount', quote_note = '$quote_note', quote_created_at = NOW(), client_id = $client_id");

    $new_quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Quote copied!', quote_id = $new_quote_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', quote_id = $new_quote_id");
    }

    $_SESSION['alert_message'] = "Quote copied";
    
    header("Location: quote.php?quote_id=$new_quote_id");

}

if(isset($_POST['add_quote_to_invoice'])){

    $quote_id = intval($_POST['quote_id']);
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $client_net_terms = intval($_POST['client_net_terms']);
    
    //Get the last Invoice Number and add 1 for the new invoice number
    $sql = mysqli_query($mysqli,"SELECT invoice_number FROM invoices ORDER BY invoice_number DESC LIMIT 1");
    $row = mysqli_fetch_array($sql);
    $invoice_number = $row['invoice_number'] + 1;

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = $row['quote_amount'];
    $quote_note = $row['quote_note'];
    $client_id = $row['client_id'];
    $category_id = $row['category_id'];

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_number = $invoice_number, invoice_date = '$date', invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), category_id = $category_id, invoice_status = 'Draft', invoice_amount = '$quote_amount', invoice_note = '$quote_note', invoice_created_at = NOW(), client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Quote copied to Invoice!', invoice_id = $new_invoice_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_subtotal'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', invoice_id = $new_invoice_id");
    }

    $_SESSION['alert_message'] = "Quoted copied to Invoice";
    
    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if(isset($_GET['delete_quote_item'])){
    $item_id = intval($_GET['delete_quote_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $quote_id = $row['quote_id'];
    $item_subtotal = $row['item_subtotal'];
    $item_tax = $row['item_tax'];
    $item_total = $row['item_total'];

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_array($sql);
    
    $new_quote_amount = $row['quote_amount'] - $item_total;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = '$new_quote_amount' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    $_SESSION['alert_message'] = "Item deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['approve_quote'])){

    $quote_id = intval($_GET['approve_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Approved' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Approved', history_description = 'Quote approved!', quote_id = $quote_id");

    $_SESSION['alert_message'] = "Quote approved";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['reject_quote'])){

    $quote_id = intval($_GET['reject_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Rejected' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Cancelled', history_description = 'Quote rejected!', quote_id = $quote_id");

    $_SESSION['alert_message'] = "Quote rejected";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['pdf_quote'])){

    $quote_id = intval($_GET['pdf_quote']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients
    WHERE quotes.client_id = clients.client_id
    AND quotes.quote_id = $quote_id"
    );

    $row = mysqli_fetch_array($sql);
    $quote_id = $row['quote_id'];
    $quote_number = $row['quote_number'];
    $quote_status = $row['quote_status'];
    $quote_date = $row['quote_date'];
    $quote_amount = $row['quote_amount'];
    $quote_note = $row['quote_note'];
    $quote_url_key = $row['quote_url_key'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_address = $row['client_address'];
    $client_city = $row['client_city'];
    $client_state = $row['client_state'];
    $client_zip = $row['client_zip'];
    $client_email = $row['client_email'];
    $client_phone = $row['client_phone'];
    if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
    }
    $client_website = $row['client_website'];

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id ORDER BY item_id ASC");

    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_price'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $total_tax = $item_tax + $total_tax;
        $sub_total = $item_price * $item_quantity + $sub_total;


        $items .= "
          <tr>
            <td align='center'>$item_name</td>
            <td>$item_description</td>
            <td align='center'>$item_quantity</td>
            <td class='cost'>$$item_price</td>
            <td class='cost'>$$item_tax</td>
            <td class='cost'>$$item_total</td>
          </tr>
        ";

    }

    $html = '
    <html>
    <head>
    <style>
    body {font-family: sans-serif;
    font-size: 10pt;
    }
    p { margin: 0pt; }
    table.items {
    border: 0.1mm solid #000000;
    }
    td { vertical-align: top; }
    .items td {
    border-left: 0.1mm solid #000000;
    border-right: 0.1mm solid #000000;
    }
    table thead td { background-color: #EEEEEE;
    text-align: center;
    border: 0.1mm solid #000000;
    font-variant: small-caps;
    }
    .items td.blanktotal {
    background-color: #EEEEEE;
    border: 0.1mm solid #000000;
    background-color: #FFFFFF;
    border: 0mm none #000000;
    border-top: 0.1mm solid #000000;
    border-right: 0.1mm solid #000000;
    }
    .items td.totals {
    text-align: right;
    border: 0.1mm solid #000000;
    }
    .items td.cost {
    text-align: "." center;
    }
    </style>
    </head>
    <body>
    <!--mpdf
    <htmlpageheader name="myheader">
    <table width="100%"><tr>
    <td width="15%"><img width="75" height="75" src=" '.$config_invoice_logo.' "></img></td>
    <td width="50%"><span style="font-weight: bold; font-size: 14pt;"> '.$config_company_name.' </span><br />' .$config_company_address.' <br /> '.$config_company_city.' '.$config_company_state.' '.$config_company_zip.'<br /> '.$config_company_phone.' </td>
    <td width="35%" style="text-align: right;">Quote No.<br /><span style="font-weight: bold; font-size: 12pt;"> QUO-'.$quote_number.' </span></td>
    </tr></table>
    </htmlpageheader>
    <htmlpagefooter name="myfooter">
    <div style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; ">
    Page {PAGENO} of {nb}
    </div>
    </htmlpagefooter>
    <sethtmlpageheader name="myheader" value="on" show-this-page="1" />
    <sethtmlpagefooter name="myfooter" value="on" />
    mpdf-->
    <div style="text-align: right">Date: '.$quote_date.'</div>
    <table width="100%" style="font-family: serif;" cellpadding="10"><tr>
    <td width="45%" style="border: 0.1mm solid #888888; "><span style="font-size: 7pt; color: #555555; font-family: sans;">TO:</span><br /><br /><b> '.$client_name.' </b><br />'.$client_address.'<br />'.$client_city.' '.$client_state.' '.$client_zip.' <br /><br> '.$client_email.' <br /> '.$client_phone.'</td>
    <td width="65%">&nbsp;</td>

    </tr></table>
    <br />
    <table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse; " cellpadding="8">
    <thead>
    <tr>
    <td width="28%">Product</td>
    <td width="28%">Description</td>
    <td width="10%">Qty</td>
    <td width="10%">Price</td>
    <td width="12%">Tax</td>
    <td width="12%">Total</td>
    </tr>
    </thead>
    <tbody>
    '.$items.'
    <tr>
    <td class="blanktotal" colspan="4" rowspan="3"><h4>Notes</h4> '.$quote_note.' </td>
    <td class="totals">Subtotal:</td>
    <td class="totals cost">$ '.number_format($sub_total,2).' </td>
    </tr>
    <tr>
    <td class="totals">Tax:</td>
    <td class="totals cost">$ '.number_format($total_tax,2).' </td>
    </tr>
    <tr>
    <td class="totals">Total:</td>
    <td class="totals cost">$ '.number_format($quote_amount,2).' </td>
    </tr>
    </tbody>
    </table>
    <div style="text-align: center; font-style: italic;"> '.$config_quote_footer.' </div>
    </body>
    </html>
    ';
    
    $mpdf = new \Mpdf\Mpdf([
    'margin_left' => 5,
    'margin_right' => 5,
    'margin_top' => 48,
    'margin_bottom' => 25,
    'margin_header' => 10,
    'margin_footer' => 10
    ]);
    $mpdf->SetProtection(array('print'));
    $mpdf->SetTitle("$config_company_name - Quote");
    $mpdf->SetAuthor("$config_company_name");
    $mpdf->SetWatermarkText("Quote");
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'DejaVuSansCondensed';
    $mpdf->watermarkTextAlpha = 0.1;
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);
    $mpdf->Output();

}

if(isset($_GET['email_quote'])){
    $quote_id = intval($_GET['email_quote']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients
    WHERE quotes.client_id = clients.client_id
    AND quotes.quote_id = $quote_id"
    );

    $row = mysqli_fetch_array($sql);
    $quote_id = $row['quote_id'];
    $quote_number = $row['quote_number'];
    $quote_status = $row['quote_status'];
    $quote_date = $row['quote_date'];
    $quote_amount = $row['quote_amount'];
    $quote_note = $row['quote_note'];
    $quote_url_key = $row['quote_url_key'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_address = $row['client_address'];
    $client_city = $row['client_city'];
    $client_state = $row['client_state'];
    $client_zip = $row['client_zip'];
    $client_email = $row['client_email'];
    $client_phone = $row['client_phone'];
    if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
    }
    $client_website = $row['client_website'];

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id ORDER BY item_id ASC");

    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_price'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $total_tax = $item_tax + $total_tax;
        $sub_total = $item_price * $item_quantity + $sub_total;


        $items .= "
          <tr>
            <td align='center'>$item_name</td>
            <td>$item_description</td>
            <td align='center'>$item_quantity</td>
            <td class='cost'>$$item_price</td>
            <td class='cost'>$$item_tax</td>
            <td class='cost'>$$item_total</td>
          </tr>
        ";

    }

    $html = '
    <html>
    <head>
    <style>
    body {font-family: sans-serif;
    font-size: 10pt;
    }
    p { margin: 0pt; }
    table.items {
    border: 0.1mm solid #000000;
    }
    td { vertical-align: top; }
    .items td {
    border-left: 0.1mm solid #000000;
    border-right: 0.1mm solid #000000;
    }
    table thead td { background-color: #EEEEEE;
    text-align: center;
    border: 0.1mm solid #000000;
    font-variant: small-caps;
    }
    .items td.blanktotal {
    background-color: #EEEEEE;
    border: 0.1mm solid #000000;
    background-color: #FFFFFF;
    border: 0mm none #000000;
    border-top: 0.1mm solid #000000;
    border-right: 0.1mm solid #000000;
    }
    .items td.totals {
    text-align: right;
    border: 0.1mm solid #000000;
    }
    .items td.cost {
    text-align: "." center;
    }
    </style>
    </head>
    <body>
    <!--mpdf
    <htmlpageheader name="myheader">
    <table width="100%"><tr>
    <td width="15%"><img width="75" height="75" src=" '.$config_invoice_logo.' "></img></td>
    <td width="50%"><span style="font-weight: bold; font-size: 14pt;"> '.$config_company_name.' </span><br />' .$config_company_address.' <br /> '.$config_company_city.' '.$config_company_state.' '.$config_company_zip.'<br /> '.$config_company_phone.' </td>
    <td width="35%" style="text-align: right;">Quote No.<br /><span style="font-weight: bold; font-size: 12pt;"> QUO-'.$quote_number.' </span></td>
    </tr></table>
    </htmlpageheader>
    <htmlpagefooter name="myfooter">
    <div style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; ">
    Page {PAGENO} of {nb}
    </div>
    </htmlpagefooter>
    <sethtmlpageheader name="myheader" value="on" show-this-page="1" />
    <sethtmlpagefooter name="myfooter" value="on" />
    mpdf-->
    <div style="text-align: right">Date: '.$quote_date.'</div>
    <table width="100%" style="font-family: serif;" cellpadding="10"><tr>
    <td width="45%" style="border: 0.1mm solid #888888; "><span style="font-size: 7pt; color: #555555; font-family: sans;">TO:</span><br /><br /><b> '.$client_name.' </b><br />'.$client_address.'<br />'.$client_city.' '.$client_state.' '.$client_zip.' <br /><br> '.$client_email.' <br /> '.$client_phone.'</td>
    <td width="65%">&nbsp;</td>

    </tr></table>
    <br />
    <table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse; " cellpadding="8">
    <thead>
    <tr>
    <td width="28%">Product</td>
    <td width="28%">Description</td>
    <td width="10%">Qty</td>
    <td width="10%">Price</td>
    <td width="12%">Tax</td>
    <td width="12%">Total</td>
    </tr>
    </thead>
    <tbody>
    '.$items.'
    <tr>
    <td class="blanktotal" colspan="4" rowspan="3"><h4>Notes</h4> '.$quote_note.' </td>
    <td class="totals">Subtotal:</td>
    <td class="totals cost">$ '.number_format($sub_total,2).' </td>
    </tr>
    <tr>
    <td class="totals">Tax:</td>
    <td class="totals cost">$ '.number_format($total_tax,2).' </td>
    </tr>
    <tr>
    <td class="totals">Total:</td>
    <td class="totals cost">$ '.number_format($quote_amount,2).' </td>
    </tr>
    </tbody>
    </table>
    <div style="text-align: center; font-style: italic;"> '.$config_quote_footer.' </div>
    </body>
    </html>
    ';
    
    $mpdf = new \Mpdf\Mpdf([
    'margin_left' => 5,
    'margin_right' => 5,
    'margin_top' => 48,
    'margin_bottom' => 25,
    'margin_header' => 10,
    'margin_footer' => 10
    ]);
    $mpdf->SetProtection(array('print'));
    $mpdf->SetTitle("$config_company_name - Quote");
    $mpdf->SetAuthor("$config_company_name");
    $mpdf->SetWatermarkText("Quote");
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'DejaVuSansCondensed';
    $mpdf->watermarkTextAlpha = 0.1;
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);
    $mpdf->Output("uploads/$quote_date-$config_company_name-Quote$quote_number.pdf", 'F');

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
        $mail->addAddress("$client_email", "$client_name");     // Add a recipient

        // Attachments
        //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
        $mail->addAttachment("uploads/$quote_date-$config_company_name-Quote$quote_number.pdf");    // Optional name

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = "Quote $quote_number - $quote_date";
        $mail->Body    = "Hello $client_name,<br><br>Attached to this email is the Quote you requested. You can approve or disapprove this quote by clicking here.<br><br>If you have any questions please contact us at the number below.<br><br>~<br>$config_company_name<br>$config_company_phone";
        
        $mail->send();
        echo 'Message has been sent';

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed Quote!', quote_id = $quote_id");

        //Don't change the status to sent if the status is anything but draft
        if($quote_status == 'Draft'){

            mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent', client_id = $client_id WHERE quote_id = $quote_id");

        }

        $_SESSION['alert_message'] = "Quote has been sent";

        header("Location: " . $_SERVER["HTTP_REFERER"]);


    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
    unlink("uploads/$quote_date-$config_company_name-Quote$quote_number.pdf");
}

if(isset($_POST['add_recurring'])){

    $client = intval($_POST['client']);
    $frequency = strip_tags(mysqli_real_escape_string($mysqli,$_POST['frequency']));
    $start_date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['start_date']));
    $category = intval($_POST['category']);

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_frequency = '$frequency', recurring_next_date = '$start_date', category_id = $category, recurring_status = 1, client_id = $client");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_description = 'Reccuring Invoice created!', recurring_id = $recurring_id");

    $_SESSION['alert_message'] = "Recurring Invoice added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_recurring'])){
    $recurring_id = intval($_GET['delete_recurring']);

    mysqli_query($mysqli,"DELETE FROM recurring WHERE recurring_id = $recurring_id");
    
    //Delete Items Associated with the Recurring
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE recurring_id = $recurring_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = $row['item_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE recurring_id = $recurring_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = $row['history_id'];
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    $_SESSION['alert_message'] = "Recurring Invoice deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['recurring_activate'])){

    $recurring_id = intval($_GET['recurring_activate']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_status = 1 WHERE recurring_id = $recurring_id");

    $_SESSION['alert_message'] = "Recurring Invoice Activated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['recurring_deactivate'])){

    $recurring_id = intval($_GET['recurring_deactivate']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_status = 0 WHERE recurring_id = $recurring_id");

    $_SESSION['alert_message'] = "Recurring Invoice Deactivated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['save_recurring'])){

    $recurring_id = intval($_POST['recurring_id']);
    
    if(isset($_POST['name'])){
        $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
        $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
        $qty = $_POST['qty'];
        $price = $_POST['price'];
        $tax = $_POST['tax'];
        
        $subtotal = $price * $qty;
        $tax = $subtotal * $tax;
        $total = $subtotal + $tax;

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = '$price', item_subtotal = '$subtotal', item_tax = '$tax', item_total = '$total', recurring_id = $recurring_id");

        //Update Invoice Balances

        $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id");
        $row = mysqli_fetch_array($sql);

        $new_recurring_amount = $row['recurring_amount'] + $total;

        mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = '$new_recurring_amount' WHERE recurring_id = $recurring_id");

        $_SESSION['alert_message'] = "Item added";

    }

    if(isset($_POST['recurring_note'])){

        $recurring_note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['recurring_note']));

        mysqli_query($mysqli,"UPDATE recurring SET recurring_note = '$recurring_note' WHERE recurring_id = $recurring_id");

        $_SESSION['alert_message'] = "Notes added";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_recurring_item'])){
    $item_id = intval($_GET['delete_recurring_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $recurring_id = $row['recurring_id'];
    $item_subtotal = $row['item_subtotal'];
    $item_tax = $row['item_tax'];
    $item_total = $row['item_total'];

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    
    $new_recurring_amount = $row['recurring_amount'] - $item_total;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = '$new_recurring_amount' WHERE recurring_id = $recurring_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    $_SESSION['alert_message'] = "Item deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}


if(isset($_GET['mark_invoice_sent'])){

    $invoice_id = intval($_GET['mark_invoice_sent']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'INVOICE marked sent', invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Invoice marked sent";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['cancel_invoice'])){

    $invoice_id = intval($_GET['cancel_invoice']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Cancelled' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Cancelled', history_description = 'INVOICE cancelled!', invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Invoice cancelled";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice'])){
    $invoice_id = intval($_GET['delete_invoice']);

    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_id = $invoice_id");

    //Delete Items Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql)){;
        $item_id = $row['item_id'];
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql)){;
        $history_id = $row['history_id'];
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    //Delete Payments Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql)){;
        $payment_id = $row['payment_id'];
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");
    }

    $_SESSION['alert_message'] = "Invoice deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['save_invoice'])){

    $invoice_id = intval($_POST['invoice_id']);
    
    if(isset($_POST['name'])){
        $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
        $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
        $qty = $_POST['qty'];
        $price = $_POST['price'];
        $tax = $_POST['tax'];
        
        $subtotal = $price * $qty;
        $tax = $subtotal * $tax;
        $total = $subtotal + $tax;

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = '$price', item_subtotal = '$subtotal', item_tax = '$tax', item_total = '$total', invoice_id = $invoice_id");

        //Update Invoice Balances

        $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql);

        $new_invoice_amount = $row['invoice_amount'] + $total;

        mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = '$new_invoice_amount' WHERE invoice_id = $invoice_id");

        $_SESSION['alert_message'] = "Item added";

    }


    if(isset($_POST['invoice_note'])){

        $invoice_note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['invoice_note']));

        mysqli_query($mysqli,"UPDATE invoices SET invoice_note = '$invoice_note' WHERE invoice_id = $invoice_id");

        $_SESSION['alert_message'] = "Notes added";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_invoice_item'])){
    $item_id = intval($_GET['delete_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $item_subtotal = $row['item_subtotal'];
    $item_tax = $row['item_tax'];
    $item_total = $row['item_total'];

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    
    $new_invoice_amount = $row['invoice_amount'] - $item_total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = '$new_invoice_amount' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    $_SESSION['alert_message'] = "Item deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_payment'])){

    $invoice_id = intval($_POST['invoice_id']);
    $balance = $_POST['balance'];
    $date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['date']));
    $amount = $_POST['amount'];
    $account = intval($_POST['account']);
    $payment_method = strip_tags(mysqli_real_escape_string($mysqli,$_POST['payment_method']));
    $reference = strip_tags(mysqli_real_escape_string($mysqli,$_POST['reference']));
    $email_receipt = intval($_POST['email_receipt']);

    //Check to see if amount entered is greater than the balance of the invoice
    if($amount > $balance){
        $_SESSION['alert_message'] = "Payment is more than the balance";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }else{
        mysqli_query($mysqli,"INSERT INTO payments SET payment_date = '$date', payment_amount = '$amount', account_id = $account, payment_method = '$payment_method', payment_reference = '$reference', invoice_id = $invoice_id");

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payments_amount FROM payments WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_total_payments_amount);
        $total_payments_amount = $row['payments_amount'];
        
        //Get the invoice total
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients WHERE invoices.client_id = clients.client_id AND invoices.invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql);
        $invoice_amount = $row['invoice_amount'];
        $invoice_number = $row['invoice_number'];
        $client_name = $row['client_name'];
        $client_email = $row['client_email'];

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
                  $mail->addAddress("$client_email", "$client_name");     // Add a recipient

                  // Content
                  $mail->isHTML(true);                                  // Set email format to HTML
                  $mail->Subject = "Payment Recieved - Invoice INV-$invoice_number";
                  $mail->Body    = "Hello $client_name,<br><br>You are paid in full, we have recieved your payment of $$formatted_amount on $date for invoice INV-$invoice_number by $payment_method.<br><br>If you have any questions please contact us at the number below.<br><br>~<br>$config_company_name<br>Automated Billing Department<br>$config_company_phone";
                  //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

                  $mail->send();
                  echo 'Message has been sent';

                  mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed Receipt!', invoice_id = $invoice_id");

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
                  $mail->addAddress("$client_email", "$client_name");     // Add a recipient

                  // Content
                  $mail->isHTML(true);                                  // Set email format to HTML
                  $mail->Subject = "Partial Payment Recieved for Invoice INV-$invoice_number";
                  $mail->Body    = "Hello $client_name,<br><br>We have recieved your payment of $$formatted_amount on $date for invoice INV-$invoice_number by $payment_method with a balance of $$formatted_invoice_balance.<br><br>If you have any questions please contact us at the number below.<br><br>~<br>$config_company_name<br>Automated Billing Department<br>$config_company_phone";
                  //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

                  $mail->send();
                  echo 'Message has been sent';

                  mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed Receipt!', invoice_id = $invoice_id");

                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            }

        }

        //Update Invoice Status
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

        //Add Payment to History
        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$invoice_status', history_description = 'INVOICE payment added', invoice_id = $invoice_id");

        $_SESSION['alert_message'] = "Payment added";
        
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['delete_payment'])){
    $payment_id = intval($_GET['delete_payment']);

    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_id = $payment_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $deleted_payment_amount = $row['payment_amount'];

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments_amount FROM payments WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_total_payments_amount);
    $total_payments_amount = $row['total_payments_amount'];
    
    //Get the invoice total
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
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
    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

    //Add Payment to History
    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$invoice_status', history_description = 'INVOICE payment deleted', invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");

    $_SESSION['alert_message'] = "Payment deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_GET['email_invoice'])){
    $invoice_id = intval($_GET['email_invoice']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $invoice_id"
    );

    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $invoice_number = $row['invoice_number'];
    $invoice_status = $row['invoice_status'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = $row['invoice_amount'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_address = $row['client_address'];
    $client_city = $row['client_city'];
    $client_state = $row['client_state'];
    $client_zip = $row['client_zip'];
    $client_email = $row['client_email'];
    $client_phone = $row['client_phone'];
    if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
    }
    $client_website = $row['client_website'];

    $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payments.account_id = accounts.account_id AND payments.invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = $row['amount_paid'];

    $balance = $invoice_amount - $amount_paid;

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY item_id ASC");

    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_price'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $total_tax = $item_tax + $total_tax;
        $sub_total = $item_price * $item_quantity + $sub_total;


        $invoice_items .= "
          <tr>
            <td align='center'>$item_name</td>
            <td>$item_description</td>
            <td align='center'>$item_quantity</td>
            <td class='cost'>$$item_price</td>
            <td class='cost'>$$item_tax</td>
            <td class='cost'>$$item_total</td>
          </tr>
        ";

    }

    $html = '
    <html>
    <head>
    <style>
    body {font-family: sans-serif;
    font-size: 10pt;
    }
    p { margin: 0pt; }
    table.items {
    border: 0.1mm solid #000000;
    }
    td { vertical-align: top; }
    .items td {
    border-left: 0.1mm solid #000000;
    border-right: 0.1mm solid #000000;
    }
    table thead td { background-color: #EEEEEE;
    text-align: center;
    border: 0.1mm solid #000000;
    font-variant: small-caps;
    }
    .items td.blanktotal {
    background-color: #EEEEEE;
    border: 0.1mm solid #000000;
    background-color: #FFFFFF;
    border: 0mm none #000000;
    border-top: 0.1mm solid #000000;
    border-right: 0.1mm solid #000000;
    }
    .items td.totals {
    text-align: right;
    border: 0.1mm solid #000000;
    }
    .items td.cost {
    text-align: "." center;
    }
    </style>
    </head>
    <body>
    <!--mpdf
    <htmlpageheader name="myheader">
    <table width="100%"><tr>
    <td width="15%"><img width="75" height="75" src=" '.$config_invoice_logo.' "></img></td>
    <td width="50%"><span style="font-weight: bold; font-size: 14pt;"> '.$config_company_name.' </span><br />' .$config_company_address.' <br /> '.$config_company_city.' '.$config_company_state.' '.$config_company_zip.'<br /> '.$config_company_phone.' </td>
    <td width="35%" style="text-align: right;">Invoice No.<br /><span style="font-weight: bold; font-size: 12pt;"> INV-'.$invoice_number.' </span></td>
    </tr></table>
    </htmlpageheader>
    <htmlpagefooter name="myfooter">
    <div style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; ">
    Page {PAGENO} of {nb}
    </div>
    </htmlpagefooter>
    <sethtmlpageheader name="myheader" value="on" show-this-page="1" />
    <sethtmlpagefooter name="myfooter" value="on" />
    mpdf-->
    <div style="text-align: right">Date: '.$invoice_date.'</div>
    <div style="text-align: right">Due: '.$invoice_due.' </div>
    <table width="100%" style="font-family: serif;" cellpadding="10"><tr>
    <td width="45%" style="border: 0.1mm solid #888888; "><span style="font-size: 7pt; color: #555555; font-family: sans;">BILL TO:</span><br /><br /><b> '.$client_name.' </b><br />'.$client_address.'<br />'.$client_city.' '.$client_state.' '.$client_zip.' <br /><br> '.$client_email.' <br /> '.$client_phone.'</td>
    <td width="65%">&nbsp;</td>

    </tr></table>
    <br />
    <table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse; " cellpadding="8">
    <thead>
    <tr>
    <td width="28%">Product</td>
    <td width="28%">Description</td>
    <td width="10%">Qty</td>
    <td width="10%">Price</td>
    <td width="12%">Tax</td>
    <td width="12%">Total</td>
    </tr>
    </thead>
    <tbody>
    '.$invoice_items.'
    <tr>
    <td class="blanktotal" colspan="4" rowspan="5"><h4>Notes</h4> '.$invoice_note.' </td>
    <td class="totals">Subtotal:</td>
    <td class="totals cost">$ '.number_format($sub_total,2).' </td>
    </tr>
    <tr>
    <td class="totals">Tax:</td>
    <td class="totals cost">$ '.number_format($total_tax,2).' </td>
    </tr>
    <tr>
    <td class="totals">Total:</td>
    <td class="totals cost">$ '.number_format($invoice_amount,2).' </td>
    </tr>
    <tr>
    <td class="totals">Paid:</td>
    <td class="totals cost">$ '.number_format($amount_paid,2).' </td>
    </tr>
    <tr>
    <td class="totals"><b>Balance:</b></td>
    <td class="totals cost"><b>$ '.number_format($balance,2).' </b></td>
    </tr>
    </tbody>
    </table>
    <div style="text-align: center; font-style: italic;"> '.$config_invoice_footer.' </div>
    </body>
    </html>
    ';
    
    $mpdf = new \Mpdf\Mpdf([
    'margin_left' => 5,
    'margin_right' => 5,
    'margin_top' => 48,
    'margin_bottom' => 25,
    'margin_header' => 10,
    'margin_footer' => 10
    ]);
    $mpdf->SetProtection(array('print'));
    $mpdf->SetTitle("$config_company_name - Invoice");
    $mpdf->SetAuthor("$config_company_name");
    if($invoice_status == 'Paid'){
    $mpdf->SetWatermarkText("Paid");
    }
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'DejaVuSansCondensed';
    $mpdf->watermarkTextAlpha = 0.1;
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);
    $mpdf->Output("uploads/$invoice_date-$config_company_name-Invoice$invoice_number.pdf", 'F');

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
        $mail->addAddress("$client_email", "$client_name");     // Add a recipient

        // Attachments
        //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
        $mail->addAttachment("uploads/$invoice_date-$config_company_name-Invoice$invoice_number.pdf");    // Optional name

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        
        if($invoice_status == 'Paid'){

            $mail->Subject = "Copy of Invoice $invoice_number";
            $mail->Body    = "Hello $client_name,<br><br>Attached to this email is a copy of your invoice marked <b>paid</b>.<br><br>If you have any questions please contact us at the number below.<br><br>~<br>$config_company_name<br>Automated Billing Department<br>$config_company_phone";

        }else{

            $mail->Subject = "Invoice $invoice_number - $invoice_date - Due on $invoice_due";
            $mail->Body    = "Hello $client_name,<br><br>Attached to this email is your invoice. Please make all checks payable to $config_company_name and mail to <br><br>$config_company_address<br>$config_company_city $config_company_state $config_company_zip<br><br>before <b>$invoice_due</b>.<br><br>If you have any questions please contact us at the number below.<br><br>~<br>$config_company_name<br>Automated Billing Department<br>$config_company_phone";
            //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
        }
        
        $mail->send();
        echo 'Message has been sent';

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Emailed Invoice!', invoice_id = $invoice_id");

        //Don't chnage the status to sent if the status is anything but draf
        if($invoice_status == 'Draft'){

            mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', client_id = $client_id WHERE invoice_id = $invoice_id");

        }

        $_SESSION['alert_message'] = "Invoice has been sent";

        header("Location: " . $_SERVER["HTTP_REFERER"]);


    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
    unlink("uploads/$invoice_date-$config_company_name-Invoice$invoice_number.pdf");
}

if(isset($_GET['pdf_invoice'])){

    $invoice_id = intval($_GET['pdf_invoice']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $invoice_id"
    );

    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $invoice_number = $row['invoice_number'];
    $invoice_status = $row['invoice_status'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_note = $row['invoice_note'];
    $invoice_category_id = $row['category_id'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_address = $row['client_address'];
    $client_city = $row['client_city'];
    $client_state = $row['client_state'];
    $client_zip = $row['client_zip'];
    $client_email = $row['client_email'];
    $client_phone = $row['client_phone'];
    if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
    }
    $client_website = $row['client_website'];

    $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payments.account_id = accounts.account_id AND payments.invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = $row['amount_paid'];

    $balance = $invoice_amount - $amount_paid;

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY item_id ASC");

    while($row = mysqli_fetch_array($sql_items)){
        $item_id = $row['item_id'];
        $item_name = $row['item_name'];
        $item_description = $row['item_description'];
        $item_quantity = $row['item_quantity'];
        $item_price = $row['item_price'];
        $item_subtotal = $row['item_price'];
        $item_tax = $row['item_tax'];
        $item_total = $row['item_total'];
        $total_tax = $item_tax + $total_tax;
        $sub_total = $item_price * $item_quantity + $sub_total;

        $invoice_items .= "
        <tr>
            <td align='center'>$item_name</td>
            <td>$item_description</td>
            <td align='center'>$item_quantity</td>
            <td class='cost'>$$item_price</td>
            <td class='cost'>$$item_tax</td>
            <td class='cost'>$$item_total</td>
        </tr>
        ";

    }

    $html = '
        <html>
        <head>
        <style>
        body {font-family: sans-serif;
          font-size: 10pt;
        }
        p { margin: 0pt; }
        table.items {
          border: 0.1mm solid #000000;
        }
        td { vertical-align: top; }
        .items td {
          border-left: 0.1mm solid #000000;
          border-right: 0.1mm solid #000000;
        }
        table thead td { background-color: #EEEEEE;
          text-align: center;
          border: 0.1mm solid #000000;
          font-variant: small-caps;
        }
        .items td.blanktotal {
          background-color: #EEEEEE;
          border: 0.1mm solid #000000;
          background-color: #FFFFFF;
          border: 0mm none #000000;
          border-top: 0.1mm solid #000000;
          border-right: 0.1mm solid #000000;
        }
        .items td.totals {
          text-align: right;
          border: 0.1mm solid #000000;
        }
        .items td.cost {
          text-align: "." center;
        }
        </style>
        </head>
        <body>
        <!--mpdf
        <htmlpageheader name="myheader">
        <table width="100%"><tr>
        <td width="15%"><img width="75" height="75" src=" '.$config_invoice_logo.' "></img></td>
        <td width="50%"><span style="font-weight: bold; font-size: 14pt;"> '.$config_company_name.' </span><br />' .$config_company_address.' <br /> '.$config_company_city.' '.$config_company_state.' '.$config_company_zip.'<br /> '.$config_company_phone.' </td>
        <td width="35%" style="text-align: right;">Invoice No.<br /><span style="font-weight: bold; font-size: 12pt;"> INV-'.$invoice_number.' </span></td>
        </tr></table>
        </htmlpageheader>
        <htmlpagefooter name="myfooter">
        <div style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; ">
        Page {PAGENO} of {nb}
        </div>
        </htmlpagefooter>
        <sethtmlpageheader name="myheader" value="on" show-this-page="1" />
        <sethtmlpagefooter name="myfooter" value="on" />
        mpdf-->
        <div style="text-align: right">Date: '.$invoice_date.'</div>
        <div style="text-align: right">Due: '.$invoice_due.'</div>
        <table width="100%" style="font-family: serif;" cellpadding="10"><tr>
        <td width="45%" style="border: 0.1mm solid #888888; "><span style="font-size: 7pt; color: #555555; font-family: sans;">BILL TO:</span><br /><br /><b> '.$client_name.' </b><br />'.$client_address.'<br />'.$client_city.' '.$client_state.' '.$client_zip.' <br /><br> '.$client_email.' <br /> '.$client_phone.'</td>
        <td width="65%">&nbsp;</td>

        </tr></table>
        <br />
        <table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse; " cellpadding="8">
        <thead>
        <tr>
        <td width="28%">Product</td>
        <td width="28%">Description</td>
        <td width="10%">Qty</td>
        <td width="10%">Price</td>
        <td width="12%">Tax</td>
        <td width="12%">Total</td>
        </tr>
        </thead>
        <tbody>
        '.$invoice_items.'
        <tr>
        <td class="blanktotal" colspan="4" rowspan="5"><h4>Notes</h4> '.$invoice_note.' </td>
        <td class="totals">Subtotal:</td>
        <td class="totals cost">$ '.number_format($sub_total,2).' </td>
        </tr>
        <tr>
        <td class="totals">Tax:</td>
        <td class="totals cost">$ '.number_format($total_tax,2).' </td>
        </tr>
        <tr>
        <td class="totals">Total:</td>
        <td class="totals cost">$ '.number_format($invoice_amount,2).' </td>
        </tr>
        <tr>
        <td class="totals">Paid:</td>
        <td class="totals cost">$ '.number_format($amount_paid,2).' </td>
        </tr>
        <tr>
        <td class="totals"><b>Balance:</b></td>
        <td class="totals cost"><b>$ '.number_format($balance,2).' </b></td>
        </tr>
        </tbody>
        </table>
        <div style="text-align: center; font-style: italic;"> '.$config_invoice_footer.' </div>
        </body>
        </html>
    ';

    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 5,
        'margin_right' => 5,
        'margin_top' => 48,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);

    $mpdf->SetProtection(array('print'));
    $mpdf->SetTitle("$config_company_name - Invoice");
    $mpdf->SetAuthor("$config_company_name");
    if($invoice_status == 'Paid'){
        $mpdf->SetWatermarkText("Paid");
    }
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'DejaVuSansCondensed';
    $mpdf->watermarkTextAlpha = 0.1;
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);
    $mpdf->Output();

}

if(isset($_POST['add_contact'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/clients/$client_id/";
        $path = $path . time() . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_email = '$email', contact_photo = '$path', client_id = $client_id");

    $_SESSION['alert_message'] = "Contact added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_contact'])){

    $contact_id = intval($_POST['contact_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $title = strip_tags(mysqli_real_escape_string($mysqli,$_POST['title']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $email = strip_tags(mysqli_real_escape_string($mysqli,$_POST['email']));

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_email = '$email' WHERE contact_id = $contact_id");

    $_SESSION['alert_message'] = "Contact updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_contact'])){
    $contact_id = intval($_GET['delete_contact']);

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id");

    $_SESSION['alert_message'] = "Contact deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_location'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $hours = strip_tags(mysqli_real_escape_string($mysqli,$_POST['hours']));

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', client_id = $client_id");

    $_SESSION['alert_message'] = "Location added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_location'])){

    $location_id = intval($_POST['location_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $address = strip_tags(mysqli_real_escape_string($mysqli,$_POST['address']));
    $city = strip_tags(mysqli_real_escape_string($mysqli,$_POST['city']));
    $state = strip_tags(mysqli_real_escape_string($mysqli,$_POST['state']));
    $zip = strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip']));
    $phone = strip_tags(mysqli_real_escape_string($mysqli,$_POST['phone']));
    $phone = preg_replace("/[^0-9]/", '',$phone);
    $hours = strip_tags(mysqli_real_escape_string($mysqli,$_POST['hours']));

    mysqli_query($mysqli,"UPDATE locations SET location_name = '$name', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours' WHERE location_id = $location_id");

    $_SESSION['alert_message'] = "Location updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_location'])){
    $location_id = intval($_GET['delete_location']);

    mysqli_query($mysqli,"DELETE FROM locations WHERE location_id = $location_id");

    $_SESSION['alert_message'] = "Location deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_asset'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $make = strip_tags(mysqli_real_escape_string($mysqli,$_POST['make']));
    $model = strip_tags(mysqli_real_escape_string($mysqli,$_POST['model']));
    $serial = strip_tags(mysqli_real_escape_string($mysqli,$_POST['serial']));
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $purchase_date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['purchase_date']));
    $warranty_expire = strip_tags(mysqli_real_escape_string($mysqli,$_POST['warranty_expire']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', location_id = $location, vendor_id = $vendor, contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_note = '$note', client_id = $client_id");

    if(!empty($_POST['username'])) {
        $asset_id = mysqli_insert_id($mysqli);
        $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
        $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));
        $description = "$type - $name";
        mysqli_query($mysqli,"INSERT INTO logins SET login_description = '$description', login_username = '$username', login_password = '$password', asset_id = $asset_id, client_id = $client_id");

    }

    $_SESSION['alert_message'] = "Asset added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_asset'])){

    $asset_id = intval($_POST['asset_id']);
    $login_id = intval($_POST['login_id']);
    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $make = strip_tags(mysqli_real_escape_string($mysqli,$_POST['make']));
    $model = strip_tags(mysqli_real_escape_string($mysqli,$_POST['model']));
    $serial = strip_tags(mysqli_real_escape_string($mysqli,$_POST['serial']));
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $purchase_date = strip_tags(mysqli_real_escape_string($mysqli,$_POST['purchase_date']));
    $warranty_expire = strip_tags(mysqli_real_escape_string($mysqli,$_POST['warranty_expire']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));
    $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
    $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));
    $description = "$type - $name";

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', location_id = $location, vendor_id = $vendor, contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_note = '$note' WHERE asset_id = $asset_id");

    //If login exists then update the login
    if($login_id > 0){
        mysqli_query($mysqli,"UPDATE logins SET login_description = '$description', login_username = '$username', login_password = '$password' WHERE login_id = $login_id");
    }else{
    //If Username is filled in then add a login
        if(!empty($_POST['username'])) {
            
            mysqli_query($mysqli,"INSERT INTO logins SET login_description = '$description', login_username = '$username', login_password = '$password', asset_id = $asset_id, client_id = $client_id");

        }
    }

    $_SESSION['alert_message'] = "Asset updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_asset'])){
    $asset_id = intval($_GET['delete_asset']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id");

    $_SESSION['alert_message'] = "Asset deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_login'])){

    $client_id = intval($_POST['client_id']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $web_link = strip_tags(mysqli_real_escape_string($mysqli,$_POST['web_link']));
    $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
    $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $application_id = intval($_POST['application']);

    mysqli_query($mysqli,"INSERT INTO logins SET login_description = '$description', login_web_link = '$web_link', login_username = '$username', login_password = '$password', login_note = '$note', vendor_id = $vendor_id, asset_id = $asset_id, application_id = $application_id, client_id = $client_id");

    $_SESSION['alert_message'] = "Login added";
    
    header("Location: client.php?client_id=$client_id&tab=logins");

}

if(isset($_POST['edit_login'])){

    $login_id = intval($_POST['login_id']);
    $description = strip_tags(mysqli_real_escape_string($mysqli,$_POST['description']));
    $web_link = strip_tags(mysqli_real_escape_string($mysqli,$_POST['web_link']));
    $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
    $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"UPDATE logins SET login_description = '$description', login_web_link = '$web_link', login_username = '$username', login_password = '$password', login_note = '$note' WHERE login_id = $login_id");

    $_SESSION['alert_message'] = "Login updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_login'])){
    $login_id = intval($_GET['delete_login']);

    mysqli_query($mysqli,"DELETE FROM logins WHERE login_id = $login_id");

    $_SESSION['alert_message'] = "Login deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_file'])){
    $client_id = intval($_POST['client_id']);
    $new_name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['new_name']));

    if($_FILES['file']['tmp_name']!='') {
        $path = "uploads/clients/$client_id/";
        $path = $path . basename( $_FILES['file']['name']);
        $file_name = basename($path);
        move_uploaded_file($_FILES['file']['tmp_name'], $path);
        $ext = pathinfo($path);
        $ext = $ext['extension'];

    }

    mysqli_query($mysqli,"INSERT INTO files SET file_name = '$path', file_ext = '$ext', client_id = $client_id");

    $_SESSION['alert_message'] = "File uploaded";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_file'])){
    $file_id = intval($_GET['delete_file']);

    $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = $row['file_name'];

    unlink($file_name);

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

    $_SESSION['alert_message'] = "File deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_note'])){

    $client_id = intval($_POST['client_id']);
    $subject = strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"INSERT INTO notes SET note_subject = '$subject', note_body = '$note', client_id = $client_id");

    $_SESSION['alert_message'] = "Note added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_note'])){

    $note_id = intval($_POST['note_id']);
    $subject = strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject']));
    $note = strip_tags(mysqli_real_escape_string($mysqli,$_POST['note']));

    mysqli_query($mysqli,"UPDATE notes SET note_subject = '$subject', note_body = '$note' WHERE note_id = $note_id");

    $_SESSION['alert_message'] = "Note updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_note'])){
    $note_id = intval($_GET['delete_note']);

    mysqli_query($mysqli,"DELETE FROM notes WHERE note_id = $note_id");

    $_SESSION['alert_message'] = "Note deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_network'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $network = strip_tags(mysqli_real_escape_string($mysqli,$_POST['network']));
    $gateway = strip_tags(mysqli_real_escape_string($mysqli,$_POST['gateway']));
    $dhcp_range = strip_tags(mysqli_real_escape_string($mysqli,$_POST['dhcp_range']));

    mysqli_query($mysqli,"INSERT INTO networks SET network_name = '$name', network = '$network', network_gateway = '$gateway', network_dhcp_range = '$dhcp_range', client_id = $client_id");

    $_SESSION['alert_message'] = "Network added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_network'])){

    $network_id = intval($_POST['network_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $network = strip_tags(mysqli_real_escape_string($mysqli,$_POST['network']));
    $gateway = strip_tags(mysqli_real_escape_string($mysqli,$_POST['gateway']));
    $dhcp_range = strip_tags(mysqli_real_escape_string($mysqli,$_POST['dhcp_range']));

    mysqli_query($mysqli,"UPDATE networks SET network_name = '$name', network = '$network', network_gateway = '$gateway', network_dhcp_range = '$dhcp_range' WHERE network_id = $network_id");

    $_SESSION['alert_message'] = "Network updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_network'])){
    $network_id = intval($_GET['delete_network']);

    mysqli_query($mysqli,"DELETE FROM networks WHERE network_id = $network_id");

    $_SESSION['alert_message'] = "Network deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_domain'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $registrar = intval($_POST['registrar']);
    $webhost = intval($_POST['webhost']);
    $expire = strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire']));

    mysqli_query($mysqli,"INSERT INTO domains SET domain_name = '$name', domain_registrar = $registrar,  domain_webhost = $webhost, domain_expire = '$expire', client_id = $client_id");

    $_SESSION['alert_message'] = "Domain added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_domain'])){

    $domain_id = intval($_POST['domain_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $registrar = intval($_POST['registrar']);
    $webhost = intval($_POST['webhost']);
    $expire = strip_tags(mysqli_real_escape_string($mysqli,$_POST['expire']));

    mysqli_query($mysqli,"UPDATE domains SET domain_name = '$name', domain_registrar = $registrar,  domain_webhost = $webhost, domain_expire = '$expire' WHERE domain_id = $domain_id");

    $_SESSION['alert_message'] = "Domain updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_domain'])){
    $domain_id = intval($_GET['delete_domain']);

    mysqli_query($mysqli,"DELETE FROM domains WHERE domain_id = $domain_id");

    $_SESSION['alert_message'] = "Domain deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

if(isset($_POST['add_software'])){

    $client_id = intval($_POST['client_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $license = strip_tags(mysqli_real_escape_string($mysqli,$_POST['license']));

    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_type = '$type', software_license = '$license', client_id = $client_id");

    if(!empty($_POST['username'])) {
        $software_id = mysqli_insert_id($mysqli);
        $username = strip_tags(mysqli_real_escape_string($mysqli,$_POST['username']));
        $password = strip_tags(mysqli_real_escape_string($mysqli,$_POST['password']));
        
        mysqli_query($mysqli,"INSERT INTO logins SET login_username = '$username', login_password = '$password', software_id = $software_id, client_id = $client_id");

    }

    $_SESSION['alert_message'] = "Software added";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_software'])){

    $software_id = intval($_POST['software_id']);
    $name = strip_tags(mysqli_real_escape_string($mysqli,$_POST['name']));
    $type = strip_tags(mysqli_real_escape_string($mysqli,$_POST['type']));
    $license = strip_tags(mysqli_real_escape_string($mysqli,$_POST['license']));

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_type = '$type', software_license = '$license' WHERE software_id = $software_id");

    $_SESSION['alert_message'] = "Software updated";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_software'])){
    $software_id = intval($_GET['delete_software']);

    mysqli_query($mysqli,"DELETE FROM software WHERE software_id = $software_id");

    $_SESSION['alert_message'] = "Software deleted";
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
  
}

?>	