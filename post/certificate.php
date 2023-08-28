<?php

/*
 * ITFlow - GET/POST request handler for client SSL certificates
 */

if (isset($_POST['add_certificate'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $domain = sanitizeInput($_POST['domain']);
    $issued_by = sanitizeInput($_POST['issued_by']);
    $expire = sanitizeInput($_POST['expire']);
    $public_key = sanitizeInput($_POST['public_key']);
    $domain_id = intval($_POST['domain_id']);

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

    mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id");

    $certificate_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Create', log_description = '$session_name created certificate $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $certificate_id");

    $_SESSION['alert_message'] = "Certificate <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_certificate'])) {

    validateTechRole();

    $certificate_id = intval($_POST['certificate_id']);
    $name = sanitizeInput($_POST['name']);
    $domain = sanitizeInput($_POST['domain']);
    $issued_by = sanitizeInput($_POST['issued_by']);
    $expire = sanitizeInput($_POST['expire']);
    $public_key = sanitizeInput($_POST['public_key']);
    $domain_id = intval($_POST['domain_id']);
    $client_id = intval($_POST['client_id']);

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

    mysqli_query($mysqli,"UPDATE certificates SET certificate_name = '$name', certificate_domain = '$domain', certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key', certificate_domain_id = '$domain_id' WHERE certificate_id = $certificate_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Modify', log_description = '$session_name modified certificate $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $certificate_id");

    $_SESSION['alert_message'] = "Certificate <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_certificate'])) {

    validateTechRole();

    $certificate_id = intval($_GET['archive_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_array($sql);
    $certificate_name = sanitizeInput($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    mysqli_query($mysqli,"UPDATE certificates SET certificate_archived_at = NOW() WHERE certificate_id = $certificate_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Archive', log_description = '$session_name archived certificate $certificate_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $certificate_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Certificate <strong>$certificate_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_certificate'])) {

    validateAdminRole();

    $certificate_id = intval($_GET['delete_certificate']);

    // Get Certificate Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT certificate_name, certificate_client_id FROM certificates WHERE certificate_id = $certificate_id");
    $row = mysqli_fetch_array($sql);
    $certificate_name = sanitizeInput($row['certificate_name']);
    $client_id = intval($row['certificate_client_id']);

    mysqli_query($mysqli,"DELETE FROM certificates WHERE certificate_id = $certificate_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Delete', log_description = '$session_name deleted certificate $certificate_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $certificate_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Certificate <strong>$certificate_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_certificates'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $certificate_ids = $_POST['certificate_ids']; // Get array of scheduled tickets IDs to be deleted

    if (!empty($certificate_ids)) {

        // Cycle through array and delete each scheduled ticket
        foreach ($certificate_ids as $certificate_id) {

            $certificate_id = intval($certificate_id);
            mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_id = $certificate_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Certificate', log_action = 'Delete', log_description = '$session_name deleted certificate (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $certificate_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Certificate', log_action = 'Delete', log_description = '$session_name bulk deleted $count certificates', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count certificate(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['export_client_certificates_csv'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
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
        $fields = array('Name', 'Domain', 'Issuer', 'Expiration Date');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['certificate_name'], $row['certificate_domain'], $row['certificate_issued_by'], $row['certificate_expire']);
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Certificate', log_action = 'Export', log_description = '$session_name exported $num_rows certificate(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    exit;

}
