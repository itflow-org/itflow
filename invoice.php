<?php include("header.php"); ?>

<?php 

if(isset($_GET['invoice_id'])){

  $invoice_id = intval($_GET['invoice_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $invoice_id
    AND invoices.company_id = $session_company_id"
  );

  if(mysqli_num_rows($sql) == 0){
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1></center>";
  }else{

  $row = mysqli_fetch_array($sql);
  $invoice_id = $row['invoice_id'];
  $invoice_number = $row['invoice_number'];
  $invoice_status = $row['invoice_status'];
  $invoice_date = $row['invoice_date'];
  $invoice_due = $row['invoice_due'];
  $invoice_amount = $row['invoice_amount'];
  $invoice_note = $row['invoice_note'];
  $invoice_url_key = $row['invoice_url_key'];
  $category_id = $row['category_id'];
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
  if($invoice_status !== "Paid" AND $invoice_status !== "Draft" AND $invoice_status !== "Cancelled"){
    $unixtime_invoice_due = strtotime($invoice_due) + 86400;
    if($unixtime_invoice_due < time()){
      $invoice_overdue = "Overdue";
    }
  }
  
  //Set Badge color based off of invoice status
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

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="invoices.php">Invoices</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=invoices"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item active"><?php echo $invoice_number; ?></li>
  <?php if(isset($invoice_overdue)){ ?>
  <span class="p-2 ml-2 badge badge-danger"><?php echo $invoice_overdue; ?></span>
  <?php } ?>
</ol>

<form action="post.php" method="post" autocomplete="off">
  <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
  
  <div class="card">
    <div class="card-header d-print-none">
      <div class="row">
        <div class="col-md-4">
          <button class="btn btn-success btn-sm" type="submit" name="save_invoice"><i class="fa fa-fw fa-check"></i> Save</button>
        </div>
        <div class="col-md-8">
          <div class="dropdown dropleft text-center">
            <button class="btn btn-primary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-fw fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">Edit</a>
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceRecurringModal<?php echo $invoice_id; ?>">Create Recurring</a>
              <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send</a>
              <?php if($invoice_status == 'Draft'){ ?><a class="dropdown-item" href="post.php?mark_invoice_sent=<?php echo $invoice_id; ?>">Mark Sent</a><?php } ?>
              <?php if($invoice_status !== 'Paid' and $invoice_status !== 'Cancelled'){ ?><a class="dropdown-item" href="#" data-toggle="modal" data-target="#addPaymentModal">Add Payment</a><?php } ?>
              <a class="dropdown-item" href="#" onclick="window.print();">Print</a>
              <a class="dropdown-item" href="guest_view_invoice.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"; ?>">Guest URL</a>
              <a class="dropdown-item" href="post.php?pdf_invoice=<?php echo $invoice_id; ?>">PDF</a>
              <?php if($invoice_status !== 'Cancelled' and $invoice_status !== 'Paid'){ ?>
              <a class="dropdown-item" href="post.php?cancel_invoice=<?php echo $invoice_id; ?>">Cancel</a>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="card-body">
        
      <div class="row mb-4">
        <div class="col-sm-2">
          <img class="img-fluid" src="<?php echo $config_invoice_logo; ?>">
        </div>
        <div class="col-sm-10">
          <div class="ribbon-wrapper">
            <div class="ribbon bg-<?php echo $invoice_badge_color; ?>">
              <?php echo $invoice_status; ?>
            </div>
          </div>
          <h3 class="text-right mt-5"><strong>Invoice</strong><br><small class="text-secondary"><?php echo $invoice_number; ?></small></h3>
        </div>
        
          
      </div>
      <div class="row mb-4">
        <div class="col-sm">
          <ul class="list-unstyled">
            <li><h4><strong><?php echo $config_company_name; ?></strong></h4></li>
            <li><?php echo $config_company_address; ?></li>
            <li><?php echo "$config_company_city $config_company_state $config_company_zip"; ?></li>
            <li>P: <?php echo $config_company_phone; ?></li>
            <li><?php echo $config_company_email; ?></li>
          </ul>
        </div>
        <div class="col-sm">
          <ul class="list-unstyled text-right">
            <li><h4><strong><?php echo $client_name; ?></strong></h4></li>
            <li><?php echo $client_address; ?></li>
            <li><?php echo "$client_city $client_state $client_zip"; ?></li>
            <li>P: <?php echo $client_phone; ?></li>
            <li>E: <?php echo $client_email; ?></li>
          </ul>
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-sm-8">
        </div>
        <div class="col-sm-4">
          <table class="table">
            <tr>
              <td>Invoice Date</td>
              <td class="text-right"><?php echo $invoice_date; ?></td>
            </tr>
            <tr>
              <td>Due Date</td>
              <td class="text-right"><div class="<?php echo $invoice_color; ?>"><?php echo $invoice_due; ?></div></td>
            </tr>
          </table>
        </div>
      </div>

      <?php $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY item_id ASC"); ?>

      <div class="row mb-4">
        <div class="col-md-12">
          <div class="card">
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
                  <td class="text-center d-print-none">
                    <a class="text-secondary" href="#" data-toggle="modal" data-target="#editItemModal<?php echo $item_id; ?>"><i class="fa fa-fw fa-edit"></i></a>
                    <a class="text-danger" href="post.php?delete_invoice_item=<?php echo $item_id; ?>"><i class="fa fa-fw fa-trash-alt"></i></a>
                  </td>
                  <td><?php echo $item_name; ?></td>
                  <td><?php echo $item_description; ?></td>
                  <td class="text-center"><?php echo $item_quantity; ?></td>
                  <td class="text-right text-monospace">$<?php echo number_format($item_price,2); ?></td>
                  <td class="text-right text-monospace">$<?php echo number_format($item_tax,2); ?></td>
                  <td class="text-right text-monospace">$<?php echo number_format($item_total,2); ?></td>  
                </tr>

                <?php 

                include("edit_item_modal.php");

                }

                ?>

                <tr class="d-print-none">
                  
                  <td></td>            
                  <td><input type="text" class="form-control" name="name"  placeholder="Product"></td>
                  <td><textarea class="form-control"  rows="1" name="description" placeholder="Description"></textarea></td>
                  <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: center;" name="qty" placeholder="Quantity"></td>
                  <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: right;" name="price" placeholder="Price"></td>
                  <td>
                    <select dir="rtl" class="form-control" name="tax">
                      <option <?php if($item_tax == '0.00'){ echo "selected"; } ?> value="0.00">None</option>
                      <option <?php if($item_tax == '0.07'){ echo "selected"; } ?> value="0.07">State Tax 7%</option>
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
          <div class="card d-none d-print-block">
            <div class="card-body">
              <div><?php echo $invoice_note; ?></div>
            </div>
          </div>
          <textarea rows="6" class="form-control mb-2 d-print-none" name="invoice_note" placeholder="Notes"><?php echo $invoice_note; ?></textarea>
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

    <hr class="d-none d-print-block mt-5">

    <center class="d-none d-print-block"><?php echo $config_invoice_footer; ?></center>
  </div>
</div>

<div class="row d-print-none mb-3">
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
      
            while($row = mysqli_fetch_array($sql_history)){
              $history_created_at = $row['history_created_at'];
              $history_status = $row['history_status'];
              $history_description = $row['history_description'];
             
            ?>
            <tr>
              <td><?php echo $history_created_at; ?></td>
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
              <th>Reference</th>
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
              $payment_reference = $row['payment_reference'];
              $account_name = $row['account_name'];

            ?>
            <tr>
              <td><?php echo $payment_date; ?></td>
              <td class=" text-right text-monospace">$<?php echo number_format($payment_amount,2); ?></td>
              <td><?php echo $payment_reference; ?></td>
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
<?php  
  } 
}
?>

<?php include("footer.php"); ?>

<script>

var products = [
  <?php 
  $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE company_id = $session_company_id");
  while($row = mysqli_fetch_array($sql)){
    $product_name = $row['product_name'];
    echo "\"$product_name\",";
  }
  ?>

];

var productCosts2 = [
  <?php 
  $sql = mysqli_query($mysqli,"SELECT product_id, product_name, product_cost FROM products WHERE company_id = $session_company_id");
  while($row = mysqli_fetch_array($sql)){
    $product_id = $row['product_id'];
    $product_name = $row['product_name'];
    $product_cost = $row['product_cost'];
    echo "\"$product_cost\",";
  }
  ?>

];


var productCosts = [
  <?php 
  $sql = mysqli_query($mysqli,"SELECT product_id, product_name, product_cost FROM products WHERE company_id = $session_company_id");
  while($row = mysqli_fetch_array($sql)){
    $product_id = $row['product_id'];
    $product_name = $row['product_name'];
    $product_cost = $row['product_cost'];
    echo "{ id: '$product_id', name: '$product_name', cost: '$product_cost' },";
  }
  ?>

];

$('#item').typeahead({
  source: products,
  afterSelect: function(){
    $('#item').val( '<?php echo $product_name; ?>' );
  }

});


</script>