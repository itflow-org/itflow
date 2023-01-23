<?php include("inc_all.php");

if (!empty($_GET['sb'])) {
  $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
}else{
  $sb = "payment_date";
}

// Reverse default sort
if (!isset($_GET['o'])) {
  $o = "DESC";
  $disp = "ASC";
}

if (empty($_GET['canned_date'])) {
  //Prevents lots of undefined variable errors.
  // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
  $_GET['canned_date'] = 'custom';
}

//Date Filter
if ($_GET['canned_date'] == "custom" && !empty($_GET['dtf'])) {
  $dtf = strip_tags(mysqli_real_escape_string($mysqli,$_GET['dtf']));
  $dtt = strip_tags(mysqli_real_escape_string($mysqli,$_GET['dtt']));
}elseif ($_GET['canned_date'] == "today") {
  $dtf = date('Y-m-d');
  $dtt = date('Y-m-d');
}elseif ($_GET['canned_date'] == "yesterday") {
  $dtf = date('Y-m-d',strtotime("yesterday"));
  $dtt = date('Y-m-d',strtotime("yesterday"));
}elseif ($_GET['canned_date'] == "thisweek") {
  $dtf = date('Y-m-d',strtotime("monday this week"));
  $dtt = date('Y-m-d');
}elseif ($_GET['canned_date'] == "lastweek") {
  $dtf = date('Y-m-d',strtotime("monday last week"));
  $dtt = date('Y-m-d',strtotime("sunday last week"));
}elseif ($_GET['canned_date'] == "thismonth") {
  $dtf = date('Y-m-01');
  $dtt = date('Y-m-d');
}elseif ($_GET['canned_date'] == "lastmonth") {
  $dtf = date('Y-m-d',strtotime("first day of last month"));
  $dtt = date('Y-m-d',strtotime("last day of last month"));
}elseif ($_GET['canned_date'] == "thisyear") {
  $dtf = date('Y-01-01');
  $dtt = date('Y-m-d');
}elseif ($_GET['canned_date'] == "lastyear") {
  $dtf = date('Y-m-d',strtotime("first day of january last year"));
  $dtt = date('Y-m-d',strtotime("last day of december last year"));  
}else{
  $dtf = "0000-00-00";
  $dtt = "9999-00-00";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM payments
  LEFT JOIN invoices ON payment_invoice_id = invoice_id
  LEFT JOIN clients ON invoice_client_id = client_id
  LEFT JOIN accounts ON payment_account_id = account_id
  WHERE payments.company_id = $session_company_id
  AND DATE(payment_date) BETWEEN '$dtf' AND '$dtt'
  AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR account_name LIKE '%$q%' OR payment_method LIKE '%$q%' OR payment_reference LIKE '%$q%')
  ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-3">
    <h3 class="card-title"><i class="fa fa-fw fa-credit-card"></i> Payments</h3>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo strip_tags(htmlentities($q));} ?>" placeholder="Search Payments">
            <div class="input-group-append">
              <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
      <div class="collapse mt-3 <?php if (!empty($_GET['dtf'])) { echo "show"; } ?>" id="advancedFilter">
        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Canned Date</label>
              <select class="form-control select2" name="canned_date">
                <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="custom">Custom</option>
                <option <?php if ($_GET['canned_date'] == "today") { echo "selected"; } ?> value="today">Today</option>
                <option <?php if ($_GET['canned_date'] == "yesterday") { echo "selected"; } ?> value="yesterday">Yesterday</option>
                <option <?php if ($_GET['canned_date'] == "thisweek") { echo "selected"; } ?> value="thisweek">This Week</option>
                <option <?php if ($_GET['canned_date'] == "lastweek") { echo "selected"; } ?> value="lastweek">Last Week</option>
                <option <?php if ($_GET['canned_date'] == "thismonth") { echo "selected"; } ?> value="thismonth">This Month</option>
                <option <?php if ($_GET['canned_date'] == "lastmonth") { echo "selected"; } ?> value="lastmonth">Last Month</option>
                <option <?php if ($_GET['canned_date'] == "thisyear") { echo "selected"; } ?> value="thisyear">This Year</option>
                <option <?php if ($_GET['canned_date'] == "lastyear") { echo "selected"; } ?> value="lastyear">Last Year</option>
              </select>
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>Date From</label>
              <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo htmlentities($dtf); ?>">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>Date To</label>
              <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo htmlentities($dtt); ?>">
            </div>
          </div>
        </div>    
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_date&o=<?php echo $disp; ?>">Payment Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_date&o=<?php echo $disp; ?>">Invoice Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_number&o=<?php echo $disp; ?>">Invoice</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_method&o=<?php echo $disp; ?>">Payment Method</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=payment_reference&o=<?php echo $disp; ?>">Reference</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=account_name&o=<?php echo $disp; ?>">Account</a></th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while ($row = mysqli_fetch_array($sql)) {
            $invoice_id = $row['invoice_id'];
            $invoice_prefix = htmlentities($row['invoice_prefix']);
            $invoice_number = htmlentities($row['invoice_number']);
            $invoice_status = htmlentities($row['invoice_status']);
            $invoice_date = $row['invoice_date'];
            $payment_date = $row['payment_date'];
            $payment_method = htmlentities($row['payment_method']);
            $payment_amount = floatval($row['payment_amount']);
            $payment_currency_code = htmlentities($row['payment_currency_code']);
            $payment_reference = htmlentities($row['payment_reference']);
            if (empty($payment_reference)) {
              $payment_reference_display = "-";
            }else{
              $payment_reference_display = $payment_reference;
            }
            $client_id = $row['client_id'];
            $client_name = htmlentities($row['client_name']);
            $account_name = htmlentities($row['account_name']);

          ?>

          <tr>
            <td><?php echo $payment_date; ?></td>
            <td><?php echo $invoice_date; ?></td>
            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
            <td><a href="client_payments.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code); ?></td>
            <td><?php echo $payment_method; ?></td>
            <td><?php echo $payment_reference_display; ?></td>
            <td><?php echo $account_name; ?></td>
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

<?php include("footer.php"); ?>