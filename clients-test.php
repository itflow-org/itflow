<?php include("header.php");

$sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN contacts ON clients.primary_contact = contacts.contact_id LEFT JOIN locations ON clients.primary_location = locations.location_id");

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-users"></i> Clients</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-fw fa-plus"></i> New Client</button>
    </div>
  </div>

  <div class="card-body">
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-hover table-borderless">
        <thead>
          <tr>
            <th>Client ID</th>
            <th>Client</th>
            <th>Contact Name</th>
            <th>Location</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $contact_name = $row['contact_name'];
            $location_address = $row['location_address'];
      
          ?>
          <tr>
            <td><?php echo $client_id; ?></td>
            <td><?php echo $client_name; ?></td>
            <td><?php echo $contact_name; ?></td>
            <td><?php echo $location_address; ?></td>
          </tr>

          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("footer.php");
