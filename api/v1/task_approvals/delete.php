<?php
/*
 * API - Task Approvals - Delete
 * POST /api/v1/task_approvals/delete.php
 *
 * Take an approval off a task, as Delete does on it - whatever its status.
 *
 * Parameters (POST, JSON body): api_key, approval_id, client_id (the ticket's client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

$approval_id = intval($_POST['approval_id'] ?? 0);
$approval_task_id = $approval_id ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT approval_task_id FROM task_approvals WHERE approval_id = $approval_id"))[0] ?? 0) : 0;

$_POST['task_id'] = $approval_task_id;
require_once '../tasks/task_find.php';

if ($approval_task_id && $task) {

    mysqli_query($mysqli, "DELETE FROM task_approvals WHERE approval_id = $approval_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $name = escapeSql($task['task_name']);
        logAudit("Task", "Delete", "Deleted the approval request on task $name via API ($api_key_name)", $client_id, intval($task['task_id']));
        logAudit("API", "Success", "Deleted the approval request on task $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
