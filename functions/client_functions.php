<?php

//API V2 compatible client related functions

function createClient(
    $parameters
) {

    $name = $parameters['name'];
    $type = $parameters['type'];
    $website = $parameters['website'];
    $referral = $parameters['referral'];
    $rate = $parameters['rate'];
    $currency_code = $parameters['currency_code'];
    $net_terms = $parameters['net_terms'];
    $tax_id_number = $parameters['tax_id_number'];
    $lead = $parameters['lead'];
    $notes = $parameters['notes'];

    $location_phone = $parameters['location_phone']??'';
    $address = $parameters['address']??'';
    $city = $parameters['city']??'';
    $state = $parameters['state']??'';
    $zip = $parameters['zip']??'';
    $country = $parameters['country']??'';

    $contact = $parameters['contact']??'';
    $title = $parameters['title']??'';
    $contact_phone = $parameters['contact_phone']??'';
    $contact_extension = $parameters['contact_extension']??'';
    $contact_mobile = $parameters['contact_mobile']??'';
    $contact_email = $parameters['contact_email']??'';

    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    $extended_log_description = "";

    // Check if api_key_client_id is set
    if (isset($parameters['api_key_client_id'])) {
        $client_id = $parameters['api_key_client_id'];
        $client = readClient($client_id);
        if ($client) {
            return $client;
        }
    }

    // Create client
    mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_rate = $rate, client_currency_code = '$currency_code', client_net_terms = $net_terms, client_tax_id_number = '$tax_id_number', client_lead = $lead, client_notes = '$notes', client_accessed_at = NOW()");

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
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
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
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO client_tags SET client_tag_client_id = $client_id, client_tag_tag_id = $tag");
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
        $extended_log_description .= ", domain added";

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
            $extended_log_description .= ", SSL certificate added";
        }

    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Client', log_action = 'Create', log_description = '$name$extended_log_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $client_id");


    $return_data = [
        'client_id' => $client_id,
        'status' => 'success'
    ];

    return $return_data;
}

function readClient(
    $parameters
) {

    global $mysqli;

    if (!empty($parameters['client_id'])) {
        $client_id = sanitizeInput($parameters['client_id']);
        $api_client_id = isset($parameters['api_key_client_id']) ? sanitizeInput($parameters['api_key_client_id']) : 0;
        $where_clause = getAPIWhereClause("client", $client_id, $api_client_id);
    } elseif (!empty($parameters['client_rmm_id'])) {
        $client_rmm_id = $parameters['client_rmm_id'];
        $api_client_id = isset($parameters['api_key_client_id']) ? sanitizeInput($parameters['api_key_client_id']) : 0;
        $where_clause = getAPIWhereClause("client_rmm", $client_rmm_id, $api_client_id);
    } else {
        return ['status' => 'error', 'message' => 'No client ID or RMM ID provided'];
    }

    $query = "SELECT * FROM clients $where_clause";
    $result = mysqli_query($mysqli, $query);

    $clients = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $clients[$row['client_id']] = $row;
    }

    if (empty($clients)) {
        echo json_encode(['status' => 'error', 'message' => 'No client found']);
        exit;
    } else {
        return $clients;
    }
}

function updateClient(
    $parameters
) {
    $client_id = $parameters['client_id'];
    $name = $parameters['name'];
    $type = $parameters['type'];
    $website = $parameters['website'];
    $referral = $parameters['referral'];
    $rate = $parameters['rate'];
    $currency_code = $parameters['currency_code'];
    $net_terms = $parameters['net_terms'];
    $tax_id_number = $parameters['tax_id_number'];
    $lead = $parameters['lead'];
    $notes = $parameters['notes'];

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    mysqli_query($mysqli, "UPDATE clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_rate = $rate, client_currency_code = '$currency_code', client_net_terms = $net_terms, client_tax_id_number = '$tax_id_number', client_lead = $lead, client_notes = '$notes' WHERE client_id = $client_id");

    // Create Referral if it doesn't exist
    $sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND category_name = '$referral'");
    if(mysqli_num_rows($sql) == 0) {
        mysqli_query($mysqli, "INSERT INTO categories SET category_name = '$referral', category_type = 'Referral'");
        // Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
    }

    // Tags
    // Delete existing tags
    mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_tag_client_id = $client_id");

    // Add new tags
    foreach($_POST['tags'] as $tag) {
        $tag = intval($tag);
        mysqli_query($mysqli, "INSERT INTO client_tags SET client_tag_client_id = $client_id, client_tag_tag_id = $tag");
    }

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client', log_action = 'Modify', log_description = '$session_name modified client $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id");

    return ['status' => 'success'];
}

function archiveClient(
    $parameters
) {
    $client_id = $parameters['client_id'];

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    // Get Client Name
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client', log_action = 'Archive', log_description = '$session_name archived client $client_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id");

    return ['status' => 'success'];
}

function unarchiveClient(
    $parameters
) {
    $client_id = $parameters['client_id'];

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    // Get Client Name
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NULL WHERE client_id = $client_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client', log_action = 'Unarchive', log_description = '$session_name unarchived client $client_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $client_id");

    return ['status' => 'success'];
}

function deleteClient(
    $parameters
) {
    $client_id = $parameters['client_id'];

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    //Get Client Name
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    // Delete Client Data
    mysqli_query($mysqli, "DELETE FROM api_keys WHERE api_key_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM assets WHERE asset_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM client_tags WHERE client_tag_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM documents WHERE document_client_id = $client_id");

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

    mysqli_query($mysqli, "DELETE FROM locations WHERE location_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM logins WHERE login_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM logs WHERE log_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM networks WHERE network_client_id = $client_id");
    mysqli_query($mysqli, "DELETE FROM notifications WHERE notification_client_id = $client_id");

    //Delete Quote  and related items
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

    //Delete Client Files
    removeDirectory('uploads/clients/$client_id');

    //Finally Remove the Client
    mysqli_query($mysqli, "DELETE FROM clients WHERE client_id = $client_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client', log_action = 'Delete', log_description = '$session_name deleted client $client_name and all associated data', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");


    return ['status' => 'success'];
}
