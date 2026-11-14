<?php
/*
 * API - Tasks - Delete
 * POST /api/v1/tasks/delete.php
 *
 * Delete a task, with its approvals, as Delete does on it. Not once the ticket
 * is resolved or closed.
 *
 * Parameters (POST, JSON body): api_key, task_id, client_id (the ticket's client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

require_once 'task_find.php';

if ($task) {

    if ($task_locked !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $task_locked;
        echo json_encode($return_arr);
        exit();
    }

    mysqli_query($mysqli, "DELETE FROM task_approvals WHERE approval_task_id = $task_id");
    mysqli_query($mysqli, "DELETE FROM tasks WHERE task_id = $task_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $name = escapeSql($task['task_name']);
        logAudit("Task", "Delete", "Deleted task $name via API ($api_key_name)", $client_id, $task_id);
        logAudit("API", "Success", "Deleted task $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
