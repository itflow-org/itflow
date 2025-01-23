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

    $name = $_GET['domain'];

    // Get SSL cert for domain (if exists)
    $certificate = getSSL($name);

    if ($certificate['success'] == "TRUE") {
        $response['success'] = "TRUE";
        $response['expire'] = $certificate['expire'];
        $response['issued_by'] = $certificate['issued_by'];
        $response['public_key'] = $certificate['public_key'];
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
    enforceUserPermission('module_support');

    $domain_id = intval($_GET['domain_id']);
    $client_id = intval($_GET['client_id']);

    // Individual domain lookup
    $cert_sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id");
    while ($row = mysqli_fetch_array($cert_sql)) {
        $response['domain'][] = $row;
    }

    // Get all registrars/webhosts (vendors) for this client that could be linked to this domain
    $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
    while ($row = mysqli_fetch_array($vendor_sql)) {
        $response['vendors'][] = $row;
    }

    // Get domain history
    $history_sql = mysqli_query($mysqli, "SELECT * FROM domain_history WHERE domain_history_domain_id = $domain_id");
    $history_html = "<table class='table table-sm table-striped border table-hover'>";
    $history_html .= "<thead class='thead-dark'><tr><th>Date</th><th>Column</th><th>Old Value</th><th>New Value</th></tr></thead><tbody>";
    while ($row = mysqli_fetch_array($history_sql)) {
        // Fetch data from the query and create table rows
        $history_html .= "<tr>";
        $history_html .= "<td>" . htmlspecialchars(date('Y-m-d', strtotime($row['domain_history_modified_at']))) . "</td>";
        $history_html .= "<td>" . htmlspecialchars($row['domain_history_column']) . "</td>";
        $history_html .= "<td>" . htmlspecialchars($row['domain_history_old_value']) . "</td>";
        $history_html .= "<td>" . htmlspecialchars($row['domain_history_new_value']) . "</td>";
        $history_html .= "</tr>";
    }
    $history_html .= "</tbody></table>";

    // Return the HTML content to JavaScript
    $response['history'] = $history_html;

    echo json_encode($response);
}

/*
 * Looks up info on the ticket number provided, used to populate the ticket merge modal
 */
if (isset($_GET['merge_ticket_get_json_details'])) {
    validateTechRole();

    $merge_into_ticket_number = intval($_GET['merge_into_ticket_number']);

    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_number, ticket_prefix, ticket_subject, ticket_priority, ticket_status, ticket_status_name, client_name, contact_name FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id 
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_number = $merge_into_ticket_number");

    if (mysqli_num_rows($sql) == 0) {
        //Do nothing.
        echo "No ticket found!";
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
    logAction("Client", "Edit", "$session_name edited client notes", $client_id);

}

if (isset($_POST['contact_set_notes'])) {
    $contact_id = intval($_POST['contact_id']);
    $notes = sanitizeInput($_POST['notes']);

    // Get Contact Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id 
        FROM contacts WHERE contact_id = $contact_id"
    );
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);

    // Update notes
    mysqli_query($mysqli, "UPDATE contacts SET contact_notes = '$notes' WHERE contact_id = $contact_id");

    // Logging
    logAction("Contact", "Edit", "$session_name edited contact notes for $contact_name", $client_id, $contact_id);

}

if (isset($_POST['asset_set_notes'])) {
    $asset_id = intval($_POST['asset_id']);
    $notes = sanitizeInput($_POST['notes']);

    // Get Asset Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id 
        FROM assets WHERE asset_id = $asset_id"
    );
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    // Update notes
    mysqli_query($mysqli, "UPDATE assets SET asset_notes = '$notes' WHERE asset_id = $asset_id");

    // Logging
    logAction("Asset", "Edit", "$session_name edited asset notes for $asset_name", $client_id, $asset_id);

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
            $response['message'] = "<i class='fas fa-fw fa-eye mr-2'></i>" . nullable_htmlentities(implode(", ", $users) . " are viewing this ticket.");
        } else {
            // Single viewer
            $response['message'] = "<i class='fas fa-fw fa-eye mr-2'></i>" . nullable_htmlentities(implode("", $users) . " is viewing this ticket.");
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
    $item_view_limit_wording = "";
    if ($item_view_limit == 1) {
        $item_view_limit_wording = " and may only be viewed <strong>once</strong>, before the link is destroyed.";
    }
    $item_expires = sanitizeInput($_GET['expires']);
    $item_expires_friendly = "never"; // default never
    if ($item_expires == "1 HOUR") {
        $item_expires_friendly = "1 hour";
    } elseif ($item_expires == "24 HOUR") {
        $item_expires_friendly = "1 day";
    } elseif ($item_expires == "168 HOUR") {
        $item_expires_friendly = "1 week";
    } elseif ($item_expires == "730 HOUR") {
        $item_expires_friendly = "1 month";
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
    $sql = mysqli_query($mysqli, "INSERT INTO shared_items SET item_active = 1, item_key = '$item_key', item_type = '$item_type', item_related_id = $item_id, item_encrypted_username = '$item_encrypted_username', item_encrypted_credential = '$item_encrypted_credential', item_note = '$item_note', item_recipient = '$item_email', item_views = 0, item_view_limit = $item_view_limit, item_expire_at = NOW() + INTERVAL + $item_expires, item_client_id = $client_id");
    $share_id = $mysqli->insert_id;

    // Return URL
    if ($item_type == "Login") {
        $url = "https://$config_base_url/guest/guest_view_item.php?id=$share_id&key=$item_key&ek=$login_encryption_key";
    }
    else {
        $url = "https://$config_base_url/guest/guest_view_item.php?id=$share_id&key=$item_key";
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

    // Sanitize Config vars from get_settings.php
    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_mail_from_name = sanitizeInput($config_mail_from_name);
    $config_mail_from_email = sanitizeInput($config_mail_from_email);

    // Send user e-mail, if specified
    if(!empty($config_smtp_host) && filter_var($item_email, FILTER_VALIDATE_EMAIL)){

        $subject = "Time sensitive - $company_name secure link enclosed";
        if ($item_expires_friendly == "never") {
            $subject = "$company_name secure link enclosed";
        }
        $body = "Hello,<br><br>$session_name from $company_name sent you a time sensitive secure link regarding \"$item_name\".<br><br>The link will expire in <strong>$item_expires_friendly</strong>$item_view_limit_wording.<br><br><strong><a href=\'$url\'>Click here to access your secure content</a></strong><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        // Add the intended recipient disclosure
        $body .= "<br><br><em>This email and any attachments are confidential and intended for the specified recipient(s) only. If you are not the intended recipient, please notify the sender and delete this email. Unauthorized use, disclosure, or distribution is prohibited.</em>";

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

        addToMailQueue($data);

    }

    echo json_encode($url);

    // Logging
    logAction("Share", "Create", "$session_name created shared link for $item_type - $item_name", $client_id, $item_id);

}

/*
 *  Looks up info for a given recurring (was scheduled) ticket ID from the database, used to dynamically populate modal edit fields
 */
if (isset($_GET['recurring_ticket_get_json_details'])) {
    validateTechRole();

    $client_id = intval($_GET['client_id']);
    $ticket_id = intval($_GET['ticket_id']);

    // Get all contacts, to allow tickets to be raised under a specific contact
    $contact_sql = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC"
    );
    while ($row = mysqli_fetch_array($contact_sql)) {
        $response['contacts'][] = $row;
    }

    // Get ticket details
    $ticket_sql = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets
    WHERE scheduled_ticket_id = $ticket_id
    AND scheduled_ticket_client_id = $client_id LIMIT 1");
    while ($row = mysqli_fetch_array($ticket_sql)) {
        $response['ticket'][] = $row;
    }

    // Get assets
    $asset_sql = mysqli_query($mysqli, "SELECT asset_id, asset_name FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL");
    while ($row = mysqli_fetch_array($asset_sql)) {
        $response['assets'][] = $row;
    }

    // Get technicians to auto assign the ticket to
    $sql_agents = mysqli_query(
        $mysqli,
        "SELECT users.user_id, user_name FROM users
            LEFT JOIN user_settings on users.user_id = user_settings.user_id
            WHERE user_role > 1
            AND user_status = 1
            AND user_archived_at IS NULL
            ORDER BY user_name ASC"
    );
    while ($row = mysqli_fetch_array($sql_agents)) {
        $response['agents'][] = $row;
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
        "SELECT contact_id, contact_name, contact_primary, contact_important, contact_technical FROM contacts
        WHERE contacts.contact_archived_at IS NULL AND contact_client_id = $client_id
        ORDER BY contact_primary DESC, contact_technical DESC, contact_important DESC, contact_name"
    );

    while ($row = mysqli_fetch_array($contact_sql)) {
        $response['contacts'][] = $row;
    }

    echo json_encode($response);
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
        // Logging
        logAction("Credential", "View TOTP", "$session_name viewed credential TOTP code for $name", $client_id, $login_id);

    }
}

if (isset($_GET['get_readable_pass'])) {
    echo json_encode(GenerateReadablePassword(4));
}
