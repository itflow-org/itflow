<?php 
  
  include("inc_all.php");
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Sent' AND company_id = $session_company_id"));
  $sent_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Viewed' AND company_id = $session_company_id"));
  $viewed_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Partial' AND company_id = $session_company_id"));
  $partial_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Draft' AND company_id = $session_company_id"));
  $draft_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Cancelled' AND company_id = $session_company_id"));
  $cancelled_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_due > CURDATE() AND company_id = $session_company_id"));
  $overdue_count = $row['num'];

  $sql_total_draft = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_draft FROM invoices WHERE invoice_status = 'Draft' AND company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_draft);
  $total_draft = $row['total_draft'];

  $sql_total_sent = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_sent FROM invoices WHERE invoice_status = 'Sent' AND company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_sent);
  $total_sent = $row['total_sent'];

  $sql_total_viewed = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_viewed FROM invoices WHERE invoice_status = 'Viewed' AND company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_viewed);
  $total_viewed = $row['total_viewed'];

  $sql_total_cancelled = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_cancelled FROM invoices WHERE invoice_status = 'Cancelled' AND company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_cancelled);
  $total_cancelled = $row['total_cancelled'];
  
  $sql_total_partial = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_partial FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial' AND invoices.company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_partial);
  $total_partial = $row['total_partial'];
  $total_partial_count = mysqli_num_rows($sql_total_partial);

  $sql_total_overdue_partial = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_overdue_partial FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial' AND invoice_due < CURDATE() AND invoices.company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_overdue_partial);
  $total_overdue_partial = $row['total_overdue_partial'];

  $sql_total_overdue = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_overdue FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Paid' AND invoice_due < CURDATE() AND invoices.company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_overdue);
  $total_overdue = $row['total_overdue'];

  $real_overdue_amount = $total_overdue - $total_overdue_partial;


  if(!empty($_GET['sb'])){
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
  }else{
    $sb = "invoice_number";
  }

  // Reverse default sort
  if(!isset($_GET['o'])){
    $o = "DESC";
    $disp = "ASC";
  }

  if(empty($_GET['canned_date'])){
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
    $_GET['canned_date'] = 'custom';
  }

  //Invoice status from GET
  if(isset($_GET['status']) && ($_GET['status']) == 'Draft'){
    $status_query = 'Draft';
  }elseif(isset($_GET['status']) && ($_GET['status']) == 'Sent'){
    $status_query = 'Sent';
  }elseif(isset($_GET['status']) && ($_GET['status']) == 'Viewed'){
    $status_query = 'Viewed';
  }elseif(isset($_GET['status']) && ($_GET['status']) == 'Partial'){
    $status_query = 'Partial';
  }else{
    $status_query = '%';
  }
  
  //Date Filter
  if($_GET['canned_date'] == "custom" && !empty($_GET['dtf'])){
    $dtf = mysqli_real_escape_string($mysqli,$_GET['dtf']);
    $dtt = mysqli_real_escape_string($mysqli,$_GET['dtt']);
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

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN categories ON invoice_category_id = category_id
    WHERE invoices.company_id = $session_company_id
    AND (invoice_status LIKE '$status_query')
    AND DATE(invoice_date) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%' OR category_name LIKE '%$q%') 
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="row">
  <div class="col-lg-3">
    <!-- small box -->
    <a href="?<?php echo $url_query_strings_sb; ?>&status=Draft" class="small-box bg-secondary">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $total_draft, $session_company_currency); ?></h3>
        <p><?php echo $draft_count; ?> Draft</p>
      </div>
      <div class="icon">
        <i class="fa fa-pencil-ruler"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-3">
    <!-- small box -->
    <a href="?<?php echo $url_query_strings_sb; ?>&status=Sent" class="small-box bg-warning">
      <div class="inner text-white">
        <h3><?php echo numfmt_format_currency($currency_format, $total_sent, $session_company_currency); ?></h3>
        <p><?php echo $sent_count; ?> Sent</p>
      </div>
      <div class="icon">
        <i class="fa fa-paper-plane"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-3">
    <!-- small box -->
    <a href="?<?php echo $url_query_strings_sb; ?>&status=Viewed" class="small-box bg-info">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $total_viewed, $session_company_currency); ?></h3>
        <p><?php echo $viewed_count; ?> Viewed</p>
      </div>
      <div class="icon">
        <i class="fa fa-eye"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

  <div class="col-lg-3">
    <!-- small box -->
    <a href="?<?php echo $url_query_strings_sb; ?>&status=Partial" class="small-box bg-primary">
      <div class="inner">
        <h3><?php echo numfmt_format_currency($currency_format, $total_partial, $session_company_currency); ?></h3>
        <p><?php echo $partial_count; ?> Partial</p>
      </div>
      <div class="icon">
        <i class="fa fa-wine-glass-alt"></i>
      </div>
    </a>
  </div>
  <!-- ./col -->

</div>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-file"></i> Invoices</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInvoiceModal"><i class="fas fa-fw fa-plus"></i> New Invoice</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <input type="hidden" name="status" value="<?php if(isset($_GET['status'])){ echo strip_tags($_GET['status']); } ?>">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Invoices">
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
              <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo $dtf; ?>">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>Date To</label>
              <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo $dtt; ?>">
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_number&o=<?php echo $disp; ?>">Number</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_scope&o=<?php echo $disp; ?>">Scope</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_date&o=<?php echo $disp; ?>">Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_due&o=<?php echo $disp; ?>">Due</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_status&o=<?php echo $disp; ?>">Status</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $invoice_id = $row['invoice_id'];
            $invoice_prefix = htmlentities($row['invoice_prefix']);
            $invoice_number = htmlentities($row['invoice_number']);
            $invoice_scope = htmlentities($row['invoice_scope']);
            if(empty($invoice_scope)){
              $invoice_scope_display = "-";
            }else{
              $invoice_scope_display = $invoice_scope;
            }
            $invoice_status = htmlentities($row['invoice_status']);
            $invoice_date = $row['invoice_date'];
            $invoice_due = $row['invoice_due'];
            $invoice_amount = floatval($row['invoice_amount']);
            $invoice_currency_code = htmlentities($row['invoice_currency_code']);
            $invoice_created_at = $row['invoice_created_at'];
            $client_id = $row['client_id'];
            $client_name = htmlentities($row['client_name']);
            $category_id = $row['category_id'];
            $category_name = htmlentities($row['category_name']);
            $client_currency_code = htmlentities($row['client_currency_code']);
            $client_net_terms = htmlentities($row['client_net_terms']);
            if($client_net_terms == 0){
              $client_net_terms = $config_default_net_terms;
            }

            $now = time();

            if(($invoice_status == "Sent" || $invoice_status == "Partial" || $invoice_status == "Viewed") && strtotime($invoice_due) + 86400 < $now ){
              $overdue_color = "text-danger font-weight-bold";
            }else{
              $overdue_color = "";
            }

            if($invoice_status == "Sent"){
              $invoice_badge_color = "warning text-white";
            }elseif($invoice_status == "Viewed"){
              $invoice_badge_color = "info";
            }elseif($invoice_status == "Partial"){
              $invoice_badge_color = "primary";
            }elseif($invoice_status == "Paid"){
              $invoice_badge_color = "success";
            }elseif($invoice_status == "Cancelled"){
              $invoice_badge_color = "danger";
            }else{
              $invoice_badge_color = "secondary";
            }

          ?>

          <tr>
            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
            <td><?php echo $invoice_scope_display; ?></td>
            <td><a href="client_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
            <td><?php echo $invoice_date; ?></td>
            <td class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></td>
            <td><?php echo $category_name; ?></td>
            <td>
              <span class="p-2 badge badge-<?php echo $invoice_badge_color; ?>">
                <?php echo $invoice_status; ?>
              </span>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
                  <div class="dropdown-divider"></div>
                  <?php if(!empty($config_smtp_host)){ ?>
                  <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send</a>
                  <div class="dropdown-divider"></div>
                  <?php } ?>
                  <a class="dropdown-item text-danger" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

            
            include("invoice_edit_modal.php");
            include("invoice_copy_modal.php");
          
          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 
  
  include("invoice_add_modal.php");
  include("category_quick_add_modal.php");

  include("footer.php");

?>