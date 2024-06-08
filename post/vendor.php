<?php

/*
 * ITFlow - GET/POST request handler for vendors
 */

// Vendor Templates

if (isset($_POST['add_vendor_template'])) {

    require_once 'post/vendor_model.php';


    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_template = 1, vendor_client_id = 0");

    $vendor_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor Template', log_action = 'Create', log_description = '$session_name created vendor template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_vendor_template'])) {

    require_once 'post/vendor_model.php';


    $vendor_id = intval($_POST['vendor_id']);
    $vendor_template_id = intval($_POST['vendor_template_id']);

    if ($_POST['global_update_vendor_name'] == 1) {
        $sql_global_update_vendor_name = ", vendor_name = '$name'";
    } else {
        $sql_global_update_vendor_name = "";
    }

    if ($_POST['global_update_vendor_description'] == 1) {
        $sql_global_update_vendor_description = ", vendor_description = '$description'";
    } else {
        $sql_global_update_vendor_description = "";
    }

    if ($_POST['global_update_vendor_account_number'] == 1) {
        $sql_global_update_vendor_account_number = ", vendor_account_number = '$account_number'";
    } else {
        $sql_global_update_vendor_account_number = "";
    }

    if ($_POST['global_update_vendor_contact_name'] == 1) {
        $sql_global_update_vendor_contact_name = ", vendor_contact_name = '$contact_name'";
    } else {
        $sql_global_update_vendor_contact_name = "";
    }

    if ($_POST['global_update_vendor_phone'] == 1) {
        $sql_global_update_vendor_phone = ", vendor_phone = '$phone', vendor_extension = '$extension'";
    } else {
        $sql_global_update_vendor_phone = "";
    }

    if ($_POST['global_update_vendor_hours'] == 1) {
        $sql_global_update_vendor_hours = ", vendor_hours = '$hours'";
    } else {
        $sql_global_update_vendor_hours = "";
    }

    if ($_POST['global_update_vendor_email'] == 1) {
        $sql_global_update_vendor_email = ", vendor_email = '$email'";
    } else {
        $sql_global_update_vendor_email = "";
    }

    if ($_POST['global_update_vendor_website'] == 1) {
        $sql_global_update_vendor_website = ", vendor_website = '$website'";
    } else {
        $sql_global_update_vendor_website = "";
    }

    if ($_POST['global_update_vendor_sla'] == 1) {
        $sql_global_update_vendor_sla = ", vendor_sla = '$sla'";
    } else {
        $sql_global_update_vendor_sla = "";
    }

    if ($_POST['global_update_vendor_code'] == 1) {
        $sql_global_update_vendor_code = ", vendor_code = '$code'";
    } else {
        $sql_global_update_vendor_code = "";
    }

    if ($_POST['global_update_vendor_notes'] == 1) {
        $sql_global_update_vendor_notes = ", vendor_notes = '$notes'";
    } else {
        $sql_global_update_vendor_notes = "";
    }

    // Update just the template
    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes' WHERE vendor_id = $vendor_id");

    if ($_POST['update_base_vendors'] == 1) {
        // Update client related vendors if anything is checked
        $sql = "$sql_global_update_vendor_name $sql_global_update_vendor_description $sql_global_update_vendor_account_number $sql_global_update_vendor_contact_name $sql_global_update_vendor_phone $sql_global_update_vendor_hours $sql_global_update_vendor_email $sql_global_update_vendor_website $sql_global_update_vendor_sla $sql_global_update_vendor_code $sql_global_update_vendor_notes";

        // Remove the first comma to prevent MySQL error
        $sql = preg_replace('/,/', '', $sql, 1);

        mysqli_query($mysqli,"UPDATE vendors SET $sql WHERE vendor_template_id = $vendor_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor Template', log_action = 'Modify', log_description = '$session_name modified vendor template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_vendor_from_template'])) {

    // GET POST Data
    $client_id = intval($_POST['client_id']); //Used if this vendor is under a contact otherwise its 0 for under company and or template
    $vendor_template_id = intval($_POST['vendor_template_id']);

    //GET Vendor Info
    $sql_vendor = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_template_id");

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
    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id, vendor_template_id = $vendor_template_id");

    $vendor_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Create', log_description = 'Vendor created from template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor created from template";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// Vendors

if (isset($_POST['add_vendor'])) {

    require_once 'post/vendor_model.php';


    $client_id = intval($_POST['client_id']); // Used if this vendor is under a contact otherwise its 0 for under company

    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id");

    $vendor_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Create', log_description = '$session_name created vendor $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_vendor'])) {

    require_once 'post/vendor_model.php';


    $vendor_id = intval($_POST['vendor_id']);
    $vendor_template_id = intval($_POST['vendor_template_id']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code',vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_template_id = $vendor_template_id WHERE vendor_id = $vendor_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Modify', log_description = '$session_name modified vendor $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor <strong>$name</strong> modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['archive_vendor'])) {
    $vendor_id = intval($_GET['archive_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = sanitizeInput($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Archive', log_description = '$session_name archived vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['unarchive_vendor'])){

    $vendor_id = intval($_GET['unarchive_vendor']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = sanitizeInput($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Unarchive', log_description = '$session_name restored credential $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $vendor_id");

    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_vendor'])) {
    $vendor_id = intval($_GET['delete_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = sanitizeInput($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);
    $vendor_template_id = intval($row['vendor_template_id']);

    // If its a template reset all vendors based off this template to no template base
    if ($vendor_template_id > 0) {
        mysqli_query($mysqli,"UPDATE vendors SET vendor_template_id = 0 WHERE vendor_template_id = $vendor_template_id");
    }

    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_id = $vendor_id");

    // Remove Relations
    mysqli_query($mysqli,"DELETE FROM vendor_files WHERE vendor_id = $vendor_id");
    mysqli_query($mysqli,"DELETE FROM vendor_documents WHERE vendor_id = $vendor_id");
    mysqli_query($mysqli,"DELETE FROM vendor_logins WHERE vendor_id = $vendor_id");
    mysqli_query($mysqli,"DELETE FROM service_vendors WHERE vendor_id = $vendor_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Delete', log_description = '$session_name deleted vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_archive_vendors'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $vendor_ids = $_POST['vendor_ids']; // Get array of IDs to be deleted

    if (!empty($vendor_ids)) {

        // Cycle through array and archive each record
        foreach ($vendor_ids as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_array($sql);
            $vendor_name = sanitizeInput($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);

            mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

            // Individual Contact logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Archive', log_description = '$session_name archived vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $vendor_id");
            $count++;
        }

        // Bulk Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Vendor', log_action = 'Archive', log_description = '$session_name archived $count vendors', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived $count credential(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_vendors'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $vendor_ids = $_POST['vendor_ids']; // Get array of IDs

    if (!empty($vendor_ids)) {

        // Cycle through array and unarchive
        foreach ($vendor_ids as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_array($sql);
            $vendor_name = sanitizeInput($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);

            mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");

            // Individual logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Unarchive', log_description = '$session_name Unarchived vendor $vendors_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $vendor_id");


            $count++;
        }

        // Bulk Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Vendor', log_action = 'Unarchive', log_description = '$session_name Unarchived $count vendors', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Unarchived $count vendor(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_vendors'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $vendor_ids = $_POST['vendor_ids']; // Get array of IDs to be deleted

    if (!empty($vendor_ids)) {

        // Cycle through array and delete each record
        foreach ($vendor_ids as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id, vendor_template_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_array($sql);
            $vendor_name = sanitizeInput($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);
            $vendor_template_id = intval($row['vendor_template_id']);

            // If its a template reset all vendors based off this template to no template base
            if ($vendor_template_id > 0) {
                mysqli_query($mysqli,"UPDATE vendors SET vendor_template_id = 0 WHERE vendor_template_id = $vendor_template_id");
            }
            
            mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id");

            // Remove Relations
            mysqli_query($mysqli,"DELETE FROM vendor_files WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli,"DELETE FROM vendor_documents WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli,"DELETE FROM vendor_logins WHERE vendor_id = $vendor_id");
            mysqli_query($mysqli,"DELETE FROM service_vendors WHERE vendor_id = $vendor_id");

            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Vendor', log_action = 'Delete', log_description = '$session_name deleted vendor $vendor_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $vendor_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Vendor', log_action = 'Delete', log_description = '$session_name bulk deleted $count vendors', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count vendor(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['export_client_vendors_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id ORDER BY vendor_name ASC");
    if ($sql->num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Vendors-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Contact Name', 'Phone', 'Website', 'Account Number', 'Notes');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Vendor', log_action = 'Export', log_description = '$session_name exported vendors to CSV', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    exit;
}
