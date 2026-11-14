<?php

// The task a request names (task_id), with its ticket - of a client the key's user
// can reach, and client_id's when it is sent - as $task, or null.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$task_id = intval($_POST['task_id'] ?? 0);
$task = $task_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tasks.*, ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_resolved_at, ticket_closed_at
    FROM tasks JOIN tickets ON ticket_id = task_ticket_id
    WHERE task_id = $task_id " . clientScopeSql('ticket_client_id') . ($client_id ? " AND ticket_client_id = $client_id" : '') . " LIMIT 1")) : null;

// A ticket that is resolved or closed keeps its tasks as they are, as on its page - $task_locked says why
$task_locked = '';
if ($task) {
    $client_id = intval($task['ticket_client_id']);
    if (!empty($task['ticket_closed_at'])) {
        $task_locked = 'Ticket ' . ticketReference($task) . ' is closed.';
    } elseif (!empty($task['ticket_resolved_at'])) {
        $task_locked = 'Ticket ' . ticketReference($task) . ' is resolved - reopen it to change its tasks.';
    }
}
