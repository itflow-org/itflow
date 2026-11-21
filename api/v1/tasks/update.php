<?php
/*
 * API - Tasks - Update
 * POST /api/v1/tasks/update.php
 *
 * Change a task, as Edit does on it - its name, its place in the list and the
 * minutes it should take, only what is sent. Not once the ticket is resolved or closed.
 *
 * Parameters (POST, JSON body):
 *   api_key                   required - Your API key
 *   task_id                   required - The task
 *   client_id                 the ticket's client - needed when the key's user is limited to certain clients
 *   task_name, task_order, task_completion_estimate   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'task_find.php';

if ($task) {

    $set = [];
    $refuse = $task_locked;
    if (array_key_exists('task_name', $_POST)) {
        $task_name = mb_substr(trim((string) (is_scalar($_POST['task_name']) ? $_POST['task_name'] : '')), 0, 255);
        if ($task_name === '') {
            $refuse = $refuse ?: 'A task needs a name - task_name.';
        }
        $set[] = "task_name = '" . escapeSql($task_name) . "'";
    }
    if (array_key_exists('task_order', $_POST)) {
        $set[] = "task_order = " . intval($_POST['task_order']);
    }
    if (array_key_exists('task_completion_estimate', $_POST)) {
        $set[] = "task_completion_estimate = " . max(0, intval($_POST['task_completion_estimate']));
    }
    if ($refuse === '' && !$set) {
        $refuse = 'Send at least one of task_name, task_order or task_completion_estimate.';
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    if (mysqli_query($mysqli, "UPDATE tasks SET " . implode(', ', $set) . " WHERE task_id = $task_id")) {
        $update_count = 1;
        $name = escapeSql((string) getFieldById('tasks', $task_id, 'task_name'));
        logAudit("Task", "Edit", "Edited task $name via API ($api_key_name)", $client_id, $task_id);
        logAudit("API", "Success", "Edited task $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
