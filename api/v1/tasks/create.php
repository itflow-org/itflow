<?php
/*
 * API - Tasks - Create
 * POST /api/v1/tasks/create.php
 *
 * Add a task to a ticket, at the end of its list, as Add a task does on the
 * ticket. Not once the ticket is resolved or closed.
 *
 * Parameters (POST, JSON body):
 *   api_key                   required - Your API key
 *   ticket_id                 required - The ticket
 *   client_id                 the ticket's client - needed when the key's user is limited to certain clients
 *   task_name                 required - Up to 255 characters
 *   task_completion_estimate  optional - Minutes it should take
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$ticket_id = intval($_POST['ticket_id'] ?? 0);

$insert_id = false;

$ticket = $ticket_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_client_id, ticket_resolved_at, ticket_closed_at FROM tickets
    WHERE ticket_id = $ticket_id " . clientScopeSql('ticket_client_id') . ($client_id ? " AND ticket_client_id = $client_id" : '') . " LIMIT 1")) : null;

if ($ticket) {

    $client_id = intval($ticket['ticket_client_id']);
    $task_name = mb_substr(trim((string) (is_scalar($_POST['task_name'] ?? '') ? ($_POST['task_name'] ?? '') : '')), 0, 255);
    $estimate = max(0, intval($_POST['task_completion_estimate'] ?? 0));

    $refuse = '';
    if (!empty($ticket['ticket_closed_at']) || !empty($ticket['ticket_resolved_at'])) {
        $refuse = 'Ticket ' . ticketReference($ticket) . ' is ' . (!empty($ticket['ticket_closed_at']) ? 'closed.' : 'resolved - reopen it to add tasks.');
    } elseif ($task_name === '') {
        $refuse = 'A task needs a name - task_name.';
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    // At the end of the list
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(task_order), 0) + 1 FROM tasks WHERE task_ticket_id = $ticket_id"))[0]);
    $name_sql = escapeSql($task_name);
    mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$name_sql', task_order = $order, task_completion_estimate = $estimate, task_ticket_id = $ticket_id");
    $task_id = intval(mysqli_insert_id($mysqli));

    if ($task_id) {
        $insert_id = $task_id;
        $reference = escapeSql(ticketReference($ticket));
        logAudit("Task", "Create", "Created task $name_sql on ticket $reference via API ($api_key_name)", $client_id, $task_id);
        logAudit("API", "Success", "Created task $name_sql on ticket $reference via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';
