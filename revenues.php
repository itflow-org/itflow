<?php include("header.php");

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
  $sb = "revenue_date";
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

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM accounts, revenues, categories
  WHERE revenues.account_id = accounts.account_id
  AND revenues.category_id = categories.category_id
  AND revenues.company_id = $session_company_id
  AND (account_name LIKE '%$q%' OR revenue_payment_method LIKE '%$q%' OR category_name LIKE '%$q%' OR revenue_reference LIKE '%$q%' OR revenue_amount LIKE '%$q%')
  AND DATE(revenue_date) BETWEEN '$dtf' AND '$dtt'
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-credit-card"></i> Revenues</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRevenueModal"><i class="fas fa-fw fa-plus"></i> Add Revenue</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Revenues">
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
        <thead class="<?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_date&o=<?php echo $disp; ?>">Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_payment_method&o=<?php echo $disp; ?>">Method</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_reference&o=<?php echo $disp; ?>">Reference</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=account_name&o=<?php echo $disp; ?>">Account</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $revenue_id = $row['revenue_id'];
            $revenue_description = $row['revenue_description'];
            $revenue_reference = $row['revenue_reference'];
            $revenue_date = $row['revenue_date'];
            $revenue_payment_method = $row['revenue_payment_method'];
            $revenue_amount = $row['revenue_amount'];
            $revenue_created_at = $row['revenue_created_at'];
            $account_id = $row['account_id'];
            $account_name = $row['account_name'];
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];

          ?>

          <tr>
            <td><a href="#" data-toggle="modal" data-target="#editRevenueModal<?php echo $revenue_id; ?>"><?php echo $revenue_date; ?></a></td>
            <td><?php echo $category_name; ?></td>
            <td class="text-right">$<?php echo number_format($revenue_amount,2); ?></td>
            <td><?php echo $revenue_payment_method; ?></td>
            <td><?php echo $revenue_reference; ?></td>
            <td><?php echo $account_name; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRevenueModal<?php echo $revenue_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?delete_revenue=<?php echo $revenue_id; ?>">Delete</a>
                </div>
              </div>
              <?php

              include("edit_revenue_modal.php");

              ?>      
            </td>
          </tr>

          <?php

          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 

  include("add_revenue_modal.php");
  include("add_quick_modal.php");

  include("footer.php");

?>