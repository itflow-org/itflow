<?php

/*
 * ITFlow - GET/POST request handler for client assets
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    require_once 'asset_model.php';

    $alert_extended = "";

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_reference = '$purchase_reference', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_physical_location = '$physical_location', asset_notes = '$notes', asset_client_id = $client_id");

    $asset_id = mysqli_insert_id($mysqli);

    // Add Photo
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

            mysqli_query($mysqli,"UPDATE assets SET asset_photo = '$new_file_name' WHERE asset_id = $asset_id");
        }
    }

    // Add Primary Interface
    mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = '1', interface_mac = '$mac', interface_ip = '$ip', interface_nat_ip = '$nat_ip', interface_ipv6 = '$ipv6', interface_primary = 1, interface_network_id = $network, interface_asset_id = $asset_id");


    if (!empty($_POST['username'])) {
        $username = trim(mysqli_real_escape_string($mysqli, encryptCredentialEntry($_POST['username'])));
        $password = trim(mysqli_real_escape_string($mysqli, encryptCredentialEntry($_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO credentials SET credential_name = '$name', credential_username = '$username', credential_password = '$password', credential_asset_id = $asset_id, credential_client_id = $client_id");

        $credential_id = mysqli_insert_id($mysqli);

        //Logging
        logAction("Credential", "Create", "$session_name created login credential for asset $asset_name", $client_id, $credential_id);

        $alert_extended = " along with login credentials";

    }

    // Add to History
    mysqli_query($mysqli,"INSERT INTO asset_history SET asset_history_status = '$status', asset_history_description = '$session_name created $name', asset_history_asset_id = $asset_id");

    //Logging
    logAction("Asset", "Create", "$session_name created asset $name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> created $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    require_once 'asset_model.php';
    $asset_id = intval($_POST['asset_id']);

    // Get Existing Photo
    $sql = mysqli_query($mysqli,"SELECT asset_photo FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['asset_photo']);

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_reference = '$purchase_reference', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_physical_location = '$physical_location', asset_notes = '$notes' WHERE asset_id = $asset_id");

    $sql_interfaces = mysqli_query($mysqli, "SELECT * FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_primary = 1");

    if(mysqli_num_rows($sql_interfaces) == 0 ) {
        // Add Primary Interface
        mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = '1', interface_mac = '$mac', interface_ip = '$ip', interface_nat_ip = '$nat_ip', interface_ipv6 = '$ipv6', interface_primary = 1, interface_network_id = $network, interface_asset_id = $asset_id");
    } else {
        // Update Primary Interface
        mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_mac = '$mac', interface_ip = '$ip', interface_nat_ip = '$nat_ip', interface_ipv6 = '$ipv6', interface_network_id = $network WHERE interface_asset_id = $asset_id AND interface_primary = 1");
    }

    // Update Photo
    if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

        // Set directory in which the uploaded file will be moved
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $upload_file_dir = "uploads/clients/$client_id/";
        $dest_path = $upload_file_dir . $new_file_name;

        move_uploaded_file($file_tmp_path, $dest_path);

        //Delete old file
        unlink("uploads/clients/$client_id/$existing_file_name");

        mysqli_query($mysqli,"UPDATE assets SET asset_photo = '$new_file_name' WHERE asset_id = $asset_id");
    }

    //Logging
    logAction("Asset", "Edit", "$session_name edited asset $name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_GET['csrf_token']);

    $asset_id = intval($_GET['archive_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id");

    //logging
    logAction("Asset", "Archive", "$session_name archived asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_GET['csrf_token']);

    $asset_id = intval($_GET['unarchive_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NULL WHERE asset_id = $asset_id");

    // Logging
    logAction("Asset", "Unarchive", "$session_name unarchived asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> Unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_asset'])) {

    enforceUserPermission('module_support', 3);

    validateCSRFToken($_GET['csrf_token']);

    $asset_id = intval($_GET['delete_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id");
    // Delete Interfaces
    mysqli_query($mysqli,"DELETE FROM asset_interfaces WHERE interface_asset_id = $asset_id");
    // Delete History
    mysqli_query($mysqli,"DELETE FROM asset_history WHERE asset_history_asset_id = $asset_id");
    // Delete Notes
    mysqli_query($mysqli,"DELETE FROM asset_notes WHERE asset_note_asset_id = $asset_id");
    // Rack Units
    mysqli_query($mysqli,"DELETE FROM rack_units WHERE unit_asset_id = $asset_id");

    // Delete Links
    mysqli_query($mysqli,"DELETE FROM asset_documents WHERE asset_id = $asset_id");
    mysqli_query($mysqli,"DELETE FROM asset_files WHERE asset_id = $asset_id");
    mysqli_query($mysqli,"DELETE FROM contact_assets WHERE asset_id = $asset_id");
    mysqli_query($mysqli,"DELETE FROM service_assets WHERE asset_id = $asset_id");
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE asset_id = $asset_id");

    // Logging
    logAction("Asset", "Delete", "$session_name deleted asset $asset_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_assign_asset_location'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $location_id = intval($_POST['bulk_location_id']);

    // Get Location name and client id for logging and alert
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);
    
    // Assign Location to Selected Contacts
    if (isset($_POST['asset_ids'])) {

        // Get Selected Contacts Count
        $asset_count = count($_POST['asset_ids']);

        foreach($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            // Get Asset Details for Logging
            $sql = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);

            mysqli_query($mysqli,"UPDATE assets SET asset_location_id = $location_id WHERE asset_id = $asset_id");

            //Logging
            logAction("Asset", "Edit", "$session_name assigned asset $asset_name to location $location_name", $client_id, $asset_id);

        } // End Assign Location Loop
        
        // Bulk Logging
        logAction("Asset", "Bulk Edit", "$session_name assigned $asset_count assets to location $location_name", $client_id);
        
        $_SESSION['alert_message'] = "You assigned <strong>$asset_count</strong> assets to location <strong>$location_name</strong>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_transfer_client_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $new_client_id = intval($_POST['bulk_client_id']);
    
    // Transfer selected asset to new client
    if (isset($_POST['asset_ids'])) {

        // Get Count
        $asset_count = count($_POST['asset_ids']);

        foreach($_POST['asset_ids'] as $current_asset_id) {
            $current_asset_id = intval($current_asset_id);

            // Get Asset details and current client ID/Name for logging
            $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT asset_name, asset_notes, asset_client_id, client_name
                FROM assets
                LEFT JOIN clients ON client_id = asset_client_id
                WHERE asset_id = $current_asset_id")
            );
            $asset_name = sanitizeInput($row['asset_name']);
            $asset_notes = sanitizeInput($row['asset_notes']);
            $current_client_id = intval($row['asset_client_id']);
            $current_client_name = sanitizeInput($row['client_name']);

            // Get new client name for logging
            $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $new_client_id"));
            $new_client_name = sanitizeInput($row['client_name']);

            // Create new asset
            mysqli_query($mysqli, "
                INSERT INTO assets (asset_type, asset_name, asset_description, asset_make, asset_model, asset_serial, asset_os, asset_status, asset_purchase_date, asset_warranty_expire, asset_install_date, asset_notes, asset_important)
                SELECT asset_type, asset_name, asset_description, asset_make, asset_model, asset_serial, asset_os, asset_status, asset_purchase_date, asset_warranty_expire, asset_install_date, asset_notes, asset_important
                FROM assets
                WHERE asset_id = $current_asset_id
            ");
            $new_asset_id = mysqli_insert_id($mysqli);

            // Transfer all Interfaces over too
            $sql_interfaces = mysqli_query($mysqli, "SELECT * FROM asset_interfaces WHERE interface_asset_id = $current_asset_id");

            while ($row = mysqli_fetch_array($sql_interfaces)) {
                $interface_name = sanitizeInput($row['interface_name']);
                $interface_mac = sanitizeInput($row['interface_mac']);
                $interface_primary = intval($row['interface_primary']);

                mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = '$interface_name', interface_mac = '$interface_mac',  interface_primary = $interface_primary, interface_asset_id = $new_asset_id");

            }

            mysqli_query($mysqli, "UPDATE assets SET asset_client_id = $new_client_id WHERE asset_id = $new_asset_id");

            // Archive/log the current asset
            $notes = $asset_notes . "\r\n\r\n---\r\n* " . date('Y-m-d H:i:s') . ": Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)";
            mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $current_asset_id");
            
            // Log Archive
            logAction("Asset", "Archive", "$session_name archived asset $asset_name (via transfer)", $current_client_id, $current_asset_id);

            // Log Transfer
            logAction("Asset", "Transfer", "$session_name Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)", $current_client_id, $current_asset_id);
            mysqli_query($mysqli, "UPDATE assets SET asset_notes = '$notes' WHERE asset_id = $current_asset_id");

            // Log the new asset
            $notes = $asset_notes . "\r\n\r\n---\r\n* " . date('Y-m-d H:i:s') . ": Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)";
            logAction("Asset", "Create", "$session_name created asset $name (via transfer)", $new_client_id, $new_asset_id);

            logAction("Asset", "Transfer", "$session_name Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)", $new_client_id, $new_asset_id);

            mysqli_query($mysqli, "UPDATE assets SET asset_notes = '$notes' WHERE asset_id = $new_asset_id");

        } // End Transfer to Client Loop

        // Bulk Logging
        logAction("Asset", "Bulk Transfer", "$session_name transferred $asset_count assets to $new_client_name", $new_client_id);
        
        $_SESSION['alert_message'] = "Transferred <strong>$asset_count</strong> assets to <strong>$new_client_name</strong>.";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_assign_asset_contact'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $contact_id = intval($_POST['bulk_contact_id']);

    // Get Contact name and client id for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    
    // Assign Contact to Selected Assets
    if (isset($_POST['asset_ids'])) {

        // Get Selected Contacts Count
        $asset_count = count($_POST['asset_ids']);

        foreach($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            // Get Asset Details for Logging
            $sql = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);

            mysqli_query($mysqli,"UPDATE assets SET asset_contact_id = $contact_id WHERE asset_id = $asset_id");

            // Logging
            logAction("Asset", "Edit", "$session_name assigned asset $asset_name to contact $contact_name", $client_id, $asset_id);

        } // End Assign Contact Loop

        // Bulk Logging
        logAction("Asset", "Bulk Edit", "$session_name assigned $asset_count assets to contact $contact_name", $client_id);
        
        $_SESSION['alert_message'] = "You assigned <strong>$asset_count</strong> assets to contact <strong>$contact_name</strong>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_asset_status'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $status = sanitizeInput($_POST['bulk_status']);
    
    // Assign Status to Selected Assets
    if (isset($_POST['asset_ids'])) {

        // Get Count
        $asset_count = count($_POST['asset_ids']);

        foreach($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            // Get Asset Details for Logging
            $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            mysqli_query($mysqli,"UPDATE assets SET asset_status = '$status' WHERE asset_id = $asset_id");

            //Logging
            logAction("Asset", "Edit", "$session_name set status to $status on $asset_name", $client_id, $asset_id);

        } // End Assign Status Loop

        // Bulk Logging
        logAction("Asset", "Bulk Edit", "$session_name set status to $status on $asset_count assets", $client_id);
        
        $_SESSION['alert_message'] = "You set the status <strong>$status</strong> on <strong>$asset_count</strong> assets.";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_archive_assets'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['asset_ids'])) {

        // Get Count
        $count = count($_POST['asset_ids']);

        foreach ($_POST['asset_ids'] as $asset_id) {

            $asset_id = intval($asset_id);

            // Get Asset Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id");

            // Individual Asset logging
            logAction("Asset", "Archive", "$session_name archived asset $asset_name", $client_id, $asset_id);

        }

        // Bulk Logging
        logAction("Asset", "Bulk Archive", "$session_name archived $count assets", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived $count asset(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_assets'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['asset_ids'])) {

        // Get Count
        $count = count($_POST['asset_ids']);

        foreach ($_POST['asset_ids'] as $asset_id) {

            $asset_id = intval($asset_id);

            // Get Asset Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NULL WHERE asset_id = $asset_id");

            // Individual Asset logging
            logAction("Asset", "Unarchive", "$session_name unarchived asset $asset_name", $client_id, $asset_id);

        }

        // Bulk Logging
        logAction("Asset", "Bulk Unarchive", "$session_name unarchived $count assets");

        $_SESSION['alert_message'] = "Unarchived $count asset(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_assets'])) {

    enforceUserPermission('module_support', 3);

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['asset_ids'])) {

        // Get Count
        $count = count($_POST['asset_ids']);

        foreach ($_POST['asset_ids'] as $asset_id) {

            $asset_id = intval($asset_id);

            // Get Asset Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id");
            // Delete Interfaces
            mysqli_query($mysqli,"DELETE FROM asset_interfaces WHERE interface_asset_id = $asset_id");
            // Delete History
            mysqli_query($mysqli,"DELETE FROM asset_history WHERE asset_history_asset_id = $asset_id");
            // Delete Notes
            mysqli_query($mysqli,"DELETE FROM asset_notes WHERE asset_note_asset_id = $asset_id");
            // Rack Units
            mysqli_query($mysqli,"DELETE FROM rack_units WHERE unit_asset_id = $asset_id");

            // Delete Links
            mysqli_query($mysqli,"DELETE FROM asset_documents WHERE asset_id = $asset_id");
            mysqli_query($mysqli,"DELETE FROM asset_files WHERE asset_id = $asset_id");
            mysqli_query($mysqli,"DELETE FROM contact_assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli,"DELETE FROM service_assets WHERE asset_id = $asset_id");
            mysqli_query($mysqli,"DELETE FROM software_assets WHERE asset_id = $asset_id");

            // Individual Asset logging
            logAction("Asset", "Delete", "$session_name deleted asset $asset_name", $client_id, $asset_id);
        }

        // Bulk Logging
        logAction("Asset", "Bulk Delete", "$session_name deleted $count assets");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> asset(s)";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// BEGIN LINKING

if (isset($_POST['link_software_to_asset'])) {

    enforceUserPermission('module_support', 2);

    $software_id = intval($_POST['software_id']);
    $asset_id = intval($_POST['asset_id']);

    // Get software Name and Client ID for logging
    $sql_software = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_array($sql_software);
    $software_name = sanitizeInput($row['software_name']);
    $client_id = intval($row['software_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"INSERT INTO software_assets SET asset_id = $asset_id, software_id = $software_id");

    // Logging
    logAction("Software", "Link", "$session_name added software license $software_name to asset $asset_name", $client_id, $software_id);

    $_SESSION['alert_message'] = "Software <strong>$software_name</strong> licensed for asset <strong>$asset_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_software_from_asset'])) {

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['asset_id']);
    $software_id = intval($_GET['software_id']);

    // Get software Name and Client ID for logging
    $sql_software = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_array($sql_software);
    $software_name = sanitizeInput($row['software_name']);
    $client_id = intval($row['software_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"DELETE FROM software_assets WHERE asset_id = $asset_id AND software_id = $software_id");

    //Logging
    logAction("software", "Unlink", "$session_name removed software license $software_name from asset $asset_name", $client_id, $software_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Removed Software License <strong>$software_name</strong> for Asset <strong>$asset_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
// Right now 1 login and have many assets but not many to many
if (isset($_POST['link_asset_to_credential'])) {

    enforceUserPermission('module_support', 2);

    $login_id = intval($_POST['login_id']);
    $asset_id = intval($_POST['asset_id']);

    // Get login Name and Client ID for logging
    $sql_login = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
    $row = mysqli_fetch_array($sql_login);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"UPDATE logins SET login_asset_id = $asset_id WHERE login_id = $login_id");

    // Logging
    logAction("Credential", "Link", "$session_name linked credential $login_name to asset $asset_name", $client_id, $login_id);

    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> linked with credential <strong>$login_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_credential_from_asset'])) {

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['asset_id']);
    $login_id = intval($_GET['login_id']);

    // Get login Name and Client ID for logging
    $sql_login = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
    $row = mysqli_fetch_array($sql_login);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"UPDATE logins SET login_asset_id = 0 WHERE login_id = $login_id");

    //Logging
    logAction("Credential", "Unlink", "$session_name unlinked asset $asset_name from credential $login_name", $client_id, $login_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Credential <strong>$login_name</strong> unlinked from Asset <strong>$asset_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_service_to_asset'])) {

    enforceUserPermission('module_support', 2);

    $service_id = intval($_POST['service_id']);
    $asset_id = intval($_POST['asset_id']);

    // Get service Name and Client ID for logging
    $sql_service = mysqli_query($mysqli,"SELECT service_name, service_client_id FROM services WHERE service_id = $service_id");
    $row = mysqli_fetch_array($sql_service);
    $service_name = sanitizeInput($row['service_name']);
    $client_id = intval($row['service_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"INSERT INTO service_assets SET asset_id = $asset_id, service_id = $service_id");

    // Logging
    logAction("Service", "Link", "$session_name linked asset $asset_name to service $service_name", $client_id, $service_id);

    $_SESSION['alert_message'] = "Service <strong>$service_name</strong> linked with asset <strong>$asset_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_service_from_asset'])) {

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['asset_id']);
    $service_id = intval($_GET['service_id']);

    // Get service Name and Client ID for logging
    $sql_service = mysqli_query($mysqli,"SELECT service_name, service_client_id FROM services WHERE service_id = $service_id");
    $row = mysqli_fetch_array($sql_service);
    $service_name = sanitizeInput($row['service_name']);
    $client_id = intval($row['service_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"DELETE FROM service_assets WHERE asset_id = $asset_id AND service_id = $service_id");

    //Logging
    logAction("Service", "Unlink", "$session_name unlinked asset $asset_name from service $service_name", $client_id, $service_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> unlinked from service <strong>$service_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_asset_to_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $asset_id = intval($_POST['asset_id']);

    // Get file Name and Client ID for logging
    $sql_file = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    // asset add query
    mysqli_query($mysqli,"INSERT INTO asset_files SET asset_id = $asset_id, file_id = $file_id");

    // Logging
    logAction("File", "Link", "$session_name linked asset $asset_name to file $file_name", $client_id, $file_id);

    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> linked with File <strong>$file_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_asset_from_file'])) {

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['asset_id']);
    $file_id = intval($_GET['file_id']);

    // Get file Name and Client ID for logging
    $sql_file = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"DELETE FROM asset_files WHERE asset_id = $asset_id AND file_id = $file_id");

    //Logging
    logAction("File", "Unlink", "$session_name unlinked asset $asset_name from file $file_name", $client_id, $file_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> unlinked from file <strong>$file_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// END LINKING


if (isset($_POST["import_assets_csv"])) {

    enforceUserPermission('module_support', 2);
    validateCSRFToken($_POST['csrf_token']);

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];

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

    //(Else)Check column count (name, desc, type, make, model, serial, os, assigned to, location)
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 10) {
        $error = true;
        $_SESSION['alert_message'] = "Invalid column count.";
    }

    //Else, parse the file
    if (!$error) {
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false) {

            // Default variables (if undefined)
            $description = $type = $make = $model = $serial = $os = '';
            $contact_id = $location_id = 0;

            $duplicate_detect = 0;
            if (isset($column[0])) {
                $name = sanitizeInput($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_name = '$name' AND asset_client_id = $client_id")) > 0) {
                    $duplicate_detect = 1;
                }
            }
            if (!empty($column[1])) {
                $description = sanitizeInput($column[1]);
            }
            if (!empty($column[2])) {
                $type = sanitizeInput($column[2]);
            }
            if (!empty($column[3])) {
                $make = sanitizeInput($column[3]);
            }
            if (!empty($column[4])) {
                $model = sanitizeInput($column[4]);
            }
            if (!empty($column[5])) {
                $serial = sanitizeInput($column[5]);
            }
            if (!empty($column[6])) {
                $os = sanitizeInput($column[6]);
            }
            if (!empty($column[7])) {
                $contact = sanitizeInput($column[7]);
                if ($contact) {
                    $sql_contact = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_name = '$contact' AND contact_client_id = $client_id");
                    $row = mysqli_fetch_assoc($sql_contact);
                    $contact_id = intval($row['contact_id']);
                }
            }
            if (!empty($column[8])) {
                $location = sanitizeInput($column[8]);
                if ($location) {
                    $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
                    $row = mysqli_fetch_assoc($sql_location);
                    $location_id = intval($row['location_id']);
                }
            }
            if (!empty($column[9])) {
                $physical_location = sanitizeInput($column[9]);
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0) {
                //Add
                mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_physical_location = '$physical_location', asset_contact_id = $contact_id, asset_location_id = $location_id, asset_client_id = $client_id");

                $asset_id = mysqli_insert_id($mysqli);
                
                // Add Primary Interface
                mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = '1', interface_primary = 1, interface_asset_id = $asset_id");

                $row_count = $row_count + 1;
            } else {
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        // Logging
        logAction("Asset", "Import", "$session_name imported $row_count asset(s) via CSV file", $client_id);

        $_SESSION['alert_message'] = "$row_count Asset(s) added, $duplicate_count duplicate(s) detected";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['download_assets_csv_template'])) {
    $client_id = intval($_GET['download_assets_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Assets-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Type', 'Make', 'Model', 'Serial', 'OS', 'Assigned To', 'Location', 'Physical Location');
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

if (isset($_POST['export_assets_csv'])) {

    enforceUserPermission('module_support');
    validateCSRFToken($_POST['csrf_token']);

    $client_name = 'All'; // default

    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND asset_client_id = $client_id";

        $client_row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id"));
        $client_name = $client_row['client_name'];
    } else {
        $client_query = '';
    }

    // Get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 LEFT JOIN clients ON asset_client_id = client_id WHERE asset_archived_at IS NULL $client_query ORDER BY asset_name ASC");
    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Assets-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Type', 'Make', 'Model', 'Serial Number', 'Operating System', 'Purchase Date', 'Warranty Expire', 'Install Date', 'Assigned To', 'Location', 'Physical Location', 'Notes');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while ($row = mysqli_fetch_array($sql)) {
            $lineData = array($row['asset_name'], $row['asset_description'], $row['asset_type'], $row['asset_make'], $row['asset_model'], $row['asset_serial'], $row['asset_os'], $row['asset_purchase_date'], $row['asset_warranty_expire'], $row['asset_install_date'], $row['contact_name'], $row['location_name'], $row['asset_physical_location'], $row['asset_notes']);
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
    logAction("Asset", "Export", "$session_name exported $num_rows asset(s) to a CSV file", $client_id);

    exit;

}

if (isset($_POST['add_asset_interface'])) {

    // 1) Permissions & CSRF
    enforceUserPermission('module_support', 2);
    validateCSRFToken($_POST['csrf_token']);

    // 2) Gather posted values
    $interface_id = intval($_POST['interface_id']);
    $asset_id     = intval($_POST['asset_id']);

    // Defines $name, $mac, $ip, $ipv6, $port, $notes, $network, $connected_to, etc.
    require_once 'asset_interface_model.php';

    // 3) Fetch asset info for logging and alert
    $sql   = mysqli_query($mysqli, "
        SELECT asset_name, asset_client_id 
        FROM assets 
        WHERE asset_id = $asset_id
    ");
    $row        = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id  = intval($row['asset_client_id']);

    // 4) Insert new interface into asset_interfaces (using SET syntax)
    $sql_insert = "
        INSERT INTO asset_interfaces SET
            interface_name       = '$name',
            interface_description= '$description',
            interface_type       = '$type',
            interface_mac        = '$mac',
            interface_ip         = '$ip',
            interface_nat_ip     = '$nat_ip',
            interface_ipv6       = '$ipv6',
            interface_notes      = '$notes',
            interface_network_id = $network,
            interface_asset_id   = $asset_id
    ";
    mysqli_query($mysqli, $sql_insert);
    
    $new_interface_id = mysqli_insert_id($mysqli);

    // If Primary Interface Checked set all interfaces primary to 0 then set the new interface as primary with a 1
    if ($primary_interface) { 
        mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_primary = 0 WHERE interface_asset_id = $asset_id");
        mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_primary = 1 WHERE interface_id = $new_interface_id");
    }

    // 5) If user selected a connected interface, insert row in asset_interface_links
    if (!empty($connected_to) && intval($connected_to) > 0) {
        $sql_link = "
            INSERT INTO asset_interface_links SET
                interface_a_id = $new_interface_id,
                interface_b_id = $connected_to
        ";
        mysqli_query($mysqli, $sql_link);
    }

    // 6) Logging
    logAction(
        "Asset Interface", 
        "Create", 
        "$session_name created interface $name for asset $asset_name", 
        $client_id, 
        $asset_id
    );

    // 7) Alert message + redirect
    $_SESSION['alert_message'] = "Interface <strong>$name</strong> created";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit;
}

if (isset($_POST['add_asset_multiple_interfaces'])) {

    enforceUserPermission('module_support', 2);
    validateCSRFToken($_POST['csrf_token']);

    $asset_id = intval($_POST['asset_id']);
    $interface_start = intval($_POST['interface_start']);
    $interfaces = intval($_POST['interfaces']);
    $type = sanitizeInput($_POST['type']);
    $name_prefix = sanitizeInput($_POST['name_prefix']);
    $network = intval($_POST['network']);
    $notes = sanitizeInput($_POST['notes']);

    $sql = mysqli_query($mysqli, "SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row  = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id  = intval($row['asset_client_id']);

    for ($interface_number = $interface_start; $interface_number < $interface_start + $interfaces; $interface_number++) {

        // Format $interface_number as a 2-digit number
        $formatted_interface_number = str_pad($interface_number, 2, '0', STR_PAD_LEFT);
    
        $sql_insert = "
            INSERT INTO asset_interfaces SET
                interface_name       = '$name_prefix$formatted_interface_number',
                interface_type       = '$type',
                interface_notes      = '$notes',
                interface_network_id = $network,
                interface_asset_id   = $asset_id
        ";
        mysqli_query($mysqli, $sql_insert);

        logAction("Asset Interface", "Create", "$session_name created interface $name for asset $asset_name", $client_id, $asset_id);
    }

    logAction("Asset Interface", "Bulk Create", "$session_name created $interfaces for asset $asset_name", $client_id, $asset_id);
    $_SESSION['alert_message'] = "Created <strong>$interfaces</strong> Interface(s) for asset <strong>$asset_name</strong>";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit;
}

if (isset($_POST['edit_asset_interface'])) {

    enforceUserPermission('module_support', 2);
    validateCSRFToken($_POST['csrf_token']);

    // Interface info
    $interface_id = intval($_POST['interface_id']);
    require_once 'asset_interface_model.php'; 
    // sets: $name, $mac, $ip, $ipv6, $port, $notes, $network, $connected_to, etc.

    // 1) Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "
        SELECT asset_name, asset_client_id, asset_id
        FROM asset_interfaces 
        LEFT JOIN assets ON asset_id = interface_asset_id
        WHERE interface_id = $interface_id
    ");
    $row       = mysqli_fetch_array($sql);
    $asset_id  = intval($row['asset_id']);
    $asset_name= sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    // 2) Update the interface details in asset_interfaces
    $sql_update = "
        UPDATE asset_interfaces SET
            interface_name       = '$name',
            interface_description= '$description',
            interface_type       = '$type',
            interface_mac        = '$mac',
            interface_ip         = '$ip',
            interface_nat_ip     = '$nat_ip',
            interface_ipv6       = '$ipv6',   
            interface_notes      = '$notes',
            interface_network_id = $network
        WHERE interface_id = $interface_id
    ";
    mysqli_query($mysqli, $sql_update);

    // If Primary Interface Checked set all interfaces primary to 0 then set the new interface as primary with a 1
    if ($primary_interface) { 
        mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_primary = 0 WHERE interface_asset_id = $asset_id");
        mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_primary = 1 WHERE interface_id = $interface_id");
    }

    // 3) Remove any existing link for this interface (one-to-one)
    $sql_delete_link = "
        DELETE FROM asset_interface_links
        WHERE interface_a_id = $interface_id
           OR interface_b_id = $interface_id
    ";
    mysqli_query($mysqli, $sql_delete_link);

    // 4) If user selected a connected interface, create a new link
    if (!empty($connected_to) && intval($connected_to) > 0) {
        $sql_link = "
            INSERT INTO asset_interface_links SET
                interface_a_id = $interface_id,
                interface_b_id = $connected_to
        ";
        mysqli_query($mysqli, $sql_link);
    }

    // 5) Logging
    logAction(
        "Asset Interface", 
        "Edit", 
        "$session_name edited interface $name for asset $asset_name", 
        $client_id, 
        $asset_id
    );

    // 6) Alert and redirect
    $_SESSION['alert_message'] = "Interface <strong>$name</strong> edited";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit;
}

if (isset($_GET['delete_asset_interface'])) {

    enforceUserPermission('module_support', 2);
    validateCSRFToken($_GET['csrf_token']);

    $interface_id = intval($_GET['delete_asset_interface']);

    // 1) Fetch details for logging / alerts
    $sql = mysqli_query($mysqli, "
        SELECT asset_name, interface_name, asset_client_id, asset_id
        FROM asset_interfaces
        LEFT JOIN assets ON asset_id = interface_asset_id
        WHERE interface_id = $interface_id
    ");
    $row = mysqli_fetch_array($sql);
    $asset_id       = intval($row['asset_id']);
    $interface_name = sanitizeInput($row['interface_name']);
    $asset_name     = sanitizeInput($row['asset_name']);
    $client_id      = intval($row['asset_client_id']);

    // 2) Delete the interface this cascadingly delete asset_interface_links
    mysqli_query($mysqli, "
        DELETE FROM asset_interfaces
        WHERE interface_id = $interface_id
    ");

    // 3) Logging
    logAction(
        "Asset Interface", 
        "Delete", 
        "$session_name deleted interface $interface_name from asset $asset_name", 
        $client_id, 
        $asset_id
    );

    // 4) Alert and redirect
    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Interface <strong>$interface_name</strong> deleted";

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

if (isset($_POST["import_client_asset_interfaces_csv"])) {

    enforceUserPermission('module_support', 2);
    validateCSRFToken($_POST['csrf_token']);

    $asset_id = intval($_POST['asset_id']);
    $file_name = $_FILES["file"]["tmp_name"];

    // Get Asset Details for logging
    $sql_asset = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_assoc($sql_asset);
    $client_id = intval($row['asset_client_id']);
    $asset_name = sanitizeInput($row['asset_name']);

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

    //(Else)Check column count (Name, Description, Type, MAC, IP, NAT IP, IPv6, Network)
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

            // Default variables (if undefined)
            $description = $type = $mac = $ip = $nat_ip = $ipv6 = $network = '';

            $duplicate_detect = 0;
            if (isset($column[0])) {
                $name = sanitizeInput($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT interface_name FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_name = '$name'")) > 0) {
                    $duplicate_detect = 1;
                }
            }
            if (!empty($column[1])) {
                $description = sanitizeInput($column[1]);
            }
            if (!empty($column[2])) {
                $type = sanitizeInput($column[2]);
            }
            if (!empty($column[3])) {
                $mac = sanitizeInput($column[3]);
            }
            if (!empty($column[4])) {
                $ip = sanitizeInput($column[4]);
            }
            if (!empty($column[5])) {
                $nat_ip = sanitizeInput($column[5]);
            }
            if (!empty($column[6])) {
                $ipv6 = sanitizeInput($column[6]);
            }
            if (!empty($column[7])) {
                $network = sanitizeInput($column[7]);
                if ($network) {
                    $sql_network = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_name = '$network' AND network_archived_at IS NULL AND network_client_id = $client_id");
                    $row = mysqli_fetch_assoc($sql_network);
                    $network_id = intval($row['network_id']);
                }
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0) {
                //Add
                mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = '$name', interface_description = '$description', interface_type = '$type', interface_mac = '$mac', interface_ip = '$ip', interface_nat_ip = '$nat_ip', interface_ipv6 = '$ipv6', interface_network_id = $network_id, interface_asset_id = $asset_id");

                $row_count = $row_count + 1;
            } else {
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        // Logging
        logAction("Asset", "Import", "$session_name imported $row_count interfaces(s) to asset $asset_name via CSV file", $client_id);

        $_SESSION['alert_message'] = "<strong>$row_count</strong> Interfaces(s) added to asset <strong>$asset_name</stong>, <strong>$duplicate_count</strong> duplicate(s) detected";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['download_client_asset_interfaces_csv_template'])) {
    $asset_id = intval($_GET['download_client_asset_interfaces_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);

    $asset_name = $row['asset_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($asset_name) . "-Asset-Interfaces-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Type', 'MAC', 'IP', 'NAT IP', 'IPv6', 'Network');
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

if (isset($_POST['export_client_asset_interfaces_csv'])) {

    enforceUserPermission('module_support');
    validateCSRFToken($_POST['csrf_token']);

    $asset_id = intval($_POST['asset_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM asset_interfaces LEFT JOIN assets ON asset_id = interface_asset_id LEFT JOIN networks ON interface_network_id = network_id LEFT JOIN clients ON asset_client_id = client_id WHERE asset_id = $asset_id AND interface_archived_at IS NULL ORDER BY interface_name ASC");
    $row = mysqli_fetch_array($sql);

    $asset_name = $row['asset_name'];
    $client_id = $row['asset_client_id'];

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($asset_name) . "-Interfaces-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Type', 'MAC', 'IP', 'NAT IP', 'IPv6', 'Network');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_array($sql)) {
            $lineData = array($row['interface_name'], $row['interface_description'], $row['interface_type'], $row['interface_mac'], $row['interface_ip'], $row['interface_nat_ip'], $row['interface_ipv6'], $row['network_name']);
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
    logAction("Asset Interface", "Export", "$session_name exported $num_rows interfaces(s) to a CSV file", $client_id);

    exit;

}