<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/get_settings.php";

// Defaults
$update_id = false;

// Sanitize input
$ticket_id       = intval($_POST['ticket_id'] ?? 0);
$subject         = trim($_POST['subject'] ?? '');
$details         = trim($_POST['details'] ?? '');
$priority        = intval($_POST['priority'] ?? 0);
$status          = intval($_POST['status'] ?? 0);
$assigned_to     = intval($_POST['assigned_to'] ?? 0);
$billable        = intval($_POST['billable'] ?? 0);
$vendor_id       = intval($_POST['vendor_id'] ?? 0);
$vendor_ticket   = trim($_POST['vendor_ticket_number'] ?? '');
$client_id       = intval($_POST['client_id'] ?? 0);
$contact_id      = intval($_POST['contact'] ?? 0);

// Ensure we have a ticket to update
if ($ticket_id > 0) {
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    $ticket_row = mysqli_fetch_array($sql);

    if ($ticket_row) {

        $updates = [];

        if ($subject !== '') {
            $updates[] = "ticket_subject = '" . mysqli_real_escape_string($mysqli, $subject) . "'";
        }
        if ($details !== '') {
            $updates[] = "ticket_details = '" . mysqli_real_escape_string($mysqli, $details) . "'";
        }
        if ($priority > 0) {
            $updates[] = "ticket_priority = $priority";
        }
        if ($status > 0) {
            $updates[] = "ticket_status = $status";
        }
        if ($assigned_to >= 0) {
            $updates[] = "ticket_assigned_to = $assigned_to";
        }
        if ($billable >= 0) {
            $updates[] = "ticket_billable = $billable";
        }
        if ($vendor_id > 0) {
            $updates[] = "ticket_vendor_id = $vendor_id";
        }
        if ($vendor_ticket !== '') {
            $updates[] = "ticket_vendor_ticket_number = '" . mysqli_real_escape_string($mysqli, $vendor_ticket) . "'";
        }
        if ($client_id > 0) {
            $updates[] = "ticket_client_id = $client_id";
        }
        if ($contact_id > 0) {
            $updates[] = "ticket_contact_id = $contact_id";
        }

        if (!empty($updates)) {
            $update_sql = "UPDATE tickets SET " . implode(", ", $updates) . " WHERE ticket_id = $ticket_id";
            $run_update = mysqli_query($mysqli, $update_sql);

            if ($run_update) {
                $update_id = $ticket_id;

                // Logging
                logAction(
                    "Ticket",
                    "Update",
                    "Updated ticket {$ticket_row['ticket_prefix']}{$ticket_row['ticket_number']} via API ($api_key_name)",
                    $ticket_row['ticket_client_id'],
                    $ticket_id
                );

                if ($status == 5) { // Assuming "5 = Closed" (adjust for your statuses)
                    logAction(
                        "Ticket",
                        "Close",
                        "Closed ticket {$ticket_row['ticket_prefix']}{$ticket_row['ticket_number']} via API ($api_key_name)",
                        $ticket_row['ticket_client_id'],
                        $ticket_id
                    );
                }

                logAction(
                    "API",
                    "Success",
                    "Updated ticket {$ticket_row['ticket_prefix']}{$ticket_row['ticket_number']} via API ($api_key_name)",
                    $ticket_row['ticket_client_id']
                );
            }
        }
    }
}

// Output
require_once '../create_output.php';
$output = ob_get_clean();
