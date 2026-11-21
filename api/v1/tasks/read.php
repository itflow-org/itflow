<?php
/*
 * API - Tasks - Read
 * GET /api/v1/tasks/read.php
 *
 * A ticket's tasks, in their order, or one task - each with its approvals:
 * who is asked (scope client or internal, and type), and its status - pending,
 * approved or declined.
 *
 *   ticket_id      This ticket's tasks - this or task_id is needed
 *   task_id        Just this task
 *   client_id      optional - Only if the ticket is this client's
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$ticket_id = intval($_GET['ticket_id'] ?? 0);
$task_id = intval($_GET['task_id'] ?? 0);

if (!$ticket_id && !$task_id) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = "tasks/read.php needs ticket_id or task_id.";
    echo json_encode($return_arr);
    exit();
}

$sql = mysqli_query($mysqli, "SELECT tasks.*, ticket_prefix, ticket_number, ticket_client_id
    FROM tasks JOIN tickets ON ticket_id = task_ticket_id
    WHERE " . ($task_id ? "task_id = $task_id" : "task_ticket_id = $ticket_id") . " " . apiClientScopeSql('ticket_client_id') . "
    ORDER BY task_order ASC, task_id ASC");

$tasks = [];
while ($sql && ($row = mysqli_fetch_assoc($sql))) {
    $row['approvals'] = [];
    $tasks[intval($row['task_id'])] = $row;
}

if ($tasks) {
    // Not the approval's link key - that is the approver's alone
    $approvals = mysqli_query($mysqli, "SELECT approval_id, approval_scope, approval_type, approval_required_user_id, approval_status, approval_created_by, approval_approved_by, approval_task_id
        FROM task_approvals WHERE approval_task_id IN (" . implode(',', array_keys($tasks)) . ") ORDER BY approval_id");
    while ($approval = mysqli_fetch_assoc($approvals)) {
        $tasks[intval($approval['approval_task_id'])]['approvals'][] = $approval;
    }
    echo json_encode(['success' => 'True', 'count' => count($tasks), 'data' => array_values($tasks)]);
    exit();
}

$sql = false;

// Output
require_once "../read_output.php";
