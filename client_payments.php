<?php

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
  $sb = "payment_date";
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


$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM payments, invoices, accounts
  WHERE invoices.client_id = $client_id
  AND payments.invoice_id = invoices.invoice_id
  AND payments.account_id = accounts.account_id
  AND (invoice_number LIKE '%$q%' OR account_name LIKE '%$q%' OR payment_method LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-credit-card"></i> Payments</h6>
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
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_date&o=<?php echo $disp; ?>">Date Recieved</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_number&o=<?php echo $disp; ?>">Invoice</a></th>
            <th class="text-right"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_amount&o=<?php echo $disp; ?>">Invoice Amount</a></th>
            <th class="text-right"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_amount&o=<?php echo $disp; ?>">Payment Amount</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=account_name&o=<?php echo $disp; ?>">Account</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_method&o=<?php echo $disp; ?>">Method</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_reference&o=<?php echo $disp; ?>">Reference</a></th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $invoice_id = $row['invoice_id'];
            $invoice_number = $row['invoice_number'];
            $invoice_status = $row['invoice_status'];
            $invoice_amount = $row['invoice_amount'];
            $payment_date = $row['payment_date'];
            $payment_method = $row['payment_method'];
            $payment_reference = $row['payment_reference'];
            $payment_amount = $row['payment_amount'];
            $account_name = $row['account_name'];

      
          ?>
          <tr>
            <td><?php echo $payment_date; ?></td>
            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo $invoice_number; ?></a></td>
            <td class="text-right text-monospace">$<?php echo number_format($invoice_amount,2); ?></td>
            <td class="text-right text-monospace">$<?php echo number_format($payment_amount,2); ?></td>
            <td><?php echo $account_name; ?></td>
            <td><?php echo $payment_method; ?></td>
            <td><?php echo $payment_reference; ?></td>
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