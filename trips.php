<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM trips ORDER BY trip_id DESC"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-bicycle mr-2"></i>Trips</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addTripModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Date</th>
            <th>Purpose</th>
            <th>From</th>
            <th>To</th>
            <th>Miles</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $trip_id = $row['trip_id'];
            $trip_date = $row['trip_date'];
            $trip_purpose = $row['trip_purpose'];
            $trip_starting_location = $row['trip_starting_location'];
            $trip_destination = $row['trip_destination'];
            $trip_miles = $row['trip_miles'];
            $client_id = $row['client_id'];
            $invoice_id = $row['invoice_id'];
            $location_id = $row['location_id'];
            $vendor_id = $row['vendor_id'];

          ?>
          <tr>
            <td><?php echo $trip_date; ?></td>
            <td><?php echo $trip_purpose; ?></td>
            <td><?php echo $trip_starting_location; ?></td>
            <td><?php echo $trip_destination; ?></td>
            <td><?php echo $trip_miles; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="//maps.google.com?q=<?php echo $trip_starting_location; ?> to <?php echo $trip_destination; ?>" target="_blank">Map it</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addTripCopyModal<?php echo $trip_id; ?>">Copy</a>
                  <a class="dropdown-item" href="post.php?delete_trip=<?php echo $trip_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_trip_modal.php"); ?>
              <?php include("add_trip_copy_modal.php"); ?>
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_trip_modal.php"); ?>

<?php include("footer.php");