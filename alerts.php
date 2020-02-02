<?php include("header.php"); ?>

<?php 

$sql = mysqli_query($mysqli,"SELECT * FROM alerts WHERE alert_ack_date IS NULL AND company_id = $session_company_id ORDER BY alert_id DESC"); 

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-exclamation-triangle"></i> Alerts</h6>
    <a href="post.php?ack_all_alerts" class="btn btn-success btn-sm badge-pill float-right mr-2"> <i class="fa fa-check"></i> Acknowledge All</a>
    <a href="alerts_archived.php" class="btn btn-secondary btn-sm badge-pill float-right mr-2">Archived</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Alert</th>
            <th class="text-center">Ack</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $alert_id = $row['alert_id'];
            $alert_type = $row['alert_type'];
            $alert_message = $row['alert_message'];
            $alert_date = $row['alert_date'];

          ?>
          <tr class="row-danger">
            <td><?php echo $alert_date; ?></td>
            <td><?php echo $alert_type; ?></td>
            <td><?php echo $alert_message; ?></td>
            <td class="text-center"><a class="btn btn-success btn-sm" href="post.php?alert_ack=<?php echo $alert_id; ?>"><i class="fa fa-check"></a></td>
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