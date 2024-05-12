<?php

/*
 * ITFlow - GET/POST request handler for client contacts
 */

if (isset($_POST['add_contact'])) {

    validateTechRole();

    require_once 'post/contact_model.php';


    // Set password
    if (!empty($_POST['contact_password'])) {
        $password_hash = password_hash(trim($_POST['contact_password']), PASSWORD_DEFAULT);
    } else {
        // Set a random password
        $password_hash = password_hash(randomString(), PASSWORD_DEFAULT);
    }

    if (!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_pin = '$pin', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_auth_method = '$auth_method', contact_password_hash = '$password_hash', contact_department = '$department', contact_location_id = $location_id, contact_client_id = $client_id");

    $contact_id = mysqli_insert_id($mysqli);

    //Update Primary contact in clients if primary contact is checked
    if ($contact_primary == 1) {
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

    require_once 'post/contact_model.php';

    $contact_id = intval($_POST['contact_id']);
    $send_email = intval($_POST['send_email']);

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
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 1, contact_important = 1 WHERE contact_id = $contact_id");
    }

    // Set password
    if (!empty($_POST['contact_password'])) {
        $password_hash = password_hash(trim($_POST['contact_password']), PASSWORD_DEFAULT);
        mysqli_query($mysqli, "UPDATE contacts SET contact_password_hash = '$password_hash' WHERE contact_id = $contact_id AND contact_client_id = $client_id");
    }

    // Send contact a welcome e-mail, if specified
    if ($send_email && !empty($auth_method) && !empty($config_smtp_host)) {

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
        $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
        $config_mail_from_email = sanitizeInput($config_mail_from_email);
        $config_mail_from_name = sanitizeInput($config_mail_from_name);
        $config_base_url = sanitizeInput($config_base_url);

        // Get Company Phone Number
        $sql = mysqli_query($mysqli,"SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Authentication info (azure, reset password, or tech-provided temporary password)

        if ($auth_method == 'azure') {
            $password_info = "Login with your Microsoft (Azure AD) account.";
        } elseif (empty($_POST['contact_password'])) {
            $password_info = "Request a password reset at https://$config_base_url/portal/login_reset.php";
        } else {
            $password_info = mysqli_real_escape_string($mysqli, $_POST['contact_password'] . " -- Please change on first login");
        }

        $subject = "Your new $company_name portal account";
        $body = "Hello $name,<br><br>$company_name has created a support portal account for you. <br><br>Username: $email<br>Password: $password_info<br><br>Login URL: https://$config_base_url/portal/<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        // Queue Mail
        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body' => $body,
            ]
        ];
        addToMailQueue($mysqli, $data);
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

if (isset($_POST['bulk_assign_contact_location'])) {

    validateTechRole();

    $location_id = intval($_POST['bulk_location_id']);

    // Get Location name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    // Get Selected Contacts Count
    $contact_count = count($_POST['contact_ids']);
    
    // Assign Location to Selected Contacts
    if (!empty($_POST['contact_ids'])) {
        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_location_id = $location_id WHERE contact_id = $contact_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = '$session_name assigned $contact_name to Location $location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

        } // End Assign Location Loop
        
        $_SESSION['alert_message'] = "You assigned <b>$contact_count</b> contacts to location <b>$location_name</b>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_contact_phone'])) {

    validateTechRole();

    $phone = preg_replace("/[^0-9]/", '', $_POST['bulk_phone']);

    // Get Selected Contacts Count
    $contact_count = count($_POST['contact_ids']);
    
    // Assign Location to Selected Contacts
    if (!empty($_POST['contact_ids'])) {
        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_phone = '$phone' WHERE contact_id = $contact_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = '$session_name set Phone Number to $phone for $contact_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

        } // End Assign Location Loop
        
        $_SESSION['alert_message'] = "You set Phone Number <b>" . formatPhoneNumber($phone) . "</b> on $contact_count</b> contacts";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_contact_department'])) {

    validateTechRole();

    $department = sanitizeInput($_POST['bulk_department']);

    // Get Selected Contacts Count
    $contact_count = count($_POST['contact_ids']);
    
    // Assign Location to Selected Contacts
    if (!empty($_POST['contact_ids'])) {
        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_department = '$department' WHERE contact_id = $contact_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = '$session_name set Department to $department for $contact_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

        } // End Assign Location Loop
        
        $_SESSION['alert_message'] = "You set the Department to <b>$department</b> for <b>$contact_count</b> contacts";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_contact_role'])) {

    validateTechRole();

    $contact_important = intval($_POST['bulk_contact_important']);
    $contact_billing = intval($_POST['bulk_contact_billing']);
    $contact_technical = intval($_POST['bulk_contact_technical']);

    // Get Selected Contacts Count
    $contact_count = count($_POST['contact_ids']);
    
    // Assign Location to Selected Contacts
    if (!empty($_POST['contact_ids'])) {
        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical WHERE contact_id = $contact_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = '$session_name updated $contact_name role', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

        } // End Assign Location Loop
        
        $_SESSION['alert_message'] = "You updated roles for <b>$contact_count</b> contacts";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['anonymize_contact'])) {

    validateAdminRole();

    $contact_id = intval($_GET['anonymize_contact']);

    // Get contact & client info
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);

    $contact_name = sanitizeInput($row['contact_name']);
    $contact_first_name = explode(" ", $contact_name)[0];
    $contact_email = sanitizeInput($row['contact_email']);
    $contact_phone = sanitizeInput($row['contact_phone']);
    $info_to_redact = array($contact_name, $contact_first_name, $contact_email, $contact_phone);

    $client_id = intval($row['contact_client_id']);

    // Redact name with asterisks
    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '*****' WHERE contact_id = $contact_id");

    // Remove all other contact information
    // Doing redactions field by field to ensure that an error updating one field doesn't break the entire query
    mysqli_query($mysqli,"UPDATE contacts SET contact_title = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_department = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_email = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_phone = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_extension = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_mobile = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_pin = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_notes = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_auth_method = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_password_hash = '' WHERE contact_id = $contact_id");
    mysqli_query($mysqli,"UPDATE contacts SET contact_location_id = '0' WHERE contact_id = $contact_id");

    // Remove Billing, Technical, Important Roles
    mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0 WHERE contact_id = $contact_id");

    // Redact audit logs
    $log_sql = mysqli_query($mysqli, "SELECT * FROM logs WHERE log_client_id =  $client_id");
    while ($log = mysqli_fetch_array($log_sql)) {
        $log_id = intval($log['log_id']);
        $description = $log['log_description'];
        $description = str_ireplace($info_to_redact, "*****", $description);
        $description = sanitizeInput($description);

        mysqli_query($mysqli,"UPDATE logs SET log_description = '$description' WHERE log_id = $log_id AND log_client_id = $client_id");
    }


    // Get all tickets this contact raised
    $contact_tickets_sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_client_id = $client_id AND ticket_contact_id =  $contact_id");
    while ($ticket = mysqli_fetch_array($contact_tickets_sql)) {

        $ticket_id = intval($ticket['ticket_id']);

        // Redact contact name or email in the subject of all tickets they raised
        $subject = $ticket['ticket_subject'];
        $subject = str_ireplace($info_to_redact, "*****", $subject);
        $subject = sanitizeInput($subject);
        mysqli_query($mysqli,"UPDATE tickets SET ticket_subject = '$subject' WHERE ticket_id = $ticket_id");

        // Redact contact name or email in the description of all tickets they raised
        $details = $ticket['ticket_details'];

        $details = str_ireplace($info_to_redact, "*****", $details);
        $details = sanitizeInput($details);
        mysqli_query($mysqli,"UPDATE tickets SET ticket_details = '$details' WHERE ticket_id = $ticket_id");

        // Redact contact name or email in the replies of all tickets they raised
        $ticket_replies_sql = mysqli_query($mysqli, "SELECT * FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");

        while($ticket_reply = mysqli_fetch_array($ticket_replies_sql)) {
            $ticket_reply_id = intval($ticket_reply['ticket_reply_id']);
            $ticket_reply_details = $ticket_reply['ticket_reply'];
            $ticket_reply_details = str_ireplace($info_to_redact, "*****", $ticket_reply_details);
            $ticket_reply_details = sanitizeInput($ticket_reply_details);
            mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply = '$ticket_reply_details' WHERE ticket_reply_id = $ticket_reply_id");
        }

    }

    // Archive contact
    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NOW() WHERE contact_id = $contact_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Anonymize', log_description = '$session_name anonymized contact', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $contact_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact $contact_name anonymized & archived";

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

    mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0, contact_auth_method = '', contact_password_hash = '', contact_archived_at = NOW() WHERE contact_id = $contact_id");

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
