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

  //Query each table and store them in their array
  $sql_contacts = mysqli_query($mysqli,"SELECT * FROM client_contacts WHERE client_id = $client_id ORDER BY client_contact_id DESC");
  $sql_locations = mysqli_query($mysqli,"SELECT * FROM client_locations WHERE client_id = $client_id ORDER BY client_location_id DESC");
  $sql_assets = mysqli_query($mysqli,"SELECT * FROM client_assets WHERE client_id = $client_id ORDER BY client_asset_id DESC");
  $sql_vendors = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id ORDER BY client_vendor_id DESC");
  $sql_logins = mysqli_query($mysqli,"SELECT * FROM client_logins WHERE client_id = $client_id ORDER BY client_login_id DESC");
  $sql_networks = mysqli_query($mysqli,"SELECT * FROM client_networks WHERE client_id = $client_id ORDER BY client_network_id DESC");
  $sql_domains = mysqli_query($mysqli,"SELECT * FROM client_domains WHERE client_id = $client_id ORDER BY client_domain_id DESC");
  $sql_applications = mysqli_query($mysqli,"SELECT * FROM client_applications WHERE client_id = $client_id ORDER BY client_application_id DESC");
  $sql_invoices = mysqli_query($mysqli,"SELECT * FROM invoices WHERE client_id = $client_id ORDER BY invoices.invoice_date DESC");
  $sql_notes = mysqli_query($mysqli,"SELECT * FROM client_notes WHERE client_id = $client_id ORDER BY client_note_id DESC");

  //Get Counts
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
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_application_id') AS num FROM client_applications WHERE client_id = $client_id"));
  $num_applications = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE client_id = $client_id"));
  $num_invoices = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_note_id') AS num FROM client_notes WHERE client_id = $client_id"));
  $num_notes = $row['num'];

?>

<!-- Breadcrumbs-->
<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="clients.php">Clients</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item active">Print</li>
</ol>

<button class="btn btn-primary btn-sm d-print-none mb-2" onclick="window.print();"><i class="fa fa-print"></i> Print</button>

<div class="row">
  <div class="col-9">
    <h2><?php echo $client_name; ?></h2>
    <table class="table">
      <tr>
        <th>Address</th>
        <td><?php echo $client_address; ?></td>
      </tr>
      <tr>
        <th>City State Zip</th>
        <td><?php echo "$client_city $client_state $client_zip"; ?></td>
      </tr>
      <tr>
        <th>Phone</th>
        <td><?php echo $client_phone; ?></td>
      </tr>
      <tr>      
        <th>Email</th>
        <td><?php echo $client_email; ?></td>
      </tr>
      <tr> 
        <th>Website</th>
        <td><?php echo $client_website; ?></td>
      </tr>
    </table>
  </div>
  <div class="col-3">
    <div class="card">
      <div class="card-header">
        <i class="fa fa-th"></i> Table of Contents</h6>
      </div>
      <div class="card-body">
        <ul class="list-unstyled">
          <?php if($num_contacts > 0){ ?> <li>Contacts</li> <?php } ?>
          <?php if($num_locations > 0){ ?> <li>Locations</li> <?php } ?>
          <?php if($num_assets > 0){ ?> <li>Assets</li> <?php } ?>
          <?php if($num_vendors > 0){ ?> <li>Vendors</li> <?php } ?>
          <?php if($num_logins > 0){ ?> <li>Logins</li> <?php } ?>
          <?php if($num_networks > 0){ ?> <li>Networks</li> <?php } ?> 
          <?php if($num_domains > 0){ ?> <li>Domains</li> <?php } ?>
          <?php if($num_applications > 0){ ?> <li>Applications</li> <?php } ?>
          <?php if($num_invoices > 0){ ?> <li>Invoices</li> <?php } ?>
          <?php if($num_quotes > 0){ ?> <li>Quotes</li> <?php } ?>
          <?php if($num_attachments > 0){ ?> <li>Attachments</li> <?php } ?>
          <?php if($num_notes > 0){ ?> <li>Notes</li> <?php } ?>
        </ul>
      </div>
    </div>
  </div>
</div>


<?php if($num_contacts > 0){ ?>
<h4>Contacts</h4>
<table class="table table-bordered table-compact table-sm mb-4">
  <thead>
    <tr>
      <th>Name</th>
      <th>Title</th>
      <th>Phone</th>
      <th>Email</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_contacts)){
      $client_contact_id = $row['client_contact_id'];
      $client_contact_name = $row['client_contact_name'];
      $client_contact_title = $row['client_contact_title'];
      $client_contact_phone = $row['client_contact_phone'];
      if(strlen($client_contact_phone)>2){ 
        $client_contact_phone = substr($row['client_contact_phone'],0,3)."-".substr($row['client_contact_phone'],3,3)."-".substr($row['client_contact_phone'],6,4);
      }
      $client_contact_email = $row['client_contact_email'];

    ?>
    <tr>
      <td><?php echo "$client_contact_name"; ?></td>
      <td><?php echo "$client_contact_title"; ?></td>
      <td><?php echo "$client_contact_phone"; ?></td>
      <td><?php echo "$client_contact_email"; ?></td>
    </tr>

    <?php
    }
    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_locations > 0){ ?>
<h4>Locations</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Location</th>
      <th>Address</th>
      <th>Phone</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_locations)){
      $client_location_id = $row['client_location_id'];
      $client_location_name = $row['client_location_name'];
      $client_location_address = $row['client_location_address'];
      $client_location_city = $row['client_location_city'];
      $client_location_state = $row['client_location_state'];
      $client_location_zip = $row['client_location_zip'];
      $client_location_phone = $row['client_location_phone'];
      if(strlen($client_location_phone)>2){ 
        $client_location_phone = substr($row['client_location_phone'],0,3)."-".substr($row['client_location_phone'],3,3)."-".substr($row['client_location_phone'],6,4);
      }

    ?>
    <tr>
      <td><?php echo "$client_location_name"; ?></td>
      <td><?php echo "$client_location_address $client_location_city $client_location_state $client_location_zip"; ?></td>
      <td><?php echo "$client_location_phone"; ?></td>
    </tr>

    <?php
    
    }
    
    ?>

  </tbody>
</table>
<?php } ?>
 

<?php if($num_assets > 0){ ?>
<h4>Assets</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Type</th>
      <th>Name</th>
      <th>Make</th>
      <th>Serial</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_assets)){
      $client_asset_id = $row['client_asset_id'];
      $client_asset_type = $row['client_asset_type'];
      $client_asset_name = $row['client_asset_name'];
      $client_asset_make = $row['client_asset_make'];
      $client_asset_model = $row['client_asset_model'];
      $client_asset_serial = $row['client_asset_serial'];

    ?>
    <tr>
      <td><?php echo $client_asset_type; ?></td>
      <td><?php echo $client_asset_name; ?></td>
      <td><?php echo "$client_asset_make $client_asset_model"; ?></td>
      <td><?php echo $client_asset_serial; ?></td>
    </tr>

    <?php
    }
    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_vendors > 0){ ?>
<h4>Vendors</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Vendor</th>
      <th>Description</th>
      <th>Account Number</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_vendors)){
      $client_vendor_id = $row['client_vendor_id'];
      $client_vendor_name = $row['client_vendor_name'];
      $client_vendor_description = $row['client_vendor_description'];
      $client_vendor_account_number = $row['client_vendor_account_number'];

    ?>
    <tr>
      <td><?php echo $client_vendor_name; ?></td>
      <td><?php echo $client_vendor_description; ?></td>
      <td><?php echo $client_vendor_account_number; ?></td>
    </tr>

    <?php
    }
    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_logins > 0){ ?>
<h4>Logins</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Description</th>
      <th>Username</th>
      <th>Password</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_logins)){
      $client_login_id = $row['client_login_id'];
      $client_login_description = $row['client_login_description'];
      $client_login_username = $row['client_login_username'];
      $client_login_password = $row['client_login_password'];

    ?>
    <tr>
      <td><?php echo $client_login_description; ?></td>
      <td><?php echo $client_login_username; ?></td>
      <td><?php echo $client_login_password; ?></td>
    </tr>

    <?php
    }
    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_networks > 0){ ?>
<h4>Networks</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Name</th>
      <th>Network</th>
      <th>Gateway</th>
      <th>DHCP Range</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_networks)){
      $client_network_id = $row['client_network_id'];
      $client_network_name = $row['client_network_name'];
      $client_network = $row['client_network'];
      $client_network_gateway = $row['client_network_gateway'];
      $client_network_dhcp_range = $row['client_network_dhcp_range'];


    ?>
    <tr>
      <td><?php echo $client_network_name; ?></td>
      <td><?php echo $client_network; ?></td>
      <td><?php echo $client_network_gateway; ?></td>
      <td><?php echo $client_network_dhcp_range; ?></td>
    </tr>

    <?php
    }
    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_domains > 0){ ?>
<h4>Domains</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Domain</th>
      <th>Registrar</th>
      <th>Expire</th>
      <th>Server</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_domains)){
      $client_domain_id = $row['client_domain_id'];
      $client_domain_name = $row['client_domain_name'];
      $client_domain_registrar = $row['client_domain_registrar'];
      $client_domain_expire = $row['client_domain_expire'];
      $client_domain_server = $row['client_domain_server'];

    ?>
    <tr>
      <td><?php echo $client_domain_name; ?></td>
      <td><?php echo $client_domain_registrar; ?></td>
      <td><?php echo $client_domain_expire; ?></td>
      <td><?php echo $client_domain_server; ?></td>
    </tr>

    <?php
    }
    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_applications > 0){ ?>
<h4>Applications</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Application</th>
      <th>Type</th>
      <th>License</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_applications)){
      $client_application_id = $row['client_application_id'];
      $client_application_name = $row['client_application_name'];
      $client_application_type = $row['client_application_type'];
      $client_application_license = $row['client_application_license'];

    ?>
    <tr>
      <td><?php echo $client_application_name; ?></td>
      <td><?php echo $client_application_type; ?></td>
      <td><?php echo $client_application_license; ?></td>
    </tr>

    <?php
    }
    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_invoices > 0){ ?>
<h4>Invoices</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Number</th>
      <th class="text-right">Amount</th>
      <th>Date</th>
      <th>Due</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_invoices)){
      $invoice_id = $row['invoice_id'];
      $invoice_number = $row['invoice_number'];
      $invoice_status = $row['invoice_status'];
      $invoice_date = $row['invoice_date'];
      $invoice_due = $row['invoice_due'];
      $invoice_balance = $row['invoice_balance'];

    ?>

    <tr>
      <td>INV-<?php echo "$invoice_number"; ?></td>
      <td class="text-right text-monospace">$<?php echo number_format($invoice_balance,2); ?></td>
      <td><?php echo "$invoice_date"; ?></td>
      <td><?php echo "$invoice_due"; ?></td>
      <td><?php echo "$invoice_status"; ?></td>
    </tr>

    <?php

    }

    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_notes > 0){ ?>
<h4>Notes</h4>
<hr>

<?php

while($row = mysqli_fetch_array($sql_notes)){
  $client_note_id = $row['client_note_id'];
  $client_note_subject = $row['client_note_subject'];
  $client_note_body = $row['client_note_body'];

?>
<h6><?php echo "$client_note_subject"; ?></h6>
<hr>
<p class="mb-4"><?php echo "$client_note_body"; ?></p>

<?php } ?>

<?php } ?>


<?php } ?>

<?php include("footer.php");