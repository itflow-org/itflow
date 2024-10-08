<?php

/*
 * ITFlow - GET/POST request handler for client domains
 */

if (isset($_POST['add_domain'])) {

    enforceUserPermission('module_support', 2);

    require_once 'domain_model.php';
    $extended_log_description = '';
    $client_id = intval($_POST['client_id']);

    // Set/check/lookup expiry date
    if (strtotime($expire)) {
        $expire = "'" . $expire . "'";
    }
    else {
        $expire = getDomainExpirationDate($name);
        if (strtotime($expire)) {
            $expire = "'" . $expire . "'";
        } else {
            $expire = 'NULL';
        }
    }

    // NS, MX, A and WHOIS records/data
    $records = getDomainRecords($name);
    $a = sanitizeInput($records['a']);
    $ns = sanitizeInput($records['ns']);
    $mx = sanitizeInput($records['mx']);
    $txt = sanitizeInput($records['txt']);
    $whois = sanitizeInput($records['whois']);

    // Add domain record
    mysqli_query($mysqli,"INSERT INTO domains SET domain_name = '$name', domain_description = '$description', domain_registrar = $registrar,  domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost, domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois', domain_notes = '$notes', domain_client_id = $client_id");

    // Get inserted ID (for linking certificate, if exists)
    $domain_id = mysqli_insert_id($mysqli);

    // Get SSL cert for domain (if exists)
    $certificate = getSSL($name);
    if ($certificate['success'] == "TRUE") {
        $expire = sanitizeInput($certificate['expire']);
        $issued_by = sanitizeInput($certificate['issued_by']);
        $public_key = sanitizeInput($certificate['public_key']);

        mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_domain = '$name', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id");
        $extended_log_description = ', with associated SSL cert';
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Create', log_description = '$session_name created domain $name$extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id");

    $_SESSION['alert_message'] = "Domain <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_domain'])) {

    enforceUserPermission('module_support', 2);

    require_once 'domain_model.php';
    $domain_id = intval($_POST['domain_id']);


//    if (empty($expire) || (new DateTime($expire)) < (new DateTime())) {
//        // Update domain expiry date
//        $expire = getDomainExpirationDate($name);
//    }

    // Set/check/lookup expiry date
    if (strtotime($expire) && (new DateTime($expire)) > (new DateTime())) {
        $expire = "'" . $expire . "'";
    }
    else {
        $expire = getDomainExpirationDate($name);
        if (strtotime($expire)) {
            $expire = "'" . $expire . "'";
        } else {
            $expire = 'NULL';
        }
    }

    $client_id = intval($_POST['client_id']);

    // Update NS, MX, A and WHOIS records/data
    $records = getDomainRecords($name);
    $a = sanitizeInput($records['a']);
    $ns = sanitizeInput($records['ns']);
    $mx = sanitizeInput($records['mx']);
    $txt = sanitizeInput($records['txt']);
    $whois = sanitizeInput($records['whois']);

    mysqli_query($mysqli,"UPDATE domains SET domain_name = '$name', domain_description = '$description', domain_registrar = $registrar,  domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost, domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois', domain_notes = '$notes' WHERE domain_id = $domain_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Modify', log_description = '$session_name modified domain $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id");

    $_SESSION['alert_message'] = "Domain <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_domain'])) {

    enforceUserPermission('module_support', 2);

    $domain_id = intval($_GET['archive_domain']);

    //Get domain Name
    $sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_id = $domain_id");
    $row = mysqli_fetch_array($sql);
    $domain_name = sanitizeInput($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NOW() WHERE domain_id = $domain_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Archive', log_description = '$session_name archived domain $domain_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Domain <strong>$domain_name archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['unarchive_domain'])){

    enforceUserPermission('module_support', 2);

    $domain_id = intval($_GET['unarchive_domain']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
    $row = mysqli_fetch_array($sql);
    $domain_name = sanitizeInput($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NULL WHERE domain_id = $domain_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'domain', log_action = 'Unarchive', log_description = '$session_name restored domain $domain_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id");

    $_SESSION['alert_message'] = "Domain <strong>$domain_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_domain'])) {

    enforceUserPermission('module_support', 3);

    $domain_id = intval($_GET['delete_domain']);

    // Get Domain Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
    $row = mysqli_fetch_array($sql);
    $domain_name = sanitizeInput($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    mysqli_query($mysqli,"DELETE FROM domains WHERE domain_id = $domain_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Delete', log_description = '$session_name deleted domain $domain_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Domain <strong>$domain_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_archive_domains'])) {
    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $domain_ids = $_POST['domain_ids']; // Get array of IDs to be deleted

    if (!empty($domain_ids)) {

        // Cycle through array and archive each record
        foreach ($domain_ids as $domain_id) {

            $domain_id = intval($domain_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
            $row = mysqli_fetch_array($sql);
            $domain_name = sanitizeInput($row['domain_name']);
            $client_id = intval($row['domain_client_id']);

            mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NOW() WHERE domain_id = $domain_id");

            // Individual Contact logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Archive', log_description = '$session_name archived domain $domain_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id");
            $count++;
        }

        // Bulk Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Domain', log_action = 'Archive', log_description = '$session_name archived $count domains', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived $count domain(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_domains'])) {
    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $domain_ids = $_POST['domain_ids']; // Get array of IDs

    if (!empty($domain_ids)) {

        // Cycle through array and unarchive
        foreach ($domain_ids as $domain_id) {

            $domain_id = intval($domain_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
            $row = mysqli_fetch_array($sql);
            $domain_name = sanitizeInput($row['domain_name']);
            $client_id = intval($row['domain_client_id']);

            mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NULL WHERE domain_id = $domain_id");

            // Individual logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Unarchive', log_description = '$session_name Unarchived domain $domains_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id");


            $count++;
        }

        // Bulk Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Domain', log_action = 'Unarchive', log_description = '$session_name Unarchived $count domains', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Unarchived $count domain(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_domains'])) {
    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $domain_ids = $_POST['domain_ids']; // Get array of domain IDs to be deleted
    $client_id = intval($_POST['client_id']);

    if (!empty($domain_ids)) {

        // Cycle through array and delete each domain
        foreach ($domain_ids as $domain_id) {

            $domain_id = intval($domain_id);
            mysqli_query($mysqli, "DELETE FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Domain', log_action = 'Delete', log_description = '$session_name deleted a domain (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $domain_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Domain', log_action = 'Delete', log_description = '$session_name bulk deleted $count domains', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count domain(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['export_client_domains_csv'])) {

    enforceUserPermission('module_support');

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_client_id = $client_id ORDER BY domain_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Domains-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Domain', 'Description', 'Registrar', 'Web Host', 'Expiration Date');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['domain_name'], $row['domain_description'], $row['domain_registrar'], $row['domain_webhost'], $row['domain_expire']);
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Domain', log_action = 'Export', log_description = '$session_name exported $num_rows domain(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    exit;

}
