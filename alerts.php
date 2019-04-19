<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM alerts ORDER BY alert_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-users"></i> Alerts</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Alert</th>
            <th>Ack</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $alert_id = $row['alert_id'];
            $alert_type = $row['alert_type'];
            $alert_message = $row['alert_message'];
            $alert_date = $row['alert_date'];
            $alert_read = $row['alert_read'];

          ?>
          <tr>
            <td><?php echo $alert_date; ?></td>
            <td><?php echo $alert_type; ?></td>
            <td><?php echo $alert_message; ?></td>
            <td><?php echo $alert_read; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="post.php?delete_alert=<?php echo $client_id; ?>">Delete</a>
                </div>
              </div>      
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

<?php include("footer.php");