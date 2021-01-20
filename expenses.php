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
    $sb = "expense_date";
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
  if(!empty($_GET['dtf'])){
    $dtf = $_GET['dtf'];
    $dtt = $_GET['dtt'];
  }else{
    $dtf = "0000-00-00";
    $dtt = "9999-00-00";
  }

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM expenses, categories, vendors, accounts
    WHERE expenses.category_id = categories.category_id
    AND expenses.vendor_id = vendors.vendor_id
    AND expenses.account_id = accounts.account_id
    AND expenses.company_id = $session_company_id
    AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
    AND (vendor_name LIKE '%$q%' OR category_name LIKE '%$q%' OR account_name LIKE '%$q%' OR expense_description LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-shopping-cart mr-2"></i>Expenses</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addExpenseModal"><i class="fas fa-fw fa-cart-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="row">
        <div class="col-md-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Expenses">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
        <div class="col-md-8">
          <button class="btn btn-primary float-right" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
        </div>
      </div>
      <div class="collapse mt-3 <?php if(!empty($_GET['dtf'])){ echo "show"; } ?>" id="advancedFilter">
        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Date From</label>
              <input type="date" class="form-control" name="dtf" value="<?php if(isset($dtf)){echo $dtf;} ?>">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>Date To</label>
              <input type="date" class="form-control" name="dtt" value="<?php if(isset($dtt)){echo $dtt;} ?>">
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=expense_date&o=<?php echo $disp; ?>">Date</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=expense_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_name&o=<?php echo $disp; ?>">Vendor</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=account_name&o=<?php echo $disp; ?>">Account</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $expense_id = $row['expense_id'];
            $expense_date = $row['expense_date'];
            $expense_amount = $row['expense_amount'];
            $expense_description = $row['expense_description'];
            $expense_receipt = $row['expense_receipt'];
            $expense_reference = $row['expense_reference'];
            $vendor_id = $row['vendor_id'];
            $vendor_name = $row['vendor_name'];
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
            $account_name = $row['account_name'];
            $account_id = $row['account_id'];

            if(empty($expense_receipt)){
              $receipt_attached = "";
            }else{
              $receipt_attached = "<a class='text-secondary mr-2' target='_blank' href='$expense_receipt'><i class='fa fa-file-pdf'></i></a>";
            }

          ?>

          <tr>
            <td><?php echo $receipt_attached; ?> <a class="text-dark" href="#" data-toggle="modal" data-target="#editExpenseModal<?php echo $expense_id; ?>"><?php echo $expense_date; ?></a></td>
            <td class="text-right">$<?php echo number_format($expense_amount,2); ?></td>
            <td><?php echo $vendor_name; ?></td>
            <td><?php echo $category_name; ?></td>
            <td><?php echo $account_name; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <?php 
                  if(!empty($expense_receipt)){
                  ?>
                  <a class="dropdown-item" href="<?php echo $expense_receipt; ?>" target="_blank">Reciept</a>
                  <div class="dropdown-divider"></div>
                  <?php
                  }
                  ?>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editExpenseModal<?php echo $expense_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addExpenseCopyModal<?php echo $expense_id; ?>">Copy</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addExpenseRefundModal<?php echo $expense_id; ?>">Refund</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?delete_expense=<?php echo $expense_id; ?>">Delete</a>
                </div>
              </div>
              <?php

              include("edit_expense_modal.php");
              include("add_expense_copy_modal.php");
              include("add_expense_refund_modal.php");

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

<?php include("add_expense_modal.php"); ?>

<?php include("footer.php");