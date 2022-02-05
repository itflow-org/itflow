<?php include("config.php"); ?>
<?php include("functions.php"); ?>
<?php include("check_login.php"); ?>

<?php 

if(isset($_GET['client_id'])){
  $client_id = intval($_GET['client_id']);

  $sql = mysqli_query($mysqli,"UPDATE clients SET client_accessed_at = NOW() WHERE client_id = $client_id AND company_id = $session_company_id");

  $sql = mysqli_query($mysqli,"SELECT * FROM clients
    LEFT JOIN locations ON primary_location = location_id AND location_archived_at IS NULL
    LEFT JOIN contacts ON primary_contact = contact_id AND contact_archived_at IS NULL
    WHERE client_id = $client_id 
    AND clients.company_id = $session_company_id");

  if(mysqli_num_rows($sql) == 0){
    include("header.php");
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1></center>";
  }else{

  $row = mysqli_fetch_array($sql);
  $client_name = $row['client_name'];
  $client_type = $row['client_type'];
  $client_website = $row['client_website'];
  $client_referral = $row['client_referral'];
  $client_currency_code = $row['client_currency_code'];
  $client_net_terms = $row['client_net_terms'];
  if($client_net_terms == 0){
    $client_net_terms = $config_default_net_terms;
  }
  $client_notes = $row['client_notes'];
  $client_created_at = $row['client_created_at'];
  $primary_contact = $row['primary_contact'];
  $primary_location = $row['primary_location'];
  $contact_id = $row['contact_id'];
  $contact_name = $row['contact_name'];
  $contact_title = $row['contact_title'];
  $contact_email = $row['contact_email'];
  $contact_phone = $row['contact_phone'];
  $contact_extension = $row['contact_extension'];
  $contact_mobile = $row['contact_mobile'];
  $location_id = $row['location_id'];
  $location_name = $row['location_name'];
  $location_address = $row['location_address'];
  $location_city = $row['location_city'];
  $location_state = $row['location_state'];
  $location_zip = $row['location_zip'];
  $location_country = $row['location_country'];
  $location_phone = $row['location_phone'];

  //Client Tags

  $client_tag_name_display_array = array();
  $client_tag_id_array = array();
  $sql_client_tags = mysqli_query($mysqli,"SELECT * FROM client_tags LEFT JOIN tags ON client_tags.tag_id = tags.tag_id WHERE client_tags.client_id = $client_id");
  while($row = mysqli_fetch_array($sql_client_tags)){

    $client_tag_id = $row['tag_id'];
    $client_tag_name = $row['tag_name'];
    $client_tag_color = $row['tag_color'];
    $client_tag_icon = $row['tag_icon'];
    if(empty($client_tag_icon)){
      $client_tag_icon = "tag";
    }
  
    $client_tag_id_array[] = $client_tag_id;
    $client_tag_name_display_array[] = "<div class='badge bg-$client_tag_color'><i class='fa fa-fw fa-$client_tag_icon'></i> $client_tag_name</div> ";
  }
  $client_tags_display = implode('', $client_tag_name_display_array);

  //Add up all the payments for the invoice and get the total amount paid to the invoice
  $sql_invoice_amounts = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled'");
  $row = mysqli_fetch_array($sql_invoice_amounts);

  $invoice_amounts = $row['invoice_amounts'];

  $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id");
  $row = mysqli_fetch_array($sql_amount_paid);
  
  $amount_paid = $row['amount_paid'];

  $balance = $invoice_amounts - $amount_paid;

  //Badge Counts

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id"));
  $num_contacts = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id"));
  $num_locations = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id"));
  $num_assets = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_client_id = $client_id"));
  $num_tickets = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_archived_at IS NULL AND ticket_status = 'Open' AND ticket_client_id = $client_id"));
  $num_open_tickets = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('service_id') AS num FROM services WHERE service_client_id = $client_id"));
  $num_services = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id"));
  $num_vendors = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins WHERE login_archived_at IS NULL AND login_client_id = $client_id"));
  $num_logins = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id"));
  $num_networks = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains WHERE domain_archived_at IS NULL AND domain_client_id = $client_id"));
  $num_domains = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('certificate_id') AS num FROM certificates WHERE certificate_archived_at IS NULL AND certificate_client_id = $client_id"));
  $num_certificates = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_archived_at IS NULL AND software_client_id = $client_id"));
  $num_software = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_archived_at IS NULL AND invoice_client_id = $client_id"));
  $num_invoices = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes WHERE quote_archived_at IS NULL AND quote_client_id = $client_id"));
  $num_quotes = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM recurring WHERE recurring_archived_at IS NULL AND recurring_client_id = $client_id"));
  $num_recurring = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id"));
  $num_payments = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('file_id') AS num FROM files WHERE file_archived_at IS NULL AND file_client_id = $client_id"));
  $num_files = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_archived_at IS NULL AND document_client_id = $client_id"));
  $num_documents = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('event_id') AS num FROM events WHERE event_client_id = $client_id"));
  $num_events = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('trip_id') AS num FROM trips WHERE trip_archived_at IS NULL AND trip_client_id = $client_id"));
  $num_trips = $row['num'];

?>

<?php include("header.php"); ?>

<?php

$contact_phone = formatPhoneNumber($contact_phone);
$contact_mobile = formatPhoneNumber($contact_mobile);
$location_phone = formatPhoneNumber($location_phone);

?>

<div class="card mb-3">
  <div class="card-body">
    <div class="row">
      <div class="col-md-3">
        <h4 class="text-secondary"><strong><?php echo $client_name; ?></strong></h4>
        <?php if(!empty($location_address)){ ?>
        <a href="//maps.<?php echo $session_map_source; ?>.com/?q=<?php echo "$location_address $location_zip"; ?>" target="_blank">
          <div><i class="fa fa-fw fa-map-marker-alt text-secondary ml-1 mr-1"></i> <?php echo $location_address; ?></div>
          <div class="ml-4 mb-2"><?php echo "$location_city $location_state $location_zip"; ?></div>
        </a>
        <?php } ?>
        <?php
        if(!empty($location_phone)){
        ?>
        <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i> <?php echo $location_phone; ?>
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
        <h4 class="text-secondary">Contact</h4>
        <?php
        if(!empty($contact_name)){
        ?>
        <i class="fa fa-fw fa-user text-secondary ml-1 mr-2 mb-2"></i> <?php echo $contact_name; ?>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($contact_email)){
        ?>
        <i class="fa fa-fw fa-envelope text-secondary ml-1 mr-2 mb-2"></i> <a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($contact_phone)){
        ?>
        <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i> <?php echo $contact_phone; ?> 
        <?php 
        if(!empty($contact_extension)){ 
        ?>
        x<?php echo $contact_extension; ?>
        <?php
        }
        ?>
        <br>
        <?php 
        } 
        ?>
        <?php
        if(!empty($contact_mobile)){
        ?>
        <i class="fa fa-fw fa-mobile-alt text-secondary ml-1 mr-2 mb-2"></i> <?php echo $contact_mobile; ?>
        <?php 
        } 
        ?>
      </div>
      <?php if($session_user_role == 1 OR $session_user_role > 3){ ?>
      <div class="col-md-3 border-left">
        <h4 class="text-secondary">Billing</h4>
        <h6 class="ml-1 text-secondary">Paid <div class="text-dark float-right"> <?php echo get_currency_symbol($session_company_currency); ?> <?php echo number_format($amount_paid,2); ?></div></h6>
        <h6 class="ml-1 text-secondary">Balance <div class="text-dark float-right"> <?php echo get_currency_symbol($session_company_currency); ?> <?php echo number_format($balance,2); ?></div></h6>
        <h6 class="ml-1 text-secondary">Net Terms <div class="text-dark float-right"><?php echo $client_net_terms; ?> <small class="text-secondary">Days</small></div></h6>
      </div>
      <?php } ?>
      <div class="col-md-2 border-left">
        <h4 class="text-secondary">Support</h4>
        <h6 class="ml-1 text-secondary">Open Tickets <div class="text-dark float-right"><?php echo $num_open_tickets; ?></div></h6>
      </div>
      <div class="col-md-1 border-left">
        <div class="dropdown dropleft text-center">
          <button class="btn btn-dark btn-sm float-right" type="button" data-toggle="dropdown">
            <i class="fas fa-fw fa-ellipsis-v"></i>
          </button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="client_print.php?client_id=<?php echo $client_id; ?>">Print</a>
            <a class="dropdown-item" href="post.php?export_client_pdf=<?php echo $client_id; ?>">Export PDF<br><small class="text-secondary">(without passwords)</small></a>
            <a class="dropdown-item" href="post.php?export_client_pdf=<?php echo $client_id; ?>&passwords">Export PDF<br><small class="text-secondary">(with passwords)</small></a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#clientEditModal<?php echo $client_id; ?>">Edit</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#clientDeleteModal<?php echo $client_id; ?>">Delete</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php 
  
  include("client_routes.php");
  include("client_edit_modal.php");
  include("client_delete_modal.php");
  include("category_quick_add_modal.php");

  }

}

include("footer.php");

?>