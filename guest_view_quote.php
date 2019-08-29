<?php include("guest_header.php"); ?>

<?php 

if(isset($_GET['quote_id'], $_GET['url_key'])){

  $url_key = mysqli_real_escape_string($mysqli,$_GET['url_key']);
  $quote_id = intval($_GET['quote_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients
    WHERE quotes.client_id = clients.client_id
    AND quotes.quote_id = $quote_id
    AND quotes.quote_url_key = '$url_key'"
  );

  if(mysqli_num_rows($sql) == 1){

    $row = mysqli_fetch_array($sql);

    $quote_id = $row['quote_id'];
    $quote_number = $row['quote_number'];
    $quote_status = $row['quote_status'];
    $quote_date = $row['quote_date'];
    $quote_amount = $row['quote_amount'];
    $quote_note = $row['quote_note'];
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

    //Mark viewed in history
    mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = '$quote_status', history_description = 'Quote viewed', history_created_at = NOW(), quote_id = $quote_id, company_id = $company_id");

    //Update status to Viewed only if invoice_status = "Sent" 
    if($quote_status == 'Sent'){
      mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Viewed' WHERE quote_id = $quote_id");
    }

    //Set Badge color based off of quote status
    if($quote_status == "Sent"){
      $quote_badge_color = "warning text-white";
    }elseif($quote_status == "Viewed"){
      $quote_badge_color = "info";
    }elseif($quote_status == "Approved"){
      $quote_badge_color = "success";
    }elseif($quote_status == "Cancelled"){
      $quote_badge_color = "danger";
    }else{
      $quote_badge_color = "secondary";
    }

  ?>

  <div class="row d-print-none">
    <div class="col-md-6">
      <h2>Quote <?php echo $quote_number; ?><span class="p-2 ml-2 badge badge-<?php echo $quote_badge_color; ?>"><?php echo $quote_status; ?></span></h2>
    </div>
    <div class="col-md-6">
      <div class="float-right">
        <a class="btn btn-primary" href="#" onclick="window.print();"><i class="fa fa-fw fa-print"></i> Print</a>
        <a class="btn btn-primary" download target="_blank" href="guest_post.php?pdf_quote=<?php echo $quote_id; ?>&url_key=<?php echo $url_key; ?>"><i class="fa fa-fw fa-download"></i> Download</a>
        <?php
        if($quote_status == "Draft" or $quote_status == "Sent"){
        ?>
        <a class="btn btn-success" href="guest_post.php?approve_quote=<?php echo $quote_id; ?>&url_key=<?php echo $url_key; ?>"><i class="fa fa-fw fa-thumbs-up"></i> Approve</a>
        <a class="btn btn-danger" href="guest_post.php?reject_quote=<?php echo $quote_id; ?>&url_key=<?php echo $url_key; ?>"><i class="fa fa-fw fa-thumbs-down"></i> Reject</a>
        <?php } ?>
      </div>
    </div>
  </div>

  <hr>

  <div class="row mb-4">
    <div class="col-sm-2">
      <img class="img-fluid" src="<?php echo $config_invoice_logo; ?>">
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
            <li><strong><?php echo $company_name; ?></strong></li>
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
          Quote To
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
            <li class="mb-1"><strong>Quote Number:</strong> <div class="float-right"><?php echo $quote_number; ?></div></li>
            <li class="mb-1"><strong>Quote Date:</strong> <div class="float-right"><?php echo $quote_date; ?></div></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <?php $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id ORDER BY item_id ASC"); ?>

  <div class="row mb-4">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          Items
        </div>
        
        <table class="table">
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
      
            while($row = mysqli_fetch_array($sql_items)){
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
              <td class="text-right">$<?php echo number_format($item_price,2); ?></td>
              <td class="text-right">$<?php echo number_format($item_tax,2); ?></td>
              <td class="text-right">$<?php echo number_format($item_total,2); ?></td>  
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
        <div class="card-header">
          Notes
        </div>
        <div class="card-body">
          <div><?php echo $quote_note; ?></div>
        </div>
      </div>
    </div>

    <div class="col-3 offset-2">
      <table class="table table-borderless">
        <tbody>    
          <tr class="border-bottom">
            <td>Subtotal</td>
            <td class="text-right">$<?php echo number_format($sub_total,2); ?></td>
          </tr>
          <?php if($discount > 0){ ?>
          <tr class="border-bottom">
            <td>Discount</td>
            <td class="text-right">$<?php echo number_format($quote_discount,2); ?></td>          
          </tr>
          <?php } ?>
          <?php if($total_tax > 0){ ?>
          <tr class="border-bottom">
            <td>Tax</td>
            <td class="text-right">$<?php echo number_format($total_tax,2); ?></td>        
          </tr>
          <?php } ?>
          <tr class="border-bottom">
            <td><strong>Total</strong></td>
            <td class="text-right"><strong>$<?php echo number_format($quote_amount,2); ?></strong></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

<?php 
  }else{
    echo "GTFO";
  }
}else{
  echo "GTFO";
} ?>

<?php include("guest_footer.php");