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
  $sb = "recurring_id";
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
 
$sql = mysqli_query($mysqli,"SELECT * FROM recurring, categories
  WHERE recurring.client_id = $client_id
  AND recurring.category_id = categories.category_id
  AND (recurring_frequency LIKE '%$q%' OR category_name LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-copy"></i> Recurring Invoices</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addRecurringModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="input-group">
        <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
        <div class="input-group-append">
          <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_frequency&o=<?php echo $disp; ?>">Frequency</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_last_sent&o=<?php echo $disp; ?>">Last Sent</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_next_date&o=<?php echo $disp; ?>">Next Date</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_status&o=<?php echo $disp; ?>">Status</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
           while($row = mysqli_fetch_array($sql)){
                $recurring_id = $row['recurring_id'];
                $recurring_frequency = $row['recurring_frequency'];
                $recurring_status = $row['recurring_status'];
                $recurring_last_sent = $row['recurring_last_sent'];
                if($recurring_last_sent == 0){
                  $recurring_last_sent = "-";
                }
                $recurring_next_date = $row['recurring_next_date'];
                $recurring_amount = $row['recurring_amount'];
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
                <td><?php echo ucwords($recurring_frequency); ?>ly</td>
                <td class="text-right text-monospace">$<?php echo number_format($recurring_amount,2); ?></td>
                <td><?php echo $recurring_last_sent; ?></td>
                <td><?php echo $recurring_next_date; ?></td>
                <td><?php echo $category_name; ?></td>
                <td>
                   <span class="p-2 badge badge-<?php echo $status_badge_color; ?>">
                    <?php echo $status; ?>
                  </span>
                </td>
                <td>
                  <div class="dropdown dropleft text-center">
                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                      <a class="dropdown-item" href="recurring_invoice.php?recurring_id=<?php echo $recurring_id; ?>">Edit</a>
                      <?php if($recurring_status == 1){ ?>
                        <a class="dropdown-item" href="post.php?recurring_deactivate=<?php echo $recurring_id; ?>">Deactivate</a>
                      <?php }else{ ?>
                        <a class="dropdown-item" href="post.php?recurring_activate=<?php echo $recurring_id; ?>">Activate</a>
                      <?php } ?>
                      <a class="dropdown-item" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">Delete</a>
                    </div>
                  </div>      
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

<?php include("add_recurring_modal.php"); ?>