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

// ✅ Validate and sanitize input directly from $_POST (already JSON-decoded in validate_api_key.php)
$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : null;
if (!$ticket_id || $ticket_id < 1) {
    http_response_code(400);
    die(json_encode(['error' => 'Valid ticket ID required']));
}

// Other inputs
$subject        = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$details        = isset($_POST['details']) ? trim($_POST['details']) : '';
$priority       = isset($_POST['priority']) ? intval($_POST['priority']) : null;
$status         = isset($_POST['status']) ? intval($_POST['status']) : null;
$assigned_to    = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
$billable       = isset($_POST['billable']) ? intval($_POST['billable']) : null;
$vendor_id      = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
$vendor_ticket  = isset($_POST['vendor_ticket_number']) ? trim($_POST['vendor_ticket_number']) : '';
$client_id      = isset($_POST['client_id']) ? intval($_POST['client_id']) : null;
$contact_id     = isset($_POST['contact']) ? intval($_POST['contact']) : null;

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

// Example of how to add fields
if ($subject !== '') { $updates[] = "ticket_subject = ?"; $params[] = $subject; $types .= 's'; }
if ($details !== '') { $updates[] = "ticket_details = ?"; $params[] = $details; $types .= 's'; }
if ($priority !== null) { $updates[] = "ticket_priority = ?"; $params[] = $priority; $types .= 'i'; }
if ($status !== null) { $updates[] = "ticket_status = ?"; $params[] = $status; $types .= 'i'; }
if ($assigned_to !== null) { $updates[] = "ticket_assigned_to = ?"; $params[] = $assigned_to; $types .= 'i'; }
if ($billable !== null) { $updates[] = "ticket_billable = ?"; $params[] = $billable; $types .= 'i'; }
if ($vendor_id !== null) { $updates[] = "ticket_vendor_id = ?"; $params[] = $vendor_id; $types .= 'i'; }
if ($vendor_ticket !== '') { $updates[] = "ticket_vendor_ticket_number = ?"; $params[] = $vendor_ticket; $types .= 's'; }
if ($client_id !== null) { $updates[] = "ticket_client_id = ?"; $params[] = $client_id; $types .= 'i'; }
if ($contact_id !== null) { $updates[] = "ticket_contact_id = ?"; $params[] = $contact_id; $types .= 'i'; }

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
