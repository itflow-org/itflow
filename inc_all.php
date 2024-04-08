<?php

require_once "config.php";

include_once "functions.php";

require_once "check_login.php";

require_once "header.php";

require_once "top_nav.php";

// Get Main Side Bar Badge Counts

// Active Ticket Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_closed_at IS NULL AND ticket_status != 4"));
$num_active_tickets = $row['num'];

// Active Project Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('project_id') AS num FROM projects WHERE project_archived_at IS NULL AND project_completed_at IS NULL"));
$num_active_projects = $row['num'];

// Open Invoices Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE (invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial') AND invoice_archived_at IS NULL"));
$num_open_invoices = $row['num'];

require_once "side_nav.php";

require_once "inc_wrapper.php";

require_once "inc_alert_feedback.php";

require_once "pagination_head.php";

?>
