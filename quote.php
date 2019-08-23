<?php include("header.php"); ?>

<?php 

if(isset($_GET['quote_id'])){

  $quote_id = intval($_GET['quote_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients
    WHERE quotes.client_id = clients.client_id
    AND quotes.quote_id = $quote_id"
  );

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

  $sql_history = mysqli_query($mysqli,"SELECT * FROM history WHERE quote_id = $quote_id ORDER BY history_id DESC");
  
  //Set Badge color based off of quote status
  if($quote_status == "Sent"){
    $quote_badge_color = "warning text-white";
  }elseif($quote_status == "Viewed"){
    $quote_badge_color = "primary";
  }elseif($quote_status == "Approved"){
    $quote_badge_color = "success";
  }elseif($quote_status == "Cancelled"){
    $quote_badge_color = "danger";
  }else{
    $quote_badge_color = "secondary";
  }

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="quotes.php">Quotes</a>
  </li>
  <li class="breadcrumb-item active"><?php echo $quote_number; ?></li>
  <span class="ml-3 p-2 badge badge-<?php echo $quote_badge_color; ?>"><?php echo $quote_status; ?></span>
</ol>

<form class="d-print-none" action="post.php" method="post">
  <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">
  <div class="row mb-4 d-print-none">
    <div class="col-md-4">
      <button class="btn btn-success btn-sm" type="submit" name="save_quote">Save</button>
    </div>
    <div class="col-md-8">
      <div class="dropdown dropleft text-center">
        <button class="btn btn-primary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-ellipsis-h"></i>
        </button>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
          <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editQuoteModal<?php echo $quote_id ?>">Edit</a>
          <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteCopyModal<?php echo $quote_id; ?>">Copy</a>
          <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteToInvoiceModal<?php echo $quote_id; ?>">Quote > Invoice</a>
          <a class="dropdown-item" href="post.php?approve_quote=<?php echo $quote_id; ?>">Approve</a>
          <a class="dropdown-item" href="post.php?reject_quote=<?php echo $quote_id; ?>">Reject</a>
          <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">Send Email</a>
          <?php if($quote_status == "Draft"){ ?><a class="dropdown-item" href="post.php?mark_quote_sent=<?php echo $quote_id; ?>">Mark Sent</a><?php } ?>
          <a class="dropdown-item" href="#" onclick="window.print();">Print</a>
          <a class="dropdown-item" href="post.php?pdf_quote=<?php echo $quote_id; ?>">PDF</a>
          <a class="dropdown-item" href="#">Delete</a>
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
      
            while($row = mysqli_fetch_array($sql_items)){
              $item_id = $row['item_id'];
              $item_name = $row['item_name'];
              $item_description = $row['item_description'];
              $item_quantity = $row['item_quantity'];
              $item_price = $row['item_price'];
              $item_subtotal = $row['item_price'];
              $item_tax = $row['item_tax'];
              $item_total = $row['item_total'];
              $total_tax = $item_tax + $invoice_tax;
              $sub_total = $item_price * $item_quantity + $sub_total;

            ?>

            <tr>
              <td class="text-center d-print-none"><a class="btn btn-danger btn-sm" href="post.php?delete_quote_item=<?php echo $item_id; ?>"><i class="fa fa-trash"></i></a></td>
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

            <tr class="d-print-none">
                <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">
                <td></td>
                <td><input type="text" class="form-control" name="name"></td>
                <td><textarea class="form-control" rows="1" name="description"></textarea></td>
                <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: center;" name="qty"></td>
                <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: right;" name="price"></td>
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
          <div class="d-none d-print-block"><?php echo $quote_note; ?></div>
          <textarea rows="6" class="form-control mb-2 d-print-none" name="quote_note"><?php echo $quote_note; ?></textarea>
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
</div>

<?php include("edit_quote_modal.php"); ?>
<?php include("add_quote_to_invoice_modal.php"); ?>
<?php include("add_quote_copy_modal.php"); ?>

<?php } ?>

<?php include("footer.php");