<?php

/*
 * ITFlow - GET/POST request handler for clients/customers (overview)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_client'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    require_once 'client_model.php';

    // Location inputs
    $location_phone_country_code = preg_replace("/[^0-9]/", '', $_POST['location_phone_country_code']);
    $location_phone = preg_replace("/[^0-9]/", '', $_POST['location_phone']);
    $location_extension = preg_replace("/[^0-9]/", '', $_POST['location_extension']);
    $location_fax_country_code = preg_replace("/[^0-9]/", '', $_POST['location_fax_country_code']);
    $location_fax = preg_replace("/[^0-9]/", '', $_POST['location_fax']);
    $address = escapeSql($_POST['address']);
    $city = escapeSql($_POST['city']);
    $state = escapeSql($_POST['state']);
    $zip = escapeSql($_POST['zip']);
    $country = escapeSql($_POST['country']);

    // Contact inputs
    $contact = escapeSql($_POST['contact']);
    $title = escapeSql($_POST['title']);
    $contact_phone_country_code = preg_replace("/[^0-9]/", '', $_POST['contact_phone_country_code']);
    $contact_phone = preg_replace("/[^0-9]/", '', $_POST['contact_phone']);
    $contact_extension = preg_replace("/[^0-9]/", '', $_POST['contact_extension']);
    $contact_mobile_country_code = preg_replace("/[^0-9]/", '', $_POST['contact_mobile_country_code']);
    $contact_mobile = preg_replace("/[^0-9]/", '', $_POST['contact_mobile']);
    $contact_email = escapeSql($_POST['contact_email']);

    $extended_log_description = '';

    // A referral typed in that isn't one yet is made one; one that is takes its own spelling
    $referral = escapeSql(categoryEnsure('Referral', $_POST['referral'] ?? ''));

    // Create client
    mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_rate = $rate, client_currency_code = '$session_company_currency', client_net_terms = $net_terms, client_reminders = " . ($reminders ?? 1) . ", client_tax_id_number = '$tax_id_number', client_lead = $lead, client_abbreviation = '$abbreviation', client_notes = '$notes', client_accessed_at = NOW()" . ($tax_input !== null ? ", " . taxClientSetSql($tax_input) : ''));

    $client_id = mysqli_insert_id($mysqli);

    // Create client folder
    $client_folder = $_SERVER['DOCUMENT_ROOT'] . "/uploads/clients/$client_id";
    if (!file_exists($client_folder)) {
        mkdir($client_folder);
        file_put_contents("$client_folder/index.php", "");
    }

    // Create primary location
    if (!empty($location_phone) || !empty($address) || !empty($city) || !empty($state) || !empty($zip)) {
        mysqli_query($mysqli, "INSERT INTO locations SET location_name = 'Primary', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone_country_code = '$location_phone_country_code', location_phone = '$location_phone', location_phone_extension = '$location_extension', location_fax_country_code = '$location_fax_country_code', location_fax = '$location_fax', location_country = '$country', location_primary = 1, location_client_id = $client_id");

        $extended_log_description .= ", primary location $address added";
    }

    // Create primary contact
    if (!empty($contact) || !empty($title) || !empty($contact_phone) || !empty($contact_mobile) || !empty($contact_email)) {
        mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$contact', contact_title = '$title', contact_phone_country_code = '$contact_phone_country_code', contact_phone = '$contact_phone', contact_extension = '$contact_extension', contact_mobile_country_code = '$contact_mobile_country_code', contact_mobile = '$contact_mobile', contact_email = '$contact_email', contact_primary = 1, contact_important = 1, contact_client_id = $client_id");

        $extended_log_description .= ", primary contact $contact added";
    }

    // Tags - client tags only, and an admin can type a new one in (functions/tags.php)
    tagSetForRecord('client', $client_id, (array) ($_POST['tags'] ?? []));

    // Create domain and SSL certificate - the website's domain, tidied, looked up
    // like any other; one whose lookup finds nothing is still added, without records
    $website_domain = domainNormalizeName($website);
    if ($website_domain !== '') {
        $website_domain_sql = escapeSql($website_domain);
        mysqli_query($mysqli, "INSERT INTO domains SET domain_name = '$website_domain_sql', domain_description = 'Website', domain_client_id = $client_id");
        $domain_id = mysqli_insert_id($mysqli);
        $extended_log_description .= ", domain $website_domain_sql added";

        if (domainApplyLookup($domain_id, domainLookup($website_domain)) && domainAddCertificate($domain_id, $website_domain, $client_id)) {
            $extended_log_description .= ", SSL certificate $website_domain_sql added";
        }
    }

    // Ticket SLA assignments (fields only rendered when active SLAs exist)
    if (isset($_POST['client_sla_low'])) {
        foreach (['Low', 'Medium', 'High', 'Urgent'] as $sla_priority) {
            $sla_value = strval($_POST['client_sla_' . strtolower($sla_priority)] ?? 'default');
            if ($sla_value !== 'default') {
                $client_sla_id = intval($sla_value);
                mysqli_query($mysqli, "INSERT INTO sla_assignments SET sla_assignment_client_id = $client_id, sla_assignment_priority = '$sla_priority', sla_assignment_sla_id = $client_sla_id");
            }
        }
    }

    logAudit("Client", "Create", "$session_name created client $name$extended_log_description", $client_id, $client_id);

    flashAlert("Client <strong>" . stripslashes($name) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_client'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    require_once 'client_model.php';

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    // Its tax as it was, for what the form leaves out
    $tax_set_sql = '';
    if ($tax_input !== null) {
        $tax_current = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_default_tax_id, client_tax_exempt, client_tax_exempt_number, client_tax_exempt_expires
            FROM clients WHERE client_id = $client_id LIMIT 1")) ?: [];
        $tax_set_sql = ", " . taxClientSetSql($tax_input, $tax_current);
    }

    // A referral typed in that isn't one yet is made one; one that is takes its own spelling
    $referral = escapeSql(categoryEnsure('Referral', $_POST['referral'] ?? ''));

    // Update client
    mysqli_query($mysqli, "UPDATE clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_rate = $rate, client_net_terms = $net_terms" . ($reminders !== null ? ", client_reminders = $reminders" : '') . ", client_tax_id_number = '$tax_id_number', client_lead = $lead, client_abbreviation = '$abbreviation', client_notes = '$notes'$tax_set_sql WHERE client_id = $client_id");

    // Tags - client tags only, and an admin can type a new one in (functions/tags.php)
    tagSetForRecord('client', $client_id, (array) ($_POST['tags'] ?? []));

    // Ticket SLA assignments (fields only rendered when active SLAs exist)
    if (isset($_POST['client_sla_low'])) {

        // Compare with current state so an unrelated client edit doesn't
        // restamp tickets (restamping clobbers manual per-ticket SLA pins)
        $current_sla_assignments = [];
        $sql_current_slas = mysqli_query($mysqli, "SELECT sla_assignment_priority, sla_assignment_sla_id FROM sla_assignments WHERE sla_assignment_client_id = $client_id");
        while ($current_sla_row = mysqli_fetch_assoc($sql_current_slas)) {
            $current_sla_assignments[$current_sla_row['sla_assignment_priority']] = strval(intval($current_sla_row['sla_assignment_sla_id']));
        }

        $sla_assignments_changed = false;
        foreach (['Low', 'Medium', 'High', 'Urgent'] as $sla_priority) {
            $sla_value = strval($_POST['client_sla_' . strtolower($sla_priority)] ?? 'default');
            $sla_current = $current_sla_assignments[$sla_priority] ?? 'default';
            if ($sla_value === $sla_current) {
                continue;
            }
            $sla_assignments_changed = true;
            mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id = $client_id AND sla_assignment_priority = '$sla_priority'");
            if ($sla_value !== 'default') {
                $client_sla_id = intval($sla_value);
                mysqli_query($mysqli, "INSERT INTO sla_assignments SET sla_assignment_client_id = $client_id, sla_assignment_priority = '$sla_priority', sla_assignment_sla_id = $client_sla_id");
            }
        }

        if ($sla_assignments_changed) {
            // Re-resolve this client's open tickets against the new assignments
            $sql_sla_tickets = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_client_id = $client_id AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL");
            while ($sla_ticket_row = mysqli_fetch_assoc($sql_sla_tickets)) {
                applyTicketSla($sla_ticket_row['ticket_id']);
            }
        }
    }

    logAudit("Client", "Edit", "$session_name edited client $name", $client_id, $client_id);

    flashAlert("Client <strong>" . stripslashes($name) . "</strong> updated");

    redirect();

}

if (isset($_GET['archive_client'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $client_id = intval($_GET['archive_client']);

    enforceClientAccess();

    // Archive client
    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");

    // Stop recurring invoices
    $sql_recurring_invoices = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1");
    while ($row = mysqli_fetch_assoc($sql_recurring_invoices)) {
        $recurring_invoice_id = intval($row['recurring_invoice_id']);
        mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_status = 0 WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id");
        mysqli_query($mysqli,"INSERT INTO history SET history_status = 0, history_description = 'Recurring Invoice inactive as client archived', history_recurring_invoice_id = $recurring_invoice_id");
    }

    // Get Client Name
    $client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));

    logAudit("Client", "Archive", "$session_name archived client $client_name", $client_id, $client_id);

    flashAlert("Client <strong>" . stripslashes($client_name) . "</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_client'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $client_id = intval($_GET['restore_client']);

    enforceClientAccess();

    // Get Client Name
    $client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));

    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NULL WHERE client_id = $client_id");

    logAudit("Client", "Restored", "$session_name restored client $client_name", $client_id);

    flashAlert("Client <strong>" . stripslashes($client_name) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_client'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 3);

    $client_id = intval($_GET['delete_client']);

    enforceClientAccess();

    // Get Client Name
    $client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));

    // Their payments and other income go with them - not once any has cleared on a reconciled statement
    $lock = reconcileClientLock($client_id);
    if ($lock) {
        flashAlert(escapeHtml('Money from ' . stripslashes($client_name) . " cleared on the {$lock['account_name']} statement to " . date('M j, Y', strtotime($lock['reconciliation_statement_date'])) . ' - keep the client archived instead, or undo that reconciliation to delete them'), 'error');
        redirect();
    }

    // Delete Associations
    // Delete Client Data
    mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM documents WHERE document_client_id = $client_id");

    // Delete Contacts
    mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_client_id = $client_id");

    // Delete Assets
    mysqli_query($mysqli, "DELETE FROM assets WHERE asset_client_id = $client_id");

    // Delete Racks (their items go with them)
    mysqli_query($mysqli, "DELETE FROM racks WHERE rack_client_id = $client_id");

    // Delete Projects - their tickets are the client's, and go below
    mysqli_query($mysqli, "DELETE FROM projects WHERE project_client_id = $client_id");

    // Delete Domains and associated records
    mysqli_query($mysqli, "DELETE FROM domains WHERE domain_client_id = $client_id");

    mysqli_query($mysqli, "DELETE calendar_event_attendees FROM calendar_event_attendees JOIN calendar_events ON event_id = attendee_event_id WHERE event_client_id = $client_id");
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

    // Don't leave the company's own-client designation pointing at a client that is gone
    mysqli_query($mysqli, "UPDATE settings SET config_internal_client_id = 0 WHERE config_internal_client_id = $client_id");

    logAudit("Client", "Deleted", "$session_name deleted Client $client_name and all associated data");

    flashAlert("Client <strong>" . stripslashes($client_name) . "</strong> deleted, with everything that was theirs", 'error');

    redirect('clients.php');

}

if (isExportRequest('export_clients')) {

    validateCSRFToken();

    enforceUserPermission('module_client');

    $format = resolveExportFormat($_POST['export_clients']);

    // Filters inherited from the clients page - mirrors agent/clients.php
    $filter_summary = [];

    // Leads Filter
    if (isset($_POST['leads']) && $_POST['leads'] == 1) {
        $leads_query = "AND client_lead = 1";
        $filter_summary['Showing'] = 'Leads';
        $export_label = 'Leads';
    } else {
        $leads_query = "AND client_lead = 0";
        $export_label = 'Clients';
    }

    // Tags Filter
    if (isset($_POST['tags']) && is_array($_POST['tags']) && !empty($_POST['tags'])) {
        $tag_filter = implode(",", array_map('intval', $_POST['tags']));
        $tag_query = "AND tags.tag_id IN ($tag_filter)";

        $tag_names = [];
        $sql_tags = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id IN ($tag_filter) ORDER BY tag_name ASC");
        while ($tag_row = mysqli_fetch_assoc($sql_tags)) {
            $tag_names[] = $tag_row['tag_name'];
        }
        $filter_summary['Tags'] = implode(', ', $tag_names);
    } else {
        // Default - any
        $tag_query = '';
    }

    // Industry Filter
    if (!empty($_POST['industry'])) {
        $industry_query = "AND (clients.client_type = '" . escapeSql($_POST['industry']) . "')";
        $filter_summary['Industry'] = $_POST['industry'];
    } else {
        // Default - any
        $industry_query = '';
    }

    // Referral Filter
    if (!empty($_POST['referral'])) {
        $referral_query = "AND (clients.client_referral = '" . escapeSql($_POST['referral']) . "')";
        $filter_summary['Referral'] = $_POST['referral'];
    } else {
        // Default - any
        $referral_query = '';
    }

    // Archived Filter
    if (isset($_POST['archived']) && $_POST['archived'] == 1) {
        $archive_query = "client_archived_at IS NOT NULL";
        $filter_summary['Archived'] = 'Archived only';
    } else {
        $archive_query = "client_archived_at IS NULL";
    }

    // Date Filter - all-time sentinels from filter_header.php are left in place,
    // they match everything anyway
    $dtf = escapeSql(!empty($_POST['dtf']) ? $_POST['dtf'] : '1970-01-01');
    $dtt = escapeSql(!empty($_POST['dtt']) ? $_POST['dtt'] : '2099-12-31');
    $date_range = formatExportDateRange($dtf, $dtt);
    if ($date_range) {
        $filter_summary['Created'] = $date_range;
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    $phone_query = preg_replace('/\D/', '', $q);
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    // Get records from database - same shape as the clients page list query
    $sql = mysqli_query(
        $mysqli,
        "SELECT clients.*, contacts.*, locations.*
        FROM clients
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        LEFT JOIN client_tags ON client_tags.client_id = clients.client_id
        LEFT JOIN tags ON tags.tag_id = client_tags.tag_id
        WHERE (client_name LIKE '%$q%' OR client_abbreviation LIKE '%$q%' OR client_type LIKE '%$q%' OR client_referral LIKE '%$q%'
               OR contact_email LIKE '%$q%' OR contact_name LIKE '%$q%' OR contact_phone LIKE '%$phone_query%'
               OR contact_mobile LIKE '%$phone_query%' OR location_address LIKE '%$q%'
               OR location_city LIKE '%$q%' OR location_state LIKE '%$q%' OR location_zip LIKE '%$q%' OR location_country LIKE '%$q%'
               OR tag_name LIKE '%$q%' OR client_tax_id_number LIKE '%$q%')
          AND $archive_query
          AND DATE(client_created_at) BETWEEN '$dtf' AND '$dtt'
          $leads_query
          " . clientScopeSql('clients.client_id') . "
          $tag_query
          $industry_query
          $referral_query
        GROUP BY client_id
        ORDER BY client_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('clients', $format, "$session_company_name-$export_label", $export_label, summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $tax_client = taxClient($row['client_id']);
            $row['client_tax_rate'] = intval($tax_client['default_id']) ? taxLabel(taxRow($tax_client['default_id'])) : '';
            $row['client_tax_exempt_display'] = $tax_client['exempt'] ? 'Yes' : '';
            $row['client_tax_exempt_number'] = $tax_client['exempt'] ? $tax_client['number'] : '';
            $row['client_tax_exempt_expires'] = $tax_client['exempt'] ? $tax_client['expires'] : '';
            addExportRow($export, $row);
        }

        finishExport($export);

        logAudit("Client", "Export", "$session_name exported $num_rows client(s) to a " . strtoupper($format) . " file");

    }

    exit;

}

if (isset($_POST["import_clients_csv"])) {

    validateCSRFToken();

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
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT 1 FROM clients WHERE client_name = '$name'")) > 0) {
                    $duplicate_detect = 1;
                }
            }

            $industry = '';
            if (isset($column[1])) {
                $industry = escapeSql($column[1]);
            }

            $referral = '';
            if (isset($column[2])) {
                $referral = (string) $column[2];
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
                // A referral that isn't one yet is made one; one that is takes its own spelling
                $referral = escapeSql(categoryEnsure('Referral', $referral));

                // Create client
                mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name', client_type = '$industry', client_website = '$website', client_referral = '$referral', client_rate = $hourly_rate, client_currency_code = '$currency_code', client_net_terms = $payment_terms, client_tax_id_number = '$tax_id_number', client_abbreviation = '$abbreviation'");

                $client_id = mysqli_insert_id($mysqli);

                if (!file_exists("../uploads/clients/$client_id")) {
                    mkdir("../uploads/clients/$client_id");
                    file_put_contents("../uploads/clients/$client_id/index.php", "");
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

    validateCSRFToken();

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
        $sql = mysqli_query($mysqli, "SELECT ticket_template_details, ticket_template_subject FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");
        $row = mysqli_fetch_assoc($sql);

        // Override Template Subject
        if(empty($subject)) {
            $subject = escapeSql($row['ticket_template_subject']);
        }
        $details = mysqli_escape_string($mysqli, $row['ticket_template_details']);

        // Get Associated Tasks from the ticket template
        $sql_task_templates = mysqli_query($mysqli, "SELECT task_template_name, task_template_order FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

    }

    // Create ticket for each selected asset
    if (isset($_POST['client_ids'])) {

        // Check every selected client before creating any tickets
        foreach ($_POST['client_ids'] as $client_id) {
            enforceClientAccess(intval($client_id));
        }

        // Get a Asset Count
        $client_count = count($_POST['client_ids']);

        foreach ($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id");
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

            // Only a project of this client's, or an internal one
            $ticket_project_id = ticketClientOwnedId('project', $project_id, $client_id);

            mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_category = $category_id, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_status = $ticket_status, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_url_key = '$url_key', ticket_client_id = $client_id, ticket_project_id = $ticket_project_id");

            $ticket_id = mysqli_insert_id($mysqli);
            applyTicketSla($ticket_id);

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

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $industry = escapeSql($_POST['bulk_industry']);

    if (isset($_POST['client_ids'])) {

        // Check every selected client before changing any of them
        foreach ($_POST['client_ids'] as $client_id) {
            enforceClientAccess(intval($client_id));
        }

        $count = count($_POST['client_ids']);

        foreach($_POST['client_ids'] as $client_id) {
            $client_id = intval($client_id);

            $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
            $row = mysqli_fetch_assoc($sql);
            $client_name = escapeSql($row['client_name']);

            mysqli_query($mysqli,"UPDATE clients SET client_type = '$industry' WHERE client_id = $client_id");

            logAudit("Client", "Edit", "$session_name set Industry to $industry for $client_name", $client_id);

        }

        logAudit("Client", "Bulk Edit", "$session_name set the industry $industry for $count client(s)", $client_id);

        flashAlert("Industry set to <strong>" . stripslashes($industry) . "</strong> on <strong>$count</strong> client" . ($count === 1 ? '' : 's'));
    }

    redirect();

}

if (isset($_POST['bulk_edit_client_referral'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $referral = escapeSql($_POST['bulk_referral']);

    if (isset($_POST['client_ids'])) {

        // Check every selected client before changing any of them
        foreach ($_POST['client_ids'] as $client_id) {
            enforceClientAccess(intval($client_id));
        }

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

        flashAlert("Referral set to <strong>" . stripslashes($referral) . "</strong> on <strong>$count</strong> client" . ($count === 1 ? '' : 's'));
    }

    redirect();

}

if (isset($_POST['bulk_edit_client_hourly_rate'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $rate = floatval($_POST['bulk_rate']);

    if (isset($_POST['client_ids'])) {

        // Check every selected client before changing any of them
        foreach ($_POST['client_ids'] as $client_id) {
            enforceClientAccess(intval($client_id));
        }

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

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $net_terms = intval($_POST['net_terms']);

    if (isset($_POST['client_ids'])) {

        // Check every selected client before changing any of them
        foreach ($_POST['client_ids'] as $client_id) {
            enforceClientAccess(intval($client_id));
        }

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

if (isset($_POST['bulk_send_client_email']) && isset($_POST['client_ids'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 1);

    $client_ids = array_map('intval', $_POST['client_ids']);
    $count = count($client_ids);

    // Check every selected client before queuing anything
    foreach ($client_ids as $client_id) {
        enforceClientAccess($client_id);
    }

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

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    if (isset($_POST['client_ids'])) {

        // Check every selected client before changing any of them
        foreach ($_POST['client_ids'] as $client_id) {
            enforceClientAccess(intval($client_id));
        }

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

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    if (isset($_POST['client_ids'])) {

        // Check every selected client before changing any of them
        foreach ($_POST['client_ids'] as $client_id) {
            enforceClientAccess(intval($client_id));
        }

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

/*
 * Export a client (functions/client_export.php): the documentation PDF, or
 * everything as a zip. Client access is the floor; each section then needs
 * its own module, so a role missing one goes without that section, and
 * passwords only go out locked with a password the agent sets.
 */
if (isset($_POST['export_client_pdf']) || isset($_POST['export_client_archive'])) {

    validateCSRFToken();

    enforceUserPermission('module_client');

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id"));
    if (!$row) {
        flashAlert('That client no longer exists.', 'error');
        redirect('clients.php');
    }
    $client_name = $row['client_name'];

    $kind = isset($_POST['export_client_archive']) ? 'archive' : 'pdf';
    $options = clientExportOptions($_POST, $kind);
    if ($error = clientExportCheck($options, $_POST)) {
        flashAlert($error, 'error');
        redirect();
    }

    // A big client's files take a while to gather; the request is the export
    set_time_limit(0);

    $logged = escapeSql(clientExportSummary($options)) . ($options['secrets'] ? ', including passwords and 2FA secrets' : '');

    if ($kind === 'pdf') {
        try {
            $pdf = clientExportPdf($client_id, $options);
        } catch (Exception $exception) {
            error_log('ITFlow client export PDF: ' . $exception->getMessage());
            flashAlert('The PDF could not be made. Try again with fewer sections, or use the full export.', 'error');
            redirect();
        }

        logAudit("Client", "Export", "$session_name exported the documentation PDF for " . escapeSql($client_name) . " ($logged)", $client_id, $client_id);

        clientExportSendHeaders(clientExportFileName($client_name, 'IT Documentation', 'pdf'), 'application/pdf', strlen($pdf));
        echo $pdf;
        exit;
    }

    $zip_path = clientExportArchive($client_id, $options, $error);
    if (!$zip_path) {
        flashAlert($error, 'error');
        redirect();
    }
    logAudit("Client", "Export", "$session_name exported all data for " . escapeSql($client_name) . " ($logged" . ($options['archived'] ? ', archived records' : '')
        . ($options['internal'] ? ', internal notes' : '') . ")", $client_id, $client_id);

    clientExportSendHeaders(clientExportFileName($client_name, 'Export', 'zip'), 'application/zip', filesize($zip_path));
    while (ob_get_level()) {
        ob_end_clean();
    }
    readfile($zip_path);
    @unlink($zip_path);
    exit;

}
