<?php include("header.php");
  
  //Rebuild URL

  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  if(isset($_GET['p'])){
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*10;
    $record_to =  10;
  }else{
    $record_from = 0;
    $record_to = 10;
    $p = 1;
  }
    
  if(isset($_GET['q'])){
    $q = mysqli_real_escape_string($mysqli,$_GET['q']);
  }else{
    $q = "";
  }

  if(!empty($_GET['sb'])){
    $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
  }else{
    $sb = "log_id";
  }

  if(isset($_GET['o'])){
    if($_GET['o'] == 'ASC'){
      $o = "ASC";
      $disp = "DESC";
    }else{
      $o = "DESC";
      $disp = "ASC";
    }
  }else{
    $o = "DESC";
    $disp = "ASC";
  }

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM logs, users 
    WHERE log_type LIKE '%$q%' OR log_action LIKE '%$q%' OR log_description LIKE '%$q%'
    AND (logs.user_id = users.user_id)
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-book mr-2"></i>Logs</h6>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Logs">
        <div class="input-group-append">
          <button class="btn btn-primary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_created_at&o=<?php echo $disp; ?>">Timestamp</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=name&o=<?php echo $disp; ?>">User</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_action&o=<?php echo $disp; ?>">Action</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_description&o=<?php echo $disp; ?>">Description</a></th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $log_id = $row['log_id'];
            $log_type = $row['log_type'];
            $log_action = $row['log_action'];
            $log_description = $row['log_description'];
            $log_created_at = $row['log_created_at'];
            $user_id = $row['user_id'];
            $user_name = $row['name'];
          
          ?>
          
          <tr>
            <td><?php echo $log_created_at; ?></td>
            <td><?php echo $user_name; ?></td>
            <td><?php echo $log_type; ?></td>
            <td><?php echo $log_action; ?></td>
            <td><?php echo $log_description; ?></td>
          </tr>

          <?php
          }
          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>  

    </div>
  </div>
</div>

<?php include("footer.php");