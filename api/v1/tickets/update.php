<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/get_settings.php";

// Defaults
$update_id = false;
$response = ['success' => false, 'message' => ''];

// Validate CSRF token for web requests (if not API-only)
//if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'https://yourdomain.com') {
//    http_response_code(403);
//    die(json_encode(['error' => 'Cross-origin request denied']));
//}

// Validate user permissions for this API key
if (!$api_key_permissions['ticket_write']) {
    http_response_code(403);
    die(json_encode(['error' => 'API key does not have ticket write permissions']));
}

// Validate and sanitize input
$ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$ticket_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Valid ticket ID required']));
}

// Sanitize other inputs with proper validation
$subject = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
$details = trim(filter_input(INPUT_POST, 'details', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
$priority = filter_input(INPUT_POST, 'priority', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 5]]);
$status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10]]);
$assigned_to = filter_input(INPUT_POST, 'assigned_to', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$billable = filter_input(INPUT_POST, 'billable', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
$vendor_id = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$vendor_ticket = trim(filter_input(INPUT_POST, 'vendor_ticket_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
$client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$contact_id = filter_input(INPUT_POST, 'contact', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

// Rate limiting check
//require_once '../rate_limit.php';
//if (!checkRateLimit($api_key_id, 'ticket_update', 10)) { // 10 requests per minute
//    http_response_code(429);
//    die(json_encode(['error' => 'Rate limit exceeded']));
//}

// Verify the ticket exists and API key has access to it
$stmt = $mysqli->prepare("SELECT t.*, c.client_id 
                         FROM tickets t 
                         LEFT JOIN clients c ON t.ticket_client_id = c.client_id 
                         WHERE t.ticket_id = ? 
                         AND (t.ticket_client_id = 0 OR c.client_id IS NOT NULL)
                         LIMIT 1");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();
$ticket_row = $result->fetch_assoc();
$stmt->close();

if (!$ticket_row) {
    http_response_code(404);
    die(json_encode(['error' => 'Ticket not found or access denied']));
}

// Verify API key has access to this client's data
if ($ticket_row['ticket_client_id'] > 0 && $ticket_row['ticket_client_id'] != $api_key_client_id && $api_key_client_id != 0) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied to this client data']));
}

// Build update query with parameterized statements
$updates = [];
$params = [];
$types = '';

// ... all your existing $updates logic remains unchanged ...

if (!empty($updates)) {
    $params[] = $ticket_id;
    $types .= 'i';
    
    $update_sql = "UPDATE tickets SET " . implode(", ", $updates) . " WHERE ticket_id = ?";
    
    $stmt = $mysqli->prepare($update_sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $run_update = $stmt->execute();
        
        if ($run_update) {
            $update_id = $ticket_id;
            $response['success'] = true;
            $response['message'] = 'Ticket updated successfully';

            // Logging
            logAction(
                "Ticket",
                "Update",
                "Updated ticket {$ticket_row['ticket_prefix']}{$ticket_row['ticket_number']} via API ($api_key_name)",
                $ticket_row['ticket_client_id'],
                $ticket_id
            );

            // --- Only change: set closed timestamps if status indicates closure ---
            $closed_status_value = 5; // adjust to your resolved/closed status ID
            if ($status == $closed_status_value) {
                $stmt2 = $mysqli->prepare("UPDATE tickets SET ticket_closed_at = NOW(), ticket_resolved_at = NOW() WHERE ticket_id = ?");
                $stmt2->bind_param("i", $ticket_id);
                $stmt2->execute();
                $stmt2->close();

                logAction(
                    "Ticket",
                    "Close",
                    "Closed ticket {$ticket_row['ticket_prefix']}{$ticket_row['ticket_number']} via API ($api_key_name)",
                    $ticket_row['ticket_client_id'],
                    $ticket_id
                );
            }
            // --- End minimal change ---

            // Audit logging
            logAction(
                "API",
                "Success",
                "Updated ticket {$ticket_row['ticket_prefix']}{$ticket_row['ticket_number']} via API ($api_key_name)",
                $ticket_row['ticket_client_id']
            );
        } else {
            http_response_code(500);
            $response['message'] = 'Database update failed';
            error_log("Ticket update failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        $response['message'] = 'Database preparation failed';
        error_log("Prepare failed: " . $mysqli->error);
    }
} else {
    $response['message'] = 'No valid fields to update';
}

// Output
header('Content-Type: application/json');
echo json_encode($response);
exit;
