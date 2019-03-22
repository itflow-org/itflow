<?php include("header.php"); ?>

<?php 

if(isset($_GET['client_id'])){
  $client_id = intval($_GET['client_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");

  $row = mysqli_fetch_array($sql);
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

  //Badge Counts

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_contact_id') AS num FROM client_contacts WHERE client_id = $client_id"));
  $num_contacts = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_location_id') AS num FROM client_locations WHERE client_id = $client_id"));
  $num_locations = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_asset_id') AS num FROM client_assets WHERE client_id = $client_id"));
  $num_assets = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_vendor_id') AS num FROM client_vendors WHERE client_id = $client_id"));
  $num_vendors = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_login_id') AS num FROM client_logins WHERE client_id = $client_id"));
  $num_logins = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_network_id') AS num FROM client_networks WHERE client_id = $client_id"));
  $num_networks = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_domain_id') AS num FROM client_domains WHERE client_id = $client_id"));
  $num_domains = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE client_id = $client_id"));
  $num_invoices = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_note_id') AS num FROM client_notes WHERE client_id = $client_id"));
  $num_notes = $row['num'];

?>
<div class="row">
  <div class="col-8">
    <h2><?php echo $client_name; ?></h2>
  </div>
  <div class="col-4">
    <div class="dropdown dropleft text-center">
      <button class="btn btn-primary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-ellipsis-h"></i>
      </button>
      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientContactModal">New Contact</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientLocationModal">New Location</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientAssetModal">New Asset</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientVendorModal">New Vendor</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientLoginModal">New Login</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientNetworkModal">New Network</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientDomainModal">New Domain</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceModal">New Invoice</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteModal">New Quote</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientAttachmentModal">New Attachment</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientNoteModal">New Note</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">Edit</a>
        <a class="dropdown-item" href="post.php?delete_client=<?php echo $client_id; ?>">Delete</a>
      </div>
    </div>
  </div>
</div>    
<div class="row">
  <div class="col-sm-12">
    <div class="card">
      <div class="card-header">
        <?php include("client_nav.php"); ?>
      </div>

      <div class="card-body">
        <?php include("client_routes.php"); ?>
      </div>
    </div>
  </div>
</div>

<?php include("edit_client_modal.php"); ?>
<?php include("add_client_contact_modal.php"); ?>
<?php include("add_client_location_modal.php"); ?>
<?php include("add_client_asset_modal.php"); ?>
<?php include("add_client_vendor_modal.php"); ?>
<?php include("add_client_login_modal.php"); ?>
<?php include("add_client_network_modal.php"); ?>
<?php include("add_client_domain_modal.php"); ?>
<?php include("add_invoice_modal.php"); ?>
<?php include("add_invoice_payment_modal.php"); ?>
<?php include("add_quote_modal.php"); ?>
<?php include("add_client_attachment_modal.php"); ?>
<?php include("add_client_note_modal.php"); ?>

<?php } ?>

<?php include("footer.php");