<?php

/*
 * ITFlow - GET/POST request handler for client SSL certificates
 */

if (isset($_POST['add_certificate'])) {

    enforceUserPermission('module_support', 2);

    require_once 'post/user/certificate_model.php';

    // Parse public key data for a manually provided public key
    if (!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = sanitizeInput($public_key_obj['issuer']['O']);
        }
    }

    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_description = '$description', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_notes = '$notes', certificate_domain_id = $domain_id, certificate_client_id = $client_id");

    $certificate_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Certificate", "Create", "$session_name created certificate $name", $client_id, $certificate_id);

    $_SESSION['alert_message'] = "Certificate <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_certificate'])) {

    enforceUserPermission('module_support', 2);

    require_once 'post/user/certificate_model.php';
    $certificate_id = intval($_POST['certificate_id']);

    // Parse public key data for a manually provided public key
    if (!empty($public_key) && (empty($expire) && empty($issued_by))) {
        // Parse the public certificate key. If successful, set attributes from the certificate
        $public_key_obj = openssl_x509_parse($_POST['public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = sanitizeInput($public_key_obj['issuer']['O']);
        }
    }

    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    mysqli_query($mysqli,"UPDATE certificates SET certificate_name = '$name', certificate_description = '$description', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_notes = '$notes', certificate_domain_id = '$domain_id' WHERE certificate_id = $certificate_id");

    // Logging
    logAction("Certificate", "Edit", "$session_name edited certificate $name", $client_id, $certificate_id);

    $_SESSION['alert_message'] = "Certificate <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_certificate'])) {

    enforceUserPermission('module_support', 2);

    $certificate_id = intval($_GET['archive_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_array($sql);
    $certificate_name = sanitizeInput($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    mysqli_query($mysqli,"UPDATE certificates SET certificate_archived_at = NOW() WHERE certificate_id = $certificate_id");

    // logging
    logAction("Certificate", "Archive", "$session_name arhvived certificate $certificate_name", $client_id, $certificate_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Certificate <strong>$certificate_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_certificate'])) {

    enforceUserPermission('module_support', 3);

    $certificate_id = intval($_GET['delete_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_array($sql);
    $certificate_name = sanitizeInput($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    mysqli_query($mysqli,"DELETE FROM certificates WHERE certificate_id = $certificate_id");

    // Logging
    logAction("Certificate", "Delete", "$session_name deleted certificate $name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Certificate <strong>$certificate_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_certificates'])) {
    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['certificate_ids'])) {

        // Get selected count
        $count = count($_POST['certificate_ids']);

        // Cycle through array and delete each certificate
        foreach ($_POST['certificate_ids'] as $certificate_id) {

            $certificate_id = intval($certificate_id);

            // Get Certificate Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
            $row = mysqli_fetch_array($sql);
            $certificate_name = sanitizeInput($row['certificate_name']);
            $client_id = intval($row['certificate_client_id']);

            mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id");
            
            // Logging
            logAction("Certificate", "Delete", "$session_name deleted certificate $certificate_name", $client_id);

        }

        // Logging
        logAction("Certificate", "Bulk Delete", "$session_name deleted $count certificates", $client_id);

        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> certificate(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['export_client_certificates_csv'])) {

    enforceUserPermission('module_support');

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_client_id = $client_id ORDER BY certificate_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Certificates-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Domain', 'Issuer', 'Expiration Date');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['certificate_name'], $row['certificate_description'], $row['certificate_domain'], $row['certificate_issued_by'], $row['certificate_expire']);
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
    logAction("Certificate", "Export", "$session_name exported $num_rows certificate(s) to a CSV file", $client_id);

    exit;

}
