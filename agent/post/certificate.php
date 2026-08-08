<?php

/*
 * ITFlow - GET/POST request handler for client SSL certificates
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_certificate'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'certificate_model.php';

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    // Parse public key data for a manually provided public key
    if (!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = escapeSql($public_key_obj['issuer']['O']);
        }
    }

    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_description = '$description', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_notes = '$notes', certificate_domain_id = $domain_id, certificate_client_id = $client_id");

    $certificate_id = mysqli_insert_id($mysqli);

    logAudit("Certificate", "Create", "$session_name created certificate $name", $client_id, $certificate_id);

    flashAlert("Certificate <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_certificate'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'certificate_model.php';

    $certificate_id = intval($_POST['certificate_id']);

    $client_id = intval(getFieldById('certificates', $certificate_id, 'certificate_client_id'));

    enforceClientAccess();

    // Parse public key data for a manually provided public key
    if (!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = escapeSql($public_key_obj['issuer']['O']);
        }
    }

    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    // Get current certificate info
    $original_certificate_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
        SELECT
            certificates.*,
            domains.domain_name
        FROM certificates
        LEFT JOIN domains ON certificate_domain_id = domain_id
        WHERE certificate_id = $certificate_id
    "));

    // Update certificate
    mysqli_query($mysqli,"UPDATE certificates SET certificate_name = '$name', certificate_description = '$description', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_notes = '$notes', certificate_domain_id = '$domain_id' WHERE certificate_id = $certificate_id");

    // Fetch the updated info
    $new_certificate_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
        SELECT
            certificates.*,
            domains.domain_name
        FROM certificates
        LEFT JOIN domains ON certificate_domain_id = domain_id
        WHERE certificate_id = $certificate_id
    "));

    // Compare/log changes between old/new info
    $ignored_columns = ["certificate_public_key", "certificate_updated_at", "certificate_accessed_at", "certificate_domain_id"];
    foreach ($original_certificate_info as $column => $old_value) {
        $new_value = $new_certificate_info[$column];
        if ($old_value != $new_value && !in_array($column, $ignored_columns)) {
            $column = escapeSql($column);
            $old_value = escapeSql($old_value);
            $new_value = escapeSql($new_value);
            mysqli_query($mysqli,"INSERT INTO certificate_history SET certificate_history_column = '$column', certificate_history_old_value = '$old_value', certificate_history_new_value = '$new_value', certificate_history_certificate_id = $certificate_id");
        }
    }

    logAudit("Certificate", "Edit", "$session_name edited certificate $name", $client_id, $certificate_id);

    flashAlert("Certificate <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['refresh_certificate'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $certificate_id = intval($_GET['refresh_certificate']);

    // Get Name, Domain and Client ID for lookup, logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_domain, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_assoc($sql);
    $certificate_name = escapeSql($row['certificate_name']);
    $certificate_domain = escapeSql($row['certificate_domain']);
    $client_id = intval($row['certificate_client_id']);

    enforceClientAccess();

    // Get fresh certificate from the live host
    $certificate = getSslCertificate($certificate_domain);

    if ($certificate_domain && $certificate['success']) {

        $expire = escapeSql($certificate['expire']);
        $issued_by = escapeSql($certificate['issued_by']);
        $public_key = escapeSql($certificate['public_key']);

        mysqli_query($mysqli,"UPDATE certificates SET certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key' WHERE certificate_id = $certificate_id");

        logAudit("Certificate", "Refresh", "$session_name refreshed certificate $certificate_name", $client_id, $certificate_id);

        flashAlert("Refreshed certificate <strong>$certificate_name</strong>");

    } else {
        flashAlert("Could not retrieve a certificate for <strong>$certificate_name</strong>", 'error');
    }

    redirect();

}

if (isset($_GET['archive_certificate'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $certificate_id = intval($_GET['archive_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_assoc($sql);
    $certificate_name = escapeSql($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE certificates SET certificate_archived_at = NOW() WHERE certificate_id = $certificate_id");

    logAudit("Certificate", "Archive", "$session_name archived certificate $certificate_name", $client_id, $certificate_id);

    flashAlert("Certificate <strong>$certificate_name</strong> archived", 'alert');

    redirect();

}

if (isset($_GET['restore_certificate'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $certificate_id = intval($_GET['restore_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_assoc($sql);
    $certificate_name = escapeSql($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE certificates SET certificate_archived_at = NULL WHERE certificate_id = $certificate_id");

    logAudit("Certificate", "Restore", "$session_name restored certificate $certificate_name", $client_id, $certificate_id);

    flashAlert("Certificate <strong>$certificate_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_certificate'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $certificate_id = intval($_GET['delete_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_assoc($sql);
    $certificate_name = escapeSql($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM certificates WHERE certificate_id = $certificate_id");

    logAudit("Certificate", "Delete", "$session_name deleted certificate $name", $client_id);

    flashAlert("Certificate <strong>$certificate_name</strong> deleted");

    redirect();

}

if (isset($_POST['bulk_refresh_certificates'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    if (isset($_POST['certificate_ids'])) {

        // TLS lookups on dead hosts wait out a 5 sec timeout each - don't time out mid-batch
        set_time_limit(0);

        // Get Selected Count
        $count = count($_POST['certificate_ids']);
        $refreshed_count = 0;

        // Cycle through array and refresh each record
        foreach ($_POST['certificate_ids'] as $certificate_id) {

            $certificate_id = intval($certificate_id);

            // Get Name, Domain and Client ID for lookup, logging and alert message
            $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_domain, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
            $row = mysqli_fetch_assoc($sql);
            $certificate_name = escapeSql($row['certificate_name']);
            $certificate_domain = escapeSql($row['certificate_domain']);
            $client_id = intval($row['certificate_client_id']);

            enforceClientAccess();

            // Skip certificates without a domain to query (eg manually pasted keys)
            if (!$certificate_domain) {
                continue;
            }

            // Get fresh certificate from the live host
            $certificate = getSslCertificate($certificate_domain);

            if ($certificate['success']) {

                $expire = escapeSql($certificate['expire']);
                $issued_by = escapeSql($certificate['issued_by']);
                $public_key = escapeSql($certificate['public_key']);

                mysqli_query($mysqli,"UPDATE certificates SET certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key' WHERE certificate_id = $certificate_id");

                logAudit("Certificate", "Refresh", "$session_name refreshed certificate $certificate_name", $client_id, $certificate_id);

                $refreshed_count++;
            }
        }

        logAudit("Certificate", "Bulk Refresh", "$session_name refreshed $refreshed_count certificate(s)", $client_id);

        flashAlert("Refreshed <strong>$refreshed_count</strong> of <strong>$count</strong> certificate(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_certificates'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    if (isset($_POST['certificate_ids'])) {

        // Get selected count
        $count = count($_POST['certificate_ids']);

        // Cycle through array and delete each certificate
        foreach ($_POST['certificate_ids'] as $certificate_id) {

            $certificate_id = intval($certificate_id);

            // Get Certificate Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
            $row = mysqli_fetch_assoc($sql);
            $certificate_name = escapeSql($row['certificate_name']);
            $client_id = intval($row['certificate_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id");

            logAudit("Certificate", "Delete", "$session_name deleted certificate $certificate_name", $client_id);

        }

        logAudit("Certificate", "Bulk Delete", "$session_name deleted $count certificates", $client_id);

        flashAlert("Deleted <strong>$count</strong> certificate(s)", 'error');

    }

    redirect();

}

if (isExportRequest('export_certificates')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_certificates']);

    // Filters inherited from the certificates page - mirrors agent/certificates.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND certificate_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "certificate_archived_at IS NOT NULL" : "certificate_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (certificate_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR certificate_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND certificate_archived_at IS NULL)";
    }

    // Expiring In Filter
    if (!empty($_POST['expire_days'])) {
        if ($_POST['expire_days'] == "expired") {
            $expire_query = "AND (certificate_expire IS NOT NULL AND certificate_expire != '0000-00-00' AND certificate_expire < CURDATE())";
            $filter_summary['Expiry'] = 'Expired';
        } else {
            $expire_days = intval($_POST['expire_days']);
            $expire_query = "AND (certificate_expire IS NOT NULL AND certificate_expire != '0000-00-00' AND certificate_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
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
        "SELECT * FROM certificates
        LEFT JOIN clients ON client_id = certificate_client_id
        WHERE $archive_query
        AND (certificate_name LIKE '%$q%' OR certificate_domain LIKE '%$q%' OR certificate_description LIKE '%$q%' OR certificate_issued_by LIKE '%$q%' OR client_name LIKE '%$q%')
        " . clientScopeSql('certificate_client_id') . "
        $client_query
        $expire_query
        ORDER BY certificate_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('certificates', $format, $file_name_prepend . 'Certificates', 'Certificates', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Certificate", "Export", "$session_name exported $num_rows certificate(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
