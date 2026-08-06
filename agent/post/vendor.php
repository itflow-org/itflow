<?php

/*
 * ITFlow - GET/POST request handler for vendors
 */

 // Todo: 2026-03-02 JQ - Need Permssions reworked as we have client vendors and Global Vendors basically check the referral url if it has client_id then Perm check client else perm check financial.

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_vendor_from_template'])) {

    validateCSRFToken();

    // GET POST Data
    $client_id = intval($_POST['client_id']); //Used if this vendor is under a contact otherwise its 0 for under company and or template

    // Permission check
    if ($client_id) {
        enforceUserPermission('module_client', 2);
        enforceClientAccess();
    } else {
        enforceUserPermission('module_financial', 2);
    }

    $vendor_template_id = intval($_POST['vendor_template_id']);

    //GET Vendor Info
    $sql_vendor_templates = mysqli_query($mysqli,"SELECT * FROM vendor_templates WHERE vendor_template_id = $vendor_template_id");

    $row = mysqli_fetch_assoc($sql_vendor_templates);

    $name = escapeSql($row['vendor_template_name']);
    $description = escapeSql($row['vendor_template_description']);
    $account_number = escapeSql($row['vendor_template_account_number']);
    $contact_name = escapeSql($row['vendor_template_contact_name']);
    $phone_country_code = preg_replace("/[^0-9]/", '',$row['vendor_template_phone_country_code']);
    $phone = preg_replace("/[^0-9]/", '',$row['vendor_template_phone']);
    $extension = preg_replace("/[^0-9]/", '',$row['vendor_template_extension']);
    $email = escapeSql($row['vendor_template_email']);
    $website = escapeSql($row['vendor_template_website']);
    $hours = escapeSql($row['vendor_template_hours']);
    $sla = escapeSql($row['vendor_template_sla']);
    $code = escapeSql($row['vendor_template_code']);
    $notes = escapeSql($row['vendor_template_notes']);

    // Vendor add query
    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id, vendor_template_id = $vendor_template_id");

    $vendor_id = mysqli_insert_id($mysqli);

    logAudit("Vendor", "Create", "$session_name created vendor $name using a template", $client_id, $vendor_id);

    flashAlert("Vendor <strong>$name</strong> created from template");

    redirect();

}

// Vendors

if (isset($_POST['add_vendor'])) {

    validateCSRFToken();

    require_once 'vendor_model.php';

    $client_id = intval($_POST['client_id']); // Used if this vendor is under a contact otherwise its 0 for under company

    // Permission check
    if ($client_id) {
        enforceUserPermission('module_client', 2);
        enforceClientAccess();
    } else {
        enforceUserPermission('module_financial', 2);
    }

    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id");

    $vendor_id = mysqli_insert_id($mysqli);

    logAudit("Vendor", "Create", "$session_name created vendor $name", $client_id, $vendor_id);

    flashAlert("Vendor <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_vendor'])) {

    validateCSRFToken();

    require_once 'vendor_model.php';

    $vendor_id = intval($_POST['vendor_id']);
    $vendor_template_id = intval($_POST['vendor_template_id']);

    // Get Client ID
    $client_id = intval(getFieldById('vendors', $vendor_id, 'vendor_client_id'));

    // Permission check
    if ($client_id) {
        enforceUserPermission('module_client', 2);
        enforceClientAccess();
    } else {
        enforceUserPermission('module_financial', 2);
    }

    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code',vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_template_id = $vendor_template_id WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Edit", "$session_name edited vendor $name", $client_id, $vendor_id);

    flashAlert("Vendor <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['archive_vendor'])) {

    validateCSRFToken();

    $vendor_id = intval($_GET['archive_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_assoc($sql);
    $vendor_name = escapeSql($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);

    // Permission check
    if ($client_id) {
        enforceUserPermission('module_client', 2);
        enforceClientAccess();
    } else {
        enforceUserPermission('module_financial', 2);
    }

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Archive", "$session_name archived vendor $vendor_name", $client_id, $vendor_id);

    flashAlert("Vendor <strong>$vendor_name</strong> archived", 'error');

    redirect();

}

if(isset($_GET['restore_vendor'])){

    validateCSRFToken();

    $vendor_id = intval($_GET['restore_vendor']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_assoc($sql);
    $vendor_name = escapeSql($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);

    // Permission check
    if ($client_id) {
        enforceUserPermission('module_client', 2);
        enforceClientAccess();
    } else {
        enforceUserPermission('module_financial', 2);
    }

    mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Restore", "$session_name restored vendor $vendor_name", $client_id, $vendor_id);

    flashAlert("Vendor <strong>$vendor_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_vendor'])) {

    validateCSRFToken();

    $vendor_id = intval($_GET['delete_vendor']);

    //Get Vendor Name
    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_assoc($sql);
    $vendor_name = escapeSql($row['vendor_name']);
    $client_id = intval($row['vendor_client_id']);
    $vendor_template_id = intval($row['vendor_template_id']);

    // Permission check
    if ($client_id) {
        enforceUserPermission('module_client', 3);
        enforceClientAccess();
    } else {
        enforceUserPermission('module_financial', 3);
    }

    // If its a template reset all vendors based off this template to no template base
    if ($vendor_template_id > 0) {
        mysqli_query($mysqli,"UPDATE vendors SET vendor_template_id = 0 WHERE vendor_template_id = $vendor_template_id");
    }

    mysqli_query($mysqli,"DELETE FROM vendors WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Delete", "$session_name deleted vendor $vendor_name", $client_id);

    flashAlert("Vendor <strong>$vendor_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_archive_vendors'])) {

    validateCSRFToken();

    if (isset($_POST['vendor_ids'])) {

        // Get Selected Count
        $count = count($_POST['vendor_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['vendor_ids'] as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_assoc($sql);
            $vendor_name = escapeSql($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);

            // Permission check
            if ($client_id) {
                enforceUserPermission('module_client', 2);
                enforceClientAccess();
            } else {
                enforceUserPermission('module_financial', 2);
            }

            mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

            logAudit("Vendor", "Archive", "$session_name archived vendor $vendor_name", $client_id, $vendor_id);
        }

        logAudit("Vendor", "Bulk Archive", "$session_name archived $count vendor(s)");

        flashAlert("Archived <strong>$count</strong> vendor(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_restore_vendors'])) {

    validateCSRFToken();

    if (isset($_POST['vendor_ids'])) {

        // Get Selected Count
        $count = count($_POST['vendor_ids']);

        // Cycle through array and unarchive each record
        foreach ($_POST['vendor_ids'] as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_assoc($sql);
            $vendor_name = escapeSql($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);

            // Permission check
            if ($client_id) {
                enforceUserPermission('module_client', 2);
                enforceClientAccess();
            } else {
                enforceUserPermission('module_financial', 2);
            }

            mysqli_query($mysqli,"UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");

            logAudit("Vendor", "Restore", "$session_name restored vendor $vendor_name", $client_id, $vendor_id);

        }

        logAudit("Vendor", "Bulk Restore", "$session_name restored $count vendor(s)");

        flashAlert("Restored <strong>$count</strong> vendor(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_vendors'])) {

    validateCSRFToken();

    if (isset($_POST['vendor_ids'])) {

        // Get Selected Count
        $count = count($_POST['vendor_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['vendor_ids'] as $vendor_id) {

            $vendor_id = intval($vendor_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_name, vendor_client_id, vendor_template_id FROM vendors WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_assoc($sql);
            $vendor_name = escapeSql($row['vendor_name']);
            $client_id = intval($row['vendor_client_id']);
            $vendor_template_id = intval($row['vendor_template_id']);

            // Permission check
            if ($client_id) {
                enforceUserPermission('module_client', 3);
                enforceClientAccess();
            } else {
                enforceUserPermission('module_financial', 3);
            }

            // If its a template reset all vendors based off this template to no template base
            if ($vendor_template_id > 0) {
                mysqli_query($mysqli,"UPDATE vendors SET vendor_template_id = 0 WHERE vendor_template_id = $vendor_template_id");
            }

            mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id");

            logAudit("Vendor", "Delete", "$session_name deleted vendor $vendor_name", $client_id);

        }

        logAudit("Vendor", "Bulk Delete", "$session_name deleted $count vendor(s)");

        flashAlert("Deleted <strong>$count</strong> vendor(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_vendors'])) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_client');

    $format = resolveExportFormat($_POST['export_vendors']);

    // Filters inherited from the vendors page - mirrors agent/vendors.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND vendor_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();
    } else {
        // Global vendors only, same as the vendors page
        $client_query = "AND vendor_client_id = 0";
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";
    }

    $archive_query = $archived ? "vendor_archived_at IS NOT NULL" : "vendor_archived_at IS NULL";

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM vendors
        LEFT JOIN clients ON client_id = vendor_client_id
        WHERE $archive_query
        AND (vendor_name LIKE '%$q%' OR vendor_description LIKE '%$q%' OR vendor_account_number LIKE '%$q%' OR vendor_website LIKE '%$q%' OR vendor_contact_name LIKE '%$q%' OR vendor_email LIKE '%$q%' OR vendor_phone LIKE '%$q%')
        $client_query
        " . clientScopeSql('vendor_client_id') . "
        ORDER BY vendor_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('vendors', $format, $file_name_prepend . 'Vendors', 'Vendors', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Vendor", "Export", "$session_name exported $num_rows vendor(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
