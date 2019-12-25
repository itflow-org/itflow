<?php

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

//Date From and Date To Filter
if(isset($_GET['dtf'])){
  $dtf = $_GET['dtf'];
  $dtt = $_GET['dtt'];
}else{
  $dtf = "0000-00-00";
  $dtt = "9999-00-00";
}

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM trips  
  WHERE (trip_purpose LIKE '%$q%' OR trip_starting_location LIKE '%$q%' OR trip_destination LIKE '%$q%')
  AND DATE(trip_date) BETWEEN '$dtf' AND '$dtt'
  AND company_id = $session_company_id
  AND client_id = $client_id
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-bicycle mr-2"></i>Trips</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addTripModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Trips">
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_date&o=<?php echo $disp; ?>">Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_purpose&o=<?php echo $disp; ?>">Purpose</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_starting_location&o=<?php echo $disp; ?>">From</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_destination&o=<?php echo $disp; ?>">To</a></th>
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
            $trip_starting_location = $row['trip_starting_location'];
            $trip_destination = $row['trip_destination'];
            $trip_miles = $row['trip_miles'];
            $round_trip = $row['round_trip'];
            $client_id = $row['client_id'];
            $invoice_id = $row['invoice_id'];
            $location_id = $row['location_id'];
            $vendor_id = $row['vendor_id'];

            if($round_trip == 1){
              $round_tip_display = "<i class='fa fa-fw fa-sync-alt text-secondary'></i>";
            }

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>"><?php echo $trip_date; ?></a></td>
            <td><?php echo $trip_purpose; ?></td>
            <td><?php echo $trip_starting_location; ?></td>
            <td><?php echo $trip_destination; ?></td>
            <td><?php echo "$trip_miles $round_tip_display"; ?></td>
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
  
              <?php 
              
              include("client_add_trip_copy_modal.php");
              include("client_edit_trip_modal.php");

              ?>
              
            </td>
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

<?php include("client_add_trip_modal.php"); ?>