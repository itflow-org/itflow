<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices, invoices
    WHERE invoices.invoice_id = recurring_invoices.invoice_id
    AND invoices.client_id = $client_id
    ORDER BY recurring_invoices.recurring_invoice_id DESC");
?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dT" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Frequency</th>
        <th>Start Date</th>
        <th>Last Sent</th>
        <th>Next Date</th>
        <th>Status</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
       while($row = mysqli_fetch_array($sql)){
            $recurring_invoice_id = $row['recurring_invoice_id'];
            $recurring_invoice_frequency = $row['recurring_invoice_frequency'];
            $recurring_invoice_status = $row['recurring_invoice_status'];
            $recurring_invoice_start_date = $row['recurring_invoice_start_date'];
            $recurring_invoice_last_sent = $row['recurring_invoice_last_sent'];
            $recurring_invoice_next_date = $row['recurring_invoice_next_date'];
            $invoice_id = $row['invoice_id'];

          ?>

          <tr>
            <td><?php echo $recurring_invoice_frequency; ?></td>
            <td><?php echo $recurring_invoice_start_date; ?></td>
            <td><?php echo $recurring_invoice_last_sent; ?></td>
            <td><?php echo $recurring_invoice_next_date; ?></td>
            <td><?php echo $recurring_invoice_status; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="recurring_invoice.php?recurring_invoice_id=<?php echo $recurring_invoice_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addinvoiceCopyModal<?php echo $invoice_id; ?>">Disable</a>
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