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
?>

<h2>Welcome, <?php echo $session_contact_name ?>, to the <?php echo $config_app_name ?> client portal.</h2>
<br>
<h2>My tickets</h2>
<div class="col-md-2">
  <div class="form-group">
    <form method="get">
      <label>Ticket Status</label>
      <select class="form-control" name="status" onchange="this.form.submit()">
        <option value="%" <?php if($status == "%"){echo "selected";}?> >Any</option>
        <option value="Open" <?php if($status == "Open"){echo "selected";}?> >Open</option>
        <option value="Closed" <?php if($status == "Closed"){echo "selected";}?> >Closed</option>
      </select>
    </form>
  </div>
</div>
<table class="table">
  <thead>
    <tr>
      <th scope="col">Subject</th>
      <th scope="col">Status</th>
    </tr>
  </thead>
  <tbody>

      <?php
      while($ticket = mysqli_fetch_array($contact_tickets)){
        echo "<tr>";
        echo "<td> <a href='ticket.php?id=$ticket[ticket_id]'> $ticket[ticket_subject]</a></td>";
        echo "<td>$ticket[ticket_status]</td>";
        echo "</tr>";
      }
      ?>
  </tbody>
</table>
</div>

<?php include("portal_footer.php"); ?>