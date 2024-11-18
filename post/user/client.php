<?php

/*
 * ITFlow - GET/POST request handler for clients/customers (overview)
 */

if (isset($_POST['add_client'])) {

    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_client', 2);

    require_once 'post/user/client_model.php';

    $location_phone = preg_replace("/[^0-9]/", '', $_POST['location_phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $country = sanitizeInput($_POST['country']);
    $contact = sanitizeInput($_POST['contact']);
    $title = sanitizeInput($_POST['title']);
    $contact_phone = preg_replace("/[^0-9]/", '', $_POST['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '', $_POST['contact_extension']);
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
        mysqli_query($mysqli, "INSERT INTO locations SET location_name = 'Primary', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$location_phone', location_country = '$country', location_primary = 1, location_client_id = $client_id");

        //Extended Logging
        $extended_log_description .= ", primary location $address added";
    }


    // Create Contact
    if (!empty($contact) || !empty($title) || !empty($contact_phone) || !empty($contact_mobile) || !empty($contact_email)) {
        mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$contact', contact_title = '$title', contact_phone = '$contact_phone', contact_extension = '$contact_extension', contact_mobile = '$contact_mobile', contact_email = '$contact_email', contact_primary = 1, contact_important = 1, contact_client_id = $client_id");

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

    // Delete Contacts and contact tags
    $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $contact_id = $row['contact_id'];
        mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
    }
    mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_client_id = $client_id");

    // Delete Assets and Interfaces
    $sql = mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $asset_id = $row['asset_id'];
        mysqli_query($mysqli, "DELETE FROM asset_interfaces WHERE interface_asset_id = $asset_id");
    }
    mysqli_query($mysqli, "DELETE FROM assets WHERE asset_client_id = $client_id");

    // Delete Domains and associated records
    $sql = mysqli_query($mysqli, "SELECT domain_id FROM domains WHERE domain_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $domain_id = $row['domain_id'];
        mysqli_query($mysqli, "DELETE FROM records WHERE record_domain_id = $domain_id");
    }
    mysqli_query($mysqli, "DELETE FROM domains WHERE domain_client_id = $client_id");

    mysqli_query($mysqli, "DELETE FROM events WHERE event_client_id = $client_id");
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
    $sql = mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_client_id = location_id");
    while($row = mysqli_fetch_array($sql)) {
        $location_id = $row['location_id'];
        mysqli_query($mysqli, "DELETE FROM location_tags WHERE location_id = $location_id");
    }
    mysqli_query($mysqli, "DELETE FROM locations WHERE location_client_id = $client_id");

    mysqli_query($mysqli, "DELETE FROM logins WHERE login_client_id = $client_id");
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
    $sql = mysqli_query($mysqli, "SELECT recurring_id FROM recurring WHERE recurring_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $recurring_id = $row['recurring_id'];
        mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_recurring_id = $recurring_id");
    }
    mysqli_query($mysqli, "DELETE FROM recurring WHERE recurring_client_id = $client_id");

    mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_client_id = $client_id");

    // Delete Services and items associated with services
    $sql = mysqli_query($mysqli, "SELECT service_id FROM services WHERE service_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $service_id = $row['service_id'];
        mysqli_query($mysqli, "DELETE FROM service_assets WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_certificates WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_contacts WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_documents WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_domains WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_logins WHERE service_id = $service_id");
        mysqli_query($mysqli, "DELETE FROM service_vendors WHERE service_id = $service_id");
    }
    mysqli_query($mysqli, "DELETE FROM services WHERE service_client_id = $client_id");

    mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_client_id = $client_id");

    $sql = mysqli_query($mysqli, "SELECT software_id FROM software WHERE software_client_id = $client_id");
    while($row = mysqli_fetch_array($sql)) {
        $software_id = $row['software_id'];
        mysqli_query($mysqli, "DELETE FROM software_assets WHERE software_id = $software_id");
        mysqli_query($mysqli, "DELETE FROM software_contacts WHERE software_id = $software_id");
    }
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

    // Delete tags
    mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_id = $client_id");

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

if (isset($_POST['export_client_pdf'])) {

    // TODO: Enforce perms based on which individual boxes are ticked
    enforceUserPermission('module_client', 3);
    enforceUserPermission('module_support', 1);
    enforceUserPermission('module_sales', 1);
    enforceUserPermission('module_financial', 1);

    $client_id = intval($_POST['client_id']);
    $export_contacts = intval($_POST['export_contacts']);
    $export_locations = intval($_POST['export_locations']);
    $export_assets = intval($_POST['export_assets']);
    $export_software = intval($_POST['export_software']);
    $export_logins = 0;
    if (lookupUserPermission("module_credential") >= 1) {
        $export_logins = intval($_POST['export_logins']);
    }
    $export_networks = intval($_POST['export_networks']);
    $export_certificates = intval($_POST['export_certificates']);
    $export_domains = intval($_POST['export_domains']);
    $export_tickets = intval($_POST['export_tickets']);
    $export_scheduled_tickets = intval($_POST['export_scheduled_tickets']);
    $export_vendors = intval($_POST['export_vendors']);
    $export_invoices = intval($_POST['export_invoices']);
    $export_recurring = intval($_POST['export_recurring']);
    $export_quotes = intval($_POST['export_quotes']);
    $export_payments = intval($_POST['export_payments']);
    $export_trips = intval($_POST['export_trips']);
    $export_logs = intval($_POST['export_logs']);

    //Logging
    logAction("Client", "Export", "$session_name exported client data to a PDF file", $client_id, $client_id);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients 
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE client_id = $client_id
    ");

    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];
    $location_address = $row['location_address'];
    $location_city = $row['location_city'];
    $location_state = $row['location_state'];
    $location_zip = $row['location_zip'];
    $contact_name = $row['contact_name'];
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_email = $row['contact_email'];
    $client_website = $row['client_website'];

    $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
    $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
    $sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_client_id = $client_id ORDER BY login_name ASC");
    $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets 
        LEFT JOIN contacts ON asset_contact_id = contact_id 
        LEFT JOIN locations ON asset_location_id = location_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_client_id = $client_id
        AND asset_archived_at IS NULL
        ORDER BY asset_type ASC"
    );
    $sql_asset_workstations = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 WHERE asset_client_id = $client_id AND (asset_type = 'desktop' OR asset_type = 'laptop') AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $sql_asset_servers = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 WHERE asset_client_id = $client_id AND asset_type = 'server' AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $sql_asset_vms = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 WHERE asset_client_id = $client_id AND asset_type = 'virtual machine' AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $sql_asset_network = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 WHERE asset_client_id = $client_id AND (asset_type = 'Firewall/Router' OR asset_type = 'Switch' OR asset_type = 'Access Point') AND asset_archived_at IS NULL ORDER BY asset_type ASC");
    $sql_asset_other = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 WHERE asset_client_id = $client_id AND (asset_type NOT LIKE 'laptop' AND asset_type NOT LIKE 'desktop' AND asset_type NOT LIKE 'server' AND asset_type NOT LIKE 'virtual machine' AND asset_type NOT LIKE 'firewall/router' AND asset_type NOT LIKE 'switch' AND asset_type NOT LIKE 'access point') AND asset_archived_at IS NULL ORDER BY asset_type ASC");
    $sql_networks = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL ORDER BY network_name ASC");
    $sql_domains = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL ORDER BY domain_name ASC");
    $sql_certficates = mysqli_query($mysqli,"SELECT * FROM certificates WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL ORDER BY certificate_name ASC");
    $sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL ORDER BY software_name ASC");

    ?>

    <script src='plugins/pdfmake/pdfmake.min.js'></script>
    <script src='plugins/pdfmake/vfs_fonts.js'></script>
    <script>

        var docDefinition = {
            info: {
                title: '<?php echo strtoAZaz09($client_name); ?>-IT Documentation',
                author: <?php echo json_encode($session_company_name); ?>
            },

            pageMargins: [ 15, 15, 15, 15 ],

            content: [
                {
                    text: <?php echo json_encode($client_name); ?>,
                    style: 'title'
                },

                {
                    layout: 'lightHorizontalLines',
                    table: {
                        body: [
                            [
                                {
                                    text: 'Address',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($location_address); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'City State Zip',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode("$location_city $location_state $location_zip"); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_phone); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Website',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($client_website); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Contact',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                }
                            ],
                            [
                                {
                                    text: 'Email',
                                    style: 'itemHeader'
                                },
                                {
                                    text: <?php echo json_encode($contact_email); ?>,
                                    style: 'item'
                                }
                            ]
                        ]
                    }
                },

                //Contacts Start
                <?php if(mysqli_num_rows($sql_contacts) > 0 && $export_contacts == 1){ ?>
                {
                    text: 'Contacts',
                    style: 'title'
                },

                {
                    table: {
                        // headers are automatically repeated if the table spans over multiple pages
                        // you can declare how many rows should be treated as headers
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Title',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Department',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Email',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Mobile',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_contacts)){
                            $contact_name = $row['contact_name'];
                            $contact_title = $row['contact_title'];
                            $contact_phone = formatPhoneNumber($row['contact_phone']);
                            $contact_extension = $row['contact_extension'];
                            if(!empty($contact_extension)){
                                $contact_extension = "x$contact_extension";
                            }
                            $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                            $contact_email = $row['contact_email'];
                            $contact_department = $row['contact_department'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_title); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_department); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_email); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$contact_phone $contact_extension"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_mobile); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Contact END

                //Locations Start
                <?php if(mysqli_num_rows($sql_locations) > 0 && $export_locations == 1){ ?>
                {
                    text: 'Locations',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Address',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_locations)){
                            $location_name = $row['location_name'];
                            $location_address = $row['location_address'];
                            $location_city = $row['location_city'];
                            $location_state = $row['location_state'];
                            $location_zip = $row['location_zip'];
                            $location_phone = formatPhoneNumber($row['location_phone']);
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$location_address $location_city $location_state $location_zip"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_phone); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Locations END

                //Vendors Start
                <?php if(mysqli_num_rows($sql_vendors) > 0 && $export_vendors == 1){ ?>
                {
                    text: 'Vendors',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Description',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Phone',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Website',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Account Number',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_vendors)){
                            $vendor_name = $row['vendor_name'];
                            $vendor_description = $row['vendor_description'];
                            $vendor_account_number = $row['vendor_account_number'];
                            $vendor_contact_name = $row['vendor_contact_name'];
                            $vendor_phone = formatPhoneNumber($row['vendor_phone']);
                            $vendor_email = $row['vendor_email'];
                            $vendor_website = $row['vendor_website'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($vendor_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_description); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_phone); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_website); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($vendor_account_number); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Vendors END

                //Logins Start
                <?php if(mysqli_num_rows($sql_logins) > 0 && $export_logins == 1){ ?>
                {
                    text: 'Credentials',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Description',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Username',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Password',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'URI',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_logins)){
                            $login_name = $row['login_name'];
                            $login_description = $row['login_description'];
                            $login_username = decryptLoginEntry($row['login_username']);
                            $login_password = decryptLoginEntry($row['login_password']);
                            $login_uri = $row['login_uri'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($login_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_description); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_username); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_password); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($login_uri); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php

                }

                ?>
                //Logins END

                //Assets Start
                <?php if(mysqli_num_rows($sql_assets) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Assets',
                    style: 'assetTitle'
                },
                <?php } ?>
                //Assets END

                //Asset Workstations Start
                <?php if(mysqli_num_rows($sql_asset_workstations) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Workstations',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Assigned To',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_workstations)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['interface_ip'];
                            $asset_mac = $row['interface_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $contact_name = $row['contact_name'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($contact_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Workstation END

                //Assets Servers Start
                <?php if(mysqli_num_rows($sql_asset_servers) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Servers',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_servers)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['interface_ip'];
                            $asset_mac = $row['interface_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Servers END

                //Asset VMs Start
                <?php if(mysqli_num_rows($sql_asset_vms) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Virtual Machines',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'OS',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_vms)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['interface_ip'];
                            $asset_mac = $row['interface_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_os); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset VMs END

                //Assets Network Devices Start
                <?php if(mysqli_num_rows($sql_asset_network) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Network Devices',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_network)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['interface_ip'];
                            $asset_mac = $row['interface_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Network Devices END

                //Asset Other Start
                <?php if(mysqli_num_rows($sql_asset_other) > 0 && $export_assets == 1){ ?>
                {
                    text: 'Other Devices',
                    style: 'assetSubTitle'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Model',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Serial',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'IP',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Purchase Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Warranty Expire',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Install Date',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Location',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_asset_other)){
                            $asset_type = $row['asset_type'];
                            $asset_name = $row['asset_name'];
                            $asset_make = $row['asset_make'];
                            $asset_model = $row['asset_model'];
                            $asset_serial = $row['asset_serial'];
                            $asset_os = $row['asset_os'];
                            $asset_ip = $row['interface_ip'];
                            $asset_mac = $row['interface_mac'];
                            $asset_purchase_date = $row['asset_purchase_date'];
                            $asset_warranty_expire = $row['asset_warranty_expire'];
                            $asset_install_date = $row['asset_install_date'];
                            $asset_notes = $row['asset_notes'];
                            $location_name = $row['location_name'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($asset_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode("$asset_make $asset_model"); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_serial); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_ip); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_purchase_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_warranty_expire); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($asset_install_date); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($location_name); ?>,
                                    style: 'item'
                                }

                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Asset Other END

                //Software Start
                <?php if(mysqli_num_rows($sql_software) > 0 && $export_software == 1){ ?>
                {
                    text: 'Software',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Type',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'License',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'License Key',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Notes',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_software)){
                            $software_name = $row['software_name'];
                            $software_type = $row['software_type'];
                            $software_key = $row['software_key'];
                            $software_license_type = $row['software_license_type'];
                            $software_notes = $row['software_notes'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($software_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_license_type); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($software_key); ?>,
                                    style: 'item'
                                },

                                {
                                    text: <?php echo json_encode($software_notes); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Software END

                //Networks Start
                <?php if(mysqli_num_rows($sql_networks) > 0 && $export_networks == 1){ ?>
                {
                    text: 'Networks',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'vLAN',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Network Subnet',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Gateway',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'DHCP Range',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_networks)){
                            $network_name = $row['network_name'];
                            $network_vlan = $row['network_vlan'];
                            $network = $row['network'];
                            $network_gateway = $row['network_gateway'];
                            $network_dhcp_range = $row['network_dhcp_range'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($network_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_vlan); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_gateway); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($network_dhcp_range); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Networks END

                //Domains Start
                <?php if(mysqli_num_rows($sql_domains) > 0 && $export_domains == 1){ ?>
                {
                    text: 'Domains',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Domain Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Expire',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_domains)){
                            $domain_name = $row['domain_name'];
                            $domain_expire = $row['domain_expire'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($domain_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($domain_expire); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Domains END

                //Certificates Start
                <?php if(mysqli_num_rows($sql_certficates) > 0 && $export_certificates == 1){ ?>
                {
                    text: 'Certificates',
                    style: 'title'
                },

                {
                    table: {
                        body: [
                            [
                                {
                                    text: 'Certificate Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Domain Name',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Issuer',
                                    style: 'itemHeader'
                                },
                                {
                                    text: 'Expiration Date',
                                    style: 'itemHeader'
                                }
                            ],

                            <?php
                            while($row = mysqli_fetch_array($sql_certficates)){
                            $certificate_name = $row['certificate_name'];
                            $certificate_domain = $row['certificate_domain'];
                            $certificate_issued_by = $row['certificate_issued_by'];
                            $certificate_expire = $row['certificate_expire'];
                            ?>

                            [
                                {
                                    text: <?php echo json_encode($certificate_name); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_domain); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_issued_by); ?>,
                                    style: 'item'
                                },
                                {
                                    text: <?php echo json_encode($certificate_expire); ?>,
                                    style: 'item'
                                }
                            ],

                            <?php
                            }
                            ?>
                        ]
                    }
                },
                <?php } ?>
                //Certificates END



            ], //End Content,
            styles: {
                //Title
                title: {
                    fontSize: 15,
                    margin: [0,20,0,5],
                    bold: true
                },
                assetTitle: {
                    fontSize: 15,
                    margin: [0,20,0,0],
                    bold: true
                },
                //Asset Subtitle
                assetSubTitle: {
                    fontSize: 10,
                    margin: [0,10,0,5],
                    bold: true
                },
                //Item Header
                itemHeader: {
                    fontSize: 9,
                    margin: [0,1,0,1],
                    bold: true
                },
                //item
                item: {
                    fontSize: 9,
                    margin: [0,1,0,1]
                }
            }
        };


        //pdfMake.createPdf(docDefinition).download('<?php echo strtoAZaz09($client_name); ?>-IT_Documentation-<?php echo date('Y-m-d'); ?>');
        pdfMake.createPdf(docDefinition).download('<?php echo strtoAZaz09($client_name); ?>-IT_Documentation-<?php echo date('Y-m-d'); ?>');

    </script>


    <?php

}
