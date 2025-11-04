<?php

/*
 * ajax.php
 * Similar to post.php, but for requests using Asynchronous JavaScript
 * Always returns data in JSON format, unless otherwise specified
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";
require_once "../plugins/totp/totp.php";

/*
 * Fetches SSL certificates from remote hosts & returns the relevant info (issuer, expiry, public key)
 */
if (isset($_GET['certificate_fetch_parse_json_details'])) {
    enforceUserPermission('module_support');

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
 * Looks up info on the ticket number provided, used to populate the ticket merge modal
 */
if (isset($_GET['merge_ticket_get_json_details'])) {
    enforceUserPermission('module_support');

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

if (isset($_POST['client_set_notes'])) {
    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);
    $notes = sanitizeInput($_POST['notes']);

    // Update notes
    mysqli_query($mysqli, "UPDATE clients SET client_notes = '$notes' WHERE client_id = $client_id");

    // Logging
    logAction("Client", "Edit", "$session_name edited client notes", $client_id);

}

if (isset($_POST['contact_set_notes'])) {
    enforceUserPermission('module_client', 2);

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
    enforceUserPermission('module_support', 2);

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
 * Ticketing Collision Detection/Avoidance
 * Called upon loading a ticket, and every 2 mins thereafter
 * Is used in conjunction with ticket_query_views to show who is currently viewing a ticket
 */
if (isset($_GET['ticket_add_view'])) {
    $ticket_id = intval($_GET['ticket_id']);

    mysqli_query($mysqli, "INSERT INTO ticket_views SET view_ticket_id = $ticket_id, view_user_id = $session_user_id, view_timestamp = NOW()");
}

/*
 * Ticketing Collision Detection/Avoidance
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
 * Generates public/guest links for sharing credentials/docs
 */
if (isset($_GET['share_generate_link'])) {
    enforceUserPermission('module_support', 2);

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

    if ($item_type == "Credential") {
        $credential = mysqli_query($mysqli, "SELECT credential_name, credential_username, credential_password FROM credentials WHERE credential_id = $item_id AND credential_client_id = $client_id LIMIT 1");
        $row = mysqli_fetch_array($credential);

        $item_name = sanitizeInput($row['credential_name']);

        // Decrypt & re-encrypt username/password for sharing
        $credential_encryption_key = randomString();

        $credential_username_cleartext = decryptCredentialEntry($row['credential_username']);
        $iv = randomString();
        $username_ciphertext = openssl_encrypt($credential_username_cleartext, 'aes-128-cbc', $credential_encryption_key, 0, $iv);
        $item_encrypted_username = $iv . $username_ciphertext;

        $credential_password_cleartext = decryptCredentialEntry($row['credential_password']);
        $iv = randomString();
        $password_ciphertext = openssl_encrypt($credential_password_cleartext, 'aes-128-cbc', $credential_encryption_key, 0, $iv);
        $item_encrypted_credential = $iv . $password_ciphertext;
    }

    // Insert entry into DB
    $sql = mysqli_query($mysqli, "INSERT INTO shared_items SET item_active = 1, item_key = '$item_key', item_type = '$item_type', item_related_id = $item_id, item_encrypted_username = '$item_encrypted_username', item_encrypted_credential = '$item_encrypted_credential', item_note = '$item_note', item_recipient = '$item_email', item_views = 0, item_view_limit = $item_view_limit, item_expire_at = NOW() + INTERVAL + $item_expires, item_client_id = $client_id");
    $share_id = $mysqli->insert_id;

    // Return URL
    if ($item_type == "Credential") {
        $url = "https://$config_base_url/guest/guest_view_item.php?id=$share_id&key=$item_key&ek=$credential_encryption_key";
    }
    else {
        $url = "https://$config_base_url/guest/guest_view_item.php?id=$share_id&key=$item_key";
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

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
 * Returns sorted list of active clients
 */
if (isset($_GET['get_active_clients'])) {
    enforceUserPermission('module_client');

    $client_sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name FROM clients
        WHERE client_archived_at IS NULL
        $access_permission_query
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
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    $contact_sql = mysqli_query(
        $mysqli,
        "SELECT contact_id, contact_name, contact_primary, contact_important, contact_technical FROM contacts
        LEFT JOIN clients on contact_client_id = client_id
        WHERE contacts.contact_archived_at IS NULL AND contact_client_id = $client_id
        $access_permission_query
        ORDER BY contact_primary DESC, contact_technical DESC, contact_important DESC, contact_name"
    );

    while ($row = mysqli_fetch_array($contact_sql)) {
        $response['contacts'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns ordered list of active assets for a specified client
 */
if (isset($_GET['get_client_assets'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    $asset_sql = mysqli_query(
        $mysqli,
        "SELECT asset_id, asset_name, contact_name FROM assets
        LEFT JOIN clients on asset_client_id = client_id
        LEFT JOIN contacts ON contact_id = asset_contact_id
        WHERE assets.asset_archived_at IS NULL AND asset_client_id = $client_id
        $access_permission_query
        ORDER BY asset_important DESC, asset_name"
    );

    while ($row = mysqli_fetch_array($asset_sql)) {
        $response['assets'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns locations for a specified client
 */
if (isset($_GET['get_client_locations'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    $locations_sql = mysqli_query(
        $mysqli,
        "SELECT location_id, location_name FROM locations
        LEFT JOIN clients on location_client_id = client_id
        WHERE locations.location_archived_at IS NULL AND location_client_id = $client_id
        $access_permission_query
        ORDER BY location_primary DESC, location_name ASC"
    );

    while ($row = mysqli_fetch_array($locations_sql)) {
        $response['locations'][] = $row;
    }

    echo json_encode($response);
}

/*
 * Returns ordered list of vendors for a specified client
 */
if (isset($_GET['get_client_vendors'])) {
    enforceUserPermission('module_client');

    $client_id = intval($_GET['client_id']);

    $vendors_sql = mysqli_query(
        $mysqli,
        "SELECT vendor_id, vendor_name FROM vendors
        LEFT JOIN clients on vendor_client_id = client_id
        WHERE vendors.vendor_archived_at IS NULL AND vendor_client_id = $client_id
        $access_permission_query
        ORDER BY vendor_name ASC"
    );

    while ($row = mysqli_fetch_array($vendors_sql)) {
        $response['vendors'][] = $row;
    }

    echo json_encode($response);
}

/*
 * NEW TOTP getter for client login/passwords page
 * When provided with a login ID, checks permissions and returns the 6-digit code
 */
if (isset($_GET['get_totp_token_via_id'])) {
    enforceUserPermission('module_credential');

    $credential_id = intval($_GET['credential_id']);

    $sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credential_name, credential_otp_secret, credential_client_id FROM credentials WHERE credential_id = $credential_id"));
    $name = sanitizeInput($sql['credential_name']);
    $totp_secret = $sql['credential_otp_secret'];
    $client_id = intval($sql['credential_client_id']);

    $otp = TokenAuth6238::getTokenCode(strtoupper($totp_secret));
    echo json_encode($otp);

    // Logging
    //  Only log the TOTP view if the user hasn't already viewed this specific login entry recently, this prevents logs filling if a user hovers across an entry a few times
    $check_recent_totp_view_logged_sql = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS recent_totp_view FROM logs WHERE log_type = 'Credential' AND log_action = 'View TOTP' AND log_user_id = $session_user_id AND log_entity_id = $credential_id AND log_client_id = $client_id AND log_created_at > (NOW() - INTERVAL 5 MINUTE)"));
    $recent_totp_view_logged_count = intval($check_recent_totp_view_logged_sql['recent_totp_view']);

    if ($recent_totp_view_logged_count == 0) {
        // Logging
        logAction("Credential", "View TOTP", "$session_name viewed credential TOTP code for $name", $client_id, $credential_id);

    }
}

if (isset($_GET['get_readable_pass'])) {
    echo json_encode(GenerateReadablePassword(4));
}

/*
 * ITFlow - POST request handler for client tickets
 */
if (isset($_POST['update_kanban_status_position'])) {
    // Update multiple ticket status kanban orders
    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];

    foreach ($positions as $position) {
        $status_id = intval($position['status_id']);
        $kanban = intval($position['status_kanban']);

        mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_order = $kanban WHERE ticket_status_id = $status_id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_kanban_ticket'])) {
    // Update ticket kanban order and status
    enforceUserPermission('module_support', 2);

    // all tickets on the column
    $positions = $_POST['positions'];

    foreach ($positions as $position) {
        $ticket_id = intval($position['ticket_id']);
        $kanban = intval($position['ticket_order']); // ticket kanban position
        $status = intval($position['ticket_status']); // ticket statuses
        $oldStatus = intval($position['ticket_oldStatus']); // ticket old status if moved

        $statuses['Closed'] = 5;
        $statuses['Resolved'] = 4;

        // Continue if status is null / Closed
        if ($status === null || $status === $statuses['Closed']) {
            continue;
        }


        if ($oldStatus === false) {
            // if ticket was not moved, just uptdate the order on kanban
            mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban WHERE ticket_id = $ticket_id");
            customAction('ticket_update', $ticket_id);
        } else {
            // If the ticket was moved from a resolved status to another status, we need to update ticket_resolved_at
            if ($oldStatus === $statuses['Resolved']) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban, ticket_status = $status, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id");
                customAction('ticket_update', $ticket_id);
            } elseif ($status === $statuses['Resolved']) {
                // If the ticket was moved to a resolved status, we need to update ticket_resolved_at
                mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban, ticket_status = $status, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
                customAction('ticket_update', $ticket_id);

                // Client notification email
                if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

                    // Get details
                    $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_status_name, ticket_assigned_to, ticket_url_key, ticket_client_id FROM tickets 
                        LEFT JOIN clients ON ticket_client_id = client_id 
                        LEFT JOIN contacts ON ticket_contact_id = contact_id
                        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
                        WHERE ticket_id = $ticket_id
                    ");
                    $row = mysqli_fetch_array($ticket_sql);

                    $contact_name = sanitizeInput($row['contact_name']);
                    $contact_email = sanitizeInput($row['contact_email']);
                    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
                    $ticket_number = intval($row['ticket_number']);
                    $ticket_subject = sanitizeInput($row['ticket_subject']);
                    $client_id = intval($row['ticket_client_id']);
                    $ticket_assigned_to = intval($row['ticket_assigned_to']);
                    $ticket_status = sanitizeInput($row['ticket_status_name']);
                    $url_key = sanitizeInput($row['ticket_url_key']);

                    // Sanitize Config vars from get_settings.php
                    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
                    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
                    $config_base_url = sanitizeInput($config_base_url);

                    // Get Company Info
                    $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
                    $row = mysqli_fetch_array($sql);
                    $company_name = sanitizeInput($row['company_name']);
                    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

                    // EMAIL
                    $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
                    $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been marked as solved and is pending closure.<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

                    // Check email valid
                    if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

                        $data = [];

                        // Email Ticket Contact
                        // Queue Mail

                        $data[] = [
                            'from' => $config_ticket_from_email,
                            'from_name' => $config_ticket_from_name,
                            'recipient' => $contact_email,
                            'recipient_name' => $contact_name,
                            'subject' => $subject,
                            'body' => $body
                        ];
                    }

                    // Also Email all the watchers
                    $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
                    $body .= "<br><br>----------------------------------------<br>YOU ARE A COLLABORATOR ON THIS TICKET";
                    while ($row = mysqli_fetch_array($sql_watchers)) {
                        $watcher_email = sanitizeInput($row['watcher_email']);

                        // Queue Mail
                        $data[] = [
                            'from' => $config_ticket_from_email,
                            'from_name' => $config_ticket_from_name,
                            'recipient' => $watcher_email,
                            'recipient_name' => $watcher_email,
                            'subject' => $subject,
                            'body' => $body
                        ];
                    }
                    addToMailQueue($data);
                }
                //End Mail IF

            } else {
                // If the ticket was moved from any status to another status
                mysqli_query($mysqli, "UPDATE tickets SET ticket_order = $kanban, ticket_status = $status WHERE ticket_id = $ticket_id");
                customAction('ticket_update', $ticket_id);
            }
        }

    }

    // return a response
    echo json_encode(['status' => 'success','payload' => $positions]);
    exit;
}

if (isset($_POST['update_ticket_tasks_order'])) {
    // Update multiple ticket tasks order
    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];
    $ticket_id = intval($_POST['ticket_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE tasks SET task_order = $order WHERE task_ticket_id = $ticket_id AND task_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_task_templates_order'])) {
    // Update multiple task templates order
    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];
    $ticket_template_id = intval($_POST['ticket_template_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE task_templates SET task_template_order = $order WHERE task_template_ticket_template_id = $ticket_template_id AND task_template_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_quote_items_order'])) {
    // Update multiple quote items order
    enforceUserPermission('module_sales', 2);

    $positions = $_POST['positions'];
    $quote_id = intval($_POST['quote_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE invoice_items SET item_order = $order WHERE item_quote_id = $quote_id AND item_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_invoice_items_order'])) {
    // Update multiple invoice items order
    enforceUserPermission('module_sales', 2);

    $positions = $_POST['positions'];
    $invoice_id = intval($_POST['invoice_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE invoice_items SET item_order = $order WHERE item_invoice_id = $invoice_id AND item_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_recurring_invoice_items_order'])) {
    // Update multiple recurring invoice items order
    enforceUserPermission('module_sales', 2);

    $positions = $_POST['positions'];
    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);

    foreach ($positions as $position) {
        $id = intval($position['id']);
        $order = intval($position['order']);

        mysqli_query($mysqli, "UPDATE invoice_items SET item_order = $order WHERE item_recurring_invoice_id = $recurring_invoice_id AND item_id = $id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_GET['client_duplicate_check'])) {
    enforceUserPermission('module_client', 2);

    $name = sanitizeInput($_GET['name']);

    $response['message'] = ""; // default

    if (strlen($name) >= 5) {
        $sql_clients = mysqli_query($mysqli, "SELECT client_name FROM clients
        WHERE client_archived_at IS NULL
        AND client_name LIKE '%$name%'
        ORDER BY client_id DESC LIMIT 1"
        );

        if (mysqli_num_rows($sql_clients) > 0) {
            while ($row = mysqli_fetch_array($sql_clients)) {
                $response['message'] = "<i class='fas fa-fw fa-copy mr-2'></i> Potential duplicate: <i>" . nullable_htmlentities($row['client_name']) . "</i> already exists.";
            }
        }
    }

    echo json_encode($response);
}

if (isset($_GET['ai_reword'])) {

    header('Content-Type: application/json');

    $sql = mysqli_query($mysqli, "SELECT * FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id WHERE ai_model_use_case = 'General' LIMIT 1");

    $row = mysqli_fetch_array($sql);
    $model_name = $row['ai_model_name'];
    $promptText = $row['ai_model_prompt'];
    $url = $row['ai_provider_api_url'];
    $key = $row['ai_provider_api_key'];

    // Collecting the input data from the AJAX request.
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE); // Convert JSON into array.

    $userText = $input['text'];

    // Preparing the data for the OpenAI Chat API request.
    $data = [
        "model" => "$model_name", // Specify the model
        "messages" => [
            ["role" => "system", "content" => $promptText],
            ["role" => "user", "content" => $userText],
        ],
        "temperature" => 0.5
    ];

    // Initialize cURL session to the OpenAI Chat API.
    $ch = curl_init("$url");

    // Set cURL options for the request.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ]);

    // Execute the cURL session and capture the response.
    $response = curl_exec($ch);
    curl_close($ch);

    // Decode the JSON response.
    $responseData = json_decode($response, true);

    // Check if the response contains the expected data and return it.
    if (isset($responseData['choices'][0]['message']['content'])) {
        // Get the response content.
        $content = $responseData['choices'][0]['message']['content'];

        // Clean any leading "html" word or other unwanted text at the beginning.
        $content = preg_replace('/^html/i', '', $content);  // Remove any occurrence of 'html' at the start

        // Clean the response content to remove backticks or code block markers.
        $cleanedContent = str_replace('```', '', $content); // Remove backticks if they exist.

        // Trim any leading/trailing whitespace.
        $cleanedContent = trim($cleanedContent);

        // Return the cleaned response.
        echo json_encode(['rewordedText' => $cleanedContent]);
    } else {
        // Handle errors or unexpected response structure.
        echo json_encode(['rewordedText' => 'Failed to get a response from the AI API.']);
    }

}

if (isset($_GET['ai_create_document_template'])) {
    // get_ai_document_template.php

    header('Content-Type: text/html; charset=UTF-8');

    $sql = mysqli_query($mysqli, "SELECT * FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id WHERE ai_model_use_case = 'General' LIMIT 1");

    $row = mysqli_fetch_array($sql);
    $model_name = $row['ai_model_name'];
    $url = $row['ai_provider_api_url'];
    $key = $row['ai_provider_api_key'];

    $prompt = $_POST['prompt'] ?? '';

    // Basic validation
    if(empty($prompt)){
        echo "No prompt provided.";
        exit;
    }

    // Prepare prompt
    $system_message = "You are a helpful IT documentation assistant. You will create a well-structured HTML template for IT documentation based on a given prompt. Include headings, subheadings, bullet points, and possibly tables for clarity. No Lorem Ipsum, use realistic placeholders and professional language.";
    $user_message = "Create an HTML formatted IT documentation template based on the following request:\n\n\"$prompt\"\n\nThe template should be structured, professional, and useful for IT staff. Include relevant sections, instructions, prerequisites, and best practices.";

    $post_data = [
        "model" => "$model_name",
        "messages" => [
            ["role" => "system", "content" => $system_message],
            ["role" => "user", "content" => $user_message]
        ],
        "temperature" => 0.5
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "Error: " . curl_error($ch);
        exit;
    }
    curl_close($ch);

    $response_data = json_decode($response, true);
    $template = $response_data['choices'][0]['message']['content'] ?? "<p>No content returned from AI.</p>";

    // Print the generated HTML template directly
    echo $template;
}

if (isset($_GET['ai_ticket_summary'])) {

    header('Content-Type: text/html; charset=UTF-8');

    $sql = mysqli_query($mysqli, "SELECT * FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id WHERE ai_model_use_case = 'General' LIMIT 1");

    $row = mysqli_fetch_array($sql);
    $model_name = $row['ai_model_name'];
    $url = $row['ai_provider_api_url'];
    $key = $row['ai_provider_api_key'];

    // Retrieve the ticket_id from POST
    $ticket_id = intval($_POST['ticket_id']);

    // Query the database for ticket details
    $sql = mysqli_query($mysqli, "
        SELECT ticket_subject, ticket_details, ticket_source, ticket_priority, ticket_status_name, category_name
        FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN categories ON ticket_category = category_id
        WHERE ticket_id = $ticket_id
        LIMIT 1
    ");
    $row = mysqli_fetch_assoc($sql);
    $ticket_subject = $row['ticket_subject'];
    $ticket_details = strip_tags($row['ticket_details']); // strip HTML for cleaner prompt
    $ticket_status = $row['ticket_status_name'];
    $ticket_category = $row['category_name'];
    $ticket_source = $row['ticket_source'];
    $ticket_priority = $row['ticket_priority'];

    // Get ticket replies
    $sql_replies = mysqli_query($mysqli, "
        SELECT ticket_reply, ticket_reply_type, user_name
        FROM ticket_replies
        LEFT JOIN users ON ticket_reply_by = user_id
        WHERE ticket_reply_ticket_id = $ticket_id
        AND ticket_reply_archived_at IS NULL
        ORDER BY ticket_reply_id ASC
    ");

    $all_replies_text = "";
    while ($reply = mysqli_fetch_assoc($sql_replies)) {
        $reply_type = $reply['ticket_reply_type'];
        $reply_text = strip_tags($reply['ticket_reply']);
        $reply_by = $reply['user_name'];
        $all_replies_text .= "\nReply Type: $reply_type Reply By: $reply_by: Reply Text: $reply_text";
    }

    $prompt = "
    Summarize the following IT support ticket and its responses in a concise, clear, and professional manner. 
    The summary should include:

    1. Main Issue: What was the problem reported by the user?
    2. Actions Taken: What steps were taken to address the issue?
    3. Resolution or Next Steps: Was the issue resolved or is it ongoing?

    Please ensure:
    - If there are multiple issues, summarize each separately.
    - Urgency: If the ticket or replies express urgency or escalation, highlight it.
    - Attachments: If mentioned in the ticket, note any relevant attachments or files.
    - Avoid extra explanations or unnecessary information.

    Ticket Data:
    - Ticket Source: $ticket_source
    - Current Ticket Status: $ticket_status
    - Ticket Priority: $ticket_priority
    - Ticket Category: $ticket_category
    - Ticket Subject: $ticket_subject
    - Ticket Details: $ticket_details
    - Replies:
    $all_replies_text

    Formatting instructions:
    - Use valid HTML tags only.
    - Use <h3> for section headers (Main Issue, Actions Taken, Resolution).
    - Use <ul><li> for bullet points under each section.
    - Do NOT wrap the output in ```html or any other code fences.
    - Do NOT include <html>, <head>, or <body>.
    - Output only the summary content in pure HTML.
    If any part of the ticket or replies is unclear or ambiguous, mention it in the summary and suggest if further clarification is needed.
    ";

    // Prepare the POST data
    $post_data = [
        "model" => "$model_name",
        "messages" => [
            ["role" => "system", "content" => "Your task is to summarize IT support tickets with clear, concise details."],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.3
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "Error: " . curl_error($ch);
        exit;
    }
    curl_close($ch);

    $response_data = json_decode($response, true);
    $summary = $response_data['choices'][0]['message']['content'] ?? "No summary available.";


    echo $summary; // nl2br to convert newlines to <br>, htmlspecialchars to prevent XSS
}
