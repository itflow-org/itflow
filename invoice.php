<?php include("header.php"); ?>

<?php 

if(isset($_GET['invoice_id'])){
  $invoice_subtotal = 0.00;
  $invoice_tax = 0.00;
  $invoice_total = 0.00;

  $invoice_id = intval($_GET['invoice_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $invoice_id"
  );

  $row = mysqli_fetch_array($sql);
  $invoice_id = $row['invoice_id'];
  $invoice_number = $row['invoice_number'];
  $invoice_status = $row['invoice_status'];
  $invoice_date = $row['invoice_date'];
  $invoice_due = $row['invoice_due'];
  $invoice_subtotal = $row['invoice_subtotal'];
  $invoice_discount = $row['invoice_discount'];
  $invoice_tax = $row['invoice_tax'];
  $invoice_total = $row['invoice_total'];
  $invoice_paid = $row['invoice_paid'];
  $invoice_balance = $row['invoice_balance'];
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];
  $client_address = $row['client_address'];
  $client_city = $row['client_city'];
  $client_state = $row['client_state'];
  $client_zip = $row['client_zip'];
  $client_email = $row['client_email'];
  $client_phone = $row['client_phone'];
  if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
  }
  $client_website = $row['client_website'];

  $sql2 = mysqli_query($mysqli,"SELECT * FROM invoice_history WHERE invoice_id = $invoice_id ORDER BY invoice_history_id DESC");
  $sql3 = mysqli_query($mysqli,"SELECT * FROM invoice_payments, accounts WHERE invoice_payments.account_id = accounts.account_id AND invoice_payments.invoice_id = $invoice_id ORDER BY invoice_payments.invoice_payment_id DESC");

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



?>
<div class="row">
  <div class="col-md-11">
    <h3>Invoice #
      <small class="text-muted">INV-<?php echo $invoice_number; ?></small>
      <span class="badge badge-<?php echo $invoice_badge_color; ?>"><?php echo $invoice_status; ?></span>
    </h3>
  </div>
  <div class="col-md-1">
    <div class="dropdown dropleft text-center">
      <button class="btn btn-primary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-ellipsis-h"></i>
      </button>
      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editinvoiceModal<?php echo $invoice_id; ?>">Edit</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addinvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
        <a class="dropdown-item" href="email_invoice.php?invoice_id=<?php echo $invoice_id; ?>">Send Email</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoicePaymentModal">Add Payment</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addinvoiceCopyModal<?php echo $invoice_id; ?>">Print</a>
        <a class="dropdown-item" href="#">Delete</a>
      </div>
    </div>
  </div>
</div>    

<div class="row mb-3">
  <div class="col-sm">
    <div class="card">
      <div class="card-header">
        From
      </div>
      <div class="card-body">
        <strong><?php echo $config_company_name; ?></strong>
      </div>
    </div>
  </div>
  <div class="col-sm">
    <div class="card">
      <div class="card-header">
        Bill To
      </div>
      <div class="card-body">
        <ul class="list-unstyled">
          <li><strong><?php echo $client_name; ?></strong></li>
          <li><?php echo $client_address; ?></li>
          <li class="mb-3"><?php echo "$client_city $client_state $client_zip"; ?></li>
          <li><?php echo $client_phone; ?></li>
          <li><?php echo $client_email; ?></li>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-sm">
    <div class="card">
      <div class="card-header">
        Invoice # <?php echo $invoice_number; ?>
      </div>
      <div class="card-body">
        <ul class="list-unstyled">
          <li class="mb-1"><strong>Invoice Date:</strong> <div class="float-right"><?php echo $invoice_date; ?></div></li>
          <li><strong>Payment Due:</strong> <div class="float-right <?php echo $invoice_color; ?>"><?php echo $invoice_due; ?></div></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php $sql4 = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY invoice_item_id ASC"); ?>

<div class="row mb-3">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header">
        Items
      </div>
      
        <table class="table">
          <thead>
            <tr>
              <th></th>
              <th>Product</th>
              <th>Description</th>
              <th class="text-center">Qty</th>
              <th class="text-right">Price</th>
              <th class="text-right">Tax</th>
              <th class="text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql4)){
              $invoice_item_id = $row['invoice_item_id'];
              $invoice_item_name = $row['invoice_item_name'];
              $invoice_item_description = $row['invoice_item_description'];
              $invoice_item_quantity = $row['invoice_item_quantity'];
              $invoice_item_price = $row['invoice_item_price'];
              $invoice_item_subtotal = $row['invoice_item_price'];
              $invoice_item_tax = $row['invoice_item_tax'];
              $invoice_item_total = $row['invoice_item_total'];

            ?>

            <tr>
              <td class="text-center"><a class="btn btn-danger btn-sm" href="post.php?delete_invoice_item=<?php echo $invoice_item_id; ?>"><i class="fa fa-trash"></i></a></td>
              <td><?php echo $invoice_item_name; ?></td>
              <td><?php echo $invoice_item_description; ?></td>
              <td class="text-center"><?php echo $invoice_item_quantity; ?></td>
              <td class="text-right">$<?php echo number_format($invoice_item_price,2); ?></td>
              <td class="text-right">$<?php echo number_format($invoice_item_tax,2); ?></td>
              <td class="text-right">$<?php echo number_format($invoice_item_total,2); ?></td>  
            </tr>

            <?php 

            }

            ?>

            <tr>
              <form action="post.php" method="post">
                <td class="text-center"><button type="submit" class="btn btn-primary btn-sm" name="add_invoice_item"><i class="fa fa-check"></i></button></td>
                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                <td><input type="text" class="form-control" name="name"></td>
                <td><textarea class="form-control" rows="1" name="description"></textarea></td>
                <td><input type="text" class="form-control" name="qty"></td>
                <td class="text-right"><input type="text" class="form-control" name="price"></td>
                <td>
                  <select class="form-control" name="tax">
                    <option value="0.00">None</option>
                    <option value="0.07">State Tax 7%</option>
                  </select>
                </td>
                <td class="text-right">$ 0.00</td>  
              </form>
            </tr>
            <tr>
              <td colspan="5"></td>
              <td><strong>SubTotal</strong></td>
              <td class="text-right">$<?php echo number_format($invoice_subtotal,2); ?></td>
            </tr>
            <tr>
              <td colspan="5"></td>
              <td><strong>Discount</strong></td>
              <td class="text-right">$<?php echo number_format($invoice_discount,2); ?></td>             
            </tr>
            <tr>
              <td colspan="5"></td>
              <td><strong>Tax</strong></td>
              <td class="text-right">$<?php echo number_format($invoice_tax,2); ?></td>        
            </tr>
            <tr>
              <td colspan="5"></td>
              <td><strong>Total</strong></td>
              <td class="text-right">$<?php echo number_format($invoice_total,2); ?></td>
            </tr>
          </tbody>
        </table>   
    </div>
  </div>
</div>
<div class="row mb-3">
  <div class="col-sm">
    <div class="card">
      <div class="card-body">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Invoice Notes</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">History</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact" aria-selected="false">Payments</a>
          </li>
        </ul>
        <div class="tab-content" id="myTabContent">
          <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">...</div>
          <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">...</div>
          <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">...</div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row mb-3">
  <div class="col-sm">
    <div class="card">
      <div class="card-header">
        Invoice Note
      </div>
      <div class="card-body">
        <textarea class="form-control" rows="8" name="invoice_note"></textarea>
      </div>
    </div>
  </div>
  <div class="col-sm">
    <div class="card">
      <div class="card-header">
        History
      </div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Status</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql2)){
              $invoice_history_date = $row['invoice_history_date'];
              $invoice_history_status = $row['invoice_history_status'];
              $invoice_history_description = $row['invoice_history_description'];
             
            ?>
            <tr>
              <td><?php echo $invoice_history_date; ?></td>
              <td><?php echo $invoice_history_status; ?></td>
              <td><?php echo $invoice_history_description; ?></td>
            </tr>
            <?php
            }
            ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-sm">
    <div class="card">
      <div class="card-header">
        Payments
      </div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Account</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql3)){
              $invoice_payment_id = $row['invoice_payment_id'];
              $invoice_payment_date = $row['invoice_payment_date'];
              $invoice_payment_amount = $row['invoice_payment_amount'];
              $account_name = $row['account_name'];

             
            ?>
            <tr>
              <td><?php echo $invoice_payment_date; ?></td>
              <td>$<?php echo number_format($invoice_payment_amount,2); ?></td>
              <td><?php echo $account_name; ?></td>
              <td class="text-center"><a class="btn btn-danger btn-sm" href="post.php?delete_invoice_payment=<?php echo $invoice_payment_id; ?>"><i class="fa fa-trash"></i></a></td>
            </tr>
            <?php
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include("add_invoice_payment_modal.php"); ?>

<?php } ?>

<?php include("footer.php");