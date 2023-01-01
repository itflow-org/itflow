<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

require_once("inc_portal.php");

// Ticket status from GET
if (!isset($_GET['status'])) {
    // If nothing is set, assume we only want to see open tickets
    $status = 'Open';
    $ticket_status_snippet = "ticket_status != 'Closed'";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Open') {
    $status = 'Open';
    $ticket_status_snippet = "ticket_status != 'Closed'";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Closed') {
    $status = 'Closed';
    $ticket_status_snippet = "ticket_status = 'Closed'";
} else {
    $status = '%';
    $ticket_status_snippet = "ticket_status LIKE '%'";
}

$contact_tickets = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN contacts ON ticket_contact_id = contact_id WHERE $ticket_status_snippet AND ticket_contact_id = '$session_contact_id' AND ticket_client_id = '$session_client_id' ORDER BY ticket_id DESC");

//Get Total tickets closed
$sql_total_tickets_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_closed FROM tickets WHERE ticket_status = 'Closed' AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_array($sql_total_tickets_closed);
$total_tickets_closed = $row['total_tickets_closed'];

//Get Total tickets open
$sql_total_tickets_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets_open FROM tickets WHERE ticket_status != 'Closed' AND ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_array($sql_total_tickets_open);
$total_tickets_open = $row['total_tickets_open'];

//Get Total tickets 
$sql_total_tickets = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS total_tickets FROM tickets WHERE  ticket_client_id = $session_client_id AND ticket_contact_id = $session_contact_id");
$row = mysqli_fetch_array($sql_total_tickets);
$total_tickets = $row['total_tickets'];


?>
    <table>
        <tr>
            <th class="text-center">
                <?php if (!empty($session_contact_photo)) { ?>
                    <img src="<?php echo "../uploads/clients/$session_company_id/$session_client_id/$session_contact_photo"; ?>" alt="..." class=" img-size-50 img-circle">

                <?php } else { ?>

                    <span class="fa-stack fa-2x rounded-left">
        <i class="fa fa-circle fa-stack-2x text-secondary"></i>
        <span class="fa fa-stack-1x text-white"><?php echo $session_contact_initials; ?></span>
      </span>
                    <br>

                <?php } ?>
                <div class="text-dark"><?php echo $session_contact_name; ?></div>
                <div><?php echo $session_contact_title; ?></div>
            </th>
            <th>
                <div class="">
                    <h4 class="">Welcome, <b><?php echo $session_contact_name ?></b>!</h4>
                    <hr>
                </div>
            </th>
        </tr>
    </table>

    <br>

    <div class="row">

        <div class="col-10">
            <div class="card">
      <span class="border border-secondary">
        <table class="table">
          <thead class="thead-dark">
            <tr>
              <th scope="col">#</th>
              <th scope="col">Subject</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>

            <?php
            while ($ticket = mysqli_fetch_array($contact_tickets)) {
                echo "<tr>";
                echo "<td> <a href='ticket.php?id=$ticket[ticket_id]'> $ticket[ticket_prefix]$ticket[ticket_number]</a></td>";
                echo "<td> <a href='ticket.php?id=$ticket[ticket_id]'> $ticket[ticket_subject]</a></td>";
                echo "<td>$ticket[ticket_status]</td>";
                echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </span>
            </div>
        </div>
        <div class="col-2">

            <div class="card">
                <a href="ticket_add.php" class="btn btn-primary">New ticket</a>
            </div>

            <hr>

            <a href="?status=Open">
                <div class="card text-white bg-danger mb-3" style="max-width: 18rem;">
                    <div class="card-header">My Open tickets | <b><?php echo $total_tickets_open ?></b></div>
                </div>
            </a>

            <a href="?status=Closed">
                <div class="card text-white bg-success mb-3" style="max-width: 18rem;">
                    <div class="card-header">Resolved tickets | <b><?php echo $total_tickets_closed ?></b></div>
                </div>
            </a>

            <a href="?status=%">
                <div class="card text-white bg-secondary mb-3"  style="max-width: 18rem;">
                    <div class="card-header">All my tickets | <b><?php echo $total_tickets ?></b></div>
                </div>
            </a>

        </div>
    </div>

<?php require_once("portal_footer.php"); ?>