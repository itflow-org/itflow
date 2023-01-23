<?php include("inc_all_client.php"); ?>

<?php 

if (!empty($_GET['sb'])) {
  $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
}else{
  $sb = "log_id";
}

// Reverse default sort
if (!isset($_GET['o'])) {
  $o = "DESC";
  $disp = "ASC";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM logs
  LEFT JOIN users ON log_user_id = user_id
  WHERE (log_type LIKE '%$q%' OR log_action LIKE '%$q%' OR log_description LIKE '%$q%' OR log_ip LIKE '%$q%' OR log_user_agent LIKE '%$q%' OR user_name LIKE '%$q%')
  AND log_client_id = $client_id
  ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-3">
    <h3 class="card-title"><i class="fa fa-fw fa-eye"></i> Audit Logs</h3>
  </div>

  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Logs">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive border">
      <table class="table table-hover">
        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_created_at&o=<?php echo $disp; ?>">Timestamp</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_name&o=<?php echo $disp; ?>">User</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_action&o=<?php echo $disp; ?>">Action</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_description&o=<?php echo $disp; ?>">Description</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_ip&o=<?php echo $disp; ?>">IP Address</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_user_agent&o=<?php echo $disp; ?>">User Agent</a></th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while ($row = mysqli_fetch_array($sql)) {
            $log_id = $row['log_id'];
            $log_type = htmlentities($row['log_type']);
            $log_action = htmlentities($row['log_action']);
            $log_description = htmlentities($row['log_description']);
            $log_ip = htmlentities($row['log_ip']);
            $log_user_agent = htmlentities($row['log_user_agent']);
            $log_user_os = get_os($log_user_agent);
            $log_user_browser = get_web_browser($log_user_agent);
            $log_created_at = $row['log_created_at'];
            $user_id = $row['user_id'];
            $user_name = htmlentities($row['user_name']);
            if (empty($user_name)) {
              $user_name_display = "-";
            }else{
              $user_name_display = $user_name;
            }
          
          ?>
          
          <tr>
            <td><?php echo $log_created_at; ?></td>
            <td><?php echo $user_name_display; ?></td>
            <td><?php echo $log_type; ?></td>
            <td><?php echo $log_action; ?></td>
            <td><?php echo $log_description; ?></td>
            <td><?php echo $log_ip; ?></td>
            <td><?php echo "$log_user_os<br>$log_user_browser"; ?></td>
          </tr>

          <?php
          }
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("footer.php"); ?>