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

//Search Query Filter  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

//Column Sortby Filter
if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "transfer_date";
}

//Order Filter
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
if($_GET['canned_date'] =="custom"){
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
 
$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS expenses.expense_date AS transfer_date, expenses.expense_amount AS transfer_amount, expenses.account_id AS transfer_account_from, revenues.account_id AS transfer_account_to, transfers.expense_id, transfers.revenue_id , transfers.transfer_id, transfers.transfer_notes AS transfer_notes FROM transfers, expenses, revenues 
  WHERE transfers.expense_id = expenses.expense_id 
  AND transfers.revenue_id = revenues.revenue_id 
  AND transfers.company_id = $session_company_id
  AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
  ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-exchange-alt"></i> Transfers</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTransferModal"><i class="fas fa-fw fa-plus"></i> New Transfer</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Transfers">
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
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_date&o=<?php echo $disp; ?>">Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_account_from&o=<?php echo $disp; ?>">From Account</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_account_to&o=<?php echo $disp; ?>">To Account</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $transfer_id = $row['transfer_id'];
            $transfer_date = $row['transfer_date'];
            $transfer_account_from = $row['transfer_account_from'];
            $transfer_account_to = $row['transfer_account_to'];
            $transfer_amount = $row['transfer_amount'];
            $transfer_notes = $row['transfer_notes'];
            $transfer_created_at = $row['transfer_created_at'];
            $expense_id = $row['expense_id'];
            $revenue_id = $row['revenue_id'];
   
            $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_id = $transfer_account_from");
            $row = mysqli_fetch_array($sql2);
            $account_name_from = $row['account_name'];

            $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_id = $transfer_account_to");
            $row = mysqli_fetch_array($sql2);
            $account_name_to = $row['account_name'];

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTransferModal<?php echo $transfer_id; ?>"><?php echo $transfer_date; ?></a></td>
            <td><?php echo $account_name_from; ?></td>
            <td><?php echo $account_name_to; ?></td>
            <td class="text-right">$<?php echo number_format($transfer_amount,2); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTransferModal<?php echo $transfer_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?delete_transfer=<?php echo $transfer_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php

          include("edit_transfer_modal.php");
        
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_transfer_modal.php"); ?>

<?php include("footer.php");