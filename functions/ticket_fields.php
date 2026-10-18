<?php

/*
 * Ticket priority, assignee and billable - changing them, and drawing them.
 * Also a project's manager and a recurring ticket's assignee, which use the
 * same agent picker.
 *
 * The change functions are what the old Change priority / Assign ticket /
 * Billing modals' post handlers did (history, audit log, SLA, notifications,
 * custom actions), moved here for the inline pickers on the ticket list and
 * ticket page (js/quick_pick.js, via agent/ajax.php), which replaced those
 * modals. They assume the caller has checked module rights and client access.
 *
 * The draw functions are the one markup for each value, used by those pages
 * and by the ajax reply that redraws them after a change.
 */

const TICKET_PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

/* The badge colour for a priority - the same everywhere. */
function ticketPriorityColor($priority) {
    $colors = ['Urgent' => 'dark', 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info'];
    return $colors[$priority] ?? 'info';
}

/* The ticket row the change functions work from, or null. */
function ticketFieldsRow($ticket_id) {
    global $mysqli;

    $ticket_id = intval($ticket_id);
    return mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_assigned_to,
            ticket_status, ticket_billable, ticket_client_id, ticket_resolved_at, ticket_closed_at, ticket_status_name, client_name
        FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN clients ON client_id = ticket_client_id
        WHERE ticket_id = $ticket_id"
    )) ?: null;
}

/* Change the priority. Returns [ok, message]. */
function ticketChangePriority($ticket_id, $priority) {
    global $mysqli, $session_name;

    $ticket_id = intval($ticket_id);
    $row = ticketFieldsRow($ticket_id);
    if (!$row) {
        return [false, 'That ticket no longer exists.'];
    }
    if (!in_array($priority, TICKET_PRIORITIES, true)) {
        return [false, 'That is not a priority.'];
    }

    $original_priority = escapeSql($row['ticket_priority']);
    if ($row['ticket_priority'] === $priority) {
        return [true, "Priority is already <strong>$priority</strong>"];
    }

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_status = escapeSql($row['ticket_status_name']);
    $client_id = intval($row['ticket_client_id']);
    $priority_sql = escapeSql($priority);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$priority_sql' WHERE ticket_id = $ticket_id");
    applyTicketSla($ticket_id);

    mysqli_query($mysqli, "INSERT INTO ticket_history SET ticket_history_status = '$ticket_status', ticket_history_description = '$session_name changed priority from $original_priority to $priority_sql', ticket_history_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name changed priority from $original_priority to $priority_sql for ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    triggerCustomAction('ticket_update', $ticket_id);

    return [true, "Priority updated from <strong>$original_priority</strong> to <strong>$priority_sql</strong>"];
}

/*
 * Assign to an agent, or to no one with 0. A New ticket that gets an agent
 * becomes Open. Returns [ok, message].
 */
function ticketChangeAssignee($ticket_id, $assigned_to) {
    global $mysqli, $session_name, $session_user_id;

    $ticket_id = intval($ticket_id);
    $assigned_to = intval($assigned_to);

    $row = ticketFieldsRow($ticket_id);
    if (!$row || intval($row['ticket_status']) == 5) {
        return [false, 'Invalid ticket!'];
    }

    // New > Open as assigned
    $ticket_status = intval($row['ticket_status']);
    if ($ticket_status == 1 && $assigned_to !== 0) {
        $ticket_status = 2;
    }

    // Allow for un-assigning tickets
    if ($assigned_to == 0) {
        $ticket_reply = "Ticket unassigned.";
        $agent_name = "No One";
    } else {
        $agent_details = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = $assigned_to AND user_type = 1 AND user_archived_at IS NULL"));
        if (!$agent_details) {
            return [false, 'Invalid agent!'];
        }
        $agent_name = escapeSql($agent_details['user_name']);
        $ticket_reply = "Ticket re-assigned to $agent_name.";
    }

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $client_id = intval($row['ticket_client_id']);
    $client_uri = $client_id ? "&client_id=$client_id" : '';

    // Update ticket & insert reply
    mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $assigned_to, ticket_status = '$ticket_status' WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logTicketHistory($ticket_id, "$session_name assigned the ticket to $agent_name");

    logAudit("Ticket", "Edit", "$session_name reassigned $ticket_prefix$ticket_number to $agent_name", $client_id, $ticket_id);

    // Notification
    if ($session_user_id != $assigned_to && $assigned_to != 0) {

        // App Notification
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject has been assigned to you by $session_name', notification_action = '/agent/ticket.php?ticket_id=$ticket_id$client_uri', notification_client_id = $client_id, notification_user_id = $assigned_to");

        // Email Notification
        ticketMailAssigned($assigned_to, [$ticket_id], stripslashes($session_name));
    }

    triggerCustomAction('ticket_assign', $ticket_id);

    return [true, "Ticket <strong>$ticket_prefix$ticket_number</strong> assigned to <strong>$agent_name</strong>"];
}

/*
 * The statuses an agent can move an open ticket to by hand: the active ones
 * except New and Closed (the system sets those) and Resolved (the Resolve
 * button does that, because it also emails the client).
 */
function ticketPickableStatuses() {
    global $mysqli;

    $statuses = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name, ticket_status_color FROM ticket_statuses
        WHERE ticket_status_id NOT IN (1, 4, 5) AND ticket_status_active = 1
        ORDER BY ticket_status_order, ticket_status_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $statuses[intval($row['ticket_status_id'])] = $row;
    }
    return $statuses;
}

/*
 * Change the status of an open ticket - what the ticket page's status menu
 * did through the reply form's status-only path. Returns [ok, message].
 */
function ticketChangeStatus($ticket_id, $status_id) {
    global $mysqli, $session_name;

    $ticket_id = intval($ticket_id);
    $status_id = intval($status_id);

    $row = ticketFieldsRow($ticket_id);
    if (!$row || $row['ticket_closed_at'] || $row['ticket_resolved_at']) {
        return [false, 'Invalid ticket!'];
    }
    $statuses = ticketPickableStatuses();
    if (!isset($statuses[$status_id])) {
        return [false, 'That status cannot be set here.'];
    }
    if (intval($row['ticket_status']) === $status_id) {
        return [true, "Status is already <strong>" . escapeHtml($statuses[$status_id]['ticket_status_name']) . "</strong>"];
    }

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $client_id = intval($row['ticket_client_id']);
    $status_name = escapeSql($statuses[$status_id]['ticket_status_name']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $status_id, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);

    logTicketHistory($ticket_id, "$session_name set the status to $status_name");

    logAudit("Ticket", "Edit", "$session_name set the status of $ticket_prefix$ticket_number to $status_name", $client_id, $ticket_id);

    triggerCustomAction('ticket_update', $ticket_id);

    return [true, "Status set to <strong>$status_name</strong>"];
}

/* Mark billable (1) or not (0). Returns [ok, message]. */
function ticketChangeBillable($ticket_id, $billable) {
    global $mysqli, $session_name;

    $ticket_id = intval($ticket_id);
    $billable = intval($billable) ? 1 : 0;

    $row = ticketFieldsRow($ticket_id);
    if (!$row) {
        return [false, 'That ticket no longer exists.'];
    }

    $billable_wording = $billable ? '' : 'Not';
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $client_id = intval($row['ticket_client_id']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_billable = $billable WHERE ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name marked ticket $ticket_prefix$ticket_number as $billable_wording Billable", $client_id, $ticket_id);

    return [true, "Ticket marked <strong>$billable_wording Billable</strong>"];
}

/*
 * What each value looks like. $style is 'list' (the ticket list's padded
 * pills) or 'detail' (the ticket page's properties panel).
 */
function ticketPriorityHtml($priority, $style = 'list') {
    $priority = in_array($priority, TICKET_PRIORITIES, true) ? $priority : 'Low';
    return '<span class="badge rounded-pill text-bg-' . ticketPriorityColor($priority) . ($style === 'list' ? ' p-2' : '') . '">' . $priority . '</span>';
}

function ticketBillableHtml($billable, $style = 'list') {
    if ($style === 'detail') {
        return $billable ? '<span>Billable</span>' : "<span class='text-muted'>Not billable</span>";
    }
    return $billable
        ? '<span class="badge rounded-pill bg-success p-2"><i class="fas fa-fw fa-dollar-sign"></i></span>'
        : '<span class="badge rounded-pill bg-secondary p-2"><i class="fas fa-fw fa-minus"></i></span>';
}

function ticketStatusHtml($status_name, $status_color, $style = 'list') {
    $color = preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,24})$/i', (string) $status_color) ? $status_color : 'var(--bs-secondary)';
    return '<span class="badge rounded-pill text-light' . ($style === 'list' ? ' p-2' : '') . '" style="background-color: ' . $color . '">' . escapeHtml($status_name) . '</span>';
}

/*
 * The button that opens a quick picker (js/quick_pick.js) for one record's
 * field, wrapped round how the value looks now. $id is the ticket, project,
 * recurring ticket or asset, depending on the field. $scope is the client
 * whose choices it offers, for fields where those are per client (an asset's
 * location and contact).
 */
function quickPick($field, $id, $current, $inner, $style = 'list', $status_id = 0, $scope = 0) {
    $labels = [
        'priority' => 'Change priority', 'assignee' => 'Change who it is assigned to', 'billable' => 'Change billing', 'status' => 'Change status',
        'project_manager' => 'Change the project manager', 'recurring_assignee' => 'Change who it is assigned to',
        'asset_status' => 'Change status', 'asset_location' => 'Change location', 'asset_contact' => 'Change who it is assigned to',
        'contact_location' => 'Change location', 'service_importance' => 'Change importance', 'network_type' => 'Change type'
    ];
    // On the ticket page it looks like the other editable properties there (agent/css/ticket.css)
    $class = 'itflow-quick-pick itflow-quick-pick-' . $style . ($style === 'detail' ? ' ticket-prop-edit' : '');
    return '<button type="button" class="' . $class . '" data-quick-pick="' . $field . '" data-quick-id="' . intval($id) . '"'
        . ' data-quick-current="' . escapeHtml($current) . '" data-quick-style="' . $style . '"' . ($status_id ? ' data-status-id="' . intval($status_id) . '"' : '') . ($scope ? ' data-quick-scope="' . intval($scope) . '"' : '') . ' data-csrf="' . escapeHtml($_SESSION['csrf_token'] ?? '') . '"'
        . ' title="' . $labels[$field] . '" aria-haspopup="listbox" aria-expanded="false">'
        . '<span class="itflow-quick-pick-value">' . $inner . '</span>'
        . ($style === 'detail' ? '<i class="fas fa-chevron-down ticket-prop-edit-icon" aria-hidden="true"></i>' : '')
        . '</button>';
}

/* The agents a picker offers: none first, then me, then everyone else by name. */
function quickPickAgents($none_label = 'Unassigned') {
    global $mysqli, $session_user_id;

    $options = [['value' => '0', 'label' => $none_label, 'icon' => 'fas fa-user-slash', 'none' => true]];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $user_id = intval($row['user_id']);
        $option = ['value' => (string) $user_id, 'label' => $row['user_name'] . ($user_id == $session_user_id ? ' (me)' : '')];
        if ($row['user_avatar']) {
            $option['image'] = "/uploads/users/$user_id/" . rawurlencode($row['user_avatar']);
        } else {
            $option['initials'] = personInitials($row['user_name']);
        }
        if ($user_id == $session_user_id) {
            array_splice($options, 1, 0, [$option]);
        } else {
            $options[] = $option;
        }
    }

    return $options;
}

/* An active agent's name and email, or null. */
function quickPickAgent($user_id) {
    global $mysqli;
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = " . intval($user_id) . " AND user_type = 1 AND user_archived_at IS NULL")) ?: null;
}

/*
 * A project's manager - what Edit Project did with just that field. The
 * caller checks the project is open and the agent may reach its client.
 */
function projectChangeManager($project_id, $manager_id) {
    global $mysqli, $session_name;

    $project_id = intval($project_id);
    $manager_id = intval($manager_id);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name, project_client_id, project_manager FROM projects WHERE project_id = $project_id"));
    if (!$row) {
        return [false, 'Invalid project!'];
    }
    if ($manager_id) {
        $agent = quickPickAgent($manager_id);
        if (!$agent) {
            return [false, 'Invalid agent!'];
        }
        $manager_name = escapeSql($agent['user_name']);
    } else {
        $manager_name = 'No One';
    }

    $project = escapeSql($row['project_prefix'] . $row['project_number'] . ' - ' . $row['project_name']);

    mysqli_query($mysqli, "UPDATE projects SET project_manager = $manager_id WHERE project_id = $project_id");

    logAudit("Project", "Edit", "$session_name set the manager of project $project to $manager_name", intval($row['project_client_id']), $project_id);

    // The new manager hears about it
    if ($manager_id !== intval($row['project_manager'])) {
        projectNotifyManager($row, $manager_id);
    }

    return [true, $manager_id ? "Project manager set to $manager_name" : 'Project manager removed'];
}

/*
 * Who a recurring ticket's tickets go to - what Bulk Assign did for one
 * recurring ticket, notification and email included. The caller checks
 * client access.
 */
function recurringTicketChangeAssignee($recurring_ticket_id, $assigned_to) {
    global $mysqli, $session_name, $session_user_id;

    $recurring_ticket_id = intval($recurring_ticket_id);
    $assigned_to = intval($assigned_to);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_ticket_subject, recurring_ticket_client_id FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id"));
    if (!$row) {
        return [false, 'Invalid recurring ticket!'];
    }
    if ($assigned_to) {
        $agent = quickPickAgent($assigned_to);
        if (!$agent) {
            return [false, 'Invalid agent!'];
        }
        $agent_name = escapeSql($agent['user_name']);
    } else {
        $agent_name = 'No One';
    }

    $subject = escapeSql($row['recurring_ticket_subject']);
    $client_id = intval($row['recurring_ticket_client_id']);

    mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_assigned_to = $assigned_to WHERE recurring_ticket_id = $recurring_ticket_id");

    logAudit("Recurring Ticket", "Edit", "$session_name reassigned recurring ticket $subject to $agent_name", $client_id, $recurring_ticket_id);

    if ($assigned_to && $assigned_to != $session_user_id) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Recurring Ticket', notification = 'Recurring ticket $subject has been assigned to you by $session_name', notification_action = 'recurring_tickets.php?assigned=$assigned_to', notification_client_id = $client_id, notification_user_id = $assigned_to");

        recurringTicketMailAssigned($assigned_to, [(string) $row['recurring_ticket_subject']], stripslashes($session_name));
    }

    return [true, $assigned_to ? "Recurring ticket assigned to $agent_name" : 'Recurring ticket unassigned'];
}
