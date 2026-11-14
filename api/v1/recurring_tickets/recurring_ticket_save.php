<?php

// After a recurring ticket is written: what the model gathered that lives beside
// it - its other assets, its tasks and its template's answers - and the agent it
// is newly assigned to told, in ITFlow and by email. $recurring_ticket_id is it,
// $rt_notify_agent the agent to tell (0: nobody) and $rt_subject its subject (raw).

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

if ($rt_assets !== null) {
    mysqli_query($mysqli, "DELETE FROM recurring_ticket_assets WHERE recurring_ticket_id = $recurring_ticket_id");
    foreach ($rt_assets as $asset_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO recurring_ticket_assets SET recurring_ticket_id = $recurring_ticket_id, asset_id = " . intval($asset_id));
    }
}

if ($rt_tasks !== null) {
    mysqli_query($mysqli, "DELETE FROM recurring_ticket_tasks WHERE recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id");
    foreach ($rt_tasks as $order => [$name, $estimate]) {
        mysqli_query($mysqli, "INSERT INTO recurring_ticket_tasks SET recurring_ticket_task_name = '" . escapeSql($name) . "', recurring_ticket_task_order = $order,
            recurring_ticket_task_completion_estimate = " . intval($estimate) . ", recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id");
    }
}

if ($rt_fill_ins !== null) {
    ticketFillInsStore('recurring_ticket', $recurring_ticket_id, $rt_fill_ins);
}

// Its agent hears, not the key's own
if ($rt_notify_agent && $rt_notify_agent !== intval($session_user_id)) {
    $by = html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8');
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Recurring Ticket', notification = '" . escapeSql("Recurring ticket $rt_subject has been assigned to you by the API ($by)") . "',
        notification_action = 'recurring_tickets.php?assigned_agent=$rt_notify_agent', notification_user_id = $rt_notify_agent");
    recurringTicketMailAssigned($rt_notify_agent, [$rt_subject], "The API ($by)");
}
