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
    
  if(isset($_GET['q'])){
    $q = mysqli_real_escape_string($mysqli,$_GET['q']);
  }else{
    $q = "";
  }

  if(!empty($_GET['sb'])){
    $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
  }else{
    $sb = "trip_date";
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

  //Date Filter
  if($_GET['canned_date'] == "custom" AND !empty($_GET['dtf'])){
    $dtf = $_GET['dtf'];
    $dtt = $_GET['dtt'];
  }elseif($_GET['canned_date'] == "today"){
    $dtf = date('Y-m-d');
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "yesterday"){
    $dtf = date('Y-m-d',strtotime("yesterday"));
    $dtt = date('Y-m-d',strtotime("yesterday"));
  }elseif($_GET['canned_date'] == "thisweek"){
    $dtf = date('Y-m-d',strtotime("monday this week"));
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "lastweek"){
    $dtf = date('Y-m-d',strtotime("monday last week"));
    $dtt = date('Y-m-d',strtotime("sunday last week"));
  }elseif($_GET['canned_date'] == "thismonth"){
    $dtf = date('Y-m-01');
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "lastmonth"){
    $dtf = date('Y-m-d',strtotime("first day of last month"));
    $dtt = date('Y-m-d',strtotime("last day of last month"));
  }elseif($_GET['canned_date'] == "thisyear"){
    $dtf = date('Y-01-01');
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "lastyear"){
    $dtf = date('Y-m-d',strtotime("first day of january last year"));
    $dtt = date('Y-m-d',strtotime("last day of december last year"));  
  }else{
    $dtf = "0000-00-00";
    $dtt = "9999-00-00";
  }

  //Rebuild URL
  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM trips  
    WHERE (trip_purpose LIKE '%$q%' OR trip_source LIKE '%$q%' OR trip_destination LIKE '%$q%' OR trip_miles LIKE '%$q%')
    AND DATE(trip_date) BETWEEN '$dtf' AND '$dtt'
    AND company_id = $session_company_id
    ORDER BY $sb $o LIMIT $record_from, $record_to"
  );

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-route"></i> Trips</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTripModal"><i class="fas fa-fw fa-plus"></i> New Trip</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Trips">
            <div class="input-group-append">
              <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
        <div class="col-sm-8">
          <div class="float-right">
            <a href="post.php?export_trips_csv" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
            <a href="post.php?import_trips_csv" class="btn btn-default"><i class="fa fa-fw fa-upload"></i> Import</a>
          </div>
        </div>
      </div>
      <div class="collapse mt-3 <?php if(!empty($_GET['dtf'])){ echo "show"; } ?>" id="advancedFilter">
        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Canned Date</label>
              <select class="form-control select2" name="canned_date">
                <option <?php if($_GET['canned_date'] == "custom"){ echo "selected"; } ?> value="custom">Custom</option>
                <option <?php if($_GET['canned_date'] == "today"){ echo "selected"; } ?> value="today">Today</option>
                <option <?php if($_GET['canned_date'] == "yesterday"){ echo "selected"; } ?> value="yesterday">Yesterday</option>
                <option <?php if($_GET['canned_date'] == "thisweek"){ echo "selected"; } ?> value="thisweek">This Week</option>
                <option <?php if($_GET['canned_date'] == "lastweek"){ echo "selected"; } ?> value="lastweek">Last Week</option>
                <option <?php if($_GET['canned_date'] == "thismonth"){ echo "selected"; } ?> value="thismonth">This Month</option>
                <option <?php if($_GET['canned_date'] == "lastmonth"){ echo "selected"; } ?> value="lastmonth">Last Month</option>
                <option <?php if($_GET['canned_date'] == "thisyear"){ echo "selected"; } ?> value="thisyear">This Year</option>
                <option <?php if($_GET['canned_date'] == "lastyear"){ echo "selected"; } ?> value="lastyear">Last Year</option>
              </select>
            </div>
          </div>
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
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_date&o=<?php echo $disp; ?>">Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_purpose&o=<?php echo $disp; ?>">Purpose</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_source&o=<?php echo $disp; ?>">Source</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_destination&o=<?php echo $disp; ?>">Destination</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_miles&o=<?php echo $disp; ?>">Miles</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $trip_id = $row['trip_id'];
            $trip_date = $row['trip_date'];
            $trip_purpose = $row['trip_purpose'];
            $trip_source = $row['trip_source'];
            $trip_destination = $row['trip_destination'];
            $trip_miles = $row['trip_miles'];
            $round_trip = $row['round_trip'];
            $client_id = $row['client_id'];

            if($round_trip == 1){
              $round_trip_display = "<i class='fa fa-fw fa-sync-alt text-secondary'></i>";
            }else{
              $round_trip_display = "";
            }

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>"><?php echo $trip_date; ?></a></td>
            <td><?php echo $trip_purpose; ?></td>
            <td><?php echo $trip_source; ?></td>
            <td><?php echo $trip_destination; ?></td>
            <td><?php echo "$trip_miles $round_trip_display"; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="//maps.google.com?q=<?php echo $trip_source; ?> to <?php echo $trip_destination; ?>" target="_blank">Map it</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addTripCopyModal<?php echo $trip_id; ?>">Copy</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_trip=<?php echo $trip_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

          include("add_trip_copy_modal.php");
          include("edit_trip_modal.php");
          
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_trip_modal.php"); ?>

<?php include("footer.php");