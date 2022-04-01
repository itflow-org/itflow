<?php
/*
 * Client Portal
 * Primary contact view: all tickets
 */

require('inc_portal.php');

if($session_contact_id !== $session_client_primary_contact_id){
  header("Location: portal_post.php?logout");
  exit();
}

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

$all_tickets = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN contacts ON ticket_contact_id = contact_id WHERE $ticket_status_snippet AND ticket_client_id = '$session_client_id' ORDER BY ticket_id DESC");
?>

  <h2>All tickets</h2>
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
      <th scope="col">#</th>
      <th scope="col">Subject</th>
      <th scope="col">Contact</th>
      <th scope="col">Status</th>
    </tr>
    </thead>
    <tbody>

    <?php
    while($ticket = mysqli_fetch_array($all_tickets)){
      echo "<tr>";
        echo "<td> <a href='ticket.php?id=$ticket[ticket_id]'> $ticket[ticket_prefix]$ticket[ticket_id]</a></td>";
        echo "<td> <a href='ticket.php?id=$ticket[ticket_id]'> $ticket[ticket_subject]</a></td>";
        echo "<td>$ticket[contact_name]</td>";
        echo "<td>$ticket[ticket_status]</td>";
      echo "</tr>";
    }
    ?>
    </tbody>
  </table>
  </div>

<?php
include('portal_footer.php');