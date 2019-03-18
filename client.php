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
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editinvoiceModal<?php echo $invoice_id; ?>">Edit</a>
        <a class="dropdown-item" href="#">Delete</a>
      </div>
    </div>
  </div>
</div>    
<div class="row">
  <div class="col-sm-12">
    <div class="card">
      <div class="card-header">
        <ul class="nav nav-pills">
          <li class="nav-item">
            <a class="nav-link active" href="?client_id=<?php echo $client_id; ?>&tab=details">Details</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="?client_id=<?php echo $client_id; ?>&tab=contacts">Contacts</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Locations</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Assets</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Vendors</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Passwords</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Invoices</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Payments</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Quotes</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Attachments</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Notes</a>
          </li>
        </ul>
      </div>

      <div class="card-body">
        <?php
        if(isset($_GET['tab'])){
          if($_GET['tab'] == "details") {
            include("client_details.php");
          }
          elseif($_GET['tab'] == "contacts") {
            include("client_contacts.php");
          }
        }
        else{
          include("client_details.php");
        }
     
        ?>
      </div>
    </div>
  </div>
</div>

<?php } ?>

<?php include("footer.php");