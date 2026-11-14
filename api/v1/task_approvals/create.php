<?php
/*
 * API - Task Approvals - Create
 * POST /api/v1/task_approvals/create.php
 *
 * Ask for a task to be approved before it is done, as Require approval does on
 * it. From the client - the ticket's contact (approval_type any), or the
 * client's technical or billing contacts - by email, with a link to approve or
 * decline it. Or from your team - any other agent (any), or one agent
 * (specific, with approval_required_user_id), told in ITFlow and by email. Not
 * on a task that is done, nor once the ticket is resolved or closed.
 *
 * Parameters (POST, JSON body):
 *   api_key                    required - Your API key
 *   task_id                    required - The task
 *   client_id                  the ticket's client - needed when the key's user is limited to certain clients
 *   approval_scope             required - client or internal
 *   approval_type              optional - client: any (the default), technical or billing; internal: any (the default) or specific
 *   approval_required_user_id  with specific - the agent who has to approve it
 *
 * Answer: insert_id, and emailed - how many it was emailed to.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$insert_id = false;

require_once '../tasks/task_find.php';

if ($task) {

    $scope = (string) ($_POST['approval_scope'] ?? '');
    $type = (string) ($_POST['approval_type'] ?? 'any');
    $types = ['client' => ['any', 'technical', 'billing'], 'internal' => ['any', 'specific']];
    $required_user_id = 0;

    $refuse = $task_locked;
    if ($refuse === '' && !empty($task['task_completed_at'])) {
        $refuse = 'Task ' . $task['task_name'] . ' is done already.';
    } elseif ($refuse === '' && !isset($types[$scope])) {
        $refuse = 'approval_scope is who approves it - client or internal.';
    } elseif ($refuse === '' && !in_array($type, $types[$scope], true)) {
        $refuse = "approval_type for $scope is one of: " . implode(', ', $types[$scope]) . '.';
    } elseif ($refuse === '' && $type === 'specific') {
        $required_user_id = ticketAgentId($_POST['approval_required_user_id'] ?? 0);
        if (!$required_user_id) {
            $refuse = 'approval_required_user_id is the agent who has to approve it - an active one, see technicians/read.php.';
        }
    } elseif ($refuse === '' && $scope === 'client' && !$client_id) {
        $refuse = "An internal ticket has no client to approve it - use approval_scope internal.";
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    $url_key = randomString(32);
    mysqli_query($mysqli, "INSERT INTO task_approvals SET approval_scope = '$scope', approval_type = '$type', approval_required_user_id = " . ($required_user_id ?: 'NULL') . ",
        approval_status = 'pending', approval_created_by = $session_user_id, approval_url_key = '$url_key', approval_task_id = $task_id");
    $approval_id = intval(mysqli_insert_id($mysqli));

    if ($approval_id) {
        $insert_id = $approval_id;

        $task_name = escapeSql($task['task_name']);
        $ticket_id = intval($task['ticket_id']);
        $by = html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8');
        $by = stripslashes($session_name) . " (the API, $by)";

        // The agent named hears in the app too
        if ($scope === 'internal' && $type === 'specific' && $required_user_id !== $session_user_id) {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '" . escapeSql("$by needs your approval for ticket " . ticketReference($task) . " task " . $task['task_name']) . "',
                notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = 0, notification_user_id = $required_user_id");
        }

        // Who is asked, by email (functions/ticket.php)
        $return_arr['emailed'] = intval(ticketMailTaskApproval($approval_id, $by));

        logAudit("Task", "Edit", "Added a task approver for $task_name via API ($api_key_name)", $client_id, $task_id);
        logAudit("API", "Success", "Asked for approval of task $task_name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';
