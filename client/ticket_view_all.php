<?php
/*
 * Client Portal
 * Primary contact view: all tickets
 */

require_once 'includes/inc_all.php';


if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: post.php?logout");
    exit();
}

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

$all_tickets = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_status_name, contact_name FROM tickets LEFT JOIN contacts ON ticket_contact_id = contact_id LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id WHERE $ticket_status_snippet AND ticket_client_id = $session_client_id ORDER BY ticket_id DESC");
?>

    <h2>All tickets</h2>
    <div class="col-md-2">
        <div class="form-group">
            <form method="get">
                <label>Ticket Status</label>
                <select class="form-control" name="status" onchange="this.form.submit()">
                    <option value="%" <?php if ($status == "%") {echo "selected";}?> >Any</option>
                    <option value="Open" <?php if ($status == "Open") {echo "selected";}?> >Open</option>
                    <option value="Closed" <?php if ($status == "Closed") {echo "selected";}?> >Closed</option>
                </select>
            </form>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Subject</th>
            <th scope="col">Contact</th>
            <th scope="col">Status</th>
        </tr>
        </thead>
        <tbody>

        <?php
        while ($row = mysqli_fetch_array($all_tickets)) {
            $ticket_id = intval($row['ticket_id']);
            $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = nullable_htmlentities($row['ticket_subject']);
            $ticket_status = nullable_htmlentities($row['ticket_status_name']);
            $ticket_contact_name = nullable_htmlentities($row['contact_name']);

            echo "<tr>";
            echo "<td> <a href='ticket.php?id=$ticket_id'> $ticket_prefix$ticket_number</a></td>";
            echo "<td> <a href='ticket.php?id=$ticket_id'> $ticket_subject</a></td>";
            echo "<td>$ticket_contact_name</td>";
            echo "<td>$ticket_status</td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
    </div>

<?php
require_once 'includes/footer.php';

