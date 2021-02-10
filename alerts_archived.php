<?php include("header.php");

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$_SESSION['records_per_page'];
  $record_to = $_SESSION['records_per_page'];
}else{
  $record_from = 0;
  $record_to = $_SESSION['records_per_page'];
  $p = 1;
}
  
//Custom Query Filter  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

//Column Filter
if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "alert_date";
}

//Column Order Filter
if(isset($_GET['o'])){
  if($_GET['o'] == 'ASC'){
    $o = "ASC";
    $disp = "DESC";
  }else{
    $o = "DESC";
    $disp = "ASC";
  }
}else{
  $o = "ASC";
  $disp = "DESC";
}

//Date From and Date To Filter
if(!empty($_GET['dtf'])){
  $dtf = $_GET['dtf'];
  $dtt = $_GET['dtt'];
}else{
  $dtf = "0000-00-00";
  $dtt = "9999-00-00";
}

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM alerts WHERE (alert_type LIKE '%$q%' OR alert_message LIKE '%$q%') AND DATE(alert_date) BETWEEN '$dtf' AND '$dtt' AND company_id = $session_company_id AND alert_ack_date IS NOT NULL ORDER BY $sb $o LIMIT $record_from, $record_to"); 

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-3">
    <h3 class="card-title"><i class="fa fa-fw fa-exclamation-triangle"></i> Archived Alerts</h3>
  </div>
  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Alerts">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
        <div class="col-sm-8">
          <button class="btn btn-primary float-right" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
        </div>
      </div>
      <div class="collapse mt-3 <?php if(!empty($_GET['dtf'])){ echo "show"; } ?>" id="advancedFilter">
        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Date From</label>
              <input type="date" class="form-control" name="dtf" value="<?php echo $dtf; ?>">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>Date To</label>
              <input type="date" class="form-control" name="dtt" value="<?php echo $dtt; ?>">
            </div>
          </div>
        </div>    
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead class="<?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=alert_date&o=<?php echo $disp; ?>">Alert Date <i class="fa fa-sort-numeric<?php if($disp=='ASC'){ echo "-up"; }else{ echo "-down"; }?>"></i></a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=alert_type&o=<?php echo $disp; ?>">Type <i class="fa fa-sort-alpha<?php if($disp=='ASC'){ echo "-up"; }else{ echo "-down"; }?>"></i></a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=alert_message&o=<?php echo $disp; ?>">Alert <i class="fa fa-sort-alpha<?php if($disp=='ASC'){ echo "-up"; }else{ echo "-down"; }?>"></i></a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=alert_ack_date&o=<?php echo $disp; ?>">Ack Date <i class="fa fa-sort-numeric<?php if($disp=='ASC'){ echo "-up"; }else{ echo "-down"; }?>"></i></a></th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $alert_id = $row['alert_id'];
            $alert_date = $row['alert_date'];
            $alert_type = $row['alert_type'];
            $alert_message = $row['alert_message'];
            $alert_ack_date = $row['alert_ack_date'];

          ?>
          <tr>
            <td><?php echo $alert_date; ?></td>
            <td><?php echo $alert_type; ?></td>
            <td><?php echo $alert_message; ?></td>
            <td><?php echo $alert_ack_date; ?></td>

          <?php
          
          }
          
          ?>

        </tbody>
      </table> 
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("footer.php");