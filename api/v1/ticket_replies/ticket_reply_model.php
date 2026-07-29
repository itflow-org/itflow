<?php

// Variable assignment from POST (or: blank/from DB if updating)

if (isset($_POST['ticket_reply'])) {
    $reply = mysqli_real_escape_string($mysqli, $_POST['ticket_reply']);
} elseif ($ticket_reply_row) {
    $reply = mysqli_real_escape_string($mysqli, $ticket_reply_row['ticket_reply']);
} else {
    $reply = '';
}

// Reply type - defaults to Internal so an integration can't accidentally email a
// client. 'Client' is reserved for inbound contact replies (the email parser) and
// is not accepted here.
if (isset($_POST['ticket_reply_type'])) {
    $reply_type = ucfirst(strtolower($_POST['ticket_reply_type']));
    if (!in_array($reply_type, ['Internal', 'Public'], true)) {
        $reply_type = 'Internal';
    }
} elseif ($ticket_reply_row) {
    $reply_type = escapeSql($ticket_reply_row['ticket_reply_type']);
} else {
    $reply_type = 'Internal';
}

// Time worked - HH:MM:SS. Defaults to none: the API isn't a technician at a keyboard,
// so time only gets logged when the caller explicitly says so.
if (isset($_POST['ticket_reply_time_worked'])) {
    $reply_time_worked = escapeSql($_POST['ticket_reply_time_worked']);
    if (!preg_match('/^\d{1,3}:[0-5]\d:[0-5]\d$/', $reply_time_worked)) {
        $reply_time_worked = '00:00:00';
    }
} elseif ($ticket_reply_row) {
    $reply_time_worked = escapeSql($ticket_reply_row['ticket_reply_time_worked']);
} else {
    $reply_time_worked = '00:00:00';
}

// Optional status change alongside the reply (0 = leave the ticket status as-is)
if (isset($_POST['ticket_status'])) {
    $reply_ticket_status = intval($_POST['ticket_status']);
} else {
    $reply_ticket_status = 0;
}
