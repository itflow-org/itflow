<?php

/*
 * ITFlow - GET/POST request handler for vendors
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_vendor_from_template'])) {

    // GET POST Data
    $client_id = intval($_POST['client_id']); //Used if this vendor is under a contact otherwise its 0 for under company and or template
    $vendor_template_id = intval($_POST['vendor_template_id']);

    //GET Vendor Info
    $sql_vendor_templates = mysqli_query($mysqli,"SELECT * FROM vendor_templates WHERE vendor_template_id = $vendor_template_id");

    $row = mysqli_fetch_array($sql_vendor_templates);

    $name = sanitizeInput($row['vendor_template_name']);
    $description = sanitizeInput($row['vendor_template_description']);
    $account_number = sanitizeInput($row['vendor_template_account_number']);
    $contact_name = sanitizeInput($row['vendor_template_contact_name']);
    $phone_country_code = preg_replace("/[^0-9]/", '',$row['vendor_template_phone_country_code']);
    $phone = preg_replace("/[^0-9]/", '',$row['vendor_template_phone']);
    $extension = preg_replace("/[^0-9]/", '',$row['vendor_template_extension']);
    $email = sanitizeInput($row['vendor_template_email']);
    $website = sanitizeInput($row['vendor_template_website']);
    $hours = sanitizeInput($row['vendor_template_hours']);
    $sla = sanitizeInput($row['vendor_template_sla']);
    $code = sanitizeInput($row['vendor_template_code']);
    $notes = sanitizeInput($row['vendor_template_notes']);

    // Vendor add query
    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id, vendor_template_id = $vendor_template_id");

    $vendor_id = mysqli_insert_id($mysqli);

    logAction("Vendor", "Create", "$session_name created vendor $name using a template", $client_id, $vendor_id);

    flash_alert("Vendor <strong>$name</strong> created from template");

    redirect();

}

// Vendors

if (isset($_POST['add_vendor'])) {

    require_once 'vendor_model.php';

    $client_id = intval($_POST['client_id']); // Used if this vendor is under a contact otherwise its 0 for under company

    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id");

    $vendor_id = mysqli_insert_id($mysqli);

    logAction("Vendor", "Create", "$session_name created vendor $name", $client_id, $vendor_id);

    flash_alert("Vendor <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_vendor'])) {

    require_once 'vendor_model.php';

    $vendor_id = intval($_POST['vendor_id']);
    $vendor_template_id = intval($_POST['vendor_template_id']);

    // Get Client ID
    $client_id = intval(getFieldById('vendors', $vendor_id, 'vendor_client_id'));

    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code',vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_template_id = $vendor_template_id WHERE vendor_id = $vendor_id");

    logAction("Vendor", "Edit", "$session_name edited vendor $name", $client_id, $vendor_id);

    flash_alert("Vendor <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['archive_vendor'])) {
    
    $vendor_id = intval($_GET['archive_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = sanitizeInput($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

    logAction("Vendor", "Archive", "$session_name archived vendor $vendor_name", $client_id, $vendor_id);

    flash_alert("Vendor <strong>$vendor_name</strong> archived", 'error');

    redirect();

}

if(isset($_GET['unarchive_vendor'])){

    $vendor_id = intval($_GET['unarchive_vendor']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_array($sql);
    $vendor_name = sanitizeInput($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");

    logAction("Vendor", "Unarchive", "$session_name unarchived vendor $vendor_name", $client_id, $vendor_id);

    flash_alert("Vendor <strong>$vendor_name</strong> restored");

    redirect();

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

    logAction("Vendor", "Delete", "$session_name deleted vendor $vendor_name", $client_id);

    flash_alert("Vendor <strong>$vendor_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_archive_vendors'])) {

    validateCSRFToken($_POST['csrf_token']);
    
    validateAdminRole();

    if (isset($_POST['vendor_ids'])) {

        // Get Selected Count
        $count = count($_POST['vendor_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['vendor_ids'] as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_array($sql);
            $vendor_name = sanitizeInput($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);

            mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

            logAction("Vendor", "Archive", "$session_name archived vendor $vendor_name", $client_id, $vendor_id);
        }

        logAction("Vendor", "Bulk Archive", "$session_name archived $count vendor(s)");

        flash_alert("Archived <strong>$count</strong> vendor(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_unarchive_vendors'])) {

    validateCSRFToken($_POST['csrf_token']);
    
    validateAdminRole();

    if (isset($_POST['vendor_ids'])) {

        // Get Selected Count
        $count = count($_POST['vendor_ids']);

        // Cycle through array and unarchive each record
        foreach ($_POST['vendor_ids'] as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_array($sql);
            $vendor_name = sanitizeInput($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);

            mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");

            logAction("Vendor", "Unarchive", "$session_name unarchived vendor $vendor_name", $client_id, $vendor_id);

        }

        logAction("Vendor", "Bulk Unarchive", "$session_name unarchived $count vendor(s)");

        flash_alert("Unarchived <strong>$count</strong> vendor(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_vendors'])) {
    
    validateCSRFToken($_POST['csrf_token']);

    validateAdminRole();
    
    if (isset($_POST['vendor_ids'])) {

        // Get Selected Count
        $count = count($_POST['vendor_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['vendor_ids'] as $vendor_id) {

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

            logAction("Vendor", "Delete", "$session_name deleted vendor $vendor_name", $client_id);

        }

        logAction("Vendor", "Bulk Delete", "$session_name deleted $count vendor(s)");
        
        flash_alert("Deleted <strong>$count</strong> vendor(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_vendors_csv'])) {
    
    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND vendor_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
    } else {
        $client_query = "AND vendor_client_id = 0";
        $client_name = '';
        $file_name_prepend = "$session_company_name-";
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_template = 0 $client_query ORDER BY vendor_name ASC");
    
    $count = mysqli_num_rows($sql);

    if ($count > 0) {
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitize_filename($file_name_prepend . "Vendors-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Contact Name', 'Phone', 'Website', 'Account Number', 'Notes');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['vendor_name'], $row['vendor_description'], $row['vendor_contact_name'], $row['vendor_phone'], $row['vendor_website'], $row['vendor_account_number'], $row['vendor_notes']);
            fputcsv($f, $lineData, $delimiter, $enclosure, $escape);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    logAction("Vendor", "Export", "$session_name exported $count vendor(s) to a CSV file");

    exit;
    
}
