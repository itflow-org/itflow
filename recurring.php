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
  $sb = "recurring_next_date";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM recurring, clients, categories
  WHERE recurring.client_id = clients.client_id
  AND recurring.category_id = categories.category_id
  AND recurring.company_id = $session_company_id
  AND (CONCAT(recurring_prefix,recurring_number) LIKE '%$q%' OR recurring_frequency LIKE '%$q%' OR recurring_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%')
  AND DATE(recurring_next_date) BETWEEN '$dtf' AND '$dtt'
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-sync-alt"></i> Recurring Invoices</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringModal"><i class="fas fa-fw fa-plus"></i> New Recurring</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Recurring Invoices">
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
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_number&o=<?php echo $disp; ?>">Number</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_next_date&o=<?php echo $disp; ?>">Next Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_scope&o=<?php echo $disp; ?>">Scope</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_frequency&o=<?php echo $disp; ?>">Frequency</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_last_sent&o=<?php echo $disp; ?>">Last Sent</a></th>
            
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_status&o=<?php echo $disp; ?>">Status</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $recurring_id = $row['recurring_id'];
            $recurring_prefix = $row['recurring_prefix'];
            $recurring_number = $row['recurring_number'];
            $recurring_scope = $row['recurring_scope'];
            $recurring_frequency = $row['recurring_frequency'];
            $recurring_status = $row['recurring_status'];
            $recurring_last_sent = $row['recurring_last_sent'];
            if($recurring_last_sent == 0){
              $recurring_last_sent = "-";
            }
            $recurring_next_date = $row['recurring_next_date'];
            $recurring_amount = $row['recurring_amount'];
            $recurring_created_at = $row['recurring_created_at'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
            if($recurring_status == 1){
              $status = "Active";
              $status_badge_color = "success";
            }else{
              $status = "Inactive";
              $status_badge_color = "secondary";
            }

          ?>

          <tr>
            <td><a href="recurring_invoice.php?recurring_id=<?php echo $recurring_id; ?>"><?php echo "$recurring_prefix$recurring_number"; ?></a></td>
            <td><?php echo $recurring_next_date; ?></td>
            <td><?php echo $recurring_scope; ?></td>
            <td><?php echo ucwords($recurring_frequency); ?>ly</td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=recurring"><?php echo $client_name; ?></a></td>
            <td class="text-right">$<?php echo number_format($recurring_amount,2); ?></td>
            <td><?php echo $recurring_last_sent; ?></td>
            <td><?php echo $category_name; ?></td>
            <td>
               <span class="p-2 badge badge-<?php echo $status_badge_color; ?>">
                <?php echo $status; ?>
              </span>
                
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRecurringModal<?php echo $recurring_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?force_recurring=<?php echo $recurring_id; ?>">Force</a>
                  <?php if($recurring_status == 1){ ?>
                    <a class="dropdown-item" href="post.php?recurring_deactivate=<?php echo $recurring_id; ?>">Deactivate</a>
                  <?php }else{ ?>
                    <a class="dropdown-item" href="post.php?recurring_activate=<?php echo $recurring_id; ?>">Activate</a>
                  <?php } ?>
                  <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>
          
          <?php
          include("edit_recurring_modal.php");

          }
          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php 
  
  include("add_recurring_modal.php");
  include("add_quick_modal.php");

  include("footer.php");

?>