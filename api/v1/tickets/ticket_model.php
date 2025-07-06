<?php

// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['ticket_contact_id'])) {
    $contact = intval($_POST['ticket_contact_id']);
} elseif ($ticket_row) {
    $contact = $ticket_row['ticket_contact_id'];
} else {
    $contact = '0';
}

if (isset($_POST['ticket_subject'])) {
    $subject = sanitizeInput($_POST['ticket_subject']);
} elseif ($ticket_row) {
    $subject = $ticket_row['ticket_subject'];
} else {
    $subject = '';
}


if (isset($_POST['ticket_priority'])) {
    $priority = sanitizeInput($_POST['ticket_priority']);
} elseif ($ticket_row) {
    $priority = $ticket_row['ticket_priority'];
} else {
    $priority = 'Low';
}


if (isset($_POST['ticket_details'])) {
    $details = sanitizeInput($_POST['ticket_details']) . "<br>";
} elseif ($ticket_row) {
    $details = $ticket_row['ticket_details'];
} else {
    $details = '< blank ><br>';
}

if (isset($_POST['ticket_vendor_id'])) {
    $vendor_id = intval($_POST['ticket_vendor_id']);
} elseif ($ticket_row) {
    $vendor_id = $ticket_row['ticket_vendor_id'];
} else {
    $vendor_id = '0';
}

if (isset($_POST['ticket_vendor_ticket_id'])) {
    $vendor_ticket_number = intval($_POST['ticket_vendor_ticket_id']);
} elseif ($ticket_row) {
    $vendor_ticket_number = $ticket_row['ticket_vendor_ticket_id'];
} else {
    $vendor_ticket_number = '0';
}

if (isset($_POST['ticket_assigned_to'])) {
    $assigned_to = intval($_POST['ticket_assigned_to']);
} elseif ($ticket_row) {
    $assigned_to = $ticket_row['ticket_assigned_to'];
} else {
    $assigned_to = '0';
}

if (isset($_POST['ticket_billable'])) {
    $billable = intval($_POST['ticket_billable']);
} elseif ($ticket_row) {
    $billable = $ticket_row['ticket_billable'];
} else {
    $billable = '0';
}
