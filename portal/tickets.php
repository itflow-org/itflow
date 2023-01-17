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

<div class="row">
    <div class="col-1 text-center">
        <?php if (!empty($session_contact_photo)) { ?>
            <img src="<?php echo "../uploads/clients/$session_company_id/$session_client_id/$session_contact_photo"; ?>" alt="..." height="50" width="50" class="img-circle img-responsive">

        <?php } else { ?>

            <span class="fa-stack fa-2x rounded-left">
                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                <span class="fa fa-stack-1x text-white"><?php echo $session_contact_initials; ?></span>
            </span>
        <?php } ?>
    </div>

    <div class="col-11 p-0">
        <h4>Welcome, <strong><?php echo $session_contact_name ?></strong>!</h4>
        <hr>
    </div>

</div>

<br>

<div class="row">

    <div class="col-10">
        
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
                $ticket_id = $row['ticket_id'];
                $ticket_prefix = htmlentities($row['ticket_prefix']);
                $ticket_number = $row['ticket_number'];
                $ticket_subject = htmlentities($row['ticket_subject']);
                $ticket_status = htmlentities($row['ticket_status']);
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

    <div class="col-2">

        <a href="ticket_add.php" class="btn btn-primary btn-block">New ticket</a>

        <hr>

        <a href="?status=Open" class="btn btn-danger btn-block p-3 mb-3 text-left">My Open tickets | <strong><?php echo $total_tickets_open ?></strong></a>

        <a href="?status=Closed" class="btn btn-success btn-block p-3 mb-3 text-left">Resolved tickets | <strong><?php echo $total_tickets_closed ?></strong></a>

        <a href="?status=%" class="btn btn-secondary btn-block p-3 mb-3 text-left">All my tickets | <strong><?php echo $total_tickets ?></strong></a>
        <?php
        if($session_contact_id == $session_client_primary_contact_id){
        ?>
        
        <hr>
        
        <a href="ticket_view_all.php" class="btn btn-dark btn-block p-2 mb-3">All Tickets</a>

        <?php
        }
        ?>

    </div>
</div>

<?php require_once("portal_footer.php"); ?>