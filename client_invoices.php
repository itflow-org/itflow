<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE client_id = $client_id ORDER BY invoices.invoice_date DESC");

?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dT" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Number</th>
        <th class="text-right">Amount</th>
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
        $invoice_balance = $row['invoice_balance'];

      ?>

      <tr>
        <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>">INV-<?php echo "$invoice_number"; ?></a></td>
        <td class="text-right text-monospace">$<?php echo "$invoice_balance"; ?></td>
        <td><?php echo "$invoice_date"; ?></td>
        <td><?php echo "$invoice_due"; ?></td>
        <td><?php echo "$invoice_status"; ?></td>
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

      include("edit_invoice_modal.php");
      include("add_invoice_copy_modal.php");
      }

      ?>

    </tbody>
  </table>
</div>