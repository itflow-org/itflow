<?php

/*
 * ITFlow - GET/POST request handler for client contacts
 */

if (isset($_POST['add_contact'])) {

    validateTechRole();

    require_once('post/contact_model.php');

    $password = password_hash(randomString(), PASSWORD_DEFAULT);

    if (!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_pin = '$pin', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_auth_method = '$auth_method', contact_password_hash = '$password', contact_department = '$department', contact_location_id = $location_id, contact_client_id = $client_id");

    $contact_id = mysqli_insert_id($mysqli);

    //Update Primary contact in clients if primary contact is checked
    if ($contact_primary == 1) {
        // Old way of adding contact_primary Set for Removal
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");     
    
        // New Way of setting primary contact
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 1, contact_important = 1 WHERE contact_id = $contact_id");
    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$client_id/";
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Create', log_description = '$session_name created contact $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

    $_SESSION['alert_message'] = "Contact <strong>$name</strong> created" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_contact'])) {

    validateTechRole();

    require_once('post/contact_model.php');

    $contact_id = intval($_POST['contact_id']);

    // Get Exisiting Contact Photo
    $sql = mysqli_query($mysqli,"SELECT contact_photo FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['contact_photo']);

    if (!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_pin = '$pin', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_auth_method = '$auth_method', contact_department = '$department', contact_location_id = $location_id WHERE contact_id = $contact_id");

    // Update Primary contact in clients if primary contact is checked
    if ($contact_primary == 1) {
        // Old way of adding contact_primary Set for Removal
        mysqli_query($mysqli,"UPDATE clients SET primary_contact = $contact_id WHERE client_id = $client_id");

        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 1, contact_important = 1 WHERE contact_id = $contact_id");
    }

    // Set password
    if (!empty($_POST['contact_password'])) {
        $password_hash = password_hash(trim($_POST['contact_password']), PASSWORD_DEFAULT);
        mysqli_query($mysqli, "UPDATE contacts SET contact_password_hash = '$password_hash' WHERE contact_id = $contact_id AND contact_client_id = $client_id");
    }

    // Send contact a welcome e-mail, if specified
    if (isset($_POST['send_email']) && !empty($auth_method) && !empty($config_smtp_host)) {

        // Un-sanitizied used in body of email
        $contact_name = $_POST['name'];

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_email_escaped = sanitizeInput($config_ticket_from_email);
        $config_ticket_from_name_escaped = sanitizeInput($config_ticket_from_name);

        if ($auth_method == 'azure') {
            $password_info = "Login with your Microsoft (Azure AD) account.";
        } else {
            $password_info = $_POST['contact_password'];
        }

        $subject = sanitizeInput("Your new $session_company_name ITFlow account");
        $body = mysqli_real_escape_string($mysqli, "Hello, $contact_name<br><br>An ITFlow account has been set up for you. <br><br>Username: $email <br>Password: $password_info<br><br>Login URL: https://$config_base_url/portal/<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email");

        // Queue Mail
        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$email', email_recipient_name = '$name', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject', email_content = '$body'");

        // Get Email ID for reference
        $email_id = mysqli_insert_id($mysqli);

    }

    // Check for and process image/photo
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            // Set directory in which the uploaded file will be moved
            $file_tmp_path = $_FILES['file']['tmp_name'];
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/clients/$client_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");

            $extended_alert_description = '. Photo successfully uploaded. ';
        } else {
            $extended_alert_description = '. Error uploading photo.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = '$session_name modified contact $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

    $_SESSION['alert_message'] = "Contact <strong>$name</strong> updated" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_contact'])) {

    validateTechRole();

    $contact_id = intval($_GET['archive_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NOW() WHERE contact_id = $contact_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Archive', log_description = '$session_name archived contact $contact_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_contact'])) {

    validateAdminRole();

    $contact_id = intval($_GET['delete_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Delete', log_description = '$session_name deleted contact $contact_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> deleted.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['export_client_contacts_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    //Contacts
    $sql = mysqli_query($mysqli,"SELECT * FROM contacts LEFT JOIN locations ON location_id = contact_location_id WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Contacts-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Ext', 'Mobile', 'Location');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Export', log_description = '$session_name exported $num_rows contact(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    exit;

}

if (isset($_POST["import_client_contacts_csv"])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = false;

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if (in_array($file_extension,$allowed_file_extensions) === false) {
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1) {
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 8) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if (!$error) {
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false) {
            $duplicate_detect = 0;
            if (isset($column[0])) {
                $name = sanitizeInput($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_name = '$name' AND contact_client_id = $client_id")) > 0) {
                    $duplicate_detect = 1;
                }
            }
            if (isset($column[1])) {
                $title = sanitizeInput($column[1]);
            }
            if (isset($column[2])) {
                $department = sanitizeInput($column[2]);
            }
            if (isset($column[3])) {
                $email = sanitizeInput($column[3]);
            }
            if (isset($column[4])) {
                $phone = preg_replace("/[^0-9]/", '',$column[4]);
            }
            if (isset($column[5])) {
                $ext = preg_replace("/[^0-9]/", '',$column[5]);
            }
            if (isset($column[6])) {
                $mobile = preg_replace("/[^0-9]/", '',$column[6]);
            }
            if (isset($column[7])) {
                $location = sanitizeInput($column[7]);
                $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
                $row = mysqli_fetch_assoc($sql_location);
                $location_id = intval($row['location_id']);
            }
            // Potentially import the rest in the future?


            // Check if duplicate was detected
            if ($duplicate_detect == 0) {
                //Add
                mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_department = '$department', contact_email = '$email', contact_phone = '$phone', contact_extension = '$ext', contact_mobile = '$mobile', contact_location_id = $location_id, contact_client_id = $client_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Import', log_description = '$session_name imported $row_count contact(s) via CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "$row_count Contact(s) added, $duplicate_count duplicate(s) detected";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['download_client_contacts_csv_template'])) {
    $client_id = intval($_GET['download_client_contacts_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
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
