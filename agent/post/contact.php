<?php

/*
 * ITFlow - GET/POST request handler for client contacts
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    require_once 'contact_model.php';

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess($client_id);

    // Only one of the client's own locations
    $location_id = assetClientOwnedId('location', $location_id, $client_id);

    // Create User Account
    $user_id = 0;
    if ($name && $email && $auth_method) {

        // Set password
        if ($contact_password !== '') {
            $password_hash = password_hash($contact_password, PASSWORD_DEFAULT);
        } else {
            // Set a random password
            $password_hash = password_hash(randomString(), PASSWORD_DEFAULT);
        }

        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password_hash', user_auth_method = '$auth_method', user_type = 2");

        $user_id = mysqli_insert_id($mysqli);
    }

    mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone_country_code = '$phone_country_code', contact_phone = '$phone', contact_extension = '$extension', contact_mobile_country_code = '$mobile_country_code', contact_mobile = '$mobile', contact_email = '$email', contact_pin = '$pin', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_department = '$department', contact_location_id = $location_id, contact_user_id = $user_id, contact_client_id = $client_id");

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
    if (!empty($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            if (!file_exists("../uploads/clients/$client_id")) {
                mkdir("../uploads/clients/$client_id", 0755, true);
            }
            $upload_file_dir = "../uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");

        }
    }

    logAudit("Contact", "Create", "$session_name created contact $name", $client_id, $contact_id);

    triggerCustomAction('contact_create', $contact_id);

    flashAlert("Contact <strong>" . stripslashes($name) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    require_once 'contact_model.php';

    $contact_id = intval($_POST['contact_id']);
    $send_email = intval($_POST['send_email'] ?? 0);

    // Get Contact Info
    $sql = mysqli_query($mysqli,"SELECT contact_photo, contact_user_id, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_assoc($sql);
    if (!$row) {
        flashAlert("That contact no longer exists", 'error');
        redirect();
    }
    $existing_file_name = basename((string) $row['contact_photo']);
    $contact_user_id = intval($row['contact_user_id']);
    $client_id = intval($row['contact_client_id']);

    enforceClientAccess();

    // Only one of the client's own locations
    $location_id = assetClientOwnedId('location', $location_id, $client_id);

    if (!file_exists("../uploads/clients/$client_id")) {
        mkdir("../uploads/clients/$client_id", 0755, true);
    }

    // Update Existing User - their portal access only when the form had it
    if ($contact_user_id > 0) {
        mysqli_query($mysqli, "UPDATE users SET user_name = '$name', user_email = '$email' WHERE user_id = $contact_user_id");
        if ($auth_method_posted) {
            mysqli_query($mysqli, "UPDATE users SET user_auth_method = '$auth_method' WHERE user_id = $contact_user_id");
        }

        // Set password
        if ($contact_password !== '') {
            $password_hash = password_hash($contact_password, PASSWORD_DEFAULT);
            mysqli_query($mysqli, "UPDATE users SET user_password = '$password_hash' WHERE user_id = $contact_user_id");
        }
    // Create New User
    } elseif ($contact_user_id == 0 && $name && $email && $auth_method) {

        // Set password
        if ($contact_password !== '') {
            $password_hash = password_hash($contact_password, PASSWORD_DEFAULT);
        } else {
            // Set a random password
            $password_hash = password_hash(randomString(), PASSWORD_DEFAULT);
        }

        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password_hash', user_auth_method = '$auth_method', user_type = 2");

        $contact_user_id = mysqli_insert_id($mysqli);

    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_phone_country_code = '$phone_country_code', contact_phone = '$phone', contact_extension = '$extension', contact_mobile_country_code = '$mobile_country_code', contact_mobile = '$mobile', contact_email = '$email', contact_pin = '$pin', contact_notes = '$notes', contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical, contact_department = '$department', contact_location_id = $location_id, contact_user_id = $contact_user_id WHERE contact_id = $contact_id");

    // Upload Photo - replacing the old one, if there was one
    $old_photo_path = $existing_file_name !== '' ? "../uploads/clients/$client_id/$existing_file_name" : '';
    if (!empty($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            // Set directory in which the uploaded file will be moved
            $file_tmp_path = $_FILES['file']['tmp_name'];
            $upload_file_dir = "../uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            // Delete old file
            if ($old_photo_path && is_file($old_photo_path)) {
                unlink($old_photo_path);
            }

            mysqli_query($mysqli,"UPDATE contacts SET contact_photo = '$new_file_name' WHERE contact_id = $contact_id");

        }
    } elseif (!empty($_POST['remove_photo'])) {
        if ($old_photo_path && is_file($old_photo_path)) {
            unlink($old_photo_path);
        }
        mysqli_query($mysqli,"UPDATE contacts SET contact_photo = NULL WHERE contact_id = $contact_id");
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
    if ($send_email && $auth_method && $config_smtp_provider && $contact_user_id) {

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_email = escapeSql($config_ticket_from_email);
        $config_ticket_from_name = escapeSql($config_ticket_from_name);
        $config_mail_from_email = escapeSql($config_mail_from_email);
        $config_mail_from_name = escapeSql($config_mail_from_name);
        $config_base_url = escapeSql($config_base_url);

        // Get Company Phone Number
        $sql = mysqli_query($mysqli,"SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);
        $company_name = escapeSql($row['company_name']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone']));

        // Authentication info (azure, reset password, or tech-provided temporary password)

        if ($auth_method == 'azure') {
            $password_info = "Login with your Microsoft (Azure AD) account.";
        } elseif ($contact_password === '') {
            $password_info = "Request a password reset at https://$config_base_url/client/login_reset.php";
        } else {
            $password_info = mysqli_real_escape_string($mysqli, $contact_password . " -- Please change on first login");
        }

        $subject = "Your new $company_name portal account";
        $body = "Hello $name,<br><br>$company_name has created a support portal account for you. <br><br>Username: $email<br>Password: $password_info<br><br>Login URL: https://$config_base_url/client/<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

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
        addToMailQueue($data);
        // Get Email ID for reference
        $email_id = mysqli_insert_id($mysqli);

    }

    logAudit("Contact", "Edit", "$session_name edited contact $name", $client_id, $contact_id);

    triggerCustomAction('contact_update', $contact_id);

    flashAlert("Contact <strong>" . stripslashes($name) . "</strong> updated");

    redirect();

}

if (isset($_POST['add_contact_note'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_POST['contact_id']);
    $type = escapeSql($_POST['type']);
    $note = escapeSql($_POST['note']);

    // Get Contact details for logging and alerting
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_assoc($sql);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "INSERT INTO contact_notes SET contact_note_type = '$type', contact_note = '$note', contact_note_created_by = $session_user_id, contact_note_contact_id = $contact_id");

    $contact_note_id = mysqli_insert_id($mysqli);

    //Logging
    logAudit("Contact", "Edit", "$session_name created a $type note for contact $contact_name", $client_id, $contact_id);

    $_SESSION['alert_message'] = "Note <strong>$type</strong> created for <strong>$contact_name</strong>";

    redirect();

}

if (isset($_GET['archive_contact_note'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_note_id = intval($_GET['archive_contact_note']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_note_type, contact_id, contact_name, contact_client_id FROM contact_notes LEFT JOIN contacts ON contact_id = contact_note_contact_id WHERE contact_note_id = $contact_note_id");
    $row = mysqli_fetch_assoc($sql);
    $contact_note_type = escapeSql($row['contact_note_type']);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_id = intval($row['contact_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE contact_notes SET contact_note_archived_at = NOW() WHERE contact_note_id = $contact_note_id");

    logAudit("Contact", "Edit", "$session_name archived note $contact_note_type for $contact_name", $client_id, $contact_id);

    flashAlert("Note <strong>$contact_note_type</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_contact_note'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_note_id = intval($_GET['unarchive_contact_note']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_note_type, contact_id, contact_name, contact_client_id FROM contact_notes LEFT JOIN contacts ON contact_id = contact_note_contact_id WHERE contact_note_id = $contact_note_id");
    $row = mysqli_fetch_assoc($sql);
    $contact_note_type = escapeSql($row['contact_note_type']);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_id = intval($row['contact_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE contact_notes SET contact_note_archived_at = NULL WHERE contact_note_id = $contact_note_id");

    logAudit("Contact", "Edit", "$session_name restored note $contact_note_type for $contact_name", $client_id, $contact_id);

    flashAlert("Note <strong>$contact_note_type</strong> restored");

    redirect();

}

if (isset($_GET['delete_contact_note'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 3);

    $contact_note_id = intval($_GET['delete_contact_note']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_note_type, contact_id, contact_name, contact_client_id FROM contact_notes LEFT JOIN contacts ON contact_id = contact_note_contact_id WHERE contact_note_id = $contact_note_id");
    $row = mysqli_fetch_assoc($sql);
    $contact_note_type = escapeSql($row['contact_note_type']);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_id = intval($row['contact_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM contact_notes WHERE contact_note_id = $contact_note_id");

    logAudit("Contact", "Edit", "$session_name deleted $contact_note_type note for $contact_name", $client_id, $contact_id);

    flashAlert("Note <strong>$contact_note_type</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_assign_contact_location'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $location_id = intval($_POST['bulk_location_id']);

    // Get Location name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_assoc($sql);
    $location_name = escapeSql($row['location_name'] ?? '');
    $client_id = intval($row['location_client_id'] ?? 0);

    enforceClientAccess();

    // Assign Location to Selected Contacts
    if (isset($_POST['contact_ids'])) {

        // Only the contacts of the location's client are moved
        $contact_count = 0;

        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_assoc($sql);
            if (!$row || intval($row['contact_client_id']) !== $client_id) {
                continue;
            }
            $contact_name = escapeSql($row['contact_name']);
            $contact_count++;

            mysqli_query($mysqli,"UPDATE contacts SET contact_location_id = $location_id WHERE contact_id = $contact_id");

            logAudit("Contact", "Edit", "$session_name assigned $contact_name to location $location_name", $client_id, $contact_id);

        } // End Assign Location Loop

        logAudit("Contact", "Bulk Edit", "$session_name assigned $contact_count contacts to location $location_name", $client_id);

        flashAlert("<strong>$contact_count</strong> contact" . ($contact_count === 1 ? '' : 's') . " assigned to location <strong>" . stripslashes($location_name) . "</strong>");
    }

    redirect();

}

if (isset($_POST['bulk_edit_contact_phone'])) {

    validateCSRFToken();

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
            $row = mysqli_fetch_assoc($sql);
            $contact_name = escapeSql($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE contacts SET contact_phone = '$phone' WHERE contact_id = $contact_id");

            logAudit("Contact", "Edit", "$session_name set Phone Number to $phone for $contact_name", $client_id, $contact_id);

        } // End Assign Location Loop

        logAudit("Contact", "Bulk Edit", "$session_name set the Phone Number $phone for $contact_count contacts", $client_id);

        flashAlert("Phone number set to <strong>" . formatPhoneNumber($phone) . "</strong> on <strong>$contact_count</strong> contact" . ($contact_count === 1 ? '' : 's'));
    }

    redirect();

}

if (isset($_POST['bulk_edit_contact_department'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $department = escapeSql($_POST['bulk_department']);

    // Assign Location to Selected Contacts
    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $contact_count = count($_POST['contact_ids']);

        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_assoc($sql);
            $contact_name = escapeSql($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE contacts SET contact_department = '$department' WHERE contact_id = $contact_id");

            logAudit("Contact", "Edit", "$session_name set Department to $department for $contact_name", $client_id, $contact_id);

        } // End Assign Location Loop

        logAudit("Contact", "Bulk Edit", "$session_name set the department $department for $contact_count contacts", $client_id);

        flashAlert("Department set to <strong>" . stripslashes($department) . "</strong> on <strong>$contact_count</strong> contact" . ($contact_count === 1 ? '' : 's'));
    }

    redirect();

}

if (isset($_POST['bulk_edit_contact_role'])) {

    validateCSRFToken();

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
            $row = mysqli_fetch_assoc($sql);
            $contact_name = escapeSql($row['contact_name']);
            $client_id = intval($row['contact_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE contacts SET contact_important = $contact_important, contact_billing = $contact_billing, contact_technical = $contact_technical WHERE contact_id = $contact_id");

            logAudit("Contact", "Edit", "$session_name updated the contact role for $contact_name", $client_id, $contact_id);

            triggerCustomAction('contact_update', $contact_id);

        } // End Assign Location Loop

        logAudit("Contact", "Bulk Edit", "$session_name edited the contact role for $contact_count contacts", $client_id);

        flashAlert("Roles updated on <strong>$contact_count</strong> contact" . ($contact_count === 1 ? '' : 's'));
    }

    redirect();

}

if (isset($_POST['send_bulk_mail_now'])) {

    validateCSRFToken();

    enforceUserPermission('module_client');

    if (isset($_POST['contact_ids'])) {

        $count = count($_POST['contact_ids']);

        $mail_from = escapeSql($_POST['mail_from']);
        $mail_from_name = escapeSql($_POST['mail_from_name']);
        $subject = escapeSql($_POST['subject']);
        $body = mysqli_escape_string($mysqli, $_POST['body']);
        $queued_at = escapeSql($_POST['queued_at']);

        // Add Emails
        foreach($_POST['contact_ids'] as $contact_id) {
            $contact_id = intval($contact_id);

            $sql = mysqli_query($mysqli,"SELECT contact_client_id, contact_email, contact_name FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_assoc($sql);
            $contact_name = escapeSql($row['contact_name']);
            $contact_email = escapeSql($row['contact_email']);
            $client_id = intval($row['contact_client_id']);

            enforceClientAccess();

            // Queue Mail
            $data[] = [
                'from' => $mail_from,
                'from_name' => $mail_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body,
                'queued_at' => $queued_at
            ];
        }
        addToMailQueue($data);

        logAudit("Bulk Mail", "Send", "$session_name sent $count messages via bulk mail");

        flashAlert("<strong>$count</strong> messages queued");

    }

    redirect();

}

if (isset($_POST['bulk_archive_contacts'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    if (isset($_POST['contact_ids'])) {

        $count = 0; // Default 0

        // Cycle through array and archive each contact
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Contact Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_primary, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_assoc($sql);
            $contact_name = escapeSql($row['contact_name']);
            $contact_primary = intval($row['contact_primary']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            enforceClientAccess();

            // Archive Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"UPDATE users SET user_archived_at = NOW() WHERE user_id = $contact_user_id");
            }

            if($contact_primary == 0) {
                mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0, contact_archived_at = NOW() WHERE contact_id = $contact_id");

                // Individual Contact logging
                logAudit("Contact", "Archive", "$session_name archived $contact_name", $client_id, $contact_id);

                $count++;
            }

        }

        logAudit("Contact", "Bulk Archive", "$session_name archived $count contacts", $client_id);

        flashAlert("Archived $count contact(s)", 'error');

    }

    redirect();
}

if (isset($_POST['bulk_restore_contacts'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['contact_ids']);

        // Cycle through array and unarchive each contact
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Contact Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_assoc($sql);
            $contact_name = escapeSql($row['contact_name']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            enforceClientAccess();

            // unArchive Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"UPDATE users SET user_archived_at = NULL WHERE user_id = $contact_user_id");
            }

            mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NULL WHERE contact_id = $contact_id");

            logAudit("Contact", "Restore", "$session_name restored $contact_name", $client_id, $contact_id);

        }

        logAudit("Contact", "Bulk Restore", "$session_name restored $count contacts", $client_id);

        flashAlert("Restored <strong>$count</strong> contact(s)");

    }

    redirect();
}

if (isset($_POST['bulk_delete_contacts'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 3);

    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['contact_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_assoc($sql);
            $contact_name = escapeSql($row['contact_name']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            enforceClientAccess();

            // Delete Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $contact_user_id");
            }

            mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id");
            credentialsForget('contact', $contact_id);

            logAudit("Contact", "Delete", "$session_name deleted $contact_name", $client_id);

        }

        logAudit("Contact", "Bulk Delete", "$session_name deleted $count contacts", $client_id);

        flashAlert("Deleted <strong>$count</strong> contact(s)", 'error');

    }

    redirect();

}

if (isset($_GET['anonymize_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 3);

    $contact_id = intval($_GET['anonymize_contact']);

    // Get contact & client info
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, contact_phone, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_assoc($sql);

    $contact_name = escapeSql($row['contact_name']);
    $contact_first_name = explode(" ", $contact_name)[0];
    $contact_email = escapeSql($row['contact_email']);
    $contact_phone = escapeSql($row['contact_phone']);
    $info_to_redact = array($contact_name, $contact_first_name, $contact_email, $contact_phone);

    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    enforceClientAccess();

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
    $log_sql = mysqli_query($mysqli, "SELECT log_description, log_id FROM logs WHERE log_client_id =  $client_id");
    while ($log = mysqli_fetch_assoc($log_sql)) {
        $log_id = intval($log['log_id']);
        $description = $log['log_description'];
        $description = str_ireplace($info_to_redact, "*****", $description);
        $description = escapeSql($description);

        mysqli_query($mysqli,"UPDATE logs SET log_description = '$description' WHERE log_id = $log_id AND log_client_id = $client_id");
    }

    // Get all tickets this contact raised
    $contact_tickets_sql = mysqli_query($mysqli, "SELECT ticket_details, ticket_id, ticket_subject FROM tickets WHERE ticket_client_id = $client_id AND ticket_contact_id =  $contact_id");
    while ($ticket = mysqli_fetch_assoc($contact_tickets_sql)) {

        $ticket_id = intval($ticket['ticket_id']);

        // Redact contact name or email in the subject of all tickets they raised
        $subject = $ticket['ticket_subject'];
        $subject = str_ireplace($info_to_redact, "*****", $subject);
        $subject = escapeSql($subject);
        mysqli_query($mysqli,"UPDATE tickets SET ticket_subject = '$subject' WHERE ticket_id = $ticket_id");

        // Redact contact name or email in the description of all tickets they raised
        $details = $ticket['ticket_details'];

        $details = str_ireplace($info_to_redact, "*****", $details);
        $details = escapeSql($details);
        mysqli_query($mysqli,"UPDATE tickets SET ticket_details = '$details' WHERE ticket_id = $ticket_id");

        // Redact contact name or email in the replies of all tickets they raised
        $ticket_replies_sql = mysqli_query($mysqli, "SELECT ticket_reply, ticket_reply_id FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");

        while($ticket_reply = mysqli_fetch_assoc($ticket_replies_sql)) {
            $ticket_reply_id = intval($ticket_reply['ticket_reply_id']);
            $ticket_reply_details = $ticket_reply['ticket_reply'];
            $ticket_reply_details = str_ireplace($info_to_redact, "*****", $ticket_reply_details);
            $ticket_reply_details = escapeSql($ticket_reply_details);

            mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply = '$ticket_reply_details'
                WHERE ticket_reply_id = $ticket_reply_id"
            );
        }

    }

    // Archive contact
    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NOW() WHERE contact_id = $contact_id");

    logAudit("Contact", "Archive", "$session_name archived and anonymized contact", $client_id, $contact_id);

    flashAlert("Contact <strong>" . stripslashes($contact_name) . "</strong> anonymized and archived", 'error');

    redirect();

}

if (isset($_GET['portal_view_as'])) {

    /*
     * View as contact - the client portal as this contact sees it, read only,
     * in this browser (functions/portal.php). Needs read access to clients and
     * support and access to the client; billing shows when the agent's role
     * also reads sales and financial. Logged, and the portal says so on every
     * page until they leave it.
     */
    validateCSRFToken();

    $contact_id = intval($_GET['portal_view_as']);
    $access = portalViewAsAccess($session_user_id, $contact_id);
    if (!$access) {
        flashAlert("You can't view the portal as that contact - it needs the client portal on, read access to clients and support, and the client", 'error');
        redirect();
    }

    // A portal sign-in left over in this browser would take the place of the view
    unset($_SESSION['client_logged_in'], $_SESSION['client_id'], $_SESSION['contact_id']);
    $_SESSION['portal_view_as'] = ['contact_id' => $contact_id, 'started' => time()];

    logAudit("Contact", "View As", "$session_name viewed the client portal as " . escapeSql($access['contact_name']), $access['client_id'], $contact_id);

    redirect('/client/index.php');

}

if (isset($_GET['reset_contact_portal_mfa'])) {

    // Turn off a contact's two-step sign-in for the client portal - a lost phone
    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_GET['reset_contact_portal_mfa']);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id"));
    $client_id = intval($row['contact_client_id'] ?? 0);

    enforceClientAccess();

    if (!$row || !intval($row['contact_user_id'])) {
        flashAlert("That contact has no client portal account", 'error');
        redirect();
    }

    portalMfaReset(intval($row['contact_user_id']));
    logAudit("Contact", "Edit", "$session_name turned off two-step sign-in for " . escapeSql($row['contact_name']) . " in the client portal", $client_id, $contact_id);
    portalAccountEmail($row['contact_email'], $row['contact_name'], 'Two-step sign-in was turned off for your client portal account',
        ['We turned off two-step sign-in for your client portal account, so it asks only for your password now.', 'You can turn it back on from Your account in the portal.']);

    flashAlert("Two-step sign-in is off for <strong>" . escapeHtml($row['contact_name']) . "</strong> - they sign in with their password alone until they set it up again");

    redirect();

}

if (isset($_GET['archive_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_GET['archive_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_assoc($sql);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    enforceClientAccess();

    // Archive Contact User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"UPDATE users SET user_archived_at = NOW() WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0, contact_archived_at = NOW() WHERE contact_id = $contact_id");

    logAudit("Contact", "Archive", "$session_name archived contact $contact_name", $client_id, $contact_id);

    flashAlert("Contact <strong>" . stripslashes($contact_name) . "</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_GET['restore_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_assoc($sql);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    enforceClientAccess();

    // unArchive Contact User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"UPDATE users SET user_archived_at = NULL WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NULL WHERE contact_id = $contact_id");

    logAudit("Contact", "Restore", "$session_name restored contact $contact_name", $client_id, $contact_id);

    flashAlert("Contact <strong>" . stripslashes($contact_name) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 3);

    $contact_id = intval($_GET['delete_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_assoc($sql);
    $contact_name = escapeSql($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    enforceClientAccess();

    // Delete User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id");

    // Their logins stay with the client, no longer pointing at a contact that is gone
    credentialsForget('contact', $contact_id);

    logAudit("Contact", "Delete", "$session_name deleted contact $contact_name", $client_id);

    flashAlert("Contact <strong>" . stripslashes($contact_name) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['link_contact_to_asset'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $asset_id = intval($_POST['asset_id']);
    $contact_id = intval($_POST['contact_id']);

    // Get Asset Name and Client ID for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_assoc($sql_asset);
    $asset_name = escapeSql($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    enforceClientAccess();

    // Get Contact Name for logging
    $contact_name = escapeSql(getFieldById('contacts', $contact_id, 'contact_name'));

    mysqli_query($mysqli,"UPDATE assets SET asset_contact_id = $contact_id WHERE asset_id = $asset_id");

    logAudit("Asset", "Link", "$session_name linked asset $asset_name to contact $contact_name", $client_id, $asset_id);

    flashAlert("Contact <strong>" . stripslashes($contact_name) . "</strong> linked with asset <strong>" . stripslashes($asset_name) . "</strong>");

    redirect();

}

if (isset($_GET['unlink_asset_from_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_GET['contact_id']);
    $asset_id = intval($_GET['asset_id']);

    // Get asset Name and Client ID for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_assoc($sql_asset);
    $asset_name = escapeSql($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    enforceClientAccess();

    // Get Contact Name for logging
    $contact_name = escapeSql(getFieldById('contacts', $contact_id, 'contact_name'));

    mysqli_query($mysqli,"UPDATE assets SET asset_contact_id = 0 WHERE asset_id = $asset_id");

    logAudit("Asset", "Unlink", "$session_name unlinked contact $contact_name from asset $asset_name", $client_id, $asset_id);

    flashAlert("Asset <strong>" . stripslashes($asset_name) . "</strong> unlinked from contact <strong>" . stripslashes($contact_name) . "</strong>", 'error');

    redirect();

}

// Licenses are linked in post/link.php (link_software_to_contact)

if (isset($_POST['link_contact_to_credential'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);
    // It changes the credential, so it takes credential edit rights too
    enforceUserPermission('module_credential', 2);

    $credential_id = intval($_POST['credential_id']);
    $contact_id = intval($_POST['contact_id']);

    // Get credential Name and Client ID for logging
    $sql_credential = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_assoc($sql_credential);
    if (!$row) {
        flashAlert("That credential no longer exists", 'error');
        redirect();
    }
    $credential_name = escapeSql($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    enforceClientAccess();

    // Only to one of the same client's people
    if (!assetClientOwnedId('contact', $contact_id, $client_id)) {
        flashAlert("That contact isn't one of this client's", 'error');
        redirect();
    }

    // Get Contact Name for logging
    $contact_name = escapeSql(getFieldById('contacts', $contact_id, 'contact_name'));

    mysqli_query($mysqli,"UPDATE credentials SET credential_contact_id = $contact_id WHERE credential_id = $credential_id");

    logAudit("Credential", "Link", "$session_name linked credential $credential_name to contact $contact_name", $client_id, $credential_id);

    flashAlert("Contact <strong>" . stripslashes($contact_name) . "</strong> linked with credential <strong>" . stripslashes($credential_name) . "</strong>");

    redirect();

}

if (isset($_GET['unlink_credential_from_contact'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);
    enforceUserPermission('module_credential', 2);

    $contact_id = intval($_GET['contact_id']);
    $credential_id = intval($_GET['credential_id']);

    // Get credential Name and Client ID for logging
    $sql_credential = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_assoc($sql_credential);
    $credential_name = escapeSql($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    enforceClientAccess();

    // Get Contact Name for logging
    $contact_name = escapeSql(getFieldById('contacts', $contact_id, 'contact_name'));

    // Only if it is this contact's - an old link can't unpick a newer one
    mysqli_query($mysqli,"UPDATE credentials SET credential_contact_id = 0 WHERE credential_id = $credential_id AND credential_contact_id = $contact_id");

    logAudit("Credential", "Unlink", "$session_name unlinked contact $contact_name from credential $credential_name", $client_id, $credential_id);

    flashAlert("Credential <strong>" . stripslashes($credential_name) . "</strong> unlinked from contact <strong>" . stripslashes($contact_name) . "</strong>", 'error');

    redirect();

}

if (isExportRequest('export_contacts')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_client');

    $format = resolveExportFormat($_POST['export_contacts']);

    // Filters inherited from the contacts page - mirrors agent/contacts.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND contact_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "contact_archived_at IS NOT NULL" : "contact_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (contact_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR contact_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND contact_archived_at IS NULL)";
    }

    // Tags Filter
    if (isset($_POST['tags']) && is_array($_POST['tags']) && !empty($_POST['tags'])) {
        $tag_filter = implode(",", array_map('intval', $_POST['tags']));
        $tag_query = "AND tags.tag_id IN ($tag_filter)";

        $tag_names = [];
        $sql_tags = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id IN ($tag_filter) ORDER BY tag_name ASC");
        while ($tag_row = mysqli_fetch_assoc($sql_tags)) {
            $tag_names[] = $tag_row['tag_name'];
        }
        $filter_summary['Tags'] = implode(', ', $tag_names);
    } else {
        // Default - any
        $tag_query = '';
    }

    // Location Filter
    if (!empty($_POST['location'])) {
        $filter_location_id = intval($_POST['location']);
        $location_query = "AND (contact_location_id = $filter_location_id)";
        $filter_summary['Location'] = getFieldById('locations', $filter_location_id, 'location_name');
    } else {
        // Default - any
        $location_query = '';
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT contacts.*, clients.*, locations.*, users.*
        FROM contacts
        LEFT JOIN clients ON client_id = contact_client_id
        LEFT JOIN locations ON location_id = contact_location_id
        LEFT JOIN users ON user_id = contact_user_id
        LEFT JOIN contact_tags ON contact_tags.contact_id = contacts.contact_id
        LEFT JOIN tags ON tags.tag_id = contact_tags.tag_id
        WHERE $archive_query
        $tag_query
        AND (contact_name LIKE '%$q%' OR contact_title LIKE '%$q%' OR location_name LIKE '%$q%' OR contact_email LIKE '%$q%' OR contact_department LIKE '%$q%' OR contact_phone LIKE '%$q%' OR contact_mobile LIKE '%$q%' OR client_name LIKE '%$q%' OR tag_name LIKE '%$q%')
        " . clientScopeSql('contact_client_id') . "
        $client_query
        $location_query
        GROUP BY contact_id
        ORDER BY contact_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('contacts', $format, $file_name_prepend . 'Contacts', 'Contacts', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Contact", "Export", "$session_name exported $num_rows contact(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

if (isset($_POST["import_contacts_csv"])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if (in_array($file_extension,$allowed_file_extensions) === false) {
        $error = true;
        flashAlert("Bad file extension", 'error');
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1) {
        $error = true;
        flashAlert("Bad file size (empty?)", 'error');
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 8) {
        $error = true;
        flashAlert("Bad column count.", 'error');
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
                $name = escapeSql($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT 1 FROM contacts WHERE contact_name = '$name' AND contact_client_id = $client_id")) > 0) {
                    $duplicate_detect = 1;
                }
            }
            if (isset($column[1])) {
                $title = escapeSql($column[1]);
            }
            if (isset($column[2])) {
                $department = escapeSql($column[2]);
            }
            if (isset($column[3])) {
                $email = escapeSql($column[3]);
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
                $location = escapeSql($column[7]);
                $sql_location = mysqli_query($mysqli,"SELECT location_id FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
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

        logAudit("Contact", "Import", "$session_name imported $row_count contact(s) via CSV file", $client_id);

        flashAlert("$row_count Contact(s) added, $duplicate_count duplicate(s) detected", 'warning');

        redirect();

    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        redirect();
    }

}

if (isset($_GET['download_contacts_csv_template'])) {

    $delimiter = ",";
    $enclosure = '"';
    $escape    = '\\';
    $filename = "Contacts-Template.csv";

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
    fputcsv($f, $fields, $delimiter, $enclosure, $escape);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}
