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
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceModal">New Invoice</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteModal">New Quote</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientAttachmentModal">New Attachment</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addClientNoteModal">New Note</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $invoice_id; ?>">Edit</a>
        <a class="dropdown-item" href="#">Delete</a>
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
<?php include("add_invoice_modal.php"); ?>
<?php include("add_invoice_payment_modal.php"); ?>
<?php include("add_quote_modal.php"); ?>
<?php include("add_client_attachment_modal.php"); ?>
<?php include("add_client_note_modal.php"); ?>

<?php } ?>

<?php include("footer.php");