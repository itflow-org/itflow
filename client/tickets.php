<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";


// Ticket status from GET
if (!isset($_GET['status']) || ($_GET['status']) == 'Open') {
    // Default to showing open
    $status = 'Open';
    $ticket_status_snippet = "ticket_closed_at IS NULL";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Closed') {
    $status = 'Closed';
    $ticket_status_snippet = "ticket_closed_at IS NOT NULL";
} else {
    $status = '%';
    $ticket_status_snippet = "ticket_status LIKE '%'";
}

$contact_tickets = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_status_name FROM tickets LEFT JOIN contacts ON ticket_contact_id = contact_id LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id WHERE $ticket_status_snippet AND ticket_contact_id = $session_contact_id AND ticket_client_id = $session_client_id ORDER BY ticket_id DESC");

//Get Total tickets closed
$sql_total_tickets_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_closed FROM tickets WHERE ticket_closed_at IS NOT NULL AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_array($sql_total_tickets_closed);
$total_tickets_closed = intval($row['total_tickets_closed']);

//Get Total tickets open
$sql_total_tickets_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_open FROM tickets WHERE ticket_closed_at IS NULL AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_array($sql_total_tickets_open);
$total_tickets_open = intval($row['total_tickets_open']);

//Get Total tickets
$sql_total_tickets = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets FROM tickets WHERE ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_array($sql_total_tickets);
$total_tickets = intval($row['total_tickets']);


?>

<h3>Tickets</h3>
<div class="row">

    <div class="col-md-10">

        <table class="table tabled-bordered border border-dark">
            <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>

            <?php
            while ($row = mysqli_fetch_array($contact_tickets)) {
                $ticket_id = intval($row['ticket_id']);
                $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                $ticket_number = intval($row['ticket_number']);
                $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                $ticket_status = nullable_htmlentities($row['ticket_status_name']);
            ?>

                <tr>
                    <td>
                        <a href="ticket.php?id=<?php echo $ticket_id; ?>"><?php echo "$ticket_prefix$ticket_number"; ?></a>
                    </td>
                    <td>
                        <a href="ticket.php?id=<?php echo $ticket_id; ?>"><?php echo $ticket_subject; ?></a>
                    </td>
                    <td><?php echo $ticket_status; ?></td>
                </tr>
            <?php
            }
            ?>
            </tbody>
        </table>

    </div>

    <div class="col-md-2">

        <a href="ticket_add.php" class="btn btn-primary btn-block">New ticket</a>

        <hr>

        <a href="?status=Open" class="btn btn-danger btn-block p-3 mb-3 text-left">My Open tickets | <strong><?php echo $total_tickets_open ?></strong></a>

        <a href="?status=Closed" class="btn btn-success btn-block p-3 mb-3 text-left">Closed tickets | <strong><?php echo $total_tickets_closed ?></strong></a>

        <a href="?status=%" class="btn btn-secondary btn-block p-3 mb-3 text-left">All my tickets | <strong><?php echo $total_tickets ?></strong></a>
        <?php
        if ($session_contact_primary == 1 || $session_contact_is_technical_contact) {
        ?>

        <hr>

        <a href="ticket_view_all.php" class="btn btn-dark btn-block p-2 mb-3">All Tickets</a>

        <?php
        }
        ?>

    </div>
</div>

<?php require_once "includes/footer.php";
 ?>
