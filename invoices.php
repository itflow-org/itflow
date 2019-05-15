<?php include("header.php"); ?>

<?php 
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Sent'"));
  $sent_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Partial'"));
  $partial_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Draft'"));
  $draft_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Cancelled'"));
  $cancelled_count = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_due > CURDATE()"));
  $overdue_count = $row['num'];

  $sql_total_draft = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_draft FROM invoices WHERE invoice_status = 'Draft'");
  $row = mysqli_fetch_array($sql_total_draft);
  $total_draft = $row['total_draft'];

  $sql_total_sent = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_sent FROM invoices WHERE invoice_status = 'Sent'");
  $row = mysqli_fetch_array($sql_total_sent);
  $total_sent = $row['total_sent'];

  $sql_total_cancelled = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_cancelled FROM invoices WHERE invoice_status = 'Cancelled'");
  $row = mysqli_fetch_array($sql_total_cancelled);
  $total_cancelled = $row['total_cancelled'];
  
  $sql_total_partial = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_partial FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.invoice_status = 'Partial'");
  $row = mysqli_fetch_array($sql_total_partial);
  $total_partial = $row['total_partial'];
  $total_partial_count = mysqli_num_rows($sql_total_partial);

  $sql_total_overdue_partial = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_overdue_partial FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.invoice_status = 'Partial' AND invoices.invoice_due < CURDATE()");
  $row = mysqli_fetch_array($sql_total_overdue_partial);
  $total_overdue_partial = $row['total_overdue_partial'];

  $sql_total_overdue = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS total_overdue FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Paid' AND invoice_due < CURDATE()");
  $row = mysqli_fetch_array($sql_total_overdue);
  $total_overdue = $row['total_overdue'];

  $real_overdue_amount = $total_overdue - $total_overdue_partial;


  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    ORDER BY invoices.invoice_number DESC");
?>

<div class="row">
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
    <div class="card text-white bg-danger o-hidden">
      <div class="card-body">
        <div class="card-body-icon">
          <i class="fas fa-fw fa-skull-crossbones"></i>
        </div>
        <div class="mr-5"><?php echo $cancelled_count; ?> Cancelled <h1>$<?php echo number_format($total_cancelled,2); ?></h1></div>
      </div>
    </div>
  </div> 
</div>

<div class="card mb-3">
  <div class="card-header">
    <h5 class="float-left mt-2"><i class="fa fa-fw fa-file mr-2"></i>Invoices</h5>
    <button type="button" class="btn btn-primary badge-pill float-right" data-toggle="modal" data-target="#addInvoiceModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Number</th>
            <th>Client</th>
            <th class="text-right">Amount</th>
            <th>Invoice Date</th>
            <th>Due Date</th>
            <th>Status</th>
            <th class="text-center">Actions</th>
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

            $now = time();

            if(($invoice_status == "Sent" or $invoice_status == "Partial") and strtotime($invoice_due) < $now ){
              $overdue_color = "text-danger font-weight-bold";
            }else{
              $overdue_color = "";
            }

            //$unixtime_invoice_due = strtotime($invoice_due);
            //if($unixtime_invoice_due < time()){
             // $overdue_color = "text-danger";
            //}else{
             // $overdue_color = "";
            //}

            if($invoice_status == "Sent"){
              $invoice_badge_color = "warning";
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
            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>">INV-<?php echo $invoice_number; ?></a></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td class="text-right text-monospace">$<?php echo number_format($invoice_amount,2); ?></td>
            <td><?php echo $invoice_date; ?></td>
            <td class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></td>
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
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-copy"></i> Copy</a>
                  <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-paper-plane"></i> Send</a>
                  <a class="dropdown-item" href="post.php?pdf_invoice=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-file-pdf"></i> PDF</a>
                  <a class="dropdown-item" href="post.php?delete_invoice=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-trash"></i> Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php

          include("add_invoice_copy_modal.php");
          }

          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_invoice_modal.php"); ?>

<?php include("footer.php");