<?php $sql = mysqli_query($mysqli,"SELECT * FROM locations WHERE client_id = $client_id ORDER BY location_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-map-marker-alt"></i> Locations</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addLocationModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">

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
            $location_id = $row['location_id'];
            $location_name = $row['location_name'];
            $location_address = $row['location_address'];
            $location_city = $row['location_city'];
            $location_state = $row['location_state'];
            $location_zip = $row['location_zip'];
            $location_phone = $row['location_phone'];
            if(strlen($location_phone)>2){ 
              $location_phone = substr($row['location_phone'],0,3)."-".substr($row['location_phone'],3,3)."-".substr($row['location_phone'],6,4);
            }
            $location_hours = $row['location_hours'];
      
          ?>
          <tr>
            <td><?php echo "$location_name"; ?></td>
            <td><a href="//maps.<?php echo $session_map_source; ?>.com?q=<?php echo "$location_address $location_zip"; ?>" target="_blank"><?php echo "$location_address"; ?></a></td>
            <td><?php echo "$location_phone"; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLocationModal<?php echo $location_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_location=<?php echo $location_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_location_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_location_modal.php"); ?>