<?php include("header.php");
  
  //Rebuild URL

  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  //Paging
  if(isset($_GET['p'])){
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*$config_records_per_page;
    $record_to = $config_records_per_page;
  }else{
    $record_from = 0;
    $record_to = $config_records_per_page;
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

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM accounts 
    WHERE account_name LIKE '%$q%' AND company_id = $session_company_id
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-piggy-bank mr-2"></i>Accounts</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addAccountModal"><i class="fas fa-plus"></i></button>
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
            $account_notes = $row['account_notes'];

            $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id");
            $row = mysqli_fetch_array($sql_payments);
            $total_payments = $row['total_payments'];

            $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE account_id = $account_id");
            $row = mysqli_fetch_array($sql_revenues);
            $total_revenues = $row['total_revenues'];
            
            $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
            $row = mysqli_fetch_array($sql_expenses);
            $total_expenses = $row['total_expenses'];

            $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;
          ?>
          
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editAccountModal<?php echo $account_id; ?>"><?php echo $account_name; ?></a></td>
            <td class="text-right">$<?php echo number_format($balance,2); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAccountModal<?php echo $account_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_account=<?php echo $account_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_account_modal.php"); ?>      
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

<?php include("add_account_modal.php"); ?>

<?php include("footer.php");