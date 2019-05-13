<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM mileage ORDER BY mileage_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-bicycle mr-2"></i>Mileage</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addMileageModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
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
            $mileage_id = $row['mileage_id'];
            $mileage_date = $row['mileage_date'];
            $mileage_purpose = $row['mileage_purpose'];
            $mileage_starting_location = $row['mileage_starting_location'];
            $mileage_destination = $row['mileage_destination'];
            $mileage_miles = $row['mileage_miles'];

          ?>
          <tr>
            <td><?php echo $mileage_date; ?></td>
            <td><?php echo $mileage_purpose; ?></td>
            <td><?php echo $mileage_starting_location; ?></td>
            <td><?php echo $mileage_destination; ?></td>
            <td><?php echo $mileage_miles; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editMileageModal<?php echo $mileage_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_mileage=<?php echo $mileage_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_mileage_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_mileage_modal.php"); ?>

<?php include("footer.php");