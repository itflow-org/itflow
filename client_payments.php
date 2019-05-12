<?php 
  $sql = mysqli_query($mysqli,"SELECT * FROM payments, invoices, accounts
    WHERE invoices.client_id = $client_id
    AND payments.invoice_id = invoices.invoice_id
    AND payments.account_id = accounts.account_id
    ORDER BY payments.payment_id DESC"); 
?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-credit-card"></i> Payments</h6>
  </div>
  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Invoice</th>
            <th class="text-right">Amount</th>
            <th>Account</th>
            <th>Method</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $invoice_id = $row['invoice_id'];
            $invoice_number = $row['invoice_number'];
            $invoice_status = $row['invoice_status'];
            $payment_date = $row['payment_date'];
            $payment_method = $row['payment_method'];
            $payment_amount = $row['payment_amount'];
            $account_name = $row['account_name'];

      
          ?>
          <tr>
            <td><?php echo $payment_date; ?></td>
            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>">INV-<?php echo $invoice_number; ?></a></td>
            <td class="text-right text-monospace">$<?php echo number_format($payment_amount,2); ?></td>
            <td><?php echo $account_name; ?></td>
            <td><?php echo $payment_method; ?></td>
          </tr>

          <?php
        
          }
          
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>