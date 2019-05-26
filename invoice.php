<?php include("header.php"); ?>

<?php 

if(isset($_GET['invoice_id'])){

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
  $invoice_amount = $row['invoice_amount'];
  $invoice_note = $row['invoice_note'];
  $invoice_category_id = $row['category_id'];
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
  $client_net_terms = $row['client_net_terms'];
  if($client_net_terms == 0){
    $client_net_terms = $config_default_net_terms;
  }

  $sql_history = mysqli_query($mysqli,"SELECT * FROM history WHERE invoice_id = $invoice_id ORDER BY history_id DESC");
  
  $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payments.account_id = accounts.account_id AND payments.invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

  //Add up all the payments for the invoice and get the total amount paid to the invoice
  $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
  $row = mysqli_fetch_array($sql_amount_paid);
  $amount_paid = $row['amount_paid'];

  $balance = $invoice_amount - $amount_paid;

  //check to see if overdue

  $unixtime_invoice_due = strtotime($invoice_due);
  if($unixtime_invoice_due < time()){
    $invoice_status = "Overdue";
    $invoice_color = "text-danger";
  }
  
  //Set Badge color based off of invoice status
  if($invoice_status == "Sent"){
    $invoice_badge_color = "warning text-white";
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

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="invoices.php">Invoices</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item active">INV-<?php echo $invoice_number; ?></li>
  <span class="p-2 ml-2 badge badge-<?php echo $invoice_badge_color; ?>"><?php echo $invoice_status; ?></span>
</ol>



<form action="post.php" method="post">
  <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
  <div class="row mb-4 d-print-none">
    <div class="col-md-4">
      <button class="btn btn-success btn-sm" type="submit" name="save_invoice">Save</button>
    </div>
    <div class="col-md-8">
      <div class="dropdown dropleft text-center">
        <button class="btn btn-primary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-fw fa-ellipsis-v"></i>
        </button>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
          <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">Edit</a>
          <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
          <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceRecurringModal<?php echo $invoice_id; ?>">Recurring</a>
          <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send</a>
          <?php if($invoice_status == 'Draft'){ ?><a class="dropdown-item" href="post.php?mark_invoice_sent=<?php echo $invoice_id; ?>">Mark Sent</a><?php } ?>
          <?php if($invoice_status !== 'Paid' and $invoice_status !== 'Cancelled'){ ?><a class="dropdown-item" href="#" data-toggle="modal" data-target="#addPaymentModal">Add Payment</a><?php } ?>
          <a class="dropdown-item" href="#" onclick="window.print();">Print</a>
          <a class="dropdown-item" href="post.php?pdf_invoice=<?php echo $invoice_id; ?>">PDF</a>
          <?php if($invoice_status !== 'Cancelled' and $invoice_status !== 'Paid'){ ?>
          <a class="dropdown-item" href="post.php?cancel_invoice=<?php echo $invoice_id; ?>">Cancel</a>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>    

  <div class="row mb-4">
    <div class="col-sm">
      <div class="card">
        <div class="card-header">
          From
        </div>
        <div class="card-body">
          <ul class="list-unstyled">
            <li><strong><?php echo $config_company_name; ?></strong></li>
            <li><?php echo $config_company_address; ?></li>
            <li class="mb-3"><?php echo "$config_company_city $config_company_state $config_company_zip"; ?></li>
            <li><?php echo $config_company_phone; ?></li>
            <li><?php echo $config_company_email; ?></li>
          </ul>
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
          Details
        </div>
        <div class="card-body">
          <ul class="list-unstyled">
            <li class="mb-1"><strong>Invoice Number:</strong> <div class="float-right">INV-<?php echo $invoice_number; ?></div></li>
            <li class="mb-1"><strong>Invoice Date:</strong> <div class="float-right"><?php echo $invoice_date; ?></div></li>
            <li><strong>Payment Due:</strong> <div class="float-right <?php echo $invoice_color; ?>"><?php echo $invoice_due; ?></div></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <?php $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY item_id ASC"); ?>

  <div class="row mb-4">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          Items
        </div>
        
        <table class="table">
          <thead>
            <tr>
              <th class="d-print-none"></th>
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
      
            while($row = mysqli_fetch_array($sql_invoice_items)){
              $item_id = $row['item_id'];
              $item_name = $row['item_name'];
              $item_description = $row['item_description'];
              $item_quantity = $row['item_quantity'];
              $item_price = $row['item_price'];
              $item_subtotal = $row['item_price'];
              $item_tax = $row['item_tax'];
              $item_total = $row['item_total'];
              $total_tax = $item_tax + $total_tax;
              $sub_total = $item_price * $item_quantity + $sub_total;

            ?>

            <tr>
              <td class="text-center d-print-none"><a class="btn btn-sm btn-danger" href="post.php?delete_invoice_item=<?php echo $item_id; ?>"><i class="fa fa-trash"></i></a></td>
              <td><?php echo $item_name; ?></td>
              <td><?php echo $item_description; ?></td>
              <td class="text-center"><?php echo $item_quantity; ?></td>
              <td class="text-right text-monospace">$<?php echo number_format($item_price,2); ?></td>
              <td class="text-right text-monospace">$<?php echo number_format($item_tax,2); ?></td>
              <td class="text-right text-monospace">$<?php echo number_format($item_total,2); ?></td>  
            </tr>

            <?php 

            }

            ?>

            <tr class="d-print-none">
              
                <td></td>            
                <td><input type="text" class="form-control typeahead" name="name" id="item"></td>
                <td><textarea class="form-control" rows="1" name="description"></textarea></td>
                <td><input type="text" class="form-control" style="text-align: right;" name="price"></td>
                <td><input type="text" class="form-control" style="text-align: center;" name="qty"></td>
                <td>
                  <select dir="rtl" class="form-control" name="tax">
                    <option value="0.00">None</option>
                    <option value="0.07">State Tax 7%</option>
                  </select>
                </td>
                <td></td>  
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-7">
      <div class="card">
        <div class="card-header">
          Notes
        </div>
        <div class="card-body">
          <div class="d-none d-print-block"><?php echo $invoice_note; ?></div>
            <textarea rows="6" class="form-control mb-2 d-print-none" name="invoice_note"><?php echo $invoice_note; ?></textarea>
        
          
        </div>
      </div>
    </div>
    <div class="col-3 offset-2">
      <table class="table table-borderless">
        <tbody>    
          <tr class="border-bottom">
            <td>Subtotal</td>
            <td class="text-right text-monospace">$<?php echo number_format($sub_total,2); ?></td>
          </tr>
          <?php if($discount > 0){ ?>
          <tr class="border-bottom">
            <td>Discount</td>
            <td class="text-right text-monospace">$<?php echo number_format($invoice_discount,2); ?></td>          
          </tr>
          <?php } ?>
          <?php if($total_tax > 0){ ?>
          <tr class="border-bottom">
            <td>Tax</td>
            <td class="text-right text-monospace">$<?php echo number_format($total_tax,2); ?></td>        
          </tr>
          <?php } ?>
          <?php if($amount_paid > 0){ ?>
          <tr class="border-bottom">
            <td><div class="text-success">Paid to Date</div></td>
            <td class="text-right text-monospace text-success">$<?php echo number_format($amount_paid,2); ?></td>
          </tr>
          <?php } ?>
          <tr class="border-bottom">
            <td><strong>Balance Due</strong></td>
            <td class="text-right text-monospace"><strong>$<?php echo number_format($balance,2); ?></strong></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</form>

<div class="row mb-3">
  <div class="col-sm d-print-none">
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
      
            while($row = mysqli_fetch_array($sql_history)){
              $history_date = $row['history_date'];
              $history_status = $row['history_status'];
              $history_description = $row['history_description'];
             
            ?>
            <tr>
              <td><?php echo $history_date; ?></td>
              <td><?php echo $history_status; ?></td>
              <td><?php echo $history_description; ?></td>
            </tr>
            <?php
            }
            ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-sm d-print-none">
    <div class="card">
      <div class="card-header">
        Payments
      </div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Date</th>
              <th class="text-right">Amount</th>
              <th>Account</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
      
            while($row = mysqli_fetch_array($sql_payments)){
              $payment_id = $row['payment_id'];
              $payment_date = $row['payment_date'];
              $payment_amount = $row['payment_amount'];
              $account_name = $row['account_name'];

             
            ?>
            <tr>
              <td><?php echo $payment_date; ?></td>
              <td class=" text-right text-monospace">$<?php echo number_format($payment_amount,2); ?></td>
              <td><?php echo $account_name; ?></td>
              <td class="text-center"><a class="btn btn-danger btn-sm" href="post.php?delete_payment=<?php echo $payment_id; ?>"><i class="fa fa-trash"></i></a></td>
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

<?php include("add_payment_modal.php"); ?>
<?php include("edit_invoice_modal.php"); ?>
<?php include("add_invoice_copy_modal.php"); ?>
<?php include("add_invoice_recurring_modal.php"); ?>
<?php } ?>

<?php include("footer.php"); ?>

<script>

var products = [
  <?php 
  $sql = mysqli_query($mysqli,"SELECT product_name FROM products");
  while($row = mysqli_fetch_array($sql)){
    $product_name = $row['product_name'];
    echo "\"$product_name\",";
  }
  ?>

];

$('#item').typeahead({
  source: products

});

</script>