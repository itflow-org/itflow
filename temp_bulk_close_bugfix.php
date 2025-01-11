<?php require_once "includes/inc_all.php"; ?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
    <li class="breadcrumb-item">
        <a href="index.html">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Temp bugfix page</li>
</ol>

<!-- Page Content -->
<h1>Temporary page to fix bulk ticket close/resolution bug</h1>
<hr>
<p>Navigate back to tickets - all bulk closed tickets should be fixed now and no longer appear as open.</p>

<?php

$sql_tickets = mysqli_query($mysqli, "SELECT ticket_id, ticket_updated_at, ticket_closed_at FROM tickets WHERE ticket_resolved_at IS NULL AND ticket_closed_at IS NOT NULL");
foreach ($sql_tickets as $row) {
    $ticket_id = intval($row['ticket_id']);
    $ticket_updated_at = sanitizeInput($row['ticket_updated_at']); // To keep old updated_at time
    $ticket_closed_at = sanitizeInput($row['ticket_closed_at']); // To keep the original closed time
    mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = '$ticket_closed_at', ticket_updated_at = '$ticket_updated_at' WHERE ticket_id = '$ticket_id'");
}

?>

<?php require_once "includes/footer.php";
