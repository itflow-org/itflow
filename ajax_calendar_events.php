<?php
require_once "config.php";

require_once "functions.php";

require_once "check_login.php";

require_once "rfc6238.php";

$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date)); // Last day of the month
?>

<h1><?php echo "Start: $start_date"; ?></h1>
<h1><?php echo "End: $end_date"; ?></h1>
<?php
$events = [];

// Fetch events
$sql = mysqli_query($mysqli, "SELECT * FROM events LEFT JOIN calendars ON event_calendar_id = calendar_id WHERE event_start BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $events[] = [
        'id' => intval($row['event_id']),
        'title' => $row['event_title'],
        'start' => $row['event_start'],
        'end' => $row['event_end'],
        'color' => $row['calendar_color']
    ];
}

// Fetch invoices created
$sql = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN invoices ON client_id = invoice_client_id WHERE invoice_date BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $events[] = [
        'id' => intval($row['invoice_id']),
        'title' => $row['invoice_prefix'] . $row['invoice_number'] . " created -scope: " . $row['invoice_scope'],
        'start' => $row['invoice_date'],
        'display' => 'list-item',
        'color' => 'blue',
        'url' => 'invoice.php?invoice_id=' . $row['invoice_id']
    ];
}

// Fetch quotes created
$sql = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN quotes ON client_id = quote_client_id WHERE quote_date BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $events[] = [
        'id' => intval($row['quote_id']),
        'title' => $row['quote_prefix'] . $row['quote_number'] . " " . $row['quote_scope'],
        'start' => $row['quote_date'],
        'display' => 'list-item',
        'color' => 'purple',
        'url' => 'quote.php?quote_id=' . $row['quote_id']
    ];
}

// Fetch tickets created
$sql = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN tickets ON client_id = ticket_client_id LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id LEFT JOIN users ON ticket_assigned_to = user_id WHERE ticket_created_at BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $username = $row['user_name'];
    $username = empty($username) ? "" : "[". substr($row['user_name'], 0, 9) . "...]";
    $events[] = [
        'id' => intval($row['ticket_id']),
        'title' => $row['ticket_prefix'] . $row['ticket_number'] . " created - " . $row['ticket_subject'] . " " . $username . "{" . $row['ticket_status_name'] . "}",
        'start' => $row['ticket_created_at'],
        'color' => getTicketColor($row['ticket_status']),
        'url' => 'ticket.php?ticket_id=' . $row['ticket_id']
    ];
}

// Fetch recurring tickets
$sql = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN scheduled_tickets ON client_id = scheduled_ticket_client_id LEFT JOIN users ON scheduled_ticket_assigned_to = user_id WHERE scheduled_ticket_next_run BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $username = $row['user_name'];
    $username = empty($username) ? "" : "[". substr($row['user_name'], 0, 9) . "...]";
    $events[] = [
        'id' => intval($row['scheduled_ticket_id']),
        'title' => "R Ticket (" . $row['scheduled_ticket_frequency'] . ") - " . $row['scheduled_ticket_subject'] . " " . $username,
        'start' => $row['scheduled_ticket_next_run'],
        'color' => $row['calendar_color'],
        'url' => 'client_recurring_tickets.php?client_id=' . $row['client_id']
    ];
}

// Fetch scheduled tickets
$sql = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN tickets ON client_id = ticket_client_id LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id LEFT JOIN users ON ticket_assigned_to = user_id WHERE ticket_schedule BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $username = $row['user_name'];
    $username = empty($username) ? "" : substr($row['user_name'], 0, 9) . "...";
    $events[] = [
        'id' => intval($row['ticket_id']),
        'title' => $row['ticket_prefix'] . $row['ticket_number'] . " scheduled - " . $row['ticket_subject'] . " [" . $username . "]{" . $row['ticket_status_name'] . "}",
        'start' => $row['ticket_schedule'],
        'color' => strtotime($row['ticket_schedule']) < time() ? (!empty($row['ticket_schedule']) ? "red" : "green") : "grey",
        'url' => 'ticket.php?ticket_id=' . $row['ticket_id']
    ];
}

// Fetch vendors created
$sql = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN vendors ON client_id = vendor_client_id WHERE vendor_template = 0 AND vendor_created_at BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $events[] = [
        'id' => intval($row['vendor_id']),
        'title' => "Vendor : '" . $row['vendor_name'] . "' created",
        'start' => $row['vendor_created_at'],
        'color' => 'brown',
        'url' => 'client_vendors.php?client_id=' . $row['client_id']
    ];
}

// Fetch clients added
$sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_created_at BETWEEN '$start_date' AND '$end_date'");
while ($row = mysqli_fetch_array($sql)) {
    $events[] = [
        'id' => intval($row['client_id']),
        'title' => "Client: '" . $row['client_name'] . "' created",
        'start' => $row['client_created_at'],
        'color' => 'brown',
        'url' => 'client_overview.php?client_id=' . $row['client_id']
    ];
}

echo json_encode($events);

function getTicketColor($status) {
    switch ($status) {
        case 1:
            return 'red';
        case 2:
            return 'blue';
        case 3:
            return 'grey';
        default:
            return 'black';
    }
}
?>