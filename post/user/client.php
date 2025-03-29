<?php

/*
 * ITFlow - GET/POST request handler for clients/customers (overview)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_client'])) {

    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_client', 2);

    require_once 'post/user/client_model.php';

    $location_phone_country_code = preg_replace("/[^0-9]/", '', $_POST['location_phone_country_code']);
    $location_phone = preg_replace("/[^0-9]/", '', $_POST['location_phone']);
    $location_extension = preg_replace("/[^0-9]/", '', $_POST['location_extension']);
    $location_fax_country_code = preg_replace("/[^0-9]/", '', $_POST['location_fax_country_code']);
    $location_fax = preg_replace("/[^0-9]/", '', $_POST['location_fax']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $country = sanitizeInput($_POST['country']);
    $contact = sanitizeInput($_POST['contact']);
    $title = sanitizeInput($_POST['title']);
    $contact_phone_country_code = preg_replace("/[^0-9]/", '', $_POST['contact_phone_country_code']);
    $contact_phone = preg_replace("/[^0-9]/", '', $_POST['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '', $_POST['contact_extension']);
    $contact_mobile_country_code = preg_replace("/[^0-9]/", '', $_POST['contact_mobile_country_code']);
    $contact_mobile = preg_replace("/[^0-9]/", '', $_POST['contact_mobile']);
    $contact_email = sanitizeInput($_POST['contact_email']);

    $extended_log_description = '';

    // Create client
    mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_rate = $rate, client_currency_code = '$currency_code', client_net_terms = $net_terms, client_tax_id_number = '$tax_id_number', client_lead = $lead, client_abbreviation = '$abbreviation', client_notes = '$notes', client_accessed_at = NOW()");

    $client_id = mysqli_insert_id($mysqli);

    if (!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
        file_put_contents("uploads/clients/$client_id/index.php", "");
    }

    // Create Referral if it doesn't exist
    $sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND category_name = '$referral'");
    if(mysqli_num_rows($sql) == 0) {
        mysqli_query($mysqli, "INSERT INTO categories SET category_name = '$referral', category_type = 'Referral'");
        // Logging
        logAction("Category", "Create", "$session_name created referral category $referral");
    }

    // Create Location
    if (!empty($location_phone) || !empty($address) || !empty($city) || !empty($state) || !empty($zip)) {
        mysqli_query($mysqli, "INSERT INTO locations SET location_name = 'Primary', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone_country_code = '$location_phone_country_code', location_phone = '$location_phone', location_extension = '$location_extension', location_fax_country_code = '$location_fax_country_code', location_fax = '$location_fax', location_country = '$country', location_primary = 1, location_client_id = $client_id");

        //Extended Logging
        $extended_log_description .= ", primary location $address added";
    }


    // Create Contact
    if (!empty($contact) || !empty($title) || !empty($contact_phone) || !empty($contact_mobile) || !empty($contact_email)) {
        mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$contact', contact_title = '$title', contact_phone_country_code = '$contact_phone_country_code', contact_phone = '$contact_phone', contact_extension = '$contact_extension', contact_mobile_country_code = '$contact_mobile_country_code', contact_mobile = '$contact_mobile', contact_email = '$contact_email', contact_primary = 1, contact_important = 1, contact_client_id = $client_id");

        //Extended Logging
        $extended_log_description .= ", primary contact $contact added";
    }

    // Add Tags
    if (isset($_POST['tags'])) {
        foreach ($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO client_tags SET client_id = $client_id, tag_id = $tag");
        }
    }

    // Create domain in domains/certificates
    if (!empty($website) && filter_var($website, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        // Get domain expiry date
        $expire = getDomainExpirationDate($website);

        // NS, MX, A and WHOIS records/data
        $records = getDomainRecords($website);
        $a = sanitizeInput($records['a']);
        $ns = sanitizeInput($records['ns']);
        $mx = sanitizeInput($records['mx']);
        $whois = sanitizeInput($records['whois']);

        // Add domain record
        mysqli_query($mysqli, "INSERT INTO domains SET domain_name = '$website', domain_registrar = 0,  domain_webhost = 0, domain_expire = '$expire', domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_raw_whois = '$whois', domain_client_id = $client_id");

        //Extended Logging
        $extended_log_description .= ", domain $website added";

        // Get inserted ID (for linking certificate, if exists)
        $domain_id = mysqli_insert_id($mysqli);

        // Get SSL cert for domain (if exists)
        $certificate = getSSL($website);
        if ($certificate['success'] == "TRUE") {
            $expire = sanitizeInput($certificate['expire']);
            $issued_by = sanitizeInput($certificate['issued_by']);
            $public_key = sanitizeInput($certificate['public_key']);

            mysqli_query($mysqli, "INSERT INTO certificates SET certificate_name = '$website', certificate_domain = '$website', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id");

            //Extended Logging
            $extended_log_description .= ", SSL certificate $website added";
        }

    }

    // Logging
    logAction("Client", "Create", "$session_name created client $name$extended_log_description", $client_id, $client_id);

    $_SESSION['alert_message'] = "Client <strong>$name</strong> created";

    header("Location: clients.php");

}

if (isset($_POST['edit_client'])) {

    enforceUserPermission('module_client', 2);

    require_once 'post/user/client_model.php';

    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli, "UPDATE clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_rate = $rate, client_currency_code = '$currency_code', client_net_terms = $net_terms, client_tax_id_number = '$tax_id_number', client_lead = $lead, client_abbreviation = '$abbreviation', client_notes = '$notes' WHERE client_id = $client_id");

    // Create Referral if it doesn't exist
    $sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND category_name = '$referral'");
    if(mysqli_num_rows($sql) == 0) {
        mysqli_query($mysqli, "INSERT INTO categories SET category_name = '$referral', category_type = 'Referral'");

        // Logging
        logAction("Category", "Create", "$session_name created referral category $referral");
    }

    // Tags
    // Delete existing tags
    mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_id = $client_id");

    // Add new tags
    if(isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO client_tags SET client_id = $client_id, tag_id = $tag");
        }
    }

    // Logging
    logAction("Client", "Edit", "$session_name edited client $name", $client_id, $client_id);

    $_SESSION['alert_message'] = "Client <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['archive_client'])) {

    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('module_client', 2);

    $client_id = intval($_GET['archive_client']);

    // Get Client Name
    $sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");

    // Logging
    logAction("Client", "Archive", "$session_name archived client $client_name", $client_id, $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Client <strong>$client_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['undo_archive_client'])) {

    enforceUserPermission('module_client', 2);

    $client_id = intval($_GET['undo_archive_client']);

    // Get Client Name
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NULL WHERE client_id = $client_id");

    // Logging
    logAction("Client", "Unarchive", "$session_name unarchived client $client_name", $client_id, $client_id);

    $_SESSION['alert_message'] = "Client <strong>$client_name</strong> unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_client'])) {

    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('module_client', 3);

    $client_id = intval($_GET['delete_client']);

    //Get Client Name
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    // Delete Client Data
    mysqli_query($mysqli, "DELETE FROM api_keys WHERE api_key_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM documents WHERE document_client_id = $client_id");

    // Delete Contacts
    mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_client_id = $client_id");

    // Delete Assets
    mysqli_query($mysqli, "DELETE FROM assets WHERE asset_client_id = $client_id");

    // Delete Domains and associated records
    mysqli_query($mysqli, "DELETE FROM domains WHERE domain_client_id = $client_id");

    mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM files WHERE file_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM folders WHERE folder_client_id = $client_id");

    //Delete Invoices and Invoice Referencing data
    $sql = mysqli_query($mysqli, "SELECT invoice_id FROM invoices WHERE invoice_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $invoice_id = $row['invoice_id'];
        mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
        mysqli_query($mysqli, "DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
        mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
    }
    mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_client_id = $client_id");

    // Delete Locations and location tags
    mysqli_query($mysqli, "DELETE FROM locations WHERE location_client_id = $client_id");

    mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM logs WHERE log_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM networks WHERE network_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM notifications WHERE notification_client_id = $client_id");

    //Delete Quote and related items
    $sql = mysqli_query($mysqli, "SELECT quote_id FROM quotes WHERE quote_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $quote_id = $row['quote_id'];

        mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_quote_id = $quote_id");
    }
    mysqli_query($mysqli, "DELETE FROM quotes WHERE quote_client_id = $client_id");

    // Delete Recurring Invoices and associated items
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $recurring_invoice_id = $row['recurring_invoice_id'];
        mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
    }
    mysqli_query($mysqli, "DELETE FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");

    mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id");

    // Delete Services
    mysqli_query($mysqli, "DELETE FROM services WHERE service_client_id = $client_id");

    // Delete Shared Items
    mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_client_id = $client_id");

    // Delete Software
    mysqli_query($mysqli, "DELETE FROM software WHERE software_client_id = $client_id");

    // Delete tickets and related data
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $ticket_id = $row['ticket_id'];
        mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");
        mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");
    }
    mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM trips WHERE trip_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_client_id = $client_id");

    //Delete Client Files
    removeDirectory('uploads/clients/$client_id');

    //Finally Remove the Client
    mysqli_query($mysqli, "DELETE FROM clients WHERE client_id = $client_id");

    //Logging
    logAction("Client", "Deleted", "$session_name deleted Client $client_name and all associated data");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Client <strong>$client_name</strong> deleted along with all associated data";

    header("Location: clients.php");
}

if (isset($_POST['export_clients_csv'])) {

    enforceUserPermission('module_client', 1);

    //get records from database
    $sql = mysqli_query($mysqli, "SELECT * FROM clients
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        ORDER BY client_name ASC
    ");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = $session_company_name . "-Clients-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Client Name', 'Industry', 'Referral', 'Website', 'Location Name', 'Location Phone', 'Location Address', 'City', 'State', 'Postal Code', 'Country', 'Contact Name', 'Title', 'Contact Phone', 'Extension', 'Contact Mobile', 'Contact Email', 'Hourly Rate', 'Currency', 'Payment Terms', 'Tax ID', 'Abbreviation');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['client_name'], $row['client_type'], $row['client_referral'], $row['client_website'], $row['location_name'], formatPhoneNumber($row['location_phone']), $row['location_address'], $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_country'], $row['contact_name'], $row['contact_title'], formatPhoneNumber($row['contact_phone']), $row['contact_extension'], formatPhoneNumber($row['contact_mobile']), $row['contact_email'], $row['client_rate'], $row['client_currency_code'], $row['client_net_terms'], $row['client_tax_id_number'], $row['client_abbreviation']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);

        logAction("Client", "Export", "$session_name exported $num_rows client(s) to a CSV file");

    }
    exit;

}

if (isset($_POST["import_clients_csv"])) {

    enforceUserPermission('module_client', 2);
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

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 22) {
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
            $duplicate_detect = 0;
            if (isset($column[0])) {
                $name = sanitizeInput($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM clients WHERE client_name = '$name'")) > 0) {
                    $duplicate_detect = 1;
                }
            }

            $industry = '';
            if (isset($column[1])) {
                $industry = sanitizeInput($column[1]);
            }

            $referral = '';
            if (isset($column[2])) {
                $referral = sanitizeInput($column[2]);
            }

            $website = '';
            if (isset($column[3])) {
                $website = sanitizeInput(preg_replace("(^https?://)", "", $column[3]));
            }

            $location_name = '';
            if (isset($column[4])) {
                $location_name = sanitizeInput($column[4]);
            }

            $location_phone = '';
            if (isset($column[5])) {
                $location_phone = preg_replace("/[^0-9]/", '', $column[5]);
            }

            $address = '';
            if (isset($column[6])) {
                $address = sanitizeInput($column[6]);
            }

            $city = '';
            if (isset($column[7])) {
                $city = sanitizeInput($column[7]);
            }

            $state = '';
            if (isset($column[8])) {
                $state = sanitizeInput($column[8]);
            }

            $zip = '';
            if (isset($column[9])) {
                $zip = sanitizeInput($column[9]);
            }

            $country = '';
            if (isset($column[10])) {
                $country = sanitizeInput($column[10]);
            }

            $contact_name = '';
            if (isset($column[11])) {
                $contact_name = sanitizeInput($column[11]);
            }

            $title = '';
            if (isset($column[12])) {
                $title = sanitizeInput($column[12]);
            }

            $contact_phone = '';
            if (isset($column[13])) {
                $contact_phone = preg_replace("/[^0-9]/", '',$column[13]);
            }

            $contact_extension = '';
            if (isset($column[14])) {
                $contact_extension = preg_replace("/[^0-9]/", '',$column[14]);
            }

            $contact_mobile = '';
            if (isset($column[15])) {
                $contact_mobile = preg_replace("/[^0-9]/", '',$column[15]);
            }

            $contact_email = '';
            if (isset($column[16])) {
                $contact_email = sanitizeInput($column[16]);
            }

            $hourly_rate = $config_default_hourly_rate;
            if (isset($column[17])) {
                $hourly_rate = floatval($column[17]);
            }

            $currency_code = sanitizeInput($session_company_currency);
            if (isset($column[18])) {
                $currency_code = sanitizeInput($column[18]);
            }

            $payment_terms = sanitizeInput($config_default_net_terms);
            if (isset($column[19])) {
                $payment_terms = intval($column[19]);
            }

            $tax_id_number = '';
            if (isset($column[20])) {
                $tax_id_number = sanitizeInput($column[20]);
            }

            $abbreviation = '';
            if (isset($column[21])) {
                $abbreviation = sanitizeInput($column[21]);
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0) {
                //Add
                // Create client
                mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name', client_type = '$industry', client_website = '$website', client_referral = '$referral', client_rate = $hourly_rate, client_currency_code = '$currency_code', client_net_terms = $payment_terms, client_tax_id_number = '$tax_id_number', client_abbreviation = '$abbreviation'");

                $client_id = mysqli_insert_id($mysqli);

                if (!file_exists("uploads/clients/$client_id")) {
                    mkdir("uploads/clients/$client_id");
                    file_put_contents("uploads/clients/$client_id/index.php", "");
                }

                // Create Referral if it doesn't exist
                $sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND category_name = '$referral'");
                if(mysqli_num_rows($sql) == 0) {
                    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '$referral', category_type = 'Referral'");
                    // Logging
                    logAction("Category", "Create", "$session_name created new refferal category $referral");
                }

                // Create Location
                mysqli_query($mysqli, "INSERT INTO locations SET location_name = '$location_name', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$location_phone', location_country = '$country', location_primary = 1, location_client_id = $client_id");

                // Create Contact
                mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$contact_name', contact_title = '$title', contact_phone = '$contact_phone', contact_extension = '$contact_extension', contact_mobile = '$contact_mobile', contact_email = '$contact_email', contact_primary = 1, contact_important = 1, contact_client_id = $client_id");

                $row_count = $row_count + 1;

            } else {

                $duplicate_count = $duplicate_count + 1;

            }

        }
        fclose($file);

        //Logging
        logAction("Client", "Import", "$session_name imported $row_count client(s) via CSV file, $duplicate_count duplicate(s) found");

        $_SESSION['alert_message'] = "<strong>$row_count</strong> Client(s) added, <strong>$duplicate_count</strong> duplicate(s) found";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    }

    //Check for any errors, if there are notify user and redirect
    if ($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['download_clients_csv_template'])) {

    $delimiter = ",";
    $filename = "Clients-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Client Name', 'Industry', 'Referral', 'Website', 'Location Name', 'Location Phone', 'Location Address', 'City', 'State', 'Postal Code', 'Country', 'Contact Name', 'Title', 'Contact Phone', 'Extension', 'Contact Mobile', 'Contact Email', 'Hourly Rate', 'Currency', 'Payment Terms', 'Tax ID', 'Abbreviation');
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

if (isset($_POST["export_client_pdf"])) {

    // Enforce permissions
    enforceUserPermission("module_client", 3);
    enforceUserPermission("module_support", 1);
    enforceUserPermission("module_sales", 1);
    enforceUserPermission("module_financial", 1);

    $client_id = intval($_POST["client_id"]);
    $export_contacts = intval($_POST["export_contacts"]);
    $export_locations = intval($_POST["export_locations"]);
    $export_assets = intval($_POST["export_assets"]);
    $export_software = intval($_POST["export_software"]);
    $export_credentials = 0;
    if (lookupUserPermission("module_credential") >= 1) {
        $export_credentials = intval($_POST["export_credentials"]);
    }
    $export_networks = intval($_POST["export_networks"]);
    $export_certificates = intval($_POST["export_certificates"]);
    $export_domains = intval($_POST["export_domains"]);
    $export_tickets = intval($_POST["export_tickets"]);
    $export_recurring_tickets = intval($_POST["export_recurring_tickets"]);
    $export_vendors = intval($_POST["export_vendors"]);
    $export_invoices = intval($_POST["export_invoices"]);
    $export_recurring_invoices = intval($_POST["export_recurring_invoices"]);
    $export_quotes = intval($_POST["export_quotes"]);
    $export_payments = intval($_POST["export_payments"]);
    $export_trips = intval($_POST["export_trips"]);
    $export_logs = intval($_POST["export_logs"]);

    // Logging
    logAction("Client", "Export", "$session_name exported client data to a PDF file", $client_id, $client_id);

    // Get client record (joining primary contact and primary location)
    $sql = mysqli_query($mysqli, "SELECT * FROM clients 
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE client_id = $client_id
    ");
    $row = mysqli_fetch_array($sql);

    // Immediately sanitize retrieved values
    $client_name = nullable_htmlentities($row["client_name"]);
    $location_address = nullable_htmlentities($row["location_address"]);
    $location_city = nullable_htmlentities($row["location_city"]);
    $location_state = nullable_htmlentities($row["location_state"]);
    $location_zip = nullable_htmlentities($row["location_zip"]);
    $contact_name = nullable_htmlentities($row["contact_name"]);
    $contact_phone_country_code = nullable_htmlentities($row["contact_phone_country_code"]);
    $contact_phone = nullable_htmlentities(formatPhoneNumber($row["contact_phone"], $contact_phone_country_code));
    $contact_extension = nullable_htmlentities($row["contact_extension"]);
    $contact_mobile_country_code = nullable_htmlentities($row["contact_mobile_country_code"]);
    $contact_mobile = nullable_htmlentities(formatPhoneNumber($row["contact_mobile"], $contact_mobile_country_code));
    $contact_email = nullable_htmlentities($row["contact_email"]);
    $client_website = nullable_htmlentities($row["client_website"]);

    // Other queries remain unchanged
    $sql_contacts = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
    $sql_locations = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
    $sql_vendors = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
    $sql_credentials = mysqli_query($mysqli, "SELECT * FROM credentials WHERE credential_client_id = $client_id ORDER BY credential_name ASC");
    $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets 
        LEFT JOIN contacts ON asset_contact_id = contact_id 
        LEFT JOIN locations ON asset_location_id = location_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_client_id = $client_id
        AND asset_archived_at IS NULL
        ORDER BY asset_type ASC"
    );
    $sql_asset_workstations = mysqli_query($mysqli, "SELECT * FROM assets 
        LEFT JOIN contacts ON asset_contact_id = contact_id 
        LEFT JOIN locations ON asset_location_id = location_id 
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 
        WHERE asset_client_id = $client_id 
        AND (asset_type = 'desktop' OR asset_type = 'laptop') 
        AND asset_archived_at IS NULL 
        ORDER BY asset_name ASC"
    );
    $sql_asset_servers = mysqli_query($mysqli, "SELECT * FROM assets 
        LEFT JOIN locations ON asset_location_id = location_id 
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 
        WHERE asset_client_id = $client_id 
        AND asset_type = 'server' 
        AND asset_archived_at IS NULL 
        ORDER BY asset_name ASC"
    );
    $sql_asset_vms = mysqli_query($mysqli, "SELECT * FROM assets 
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 
        WHERE asset_client_id = $client_id 
        AND asset_type = 'virtual machine' 
        AND asset_archived_at IS NULL 
        ORDER BY asset_name ASC"
    );
    $sql_asset_network = mysqli_query($mysqli, "SELECT * FROM assets 
        LEFT JOIN locations ON asset_location_id = location_id 
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 
        WHERE asset_client_id = $client_id 
        AND (asset_type = 'Firewall/Router' OR asset_type = 'Switch' OR asset_type = 'Access Point') 
        AND asset_archived_at IS NULL 
        ORDER BY asset_type ASC"
    );
    $sql_asset_other = mysqli_query($mysqli, "SELECT * FROM assets 
        LEFT JOIN contacts ON asset_contact_id = contact_id 
        LEFT JOIN locations ON asset_location_id = location_id 
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 
        WHERE asset_client_id = $client_id 
        AND (asset_type NOT LIKE 'laptop' AND asset_type NOT LIKE 'desktop' AND asset_type NOT LIKE 'server' AND asset_type NOT LIKE 'virtual machine' AND asset_type NOT LIKE 'firewall/router' AND asset_type NOT LIKE 'switch' AND asset_type NOT LIKE 'access point') 
        AND asset_archived_at IS NULL 
        ORDER BY asset_type ASC"
    );
    $sql_networks = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL ORDER BY network_name ASC");
    $sql_domains = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL ORDER BY domain_name ASC");
    $sql_certficates = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL ORDER BY certificate_name ASC");
    $sql_software = mysqli_query($mysqli, "SELECT * FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL ORDER BY software_name ASC");

    $sql_user_licenses = mysqli_query($mysqli, "
        SELECT 
            contact_name,
            software_name
        FROM 
            software_contacts
        JOIN 
            contacts ON software_contacts.contact_id = contacts.contact_id
        JOIN 
            software ON software_contacts.software_id = software.software_id
        WHERE software_archived_at IS NULL
        AND contact_archived_at IS NULL
        AND software_client_id = $client_id
        AND contact_client_id = $client_id
        ORDER BY 
            contact_name, software_name;"
    );

    $sql_asset_licenses = mysqli_query($mysqli, "
        SELECT 
            asset_name,
            software_name
        FROM 
            software_assets
        JOIN 
            assets ON software_assets.asset_id = assets.asset_id
        JOIN 
            software ON software_assets.software_id = software.software_id
        WHERE software_archived_at IS NULL
        AND asset_archived_at IS NULL
        AND software_client_id = $client_id
        AND asset_client_id = $client_id
        ORDER BY 
            asset_name, software_name;"
    );

    require_once("plugins/TCPDF/tcpdf.php");

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, "UTF-8", false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($session_company_name);
    $pdf->SetTitle("$client_name - IT Documentation");

    // Enable auto page breaks with a margin from the bottom
    $pdf->SetAutoPageBreak(true, 15);

    // ----- Start Main Content -----
    $pdf->AddPage();
    $pdf->SetFont("helvetica", "", 10);

    // Build the HTML content with enhanced styling and semantic markup
    $html = "
    <style>
      body { font-family: Helvetica, Arial, sans-serif; margin: 0; padding: 0; }
      .cover { text-align: center; margin-top: 50px; margin-bottom: 30px; }
      .cover h1 { font-size: 28px; color: #000; margin-bottom: 10px; }
      .cover h2 { font-size: 20px; color: #000; margin-bottom: 10px; }
      .cover p { font-size: 14px; color: #000; }
      .client-header { padding: 10px 20px; margin-bottom: 20px; border: 1px solid #000; }
      .client-header p { margin: 4px 0; }
      .client-header strong { display: inline-block; width: 140px; }
      h2.section-title { color: #000; border-bottom: 2px solid #000; padding-bottom: 5px; margin-top: 30px; font-size: 18px; }
      h3.subsection-title { color: #000; margin-top: 20px; font-size: 16px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
      table, th, td { border: 1px solid #000; }
      table th { background-color: #000; color: #fff; padding: 10px; text-align: left; font-weight: bold; }
      table td { padding: 10px; }
      thead { display: table-header-group; }
      tbody tr { page-break-inside: avoid; }
      tr:nth-child(even) { background-color: #f2f2f2; }
      hr { border: 0; border-top: 2px solid #000; margin: 20px 0; }
    </style>
    ";

    // Cover page section (for main content, not the TOC)
    $html .= "
    <div class='cover'>
      <h1>$client_name</h1>
      <h2>IT Documentation</h2>
      <p>Export Date: " . date("F j, Y") . "</p>
    </div>
    <hr>";

    // Client header information (non-table)
    $html .= "
    <div class='client-header'>
      <p><strong>Address:</strong> $location_address</p>
      <p><strong>City State Zip:</strong> $location_city $location_state $location_zip</p>
      <p><strong>Phone:</strong> $contact_phone</p>
      <p><strong>Website:</strong> $client_website</p>
      <p><strong>Contact:</strong> $contact_name</p>
      <p><strong>Email:</strong> $contact_email</p>
    </div>";

    // Add bookmarks and TOC entries for each major section:

    // Contacts Section
    if (mysqli_num_rows($sql_contacts) > 0 && $export_contacts == 1) {
        $pdf->Bookmark("Contacts", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Contacts</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Title</th>
              <th>Department</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Mobile</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_contacts)) {
            $contact_name = nullable_htmlentities(getFallBack($row["contact_name"]));
            $contact_title = nullable_htmlentities(getFallBack($row["contact_title"]));
            $contact_department = nullable_htmlentities($row["contact_department"]);
            $contact_email = nullable_htmlentities($row["contact_email"]);
            $contact_phone_country_code = nullable_htmlentities($row["contact_phone_country_code"]);
            $contact_phone = nullable_htmlentities(formatPhoneNumber($row["contact_phone"], $contact_phone_country_code));
            $contact_extension = nullable_htmlentities($row["contact_extension"]);
            if (!empty($contact_extension)) {
                $contact_extension = "x$contact_extension";
            }
            $contact_mobile_country_code = nullable_htmlentities($row["contact_mobile_country_code"]);
            $contact_mobile = nullable_htmlentities(formatPhoneNumber($row["contact_mobile"], $contact_mobile_country_code));
            $html .= "
            <tr>
              <td>$contact_name</td>
              <td>$contact_title</td>
              <td>$contact_department</td>
              <td>$contact_email</td>
              <td>$contact_phone $contact_extension</td>
              <td>$contact_mobile</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Locations Section
    if (mysqli_num_rows($sql_locations) > 0 && $export_locations == 1) {
        $pdf->Bookmark("Locations", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Locations</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Address</th>
              <th>Phone</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_locations)) {
            $location_name = nullable_htmlentities($row["location_name"]);
            $location_address = nullable_htmlentities($row["location_address"]);
            $location_city = nullable_htmlentities($row["location_city"]);
            $location_state = nullable_htmlentities($row["location_state"]);
            $location_zip = nullable_htmlentities($row["location_zip"]);
            $location_phone_country_code = nullable_htmlentities($row["location_phone_country_code"]);
            $location_phone = nullable_htmlentities(formatPhoneNumber($row["location_phone"], $location_phone_country_code));
            $html .= "
            <tr>
              <td>$location_name</td>
              <td>$location_address $location_city $location_state $location_zip</td>
              <td>$location_phone</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Vendors Section
    if (mysqli_num_rows($sql_vendors) > 0 && $export_vendors == 1) {
        $pdf->Bookmark("Vendors", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Vendors</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Phone</th>
              <th>Website</th>
              <th>Account Number</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_vendors)) {
            $vendor_name = nullable_htmlentities($row["vendor_name"]);
            $vendor_description = nullable_htmlentities($row["vendor_description"]);
            $vendor_account_number = nullable_htmlentities($row["vendor_account_number"]);
            $vendor_phone_country_code = nullable_htmlentities($row["vendor_phone_country_code"]);
            $vendor_phone = nullable_htmlentities(formatPhoneNumber($row["vendor_phone"], $vendor_phone_country_code));
            $vendor_website = nullable_htmlentities($row["vendor_website"]);
            $html .= "
            <tr>
              <td>$vendor_name</td>
              <td>$vendor_description</td>
              <td>$vendor_phone</td>
              <td>$vendor_website</td>
              <td>$vendor_account_number</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Credentials Section
    if (mysqli_num_rows($sql_credentials) > 0 && $export_credentials == 1) {
        $pdf->Bookmark("Credentials", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Credentials</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Username</th>
              <th>Password</th>
              <th>URI</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_credentials)) {
            $credential_name = nullable_htmlentities($row["credential_name"]);
            $credential_description = nullable_htmlentities($row["credential_description"]);
            $credential_username = nullable_htmlentities(decryptCredentialEntry($row["credential_username"]));
            $credential_password = nullable_htmlentities(decryptCredentialEntry($row["credential_password"]));
            $credential_uri = nullable_htmlentities($row["credential_uri"]);
            $html .= "
            <tr>
              <td>$credential_name</td>
              <td>$credential_description</td>
              <td>$credential_username</td>
              <td>$credential_password</td>
              <td>$credential_uri</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Assets Section Header
    if (mysqli_num_rows($sql_assets) > 0 && $export_assets == 1) {
        $pdf->Bookmark("Assets", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Assets</h2>";
    }

    // Workstations
    if (mysqli_num_rows($sql_asset_workstations) > 0 && $export_assets == 1) {
        $pdf->Bookmark("Workstations", 1, 0, "", "", array(0,0,0));
        $html .= "
        <h3 class='subsection-title'>Workstations</h3>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Model</th>
              <th>Serial</th>
              <th>OS</th>
              <th>Purchase Date</th>
              <th>Warranty Expire</th>
              <th>Install Date</th>
              <th>Assigned To</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_asset_workstations)) {
            $asset_name = nullable_htmlentities($row["asset_name"]);
            $asset_type = nullable_htmlentities($row["asset_type"]);
            $asset_make = nullable_htmlentities($row["asset_make"]);
            $asset_model = nullable_htmlentities($row["asset_model"]);
            $asset_serial = nullable_htmlentities($row["asset_serial"]);
            $asset_os = nullable_htmlentities($row["asset_os"]);
            $asset_purchase_date = nullable_htmlentities($row["asset_purchase_date"]);
            $asset_warranty_expire = nullable_htmlentities($row["asset_warranty_expire"]);
            $asset_install_date = nullable_htmlentities($row["asset_install_date"]);
            $contact_name = nullable_htmlentities($row["contact_name"]);
            $location_name = nullable_htmlentities($row["location_name"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$asset_name</td>
              <td>$asset_type</td>
              <td>$asset_make $asset_model</td>
              <td>$asset_serial</td>
              <td>$asset_os</td>
              <td>$asset_purchase_date</td>
              <td>$asset_warranty_expire</td>
              <td>$asset_install_date</td>
              <td>$contact_name</td>
              <td>$location_name</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Servers
    if (mysqli_num_rows($sql_asset_servers) > 0 && $export_assets == 1) {
        $pdf->Bookmark("Servers", 1, 0, "", "", array(0,0,0));
        $html .= "
        <h3 class='subsection-title'>Servers</h3>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Model</th>
              <th>Serial</th>
              <th>OS</th>
              <th>IP</th>
              <th>Purchase Date</th>
              <th>Warranty Expire</th>
              <th>Install Date</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_asset_servers)) {
            $asset_name = nullable_htmlentities($row["asset_name"]);
            $asset_make = nullable_htmlentities($row["asset_make"]);
            $asset_model = nullable_htmlentities($row["asset_model"]);
            $asset_serial = nullable_htmlentities($row["asset_serial"]);
            $asset_os = nullable_htmlentities($row["asset_os"]);
            $asset_ip = nullable_htmlentities($row["interface_ip"]);
            $asset_purchase_date = nullable_htmlentities($row["asset_purchase_date"]);
            $asset_warranty_expire = nullable_htmlentities($row["asset_warranty_expire"]);
            $asset_install_date = nullable_htmlentities($row["asset_install_date"]);
            $location_name = nullable_htmlentities($row["location_name"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$asset_name</td>
              <td>$asset_make $asset_model</td>
              <td>$asset_serial</td>
              <td>$asset_os</td>
              <td>$asset_ip</td>
              <td>$asset_purchase_date</td>
              <td>$asset_warranty_expire</td>
              <td>$asset_install_date</td>
              <td>$location_name</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Virtual Machines
    if (mysqli_num_rows($sql_asset_vms) > 0 && $export_assets == 1) {
        $pdf->Bookmark("Virtual Machines", 1, 0, "", "", array(0,0,0));
        $html .= "
        <h3 class='subsection-title'>Virtual Machines</h3>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>OS</th>
              <th>IP</th>
              <th>Install Date</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_asset_vms)) {
            $asset_name = nullable_htmlentities($row["asset_name"]);
            $asset_os = nullable_htmlentities($row["asset_os"]);
            $asset_ip = nullable_htmlentities($row["interface_ip"]);
            $asset_install_date = nullable_htmlentities($row["asset_install_date"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$asset_name</td>
              <td>$asset_os</td>
              <td>$asset_ip</td>
              <td>$asset_install_date</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Network Devices
    if (mysqli_num_rows($sql_asset_network) > 0 && $export_assets == 1) {
        $pdf->Bookmark("Network Devices", 1, 0, "", "", array(0,0,0));
        $html .= "
        <h3 class='subsection-title'>Network Devices</h3>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Model</th>
              <th>Serial</th>
              <th>IP</th>
              <th>Purchase Date</th>
              <th>Warranty Expire</th>
              <th>Install Date</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_asset_network)) {
            $asset_name = nullable_htmlentities($row["asset_name"]);
            $asset_type = nullable_htmlentities($row["asset_type"]);
            $asset_make = nullable_htmlentities($row["asset_make"]);
            $asset_model = nullable_htmlentities($row["asset_model"]);
            $asset_serial = nullable_htmlentities($row["asset_serial"]);
            $asset_ip = nullable_htmlentities($row["interface_ip"]);
            $asset_purchase_date = nullable_htmlentities($row["asset_purchase_date"]);
            $asset_warranty_expire = nullable_htmlentities($row["asset_warranty_expire"]);
            $asset_install_date = nullable_htmlentities($row["asset_install_date"]);
            $location_name = nullable_htmlentities($row["location_name"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$asset_name</td>
              <td>$asset_type</td>
              <td>$asset_make $asset_model</td>
              <td>$asset_serial</td>
              <td>$asset_ip</td>
              <td>$asset_purchase_date</td>
              <td>$asset_warranty_expire</td>
              <td>$asset_install_date</td>
              <td>$location_name</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Other Devices
    if (mysqli_num_rows($sql_asset_other) > 0 && $export_assets == 1) {
        $pdf->Bookmark("Other Devices", 1, 0, "", "", array(0,0,0));
        $html .= "
        <h3 class='subsection-title'>Other Devices</h3>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Model</th>
              <th>Serial</th>
              <th>IP</th>
              <th>Purchase Date</th>
              <th>Warranty Expire</th>
              <th>Install Date</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_asset_other)) {
            $asset_name = nullable_htmlentities($row["asset_name"]);
            $asset_type = nullable_htmlentities($row["asset_type"]);
            $asset_make = nullable_htmlentities($row["asset_make"]);
            $asset_model = nullable_htmlentities($row["asset_model"]);
            $asset_serial = nullable_htmlentities($row["asset_serial"]);
            $asset_ip = nullable_htmlentities($row["interface_ip"]);
            $asset_purchase_date = nullable_htmlentities($row["asset_purchase_date"]);
            $asset_warranty_expire = nullable_htmlentities($row["asset_warranty_expire"]);
            $asset_install_date = nullable_htmlentities($row["asset_install_date"]);
            $location_name = nullable_htmlentities($row["location_name"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$asset_name</td>
              <td>$asset_type</td>
              <td>$asset_make $asset_model</td>
              <td>$asset_serial</td>
              <td>$asset_ip</td>
              <td>$asset_purchase_date</td>
              <td>$asset_warranty_expire</td>
              <td>$asset_install_date</td>
              <td>$location_name</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Software Section
    if (mysqli_num_rows($sql_software) > 0 && $export_software == 1) {
        $pdf->Bookmark("Software", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Software</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>License</th>
              <th>License Key</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_software)) {
            $software_name = nullable_htmlentities($row["software_name"]);
            $software_type = nullable_htmlentities($row["software_type"]);
            $software_license_type = nullable_htmlentities($row["software_license_type"]);
            $software_key = nullable_htmlentities($row["software_key"]);
            $software_notes = nullable_htmlentities($row["software_notes"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$software_name</td>
              <td>$software_type</td>
              <td>$software_license_type</td>
              <td>$software_key</td>
              <td>$software_notes</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // User Assigned Software Licenses
    if (mysqli_num_rows($sql_user_licenses) > 0 && $export_software == 1) {
        $pdf->Bookmark("User Assigned Licenses", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>User Assigned Licenses</h2>
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Software</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_user_licenses)) {
            $contact_name = nullable_htmlentities($row["contact_name"]);
            $software_name = nullable_htmlentities($row["software_name"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$contact_name</td>
              <td>$software_name</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Asset Assigned Software Licenses
    if (mysqli_num_rows($sql_asset_licenses) > 0 && $export_software == 1) {
        $pdf->Bookmark("Asset Assigned Licenses", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Asset Assigned Licenses</h2>
        <table>
          <thead>
            <tr>
              <th>Asset</th>
              <th>Software</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_asset_licenses)) {
            $asset_name = nullable_htmlentities($row["asset_name"]);
            $software_name = nullable_htmlentities($row["software_name"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$asset_name</td>
              <td>$software_name</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Networks Section
    if (mysqli_num_rows($sql_networks) > 0 && $export_networks == 1) {
        $pdf->Bookmark("Networks", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Networks</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>vLAN</th>
              <th>Network Subnet</th>
              <th>Gateway</th>
              <th>DHCP Range</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_networks)) {
            $network_name = nullable_htmlentities($row["network_name"]);
            $network_vlan = nullable_htmlentities($row["network_vlan"]);
            $network = nullable_htmlentities($row["network"]);
            $network_gateway = nullable_htmlentities($row["network_gateway"]);
            $network_dhcp_range = nullable_htmlentities($row["network_dhcp_range"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$network_name</td>
              <td>$network_vlan</td>
              <td>$network</td>
              <td>$network_gateway</td>
              <td>$network_dhcp_range</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Domains Section
    if (mysqli_num_rows($sql_domains) > 0 && $export_domains == 1) {
        $pdf->Bookmark("Domains", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Domains</h2>
        <table>
          <thead>
            <tr>
              <th>Domain Name</th>
              <th>Expire</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_domains)) {
            $domain_name = nullable_htmlentities($row["domain_name"]);
            $domain_expire = nullable_htmlentities($row["domain_expire"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$domain_name</td>
              <td>$domain_expire</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Certificates Section
    if (mysqli_num_rows($sql_certficates) > 0 && $export_certificates == 1) {
        $pdf->Bookmark("Certificates", 0, 0, "", "B", array(0,0,0));
        $html .= "
        <h2 class='section-title'>Certificates</h2>
        <table>
          <thead>
            <tr>
              <th>Certificate Name</th>
              <th>Domain Name</th>
              <th>Issuer</th>
              <th>Expiration Date</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_array($sql_certficates)) {
            $certificate_name = nullable_htmlentities($row["certificate_name"]);
            $certificate_domain = nullable_htmlentities($row["certificate_domain"]);
            $certificate_issued_by = nullable_htmlentities($row["certificate_issued_by"]);
            $certificate_expire = nullable_htmlentities($row["certificate_expire"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$certificate_name</td>
              <td>$certificate_domain</td>
              <td>$certificate_issued_by</td>
              <td>$certificate_expire</td>
            </tr>";
        }
        $html .= "
          </tbody>
        </table>";
    }

    // Write the HTML content to the PDF document
    $pdf->writeHTML($html, true, false, true, false, "");

    // Output the PDF document for download
    $pdf->Output(strtoAZaz09($client_name) . "-IT_Documentation-" . date("Y-m-d") . ".pdf", "D");
    exit;
}