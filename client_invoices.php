<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE client_id = $client_id AND invoice_number > 0 ORDER BY invoice_number DESC");

?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dT" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Number</th>
        <th class="text-right">Amount</th>
        <th class="text-right">Balance</th>
        <th>Date</th>
        <th>Due</th>
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
        //check to see if overdue

        $unixtime_invoice_due = strtotime($invoice_due);
        if($unixtime_invoice_due < time()){
          $invoice_status = "Overdue";
          $invoice_color = "text-danger";
        }
        
        //Set Badge color based off of invoice status
        if($invoice_status == "Sent"){
          $invoice_badge_color = "warning";
        }elseif($invoice_status == "Partial"){
          $invoice_badge_color = "primary";
        }elseif($invoice_status == "Paid"){
          $invoice_badge_color = "success";
        }elseif($invoice_status == "Overdue"){
          $invoice_badge_color = "danger";
        }else{
          $invoice_badge_color = "secondary";
        }

        //Add up all the payments for the invoice and get the total amount paid to the invoice

        $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_amount_paid);
        
        $amount_paid = $row['amount_paid'];

        $balance = $invoice_amount - $amount_paid;
        //set Text color on balance
        if($balance > 0){
          $balance_text_color = "text-danger";
        }else{
          $balance_text_color = "";
        }

      ?>

      <tr>
        <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>">INV-<?php echo $invoice_number; ?></a></td>
        <td class="text-right text-monospace">$<?php echo number_format($invoice_amount,2); ?></td>
        <td class="text-right text-monospace <?php echo $balance_text_color; ?>">$<?php echo number_format($balance,2); ?></td>
        <td><?php echo $invoice_date; ?></td>
        <td><div class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></div></td>
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
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editinvoiceModal<?php echo $invoice_id; ?>">Edit</a>
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addinvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addinvoiceCopyModal<?php echo $invoice_id; ?>">PDF</a>
              <a class="dropdown-item" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php

      //include("edit_invoice_modal.php");
      include("add_invoice_copy_modal.php");
      }

      ?>

    </tbody>
  </table>
</div>