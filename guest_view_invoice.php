<?php include("guest_header.php"); ?>

<?php 

if(isset($_GET['invoice_id'], $_GET['url_key'])){

  $url_key = mysqli_real_escape_string($mysqli,$_GET['url_key']);
  $invoice_id = intval($_GET['invoice_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $invoice_id 
    AND invoices.invoice_url_key = '$url_key'"
  );

  if(mysqli_num_rows($sql) == 1){

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
    $company_id = $row['company_id'];

    $sql_company = mysqli_query($mysqli,"SELECT * FROM settings, companies WHERE settings.company_id = companies.company_id AND companies.company_id = $company_id");
    $row = mysqli_fetch_array($sql_company);

    $company_name = $row['company_name'];
    $config_company_address = $row['config_company_address'];
    $config_company_city = $row['config_company_city'];
    $config_company_state = $row['config_company_state'];
    $config_company_zip = $row['config_company_zip'];
    $config_company_phone = $row['config_company_phone'];
    if(strlen($config_company_phone)>2){ 
      $config_company_phone = substr($row['config_company_phone'],0,3)."-".substr($row['config_company_phone'],3,3)."-".substr($row['config_company_phone'],6,4);
    }
    $config_company_email = $row['config_company_email'];
    $config_invoice_logo = $row['config_invoice_logo'];
    $config_invoice_footer = $row['config_invoice_footer'];
    $config_stripe_enable = $row['config_stripe_enable'];
    $config_stripe_publishable = $row['config_stripe_publishable'];
    $config_stripe_secret = $row['config_stripe_secret'];

    $ip = get_ip();
    $os = get_os();
    $browser = get_web_browser();
    $device = get_device();

    //Update status to Viewed only if invoice_status = "Sent" 
    if($invoice_status == 'Sent'){
      mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Viewed' WHERE invoice_id = $invoice_id");
    }

    //Mark viewed in history
    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$invoice_status', history_description = 'Invoice viewed - $ip - $os - $browser - $device', history_created_at = NOW(), invoice_id = $invoice_id, company_id = $company_id");

    mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Invoice Viewed', alert_message = 'Invoice $invoice_number has been viewed by $client_name - $ip - $os - $browser - $device', alert_date = NOW(), company_id = $company_id");

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
        $invoice_color = "text-danger";
      }
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

  <div class="card">
    <div class="card-header d-print-none">
      <div class="float-right">
        <a class="btn btn-primary" href="#" onclick="window.print();"><i class="fa fa-fw fa-print"></i> Print</a>
        <a class="btn btn-primary" download target="_blank" href="guest_post.php?pdf_invoice=<?php echo $invoice_id; ?>&url_key=<?php echo $url_key; ?>"><i class="fa fa-fw fa-download"></i> Download</a>
        <?php
        if($invoice_status != "Paid" and $invoice_status  != "Cancelled" and $invoice_status != "Draft" and $config_stripe_enable == 1){
        ?>
        <a class="btn btn-success" href="guest_pay.php?invoice_id=<?php echo $invoice_id; ?>"><i class="fa fa-fw fa-credit-card"></i> Pay Online (Coming Soon)</a>
        <?php } ?>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-4">
        <div class="col-sm-2">
          <img class="img-fluid" src="<?php echo $config_invoice_logo; ?>">
        </div>
        <div class="col-sm-10">
          <h3 class="text-right"><strong>Invoice</strong><br><small class="text-secondary"><?php echo $invoice_number; ?></small></h3>
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-sm">    
          <ul class="list-unstyled">
            <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
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
            <table class="table table-striped">
              <thead>
                <tr>
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

              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-7">
          <div class="card">
            <div class="card-body">
              <div><?php echo $invoice_note; ?></div>
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

      <hr class="mt-5">

      <center><?php echo $config_invoice_footer; ?></center>
    </div>
  </div>

<?php 
  }else{
    echo "GTFO";
  }
}else{
  echo "GTFO";
} ?>

<?php include("guest_footer.php"); ?>