<?php

/*
 * ITFlow - GET/POST request handler for client software & licenses
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_software_from_template'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    // GET POST Data
    $client_id = intval($_POST['client_id']);
    $software_template_id = intval($_POST['software_template_id']);

    enforceClientAccess();

    // GET Software Template Info
    $sql_software_templates = mysqli_query($mysqli,"SELECT software_template_description, software_template_license_type, software_template_name,
        software_template_notes, software_template_type, software_template_version FROM software_templates WHERE software_template_id = $software_template_id");
    $row = mysqli_fetch_assoc($sql_software_templates);
    $name = escapeSql($row['software_template_name']);
    $version = escapeSql($row['software_template_version']);
    $description = escapeSql($row['software_template_description']);
    $type = escapeSql($row['software_template_type']);
    $license_type = escapeSql($row['software_template_license_type']);
    $notes = escapeSql($row['software_template_notes']);
    $vendor = intval($_POST['vendor'] ?? 0);

    // Software add query
    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_license_type = '$license_type', software_notes = '$notes', software_vendor_id = $vendor, software_client_id = $client_id");

    $software_id = mysqli_insert_id($mysqli);

    logAudit("Software", "Create", "$session_name created software $name using template", $client_id, $software_id);

    flashAlert("Software <strong>$name</strong> created from template");

    redirect();

}

if (isset($_POST['add_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $name = escapeSql($_POST['name']);
    $version = escapeSql($_POST['version']);
    $description = escapeSql($_POST['description']);
    $type = escapeSql($_POST['type']);
    $license_type = escapeSql($_POST['license_type']);
    $notes = escapeSql($_POST['notes']);
    $key = escapeSql($_POST['key']);
    $seats = intval($_POST['seats']);
    $purchase_reference = escapeSql($_POST['purchase_reference']);
    $purchase = escapeSql($_POST['purchase']);
    if (empty($purchase)) {
        $purchase = "NULL";
    } else {
        $purchase = "'" . $purchase . "'";
    }
    $expire = escapeSql($_POST['expire']);
    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }
    $notes = escapeSql($_POST['notes']);
    $vendor = intval($_POST['vendor'] ?? 0);

    enforceClientAccess();

    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_key = '$key', software_license_type = '$license_type', software_seats = $seats, software_purchase_reference = '$purchase_reference', software_purchase = $purchase, software_expire = $expire, software_notes = '$notes', software_vendor_id = $vendor, software_client_id = $client_id");

    $software_id = mysqli_insert_id($mysqli);

    $alert_extended = "";

    // Add Asset Licenses
    if (isset($_POST['assets'])) {
        foreach($_POST['assets'] as $asset) {
            $asset_id = intval($asset);
            mysqli_query($mysqli,"INSERT INTO software_assets SET software_id = $software_id, asset_id = $asset_id");
        }
    }

    // Add Contact Licenses
    if (isset($_POST['contacts'])) {
        foreach($_POST['contacts'] as $contact) {
            $contact = intval($contact);
            mysqli_query($mysqli,"INSERT INTO software_contacts SET software_id = $software_id, contact_id = $contact");
        }
    }

    logAudit("Software", "Create", "$session_name created software $name", $client_id, $software_id);

    flashAlert("Software <strong>$name</strong> created $alert_extended");

    redirect();

}

if (isset($_POST['edit_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $software_id = intval($_POST['software_id']);
    $name = escapeSql($_POST['name']);
    $version = escapeSql($_POST['version']);
    $description = escapeSql($_POST['description']);
    $type = escapeSql($_POST['type']);
    $license_type = escapeSql($_POST['license_type']);
    $notes = escapeSql($_POST['notes']);
    $key = escapeSql($_POST['key']);
    $seats = intval($_POST['seats']);
    $purchase_reference = escapeSql($_POST['purchase_reference']);
    $purchase = escapeSql($_POST['purchase']);
    if (empty($purchase)) {
        $purchase = "NULL";
    } else {
        $purchase = "'" . $purchase . "'";
    }
    $expire = escapeSql($_POST['expire']);
    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }
    $notes = escapeSql($_POST['notes']);
    $vendor = intval($_POST['vendor'] ?? 0);

    $client_id = intval(getFieldById('software', $software_id, 'software_client_id'));

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_key = '$key', software_license_type = '$license_type', software_seats = $seats, software_purchase_reference = '$purchase_reference', software_purchase = $purchase, software_expire = $expire, software_notes = '$notes', software_vendor_id = $vendor WHERE software_id = $software_id");


    // Update Asset Licenses
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");
    if (isset($_POST['assets'])) {
        foreach($_POST['assets'] as $asset) {
            $asset = intval($asset);
            mysqli_query($mysqli,"INSERT INTO software_assets SET software_id = $software_id, asset_id = $asset");
        }
    }

    // Update Contact Licenses
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    if (isset($_POST['contacts'])) {
        foreach($_POST['contacts'] as $contact) {
            $contact = intval($contact);
            mysqli_query($mysqli,"INSERT INTO software_contacts SET software_id = $software_id, contact_id = $contact");
        }
    }

    logAudit("Software", "Edit", "$session_name edited software $name", $client_id, $software_id);

    flashAlert("Software <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['archive_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $software_id = intval($_GET['archive_software']);

    // Get Software Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_assoc($sql);
    $software_name = escapeSql($row['software_name']);
    $client_id = intval($row['software_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE software SET software_archived_at = NOW() WHERE software_id = $software_id");

    // Remove Software Relations
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");

    logAudit("Software", "Archive", "$session_name archived software $software_name and removed all device/user license associations", $client_id, $software_id);

    flashAlert("Software <strong>$software_name</strong> archived and removed all device/user license associations", 'error');

    redirect();

}

if (isset($_GET['delete_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $software_id = intval($_GET['delete_software']);

    // Get Software Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_assoc($sql);
    $software_name = escapeSql($row['software_name']);
    $client_id = intval($row['software_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM software WHERE software_id = $software_id");

    logAudit("Software", "Delete", "$session_name deleted software $software_name and removed all device/user license associations", $client_id);

    flashAlert("Software <strong>$software_name</strong> deleted and removed all device/user license associations", 'error');

    redirect();

}

if (isExportRequest('export_software')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_software']);

    // Filters inherited from the software page - mirrors agent/software.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND software_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "software_archived_at IS NOT NULL" : "software_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (software_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR software_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND software_archived_at IS NULL)";
    }

    // Expiring In Filter
    if (!empty($_POST['expire_days'])) {
        if ($_POST['expire_days'] == "expired") {
            $expire_query = "AND (software_expire IS NOT NULL AND software_expire != '0000-00-00' AND software_expire < CURDATE())";
            $filter_summary['Expiry'] = 'Expired';
        } else {
            $expire_days = intval($_POST['expire_days']);
            $expire_query = "AND (software_expire IS NOT NULL AND software_expire != '0000-00-00' AND software_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
            $filter_summary['Expiry'] = "Expiring within $expire_days days";
        }
    } else {
        // Default - any
        $expire_query = '';
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT software_id FROM software
        LEFT JOIN clients ON client_id = software_client_id
        LEFT JOIN vendors ON vendor_id = software_vendor_id
        WHERE (software_name LIKE '%$q%' OR software_type LIKE '%$q%' OR software_key LIKE '%$q%' OR client_name LIKE '%$q%')
        AND $archive_query
        " . clientScopeSql('software_client_id') . "
        $client_query
        $expire_query
        ORDER BY software_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('software', $format, $file_name_prepend . 'Software', 'Software', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            // Asset and contact licence lists, only when those columns are wanted
            if (isset($export['columns']['assigned_to_assets'])) {
                $software_id = intval($row['software_id']);
                $assigned_to_assets = [];
                $asset_licenses_sql = mysqli_query($mysqli, "SELECT asset_name FROM software_assets LEFT JOIN assets ON software_assets.asset_id = assets.asset_id WHERE software_id = $software_id");
                while ($asset_row = mysqli_fetch_assoc($asset_licenses_sql)) {
                    $assigned_to_assets[] = $asset_row['asset_name'];
                }
                $row['assigned_to_assets'] = implode(', ', $assigned_to_assets);
            }
            if (isset($export['columns']['assigned_to_contacts'])) {
                $software_id = intval($row['software_id']);
                $assigned_to_contacts = [];
                $contact_licenses_sql = mysqli_query($mysqli, "SELECT contact_name FROM software_contacts LEFT JOIN contacts ON software_contacts.contact_id = contacts.contact_id WHERE software_id = $software_id");
                while ($contact_row = mysqli_fetch_assoc($contact_licenses_sql)) {
                    $assigned_to_contacts[] = $contact_row['contact_name'];
                }
                $row['assigned_to_contacts'] = implode(', ', $assigned_to_contacts);
            }
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Software", "Export", "$session_name exported $num_rows software(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
