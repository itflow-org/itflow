<?php include("config.php"); ?>
<?php include("check_login.php"); ?>

<?php 

if(isset($_GET['client_id'])){
  $client_id = intval($_GET['client_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id AND company_id = $session_company_id");

  if(mysqli_num_rows($sql) == 0){
    include("header.php");
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1></center>";
  }else{

  $row = mysqli_fetch_array($sql);
  $client_name = $row['client_name'];
  $client_type = $row['client_type'];
  $client_country = $row['client_country'];
  $client_address = $row['client_address'];
  $client_city = $row['client_city'];
  $client_state = $row['client_state'];
  $client_zip = $row['client_zip'];
  $client_contact = $row['client_contact'];
  $client_email = $row['client_email'];
  $client_phone = $row['client_phone'];
  if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
  }
  $client_mobile = $row['client_mobile'];
  if(strlen($client_mobile)>2){ 
    $client_mobile = substr($row['client_mobile'],0,3)."-".substr($row['client_mobile'],3,3)."-".substr($row['client_mobile'],6,4);
  }
  $client_website = $row['client_website'];
  $client_net_terms = $row['client_net_terms'];
  if($client_net_terms == 0){
    $client_net_terms = $config_default_net_terms;
  }
  $client_company_size = $row['client_company_size'];
  $client_notes = $row['client_notes'];

  //Add up all the payments for the invoice and get the total amount paid to the invoice
  $sql_invoice_amounts = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled'");
  $row = mysqli_fetch_array($sql_invoice_amounts);

  $invoice_amounts = $row['invoice_amounts'];

  $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id");
  $row = mysqli_fetch_array($sql_amount_paid);
  
  $amount_paid = $row['amount_paid'];

  $balance = $invoice_amounts - $amount_paid;

  //Badge Counts

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts WHERE client_id = $client_id"));
  $num_contacts = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations WHERE client_id = $client_id"));
  $num_locations = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets WHERE client_id = $client_id"));
  $num_assets = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('ticket_id') AS num FROM tickets WHERE client_id = $client_id"));
  $num_tickets = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE client_id = $client_id"));
  $num_vendors = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins WHERE client_id = $client_id"));
  $num_logins = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks WHERE client_id = $client_id"));
  $num_networks = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains WHERE client_id = $client_id"));
  $num_domains = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE client_id = $client_id"));
  $num_software = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE client_id = $client_id"));
  $num_invoices = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes WHERE client_id = $client_id"));
  $num_quotes = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM recurring WHERE client_id = $client_id"));
  $num_recurring = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id"));
  $num_payments = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('file_id') AS num FROM files WHERE client_id = $client_id"));
  $num_files = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('note_id') AS num FROM notes WHERE client_id = $client_id"));
  $num_notes = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('event_id') AS num FROM events WHERE client_id = $client_id"));
  $num_events = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('trip_id') AS num FROM trips WHERE client_id = $client_id"));
  $num_trips = $row['num'];

?>

<?php include("header.php"); ?>

<div class="card mb-3">
  <div class="card-body">
    <div class="row">
      <div class="col-md-3">
        <h4><strong><?php echo $client_name; ?></strong></h4>
        <a href="//maps.<?php echo $session_map_source; ?>.com/?q=<?php echo "$client_address $client_zip"; ?>" target="_blank">
          <div class="ml-1"><?php echo $client_address; ?></div>
          <div class="ml-1"><?php echo "$client_city $client_state $client_zip"; ?></div>
        </a>
      </div>
      <div class="col-md-3 border-left">
        <h4 class="text-secondary">Contact</h4>
        <?php
        if(!empty($client_contact)){
        ?>
        <i class="fa fa-fw fa-user text-secondary ml-1 mr-2 mb-2"></i> <?php echo $client_contact; ?>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($client_email)){
        ?>
        <i class="fa fa-fw fa-envelope text-secondary ml-1 mr-2 mb-2"></i> <a href="mailto:<?php echo $client_email; ?>"><?php echo $client_email; ?></a>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($client_phone)){
        ?>
        <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i> <?php echo $client_phone; ?>
        <br>
        <?php 
        } 
        ?>
        <?php
        if(!empty($client_mobile)){
        ?>
        <i class="fa fa-fw fa-mobile-alt text-secondary ml-1 mr-2 mb-2"></i> <?php echo $client_mobile; ?>
        <br>
        <?php 
        } 
        ?>
        <?php
        if(!empty($client_website)){
        ?>
        <i class="fa fa-fw fa-globe text-secondary ml-1 mr-2 mb-2"></i> <a target="_blank" href="//<?php echo $client_website; ?>"><?php echo $client_website; ?></a>
        <?php 
        }
        ?>
      </div>
      <div class="col-md-3 border-left">
        <h4 class="text-secondary">Billing</h4>
        <h6 class="ml-1 text-secondary">Paid <div class="text-dark float-right">$<?php echo number_format($amount_paid,2); ?></div></h6>
        <h6 class="ml-1 text-secondary">Balance <div class="text-dark float-right">$<?php echo number_format($balance,2); ?></div></h6>
        <h6 class="ml-1 text-secondary">Net Terms <div class="text-dark float-right"><?php echo $client_net_terms; ?> <small class="text-secondary">Days</small></div></h6>
      </div>
      <div class="col-md-2 border-left">
        <h4 class="text-secondary">Services</h4>
      </div>
      <div class="col-md-1 border-left">
        <div class="dropdown dropleft text-center">
          <button class="btn btn-dark btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-fw fa-ellipsis-v"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <a class="dropdown-item" href="client_print.php?client_id=<?php echo $client_id; ?>">Print</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">Edit</a>
            <a class="dropdown-item" href="post.php?delete_client=<?php echo $client_id; ?>">Delete</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include("client_routes.php"); ?>

<?php include("edit_client_modal.php"); ?>

<?php 

  }

}

?>

<?php include("footer.php");