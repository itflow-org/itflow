<?php include("header.php"); ?>

<?php 

if(isset($_GET['recurring_id'])){

  $recurring_id = intval($_GET['recurring_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM clients, recurring
    WHERE recurring.client_id = clients.client_id
    AND recurring.recurring_id = $recurring_id"
  );

  $row = mysqli_fetch_array($sql);
  $recurring_id = $row['recurring_id'];
  $recurring_frequency = $row['recurring_frequency'];
  $recurring_status = $row['recurring_status'];
  $recurring_start_date = $row['recurring_start_date'];
  $recurring_last_sent = $row['recurring_last_sent'];
  if($recurring_last_sent == 0){
    $recurring_last_sent = '-';
  }
  $recurring_next_date = $row['recurring_next_date'];
  $recurring_amount = $row['recurring_amount'];
  $recurring_note = $row['recurring_note'];
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
  
  if($recurring_status == 1){
    $status = "Active";
    $status_badge_color = "success";
  }else{
    $status = "Inactive";
    $status_badge_color = "secondary";
  }

  $sql_history = mysqli_query($mysqli,"SELECT * FROM history WHERE recurring_id = $recurring_id ORDER BY history_id DESC");

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="recurring.php"> Recurring Invoices</a>
  </li>
  <li class="breadcrumb-item active"><?php echo $recurring_id; ?></li>
  <span class="ml-3 p-2 badge badge-<?php echo $status_badge_color; ?>"><?php echo $status; ?></span>
</ol>

<div class="row mb-4 d-print-none">
  <div class="col-md-12">
    <div class="dropdown dropleft text-center">
      <button class="btn btn-primary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-ellipsis-h"></i>
      </button>
      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRecurringModal<?php echo $recurring_id; ?>">Edit</a>
        <?php if($recurring_status == 1){ ?>
          <a class="dropdown-item" href="post.php?recurring_deactivate=<?php echo $recurring_id; ?>">Deactivate</a>
        <?php }else{ ?>
          <a class="dropdown-item" href="post.php?recurring_activate=<?php echo $recurring_id; ?>">Activate</a>
        <?php } ?>
        <a class="dropdown-item" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">Delete</a>
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
          <li class="mb-1"><strong>Frequency:</strong> <div class="float-right"><?php echo ucwords($recurring_frequency); ?>ly</div></li>
          <li class="mb-1"><strong>Start Date:</strong> <div class="float-right"><?php echo $recurring_start_date; ?></div></li>
          <li class="mb-1"><strong>Next Date:</strong> <div class="float-right"><?php echo $recurring_next_date; ?></div></li>
          <li class="mb-1"><strong>Last Sent:</strong> <div class="float-right"><?php echo $recurring_last_sent; ?></div></li>
          <li class="mb-1"><strong>Net Terms:</strong> <div class="float-right"><?php echo $client_net_terms; ?> Day</div></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE recurring_id = $recurring_id ORDER BY item_id ASC"); ?>

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
            <th>Item</th>
            <th>Description</th>
            <th class="text-right">Unit Cost</th>
            <th class="text-center">Quantity</th>
            <th class="text-right">Tax</th>
            <th class="text-right">Line Total</th>
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
            <td class="text-center d-print-none"><a class="btn btn-danger btn-sm" href="post.php?delete_recurring_item=<?php echo $item_id; ?>"><i class="fa fa-trash"></i></a></td>
            <td><?php echo $item_name; ?></td>
            <td><?php echo $item_description; ?></td>
            <td class="text-right">$<?php echo number_format($item_price,2); ?></td>
            <td class="text-center"><?php echo $item_quantity; ?></td>
            <td class="text-right">$<?php echo number_format($item_tax,2); ?></td>
            <td class="text-right">$<?php echo number_format($item_total,2); ?></td>  
          </tr>

          <?php 

          }

          ?>

          <tr class="d-print-none">
            <form action="post.php" method="post">
              <td class="text-center"><button type="submit" class="btn btn-primary btn-sm" name="add_recurring_item"><i class="fa fa-check"></i></button></td>
              <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">
              <td><input type="text" class="form-control" name="name"></td>
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
      </div>
      <div class="card-body">
        <div class="d-none d-print-block"><?php echo $recurring_note; ?></div>
        <form class="d-print-none" action="post.php" method="post">
          <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">
          <textarea rows="6" class="form-control mb-2" name="recurring_note"><?php echo $recurring_note; ?></textarea>
          <button class="btn btn-primary btn-sm float-right" type="submit" name="edit_recurring_note"><i class="fa fa-fw fa-check"></i></button>
        </form>
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
          <td class="text-right">$<?php echo number_format($invoice_discount,2); ?></td>          
        </tr>
        <?php } ?>
        <?php if($total_tax > 0){ ?>
        <tr class="border-bottom">
          <td>Tax</td>
          <td class="text-right">$<?php echo number_format($total_tax,2); ?></td>        
        </tr>
        <?php } ?>
        <tr class="border-bottom">
          <td><strong>Amount</strong></td>
          <td class="text-right"><strong>$<?php echo number_format($recurring_amount,2); ?></strong></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

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
              <th>Event</th>
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

<?php include("edit_recurring_modal.php"); ?>

<?php } ?>

<?php include("footer.php");