<?php include("header.php"); ?>

<?php 

if(isset($_GET['quote_id'])){

  $quote_id = intval($_GET['quote_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM quotes, clients, companies
    WHERE quotes.client_id = clients.client_id
    AND quotes.company_id = companies.company_id
    AND quotes.quote_id = $quote_id"
  );

  $row = mysqli_fetch_array($sql);
  $quote_id = $row['quote_id'];
  $quote_number = $row['quote_number'];
  $quote_scope = $row['quote_scope'];
  $quote_status = $row['quote_status'];
  $quote_date = $row['quote_date'];
  $quote_amount = $row['quote_amount'];
  $quote_note = $row['quote_note'];
  $quote_url_key = $row['quote_url_key'];
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
  $client_extension = $row['client_extension'];
  $client_mobile = $row['client_mobile'];
  if(strlen($client_mobile)>2){ 
    $client_mobile = substr($row['client_mobile'],0,3)."-".substr($row['client_mobile'],3,3)."-".substr($row['client_mobile'],6,4);
  }
  $client_website = $row['client_website'];
  $client_net_terms = $row['client_net_terms'];
  if($client_net_terms == 0){
    $client_net_terms = $config_default_net_terms;
  }
  $company_name = $row['company_name'];
  $company_country = $row['company_country'];
  $company_address = $row['company_address'];
  $company_city = $row['company_city'];
  $company_state = $row['company_state'];
  $company_zip = $row['company_zip'];
  $company_phone = $row['company_phone'];
  if(strlen($company_phone)>2){ 
    $company_phone = substr($row['company_phone'],0,3)."-".substr($row['company_phone'],3,3)."-".substr($row['company_phone'],6,4);
  }
  $company_email = $row['company_email'];
  $company_website = $row['company_website'];
  $company_logo = $row['company_logo'];

  $sql_history = mysqli_query($mysqli,"SELECT * FROM history WHERE quote_id = $quote_id ORDER BY history_id DESC");
  
  //Set Badge color based off of quote status
  if($quote_status == "Sent"){
    $quote_badge_color = "warning text-white";
  }elseif($quote_status == "Viewed"){
    $quote_badge_color = "primary";
  }elseif($quote_status == "Accepted"){
    $quote_badge_color = "success";
  }elseif($quote_status == "Declined"){
    $quote_badge_color = "danger";
  }elseif($quote_status == "Invoiced"){
    $quote_badge_color = "info";
  }else{
    $quote_badge_color = "secondary";
  }

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="quotes.php">Quotes</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=quotes"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item active"><?php echo $quote_number; ?></li>
  <span class="ml-3 p-2 badge badge-<?php echo $quote_badge_color; ?>"><?php echo $quote_status; ?></span>
</ol>


<div class="card">
  <div class="card-header d-print-none">

    <div class="row">

      <div class="col-md-4">
        <?php if($quote_status == 'Draft'){ ?>
        <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
          <i class="fas fa-fw fa-paper-plane"></i> Send
        </button>
        <div class="dropdown-menu">
          <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">Send Email</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="post.php?mark_quote_sent=<?php echo $quote_id; ?>">Mark Sent</a>
        </div>
        <?php } ?>

        <?php if($quote_status == 'Draft' or $quote_status == 'Sent' or $quote_status == 'Viewed'){ ?>
        <a class="btn btn-success" href="post.php?accept_quote=<?php echo $quote_id; ?>"><i class="fas fa-fw fa-check"></i> Accept</a>
        <a class="btn btn-danger" href="post.php?decline_quote=<?php echo $quote_id; ?>"><i class="fas fa-fw fa-times"></i> Decline</a>
        <?php } ?>

        <?php if($quote_status == 'Accepted'){ ?>
          <a class="btn btn-success btn-sm" href="#" data-toggle="modal" data-target="#addQuoteToInvoiceModal<?php echo $quote_id; ?>"><i class="fas fa-fw fa-check"></i> Invoice</a>
        <?php } ?>

      </div>

      <div class="col-md-8">
        <div class="dropdown dropleft text-center">
          <button class="btn btn-primary btn-sm float-right" type="button" data-toggle="dropdown">
            <i class="fas fa-ellipsis-h"></i>
          </button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editQuoteModal<?php echo $quote_id ?>">Edit</a>
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteCopyModal<?php echo $quote_id; ?>">Copy</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" onclick="window.print();">Print</a>
            <a class="dropdown-item" href="post.php?pdf_quote=<?php echo $quote_id; ?>">PDF</a>
            <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">Send Email</a>
            <a class="dropdown-item" href="guest_view_quote.php?quote_id=<?php echo "$quote_id&url_key=$quote_url_key"; ?>">Guest URL</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#">Delete</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card-body">

    <div class="row mb-4">
      <div class="col-sm-2">
        <img class="img-fluid" src="<?php echo $company_logo; ?>">
      </div>
      <div class="col-sm-10">
        <h3 class="text-right"><strong>Quote</strong><br><small class="text-secondary"><?php echo $quote_number; ?></small></h3>
      </div>
    </div>
    <div class="row mb-4">
      <div class="col-sm">
        <ul class="list-unstyled">
          <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
          <li><?php echo $company_address; ?></li>
          <li><?php echo "$company_city $company_state $company_zip"; ?></li>
          <li><?php echo $company_phone; ?></li>
          <li><?php echo $company_email; ?></li>
        </ul>
      </div>
      <div class="col-sm">
        <ul class="list-unstyled text-right">
          <li><h4><strong><?php echo $client_name; ?></strong></h4></li>
          <li><?php echo $client_address; ?></li>
          <li><?php echo "$client_city $client_state $client_zip"; ?></li>
          <li><?php echo "$client_phone $client_extension"; ?></li>
          <li><?php echo "$client_mobile"; ?></li>
          <li><?php echo $client_email; ?></li>
        </ul>
      </div>
    </div>
    <div class="row mb-4">
      <div class="col-sm-8">
      </div>
      <div class="col-sm-4">
        <table class="table">
          <tr>
            <td>Quote Date</td>
            <td class="text-right"><?php echo $quote_date; ?></td>
          </tr>
        </table>
      </div>
    </div>    

    <?php $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE quote_id = $quote_id ORDER BY item_id ASC"); ?>

    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          
          <table class="table">
            <thead>
              <tr>
                <th class="d-print-none"></th>
                <th>Item</th>
                <th>Description</th>
                <th class="text-center">QTY</th>
                <th class="text-right">Price</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php

              $total_tax = 0;
              $sub_total = 0;
        
              while($row = mysqli_fetch_array($sql_items)){
                $item_id = $row['item_id'];
                $item_name = $row['item_name'];
                $item_description = $row['item_description'];
                $item_quantity = $row['item_quantity'];
                $item_price = $row['item_price'];
                $item_subtotal = $row['item_price'];
                $item_tax = $row['item_tax'];
                $item_total = $row['item_total'];
                $tax_id = $row['tax_id'];
                $total_tax = $item_tax + $total_tax;
                $sub_total = $item_price * $item_quantity + $sub_total;

              ?>

              <tr>
                <td class="text-center d-print-none">
                  <a class="text-secondary" href="#" data-toggle="modal" data-target="#editQuoteItemModal<?php echo $item_id; ?>"><i class="fa fa-fw fa-edit"></i></a>
                  <a class="text-danger" href="post.php?delete_quote_item=<?php echo $item_id; ?>"><i class="fa fa-fw fa-trash-alt"></i></a>
                </td>
                <td><?php echo $item_name; ?></td>
                <td><?php echo $item_description; ?></td>
                <td class="text-center"><?php echo $item_quantity; ?></td>
                <td class="text-right">$<?php echo number_format($item_price,2); ?></td>
                <td class="text-right">$<?php echo number_format($item_tax,2); ?></td>
                <td class="text-right">$<?php echo number_format($item_total,2); ?></td>  
              </tr>

              <?php

              include("edit_quote_item_modal.php");

              }

              ?>

              <tr class="d-print-none">
                <form action="post.php" method="post" autocomplete="off">
                  <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">
                  <td></td>
                  <td><input type="text" class="form-control" name="name" placeholder="Item" required></td>
                  <td><textarea class="form-control" rows="2" name="description" placeholder="Description"></textarea></td>
                  <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: center;" name="qty" placeholder="QTY"></td>
                  <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: right;" name="price" placeholder="Price"></td>
                  <td>
                    <select class="form-control select2" name="tax_id" required>
                      <option value="0">None</option>
                      <?php 
                      
                      $taxes_sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE company_id = $session_company_id ORDER BY tax_name ASC"); 
                      while($row = mysqli_fetch_array($taxes_sql)){
                        $tax_id = $row['tax_id'];
                        $tax_name = $row['tax_name'];
                        $tax_percent = $row['tax_percent'];
                      ?>
                        <option value="<?php echo $tax_id; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                      
                      <?php
                      }
                      ?>
                    </select>
                  </td>
                  <td>
                    <button class="btn btn-link text-success" type="submit" name="add_quote_item">
                      <i class="fa fa-fw fa-check"></i>
                    </button>
                  </td>
                </form>  
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
            <div class="card-tools d-print-none">
              <a href="#" class="btn btn-tool" data-toggle="modal" data-target="#quoteNoteModal">
                <i class="fas fa-edit"></i>
              </a>
            </div>
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

    <hr class="d-none d-print-block mt-5">

    <center class="d-none d-print-block"><?php echo $config_quote_footer; ?></center>
  </div>
</div>

<div class="row mb-3">
  <div class="col-sm d-print-none">
    <div class="card">
      <div class="card-header">
        <i class="fa fa-fw fa-history"></i> History
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
          <button type="button" class="btn btn-tool" data-card-widget="remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
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
<?php include("quote_note_modal.php"); ?>

<?php } ?>

<?php include("footer.php");