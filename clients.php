<?php include("header.php");

//Permission check
if($session_permission_level == 2){
  $permission_sql = "AND client_id IN ($session_permission_clients)";
}else{
  $permission_sql = "";
}

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
  $sb = "client_accessed_at";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM clients 
  WHERE (client_name LIKE '%$q%' OR client_type LIKE '%$q%' OR client_support LIKE '%$q%' OR client_email LIKE '%$q%' OR client_contact LIKE '%$q%' OR client_phone LIKE '%$q%' 
  OR client_mobile LIKE '%$q%' OR client_address LIKE '%$q%' OR client_city LIKE '%$q%' OR client_state LIKE '%$q%' OR client_zip LIKE '%$q%') 
  AND DATE(client_created_at) BETWEEN '$dtf' AND '$dtt' 
  AND company_id = $session_company_id $permission_sql 
  ORDER BY $sb $o LIMIT $record_from, $record_to"
); 

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-users"></i> Clients</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-fw fa-plus"></i> New Client</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Clients" autofocus>
            <div class="input-group-append">
              <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
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
      <table class="table table-striped table-hover table-borderless">
        <thead class="<?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Name <i class="fa fa-sort-alpha<?php if($disp=='ASC'){ echo "-up"; }else{ echo "-down"; }?>"></i></a></th>
            <th>Address</th>
            <th>Contact</th>
            <th class="text-right">Balance</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $client_type = $row['client_type'];
            $client_country = $row['client_country'];
            $client_address = $row['client_address'];
            $client_city = $row['client_city'];
            $client_state = $row['client_state'];
            $client_zip = $row['client_zip'];
            $client_contact = $row['client_contact'];
            $client_phone = $row['client_phone'];
            if(strlen($client_phone)>2){ 
              $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
            }
            $client_extension = $row['client_extension'];
            $client_mobile = $row['client_mobile'];
            if(strlen($client_mobile)>2){ 
              $client_mobile = substr($row['client_mobile'],0,3)."-".substr($row['client_mobile'],3,3)."-".substr($row['client_mobile'],6,4);
            }
            $client_email = $row['client_email'];
            $client_website = $row['client_website'];
            $client_currency_code = $row['client_currency_code'];
            $client_net_terms = $row['client_net_terms'];
            $client_referral = $row['client_referral'];
            $client_support = $row['client_support'];
            $client_notes = $row['client_notes'];
            $client_created_at = $row['client_created_at'];
            $client_updated_at = $row['client_updated_at'];

            //Add up all the payments for the invoice and get the total amount paid to the invoice
            $sql_invoice_amounts = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' ");
            $row = mysqli_fetch_array($sql_invoice_amounts);

            $invoice_amounts = $row['invoice_amounts'];

            $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id");
            $row = mysqli_fetch_array($sql_amount_paid);
            
            $amount_paid = $row['amount_paid'];

            $balance = $invoice_amounts - $amount_paid;
            //set Text color on balance
            if($balance > 0){
              $balance_text_color = "text-danger font-weight-bold";
            }else{
              $balance_text_color = "";
            }            
      
          ?>
          <tr>
            <td>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=contacts"><?php echo $client_name; ?></a>
              <br>
              <small class="text-secondary"><?php echo $client_type; ?></small>
              <br>
              <small class="text-secondary"><i class="fas fa-handshake fa-fw"></i> <b>SUPPORT: </b><span style="color:<?php echo ($client_support == "Maintenance" ? "green" : "red");?>;"><?php echo $client_support;?></span></small>
              <br>
              <small class="text-secondary"><b>Contract started: </b><?php echo $client_created_at; ?></small>
            </td>
            <td>
              <?php echo "$client_address<br>$client_city $client_state $client_zip"; ?>
            </td>
            <td>
              <?php
              if(!empty($client_contact)){
              ?>
              <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><?php echo $client_contact; ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($client_phone)){
              ?>
              <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $client_phone; ?> <?php if(!empty($client_extension)){ echo "x$client_extension"; } ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($client_mobile)){
              ?>
              <i class="fa fa-fw fa-mobile-alt text-secondary mr-2 mb-2"></i><?php echo $client_mobile; ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($client_email)){
              ?>
              <i class="fa fa-fw fa-envelope text-secondary mr-2 mb-2"></i><a href="mailto:<?php echo $client_email; ?>"><?php echo $client_email; ?></a>
              <?php
              }
              ?>
            </td>
            <td class="text-right <?php echo $balance_text_color; ?>">$<?php echo number_format($balance,2); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#deleteClientModal<?php echo $client_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

            include("edit_client_modal.php");
            include("delete_client_modal.php");

          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_client_modal.php"); ?>
<?php include("add_quick_modal.php"); ?>

<?php include("footer.php");
