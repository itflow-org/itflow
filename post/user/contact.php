<?php

/*
 * ITFlow - GET/POST request handler for client contacts
 */

if (isset($_POST['add_contact'])) {

    enforceUserPermission('module_client', 2);

    require_once 'post/user/contact_model.php';

    // Create User Account
    $user_id = 0;
    if ($name && $email && $auth_method) {

        // Set password
        if (!empty($_POST['contact_password'])) {
            $password_hash = password_hash(trim($_POST['contact_password']), PASSWORD_DEFAULT);
        } else {
            // Set a random password
            $password_hash = password_hash(randomString(), PASSWORD_DEFAULT);
        }

        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password_hash', user_auth_method = '$auth_method', user_type = 2");

        $user_id = mysqli_insert_id($mysqli);
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_pin = '$pin', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_department = '$department', contact_location_id = $location_id, contact_user_id = $user_id, contact_client_id = $client_id");

    $contact_id = mysqli_insert_id($mysqli);

    // Add Tags
    if (isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO contact_tags SET contact_id = $contact_id, tag_id = $tag");
        }
    }

    //Update Primary contact in clients if primary contact is checked
    if ($contact_primary == 1) {
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 1, contact_important = 1 WHERE contact_id = $contact_id");
    }

    // Check for and process image/photo
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            if (!file_exists("uploads/clients/$client_id")) {
                mkdir("uploads/clients/$client_id");
            }
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");
            
        }
    }

    // Logging
    logAction("Contact", "Create", "$session_name created contact $name", $client_id, $contact_id);

    customAction('contact_create', $contact_id);

    $_SESSION['alert_message'] = "Contact <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_contact'])) {

    enforceUserPermission('module_client', 2);

    require_once 'post/user/contact_model.php';

    $contact_id = intval($_POST['contact_id']);
    $send_email = intval($_POST['send_email'] ?? 0);

    // Get Exisiting Contact Photo and contact_user_id
    $sql = mysqli_query($mysqli,"SELECT contact_photo, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['contact_photo']);
    $contact_user_id = intval($row['contact_user_id']);

    if (!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    // Update Existing User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli, "UPDATE users SET user_name = '$name', user_email = '$email', user_auth_method = '$auth_method' WHERE user_id = $contact_user_id");

        // Set password
        if ($_POST['contact_password']) {
            $password_hash = password_hash(trim($_POST['contact_password']), PASSWORD_DEFAULT);
            mysqli_query($mysqli, "UPDATE users SET user_password = '$password_hash' WHERE user_id = $contact_user_id");
        }
    // Create New User
    } elseif ($contact_user_id == 0 && $name && $email && $auth_method) {
        
        // Set password
        if ($_POST['contact_password']) {
            $password_hash = password_hash(trim($_POST['contact_password']), PASSWORD_DEFAULT);
        } else {
            // Set a random password
            $password_hash = password_hash(randomString(), PASSWORD_DEFAULT);
        }

        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password_hash', user_auth_method = '$auth_method', user_type = 2");

        $contact_user_id = mysqli_insert_id($mysqli);

    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_pin = '$pin', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_department = '$department', contact_location_id = $location_id, contact_user_id = $contact_user_id WHERE contact_id = $contact_id");

    // Upload Photo
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            // Set directory in which the uploaded file will be moved
            $file_tmp_path = $_FILES['file']['tmp_name'];
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/clients/$client_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");
                
        }
    }

    // Tags
    // Delete existing tags
    mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");

    // Add new tags
    if (isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO contact_tags SET contact_id = $contact_id, tag_id = $tag");
        }
    }

    // Update Primary contact in clients if primary contact is checked
    if ($contact_primary == 1) {
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 1, contact_important = 1 WHERE contact_id = $contact_id");
    }

    // Send contact a welcome e-mail, if specified
    if ($send_email && $auth_method && $config_smtp_host && $contact_user_id) {

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

    //Logging
    logAction("Contact", "Edit", "$session_name edited contact $name", $client_id, $contact_id);

    customAction('contact_update', $contact_id);

    $_SESSION['alert_message'] = "Contact <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_contact_note'])) {

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_POST['contact_id']);
    $type = sanitizeInput($_POST['type']);
    $note = sanitizeInput($_POST['note']);

    // Get Contact details for logging and alerting
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    mysqli_query($mysqli, "INSERT INTO contact_notes SET contact_note_type = '$type', contact_note = '$note', contact_note_created_by = $session_user_id, contact_note_contact_id = $contact_id");

    $contact_note_id = mysqli_insert_id($mysqli);

    //Logging
    logAction("Contact", "Edit", "$session_name created a $type note for contact $contact_name", $client_id, $contact_id);

    $_SESSION['alert_message'] = "Note <strong>$type</strong> created for <strong>$contact_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_contact_note'])) {

    enforceUserPermission('module_client', 2);

    $contact_note_id = intval($_GET['archive_contact_note']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_note_type, contact_id, contact_name, contact_client_id FROM contact_notes LEFT JOIN contacts ON contact_id = contact_note_contact_id WHERE contact_note_id = $contact_note_id");
    $row = mysqli_fetch_array($sql);
    $contact_note_type = sanitizeInput($row['contact_note_type']);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_id = intval($row['contact_id']);

    mysqli_query($mysqli,"UPDATE contact_notes SET contact_note_archived_at = NOW() WHERE contact_note_id = $contact_note_id");
    
    // Logging
    logAction("Contact", "Edit", "$session_name archived note $contact_note_type for $contact_name", $client_id, $contact_id);


    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Note <strong>$contact_note_type</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_contact_note'])) {

    enforceUserPermission('module_client', 2);

    $contact_note_id = intval($_GET['unarchive_contact_note']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_note_type, contact_id, contact_name, contact_client_id FROM contact_notes LEFT JOIN contacts ON contact_id = contact_note_contact_id WHERE contact_note_id = $contact_note_id");
    $row = mysqli_fetch_array($sql);
    $contact_note_type = sanitizeInput($row['contact_note_type']);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_id = intval($row['contact_id']);

    mysqli_query($mysqli,"UPDATE contact_notes SET contact_note_archived_at = NULL WHERE contact_note_id = $contact_note_id");
    
    // Logging
    logAction("Contact", "Edit", "$session_name restored note $contact_note_type for $contact_name", $client_id, $contact_id);

    $_SESSION['alert_message'] = "Note <strong>$contact_note_type</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_contact_note'])) {

    enforceUserPermission('module_client', 3);

    $contact_note_id = intval($_GET['delete_contact_note']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_note_type, contact_id, contact_name, contact_client_id FROM contact_notes LEFT JOIN contacts ON contact_id = contact_note_contact_id WHERE contact_note_id = $contact_note_id");
    $row = mysqli_fetch_array($sql);
    $contact_note_type = sanitizeInput($row['contact_note_type']);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_id = intval($row['contact_id']);

    mysqli_query($mysqli,"DELETE FROM contact_notes WHERE contact_note_id = $contact_note_id");

    //Logging
    logAction("Contact", "Edit", "$session_name deleted $contact_note_type note for $contact_name", $client_id, $contact_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Note <strong>$contact_note_type</strong> deleted.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_assign_contact_location'])) {

    enforceUserPermission('module_client', 2);

    $location_id = intval($_POST['bulk_location_id']);

    // Get Location name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    // Assign Location to Selected Contacts
    if (isset($_POST['contact_ids'])) {
        
        // Get Selected Contacts Count
        $contact_count = count($_POST['contact_ids']);
        
        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_location_id = $location_id WHERE contact_id = $contact_id");

            // Logging
            logAction("Contact", "Edit", "$session_name assigned $contaxt_name to location $location_name", $client_id, $contact_id);

        } // End Assign Location Loop

        // Bulk Log
        logAction("Contact", "Bulk Edit", "$session_name assigned $contact_count contacts to location $location_name", $client_id);

        $_SESSION['alert_message'] = "You assigned <b>$contact_count</b> contacts to location <b>$location_name</b>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_contact_phone'])) {

    enforceUserPermission('module_client', 2);

    $phone = preg_replace("/[^0-9]/", '', $_POST['bulk_phone']);

    // Assign Location to Selected Contacts
    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $contact_count = count($_POST['contact_ids']);

        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_phone = '$phone' WHERE contact_id = $contact_id");

            // Logging
            logAction("Contact", "Edit", "$session_name set Phone Number to $phone for $contact_name", $client_id, $contact_id);

        } // End Assign Location Loop
        // Bulk Log
            logAction("Contact", "Bulk Edit", "$session_name set the Phone Number $phone for $contact_count contacts", $client_id);

        $_SESSION['alert_message'] = "You set Phone Number <b>" . formatPhoneNumber($phone) . "</b> on $contact_count</b> contacts";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_contact_department'])) {

    enforceUserPermission('module_client', 2);

    $department = sanitizeInput($_POST['bulk_department']);

    // Assign Location to Selected Contacts
    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $contact_count = count($_POST['contact_ids']);

        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_department = '$department' WHERE contact_id = $contact_id");

            //Logging
            logAction("Contact", "Edit", "$session_name set Department to $department for $contact_name", $client_id, $contact_id);

        } // End Assign Location Loop

        // Bulk Log
        logAction("Contact", "Bulk Edit", "$session_name set the department $department for $contact_count contacts", $client_id);

        $_SESSION['alert_message'] = "You set the Department to <strong>$department</strong> for <strong>$contact_count</strong> contacts";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_contact_role'])) {

    enforceUserPermission('module_client', 2);

    $contact_important = intval($_POST['bulk_contact_important']);
    $contact_billing = intval($_POST['bulk_contact_billing']);
    $contact_technical = intval($_POST['bulk_contact_technical']);

    // Assign Location to Selected Contacts
    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $contact_count = count($_POST['contact_ids']);

        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            mysqli_query($mysqli,"UPDATE contacts SET contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical WHERE contact_id = $contact_id");

            //Logging
            logAction("Contact", "Edit", "$session_name updated the contact role for $contact_name", $client_id, $contact_id);

            customAction('contact_update', $contact_id);

        } // End Assign Location Loop

         // Bulk Log
        logAction("Contact", "Bulk Edit", "$session_name edited the contact role for $contact_count contacts", $client_id);

        $_SESSION['alert_message'] = "You updated contact roles for <b>$contact_count</b> contacts";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_assign_contact_tags'])) {

    enforceUserPermission('module_client', 2);

    // Assign Location to Selected Contacts
    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['contact_ids']);

        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            if($_POST['bulk_remove_tags']) {
                // Delete tags if chosed to do so
                mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
            }

            // Add new tags
            if (isset($_POST['bulk_tags'])) {
                foreach($_POST['bulk_tags'] as $tag) {
                    $tag = intval($tag);

                    $sql = mysqli_query($mysqli,"SELECT * FROM contact_tags WHERE contact_id = $contact_id AND tag_id = $tag");
                    if (mysqli_num_rows($sql) == 0) {
                        mysqli_query($mysqli, "INSERT INTO contact_tags SET contact_id = $contact_id, tag_id = $tag");
                    }
                }
            }

            //Logging
            logAction("Contact", "Edit", "$session_name added tags to $contact_name", $client_id, $contact_id);

        } // End Assign Location Loop

        // Bulk Log
        logAction("Contact", "Bulk Edit", "$session_name added tags for $contact_count contacts", $client_id);

        $_SESSION['alert_message'] = "You assigned tags for <strong>$count</strong> contacts";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_archive_contacts'])) {

    enforceUserPermission('module_client', 2);

    //validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['contact_ids'])) {

        $count = 0; // Default 0

        // Cycle through array and archive each contact
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Contact Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_primary, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $contact_primary = intval($row['contact_primary']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            // Archive Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"UPDATE users SET user_archived_at = NOW() WHERE user_id = $contact_user_id");
            }


            if($contact_primary == 0) {
                mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0, contact_archived_at = NOW() WHERE contact_id = $contact_id");

                // Individual Contact logging
                logAction("Contact", "Archive", "$session_name archived $contact_name", $client_id, $contact_id);
                
                $count++;
            }

        }

        // Bulk Logging
        logAction("Contact", "Bulk Archive", "$session_name archived $count contacts", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived $count contact(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_contacts'])) {

    enforceUserPermission('module_client', 2);
    //validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['contact_ids']);

        // Cycle through array and unarchive each contact
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Contact Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            // unArchive Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"UPDATE users SET user_archived_at = NULL WHERE user_id = $contact_user_id");
            }

            mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NULL WHERE contact_id = $contact_id");

            // Individual Contact logging
            logAction("Contact", "Unarchive", "$session_name unarchived $contact_name", $client_id, $contact_id);

        }

        // Bulk Logging
        logAction("Contact", "Bulk Unarchive", "$session_name Unarchived $count contacts", $client_id);

        $_SESSION['alert_message'] = "You unarchived <strong>$count</strong> contact(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_contacts'])) {

    enforceUserPermission('module_client', 3);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['contact_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            // Delete Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $contact_user_id");
            }

            mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id");

            // Remove Relations
            mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_assets WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_documents WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_files WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_logins WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_notes WHERE contact_note_contact_id = $contact_id");

            // Individual Logging
            logAction("Contact", "Delete", "$session_name deleted $contact_name", $client_id);

        }

        // Bulk Logging
         logAction("Contact", "Bulk Delete", "$session_name deleted $count contacts", $client_id);

        $_SESSION['alert_message'] = "You deleted <strong>$count</strong> contact(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['anonymize_contact'])) {

    enforceUserPermission('module_client', 3);

    $contact_id = intval($_GET['anonymize_contact']);

    // Get contact & client info
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);

    $contact_name = sanitizeInput($row['contact_name']);
    $contact_first_name = explode(" ", $contact_name)[0];
    $contact_email = sanitizeInput($row['contact_email']);
    $contact_phone = sanitizeInput($row['contact_phone']);
    $info_to_redact = array($contact_name, $contact_first_name, $contact_email, $contact_phone);

    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

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
    mysqli_query($mysqli,"UPDATE contacts SET contact_location_id = '0' WHERE contact_id = $contact_id");

    // Remove Billing, Technical, Important Roles
    mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0 WHERE contact_id = $contact_id");

    // Archive Contact User
    if ($contact_user_id > 0) {
        $unix_timestamp = time();

        mysqli_query($mysqli,"UPDATE users SET user_name = 'Archived - $unix_timestamp', user_email = 'Archived - $unix_timestamp', user_archived_at = NOW() WHERE user_id = $contact_user_id");
    }


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
    logAction("Contact", "Archive", "$session_name archived and anonymized contact", $client_id, $contact_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact $contact_name anonymized & archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['archive_contact'])) {

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_GET['archive_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    // Archive Contact User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"UPDATE users SET user_archived_at = NOW() WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0, contact_archived_at = NOW() WHERE contact_id = $contact_id");
    
    // Logging
    logAction("Contact", "Archive", "$session_name archived contact $contact_name", $client_id, $contact_id);


    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> has been archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_contact'])) {

    validateAdminRole();

    $contact_id = intval($_GET['unarchive_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    // unArchive Contact User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"UPDATE users SET user_archived_at = NULL WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NULL WHERE contact_id = $contact_id");

    // logging
    logAction("Contact", "Unarchive", "$session_name unarchived contact $contact_name", $client_id, $contact_id);

    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> has been Unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_contact'])) {

    enforceUserPermission('module_client', 3);

    $contact_id = intval($_GET['delete_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    // Delete User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id");

    // Remove Relations
    mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_assets WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_documents WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_files WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_logins WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_notes WHERE contact_note_contact_id = $contact_id");

    //Logging
    logAction("Contact", "Delete", "$session_name deleted contact $contact_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> has been deleted.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_contact_to_asset'])) {

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_POST['asset_id']);
    $contact_id = intval($_POST['contact_id']);

    // Get Asset Name and Client ID for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"UPDATE assets SET asset_contact_id = $contact_id WHERE asset_id = $asset_id");

    // Logging
    logAction("Asset", "Link", "$session_name linked asset $asset_name to contact $contact_name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> linked with asset <strong>$asset_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_asset_from_contact'])) {

    enforceUserPermission('module_support', 2);

    $contact_id = intval($_GET['contact_id']);
    $asset_id = intval($_GET['asset_id']);

    // Get asset Name and Client ID for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"UPDATE assets SET asset_contact_id = 0 WHERE asset_id = $asset_id");

    //Logging
    logAction("Asset", "Unlink", "$session_name unlinked contact $contact_name from asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> unlinked from Contact <strong>$contact_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_contact_to_credential'])) {

    enforceUserPermission('module_support', 2);

    $login_id = intval($_POST['login_id']);
    $contact_id = intval($_POST['contact_id']);

    // Get login Name and Client ID for logging
    $sql_login = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
    $row = mysqli_fetch_array($sql_login);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"UPDATE logins SET login_contact_id = $contact_id WHERE login_id = $login_id");

    // Logging
    logAction("Asset", "Link", "$session_name linked credential $login_name to contact $contact_name", $client_id, $login_id);

    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> linked with credential <strong>$login_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_credential_from_contact'])) {

    enforceUserPermission('module_support', 2);

    $contact_id = intval($_GET['contact_id']);
    $login_id = intval($_GET['login_id']);

    // Get login Name and Client ID for logging
    $sql_login = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
    $row = mysqli_fetch_array($sql_login);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"UPDATE logins SET login_contact_id = 0 WHERE login_id = $login_id");

    //Logging
    logAction("Credential", "Unlink", "$session_name unlinked contact $contact_name from credential $login_name", $client_id, $login_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Credential <strong>$login_name</strong> unlinked from Contact <strong>$contact_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_service_to_contact'])) {

    enforceUserPermission('module_support', 2);

    $service_id = intval($_POST['service_id']);
    $contact_id = intval($_POST['contact_id']);

    // Get service Name and Client ID for logging
    $sql_service = mysqli_query($mysqli,"SELECT service_name, service_client_id FROM services WHERE service_id = $service_id");
    $row = mysqli_fetch_array($sql_service);
    $service_name = sanitizeInput($row['service_name']);
    $client_id = intval($row['service_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"INSERT INTO service_contacts SET contact_id = $contact_id, service_id = $service_id");

    // Logging
    logAction("Service", "Link", "$session_name linked contact $contact_name to service $service_name", $client_id, $service_id);

    $_SESSION['alert_message'] = "service <strong>$service_name</strong> linked with contact <strong>$contact_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_service_from_contact'])) {

    enforceUserPermission('module_support', 2);

    $contact_id = intval($_GET['contact_id']);
    $service_id = intval($_GET['service_id']);

    // Get service Name and Client ID for logging
    $sql_service = mysqli_query($mysqli,"SELECT service_name, service_client_id FROM services WHERE service_id = $service_id");
    $row = mysqli_fetch_array($sql_service);
    $service_name = sanitizeInput($row['service_name']);
    $client_id = intval($row['service_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"DELETE FROM service_contacts WHERE contact_id = $contact_id AND service_id = $service_id");

    //Logging
    logAction("service", "Unlink", "$session_name unlinked contact $contact_name from service $service_name", $client_id, $service_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> unlinked from service <strong>$service_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_contact_to_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $contact_id = intval($_POST['contact_id']);

    // Get file Name and Client ID for logging
    $sql_file = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO contact_files SET contact_id = $contact_id, file_id = $file_id");

    // Logging
    logAction("File", "Link", "$session_name linked contact $contact_name to file $file_name", $client_id, $file_id);

    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> linked with File <strong>$file_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_contact_from_file'])) {

    enforceUserPermission('module_support', 2);

    $contact_id = intval($_GET['contact_id']);
    $file_id = intval($_GET['file_id']);

    // Get file Name and Client ID for logging
    $sql_file = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"DELETE FROM contact_files WHERE contact_id = $contact_id AND file_id = $file_id");

    //Logging
    logAction("File", "Unlink", "$session_name unlinked contact $contact_name from file $file_name", $client_id, $file_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> unlinked from file <strong>$file_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['export_client_contacts_csv'])) {

    enforceUserPermission('module_client');

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
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
    logAction("Contact", "Export", "$session_name exported $num_rows contact(s) to a CSV file", $client_id);

    exit;

}

if (isset($_POST["import_client_contacts_csv"])) {

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);
    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        $_SESSION['alert_message'] = "Please select a file to upload.";
        $_SESSION['alert_type'] = "error";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

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
        logAction("Contact", "Import", "$session_name imported $row_count contact(s) via CSV file", $client_id);

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
