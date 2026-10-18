<?php

/*
 * ITFlow - GET/POST request handler for client tickets
 *
 * Every handler loads the ticket through ticketForRequest() (it exists, the
 * agent has the support module at the level asked, and may reach its client
 * - a ticket with no client is internal work any agent can reach). Whatever
 * a form links a ticket to - contact, asset and other assets, site, vendor,
 * project - must be the ticket's own client's (ticketClientOwnedId() in
 * functions/ticket.php); anything else is dropped, not saved. Priority,
 * category, agent and status are checked against what exists.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** Tell an agent about a ticket - an app notification linking to it. Not themselves, not nobody. */
function ticketNotifyAgent($user_id, $row, $text) {
    global $mysqli, $session_user_id;
    $user_id = intval($user_id);
    if (!$user_id || $user_id == $session_user_id) {
        return;
    }
    $client_id = intval($row['ticket_client_id']);
    $action = escapeSql('/agent/' . ticketUrl($row['ticket_id'], $client_id));
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '" . escapeSql($text) . "', notification_action = '$action', notification_client_id = $client_id, notification_user_id = $user_id");
}

/** Close a merged ticket into another one - notes on both, its tags onto the other, replies moved when asked. */
function ticketMergeInto($child, $parent, $comment, $move_replies = false) {
    global $mysqli, $session_name, $session_user_id;
    $ticket_id = intval($child['ticket_id']);
    $parent_id = intval($parent['ticket_id']);
    $child_reference = escapeSql(ticketReference($child));
    $parent_reference = escapeSql(ticketReference($parent));
    $child_subject = escapeSql($child['ticket_subject']);
    $child_details = mysqli_real_escape_string($mysqli, (string) $child['ticket_details']);
    $parent_url = escapeSql(ticketUrl($parent_id, $parent['ticket_client_id']));

    if ($move_replies) {
        mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_ticket_id = $parent_id WHERE ticket_reply_ticket_id = $ticket_id");
        // The work goes over, and the driving for it with it
        mysqli_query($mysqli, "UPDATE trips SET trip_ticket_id = $parent_id WHERE trip_ticket_id = $ticket_id");
    }
    if (empty($child['ticket_first_response_at'])) {
        setTicketFirstResponse($ticket_id);
    }
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $child_reference merged into <a href=\"$parent_url\">$parent_reference</a>. Comment: $comment', ticket_reply_time_worked = '00:00:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = " . TICKET_STATUS_CLOSED . ", ticket_resolved_at = COALESCE(ticket_resolved_at, NOW()), ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);
    setTicketResolutionSlaMet($ticket_id);

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $child_reference was merged into this ticket with comment: $comment.<br><br><b>$child_subject</b><br>$child_details', ticket_reply_time_worked = '00:00:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $parent_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $parent_id");

    // Its tags go with it - not archived ones, which aren't put on more tickets
    mysqli_query($mysqli, "INSERT IGNORE INTO ticket_tags (ticket_id, tag_id)
        SELECT $parent_id, ticket_tags.tag_id FROM ticket_tags JOIN tags ON tags.tag_id = ticket_tags.tag_id
        WHERE ticket_tags.ticket_id = $ticket_id AND tags.tag_archived_at IS NULL");

    logTicketHistory($ticket_id, "$session_name merged this ticket into $parent_reference and closed it");
    logTicketHistory($parent_id, "$session_name merged $child_reference into this ticket");
    logAudit("Ticket", "Merged", "$session_name merged ticket $child_reference into $parent_reference", intval($child['ticket_client_id']), $ticket_id);
    triggerCustomAction('ticket_merge', $ticket_id);
}

/** The ticket a merge goes into - one the agent may reach, still open. Back with an error otherwise. */
function ticketMergeTarget($ticket_id) {
    $parent = ticketRow($ticket_id);
    if (!$parent || !empty($parent['ticket_closed_at']) || (intval($parent['ticket_client_id']) && !hasClientAccess($parent['ticket_client_id']))) {
        flashAlert('Pick an open ticket you can reach to merge into.', 'error');
        redirect();
    }
    return $parent;
}

if (isset($_POST['add_ticket'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    if (!$client_id || !getFieldById('clients', $client_id, 'client_name')) {
        flashAlert('Pick the client this ticket is for.', 'error');
        redirect();
    }
    enforceClientAccess();

    $subject_input = trim((string) ($_POST['subject'] ?? ''));
    if ($subject_input === '') {
        flashAlert('A ticket needs a subject.', 'error');
        redirect();
    }
    $subject = escapeSql(mb_substr($subject_input, 0, 500));
    $details = mysqli_real_escape_string($mysqli, (string) ($_POST['details'] ?? ''));
    $priority = escapeSql(ticketPriority($_POST['priority'] ?? ''));
    $category_id = ticketCategoryId($_POST['category_id'] ?? 0);
    $assigned_to = ticketAgentId($_POST['assigned_to'] ?? 0);
    $ticket_status = $assigned_to ? TICKET_STATUS_OPEN : TICKET_STATUS_NEW;
    $billable = intval($_POST['billable'] ?? 0) ? 1 : 0;
    $due = ticketDueSql($_POST['due'] ?? '');
    $vendor_ticket_number = escapeSql(mb_substr(trim((string) ($_POST['vendor_ticket_number'] ?? '')), 0, 255));
    $ticket_template_id = intval($_POST['ticket_template_id'] ?? 0);

    // What it is about - the client's own only
    $contact_id = ticketClientOwnedId('contact', $_POST['contact_id'] ?? 0, $client_id);
    if (intval($_POST['use_primary_contact'] ?? 0)) {
        $primary = mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 AND contact_archived_at IS NULL LIMIT 1"));
        $contact_id = $primary ? intval($primary[0]) : $contact_id;
    }
    $asset_id = ticketClientOwnedId('asset', $_POST['asset_id'] ?? 0, $client_id);
    $location_id = ticketClientOwnedId('location', $_POST['location_id'] ?? 0, $client_id);
    $vendor_id = ticketClientOwnedId('vendor', $_POST['vendor_id'] ?? 0, $client_id);
    $project_id = ticketClientOwnedId('project', $_POST['project_id'] ?? 0, $client_id);
    $other_asset_ids = ticketClientOwnedAssetIds($_POST['additional_assets'] ?? [], $client_id, $asset_id);

    // Atomically increment and get the new ticket number
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number), config_ticket_next_number = config_ticket_next_number + 1 WHERE company_id = 1");
    $ticket_number = mysqli_insert_id($mysqli);
    $ticket_prefix = escapeSql($config_ticket_prefix);

    // A key for the client's guest link to it
    $url_key = randomString(32);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Agent', ticket_category = $category_id,
        ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_status = $ticket_status,
        ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_location_id = $location_id, ticket_asset_id = $asset_id,
        ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact_id, ticket_url_key = '$url_key', ticket_due_at = $due,
        ticket_client_id = $client_id, ticket_invoice_id = 0, ticket_project_id = $project_id");

    $ticket_id = mysqli_insert_id($mysqli);
    applyTicketSla($ticket_id);

    // Tasks come from the editable rows in the modal, which the template pre-fills.
    // Fall back to copying the template directly for a form without that section.
    if (isset($_POST['tasks_submitted'])) {
        foreach (parseSubmittedTasks() as $task) {
            mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '{$task['name']}', task_order = {$task['order']}, task_completion_estimate = {$task['estimate']}, task_ticket_id = $ticket_id");
        }
    } else {
        addTasksFromTicketTemplate($ticket_id, $ticket_template_id);
    }

    // Store any attached files against the ticket itself
    $emailable_attachments = filterEmailableAttachments(saveTicketAttachments($ticket_id, null));

    ticketAddWatchers($ticket_id, ticketWatcherEmails($_POST['watchers'] ?? []));
    ticketSetOtherAssets($ticket_id, $other_asset_ids);
    tagSetForRecord('ticket', $ticket_id, (array) ($_POST['tags'] ?? []));

    // Raised about a service - from the service page's "Report a problem"
    foreach (array_unique(array_map('intval', (array) ($_POST['service_ids'] ?? []))) as $service_id) {
        $service = serviceRow($service_id);
        if ($service && intval($service['service_client_id']) === $client_id && !$service['service_archived_at']) {
            serviceLinkTickets($service_id, $client_id, [$ticket_id]);
            logAudit("Service", "Link", "$session_name raised ticket $ticket_prefix$ticket_number about service " . escapeSql($service['service_name']), $client_id, $service_id);
        }
    }

    // E-mail the client and the watchers
    if (intval($config_ticket_client_general_notifications) === 1) {
        ticketMailCreated($ticket_id, 'Ticket Created', $emailable_attachments['send']);
        ticketMailColleaguesNew($ticket_id);
    }

    // Custom action/notif handler
    triggerCustomAction('ticket_create', $ticket_id);

    if ($assigned_to) {
        $row = ticketRow($ticket_id);
        ticketNotifyAgent($assigned_to, $row, "Ticket " . ticketReference($row) . " - Subject: " . $row['ticket_subject'] . " has been assigned to you by $session_name");
        ticketMailAssigned($assigned_to, [$ticket_id], stripslashes($session_name));
    }

    logAudit("Ticket", "Create", "$session_name created ticket $ticket_prefix$ticket_number - $subject", $client_id, $ticket_id);

    flashAlert("Ticket <strong>" . escapeHtml($config_ticket_prefix . $ticket_number) . "</strong> created");

    // Tell the agent about anything too large for the mail queue to carry
    if (!empty($emailable_attachments['skipped'])) {
        $skipped_names = [];
        foreach ($emailable_attachments['skipped'] as $skipped_attachment) {
            $skipped_names[] = escapeHtml($skipped_attachment['name']);
        }
        flashAlert("Stored on the ticket but too large to email: <strong>" . implode(', ', $skipped_names) . "</strong>", 'error');
    }

    redirect("ticket.php?client_id=$client_id&ticket_id=$ticket_id");

}

if (isset($_POST['edit_ticket'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $reference = escapeSql(ticketReference($row));

    if (!empty($row['ticket_closed_at'])) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is closed - a closed ticket stays as it was.", 'error');
        redirect();
    }

    $subject_input = trim((string) ($_POST['subject'] ?? ''));
    if ($subject_input === '') {
        flashAlert('A ticket needs a subject.', 'error');
        redirect();
    }
    $subject = escapeSql(mb_substr($subject_input, 0, 500));
    $details = mysqli_real_escape_string($mysqli, (string) ($_POST['details'] ?? ''));
    $priority = ticketPriority($_POST['priority'] ?? $row['ticket_priority']);
    $priority_sql = escapeSql($priority);
    $category_id = ticketCategoryId($_POST['category_id'] ?? 0);
    $assigned_to = ticketAgentId($_POST['assigned_to'] ?? 0);
    $billable = intval($_POST['billable'] ?? 0) ? 1 : 0;
    $due = ticketDueSql($_POST['due'] ?? '');
    $notify = intval($_POST['contact_notify'] ?? 0);

    // A ticket that gets an agent is no longer New
    $ticket_status = intval($row['ticket_status']);
    if ($ticket_status === TICKET_STATUS_NEW && $assigned_to) {
        $ticket_status = TICKET_STATUS_OPEN;
    }

    $set = "ticket_category = $category_id, ticket_subject = '$subject', ticket_priority = '$priority_sql', ticket_billable = $billable, ticket_details = '$details',
        ticket_due_at = $due, ticket_assigned_to = $assigned_to, ticket_status = $ticket_status";

    // The contact and what it is linked to - each only from a form that showed it
    // (a ticket with no client has no contact, asset or site), and only the client's own
    $contact_id = intval($row['ticket_contact_id']);
    if (isset($_POST['contact_id'])) {
        $contact_id = ticketClientOwnedId('contact', $_POST['contact_id'], $client_id);
        $set .= ", ticket_contact_id = $contact_id";
    }
    $asset_posted = isset($_POST['asset_id']);
    if ($asset_posted) {
        $asset_id = ticketClientOwnedId('asset', $_POST['asset_id'], $client_id);
        $set .= ", ticket_asset_id = $asset_id";
    }
    if (isset($_POST['location_id'])) {
        $set .= ", ticket_location_id = " . ticketClientOwnedId('location', $_POST['location_id'], $client_id);
    }
    if (isset($_POST['vendor_id'])) {
        $set .= ", ticket_vendor_id = " . ticketClientOwnedId('vendor', $_POST['vendor_id'], $client_id);
    }
    if (isset($_POST['vendor_ticket_number'])) {
        $set .= ", ticket_vendor_ticket_number = '" . escapeSql(mb_substr(trim((string) $_POST['vendor_ticket_number']), 0, 255)) . "'";
    }
    if (isset($_POST['project_id'])) {
        $set .= ", ticket_project_id = " . ticketClientOwnedId('project', $_POST['project_id'], $client_id);
    }

    mysqli_query($mysqli, "UPDATE tickets SET $set WHERE ticket_id = $ticket_id");
    applyTicketSla($ticket_id);
    if ($ticket_status !== intval($row['ticket_status'])) {
        syncTicketSlaClock($ticket_id);
    }

    // An empty multi-select posts nothing, so the other assets go with the asset field
    if ($asset_posted) {
        ticketSetOtherAssets($ticket_id, ticketClientOwnedAssetIds($_POST['additional_assets'] ?? [], $client_id, $asset_id));
    }

    // ...and the tags with their marker
    if (isset($_POST['tags_form'])) {
        $tags_before = tagIdsForRecord('ticket', $ticket_id);
        $tags_after = tagSetForRecord('ticket', $ticket_id, (array) ($_POST['tags'] ?? []));
        $tags_text = tagChangeText($tags_before, $tags_after);
        if ($tags_text !== '') {
            logTicketHistory($ticket_id, escapeSql(stripslashes((string) $session_name) . " $tags_text"));
        }
    }

    // The trail for what changed - the same entries the dedicated pickers write
    if ($row['ticket_priority'] !== $priority) {
        logTicketHistory($ticket_id, "$session_name changed priority from " . escapeSql($row['ticket_priority']) . " to $priority_sql");
    }
    if (intval($row['ticket_assigned_to']) !== $assigned_to) {
        if ($assigned_to) {
            logTicketHistory($ticket_id, "$session_name assigned the ticket to " . escapeSql(getFieldById('users', $assigned_to, 'user_name')));
            ticketNotifyAgent($assigned_to, $row, "Ticket " . ticketReference($row) . " - Subject: " . $subject_input . " has been assigned to you by $session_name");
            ticketMailAssigned($assigned_to, [$ticket_id], stripslashes($session_name));
        } else {
            logTicketHistory($ticket_id, "$session_name unassigned the ticket");
        }
    }
    if (intval($row['ticket_contact_id']) !== $contact_id) {
        $contact_name = $contact_id ? escapeSql(getFieldById('contacts', $contact_id, 'contact_name')) : 'No one';
        logTicketHistory($ticket_id, "$session_name changed the contact from " . ($row['contact_name'] ? escapeSql($row['contact_name']) : 'No one') . " to $contact_name");
    }

    // Let the contact know about it, when asked
    if ($notify && $contact_id && empty($row['ticket_closed_at'])) {
        ticketMailCreated($ticket_id, 'Ticket Created -', [], false);
    }

    // Custom action/notif handler
    triggerCustomAction('ticket_update', $ticket_id);

    logAudit("Ticket", "Edit", "$session_name edited ticket $reference", $client_id, $ticket_id);

    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> updated");

    redirect();

}

if (isset($_POST['edit_ticket_sla'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $sla_id = intval($_POST['sla_id'] ?? 0);

    // No SLA, the one it has, or one that is still in use
    $sla_name = 'None';
    if ($sla_id) {
        $sla = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT sla_name FROM slas WHERE sla_id = $sla_id AND (sla_archived_at IS NULL OR sla_id = " . intval($row['ticket_sla_id']) . ")"));
        if (!$sla) {
            flashAlert('That SLA plan is not available.', 'error');
            redirect();
        }
        $sla_name = $sla['sla_name'];
    }
    $original_sla_name = intval($row['ticket_sla_id']) ? (string) getFieldById('slas', $row['ticket_sla_id'], 'sla_name') : 'None';

    // Pin the ticket to the chosen SLA and recompute its targets
    applyTicketSla($ticket_id, $sla_id);

    logTicketHistory($ticket_id, "$session_name changed SLA from " . escapeSql($original_sla_name) . " to " . escapeSql($sla_name));

    logAudit("Ticket", "Edit", "$session_name changed SLA from " . escapeSql($original_sla_name) . " to " . escapeSql($sla_name) . " for ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);

    triggerCustomAction('ticket_update', $ticket_id);

    flashAlert("SLA updated from <strong>" . escapeHtml($original_sla_name) . "</strong> to <strong>" . escapeHtml($sla_name) . "</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_contact'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $contact_id = ticketClientOwnedId('contact', $_POST['contact'] ?? 0, $client_id);
    $notify = intval($_POST['contact_notify'] ?? 0);

    $original_contact_name = $row['contact_name'] ? $row['contact_name'] : 'No one';
    $contact_name = $contact_id ? (string) getFieldById('contacts', $contact_id, 'contact_name') : 'No one';

    mysqli_query($mysqli, "UPDATE tickets SET ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id");

    // Let the new contact know about it, when asked
    if ($notify && $contact_id && empty($row['ticket_closed_at'])) {
        ticketMailCreated($ticket_id, 'Ticket Created -', [], false);
    }

    triggerCustomAction('ticket_update', $ticket_id);

    logTicketHistory($ticket_id, "$session_name changed the contact from " . escapeSql($original_contact_name) . " to " . escapeSql($contact_name));

    logAudit("Ticket", "Edit", "$session_name changed the contact from " . escapeSql($original_contact_name) . " to " . escapeSql($contact_name) . " for ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);

    flashAlert("Contact changed from <strong>" . escapeHtml($original_contact_name) . "</strong> to <strong>" . escapeHtml($contact_name) . "</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_project'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $project_id = ticketClientOwnedId('project', $_POST['project'] ?? 0, $client_id);
    $project_name = $project_id ? (string) getFieldById('projects', $project_id, 'project_name') : '';
    $reference = escapeSql(ticketReference($row));

    mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

    if ($project_id) {
        logTicketHistory($ticket_id, "$session_name added the ticket to project " . escapeSql($project_name));
        logAudit("Ticket", "Edit", "$session_name set ticket $reference project to " . escapeSql($project_name), $client_id, $ticket_id);
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is now part of project <strong>" . escapeHtml($project_name) . "</strong>");
    } else {
        logTicketHistory($ticket_id, "$session_name took the ticket out of its project");
        logAudit("Ticket", "Edit", "$session_name took ticket $reference out of its project", $client_id, $ticket_id);
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is no longer part of a project");
    }

    redirect();

}

if (isset($_POST['add_ticket_watcher'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $notify = intval($_POST['watcher_notify'] ?? 0);
    $reference = escapeSql(ticketReference($row));

    $added = ticketAddWatchers($ticket_id, ticketWatcherEmails($_POST['watcher_email'] ?? ''));

    // Let each new watcher know, when asked
    if ($added && $notify) {
        ticketMailWatchersAdded($ticket_id, $added, stripslashes($session_name));
    }

    foreach ($added as $watcher_email) {
        logTicketHistory($ticket_id, "$session_name added " . escapeSql($watcher_email) . " as a watcher");
        logAudit("Ticket", "Edit", "$session_name added " . escapeSql($watcher_email) . " as a watcher for ticket $reference", $client_id, $ticket_id);
    }

    $count = count($added);
    flashAlert($count ? "Added <strong>$count</strong> watcher" . ($count === 1 ? '' : 's') : 'No new watchers - those addresses are watching already, or are not email addresses', $count ? 'success' : 'error');

    redirect();

}

if (isset($_GET['delete_ticket_watcher'])) {

    validateCSRFToken();

    $watcher_id = intval($_GET['delete_ticket_watcher']);
    $watcher = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT watcher_email, watcher_ticket_id FROM ticket_watchers WHERE watcher_id = $watcher_id"));
    if (!$watcher) {
        flashAlert('That watcher was already removed.', 'error');
        redirect();
    }
    $row = ticketForRequest($watcher['watcher_ticket_id'], 2);
    $ticket_id = intval($row['ticket_id']);
    $watcher_email = escapeSql($watcher['watcher_email']);

    mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_id = $watcher_id");

    logTicketHistory($ticket_id, "$session_name removed $watcher_email as a watcher");

    logAudit("Ticket", "Edit", "$session_name removed $watcher_email as a watcher for ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);

    flashAlert("Removed ticket watcher <strong>" . escapeHtml($watcher['watcher_email']) . "</strong>", 'error');

    redirect();

}

if (isset($_GET['delete_ticket_additional_asset'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $asset_id = intval($_GET['delete_ticket_additional_asset']);
    $asset_name = (string) getFieldById('assets', $asset_id, 'asset_name');

    mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id AND asset_id = $asset_id");

    if (mysqli_affected_rows($mysqli)) {
        logTicketHistory($ticket_id, "$session_name removed additional asset " . escapeSql($asset_name));
        logAudit("Ticket", "Edit", "$session_name removed asset " . escapeSql($asset_name) . " from ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);
    }

    flashAlert("Removed asset <strong>" . escapeHtml($asset_name) . "</strong> from the ticket", 'error');

    redirect();

}

if (isset($_POST['edit_ticket_asset'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $asset_id = ticketClientOwnedId('asset', $_POST['asset'] ?? 0, $client_id);
    $reference = escapeSql(ticketReference($row));

    mysqli_query($mysqli, "UPDATE tickets SET ticket_asset_id = $asset_id WHERE ticket_id = $ticket_id");

    // The others, when the form carried them
    if (isset($_POST['additional_assets']) || isset($_POST['additional_assets_form'])) {
        ticketSetOtherAssets($ticket_id, ticketClientOwnedAssetIds($_POST['additional_assets'] ?? [], $client_id, $asset_id));
    } else {
        // The primary asset is never also one of the others
        mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id AND asset_id = $asset_id");
    }

    $asset_name = $asset_id ? (string) getFieldById('assets', $asset_id, 'asset_name') : '';
    if ($asset_id !== intval($row['ticket_asset_id'])) {
        logTicketHistory($ticket_id, $asset_id ? "$session_name set the asset to " . escapeSql($asset_name) : "$session_name removed the asset");
    }
    logAudit("Ticket", "Edit", "$session_name " . ($asset_id ? "changed asset to " . escapeSql($asset_name) : "removed the asset") . " for ticket $reference", $client_id, $ticket_id);

    flashAlert($asset_id ? "Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> asset set to <strong>" . escapeHtml($asset_name) . "</strong>" : "Removed the asset from ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_vendor'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $reference = escapeSql(ticketReference($row));

    // Only the ticket's own client's vendors
    $vendor_id = ticketClientOwnedId('vendor', $_POST['vendor'] ?? 0, $client_id);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_vendor_id = $vendor_id WHERE ticket_id = $ticket_id");

    // The vendor modal carries the vendor's own ticket number too. Only touched when the
    // field was posted, so anything else that submits this action cannot blank it by omission
    if (isset($_POST['vendor_ticket_number'])) {
        $vendor_ticket_number = escapeSql(mb_substr(trim((string) $_POST['vendor_ticket_number']), 0, 255));
        mysqli_query($mysqli, "UPDATE tickets SET ticket_vendor_ticket_number = '$vendor_ticket_number' WHERE ticket_id = $ticket_id");
    }

    $vendor_name = $vendor_id ? (string) getFieldById('vendors', $vendor_id, 'vendor_name') : '';

    if ($vendor_id) {
        if ($vendor_id !== intval($row['ticket_vendor_id'])) {
            logTicketHistory($ticket_id, "$session_name set the vendor to " . escapeSql($vendor_name));
        }
        logAudit("Ticket", "Edit", "$session_name set vendor to " . escapeSql($vendor_name) . " for ticket $reference", $client_id, $ticket_id);
        flashAlert("Set vendor to <strong>" . escapeHtml($vendor_name) . "</strong> for ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong>");
    } else {
        if (intval($row['ticket_vendor_id'])) {
            logTicketHistory($ticket_id, "$session_name removed the vendor");
        }
        logAudit("Ticket", "Edit", "$session_name removed the vendor from ticket $reference", $client_id, $ticket_id);
        flashAlert("Removed the vendor from ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong>");
    }

    redirect();

}

// Closed tickets are the record of work done - kept, not deleted
if (isset($_GET['delete_ticket'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['delete_ticket'], 3);
    $ticket_id = intval($row['ticket_id']);
    $reference = escapeSql(ticketReference($row));

    if (!empty($row['ticket_closed_at'])) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is closed - closed tickets are kept, not deleted.", 'error');
        redirect();
    }

    ticketDeleteForGood($ticket_id);

    logAudit("Ticket", "Delete", "$session_name deleted $reference - " . escapeSql($row['ticket_subject']) . " along with all replies", $client_id);

    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> along with all replies deleted", 'error');

    triggerCustomAction('ticket_delete', $ticket_id);

    redirect("tickets.php");

}

if (isset($_POST['bulk_delete_tickets'])) {

    validateCSRFToken();

    $count = $kept = 0;
    foreach (ticketsForBulk(3) as $ticket_id => $row) {
        if (!empty($row['ticket_closed_at'])) {
            $kept++;
            continue;
        }
        ticketDeleteForGood($ticket_id);
        logAudit("Ticket", "Delete", "$session_name deleted ticket " . escapeSql(ticketReference($row)) . " - " . escapeSql($row['ticket_subject']), intval($row['ticket_client_id']));
        triggerCustomAction('ticket_delete', $ticket_id);
        $count++;
    }

    logAudit("Ticket", "Bulk Delete", "$session_name deleted $count ticket(s)");

    flashAlert("Deleted <strong>$count</strong> ticket" . ($count === 1 ? '' : 's') . ($kept ? ", kept <strong>$kept</strong> closed - closed tickets are not deleted" : ''), 'error');

    redirect();

}

if (isset($_POST['bulk_assign_ticket'])) {

    validateCSRFToken();

    $assign_to = ticketAgentId($_POST['assign_to'] ?? 0);
    if (intval($_POST['assign_to'] ?? 0) && !$assign_to) {
        flashAlert('Pick an active agent.', 'error');
        redirect();
    }
    $agent_name = $assign_to ? (string) getFieldById('users', $assign_to, 'user_name') : 'No One';
    $agent_name_sql = escapeSql($agent_name);

    $count = $skipped = 0;
    $assigned_ids = [];
    foreach (ticketsForBulk(2) as $ticket_id => $row) {
        // A closed ticket keeps who closed it
        if (!empty($row['ticket_closed_at'])) {
            $skipped++;
            continue;
        }
        $ticket_status = intval($row['ticket_status']);
        if ($ticket_status === TICKET_STATUS_NEW && $assign_to) {
            $ticket_status = TICKET_STATUS_OPEN;
        }
        $reply = $assign_to ? "Ticket re-assigned to $agent_name_sql." : "Ticket unassigned, pending re-assignment.";

        mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $assign_to, ticket_status = $ticket_status WHERE ticket_id = $ticket_id");
        syncTicketSlaClock($ticket_id);

        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$reply', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

        logTicketHistory($ticket_id, "$session_name assigned the ticket to $agent_name_sql");

        logAudit("Ticket", "Edit", "$session_name reassigned ticket " . escapeSql(ticketReference($row)) . " to $agent_name_sql", intval($row['ticket_client_id']), $ticket_id);

        triggerCustomAction('ticket_assign', $ticket_id);

        $assigned_ids[] = $ticket_id;
        $count++;
    }

    // One notification and one email for the lot
    if ($count && $assign_to && $assign_to != $session_user_id) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$count ticket" . ($count === 1 ? ' has' : 's have') . " been assigned to you by $session_name', notification_action = '/agent/tickets.php?assigned=$assign_to', notification_user_id = $assign_to");

        ticketMailAssigned($assign_to, $assigned_ids, stripslashes($session_name));
    }

    flashAlert("Assigned <strong>$count</strong> ticket" . ($count === 1 ? '' : 's') . " to <strong>" . escapeHtml($agent_name) . "</strong>" . ($skipped ? ", <strong>$skipped</strong> closed left as they were" : ''));

    redirect();

}

if (isset($_POST['bulk_edit_ticket_priority'])) {

    validateCSRFToken();

    $priority = $_POST['bulk_priority'] ?? '';
    if (!in_array($priority, TICKET_PRIORITIES, true)) {
        flashAlert('That is not a priority.', 'error');
        redirect();
    }

    // The same change the priority picker makes - history, SLA, audit and the custom action
    $count = 0;
    foreach (ticketsForBulk(2) as $ticket_id => $row) {
        [$ok] = ticketChangePriority($ticket_id, $priority);
        $count += $ok ? 1 : 0;
    }

    logAudit("Ticket", "Bulk Edit", "$session_name set the priority of $count ticket(s) to $priority");

    flashAlert("Priority set to <strong>$priority</strong> on <strong>$count</strong> ticket" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_edit_ticket_category'])) {

    validateCSRFToken();

    $category_id = ticketCategoryId($_POST['bulk_category'] ?? 0);
    $category_name = $category_id ? (string) getFieldById('categories', $category_id, 'category_name') : 'Not categorized';

    $count = 0;
    foreach (ticketsForBulk(2) as $ticket_id => $row) {
        $previous = intval($row['ticket_category']) ? (string) getFieldById('categories', intval($row['ticket_category']), 'category_name') : 'Not categorized';

        mysqli_query($mysqli, "UPDATE tickets SET ticket_category = $category_id WHERE ticket_id = $ticket_id");

        if (intval($row['ticket_category']) !== $category_id) {
            logTicketHistory($ticket_id, "$session_name changed the category from " . escapeSql($previous) . " to " . escapeSql($category_name));
        }
        logAudit("Ticket", "Edit", "$session_name updated the category on ticket " . escapeSql(ticketReference($row)) . " from " . escapeSql($previous) . " to " . escapeSql($category_name), intval($row['ticket_client_id']), $ticket_id);

        triggerCustomAction('ticket_update', $ticket_id);
        $count++;
    }

    logAudit("Ticket", "Bulk Edit", "$session_name set the category of $count ticket(s) to " . escapeSql($category_name));

    flashAlert("Category set to <strong>" . escapeHtml($category_name) . "</strong> on <strong>$count</strong> ticket" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_merge_tickets'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $parent = ticketMergeTarget($_POST['merge_into_ticket_id'] ?? 0);
    $comment = escapeSql($_POST['merge_comment'] ?? '');

    // A ticket only merges into one of its own client's - another client's would see the conversation
    $count = $skipped = $other_client = 0;
    foreach (ticketsForBulk(2) as $ticket_id => $row) {
        if ($ticket_id === intval($parent['ticket_id']) || !empty($row['ticket_closed_at'])) {
            $skipped++;
            continue;
        }
        if (intval($row['ticket_client_id']) !== intval($parent['ticket_client_id'])) {
            $other_client++;
            continue;
        }
        ticketMergeInto($row, $parent, $comment);
        $count++;
    }

    flashAlert("<strong>$count</strong> ticket" . ($count === 1 ? '' : 's') . " merged into <strong>" . escapeHtml(ticketReference($parent)) . "</strong>"
        . ($skipped ? ", <strong>$skipped</strong> left - closed already, or the ticket itself" : '')
        . ($other_client ? ", <strong>$other_client</strong> left - another client's" : ''), $count ? 'success' : 'error');

    redirect();

}

if (isset($_POST['bulk_resolve_tickets'])) {

    validateCSRFToken();

    $details = mysqli_real_escape_string($mysqli, (string) ($_POST['bulk_details'] ?? ''));
    $time_worked = escapeSql(ticketTimeWorked($_POST['time'] ?? ''));
    $private_note = intval($_POST['bulk_private_note'] ?? 0);
    $reply_type = $private_note ? 'Internal' : 'Public';

    $count = $skipped_tasks = $skipped_done = 0;
    foreach (ticketsForBulk(2) as $ticket_id => $row) {
        if (!ticketIsOpen($row)) {
            $skipped_done++;
            continue;
        }
        // Tasks have to be finished first
        if (ticketOpenTaskCount($ticket_id)) {
            $skipped_tasks++;
            continue;
        }

        ticketResolve($row);

        if ($details !== '' || $time_worked !== '00:00:00') {
            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$details', ticket_reply_type = '$reply_type', ticket_reply_time_worked = '$time_worked', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
        }

        // Client notification email
        if (intval($config_ticket_client_general_notifications) === 1 && !$private_note) {
            ticketMailResolved($ticket_id, (string) ($_POST['bulk_details'] ?? ''));
        }
        $count++;
    }

    flashAlert("Resolved <strong>$count</strong> ticket" . ($count === 1 ? '' : 's')
        . ($skipped_tasks ? ", <strong>$skipped_tasks</strong> not resolved - they have open tasks" : '')
        . ($skipped_done ? ", <strong>$skipped_done</strong> resolved or closed already" : ''), $skipped_tasks ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_ticket_reply'])) {

    validateCSRFToken();

    $reply = mysqli_real_escape_string($mysqli, (string) ($_POST['bulk_reply_details'] ?? ''));
    $status_input = intval($_POST['bulk_status'] ?? 0);
    $time_worked = escapeSql(ticketTimeWorked($_POST['time'] ?? ''));
    $private_note = intval($_POST['bulk_private_reply'] ?? 0);
    $reply_type = $private_note ? 'Internal' : 'Public';

    $count = $skipped = 0;
    foreach (ticketsForBulk(2) as $ticket_id => $row) {
        // A closed ticket takes no more replies
        if (!empty($row['ticket_closed_at'])) {
            $skipped++;
            continue;
        }
        $original_status = intval($row['ticket_status']);
        $ticket_status = in_array($status_input, ticketReplyStatuses($original_status), true) ? $status_input : $original_status;
        // Resolving waits for the tasks
        if ($ticket_status === TICKET_STATUS_RESOLVED && $original_status !== TICKET_STATUS_RESOLVED && ticketOpenTaskCount($ticket_id)) {
            $ticket_status = $original_status;
        }
        $reference = escapeSql(ticketReference($row));
        $subject = escapeSql($row['ticket_subject']);

        // Mark FR time if required - internal notes don't count as a response
        if (empty($row['ticket_first_response_at']) && $reply_type === 'Public') {
            setTicketFirstResponse($ticket_id);
        }

        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$reply', ticket_reply_time_worked = '$time_worked', ticket_reply_type = '$reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $ticket_status, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");
        syncTicketSlaClock($ticket_id);

        if ($ticket_status !== $original_status && $ticket_status !== TICKET_STATUS_RESOLVED) {
            logTicketHistory($ticket_id, "$session_name set the status to " . escapeSql(getTicketStatusName($ticket_status)));
        }

        logAudit("Ticket", "Reply", "$session_name replied to ticket $reference - $subject and was a $reply_type reply", intval($row['ticket_client_id']), $ticket_id);

        triggerCustomAction($reply_type === 'Internal' ? 'ticket_reply_agent_internal' : 'reply_reply_agent_public', $ticket_id);

        // Resolve the ticket, if it is actually moving into Resolved
        if ($ticket_status === TICKET_STATUS_RESOLVED && $original_status !== TICKET_STATUS_RESOLVED) {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
            setTicketResolutionSlaMet($ticket_id);
            logTicketHistory($ticket_id, "$session_name resolved the ticket");
            logAudit("Ticket", "Resolved", "$session_name resolved ticket $reference", intval($row['ticket_client_id']), $ticket_id);
            triggerCustomAction('ticket_resolve', $ticket_id);
        }

        // Email the client and watchers about a public update
        if (!$private_note) {
            ticketMailReply($ticket_id, (string) ($_POST['bulk_reply_details'] ?? ''), stripslashes($session_name));
        }

        // Tell the agent it is assigned to, and whoever opened it
        ticketNotifyAgent($row['ticket_assigned_to'], $row, "$session_name updated Ticket " . ticketReference($row) . " - Subject: " . $row['ticket_subject'] . " that is assigned to you");
        if (intval($row['ticket_created_by']) !== intval($row['ticket_assigned_to'])) {
            ticketNotifyAgent($row['ticket_created_by'], $row, "$session_name updated Ticket " . ticketReference($row) . " - Subject: " . $row['ticket_subject'] . " that you opened");
        }
        $count++;
    }

    flashAlert("Updated <strong>$count</strong> ticket" . ($count === 1 ? '' : 's') . ($skipped ? ", <strong>$skipped</strong> closed left as they were" : ''));

    redirect();

}

if (isset($_POST['bulk_add_ticket_project'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $project_input = intval($_POST['project_id'] ?? 0);
    $project_name = $project_input ? (string) getFieldById('projects', $project_input, 'project_name') : '';

    $count = $skipped = 0;
    foreach (ticketsForBulk(2) as $ticket_id => $row) {
        // The project has to be the ticket's client's, or an internal one
        $project_id = ticketClientOwnedId('project', $project_input, $row['ticket_client_id']);
        if ($project_input && !$project_id) {
            $skipped++;
            continue;
        }
        mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");
        logTicketHistory($ticket_id, $project_id ? "$session_name added the ticket to project " . escapeSql($project_name) : "$session_name took the ticket out of its project");
        logAudit("Ticket", "Edit", "$session_name " . ($project_id ? "added ticket " . escapeSql(ticketReference($row)) . " to project " . escapeSql($project_name) : "took ticket " . escapeSql(ticketReference($row)) . " out of its project"), intval($row['ticket_client_id']), $ticket_id);
        $count++;
    }

    flashAlert(($project_input ? "<strong>$count</strong> ticket" . ($count === 1 ? '' : 's') . " added to project <strong>" . escapeHtml($project_name) . "</strong>" : "<strong>$count</strong> ticket" . ($count === 1 ? '' : 's') . " taken out of their project")
        . ($skipped ? ", <strong>$skipped</strong> skipped - the project is another client's" : ''));

    redirect();

}

// New ticket for each asset picked on the assets list
if (isset($_POST['bulk_add_asset_ticket'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $assigned_to = ticketAgentId($_POST['bulk_assigned_to'] ?? 0);
    $ticket_status = $assigned_to ? TICKET_STATUS_OPEN : TICKET_STATUS_NEW;
    $subject_input = trim((string) ($_POST['bulk_subject'] ?? ''));
    $priority = escapeSql(ticketPriority($_POST['bulk_priority'] ?? ''));
    $category_id = ticketCategoryId($_POST['bulk_category'] ?? 0);
    $details = mysqli_real_escape_string($mysqli, (string) ($_POST['bulk_details'] ?? ''));
    $project_input = intval($_POST['bulk_project'] ?? 0);
    $ticket_template_id = intval($_POST['bulk_ticket_template_id'] ?? 0);
    $billable = intval($_POST['bulk_billable'] ?? 0) ? 1 : 0;
    $ticket_prefix = escapeSql($config_ticket_prefix);

    // A template fills in what was left empty
    if ($ticket_template_id) {
        $template = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_template_details, ticket_template_subject FROM ticket_templates WHERE ticket_template_id = $ticket_template_id"));
        if ($template) {
            $subject_input = $subject_input !== '' ? $subject_input : (string) $template['ticket_template_subject'];
            $details = $details !== '' ? $details : mysqli_real_escape_string($mysqli, (string) $template['ticket_template_details']);
        }
    }

    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['asset_ids'] ?? []))) as $asset_id) {
        $asset = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_client_id, asset_name, asset_contact_id, asset_location_id FROM assets WHERE asset_id = $asset_id"));
        if (!$asset) {
            continue;
        }
        $client_id = intval($asset['asset_client_id']);
        enforceClientAccess();

        // The person using it and where it is
        $contact_id = ticketClientOwnedId('contact', $asset['asset_contact_id'], $client_id);
        $location_id = ticketClientOwnedId('location', $asset['asset_location_id'], $client_id);
        $project_id = ticketClientOwnedId('project', $project_input, $client_id);
        $subject = escapeSql(mb_substr($asset['asset_name'] . ' - ' . $subject_input, 0, 500));

        mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number), config_ticket_next_number = config_ticket_next_number + 1 WHERE company_id = 1");
        $ticket_number = mysqli_insert_id($mysqli);
        $url_key = randomString(32);

        mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Agent', ticket_category = $category_id,
            ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_status = $ticket_status,
            ticket_asset_id = $asset_id, ticket_contact_id = $contact_id, ticket_location_id = $location_id, ticket_created_by = $session_user_id,
            ticket_assigned_to = $assigned_to, ticket_url_key = '$url_key', ticket_client_id = $client_id, ticket_project_id = $project_id");
        $ticket_id = mysqli_insert_id($mysqli);
        applyTicketSla($ticket_id);

        // Tasks from the modal, then the template's
        foreach ((array) ($_POST['tasks'] ?? []) as $task) {
            $task_name = escapeSql(mb_substr(trim((string) $task), 0, 255));
            if ($task_name !== '') {
                mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_ticket_id = $ticket_id");
            }
        }
        addTasksFromTicketTemplate($ticket_id, $ticket_template_id);

        logAudit("Ticket", "Create", "$session_name created ticket $ticket_prefix$ticket_number - $subject", $client_id, $ticket_id);
        triggerCustomAction('ticket_create', $ticket_id);
        $count++;
    }

    logAudit("Ticket", "Bulk Create", "$session_name created $count ticket(s) for the assets picked");

    flashAlert("Created <strong>$count</strong> ticket" . ($count === 1 ? '' : 's') . " for the assets picked");

    redirect();

}

if (isset($_POST['add_ticket_reply'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $reference = escapeSql(ticketReference($row));
    $subject = escapeSql($row['ticket_subject']);

    if (!empty($row['ticket_closed_at'])) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is closed - start a new ticket instead.", 'error');
        redirect();
    }

    $ticket_reply = (string) ($_POST['ticket_reply'] ?? ''); // SQL escaped below
    $original_ticket_status = intval($row['ticket_status']);
    $ticket_status = intval($_POST['status'] ?? $original_ticket_status);

    // Only a status a reply may set; Resolved waits for the tasks
    if (!in_array($ticket_status, ticketReplyStatuses($original_ticket_status), true)) {
        $ticket_status = $original_ticket_status;
    }
    if ($ticket_status === TICKET_STATUS_RESOLVED && $original_ticket_status !== TICKET_STATUS_RESOLVED && ticketOpenTaskCount($ticket_id)) {
        flashAlert('Finish the open tasks before resolving this ticket.', 'error');
        $ticket_status = $original_ticket_status;
    }

    // Time tracking, inputs & combine into string
    $ticket_reply_time_worked = escapeSql(sprintf("%02d:%02d:%02d", max(0, intval($_POST['hours'] ?? 0)), max(0, min(59, intval($_POST['minutes'] ?? 0))), max(0, min(59, intval($_POST['seconds'] ?? 0)))));

    // Who sees it: internal note, public note, or public and emailed
    $send_email = 0;
    $ticket_reply_id = 0;
    $public_reply_type = intval($_POST['public_reply_type'] ?? 0);
    $ticket_reply_type = $public_reply_type ? 'Public' : 'Internal';
    if ($public_reply_type === 2) {
        $send_email = 1;
    }
    // Add Signature to the end of the ticket reply if not Internal and if there is reply
    if ($ticket_reply !== '' && $send_email) {
        $ticket_reply .= getFieldById('user_settings', $session_user_id, 'user_config_signature');
    }
    $ticket_reply = mysqli_real_escape_string($mysqli, $ticket_reply);

    // Update Ticket Status & updated at (in case status didn't change)
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $ticket_status, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);

    // Resolve the ticket, if it is actually moving into Resolved - replying on an
    // already-resolved ticket must not restamp resolved_at or re-log the resolve
    if ($ticket_status === TICKET_STATUS_RESOLVED && $original_ticket_status !== TICKET_STATUS_RESOLVED) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
        setTicketResolutionSlaMet($ticket_id);
        logTicketHistory($ticket_id, "$session_name resolved the ticket");
        logAudit("Ticket", "Resolved", "$session_name resolved ticket $reference", $client_id, $ticket_id);
        triggerCustomAction('ticket_resolve', $ticket_id);
    }

    $emailable_attachments = ['send' => [], 'skipped' => []];

    // Process reply actions, if we have a reply to work with (e.g. we're not just editing the status)
    if ($ticket_reply !== '') {

        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
        $ticket_reply_id = mysqli_insert_id($mysqli);

        // Store any attached files against this reply before the email is built, so
        // a public reply can carry them
        $emailable_attachments = filterEmailableAttachments(saveTicketAttachments($ticket_id, $ticket_reply_id));

        // Email the client and the watchers
        if ($send_email) {
            // The reply as saved - with the signature - read back
            ticketMailReply($ticket_id, (string) getFieldById('ticket_replies', $ticket_reply_id, 'ticket_reply'), stripslashes($session_name),
                $ticket_status === TICKET_STATUS_RESOLVED, $emailable_attachments['send']);
        }

        // Tell the agent it is assigned to, and whoever opened it
        ticketNotifyAgent($row['ticket_assigned_to'], $row, "$session_name updated Ticket " . ticketReference($row) . " - Subject: " . $row['ticket_subject'] . " that is assigned to you");
        if (intval($row['ticket_created_by']) !== intval($row['ticket_assigned_to'])) {
            ticketNotifyAgent($row['ticket_created_by'], $row, "$session_name updated Ticket " . ticketReference($row) . " - Subject: " . $row['ticket_subject'] . " that you opened");
        }

        // Handle first response
        if (empty($row['ticket_first_response_at']) && $ticket_reply_type === 'Public') {
            setTicketFirstResponse($ticket_id);
        }

        triggerCustomAction($ticket_reply_type === 'Internal' ? 'ticket_reply_agent_internal' : 'reply_reply_agent_public', $ticket_id);

        flashAlert(($ticket_reply_type === 'Internal' ? 'Internal note added to' : 'Reply added to') . " ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong>");

    } else {
        // A file uploaded without any accompanying text has no reply to hang off,
        // so it attaches to the ticket itself
        $emailable_attachments = filterEmailableAttachments(saveTicketAttachments($ticket_id, null));
        flashAlert("Ticket updated");
    }

    // Tell the agent about anything too large for the mail queue to carry, rather
    // than letting them assume the recipient got it
    if (!empty($emailable_attachments['skipped'])) {
        $skipped_names = [];
        foreach ($emailable_attachments['skipped'] as $skipped_attachment) {
            $skipped_names[] = escapeHtml($skipped_attachment['name']);
        }
        flashAlert("Stored on the ticket but too large to email: <strong>" . implode(', ', $skipped_names) . "</strong>", 'error');
    }

    /*
     * The reply form preselects the ticket's current status, so most replies post
     * it straight back - only record a status change when it actually changed.
     * Resolved is left out because the resolve block above already logged it
     */
    if ($ticket_status !== $original_ticket_status && $ticket_status !== TICKET_STATUS_RESOLVED) {
        logTicketHistory($ticket_id, "$session_name set the status to " . escapeSql(getTicketStatusName($ticket_status)));
    }

    logAudit("Ticket", "Reply", "$session_name replied to ticket $reference - $subject and was a $ticket_reply_type reply", $client_id, $ticket_id);

    redirect();

}

if (isset($_GET['delete_ticket_attachment'])) {

    validateCSRFToken();

    $attachment_id = intval($_GET['delete_ticket_attachment']);
    $attachment = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_attachment_name, ticket_attachment_reference_name, ticket_attachment_ticket_id FROM ticket_attachments WHERE ticket_attachment_id = $attachment_id LIMIT 1"));
    if (!$attachment) {
        flashAlert('That attachment no longer exists.', 'error');
        redirect();
    }

    $row = ticketForRequest($attachment['ticket_attachment_ticket_id'], 3);
    $ticket_id = intval($row['ticket_id']);
    $attachment_name = escapeSql($attachment['ticket_attachment_name']);

    // Resolve the path and confirm it is still inside uploads before unlinking,
    // the same guard the download endpoint applies
    $uploads_base = realpath(__DIR__ . "/../../uploads");
    $file_path = realpath(__DIR__ . "/../../uploads/tickets/$ticket_id/" . $attachment['ticket_attachment_reference_name']);
    if ($file_path !== false && $uploads_base !== false && strpos($file_path, $uploads_base) === 0) {
        unlink($file_path);
    }

    mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_id = $attachment_id");

    logAudit("Ticket", "Delete", "$session_name deleted ticket attachment $attachment_name", $client_id, $ticket_id);

    flashAlert("Attachment <strong>" . escapeHtml($attachment['ticket_attachment_name']) . "</strong> deleted", 'error');

    redirect();

}

/** The reply a request names and its ticket, after the checks - [reply, ticket]. */
function ticketReplyForRequest($ticket_reply_id, $level) {
    global $mysqli;
    $ticket_reply_id = intval($ticket_reply_id);
    $reply = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_reply_id, ticket_reply_type, ticket_reply_ticket_id FROM ticket_replies WHERE ticket_reply_id = $ticket_reply_id LIMIT 1"));
    if (!$reply) {
        flashAlert('That reply no longer exists.', 'error');
        redirect();
    }
    return [$reply, ticketForRequest($reply['ticket_reply_ticket_id'], $level)];
}

if (isset($_POST['edit_ticket_reply'])) {

    validateCSRFToken();

    [$reply, $row] = ticketReplyForRequest($_POST['ticket_reply_id'] ?? 0, 2);
    $ticket_reply_id = intval($reply['ticket_reply_id']);

    // What the client wrote stays as they wrote it
    if ($reply['ticket_reply_type'] === 'Client') {
        flashAlert("A client's reply can't be edited - redact it instead.", 'error');
        redirect();
    }

    $ticket_reply = mysqli_real_escape_string($mysqli, (string) ($_POST['ticket_reply'] ?? ''));
    // Public or internal - anything else keeps what it was
    $ticket_reply_type = in_array($_POST['ticket_reply_type'] ?? '', ['Public', 'Internal'], true) ? $_POST['ticket_reply_type'] : $reply['ticket_reply_type'];
    $ticket_reply_time_worked = escapeSql(ticketTimeWorked($_POST['time'] ?? ''));

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '$ticket_reply_time_worked' WHERE ticket_reply_id = $ticket_reply_id");

    logAudit("Ticket", "Reply", "$session_name edited a reply on ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_reply_id);

    flashAlert("Ticket reply updated");

    redirect();

}

if (isset($_POST['redact_ticket_reply'])) {

    validateCSRFToken();

    [$reply, $row] = ticketReplyForRequest($_POST['ticket_reply_id'] ?? 0, 2);
    $ticket_reply_id = intval($reply['ticket_reply_id']);
    $ticket_reply = mysqli_real_escape_string($mysqli, (string) ($_POST['ticket_reply'] ?? ''));

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply = '$ticket_reply' WHERE ticket_reply_id = $ticket_reply_id");

    logAudit("Ticket", "Reply", "$session_name redacted a reply on ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_reply_id);

    flashAlert("Ticket reply redacted");

    redirect();

}

if (isset($_GET['archive_ticket_reply'])) {

    validateCSRFToken();

    [$reply, $row] = ticketReplyForRequest($_GET['archive_ticket_reply'], 2);
    $ticket_reply_id = intval($reply['ticket_reply_id']);

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_archived_at = NOW() WHERE ticket_reply_id = $ticket_reply_id");

    logAudit("Ticket Reply", "Archive", "$session_name archived a reply on ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_reply_id);

    flashAlert("Ticket reply archived", 'error');

    redirect();

}

if (isset($_POST['merge_ticket'])) {

    validateCSRFToken();

    // Both tickets - the one closed by the merge and the one it goes into - have to be reachable
    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $parent = ticketMergeTarget($_POST['merge_into_ticket_id'] ?? 0);

    if (intval($row['ticket_id']) === intval($parent['ticket_id'])) {
        flashAlert("A ticket can't be merged into itself.", 'error');
        redirect();
    }
    if (!empty($row['ticket_closed_at'])) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is closed already.", 'error');
        redirect();
    }
    // Another client's ticket would show them this conversation
    if (intval($row['ticket_client_id']) !== intval($parent['ticket_client_id'])) {
        flashAlert("A ticket merges into one of the same client's - move it to that client first.", 'error');
        redirect();
    }

    ticketMergeInto($row, $parent, escapeSql($_POST['merge_comment'] ?? ''), intval($_POST['merge_move_replies'] ?? 0) === 1);

    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> merged into <strong>" . escapeHtml(ticketReference($parent)) . "</strong>");

    redirect(ticketUrl($parent['ticket_id'], $parent['ticket_client_id']));

}

if (isset($_POST['change_client_ticket'])) {

    validateCSRFToken();

    // The ticket as it is - its current client has to be reachable too
    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $old_client_id = intval($row['ticket_client_id']);

    $client_id = intval($_POST['new_client_id'] ?? 0);
    if (!$client_id || !getFieldById('clients', $client_id, 'client_name')) {
        flashAlert('Pick the client to move it to.', 'error');
        redirect();
    }
    enforceClientAccess();

    $contact_id = ticketClientOwnedId('contact', $_POST['new_contact_id'] ?? 0, $client_id);
    // What was the old client's can't come along - our own vendor and an internal project can
    $vendor_id = ticketClientOwnedId('vendor', $row['ticket_vendor_id'], 0) ?: ticketClientOwnedId('vendor', $row['ticket_vendor_id'], $client_id);
    $project_id = ticketClientOwnedId('project', $row['ticket_project_id'], $client_id);

    // The old client's contact saw the public replies - nobody at the new one should
    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_type = 'Internal' WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_type = 'Public'");

    mysqli_query($mysqli, "UPDATE tickets SET ticket_client_id = $client_id, ticket_contact_id = $contact_id, ticket_asset_id = 0, ticket_location_id = 0,
        ticket_vendor_id = $vendor_id, ticket_project_id = $project_id WHERE ticket_id = $ticket_id LIMIT 1");
    mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id");
    // The driving for it goes with it - not to the old client's site
    mysqli_query($mysqli, "UPDATE trips SET trip_client_id = $client_id, trip_location_id = 0 WHERE trip_ticket_id = $ticket_id");
    applyTicketSla($ticket_id);

    $old_client_name = $old_client_id ? (string) getFieldById('clients', $old_client_id, 'client_name') : 'no client';
    $new_client_name = (string) getFieldById('clients', $client_id, 'client_name');

    logTicketHistory($ticket_id, "$session_name moved the ticket from " . escapeSql($old_client_name) . " to " . escapeSql($new_client_name));

    logAudit("Ticket", "Change", "$session_name moved ticket " . escapeSql(ticketReference($row)) . " from " . escapeSql($old_client_name) . " to " . escapeSql($new_client_name), $client_id, $ticket_id);

    triggerCustomAction('ticket_update', $ticket_id);

    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> moved to <strong>" . escapeHtml($new_client_name) . "</strong>");

    redirect(ticketUrl($ticket_id, $client_id));

}

// Take it - the list's and the board's "Assign to me"
if (isset($_GET['assign_ticket_to_me'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['assign_ticket_to_me'], 2);

    if (!empty($row['ticket_closed_at'])) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is closed.", 'error');
        redirect();
    }
    if (intval($row['ticket_assigned_to']) === intval($session_user_id)) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is yours already.");
        redirect();
    }

    [$ok, $message] = ticketChangeAssignee($row['ticket_id'], $session_user_id);

    flashAlert($message, $ok ? 'success' : 'error');

    redirect();

}

if (isset($_GET['resolve_ticket'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['resolve_ticket'], 2);
    $ticket_id = intval($row['ticket_id']);

    if (!ticketIsOpen($row)) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is resolved or closed already.", 'error');
        redirect();
    }
    $open_tasks = ticketOpenTaskCount($ticket_id);
    if ($open_tasks) {
        flashAlert("Finish the <strong>$open_tasks</strong> open task" . ($open_tasks === 1 ? '' : 's') . " before resolving this ticket.", 'error');
        redirect();
    }

    ticketResolve($row);

    // Client notification email
    if (intval($config_ticket_client_general_notifications) === 1) {
        ticketMailResolved($ticket_id);
    }

    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> resolved");

    redirect();

}

if (isset($_GET['close_ticket'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['close_ticket'], 2);
    $ticket_id = intval($row['ticket_id']);
    $reference = escapeSql(ticketReference($row));

    if (!empty($row['ticket_closed_at'])) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is closed already.", 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = " . TICKET_STATUS_CLOSED . ", ticket_resolved_at = COALESCE(ticket_resolved_at, NOW()), ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);
    setTicketResolutionSlaMet($ticket_id);

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logTicketHistory($ticket_id, "$session_name closed the ticket");

    logAudit("Ticket", "Closed", "$session_name closed ticket $reference", $client_id, $ticket_id);

    triggerCustomAction('ticket_close', $ticket_id);

    // Client notification email
    if (intval($config_ticket_client_general_notifications) === 1) {
        ticketMailClosed($ticket_id);
    }

    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> closed - for more work on it, start a new ticket");

    redirect();

}

if (isset($_GET['reopen_ticket'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['reopen_ticket'], 2);
    $ticket_id = intval($row['ticket_id']);

    // Only a resolved ticket comes back - a closed one is final
    if (!empty($row['ticket_closed_at']) || empty($row['ticket_resolved_at'])) {
        flashAlert(!empty($row['ticket_closed_at']) ? "Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is closed - closed tickets can't be reopened. Start a new one instead." : "Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is open already.", 'error');
        redirect();
    }

    ticketReopen($row);

    flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> reopened");

    redirect();

}

/*
 * Invoice a ticket - its time, at the hours and rate in the window, on a new
 * draft or one of the client's drafts. billingTimeInvoice() does it, the same
 * as Unbilled Time; a ticket that isn't done yet can be billed from here.
 */
if (isset($_POST['add_invoice_from_ticket'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);

    if (!$client_id) {
        flashAlert('Move the ticket to a client before invoicing it.', 'error');
        redirect();
    }

    $result = billingTimeInvoice($client_id, [$ticket_id => (string) ($_POST['qty'] ?? '')], [
        'invoice_id' => intval($_POST['invoice_id'] ?? 0),
        'date' => (string) ($_POST['date'] ?? ''),
        'category_id' => intval($_POST['category'] ?? 0),
        'scope' => (string) ($_POST['scope'] ?? ''),
        'item_name' => (string) ($_POST['item_name'] ?? ''),
        'description' => isset($_POST['item_description']) ? (string) $_POST['item_description'] : null,
        'price' => (string) ($_POST['price'] ?? ''),
        'tax_id' => intval($_POST['tax_id'] ?? 0),
        'include_open' => true,
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    flashAlert('<strong>' . escapeHtml(ticketReference($row)) . '</strong> billed on <strong>' . escapeHtml($result['number']) . '</strong> - ' . billingQuantity($result['hours']) . ' h'
        . ($result['lines'] ? '' : ', with no charge'));

    redirect("invoice.php?invoice_id={$result['invoice_id']}");

}

if (isset($_POST['add_quote_from_ticket'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $reference = escapeSql(ticketReference($row));

    if (!$client_id) {
        flashAlert('Move the ticket to a client before quoting it.', 'error');
        redirect();
    }

    require_once 'quote_model.php';

    $item_name = escapeSql($_POST['item_name'] ?? '');
    $item_description = escapeSql($_POST['item_description'] ?? '');
    $qty = floatval($_POST['qty'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $tax_id = intval($_POST['tax_id'] ?? 0);

    // Totals - at the rate's parts, each to the cent
    if ($tax_id && !taxRow($tax_id)) {
        $tax_id = 0;
    }
    [$subtotal, $tax_amount, $total] = billingLineTax($qty, $price, $tax_id);

    // Atomically increment and get the new quote number
    mysqli_query($mysqli, "UPDATE settings SET config_quote_next_number = LAST_INSERT_ID(config_quote_next_number), config_quote_next_number = config_quote_next_number + 1 WHERE company_id = 1");
    $quote_number = mysqli_insert_id($mysqli);

    // A key for the client's guest link to it
    $quote_url_key = randomString(32);

    mysqli_query($mysqli, "INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_amount = $total, quote_currency_code = '$session_company_currency', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_client_id = $client_id");
    $quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "INSERT INTO quote_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_order = 1, item_tax_id = $tax_id, item_quote_id = $quote_id");
    billingRecalc('quote', $quote_id);

    // Add internal note to ticket, and link to quote in database
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Created quote <a href=\"quote.php?quote_id=$quote_id\">$config_quote_prefix$quote_number</a> for this ticket.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_quote_id = $quote_id WHERE ticket_id = $ticket_id LIMIT 1");

    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Draft', history_description = 'Quote created from Ticket $reference', history_quote_id = $quote_id");
    logAudit("Quote", "Create", "$session_name created quote $config_quote_prefix$quote_number from ticket $reference", $client_id, $quote_id);

    triggerCustomAction('quote_create', $quote_id);

    flashAlert("Quote <strong>" . escapeHtml($config_quote_prefix . $quote_number) . "</strong> created");
    redirect("quote.php?quote_id=$quote_id");

}

if (isExportRequest('export_tickets')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_tickets']);

    // Filters inherited from the tickets page - mirrors agent/tickets.php
    $filter_summary = [];

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND ticket_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter - the global ticket list can be narrowed to one client
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND ticket_client_id = $filter_client_id";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }
    }

    // Status Filter - a set of status IDs, or the Open / Closed shorthand
    if (isset($_POST['status']) && is_array($_POST['status']) && !empty($_POST['status'])) {
        $status_ids = implode(",", array_map('intval', $_POST['status']));
        $ticket_status_snippet = "ticket_status IN ($status_ids)";

        $status_names = [];
        $sql_statuses = mysqli_query($mysqli, "SELECT ticket_status_name FROM ticket_statuses WHERE ticket_status_id IN ($status_ids) ORDER BY ticket_status_name ASC");
        while ($status_row = mysqli_fetch_assoc($sql_statuses)) {
            $status_names[] = $status_row['ticket_status_name'];
        }
        $filter_summary['Status'] = implode(', ', $status_names);
    } elseif (!empty($_POST['resolution']) && $_POST['resolution'] == 'Closed') {
        $ticket_status_snippet = "ticket_resolved_at IS NOT NULL";
        $filter_summary['Status'] = 'Closed';
    } elseif (!empty($_POST['resolution']) && $_POST['resolution'] == 'All') {
        $ticket_status_snippet = "1 = 1";
        $filter_summary['Status'] = 'Open and closed';
    } else {
        // Default - open tickets
        $ticket_status_snippet = "ticket_resolved_at IS NULL";
        $filter_summary['Status'] = 'Open';
    }

    // Billing Filter - same values the tickets page offers
    $ticket_billable_snippet = '';
    if (!empty($_POST['billing'])) {
        if ($_POST['billing'] == 'unbilled') {
            $ticket_billable_snippet = "AND ticket_billable = 1 AND ticket_invoice_id = 0";
            $filter_summary['Billing'] = 'Billable, not invoiced';
        } elseif ($_POST['billing'] == 'invoiced') {
            $ticket_billable_snippet = "AND ticket_invoice_id > 0";
            $filter_summary['Billing'] = 'Invoiced';
        } elseif ($_POST['billing'] == 'nonbillable') {
            $ticket_billable_snippet = "AND ticket_billable = 0";
            $filter_summary['Billing'] = 'Not billable';
        }
    }

    // Category Filter
    if (!empty($_POST['category'])) {
        $filter_category_id = intval($_POST['category']);
        $category_query = "AND (ticket_category = $filter_category_id)";
        $filter_summary['Category'] = getFieldById('categories', $filter_category_id, 'category_name');
    } else {
        // Default - any
        $category_query = '';
    }

    // Assignment Filter
    if (!empty($_POST['assigned'])) {
        if ($_POST['assigned'] == 'unassigned') {
            $ticket_assigned_query = 'AND ticket_assigned_to = 0';
            $filter_summary['Assigned'] = 'Unassigned';
        } else {
            $filter_user_id = intval($_POST['assigned']);
            $ticket_assigned_query = "AND ticket_assigned_to = $filter_user_id";
            $filter_summary['Assigned'] = getFieldById('users', $filter_user_id, 'user_name');
        }
    } else {
        // Default - any
        $ticket_assigned_query = '';
    }

    // SLA State Filter
    $ticket_sla_query = '';
    if (!empty($_POST['sla'])) {
        $sla_filter = $_POST['sla'];
        if ($sla_filter == 'breached') {
            $ticket_sla_query = 'AND ticket_sla_id > 0 AND (ticket_response_sla_alert_stage = 2 OR ticket_resolution_sla_alert_stage = 2 OR ticket_response_sla_met = 0 OR ticket_resolution_sla_met = 0)';
            $filter_summary['SLA'] = 'SLA breached';
        } elseif ($sla_filter == 'at_risk') {
            $ticket_sla_query = 'AND ticket_sla_id > 0 AND COALESCE(ticket_status_pauses_sla, 0) = 0 AND (ticket_response_sla_alert_stage = 1 OR ticket_resolution_sla_alert_stage = 1)';
            $filter_summary['SLA'] = 'SLA at risk';
        } elseif ($sla_filter == 'paused') {
            // Mirrors agent/tickets.php - finished tickets have a verdict, not a pause
            $ticket_sla_query = 'AND ticket_sla_id > 0 AND ticket_status_pauses_sla = 1 AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL';
            $filter_summary['SLA'] = 'SLA paused';
        } elseif ($sla_filter == 'met') {
            $ticket_sla_query = 'AND ticket_sla_id > 0 AND ticket_response_sla_met = 1 AND (ticket_resolution_sla_met = 1 OR ticket_resolution_due_at IS NULL)';
            $filter_summary['SLA'] = 'SLA met';
        } elseif ($sla_filter == 'none') {
            $ticket_sla_query = 'AND ticket_sla_id = 0';
            $filter_summary['SLA'] = 'No SLA';
        }
    }

    // Project Filter
    if (!empty($_POST['project']) && $_POST['project'] > '0') {
        $filter_project_id = intval($_POST['project']);
        $ticket_project_snippet = "AND ticket_project_id = $filter_project_id";
        $filter_summary['Project'] = getFieldById('projects', $filter_project_id, 'project_name');
    } else {
        // Default - any, including tickets without a project
        $ticket_project_snippet = '';
    }

    // Tickets with no client stay visible to restricted agents - clientScopeSql() includes 0
    $access_permission_query_overide = clientScopeSql('ticket_client_id');

    // Date Filter
    $dtf = escapeSql(!empty($_POST['dtf']) ? $_POST['dtf'] : '1970-01-01');
    $dtt = escapeSql(!empty($_POST['dtt']) ? $_POST['dtt'] : '2099-12-31');
    $date_range = formatExportDateRange($dtf, $dtt);
    if ($date_range) {
        $filter_summary['Opened'] = $date_range;
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    // Tags - a ticket with any of them
    $ticket_tag_query = tagExportFilterSql('ticket', $_POST['tags'] ?? [], $filter_summary, 'tickets.ticket_id');

    // Get records from database - same shape as the tickets page list query
    $sql = mysqli_query(
        $mysqli,
        "SELECT category_name, client_name, contact_name, ticket_billable, ticket_closed_at,
        ticket_created_at, ticket_number, ticket_prefix, ticket_priority, ticket_resolved_at,
        ticket_status_name, ticket_subject, user_name, " . tagNamesSql('ticket', 'tickets.ticket_id') . " AS ticket_tags FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN assets ON ticket_asset_id = asset_id
        LEFT JOIN locations ON ticket_location_id = location_id
        LEFT JOIN vendors ON ticket_vendor_id = vendor_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN categories ON ticket_category = category_id
        WHERE $ticket_status_snippet
        $ticket_assigned_query
        $category_query
        AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
        AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_status_name LIKE '%$q%' OR ticket_priority LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR vendor_name LIKE '%$q%'
            " . tagSearchSql('ticket', $q, 'tickets.ticket_id') . ")
        $ticket_tag_query
        $ticket_sla_query
        $ticket_billable_snippet
        $ticket_project_snippet
        $access_permission_query_overide
        $client_query
        ORDER BY ticket_number ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('tickets', $format, $file_name_prepend . 'Tickets', 'Tickets', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            // Per-ticket prefix where the row carries one, config default otherwise
            $row['ticket_number_display'] = ($row['ticket_prefix'] ?: $config_ticket_prefix) . $row['ticket_number'];
            $row['ticket_category_name'] = $row['category_name'];
            $row['ticket_assigned_to'] = $row['user_name'];
            $row['ticket_billable'] = $row['ticket_billable'] ? 'Yes' : 'No';
            $row['ticket_tags'] = (string) $row['ticket_tags'];
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Ticket", "Export", "$session_name exported $num_rows ticket(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

if (isset($_POST['edit_ticket_schedule'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $schedule = ticketScheduleValue($_POST['scheduled_date_time'] ?? '');

    if ($schedule === '') {
        flashAlert('Pick the date and time for the work.', 'error');
        redirect();
    }
    if (!ticketIsOpen($row)) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is resolved or closed - reopen it to schedule work.", 'error');
        redirect();
    }

    $result = ticketSetSchedule($row, $schedule, intval($_POST['onsite'] ?? 0) ? 1 : 0);

    flashAlert("Ticket scheduled for " . escapeHtml($result['when_text']));
    if ($result['conflicts']) {
        flashAlert("The same agent has other work within two hours of it: <br>" . implode(", <br>", array_map('escapeHtml', $result['conflicts'])), 'warning');
    }

    redirect();

}

if (isset($_GET['cancel_ticket_schedule'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['cancel_ticket_schedule'], 2);

    if (empty($row['ticket_schedule'])) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> has no work scheduled.", 'error');
        redirect();
    }

    ticketCancelSchedule($row);

    flashAlert("Ticket schedule cancelled", 'error');

    redirect();

}
