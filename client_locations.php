<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_locations WHERE client_id = $client_id ORDER BY client_location_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Name</th>
        <th>Address</th>
        <th>Phone</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_location_id = $row['client_location_id'];
        $client_location_name = $row['client_location_name'];
        $client_location_address = $row['client_location_address'];
        $client_location_city = $row['client_location_city'];
        $client_location_state = $row['client_location_state'];
        $client_location_zip = $row['client_location_zip'];
        $client_location_phone = $row['client_location_phone'];
        if(strlen($client_location_phone)>2){ 
          $client_location_phone = substr($row['client_location_phone'],0,3)."-".substr($row['client_location_phone'],3,3)."-".substr($row['client_location_phone'],6,4);
        }
  
      ?>
      <tr>
        <td><?php echo "$client_location_name"; ?></td>
        <td><a href="https://maps.google.com?q=<?php echo "$client_location_address $client_location_zip"; ?>" target="_blank"><?php echo "$client_location_address"; ?></td>
        <td><?php echo "$client_location_phone"; ?></td>
        <td>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientLocationModal<?php echo $client_location_id; ?>">Edit</a>
              <a class="dropdown-item" href="post.php?delete_client_location=<?php echo $client_location_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_location_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>