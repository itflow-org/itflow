<?php

/*
 * ITFlow - GET/POST request handler for recurring tickets
 *
 * Every handler loads the recurring ticket through recurringTicketForRequest()
 * or recurringTicketsForBulk() (functions/ticket.php) - it exists, the agent
 * has the support module at the level asked and may reach its client. What a
 * schedule links to (contact, asset, other assets) must be its client's own;
 * priority, frequency, dates, agent, category and template are checked
 * against what exists. Raising a ticket - by hand or by the nightly cron - is
 * recurringTicketRaise() / recurringTicketRunNow(), one copy for both.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/**
 * The schedule fields from New / Edit Recurring Ticket, checked, as SQL for
 * a SET clause - or back to the form with what is wrong. $current is the
 * recurring ticket being edited (null for a new one).
 */
function recurringTicketFromPost($client_id, $current = null) {
    global $mysqli;

    $subject = trim((string) ($_POST['subject'] ?? ''));
    if ($subject === '') {
        flashAlert('A recurring ticket needs a subject.', 'error');
        redirect();
    }
    $frequency = recurringTicketFrequency($_POST['frequency'] ?? '');
    if (!$frequency) {
        flashAlert('Pick how often it runs.', 'error');
        redirect();
    }

    $template_id = intval($_POST['ticket_template_id'] ?? 0);
    if ($template_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM ticket_templates WHERE ticket_template_id = $template_id"))) {
        $template_id = 0;
    }
    // Billing is the sales module's to change - without it a schedule keeps what it has
    $billable = lookupUserPermission('module_sales') >= 2 ? (intval($_POST['billable'] ?? 0) ? 1 : 0) : intval($current['recurring_ticket_billable'] ?? 0);

    return [
        'subject' => escapeSql(mb_substr($subject, 0, 500)),
        'details' => mysqli_real_escape_string($mysqli, (string) ($_POST['details'] ?? '')),
        'priority' => escapeSql(ticketPriority($_POST['priority'] ?? 'Medium')),
        'frequency' => $frequency,
        'billable' => $billable,
        'assigned_to' => ticketAgentId($_POST['assigned_to'] ?? 0),
        'contact_id' => ticketClientOwnedId('contact', $_POST['contact_id'] ?? 0, $client_id),
        'asset_id' => ticketClientOwnedId('asset', $_POST['asset_id'] ?? 0, $client_id),
        'category_id' => ticketCategoryId($_POST['category_id'] ?? 0),
        'template_id' => $template_id,
    ];
}

/** Replace a schedule's other assets with these (already checked) ids. */
function recurringTicketSetOtherAssets($recurring_ticket_id, array $asset_ids) {
    global $mysqli;
    $recurring_ticket_id = intval($recurring_ticket_id);
    mysqli_query($mysqli, "DELETE FROM recurring_ticket_assets WHERE recurring_ticket_id = $recurring_ticket_id");
    foreach ($asset_ids as $asset_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO recurring_ticket_assets SET recurring_ticket_id = $recurring_ticket_id, asset_id = " . intval($asset_id));
    }
}

/** Replace a schedule's tasks with the ones the form posted. */
function recurringTicketSetTasks($recurring_ticket_id) {
    global $mysqli;
    $recurring_ticket_id = intval($recurring_ticket_id);
    mysqli_query($mysqli, "DELETE FROM recurring_ticket_tasks WHERE recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id");
    foreach (parseSubmittedTasks() as $task) {
        mysqli_query($mysqli, "INSERT INTO recurring_ticket_tasks SET recurring_ticket_task_name = '{$task['name']}', recurring_ticket_task_order = {$task['order']}, recurring_ticket_task_completion_estimate = {$task['estimate']}, recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id");
    }
}

/** Tell an agent some recurring tickets are theirs now - not themselves, not nobody. $subjects are raw. */
function recurringTicketNotifyAgent($user_id, array $subjects) {
    global $mysqli, $session_user_id, $session_name;
    $user_id = intval($user_id);
    if (!$user_id || $user_id == $session_user_id || !$subjects) {
        return;
    }
    $count = count($subjects);
    $what = $count === 1 ? 'Recurring ticket ' . escapeSql($subjects[0]) . ' has' : "$count recurring tickets have";
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Recurring Ticket', notification = '$what been assigned to you by " . escapeSql($session_name) . "', notification_action = 'recurring_tickets.php?assigned_agent=$user_id', notification_user_id = $user_id");

    recurringTicketMailAssigned($user_id, $subjects, stripslashes($session_name));
}

if (isset($_POST['add_recurring_ticket'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id"))) {
        flashAlert('Pick the client it is for.', 'error');
        redirect();
    }
    enforceClientAccess();

    $start_date = recurringTicketDate($_POST['start_date'] ?? '');
    if (!$start_date || $start_date < date('Y-m-d')) {
        flashAlert('Pick when it starts - today or later.', 'error');
        redirect();
    }

    $f = recurringTicketFromPost($client_id);

    mysqli_query($mysqli, "INSERT INTO recurring_tickets SET recurring_ticket_subject = '{$f['subject']}', recurring_ticket_details = '{$f['details']}',
        recurring_ticket_priority = '{$f['priority']}', recurring_ticket_frequency = '{$f['frequency']}', recurring_ticket_billable = {$f['billable']},
        recurring_ticket_start_date = '$start_date', recurring_ticket_next_run = '$start_date', recurring_ticket_assigned_to = {$f['assigned_to']},
        recurring_ticket_created_by = $session_user_id, recurring_ticket_client_id = $client_id, recurring_ticket_contact_id = {$f['contact_id']},
        recurring_ticket_asset_id = {$f['asset_id']}, recurring_ticket_category = {$f['category_id']}, recurring_ticket_ticket_template_id = {$f['template_id']}");

    $recurring_ticket_id = mysqli_insert_id($mysqli);

    recurringTicketSetOtherAssets($recurring_ticket_id, ticketClientOwnedAssetIds($_POST['additional_assets'] ?? [], $client_id, $f['asset_id']));
    recurringTicketSetTasks($recurring_ticket_id);

    if ($f['assigned_to']) {
        recurringTicketNotifyAgent($f['assigned_to'], [trim($_POST['subject'])]);
    }

    logAudit("Recurring Ticket", "Create", "$session_name created recurring ticket {$f['subject']} - {$f['frequency']}", $client_id, $recurring_ticket_id);

    flashAlert("Recurring ticket <strong>" . escapeHtml(trim($_POST['subject'])) . "</strong> created - first run " . escapeHtml(date('M j, Y', strtotime($start_date))));

    redirect();

}

if (isset($_POST['edit_recurring_ticket'])) {

    validateCSRFToken();

    $row = recurringTicketForRequest($_POST['recurring_ticket_id'] ?? 0, 2);
    $recurring_ticket_id = intval($row['recurring_ticket_id']);

    // The next run - a date in the past only when it was already (a missed run stays missed until moved)
    $next_run = recurringTicketDate($_POST['next_date'] ?? '');
    $current_next_run = substr((string) $row['recurring_ticket_next_run'], 0, 10);
    if (!$next_run || ($next_run < date('Y-m-d') && $next_run !== $current_next_run)) {
        flashAlert('Pick when it runs next - today or later.', 'error');
        redirect();
    }

    $f = recurringTicketFromPost($client_id, $row);

    // A run moved by hand is the new anchor - monthly ones keep to its day of the month from here
    $anchor_sql = $next_run !== $current_next_run ? ", recurring_ticket_start_date = '$next_run'" : '';

    mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_subject = '{$f['subject']}', recurring_ticket_details = '{$f['details']}',
        recurring_ticket_priority = '{$f['priority']}', recurring_ticket_frequency = '{$f['frequency']}', recurring_ticket_billable = {$f['billable']},
        recurring_ticket_next_run = '$next_run' $anchor_sql, recurring_ticket_assigned_to = {$f['assigned_to']}, recurring_ticket_asset_id = {$f['asset_id']},
        recurring_ticket_contact_id = {$f['contact_id']}, recurring_ticket_category = {$f['category_id']}, recurring_ticket_ticket_template_id = {$f['template_id']}
        WHERE recurring_ticket_id = $recurring_ticket_id");

    // The other assets go with the asset field - an empty multi-select posts nothing
    if (isset($_POST['asset_id'])) {
        recurringTicketSetOtherAssets($recurring_ticket_id, ticketClientOwnedAssetIds($_POST['additional_assets'] ?? [], $client_id, $f['asset_id']));
    }
    if (isset($_POST['tasks_submitted'])) {
        recurringTicketSetTasks($recurring_ticket_id);
    }
    if ($f['assigned_to'] && $f['assigned_to'] !== intval($row['recurring_ticket_assigned_to'])) {
        recurringTicketNotifyAgent($f['assigned_to'], [trim($_POST['subject'])]);
    }

    triggerCustomAction('recurring_ticket_update', $recurring_ticket_id);

    logAudit("Recurring Ticket", "Edit", "$session_name edited recurring ticket {$f['subject']}", $client_id, $recurring_ticket_id);

    flashAlert("Recurring ticket <strong>" . escapeHtml(trim($_POST['subject'])) . "</strong> updated");

    redirect();

}

// Raise it now - its next run moves on, so this one takes that one's place
if (isset($_GET['force_recurring_ticket'])) {

    validateCSRFToken();

    $row = recurringTicketForRequest($_GET['force_recurring_ticket'], 2);

    $ticket_id = recurringTicketRunNow($row, $session_name);
    if (!$ticket_id) {
        flashAlert("The ticket could not be raised.", 'error');
        redirect();
    }

    $ticket = ticketRow($ticket_id);
    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($ticket)) . "</strong> raised from <strong>" . escapeHtml($row['recurring_ticket_subject']) . "</strong>");

    redirect();

}

if (isset($_POST['bulk_force_recurring_tickets'])) {

    validateCSRFToken();

    $count = 0;
    foreach (recurringTicketsForBulk(2) as $row) {
        if (recurringTicketRunNow($row, $session_name)) {
            $count++;
        }
    }

    flashAlert("Raised <strong>$count</strong> ticket" . ($count === 1 ? '' : 's'));

    redirect();

}

// Pause - kept, raising nothing until resumed
if (isset($_GET['pause_recurring_ticket']) || isset($_GET['resume_recurring_ticket'])) {

    validateCSRFToken();

    $pause = isset($_GET['pause_recurring_ticket']);
    $row = recurringTicketForRequest($pause ? $_GET['pause_recurring_ticket'] : $_GET['resume_recurring_ticket'], 2);
    $recurring_ticket_id = intval($row['recurring_ticket_id']);
    $subject = escapeSql($row['recurring_ticket_subject']);

    if ($pause) {
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_paused_at = COALESCE(recurring_ticket_paused_at, NOW()) WHERE recurring_ticket_id = $recurring_ticket_id");
    } else {
        // Runs it missed while paused are not raised now - it picks up from today
        $next_run = substr((string) $row['recurring_ticket_next_run'], 0, 10);
        if ($next_run < date('Y-m-d')) {
            $next_run = recurringTicketNextRun($next_run, $row['recurring_ticket_frequency'], $row['recurring_ticket_start_date'], date('Y-m-d', strtotime('-1 day')));
        }
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_paused_at = NULL, recurring_ticket_next_run = '$next_run' WHERE recurring_ticket_id = $recurring_ticket_id");
    }

    logAudit("Recurring Ticket", "Edit", "$session_name " . ($pause ? 'paused' : 'resumed') . " recurring ticket $subject", $client_id, $recurring_ticket_id);

    flashAlert("Recurring ticket <strong>" . escapeHtml($row['recurring_ticket_subject']) . "</strong> " . ($pause ? 'paused' : 'resumed'));

    redirect();

}

if (isset($_POST['bulk_pause_recurring_tickets']) || isset($_POST['bulk_resume_recurring_tickets'])) {

    validateCSRFToken();

    $pause = isset($_POST['bulk_pause_recurring_tickets']);
    $count = 0;
    foreach (recurringTicketsForBulk(2) as $recurring_ticket_id => $row) {
        if ($pause === recurringTicketPaused($row)) {
            continue;
        }
        if ($pause) {
            mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_paused_at = NOW() WHERE recurring_ticket_id = $recurring_ticket_id");
        } else {
            $next_run = substr((string) $row['recurring_ticket_next_run'], 0, 10);
            if ($next_run < date('Y-m-d')) {
                $next_run = recurringTicketNextRun($next_run, $row['recurring_ticket_frequency'], $row['recurring_ticket_start_date'], date('Y-m-d', strtotime('-1 day')));
            }
            mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_paused_at = NULL, recurring_ticket_next_run = '$next_run' WHERE recurring_ticket_id = $recurring_ticket_id");
        }
        logAudit("Recurring Ticket", "Edit", "$session_name " . ($pause ? 'paused' : 'resumed') . " recurring ticket " . escapeSql($row['recurring_ticket_subject']), intval($row['recurring_ticket_client_id']), $recurring_ticket_id);
        $count++;
    }

    flashAlert(($pause ? 'Paused' : 'Resumed') . " <strong>$count</strong> recurring ticket" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_GET['delete_recurring_ticket'])) {

    validateCSRFToken();

    $row = recurringTicketForRequest($_GET['delete_recurring_ticket'], 3);
    $recurring_ticket_id = intval($row['recurring_ticket_id']);
    $subject = escapeSql($row['recurring_ticket_subject']);

    // Its tasks and other assets go with it (ON DELETE CASCADE); tickets it raised stay
    mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");

    logAudit("Recurring Ticket", "Delete", "$session_name deleted recurring ticket $subject", $client_id, $recurring_ticket_id);

    flashAlert("Recurring ticket <strong>" . escapeHtml($row['recurring_ticket_subject']) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_delete_recurring_tickets'])) {

    validateCSRFToken();

    $count = 0;
    foreach (recurringTicketsForBulk(3) as $recurring_ticket_id => $row) {
        mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");
        logAudit("Recurring Ticket", "Delete", "$session_name deleted recurring ticket " . escapeSql($row['recurring_ticket_subject']), intval($row['recurring_ticket_client_id']), $recurring_ticket_id);
        $count++;
    }

    logAudit("Recurring Ticket", "Bulk Delete", "$session_name deleted $count recurring ticket(s)");

    flashAlert("Deleted <strong>$count</strong> recurring ticket" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_assign_recurring_ticket'])) {

    validateCSRFToken();

    $assign_to = ticketAgentId($_POST['assign_to'] ?? 0);
    if (intval($_POST['assign_to'] ?? 0) && !$assign_to) {
        flashAlert('Pick an active agent.', 'error');
        redirect();
    }
    $agent_name = $assign_to ? (string) getFieldById('users', $assign_to, 'user_name') : 'No one';

    $count = 0;
    $subjects = [];
    foreach (recurringTicketsForBulk(2) as $recurring_ticket_id => $row) {
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_assigned_to = $assign_to WHERE recurring_ticket_id = $recurring_ticket_id");
        logAudit("Recurring Ticket", "Edit", "$session_name assigned recurring ticket " . escapeSql($row['recurring_ticket_subject']) . " to " . escapeSql($agent_name), intval($row['recurring_ticket_client_id']), $recurring_ticket_id);
        triggerCustomAction('recurring_ticket_update', $recurring_ticket_id);
        if (intval($row['recurring_ticket_assigned_to']) !== $assign_to) {
            $subjects[] = $row['recurring_ticket_subject'];
        }
        $count++;
    }

    recurringTicketNotifyAgent($assign_to, $subjects);

    flashAlert("Assigned <strong>$count</strong> recurring ticket" . ($count === 1 ? '' : 's') . " to <strong>" . escapeHtml($agent_name) . "</strong>");

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_priority'])) {

    validateCSRFToken();

    if (!in_array($_POST['bulk_priority'] ?? '', TICKET_PRIORITIES, true)) {
        flashAlert('Pick a priority.', 'error');
        redirect();
    }
    $priority = $_POST['bulk_priority'];

    $count = 0;
    foreach (recurringTicketsForBulk(2) as $recurring_ticket_id => $row) {
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_priority = '$priority' WHERE recurring_ticket_id = $recurring_ticket_id");
        logAudit("Recurring Ticket", "Edit", "$session_name changed the priority of recurring ticket " . escapeSql($row['recurring_ticket_subject']) . " from " . escapeSql($row['recurring_ticket_priority']) . " to $priority", intval($row['recurring_ticket_client_id']), $recurring_ticket_id);
        triggerCustomAction('recurring_ticket_update', $recurring_ticket_id);
        $count++;
    }

    flashAlert("Priority set to <strong>$priority</strong> on <strong>$count</strong> recurring ticket" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_category'])) {

    validateCSRFToken();

    $category_id = ticketCategoryId($_POST['bulk_category'] ?? 0);
    $category_name = $category_id ? (string) getFieldById('categories', $category_id, 'category_name') : 'Not categorized';

    $count = 0;
    foreach (recurringTicketsForBulk(2) as $recurring_ticket_id => $row) {
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_category = $category_id WHERE recurring_ticket_id = $recurring_ticket_id");
        logAudit("Recurring Ticket", "Edit", "$session_name set the category of recurring ticket " . escapeSql($row['recurring_ticket_subject']) . " to " . escapeSql($category_name), intval($row['recurring_ticket_client_id']), $recurring_ticket_id);
        triggerCustomAction('recurring_ticket_update', $recurring_ticket_id);
        $count++;
    }

    flashAlert("Category set to <strong>" . escapeHtml($category_name) . "</strong> on <strong>$count</strong> recurring ticket" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_billable'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $billable = intval($_POST['billable'] ?? 0) ? 1 : 0;
    $billable_wording = $billable ? 'Billable' : 'Not billable';

    $count = 0;
    foreach (recurringTicketsForBulk(2) as $recurring_ticket_id => $row) {
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_billable = $billable WHERE recurring_ticket_id = $recurring_ticket_id");
        logAudit("Recurring Ticket", "Edit", "$session_name marked recurring ticket " . escapeSql($row['recurring_ticket_subject']) . " $billable_wording", intval($row['recurring_ticket_client_id']), $recurring_ticket_id);
        triggerCustomAction('recurring_ticket_update', $recurring_ticket_id);
        $count++;
    }

    flashAlert("<strong>$count</strong> recurring ticket" . ($count === 1 ? '' : 's') . " marked <strong>$billable_wording</strong>");

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_next_run_date'])) {

    validateCSRFToken();

    $next_run = recurringTicketDate($_POST['next_run_date'] ?? '');
    if (!$next_run || $next_run < date('Y-m-d')) {
        flashAlert('Pick when they run next - today or later.', 'error');
        redirect();
    }

    $count = 0;
    foreach (recurringTicketsForBulk(2) as $recurring_ticket_id => $row) {
        // A run moved by hand is the new anchor for the day of the month
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_next_run = '$next_run', recurring_ticket_start_date = '$next_run' WHERE recurring_ticket_id = $recurring_ticket_id");
        logAudit("Recurring Ticket", "Edit", "$session_name moved the next run of recurring ticket " . escapeSql($row['recurring_ticket_subject']) . " from " . escapeSql($row['recurring_ticket_next_run']) . " to $next_run", intval($row['recurring_ticket_client_id']), $recurring_ticket_id);
        triggerCustomAction('recurring_ticket_update', $recurring_ticket_id);
        $count++;
    }

    flashAlert("Next run set to <strong>" . escapeHtml(date('M j, Y', strtotime($next_run))) . "</strong> on <strong>$count</strong> recurring ticket" . ($count === 1 ? '' : 's'));

    redirect();

}
