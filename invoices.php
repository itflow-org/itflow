<?php 
  
  include("header.php");
  
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
  
  $sql_total_partial = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_partial FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.invoice_status = 'Partial' AND invoices.company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_partial);
  $total_partial = $row['total_partial'];
  $total_partial_count = mysqli_num_rows($sql_total_partial);

  $sql_total_overdue_partial = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_overdue_partial FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.invoice_status = 'Partial' AND invoices.invoice_due < CURDATE() AND invoices.company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_overdue_partial);
  $total_overdue_partial = $row['total_overdue_partial'];

  $sql_total_overdue = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_overdue FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Paid' AND invoice_due < CURDATE() AND invoices.company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_overdue);
  $total_overdue = $row['total_overdue'];

  $real_overdue_amount = $total_overdue - $total_overdue_partial;

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
    $sb = $_GET['sb'];
  }else{
    $sb = "invoice_number";
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

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM invoices, clients, categories
    WHERE invoices.client_id = clients.client_id
    AND invoices.category_id = categories.category_id
    AND invoices.company_id = $session_company_id
    AND DATE(invoice_date) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR category_name LIKE '%$q%') 
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="row">
  <div class="col-lg-3">
    <!-- small box -->
    <a href="?q=Draft" class="small-box bg-secondary">
      <div class="inner">
        <h3>$<?php echo number_format($total_draft,2); ?></h3>
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
    <a href="?q=Sent" class="small-box bg-warning">
      <div class="inner text-white">
        <h3>$<?php echo number_format($total_sent,2); ?></h3>
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
    <a href="?q=Viewed" class="small-box bg-info">
      <div class="inner">
        <h3>$<?php echo number_format($total_viewed,2); ?></h3>
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
    <a href="?q=Partial" class="small-box bg-primary">
      <div class="inner">
        <h3>$<?php echo number_format($total_partial,2); ?></h3>
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_number&o=<?php echo $disp; ?>">Number</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_scope&o=<?php echo $disp; ?>">Scope</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_date&o=<?php echo $disp; ?>">Invoice Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_due&o=<?php echo $disp; ?>">Due Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_status&o=<?php echo $disp; ?>">Status</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $invoice_id = $row['invoice_id'];
            $invoice_prefix = $row['invoice_prefix'];
            $invoice_number = $row['invoice_number'];
            $invoice_scope = $row['invoice_scope'];
            $invoice_status = $row['invoice_status'];
            $invoice_date = $row['invoice_date'];
            $invoice_due = $row['invoice_due'];
            $invoice_amount = $row['invoice_amount'];
            $invoice_created_at = $row['invoice_created_at'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
            $client_net_terms = $row['client_net_terms'];
            if($client_net_terms == 0){
              $client_net_terms = $config_default_net_terms;
            }

            $now = time();

            if(($invoice_status == "Sent" or $invoice_status == "Partial" or $invoice_status == "Viewed") and strtotime($invoice_due) + 86400 < $now ){
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
            <td><?php echo $invoice_scope; ?></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=invoices"><?php echo $client_name; ?></a></td>
            <td class="text-right">$<?php echo number_format($invoice_amount,2); ?></td>
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
                  <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send</a>
                  <a class="dropdown-item" href="post.php?pdf_invoice=<?php echo $invoice_id; ?>">PDF</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

            include("add_invoice_copy_modal.php");
            include("edit_invoice_modal.php");
          
          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 
  
  include("add_invoice_modal.php");
  include("add_quick_modal.php");

  include("footer.php");

?>