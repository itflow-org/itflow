<?php
// Get Main Side Bar Badge Counts

// Active Clients Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('client_id') AS num FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . ""));
$num_active_clients = $row['num'];

// Active Ticket Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_closed_at IS NULL AND ticket_status != 4 " . clientScopeSql('ticket_client_id') . ""));
$num_active_tickets = $row['num'];

// Recurring Ticket Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_ticket_id') AS num FROM recurring_tickets WHERE 1 = 1 " . clientScopeSql('recurring_ticket_client_id') . ""));
$num_recurring_tickets = $row['num'];

// Active Project Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('project_id') AS num FROM projects WHERE project_archived_at IS NULL AND project_completed_at IS NULL " . clientScopeSql('project_client_id') . ""));
$num_active_projects = $row['num'];

// Open Invoices Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE (invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial' OR invoice_status = 'Draft') AND invoice_archived_at IS NULL " . clientScopeSql('invoice_client_id') . ""));
$num_open_invoices = $row['num'];

// Recurring Invoice Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_invoice_id') AS num FROM recurring_invoices WHERE recurring_invoice_archived_at IS NULL " . clientScopeSql('recurring_invoice_client_id') . ""));
$num_recurring_invoices = $row['num'];

// Open Quotes Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('quote_id') AS num FROM quotes WHERE (quote_status = 'Sent' OR quote_status = 'Viewed') AND quote_archived_at IS NULL " . clientScopeSql('quote_client_id') . ""));
$num_open_quotes = $row['num'];

// Recurring Expenses Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_expense_id') AS num FROM recurring_expenses WHERE recurring_expense_archived_at IS NULL"));
$num_recurring_expenses = $row['num'];
