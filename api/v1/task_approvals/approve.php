<?php
/*
 * API - Task Approvals - Approve
 * POST /api/v1/task_approvals/approve.php
 *
 * Approve a task as the key's agent, as Approve does on the ticket - an
 * approval asked of your team that is still pending: one asked of this agent,
 * or of any agent but the one who asked. The agent who asked is told. The
 * client approves theirs from the email they get.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   approval_id  required - The approval
 *   client_id    the ticket's client - needed when the key's user is limited to certain clients
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

$approval_id = intval($_POST['approval_id'] ?? 0);
$approval = $approval_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT approval_id, approval_scope, approval_type, approval_required_user_id, approval_created_by, approval_status, approval_task_id
    FROM task_approvals WHERE approval_id = $approval_id LIMIT 1")) : null;

// The task and ticket, of a client the key's user can reach
$_POST['task_id'] = intval($approval['approval_task_id'] ?? 0);
require_once '../tasks/task_find.php';

if ($approval && $task) {

    $required_user = intval($approval['approval_required_user_id']);
    $created_by = intval($approval['approval_created_by']);
    $task_name = (string) $task['task_name'];

    $refuse = '';
    if ($approval['approval_scope'] !== 'internal') {
        $refuse = 'This approval is asked of the client - they approve it from the email they got.';
    } elseif ($approval['approval_status'] !== 'pending') {
        $refuse = "This approval is {$approval['approval_status']} already.";
    } elseif ($required_user > 0 && $required_user !== $session_user_id) {
        $refuse = 'This approval is for another agent.';
    } elseif ($required_user === 0 && $created_by === $session_user_id) {
        $refuse = "The key's agent asked for this approval - someone else has to give it.";
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    mysqli_query($mysqli, "UPDATE task_approvals SET approval_status = 'approved', approval_approved_by = $session_user_id WHERE approval_id = $approval_id AND approval_status = 'pending'");

    if (mysqli_affected_rows($mysqli) > 0) {
        $update_count = 1;
        $ticket_id = intval($task['ticket_id']);
        $task_name_sql = escapeSql($task_name);

        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name approved ticket task $task_name_sql', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = 0, notification_user_id = $created_by");

        logAudit("Task", "Edit", "$session_name approved task $task_name_sql (approval $approval_id) via API ($api_key_name)", $client_id, intval($task['task_id']));
        logAudit("API", "Success", "Approved task $task_name_sql via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
