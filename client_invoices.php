<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, categories WHERE invoices.client_id = $client_id AND invoices.category_id = categories.category_id ORDER BY invoice_number DESC");

?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-file"></i> Invoices</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addInvoiceModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Number</th>
            <th class="text-right">Amount</th>
            <th>Date</th>
            <th>Due</th>
            <th>Category</th>
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
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
            $now = time();

            if(($invoice_status == "Sent" or $invoice_status == "Partial") and strtotime($invoice_due) < $now ){
                $overdue_color = "text-danger font-weight-bold";
              }else{
                $overdue_color = "";
              }

            //Set Badge color based off of invoice status
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
            <td class="text-right text-monospace">$<?php echo number_format($invoice_amount,2); ?></td>
            <td><?php echo $invoice_date; ?></td>
            <td><div class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></div></td>
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
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
                  <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send</a>
                  <a class="dropdown-item" href="post.php?pdf_invoice=<?php echo $invoice_id; ?>">PDF</a>
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
  </div>
</div>

<?php include("add_invoice_modal.php"); ?>