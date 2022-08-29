<?php include("inc_all.php"); ?>

<?php 

if(isset($_GET['recurring_id'])){

  $recurring_id = intval($_GET['recurring_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM recurring 
    LEFT JOIN clients ON recurring_client_id = client_id
    LEFT JOIN locations ON primary_location = location_id
    LEFT JOIN contacts ON primary_contact = contact_id
    LEFT JOIN companies ON recurring.company_id = companies.company_id
    WHERE recurring_id = $recurring_id"
  );

  $row = mysqli_fetch_array($sql);
  $recurring_id = $row['recurring_id'];
  $recurring_prefix = $row['recurring_prefix'];
  $recurring_number = $row['recurring_number'];
  $recurring_scope = $row['recurring_scope'];
  $recurring_frequency = $row['recurring_frequency'];
  $recurring_status = $row['recurring_status'];
  $recurring_created_at = $row['recurring_created_at'];
  $recurring_last_sent = $row['recurring_last_sent'];
  if($recurring_last_sent == 0){
    $recurring_last_sent = '-';
  }
  $recurring_next_date = $row['recurring_next_date'];
  $recurring_amount = $row['recurring_amount'];
  $recurring_currency_code = $row['recurring_currency_code'];
  $recurring_note = $row['recurring_note'];
  $recurring_created_at = $row['recurring_created_at'];
  $category_id = $row['recurring_category_id'];
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];
  $location_address = $row['location_address'];
  $location_city = $row['location_city'];
  $location_state = $row['location_state'];
  $location_zip = $row['location_zip'];
  $contact_email = $row['contact_email'];
  $contact_phone = formatPhoneNumber($row['contact_phone']);
  $contact_extension = $row['contact_extension'];
  $contact_mobile = formatPhoneNumber($row['contact_mobile']);
  $client_website = $row['client_website'];
  $client_currency_code = $row['client_currency_code'];
  $client_net_terms = $row['client_net_terms'];
  
  if($recurring_status == 1){
    $status = "Active";
    $status_badge_color = "success";
  }else{
    $status = "Inactive";
    $status_badge_color = "secondary";
  }
  $company_id = $row['company_id'];
  $company_name = $row['company_name'];
  $company_country = $row['company_country'];
  $company_address = $row['company_address'];
  $company_city = $row['company_city'];
  $company_state = $row['company_state'];
  $company_zip = $row['company_zip'];
  $company_phone = formatPhoneNumber($row['company_phone']);
  $company_email = $row['company_email'];
  $company_website = $row['company_website'];
  $company_logo = $row['company_logo'];

  $sql_history = mysqli_query($mysqli,"SELECT * FROM history WHERE history_recurring_id = $recurring_id ORDER BY history_id DESC");

  //Product autocomplete
  $products_sql = mysqli_query($mysqli,"SELECT product_name AS label, product_description AS description, product_price AS price FROM products WHERE company_id = $session_company_id");

  if(mysqli_num_rows($products_sql) > 0){
    while($row = mysqli_fetch_array($products_sql)){
      $products[] = $row;
    }
    $json_products = json_encode($products);
  }

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="recurring_invoices.php"> Recurring Invoices</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=recurring_invoices"> <?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item active"><?php echo "$recurring_prefix$recurring_number"; ?></li>
  <span class="ml-3 p-2 badge badge-<?php echo $status_badge_color; ?>"><?php echo $status; ?></span>
</ol>

<div class="card">
  <div class="card-header d-print-none">

    <div class="row">
      <div class="col-md-4">
      </div>
      <div class="col-md-8">
        <div class="dropdown dropleft text-center">
          <button class="btn btn-primary btn-sm float-right" type="button" data-toggle="dropdown">
            <i class="fas fa-ellipsis-h"></i>
          </button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRecurringModal<?php echo $recurring_id; ?>">Edit</a>
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editNextDateRecurringModal<?php echo $recurring_id; ?>">Set Next Date</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="post.php?force_recurring=<?php echo $recurring_id; ?>">Force Send</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">Delete</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="card-body">

    <div class="row mb-4">
      <div class="col-sm-2">
        <img class="img-fluid" src="<?php echo "uploads/settings/$company_id/$company_logo"; ?>">
      </div>
      <div class="col-sm-10">
        <h3 class="text-right"><strong>Recurring Invoice</strong><br><small class="text-secondary"><?php echo ucwords($recurring_frequency); ?>ly</small></h3>
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
          <li><?php echo $location_address; ?></li>
          <li><?php echo "$location_city $location_state $location_zip"; ?></li>
          <li><?php echo "$contact_phone $contact_extension"; ?></li>
          <li><?php echo $contact_mobile; ?></li>
          <li><?php echo $contact_email; ?></li>
        </ul>
      </div>
    </div>
    <div class="row mb-4">
      <div class="col-sm-8">
      </div>
      <div class="col-sm-4">
        <table class="table">
          <tr>
            <td>Created</td>
            <td class="text-right"><?php echo $recurring_created_at; ?></td>
          </tr>
          <tr>
            <td>Next Date</td>
            <td class="text-right"><?php echo $recurring_next_date; ?></td>
          </tr>
          <tr>
            <td>Last Sent</td>
            <td class="text-right"><?php echo $recurring_last_sent; ?></td>
          </tr>
        </table>
      </div>
    </div>   

    <?php $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id ORDER BY item_id ASC"); ?>

    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th class="d-print-none"></th>
                  <th>Item</th>
                  <th>Description</th>
                  <th class="text-center">Qty</th>
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
                  $item_created_at = $row['item_created_at'];
                  $tax_id = $row['item_tax_id'];
                  $total_tax = $item_tax + $total_tax;
                  $sub_total = $item_price * $item_quantity + $sub_total;

                ?>

                <tr>
                  <td class="text-center d-print-none">
                    <a class="text-secondary" href="#" data-toggle="modal" data-target="#editItemModal<?php echo $item_id; ?>"><i class="fa fa-fw fa-edit"></i></a>
                    <a class="text-danger" href="post.php?delete_recurring_item=<?php echo $item_id; ?>"><i class="fa fa-fw fa-trash-alt"></i></a>
                  </td>
                  <td><?php echo $item_name; ?></td>
                  <td><div style="white-space:pre-line"><?php echo $item_description; ?></div></td>
                  <td class="text-center"><?php echo $item_quantity; ?></td>
                  <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_price, $recurring_currency_code); ?></td>
                  <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_tax, $recurring_currency_code); ?></td>
                  <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $recurring_currency_code); ?></td>  
                </tr>

                <?php

                include("item_edit_modal.php"); 

                }

                ?>

                <tr class="d-print-none">
                  <form action="post.php" method="post">
                    <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">
                    <td></td>            
                    <td><input type="text" class="form-control" id="name" name="name" placeholder="Item" required></td>
                    <td><textarea class="form-control"  rows="1" id="desc" name="description" placeholder="Description"></textarea></td>
                    <td><input type="number" step="0.01" min="0" class="form-control" style="text-align: center;" id="qty" name="qty" placeholder="QTY"></td>
                    <td><input type="number" step="0.01" class="form-control" style="text-align: right;" id="price" name="price" placeholder="Price"></td>
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
                      <button class="btn btn-link text-success" type="submit" name="add_recurring_item">
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
    </div>

    <div class="row mb-4">
      <div class="col-sm-7">
        <div class="card">
          <div class="card-header">
            Notes
            <div class="card-tools d-print-none">
              <a href="#" class="btn btn-tool" data-toggle="modal" data-target="#recurringNoteModal">
                <i class="fas fa-edit"></i>
              </a>
            </div>
          </div>
          <div class="card-body">
            <div style="white-space:pre-line"><?php echo $recurring_note; ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-3 offset-sm-2">
        <table class="table table-borderless">
          <tbody>    
            <tr class="border-bottom">
              <td>Subtotal</td>
              <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $recurring_currency_code); ?></td>
            </tr>
            <?php if($total_tax > 0){ ?>
            <tr class="border-bottom">
              <td>Tax</td>
              <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $recurring_currency_code); ?></td>        
            </tr>
            <?php } ?>
            <tr class="border-bottom">
              <td><strong>Amount</strong></td>
              <td class="text-right"><strong><?php echo numfmt_format_currency($currency_format, $recurring_amount, $recurring_currency_code); ?></strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
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
              <th>Event</th>
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

<?php 
  
  include("recurring_invoice_edit_modal.php");
  include("recurring_invoice_edit_next_date_modal.php");
  include("recurring_invoice_note_modal.php");
  include("category_quick_add_modal.php");

}

include("footer.php");

?>

<!-- JSON Autocomplete / type ahead -->
<link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
<script src="plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
  $(function(){
    var availableProducts = <?php echo $json_products?>;

    $("#name").autocomplete({
      source: availableProducts,
      select: function (event, ui){
        $("#name").val(ui.item.label); // Product name field - this seemingly has to referenced as label
        $("#desc").val(ui.item.description); // Product description field
        $("#qty").val(1); // Product quantity field automatically make it a 1
        $("#price").val(ui.item.price); // Product price field
        return false;
      }
    });
  });
</script>