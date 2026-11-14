<?php

// Variable assignment from POST (or: blank/from DB is updating)
//
// What a ticket links to - its contact, asset and vendor - has to be its own
// client's (ticketClientOwnedId() in functions/ticket.php, the check the app
// itself uses); anything else is dropped, not saved. The agent has to be an
// active one.

if (isset($_POST['ticket_contact_id'])) {
    $contact = ticketClientOwnedId('contact', $_POST['ticket_contact_id'], $client_id);
} elseif ($ticket_row) {
    $contact = intval($ticket_row['ticket_contact_id']);
} else {
    $contact = 0;
}

if (isset($_POST['ticket_asset_id'])) {
    $asset = ticketClientOwnedId('asset', $_POST['ticket_asset_id'], $client_id);
} elseif ($ticket_row) {
    $asset = intval($ticket_row['ticket_asset_id']);
} else {
    $asset = 0;
}

if (isset($_POST['ticket_subject'])) {
    $subject = escapeSql($_POST['ticket_subject']);
} elseif ($ticket_row) {
    $subject = mysqli_real_escape_string($mysqli, $ticket_row['ticket_subject']);
} else {
    $subject = '';
}


// Priority has to be one of the four the rest of the app is built around:
// sla_assignments are keyed on these exact strings, and the ticket list and
// kanban both fall through to the Low styling for anything else, so an
// unrecognised value silently reads as the lowest priority everywhere.
// Anything else is normalised away rather than rejected, matching how this
// model already handles a non-integer client_id.
$allowed_priorities = ['Low', 'Medium', 'High', 'Urgent'];

if (isset($_POST['ticket_priority']) && in_array($_POST['ticket_priority'], $allowed_priorities, true)) {
    $priority = escapeSql($_POST['ticket_priority']);
} elseif ($ticket_row) {
    $priority = mysqli_real_escape_string($mysqli, $ticket_row['ticket_priority']);
} else {
    $priority = 'Medium';
}


if (isset($_POST['ticket_details'])) {
    $details = mysqli_real_escape_string($mysqli, $_POST['ticket_details'] . "<br>");
} elseif ($ticket_row) {
    $details = mysqli_real_escape_string($mysqli, $ticket_row['ticket_details']);
} else {
    $details = '< blank ><br>';
}

if (isset($_POST['ticket_vendor_id'])) {
    $vendor_id = ticketClientOwnedId('vendor', $_POST['ticket_vendor_id'], $client_id);
} elseif ($ticket_row) {
    $vendor_id = intval($ticket_row['ticket_vendor_id']);
} else {
    $vendor_id = 0;
}

// The vendor's own reference for it - a string (case numbers have letters in
// them). ticket_vendor_ticket_id is the name the API used to take.
$vendor_ticket_input = $_POST['ticket_vendor_ticket_number'] ?? $_POST['ticket_vendor_ticket_id'] ?? null;
if ($vendor_ticket_input !== null) {
    $vendor_ticket_number = escapeSql(mb_substr(trim((string) $vendor_ticket_input), 0, 255));
} elseif ($ticket_row) {
    $vendor_ticket_number = escapeSql((string) $ticket_row['ticket_vendor_ticket_number']);
} else {
    $vendor_ticket_number = '';
}

if (isset($_POST['ticket_assigned_to'])) {
    $assigned_to = ticketAgentId($_POST['ticket_assigned_to']);
} elseif ($ticket_row) {
    $assigned_to = intval($ticket_row['ticket_assigned_to']);
} else {
    $assigned_to = 0;
}

if (isset($_POST['ticket_billable'])) {
    $billable = intval($_POST['ticket_billable']) ? 1 : 0;
} elseif ($ticket_row) {
    $billable = intval($ticket_row['ticket_billable']);
} else {
    $billable = 0;
}

// The project it is part of - one of its client's, or an internal one, as the
// ticket's Project field allows (ticketClientOwnedId()). 0 takes it off. Any
// other is turned down, so a wrong id doesn't take it off the one it's on.
if (isset($_POST['ticket_project_id'])) {
    $ticket_project_id = ticketClientOwnedId('project', $_POST['ticket_project_id'], $client_id);
    if (intval($_POST['ticket_project_id']) && !$ticket_project_id) {
        header("HTTP/1.1 400 Bad Request");
        $return_arr['success'] = "False";
        $return_arr['message'] = "ticket_project_id " . intval($_POST['ticket_project_id']) . " isn't one of the client's projects or an internal one.";
        echo json_encode($return_arr);
        exit();
    }
} elseif ($ticket_row) {
    $ticket_project_id = intval($ticket_row['ticket_project_id']);
} else {
    $ticket_project_id = 0;
}
