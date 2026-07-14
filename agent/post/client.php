<?php

/*
 * ITFlow - GET/POST request handler for clients/customers (overview)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_client'])) {

    // JQ - Using Prepared MySQLi Statements here for show this is not our standard and is only used in the client add/edit POST.

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    require_once 'client_model.php';

    // Location inputs
    $location_phone_country_code = preg_replace("/[^0-9]/", '', $_POST['location_phone_country_code']);
    $location_phone = preg_replace("/[^0-9]/", '', $_POST['location_phone']);
    $location_extension = preg_replace("/[^0-9]/", '', $_POST['location_extension']);
    $location_fax_country_code = preg_replace("/[^0-9]/", '', $_POST['location_fax_country_code']);
    $location_fax = preg_replace("/[^0-9]/", '', $_POST['location_fax']);
    $address = cleanInput($_POST['address']);
    $city = cleanInput($_POST['city']);
    $state = cleanInput($_POST['state']);
    $zip = cleanInput($_POST['zip']);
    $country = cleanInput($_POST['country']);

    // Contact inputs
    $contact = cleanInput($_POST['contact']);
    $title = cleanInput($_POST['title']);
    $contact_phone_country_code = preg_replace("/[^0-9]/", '', $_POST['contact_phone_country_code']);
    $contact_phone = preg_replace("/[^0-9]/", '', $_POST['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '', $_POST['contact_extension']);
    $contact_mobile_country_code = preg_replace("/[^0-9]/", '', $_POST['contact_mobile_country_code']);
    $contact_mobile = preg_replace("/[^0-9]/", '', $_POST['contact_mobile']);
    $contact_email = cleanInput($_POST['contact_email']);

    $extended_log_description = '';

    // Insert client using SET
    $query = mysqli_prepare(
        $mysqli,
        "INSERT INTO clients SET
        client_name = ?,
        client_type = ?,
        client_website = ?,
        client_referral = ?,
        client_rate = ?,
        client_currency_code = ?,
        client_net_terms = ?,
        client_tax_id_number = ?,
        client_lead = ?,
        client_abbreviation = ?,
        client_notes = ?,
        client_accessed_at = NOW()"
    );
    mysqli_stmt_bind_param(
        $query,
        "ssssdsiisss",
        $name,
        $type,
        $website,
        $referral,
        $rate,
        $session_company_currency,
        $net_terms,
        $tax_id_number,
        $lead,
        $abbreviation,
        $notes
    );
    mysqli_stmt_execute($query);
    $client_id = mysqli_insert_id($mysqli);

    // Create client folder
    $client_folder = $_SERVER['DOCUMENT_ROOT'] . "/uploads/clients/$client_id";
    if (!file_exists($client_folder)) {
        mkdir($client_folder);
        file_put_contents("$client_folder/index.php", "");
    }

    // Create referral category if it doesn't exist
    $query = mysqli_prepare($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND category_name = ?");
    mysqli_stmt_bind_param($query, "s", $referral);
    mysqli_stmt_execute($query);
    mysqli_stmt_store_result($query);
    if (mysqli_stmt_num_rows($query) == 0) {
        $query = mysqli_prepare($mysqli, "INSERT INTO categories SET category_name = ?, category_type = 'Referral'");
        mysqli_stmt_bind_param($query, "s", $referral);
        mysqli_stmt_execute($query);

        logAudit("Category", "Create", "$session_name created referral category $referral");
    }

    // Insert primary location using SET
    if (!empty($location_phone) || !empty($address) || !empty($city) || !empty($state) || !empty($zip)) {
        $query = mysqli_prepare(
            $mysqli,
            "INSERT INTO locations SET
            location_name = 'Primary',
            location_address = ?,
            location_city = ?,
            location_state = ?,
            location_zip = ?,
            location_phone_country_code = ?,
            location_phone = ?,
            location_phone_extension = ?,
            location_fax_country_code = ?,
            location_fax = ?,
            location_country = ?,
            location_primary = 1,
            location_client_id = ?"
        );
        mysqli_stmt_bind_param(
            $query,
            "ssssssssssi",
            $address,
            $city,
            $state,
            $zip,
            $location_phone_country_code,
            $location_phone,
            $location_extension,
            $location_fax_country_code,
            $location_fax,
            $country,
            $client_id
        );
        mysqli_stmt_execute($query);
        $extended_log_description .= ", primary location $address added";
    }

    // Insert primary contact using SET
    if (!empty($contact) || !empty($title) || !empty($contact_phone) || !empty($contact_mobile) || !empty($contact_email)) {
        $query = mysqli_prepare(
            $mysqli,
            "INSERT INTO contacts SET
            contact_name = ?,
            contact_title = ?,
            contact_phone_country_code = ?,
            contact_phone = ?,
            contact_extension = ?,
            contact_mobile_country_code = ?,
            contact_mobile = ?,
            contact_email = ?,
            contact_primary = 1,
            contact_important = 1,
            contact_client_id = ?"
        );
        mysqli_stmt_bind_param(
            $query,
            "ssssssssi",
            $contact,
            $title,
            $contact_phone_country_code,
            $contact_phone,
            $contact_extension,
            $contact_mobile_country_code,
            $contact_mobile,
            $contact_email,
            $client_id
        );
        mysqli_stmt_execute($query);
        $extended_log_description .= ", primary contact $contact added";
    }

    // Add tags
    if (isset($_POST['tags'])) {
        $query = mysqli_prepare($mysqli, "INSERT INTO client_tags SET client_id = ?, tag_id = ?");
        foreach ($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_stmt_bind_param($query, "ii", $client_id, $tag);
            mysqli_stmt_execute($query);
        }
    }

    // Insert domain and SSL using SET
    if (!empty($website) && filter_var($website, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $expire = getDomainExpirationDate($website);
        $records = getDnsRecords($website);
        $a = cleanInput($records['a']);
        $ns = cleanInput($records['ns']);
        $mx = cleanInput($records['mx']);
        $whois = cleanInput($records['whois']);

        try {
            $query = mysqli_prepare(
                $mysqli,
                "INSERT INTO domains SET
                domain_name = ?,
                domain_registrar = 0,
                domain_webhost = 0,
                domain_expire = ?,
                domain_ip = ?,
                domain_name_servers = ?,
                domain_mail_servers = ?,
                domain_raw_whois = ?,
                domain_client_id = ?"
            );
            mysqli_stmt_bind_param($query, "ssssssi", $website, $expire, $a, $ns, $mx, $whois, $client_id);
            mysqli_stmt_execute($query);
            $extended_log_description .= ", domain $website added";
        } catch (Exception $e) {
            $extended_log_description .= ", domain not added";
            logApp("Client", "warning", "Failed to add domain $website during client creation");
        }

        $domain_id = mysqli_insert_id($mysqli);
        $certificate = getSslCertificate($website);

        if ($certificate['success'] == "TRUE") {
            $expire = cleanInput($certificate['expire']);
            $issued_by = cleanInput($certificate['issued_by']);
            $public_key = cleanInput($certificate['public_key']);

            $query = mysqli_prepare(
                $mysqli,
                "INSERT INTO certificates SET
                certificate_name = ?,
                certificate_domain = ?,
                certificate_issued_by = ?,
                certificate_expire = ?,
                certificate_public_key = ?,
                certificate_domain_id = ?,
                certificate_client_id = ?"
            );
            mysqli_stmt_bind_param(
                $query,
                "sssssii",
                $website,
                $website,
                $issued_by,
                $expire,
                $public_key,
                $domain_id,
                $client_id
            );
            mysqli_stmt_execute($query);

            $extended_log_description .= ", SSL certificate $website added";
        }
    }

    logAudit("Client", "Create", "$session_name created client $name$extended_log_description", $client_id, $client_id);

    flashAlert("Client <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_client'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    require_once 'client_model.php';

    $client_id = intval($_POST['client_id']);

    // Update client using prepared statement
    $query = mysqli_prepare(
        $mysqli,
        "UPDATE clients SET
        client_name = ?,
        client_type = ?,
        client_website = ?,
        client_referral = ?,
        client_rate = ?,
        client_net_terms = ?,
        client_tax_id_number = ?,
        client_lead = ?,
        client_abbreviation = ?,
        client_notes = ?
        WHERE client_id = ?"
    );
    mysqli_stmt_bind_param(
        $query,
        "ssssdisissi",
        $name,
        $type,
        $website,
        $referral,
        $rate,
        $net_terms,
        $tax_id_number,
        $lead,
        $abbreviation,
        $notes,
        $client_id
    );
    mysqli_stmt_execute($query);

    // Create referral category if it doesn't exist
    $query = mysqli_prepare($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND category_name = ?");
    mysqli_stmt_bind_param($query, "s", $referral);
    mysqli_stmt_execute($query);
    mysqli_stmt_store_result($query);
    if (mysqli_stmt_num_rows($query) == 0) {
        $query = mysqli_prepare($mysqli, "INSERT INTO categories SET category_name = ?, category_type = 'Referral'");
        mysqli_stmt_bind_param($query, "s", $referral);
        mysqli_stmt_execute($query);

        logAudit("Category", "Create", "$session_name created referral category $referral");
    }

    // Tags - delete existing and re-insert
    $query = mysqli_prepare($mysqli, "DELETE FROM client_tags WHERE client_id = ?");
    mysqli_stmt_bind_param($query, "i", $client_id);
    mysqli_stmt_execute($query);

    if (isset($_POST['tags'])) {
        $query = mysqli_prepare($mysqli, "INSERT INTO client_tags SET client_id = ?, tag_id = ?");
        foreach ($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_stmt_bind_param($query, "ii", $client_id, $tag);
            mysqli_stmt_execute($query);
        }
    }

    logAudit("Client", "Edit", "$session_name edited client $name", $client_id, $client_id);

    flashAlert("Client <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['archive_client'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_client', 2);

    $client_id = intval($_GET['archive_client']);

    // Archive client
    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");

    // Stop recurring invoices
    $sql_recurring_invoices = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1");
    while ($row = mysqli_fetch_assoc($sql_recurring_invoices)) {
        $recurring_invoice_id = intval($row['recurring_invoice_id']);
        mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_status = 0 WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id");
        mysqli_query($mysqli,"INSERT INTO history SET history_status = 0, history_description = 'Recurring Invoice inactive as client archived', history_recurring_invoice_id = $recurring_invoice_id");
    }

    // Get Client Name
    $client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));

    logAudit("Client", "Archive", "$session_name archived client $client_name", $client_id, $client_id);

    flashAlert("Client <strong>$client_name</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_client'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_client', 2);

    $client_id = intval($_GET['restore_client']);

    // Get Client Name
    $client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));

    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NULL WHERE client_id = $client_id");

    logAudit("Client", "Restored", "$session_name restored client $client_name", $client_id);

    flashAlert("Client <strong>$client_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_client'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_client', 3);

    $client_id = intval($_GET['delete_client']);

    // Get Client Name
    $client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));

    // Delete Associations
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
    while($row = mysqli_fetch_assoc($sql)) {
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
    while($row = mysqli_fetch_assoc($sql)) {
        $quote_id = $row['quote_id'];

        mysqli_query($mysqli, "DELETE FROM quote_items WHERE item_quote_id = $quote_id");
    }
    mysqli_query($mysqli, "DELETE FROM quotes WHERE quote_client_id = $client_id");

    // Delete Recurring Invoices and associated items
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");
    while($row = mysqli_fetch_assoc($sql)) {
        $recurring_invoice_id = $row['recurring_invoice_id'];
        mysqli_query($mysqli, "DELETE FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
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
    while($row = mysqli_fetch_assoc($sql)) {
        $ticket_id = $row['ticket_id'];
        mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");
        mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");
    }
    mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM trips WHERE trip_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_client_id = $client_id");

    //Delete Client Files
    removeDirectory("../uploads/clients/$client_id");

    //Finally Remove the Client
    mysqli_query($mysqli, "DELETE FROM clients WHERE client_id = $client_id");

    logAudit("Client", "Deleted", "$session_name deleted Client $client_name and all associated data");

    flashAlert("Client <strong>$client_name</strong> deleted along with all associated data", 'error');

    redirect('clients.php');

}

if (isset($_POST['export_clients_csv'])) {

    validateCSRFToken($_POST['csrf_token']);

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
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitizeFilename($session_company_name . "-Clients-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Client Name', 'Industry', 'Referral', 'Website', 'Primary Location Name', 'Location Phone', 'Location Address', 'City', 'State', 'Postal Code', 'Country', 'Primary Contact Name', 'Title', 'Contact Phone', 'Extension', 'Contact Mobile', 'Contact Email', 'Hourly Rate', 'Currency', 'Payment Terms', 'Tax ID', 'Abbreviation');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['client_name'], $row['client_type'], $row['client_referral'], $row['client_website'], $row['location_name'], formatPhoneNumber($row['location_phone']), $row['location_address'], $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_country'], $row['contact_name'], $row['contact_title'], formatPhoneNumber($row['contact_phone']), $row['contact_extension'], formatPhoneNumber($row['contact_mobile']), $row['contact_email'], $row['client_rate'], $row['client_currency_code'], $row['client_net_terms'], $row['client_tax_id_number'], $row['client_abbreviation']);
            fputcsv($f, $lineData, $delimiter, $enclosure, $escape);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);

        logAudit("Client", "Export", "$session_name exported $num_rows client(s) to a CSV file");

    }

    exit;

}

if (isset($_POST["import_clients_csv"])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);
    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if (in_array($file_extension,$allowed_file_extensions) === false) {
        $error = true;
        flashAlert("Bad file extension", 'error');
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1) {
        $error = true;
        flashAlert("Bad file size (empty?)", 'error');
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 22) {
        $error = true;
        flashAlert("Bad column count.", 'error');
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
                $name = escapeSql($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM clients WHERE client_name = '$name'")) > 0) {
                    $duplicate_detect = 1;
                }
            }

            $industry = '';
            if (isset($column[1])) {
                $industry = escapeSql($column[1]);
            }

            $referral = '';
            if (isset($column[2])) {
                $referral = escapeSql($column[2]);
            }

            $website = '';
            if (isset($column[3])) {
                $website = escapeSql(preg_replace("(^https?://)", "", $column[3]));
            }

            $location_name = '';
            if (isset($column[4])) {
                $location_name = escapeSql($column[4]);
            }

            $location_phone = '';
            if (isset($column[5])) {
                $location_phone = preg_replace("/[^0-9]/", '', $column[5]);
            }

            $address = '';
            if (isset($column[6])) {
                $address = escapeSql($column[6]);
            }

            $city = '';
            if (isset($column[7])) {
                $city = escapeSql($column[7]);
            }

            $state = '';
            if (isset($column[8])) {
                $state = escapeSql($column[8]);
            }

            $zip = '';
            if (isset($column[9])) {
                $zip = escapeSql($column[9]);
            }

            $country = '';
            if (isset($column[10])) {
                $country = escapeSql($column[10]);
            }

            $contact_name = '';
            if (isset($column[11])) {
                $contact_name = escapeSql($column[11]);
            }

            $title = '';
            if (isset($column[12])) {
                $title = escapeSql($column[12]);
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
                $contact_email = escapeSql($column[16]);
            }

            $hourly_rate = $config_default_hourly_rate;
            if (isset($column[17])) {
                $hourly_rate = floatval($column[17]);
            }

            $currency_code = escapeSql($session_company_currency);
            if (isset($column[18])) {
                $currency_code = escapeSql($column[18]);
            }

            $payment_terms = escapeSql($config_default_net_terms);
            if (isset($column[19])) {
                $payment_terms = intval($column[19]);
            }

            $tax_id_number = '';
            if (isset($column[20])) {
                $tax_id_number = escapeSql($column[20]);
            }

            $abbreviation = '';
            if (isset($column[21])) {
                $abbreviation = escapeSql($column[21]);
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0) {
                //Add
                // Create client
                mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name', client_type = '$industry', client_website = '$website', client_referral = '$referral', client_rate = $hourly_rate, client_currency_code = '$currency_code', client_net_terms = $payment_terms, client_tax_id_number = '$tax_id_number', client_abbreviation = '$abbreviation'");

                $client_id = mysqli_insert_id($mysqli);

                if (!file_exists("../uploads/clients/$client_id")) {
                    mkdir("../uploads/clients/$client_id");
                    file_put_contents("../uploads/clients/$client_id/index.php", "");
                }

                // Create Referral if it doesn't exist
                $sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND category_name = '$referral'");
                if(mysqli_num_rows($sql) == 0) {
                    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '$referral', category_type = 'Referral'");
                    // Logging
                    logAudit("Category", "Create", "$session_name created new refferal category $referral");
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

        logAudit("Client", "Import", "$session_name imported $row_count client(s) via CSV file, $duplicate_count duplicate(s) found");

        flashAlert("<strong>$row_count</strong> Client(s) added, <strong>$duplicate_count</strong> duplicate(s) found");

        redirect();

    }

    //Check for any errors, if there are notify user and redirect
    if ($error) {
        redirect();
    }
}

if (isset($_GET['download_clients_csv_template'])) {

    $delimiter = ",";
    $enclosure = '"';
    $escape    = '\\';   // backsla
    $filename = "Clients-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Client Name', 'Industry', 'Referral', 'Website', 'Primary Location Name', 'Location Phone', 'Location Address', 'City', 'State', 'Postal Code', 'Country', 'Primary Contact Name', 'Title', 'Contact Phone', 'Extension', 'Contact Mobile', 'Contact Email', 'Hourly Rate', 'Currency', 'Payment Terms', 'Tax ID', 'Abbreviation');
    fputcsv($f, $fields, $delimiter, $enclosure, $escape);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);

    exit;

}

if (isset($_POST['bulk_add_client_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $assigned_to = intval($_POST['bulk_assigned_to']);
    if ($assigned_to == 0) {
        $ticket_status = 1;
    } else {
        $ticket_status = 2;
    }
    $subject = escapeSql($_POST['bulk_subject']);
    $priority = escapeSql($_POST['bulk_priority']);
    $category_id = intval($_POST['bulk_category']);
    $details = mysqli_real_escape_string($mysqli, $_POST['bulk_details']);
    $project_id = intval($_POST['bulk_project']);
    $use_primary_contact = intval($_POST['use_primary_contact']);
    $ticket_template_id = intval($_POST['bulk_ticket_template_id']);
    $billable = intval($_POST['bulk_billable'] ?? 0);

    // Check to see if adding a ticket by template
    if($ticket_template_id) {
        $sql = mysqli_query($mysqli, "SELECT * FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");
        $row = mysqli_fetch_assoc($sql);

        // Override Template Subject
        if(empty($subject)) {
            $subject = escapeSql($row['ticket_template_subject']);
        }
        $details = mysqli_escape_string($mysqli, $row['ticket_template_details']);

        // Get Associated Tasks from the ticket template
        $sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

    }

    // Create ticket for each selected asset
    if (isset($_POST['client_ids'])) {

        // Get a Asset Count
        $client_count = count($_POST['client_ids']);

        foreach ($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);

            $client_name = escapeSql($row['client_name']);

            // Atomically increment and get the new ticket number
            mysqli_query($mysqli, "
                UPDATE settings
                SET
                    config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
                    config_ticket_next_number = config_ticket_next_number + 1
                WHERE company_id = 1
            ");

            $ticket_number = mysqli_insert_id($mysqli);

            // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
            $config_ticket_prefix = escapeSql($config_ticket_prefix);
            $config_ticket_from_name = escapeSql($config_ticket_from_name);
            $config_ticket_from_email = escapeSql($config_ticket_from_email);
            $config_base_url = escapeSql($config_base_url);

            //Generate a unique URL key for clients to access
            $url_key = randomString(32);

            mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_category = $category_id, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_status = $ticket_status, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_url_key = '$url_key', ticket_client_id = $client_id, ticket_project_id = $project_id");

            $ticket_id = mysqli_insert_id($mysqli);

            // Add Tasks
            if (!empty($_POST['tasks'])) {
                foreach ($_POST['tasks'] as $task) {
                    $task_name = escapeSql($task);
                    // Check that task_name is not-empty (For some reason the !empty on the array doesnt work here like in watchers)
                    if (!empty($task_name)) {
                        mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_ticket_id = $ticket_id");
                    }
                }
            }

            // Add Tasks from Template if Template was selected
            if($ticket_template_id) {
                if (mysqli_num_rows($sql_task_templates) > 0) {
                    while ($row = mysqli_fetch_assoc($sql_task_templates)) {
                        $task_order = intval($row['task_template_order']);
                        $task_name = escapeSql($row['task_template_name']);

                        mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_order = $task_order, task_ticket_id = $ticket_id");
                    }
                }
            }

            // Custom action/notif handler
            triggerCustomAction('ticket_create', $ticket_id);
        }

        logAudit("Ticket", "Bulk Create", "$session_name created $client_count tickets for $client_name");

        flashAlert("<strong>$client_count</strong> tickets created for selected clients");

    }

    redirect();

}

if (isset($_POST['bulk_edit_client_industry'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    $industry = escapeSql($_POST['bulk_industry']);

    if (isset($_POST['client_ids'])) {

        $count = count($_POST['client_ids']);

        foreach($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            mysqli_query($mysqli,"UPDATE clients SET client_type = '$industry' WHERE client_id = $client_id");

            logAudit("Client", "Edit", "$session_name set Industry to $industry for $client_name", $client_id);

        }

        logAudit("Client", "Bulk Edit", "$session_name set the department $industry for $count client(s)", $client_id);

        flashAlert("Set the Industry to <strong>$industry</strong> for <strong>$count</strong> clients");
    }

    redirect();

}

if (isset($_POST['bulk_edit_client_referral'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    $referral = escapeSql($_POST['bulk_referral']);

    if (isset($_POST['client_ids'])) {

        $count = count($_POST['client_ids']);

        foreach($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            mysqli_query($mysqli,"UPDATE clients SET client_referral = '$referral' WHERE client_id = $client_id");

            logAudit("Client", "Edit", "$session_name set Referral to $referral for $client_name", $client_id);

        }

        logAudit("Client", "Bulk Edit", "$session_name set the referral $referral for $count client(s)", $client_id);

        flashAlert("Set the Referral to <strong>$referral</strong> for <strong>$count</strong> clients");
    }

    redirect();

}

if (isset($_POST['bulk_edit_client_hourly_rate'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    $rate = floatval($_POST['bulk_rate']);

    if (isset($_POST['client_ids'])) {

        $count = count($_POST['client_ids']);

        foreach($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            mysqli_query($mysqli,"UPDATE clients SET client_rate = '$rate' WHERE client_id = $client_id");

            logAudit("Client", "Edit", "$session_name set Hourly Rate to" . numfmt_format_currency($currency_format, $rate, $session_company_currency) . "for $client_name", $client_id);

        }

        logAudit("Client", "Bulk Edit", "$session_name set the hourly rate" . numfmt_format_currency($currency_format, $rate, $session_company_currency) . "for $count client(s)", $client_id);

        flashAlert("Set the Hourly Rate to <strong>" . numfmt_format_currency($currency_format, $rate, $session_company_currency) . "</strong> for <strong>$count</strong> client(s)");
    }

    redirect();

}

if (isset($_POST['bulk_edit_client_net_terms'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    $net_terms = intval($_POST['net_terms']);

    if (isset($_POST['client_ids'])) {

        $count = count($_POST['client_ids']);

        foreach($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            mysqli_query($mysqli,"UPDATE clients SET client_net_terms = $net_terms WHERE client_id = $client_id");

            logAudit("Client", "Edit", "$session_name set net terms to $net_terms days for $client_name", $client_id);

        }

        logAudit("Client", "Bulk Edit", "$session_name set the net terms to $net_terms days for $count client(s)", $client_id);

        flashAlert("Set Net Term to <strong>$net_terms days</strong> for <strong>$count</strong> client(s)");
    }

    redirect();

}

if (isset($_POST['bulk_assign_client_tags'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    if (isset($_POST['client_ids'])) {

        $count = count($_POST['client_ids']);

        foreach($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            if ($_POST['bulk_remove_tags']) {
                mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_id = $client_id");
            }

            if (isset($_POST['bulk_tags'])) {
                foreach($_POST['bulk_tags'] as $tag) {
                    $tag = intval($tag);

                    $sql = mysqli_query($mysqli,"SELECT * FROM client_tags WHERE client_id = $client_id AND tag_id = $tag");
                    if (mysqli_num_rows($sql) == 0) {
                        mysqli_query($mysqli, "INSERT INTO client_tags SET client_id = $client_id, tag_id = $tag");
                    }
                }
            }

            logAudit("Client", "Edit", "$session_name added tags to $client_name", $client_id, $client_id);

        }

        logAudit("Client", "Bulk Edit", "$session_name added tags for $count clients", $client_id);

        flashAlert("Assigned tags for <strong>$count</strong> clients");
    }

    redirect();

}

if (isset($_POST['bulk_send_client_email']) && isset($_POST['client_ids'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 1);

    $client_ids = array_map('intval', $_POST['client_ids']);
    $count = count($client_ids);

    // Email metadata
    $mail_from = escapeSql($_POST['mail_from']);
    $mail_from_name = escapeSql($_POST['mail_from_name']);
    $subject = escapeSql($_POST['subject']);
    $body = mysqli_real_escape_string($mysqli, $_POST['body']);
    $queued_at = escapeSql($_POST['queued_at']);

    // Build contact type filters
    $filters = [];

    if (!empty($_POST['primary_contacts'])) {
        $filters[] = "contact_primary = 1";
    }
    if (!empty($_POST['important_contacts'])) {
        $filters[] = "contact_important = 1";
    }
    if (!empty($_POST['billing_contacts'])) {
        $filters[] = "contact_billing = 1";
    }
    if (!empty($_POST['technical_contacts'])) {
        $filters[] = "contact_technical = 1";
    }

    $contact_filter_query = '';
    if (!empty($filters)) {
        $contact_filter_query = ' AND (' . implode(' OR ', $filters) . ')';
    }

    // Prepare client ID list for SQL
    $client_ids_str = implode(',', $client_ids);

    // SQL to fetch matching contacts
    $sql = "SELECT * FROM contacts
            WHERE contact_client_id IN ($client_ids_str)
            $contact_filter_query";

    $result = mysqli_query($mysqli, $sql);

    $data = [];
    $unique_contacts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $contact_email = escapeSql($row['contact_email']);

        // Skip if email is missing or invalid
        if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        // Skip duplicates (same email)
        if (isset($unique_contacts[$contact_email])) {
            continue;
        }
        $unique_contacts[$contact_email] = true;

        $contact_name = escapeSql($row['contact_name']);

        $data[] = [
            'from' => $mail_from,
            'from_name' => $mail_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject,
            'body' => $body,
            'queued_at' => $queued_at
        ];
    }

    if (!empty($data)) {
        addToMailQueue($data);
        logAudit("Bulk Mail", "Send", "$session_name sent " . count($data) . " messages via bulk mail");
        flashAlert("<strong>" . count($data) . "</strong> messages queued");
    } else {
        flashAlert("No valid contacts found to queue emails.", 'error');
    }

    redirect();

}

if (isset($_POST['bulk_archive_clients'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    if (isset($_POST['client_ids'])) {

        $count = 0;

        foreach ($_POST['client_ids'] as $client_id) {

            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            mysqli_query($mysqli,"UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");

            logAudit("Client", "Archive", "$session_name archived $client_name", $client_id);

            $count++;

        }

        logAudit("Client", "Bulk Archive", "$session_name archived $count clients", $client_id);

        flashAlert("Archived $count client(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_unarchive_clients'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_client', 2);

    if (isset($_POST['client_ids'])) {

        $count = count($_POST['client_ids']);

        foreach ($_POST['client_ids'] as $client_id) {

            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            mysqli_query($mysqli,"UPDATE clients SET client_archived_at = NULL WHERE client_id = $client_id");

            logAudit("client", "Restore", "$session_name restored $client_name", $client_id);

        }

        logAudit("Client", "Bulk Restore", "$session_name restored $count client(s)", $client_id);

        flashAlert("You restored <strong>$count</strong> client(s)");

    }

    redirect();

}

if (isset($_POST["export_client_pdf"])) {

    validateCSRFToken($_POST['csrf_token']);

    // Enforce permissions
    enforceUserPermission("module_client", 3);
    enforceUserPermission("module_support", 1);
    enforceUserPermission("module_sales", 1);
    enforceUserPermission("module_financial", 1);

    $sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
    $row = mysqli_fetch_assoc($sql);
    $company_name = escapeHtml($row['company_name']);
    $company_phone_country_code = escapeHtml($row['company_phone_country_code']);
    $company_phone = escapeHtml(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
    $company_email = escapeHtml($row['company_email']);
    $company_website = escapeHtml($row['company_website']);
    $company_logo = escapeHtml($row['company_logo']);

    $client_id = intval($_POST["client_id"]);
    $export_contacts = intval($_POST["export_contacts"]);
    $export_locations = intval($_POST["export_locations"]);
    $export_assets = intval($_POST["export_assets"]);
    $export_software = intval($_POST["export_software"]);
    $export_credentials = 0;
    if (lookupUserPermission("module_credential") >= 1) {
        $export_credentials = intval($_POST["export_credentials"] ?? 0);
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

    logAudit("Client", "Export", "$session_name exported client data to a PDF file", $client_id, $client_id);

    // Get client record (joining primary contact and primary location)
    $sql = mysqli_query($mysqli, "SELECT * FROM clients
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE client_id = $client_id
    ");
    $row = mysqli_fetch_assoc($sql);

    // Immediately sanitize retrieved values
    $client_name = escapeHtml($row["client_name"]);
    $location_address = escapeHtml($row["location_address"]);
    $location_city = escapeHtml($row["location_city"]);
    $location_state = escapeHtml($row["location_state"]);
    $location_zip = escapeHtml($row["location_zip"]);
    $contact_name = escapeHtml($row["contact_name"]);
    $contact_phone_country_code = escapeHtml($row["contact_phone_country_code"]);
    $contact_phone = escapeHtml(formatPhoneNumber($row["contact_phone"], $contact_phone_country_code));
    $contact_extension = escapeHtml($row["contact_extension"]);
    $contact_mobile_country_code = escapeHtml($row["contact_mobile_country_code"]);
    $contact_mobile = escapeHtml(formatPhoneNumber($row["contact_mobile"], $contact_mobile_country_code));
    $contact_email = escapeHtml($row["contact_email"]);
    $client_website = escapeHtml($row["client_website"]);

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

    require_once("../libs/TCPDF/tcpdf.php");

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, "UTF-8", false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($session_company_name);
    $pdf->SetTitle("$client_name - IT Documentation");

    // TODO: Add page numbers to footer, but can't work out how to do it without the ugly line
    //    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    //    $pdf->setFooterData();

    // Enable auto page breaks with a margin from the bottom
    $pdf->SetAutoPageBreak(true, 15);

    // ----- Start Main Content -----
    $pdf->AddPage();
    $pdf->SetFont("freeserif", "", 10);

    // Build the HTML content with enhanced styling and semantic markup
    $html = "
    <style>
      body { font-family: sans-serif; margin: 0; padding: 0; }
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
    $html .= '<div class="cover">';
    if (!empty($company_logo)) {
        //$pdf->Image('../uploads/settings/' . $company_logo, '', '', 35, 35, '', '', 'L', false, 300, '', false, false, 1, false, false, false);
        $html .= '<div style="text-align:right;">
        <img src="' . realpath('../uploads/settings/' . $company_logo) . '" width="120">
        </div>';
    }

    $html .= "
     <h1>IT Documentation</h1>
     <h2>$client_name</h2>
    ";

    $html .= "
    <h4>Prepared by $session_name on " . date("F j, Y") . "</h4>
    </div>
    ";

    $html .= "
      <br>
      <h4>$session_company_name</h4>
      $company_phone<br>$company_email<br>
    ";

    if (!$config_whitelabel_enabled) {
        $html .= '<div style="text-align:right;">
        <small class="text-muted">Powered by ITFlow</small>
        </div>';
    }

    $html .= '<hr>';

    // Client header information (non-table)
    $html .= "
    <div class='client-header'>
      <h3>$client_name</h3>
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
        while ($row = mysqli_fetch_assoc($sql_contacts)) {
            $contact_name = escapeHtml(getFallBack($row["contact_name"]));
            $contact_title = escapeHtml(getFallBack($row["contact_title"]));
            $contact_department = escapeHtml($row["contact_department"]);
            $contact_email = escapeHtml($row["contact_email"]);
            $contact_phone_country_code = escapeHtml($row["contact_phone_country_code"]);
            $contact_phone = escapeHtml(formatPhoneNumber($row["contact_phone"], $contact_phone_country_code));
            $contact_extension = escapeHtml($row["contact_extension"]);
            if (!empty($contact_extension)) {
                $contact_extension = "x$contact_extension";
            }
            $contact_mobile_country_code = escapeHtml($row["contact_mobile_country_code"]);
            $contact_mobile = escapeHtml(formatPhoneNumber($row["contact_mobile"], $contact_mobile_country_code));
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
        while ($row = mysqli_fetch_assoc($sql_locations)) {
            $location_name = escapeHtml($row["location_name"]);
            $location_address = escapeHtml($row["location_address"]);
            $location_city = escapeHtml($row["location_city"]);
            $location_state = escapeHtml($row["location_state"]);
            $location_zip = escapeHtml($row["location_zip"]);
            $location_phone_country_code = escapeHtml($row["location_phone_country_code"]);
            $location_phone = escapeHtml(formatPhoneNumber($row["location_phone"], $location_phone_country_code));
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
        while ($row = mysqli_fetch_assoc($sql_vendors)) {
            $vendor_name = escapeHtml($row["vendor_name"]);
            $vendor_description = escapeHtml($row["vendor_description"]);
            $vendor_account_number = escapeHtml($row["vendor_account_number"]);
            $vendor_phone_country_code = escapeHtml($row["vendor_phone_country_code"]);
            $vendor_phone = escapeHtml(formatPhoneNumber($row["vendor_phone"], $vendor_phone_country_code));
            $vendor_website = escapeHtml($row["vendor_website"]);
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
              <th>TOTP</th>
              <th>URI</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_assoc($sql_credentials)) {
            $credential_name = escapeHtml($row["credential_name"]);
            $credential_description = getFallback(escapeHtml($row["credential_description"]));
            $credential_username = escapeHtml(decryptCredentialEntry($row["credential_username"]));
            $credential_password = escapeHtml(decryptCredentialEntry($row["credential_password"]));
            $credential_totp_secret = getFallback(escapeHtml($row['credential_otp_secret']));
            $credential_uri = getFallback(escapeHtml($row["credential_uri"]));
            $html .= "
            <tr>
              <td>$credential_name</td>
              <td>$credential_description</td>
              <td>$credential_username</td>
              <td>$credential_password</td>
              <td>$credential_totp_secret</td>
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
        while ($row = mysqli_fetch_assoc($sql_asset_workstations)) {
            $asset_name = escapeHtml($row["asset_name"]);
            $asset_type = escapeHtml($row["asset_type"]);
            $asset_make = escapeHtml($row["asset_make"]);
            $asset_model = escapeHtml($row["asset_model"]);
            $asset_serial = escapeHtml($row["asset_serial"]);
            $asset_os = escapeHtml($row["asset_os"]);
            $asset_purchase_date = escapeHtml($row["asset_purchase_date"]);
            $asset_warranty_expire = escapeHtml($row["asset_warranty_expire"]);
            $asset_install_date = escapeHtml($row["asset_install_date"]);
            $contact_name = escapeHtml($row["contact_name"]);
            $location_name = escapeHtml($row["location_name"]);
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
        while ($row = mysqli_fetch_assoc($sql_asset_servers)) {
            $asset_name = escapeHtml($row["asset_name"]);
            $asset_make = escapeHtml($row["asset_make"]);
            $asset_model = escapeHtml($row["asset_model"]);
            $asset_serial = escapeHtml($row["asset_serial"]);
            $asset_os = escapeHtml($row["asset_os"]);
            $asset_ip = escapeHtml($row["interface_ip"]);
            $asset_purchase_date = escapeHtml($row["asset_purchase_date"]);
            $asset_warranty_expire = escapeHtml($row["asset_warranty_expire"]);
            $asset_install_date = escapeHtml($row["asset_install_date"]);
            $location_name = escapeHtml($row["location_name"]);
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
        while ($row = mysqli_fetch_assoc($sql_asset_vms)) {
            $asset_name = escapeHtml($row["asset_name"]);
            $asset_os = escapeHtml($row["asset_os"]);
            $asset_ip = escapeHtml($row["interface_ip"]);
            $asset_install_date = escapeHtml($row["asset_install_date"]);
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
        while ($row = mysqli_fetch_assoc($sql_asset_network)) {
            $asset_name = escapeHtml($row["asset_name"]);
            $asset_type = escapeHtml($row["asset_type"]);
            $asset_make = escapeHtml($row["asset_make"]);
            $asset_model = escapeHtml($row["asset_model"]);
            $asset_serial = escapeHtml($row["asset_serial"]);
            $asset_ip = escapeHtml($row["interface_ip"]);
            $asset_purchase_date = escapeHtml($row["asset_purchase_date"]);
            $asset_warranty_expire = escapeHtml($row["asset_warranty_expire"]);
            $asset_install_date = escapeHtml($row["asset_install_date"]);
            $location_name = escapeHtml($row["location_name"]);
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
        while ($row = mysqli_fetch_assoc($sql_asset_other)) {
            $asset_name = escapeHtml($row["asset_name"]);
            $asset_type = escapeHtml($row["asset_type"]);
            $asset_make = escapeHtml($row["asset_make"]);
            $asset_model = escapeHtml($row["asset_model"]);
            $asset_serial = escapeHtml($row["asset_serial"]);
            $asset_ip = escapeHtml($row["interface_ip"]);
            $asset_purchase_date = escapeHtml($row["asset_purchase_date"]);
            $asset_warranty_expire = escapeHtml($row["asset_warranty_expire"]);
            $asset_install_date = escapeHtml($row["asset_install_date"]);
            $location_name = escapeHtml($row["location_name"]);
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
              <th>Purchase Date</th>
              <th>Expiration Date</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_assoc($sql_software)) {
            $software_name = escapeHtml($row["software_name"]);
            $software_type = escapeHtml($row["software_type"]);
            $software_license_type = escapeHtml($row["software_license_type"]);
            $software_key = escapeHtml($row["software_key"]);
            $software_purchase = escapeHtml($row['software_purchase']);
            $software_expire = escapeHtml($row['software_expire']);
            $software_notes = escapeHtml($row["software_notes"]);
            $html .= "
            <tr style='page-break-inside: avoid;'>
              <td>$software_name</td>
              <td>$software_type</td>
              <td>$software_license_type</td>
              <td>$software_key</td>
              <td>$software_purchase</td>
              <td>$software_expire</td>
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
        while ($row = mysqli_fetch_assoc($sql_user_licenses)) {
            $contact_name = escapeHtml($row["contact_name"]);
            $software_name = escapeHtml($row["software_name"]);
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
        while ($row = mysqli_fetch_assoc($sql_asset_licenses)) {
            $asset_name = escapeHtml($row["asset_name"]);
            $software_name = escapeHtml($row["software_name"]);
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
        while ($row = mysqli_fetch_assoc($sql_networks)) {
            $network_name = escapeHtml($row["network_name"]);
            $network_vlan = escapeHtml($row["network_vlan"]);
            $network = escapeHtml($row["network"]);
            $network_gateway = escapeHtml($row["network_gateway"]);
            $network_dhcp_range = escapeHtml($row["network_dhcp_range"]);
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
              <th>Expiration Date</th>
            </tr>
          </thead>
          <tbody>";
        while ($row = mysqli_fetch_assoc($sql_domains)) {
            $domain_name = escapeHtml($row["domain_name"]);
            $domain_expire = escapeHtml($row["domain_expire"]);
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
        while ($row = mysqli_fetch_assoc($sql_certficates)) {
            $certificate_name = escapeHtml($row["certificate_name"]);
            $certificate_domain = escapeHtml($row["certificate_domain"]);
            $certificate_issued_by = escapeHtml($row["certificate_issued_by"]);
            $certificate_expire = escapeHtml($row["certificate_expire"]);
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
    $pdf->Output(toAlphanumeric($client_name) . "-IT_Documentation-" . date("Y-m-d") . ".pdf", "D");
    exit;

}
