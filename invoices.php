<?php include("header.php"); ?>

<?php 
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Sent' AND company_id = $session_company_id"));
  $sent_count = $row['num'];

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

  $sql_total_cancelled = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_cancelled FROM invoices WHERE invoice_status = 'Cancelled' AND company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_total_cancelled);
  $total_cancelled = $row['total_cancelled'];
  
  $sql_total_partial = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_partial FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.invoice_status = 'Partial' AND invoices.company_id = $session_company_id");
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
    $q = $_GET['q'];
  }else{
    $q = "";
  }

  if(!empty($_GET['sb'])){
    $sb = $_GET['sb'];
  }else{
    $sb = "invoice_id";
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

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM invoices, clients, categories
    WHERE invoices.client_id = clients.client_id
    AND invoices.category_id = categories.category_id
    AND invoices.company_id = $session_company_id
    AND (invoice_number LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="row">
  <div class="col-xl-3 col-sm-6 mb-3">
    <div class="card text-white bg-secondary o-hidden">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-pencil-ruler"></i>
        </div>
        <div class="mr-5"><?php echo $draft_count; ?> Draft <h1>$<?php echo number_format($total_draft,2); ?></h1></div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-sm-6 mb-3">
    <div class="card text-white bg-warning o-hidden">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-paper-plane"></i>
        </div>
        <div class="mr-5"><?php echo $sent_count; ?> Sent <h1>$<?php echo number_format($total_sent,2); ?></h1></div>
      </div>
    </div>
  </div>
  
  <div class="col-xl-3 col-sm-6 mb-3">
    <div class="card text-white bg-primary o-hidden">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-wine-glass-alt"></i>
        </div>
        <div class="mr-5"><?php echo $partial_count; ?> Partial <h1>$<?php echo number_format($total_partial,2); ?></h1></div>        
      </div>      
    </div>
  </div>
  
  <div class="col-xl-3 col-sm-6 mb-3">
    <div class="card text-white bg-danger o-hidden">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-ban"></i>
        </div>
        <div class="mr-5"><?php echo $cancelled_count; ?> Cancelled <h1>$<?php echo number_format($total_cancelled,2); ?></h1></div>
      </div>
    </div>
  </div> 
</div>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-2"><i class="fa fa-fw fa-file mr-2"></i>Invoices</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addInvoiceModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo $q;} ?>" placeholder="Search Invoices">
        <div class="input-group-append">
          <button class="btn btn-primary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_number&o=<?php echo $disp; ?>">Number</a></th>
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
            $invoice_number = $row['invoice_number'];
            $invoice_status = $row['invoice_status'];
            $invoice_date = $row['invoice_date'];
            $invoice_due = $row['invoice_due'];
            $invoice_amount = $row['invoice_amount'];
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
            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo $invoice_number; ?></a></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td class="text-right text-monospace">$<?php echo number_format($invoice_amount,2); ?></td>
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
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-copy"></i> Copy</a>
                  <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-paper-plane"></i> Send</a>
                  <a class="dropdown-item" href="post.php?pdf_invoice=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-file-pdf"></i> PDF</a>
                  <a class="dropdown-item" href="post.php?delete_invoice=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-trash"></i> Delete</a>
                </div>
              </div>
              <?php

              include("add_invoice_copy_modal.php");
              include("edit_invoice_modal.php");

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

<?php include("add_invoice_modal.php"); ?>

<?php include("footer.php");