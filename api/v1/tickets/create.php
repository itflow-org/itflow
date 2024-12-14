<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Ticket-related settings
require_once "../../../get_settings.php";

$sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
$row = mysqli_fetch_array($sql);
$company_name = $row['company_name'];
$company_phone = formatPhoneNumber($row['company_phone']);

// Parse Info
$ticket_row = false; // Creation, not an update
require_once 'ticket_model.php';

// Default
$insert_id = false;

if (!empty($subject)) {

    if (!is_int($client_id)) {
        $client_id = 0;
    }

    // If no contact is selected automatically choose the primary contact for the client (if client set)
    if ($contact == 0 && $client_id != 0) {
        $sql = mysqli_query($mysqli,"SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_array($sql);
        $contact = intval($row['contact_id']);
    }

    //Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    // Insert ticket
    $url_key = randomString(156);
    $insert_sql = mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 1, ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_created_by = 0, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_url_key = '$url_key', ticket_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Logging
        logAction("Ticket", "Create", "Created ticket $config_ticket_prefix$ticket_number $subject via API ($api_key_name)", $client_id, $insert_id);
        logAction("API", "Success", "Created ticket $config_ticket_prefix$ticket_number $subject via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';
