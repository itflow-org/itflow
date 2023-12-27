<?php

/*
 * ajax.php
 * Similar to post.php, but for requests using Asynchronous JavaScript
 * Always returns data in JSON format, unless otherwise specified
 */

require_once "config.php";

require_once "functions.php";

require_once "check_login.php";

require_once "rfc6238.php";


/*
 * Fetches SSL certificates from remote hosts & returns the relevant info (issuer, expiry, public key)
 */
if (isset($_GET['certificate_fetch_parse_json_details'])) {
    // PHP doesn't appreciate attempting SSL sockets to non-existent domains
    if (empty($_GET['domain'])) {
        exit();
    }
    $domain = $_GET['domain'];

    // FQDNs in database shouldn't have a URL scheme, adding one
    $domain = "https://".$domain;

    // Parse host and port
    $url = parse_url($domain, PHP_URL_HOST);
    $port = parse_url($domain, PHP_URL_PORT);
    // Default port
    if (!$port) {
        $port = "443";
    }

    // Get certificate (using verify peer false to allow for self-signed certs)
    $socket = "ssl://$url:$port";
    $get = stream_context_create(array("ssl" => array("capture_peer_cert" => true, "verify_peer" => false,)));
    $read = stream_socket_client($socket, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
    $cert = stream_context_get_params($read);
    $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

    // Process data
    if ($cert_public_key_obj) {
        $response['success'] = "TRUE";
        $response['expire'] = date('Y-m-d', $cert_public_key_obj['validTo_time_t']);
        $response['issued_by'] = strip_tags($cert_public_key_obj['issuer']['O']);
        $response['public_key'] = $export; //nl2br
    } else {
        $response['success'] = "FALSE";
    }

    echo json_encode($response);

}

/*
 * Looks up info for a given certificate ID from the database, used to dynamically populate modal fields
 */
if (isset($_GET['certificate_get_json_details'])) {
    validateTechRole();

    $certificate_id = intval($_GET['certificate_id']);
    $client_id = intval($_GET['client_id']);

    // Individual certificate lookup
    $cert_sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id");
    while ($row = mysqli_fetch_array($cert_sql)) {
        $response['certificate'][] = $row;
    }

    // Get all domains for this client that could be linked to this certificate
    $domains_sql = mysqli_query($mysqli, "SELECT domain_id, domain_name FROM domains WHERE domain_client_id = $client_id");
    while ($row = mysqli_fetch_array($domains_sql)) {
        $response['domains'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Looks up info for a given domain ID from the database, used to dynamically populate modal fields
 */
if (isset($_GET['domain_get_json_details'])) {
    validateTechRole();

    $domain_id = intval($_GET['domain_id']);
    $client_id = intval($_GET['client_id']);

    // Individual domain lookup
    $cert_sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id");
    while ($row = mysqli_fetch_array($cert_sql)) {
        $response['domain'][] = $row;
    }

    // Get all registrars/webhosts (vendors) for this client that could be linked to this domain
    $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id");
    while ($row = mysqli_fetch_array($vendor_sql)) {
        $response['vendors'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Looks up info on the ticket number provided, used to populate the ticket merge modal
 */
if (isset($_GET['merge_ticket_get_json_details'])) {
    validateTechRole();

    $merge_into_ticket_number = intval($_GET['merge_into_ticket_number']);

    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_number, ticket_prefix, ticket_subject, ticket_priority, ticket_status, client_name, contact_name FROM tickets
      LEFT JOIN clients ON ticket_client_id = client_id 
      LEFT JOIN contacts ON ticket_contact_id = contact_id
      WHERE ticket_number = $merge_into_ticket_number");

    if (mysqli_num_rows($sql) == 0) {
        //Do nothing.
    } else {
        //Return ticket, client and contact details for the given ticket number
        $response = mysqli_fetch_array($sql);

        echo json_encode($response);
    }
}

/*
 * Looks up info for a given network ID from the database, used to dynamically populate modal fields
 */
if (isset($_GET['network_get_json_details'])) {
    validateTechRole();

    $network_id = intval($_GET['network_id']);
    $client_id = intval($_GET['client_id']);

    // Individual network lookup
    $network_sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_id = $network_id AND network_client_id = $client_id");
    while ($row = mysqli_fetch_array($network_sql)) {
        $response['network'][] = $row;
    }

    // Lookup all client locations, as networks can be associated with any client location
    $locations_sql = mysqli_query(
        $mysqli,
        "SELECT location_id, location_name FROM locations
         WHERE location_client_id = '$client_id'"
    );
    while ($row = mysqli_fetch_array($locations_sql)) {
        $response['locations'][] = $row;
    }

    echo json_encode($response);
}

if (isset($_POST['client_set_notes'])) {
    $client_id = intval($_POST['client_id']);
    $notes = sanitizeInput($_POST['notes']);

    // Update notes
    mysqli_query($mysqli, "UPDATE clients SET client_notes = '$notes' WHERE client_id = $client_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client', log_action = 'Modify', log_description = '$session_name modified client notes', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

}

if (isset($_POST['contact_set_notes'])) {
    $contact_id = intval($_POST['contact_id']);
    $notes = sanitizeInput($_POST['notes']);

    // Update notes
    mysqli_query($mysqli, "UPDATE contacts SET contact_notes = '$notes' WHERE contact_id = $contact_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = '$session_name modified contact notes', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

}

if (isset($_POST['asset_set_notes'])) {
    $asset_id = intval($_POST['asset_id']);
    $notes = sanitizeInput($_POST['notes']);

    // Update notes
    mysqli_query($mysqli, "UPDATE assets SET asset_notes = '$notes' WHERE asset_id = $asset_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Assets', log_action = 'Modify', log_description = '$session_name modified asset notes', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

}

/*
 * Collision Detection/Avoidance
 * Called upon loading a ticket, and every 2 mins thereafter
 * Is used in conjunction with ticket_query_views to show who is currently viewing a ticket
 */
if (isset($_GET['ticket_add_view'])) {
    $ticket_id = intval($_GET['ticket_id']);

    mysqli_query($mysqli, "INSERT INTO ticket_views SET view_ticket_id = $ticket_id, view_user_id = $session_user_id, view_timestamp = NOW()");
}

/*
 * Collision Detection/Avoidance
 * Returns formatted text of the agents currently viewing a ticket
 * Called upon loading a ticket, and every 2 mins thereafter
 */
if (isset($_GET['ticket_query_views'])) {
    $ticket_id = intval($_GET['ticket_id']);

    $query = mysqli_query($mysqli, "SELECT user_name FROM ticket_views LEFT JOIN users ON view_user_id = user_id WHERE view_ticket_id = $ticket_id AND view_user_id != $session_user_id AND view_timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    while ($row = mysqli_fetch_array($query)) {
        $users[] = $row['user_name'];
    }

    if (!empty($users)) {
        $users = array_unique($users);
        if (count($users) > 1) {
            // Multiple viewers
            $response['message'] = nullable_htmlentities(implode(", ", $users) . " are viewing this ticket.");
        } else {
            // Single viewer
            $response['message'] = nullable_htmlentities(implode("", $users) . " is viewing this ticket.");
        }
    } else {
        // No viewers
        $response['message'] = "";
    }

    echo json_encode($response);
}

/*
 * Generates public/guest links for sharing logins/docs
 */
if (isset($_GET['share_generate_link'])) {
    validateTechRole();

    $item_encrypted_username = '';  // Default empty
    $item_encrypted_credential = '';  // Default empty

    $client_id = intval($_GET['client_id']);
    $item_type = sanitizeInput($_GET['type']);
    $item_id = intval($_GET['id']);
    $item_email = sanitizeInput($_GET['contact_email']);
    $item_note = sanitizeInput($_GET['note']);
    $item_view_limit = intval($_GET['views']);
    $item_expires = sanitizeInput($_GET['expires']);
    $item_expires_friendly = "never"; // default never
    if ($item_expires == "30 MINUTE") {
        $item_expires_friendly = "30 minutes";
    } elseif ($item_expires == "24 HOUR") {
        $item_expires_friendly = "24 hours";
    } elseif ($item_expires == "72 HOUR") {
        $item_expires_friendly = "72 hours (3 days)";
    }

    $item_key = randomString(156);

    if ($item_type == "Document") {
        $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT document_name FROM documents WHERE document_id = $item_id AND document_client_id = $client_id LIMIT 1"));
        $item_name = sanitizeInput($row['document_name']);
    }

    if ($item_type == "File") {
        $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT file_name FROM files WHERE file_id = $item_id AND file_client_id = $client_id LIMIT 1"));
        $item_name = sanitizeInput($row['file_name']);
    }

    if ($item_type == "Login") {
        $login = mysqli_query($mysqli, "SELECT login_name, login_username, login_password FROM logins WHERE login_id = $item_id AND login_client_id = $client_id LIMIT 1");
        $row = mysqli_fetch_array($login);

        $item_name = sanitizeInput($row['login_name']);

        // Decrypt & re-encrypt username/password for sharing
        $login_encryption_key = randomString();

        $login_username_cleartext = decryptLoginEntry($row['login_username']);
        $iv = randomString();
        $username_ciphertext = openssl_encrypt($login_username_cleartext, 'aes-128-cbc', $login_encryption_key, 0, $iv);
        $item_encrypted_username = $iv . $username_ciphertext;

        $login_password_cleartext = decryptLoginEntry($row['login_password']);
        $iv = randomString();
        $password_ciphertext = openssl_encrypt($login_password_cleartext, 'aes-128-cbc', $login_encryption_key, 0, $iv);
        $item_encrypted_credential = $iv . $password_ciphertext;
    }

    // Insert entry into DB
    $sql = mysqli_query($mysqli, "INSERT INTO shared_items SET item_active = 1, item_key = '$item_key', item_type = '$item_type', item_related_id = $item_id, item_encrypted_username = '$item_encrypted_username', item_encrypted_credential = '$item_encrypted_credential', item_note = '$item_note', item_views = 0, item_view_limit = $item_view_limit, item_expire_at = NOW() + INTERVAL + $item_expires, item_client_id = $client_id");
    $share_id = $mysqli->insert_id;

    // Return URL
    if ($item_type == "Login") {
        $url = "https://$config_base_url/guest_view_item.php?id=$share_id&key=$item_key&ek=$login_encryption_key";
    }
    else {
        $url = "https://$config_base_url/guest_view_item.php?id=$share_id&key=$item_key";
    }

    // Send user e-mail, if specified
    if(!empty($config_smtp_host) && filter_var($item_email, FILTER_VALIDATE_EMAIL)){

        $subject = "Time sensitive - $session_company_name secure link enclosed";
        if ($item_expires_friendly == "never") {
            $subject = "$session_company_name secure link enclosed";
        }
        $body = mysqli_real_escape_string($mysqli, "Hello,<br><br>$session_name from $session_company_name sent you a time sensitive secure link regarding '$item_name'.<br><br>The link will expire in <strong>$item_expires_friendly</strong> and may only be viewed <strong>$item_view_limit</strong> times, before the link is destroyed. <br><br><strong><a href='$url'>Click here to access your secure content</a></strong><br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email");

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $item_email,
                'recipient_name' => $item_email,
                'subject' => $subject,
                'body' => $body
            ]
        ];
        
        $mail = addToMailQueue($mysqli, $data);

        if ($mail !== true) {
            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $item_email'");
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $item_email regarding $subject. $item_mail', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
        }

    }

    echo json_encode($url);


    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Sharing', log_action = 'Create', log_description = '$session_name created shared link for $item_type - $item_name', log_client_id = $client_id, log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

}

/*
 *  Looks up info for a given scheduled ticket ID from the database, used to dynamically populate modal edit fields
 */
if (isset($_GET['scheduled_ticket_get_json_details'])) {
    validateTechRole();

    $client_id = intval($_GET['client_id']);
    $ticket_id = intval($_GET['ticket_id']);

    $contact_sql = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC"
    );
    while ($row = mysqli_fetch_array($contact_sql)) {
        $response['contacts'][] = $row;
    }


    $ticket_sql = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets
    WHERE scheduled_ticket_id = $ticket_id
    AND scheduled_ticket_client_id = $client_id LIMIT 1");
    while ($row = mysqli_fetch_array($ticket_sql)) {
        $response['ticket'][] = $row;
    }

    $asset_sql = mysqli_query($mysqli, "SELECT asset_id, asset_name FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL");
    while ($row = mysqli_fetch_array($asset_sql)) {
        $response['assets'][] = $row;
    }

    echo json_encode($response);

}

/*
 * Looks up info for a given quote ID from the database, used to dynamically populate modal fields
 */
if (isset($_GET['quote_get_json_details'])) {
    $quote_id = intval($_GET['quote_id']);

    // Get quote details
    $quote_sql = mysqli_query(
        $mysqli,
        "SELECT * FROM quotes
        LEFT JOIN clients ON quote_client_id = client_id
        WHERE quote_id = $quote_id LIMIT 1"
    );

    while ($row = mysqli_fetch_array($quote_sql)) {
        $response['quote'][] = $row;
    }


    // Get all income-related categories for quoting
    $quote_created_at = $response['quote'][0]['quote_created_at'];
    $category_sql = mysqli_query(
        $mysqli,
        "SELECT category_id, category_name FROM categories
        WHERE category_type = 'Income' AND (category_archived_at > '$quote_created_at' OR category_archived_at IS NULL)
        ORDER BY category_name"
    );

    while ($row = mysqli_fetch_array($category_sql)) {
        $response['categories'][] = $row;
    }

    echo json_encode($response);

}

/*
 * Returns sorted list of active clients
 */
if (isset($_GET['get_active_clients'])) {

    $client_sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name FROM clients
        WHERE client_archived_at IS NULL
        ORDER BY client_accessed_at DESC"
    );

    while ($row = mysqli_fetch_array($client_sql)) {
        $response['clients'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns ordered list of active contacts for a specified client
 */
if (isset($_GET['get_client_contacts'])) {
    $client_id = intval($_GET['client_id']);

    $contact_sql = mysqli_query(
        $mysqli,
        "SELECT contact_id, contact_name FROM contacts
        WHERE contacts.contact_archived_at IS NULL AND contact_client_id = $client_id
        ORDER BY contact_important DESC, contact_name"
    );

    while ($row = mysqli_fetch_array($contact_sql)) {
        $response['contacts'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Dynamic TOTP "resolver"
 * When provided with a TOTP secret, returns a 6-digit code
 * // TODO: Check if this can now be removed
 */
if (isset($_GET['get_totp_token'])) {
    $otp = TokenAuth6238::getTokenCode(strtoupper($_GET['totp_secret']));

    echo json_encode($otp);
}

/*
 * NEW TOTP getter for client login/passwords page
 * When provided with a login ID, checks permissions and returns the 6-digit code
 */
if (isset($_GET['get_totp_token_via_id'])) {
    validateTechRole();

    $login_id = intval($_GET['login_id']);

    $sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT login_name, login_otp_secret, login_client_id FROM logins WHERE login_id = $login_id"));
    $name = sanitizeInput($sql['login_name']);
    $totp_secret = $sql['login_otp_secret'];
    $client_id = intval($sql['login_client_id']);

    $otp = TokenAuth6238::getTokenCode(strtoupper($totp_secret));
    echo json_encode($otp);

    // Logging
    //  Only log the TOTP view if the user hasn't already viewed this specific login entry recently, this prevents logs filling if a user hovers across an entry a few times
    $check_recent_totp_view_logged_sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS recent_totp_view FROM logs WHERE log_type = 'Login' AND log_action = 'View TOTP' AND log_user_id = $session_user_id AND log_entity_id = $login_id AND log_client_id = $client_id AND log_created_at > (NOW() - INTERVAL 5 MINUTE)"));
    $recent_totp_view_logged_count = intval($check_recent_totp_view_logged_sql['recent_totp_view']);

    if ($recent_totp_view_logged_count == 0) {
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'View TOTP', log_description = '$session_name viewed login TOTP code for $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id");
    }
}

if (isset($_GET['get_readable_pass'])) {
    echo GenerateReadablePassword(4);
}
