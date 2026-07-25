<?php

/*
 * API - technicians/time.php
 * Returns time worked by technicians on tickets
 * 
 * GET Parameters:
 *   api_key (required) - API key for authentication
 *   year (optional) - Filter by year (default: current year)
 *   month (optional) - Filter by month 1-12 (default: current month)
 *   technician_id (optional) - Filter by specific technician user ID
 *   limit (optional) - Number of results to return (default: 50)
 *   offset (optional) - Offset for pagination (default: 0)
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

// Get filter parameters
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : null;

// Validate month if provided
if ($month !== null && ($month < 1 || $month > 12)) {
    $return_arr['success'] = "False";
    $return_arr['message'] = "Invalid month parameter. Must be between 1 and 12.";
    echo json_encode($return_arr);
    exit();
}

// Optional technician filter
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : null;

// Build WHERE conditions for date filtering
$date_conditions = "YEAR(tr.ticket_reply_created_at) = $year";
if ($month !== null) {
    $date_conditions .= " AND MONTH(tr.ticket_reply_created_at) = $month";
}

// Build technician filter
$technician_condition = "";
if ($technician_id !== null) {
    $technician_condition = "AND tr.ticket_reply_by = $technician_id";
}

// Query to get time worked per ticket reply, grouped by technician
$sql = mysqli_query(
    $mysqli,
    "SELECT 
        t.ticket_id,
        CONCAT(t.ticket_prefix, t.ticket_number) AS ticket_number,
        t.ticket_subject,
        c.client_id,
        c.client_name AS company,
        u.user_id AS technician_id,
        u.user_name AS technician,
        SEC_TO_TIME(SUM(TIME_TO_SEC(tr.ticket_reply_time_worked))) AS time_worked
    FROM ticket_replies tr
    INNER JOIN tickets t ON t.ticket_id = tr.ticket_reply_ticket_id
    INNER JOIN clients c ON c.client_id = t.ticket_client_id
    INNER JOIN users u ON u.user_id = tr.ticket_reply_by
    WHERE tr.ticket_reply_time_worked IS NOT NULL
        AND tr.ticket_reply_time_worked != '00:00:00'
        AND $date_conditions
        AND t.1=1 " . apiClientScopeSql('ticket_client_id') . "
        $technician_condition
    GROUP BY t.ticket_id, u.user_id
    ORDER BY c.client_name ASC, t.ticket_number ASC, u.user_name ASC
    LIMIT $limit OFFSET $offset"
);

// Output
require_once "../read_output.php";
