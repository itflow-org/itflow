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
    $sb = "transfer_date";
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
 

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS expenses.expense_date AS transfer_date, expenses.expense_amount AS transfer_amount, expenses.account_id AS transfer_account_from, revenues.account_id AS transfer_account_to, transfers.expense_id, transfers.revenue_id , transfers.transfer_id FROM transfers, expenses, revenues WHERE transfers.expense_id = expenses.expense_id AND transfers.revenue_id = revenues.revenue_id AND transfers.company_id = $session_company_id ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-exchange-alt mr-2"></i>Transfers</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addTransferModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Transfers">
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
            <td class="text-right text-monospace">$<?php echo number_format($transfer_amount,2); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTransferModal<?php echo $transfer_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_transfer=<?php echo $transfer_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_transfer_modal.php"); ?>      
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

<?php include("add_transfer_modal.php"); ?>

<?php include("footer.php");