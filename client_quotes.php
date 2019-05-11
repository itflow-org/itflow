<?php 
 
  $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE client_id = $client_id AND invoice_number > 0 ORDER BY invoice_number DESC");

?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-file"></i> Quotes</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientQuoteModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Number</th>
            <th class="text-right">Amount</th>
            <th>Date</th>
            <th>Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $quote_id = $row['quote_id'];
            $quote_number = $row['quote_number'];
            $quote_status = $row['quote_status'];
            $quote_date = $row['quote_date'];
            $quote_amount = $row['quote_amount'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];

            if($quote_status == "Sent"){
              $quote_badge_color = "warning";
            }elseif($quote_status == "Viewed"){
              $quote_badge_color = "primary";
            }elseif($quote_status == "Approved"){
              $quote_badge_color = "success";
            }else{
              $quote_badge_color = "secondary";
            }

          ?>

          <tr>
            <td><a href="quote.php?quote_id=<?php echo $quote_id; ?>">QUO-<?php echo $quote_number; ?></a></td>
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
                  <a class="dropdown-item" href="post.php?pdf_invoice=<?php echo $invoice_id; ?>">PDF</a>
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
  </div>
</div>

<?php include("add_client_quote_modal.php"); ?>