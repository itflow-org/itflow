<?php
/*
 * API - Tasks - Complete
 * POST /api/v1/tasks/complete.php
 *
 * Mark a task done, as its tick box on the ticket does - or, with completed 0,
 * not done again. The ticket gets an internal note saying so. A task waiting
 * on an approval, or whose approval was declined, can't be marked done. Not
 * once the ticket is closed.
 *
 * Parameters (POST, JSON body):
 *   api_key    required - Your API key
 *   task_id    required - The task
 *   client_id  the ticket's client - needed when the key's user is limited to certain clients
 *   completed  optional - 1 (the default) done, 0 not done
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'task_find.php';

if ($task) {

    $complete = !array_key_exists('completed', $_POST) || !empty($_POST['completed']);
    $ticket_id = intval($task['ticket_id']);
    $name = (string) $task['task_name'];

    $refuse = '';
    if (!empty($task['ticket_closed_at'])) {
        $refuse = $task_locked;
    } elseif ($complete && !empty($task['task_completed_at'])) {
        $refuse = "Task $name is done already.";
    } elseif (!$complete && empty($task['task_completed_at'])) {
        $refuse = "Task $name isn't done.";
    } elseif ($complete) {
        $waiting = mysqli_fetch_row(mysqli_query($mysqli, "SELECT approval_status FROM task_approvals WHERE approval_task_id = $task_id AND approval_status IN ('pending', 'declined') ORDER BY approval_status = 'declined' DESC LIMIT 1"));
        if ($waiting) {
            $refuse = $waiting[0] === 'declined' ? "Task $name can't be done - its approval was declined." : "Task $name is waiting for approval.";
        }
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    if ($complete) {
        mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_id = $task_id AND task_completed_at IS NULL");
    } else {
        mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NULL, task_completed_by = NULL WHERE task_id = $task_id");
    }

    // The note on the ticket, as when it is ticked there - no time worked
    $name_sql = escapeSql($name);
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . ($complete ? 'Completed Task' : 'Undo Completed Task') . " - $name_sql', ticket_reply_time_worked = '00:00:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $update_count = 1;
    logAudit("Task", "Edit", ($complete ? "Completed task $name_sql" : "Marked task $name_sql as incomplete") . " via API ($api_key_name)", $client_id, $task_id);
    logAudit("API", "Success", ($complete ? "Completed task $name_sql" : "Marked task $name_sql as incomplete") . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
