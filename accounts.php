<?php include("header.php"); ?>

<?php
  
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
    $sb = "account_name";
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
    $o = "ASC";
    $disp = "DESC";
  }

  //Rebuild URL
  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM accounts 
    WHERE account_name LIKE '%$q%' AND company_id = $session_company_id
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-piggy-bank"></i> Accounts</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAccountModal"><i class="fas fa-fw fa-plus"></i> New Account</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Accounts">
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=account_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=account_currency_code&o=<?php echo $disp; ?>">Currency</a></th>
            <th class="text-right">Balance</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $account_id = $row['account_id'];
            $account_name = $row['account_name'];
            $opening_balance = $row['opening_balance'];
            $account_currency_code = $row['account_currency_code'];
            $account_notes = $row['account_notes'];

            $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
            $row = mysqli_fetch_array($sql_payments);
            $total_payments = $row['total_payments'];

            $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
            $row = mysqli_fetch_array($sql_revenues);
            $total_revenues = $row['total_revenues'];
            
            $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
            $row = mysqli_fetch_array($sql_expenses);
            $total_expenses = $row['total_expenses'];

            $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;
          ?>
          
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editAccountModal<?php echo $account_id; ?>"><?php echo $account_name; ?></a></td>
            <td><?php echo $account_currency_code; ?></td>
            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $balance, $account_currency_code); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAccountModal<?php echo $account_id; ?>">Edit</a>
                  <?php if($balance == 0){ //Cannot Archive an Account until it reaches 0 Balance ?>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?archive_account=<?php echo $account_id; ?>">Archive</a>
                  <?php } ?>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("account_edit_modal.php");
          }
          ?>

        </tbody>
      </table>  
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php

  include("account_add_modal.php");
  
  include("footer.php");

?>