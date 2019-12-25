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
  $client_net_terms = $row['client_net_terms'];

  //Query each table and store them in their array
  $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE client_id = $client_id ORDER BY contact_id DESC");
  $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE client_id = $client_id ORDER BY location_id DESC");
  $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets WHERE client_id = $client_id ORDER BY asset_id DESC");
  $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id ORDER BY vendor_id DESC");
  $sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE client_id = $client_id ORDER BY login_id DESC");
  $sql_networks = mysqli_query($mysqli,"SELECT * FROM networks WHERE client_id = $client_id ORDER BY network_id DESC");
  $sql_domains = mysqli_query($mysqli,"SELECT * FROM domains WHERE client_id = $client_id ORDER BY domain_id DESC");
  $sql_applications = mysqli_query($mysqli,"SELECT * FROM applications WHERE client_id = $client_id ORDER BY application_id DESC");
  $sql_invoices = mysqli_query($mysqli,"SELECT * FROM invoices WHERE client_id = $client_id ORDER BY invoice_id DESC");

  $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, invoices, accounts
    WHERE invoices.client_id = $client_id
    AND payments.invoice_id = invoices.invoice_id
    AND payments.account_id = accounts.account_id
    ORDER BY payments.payment_id DESC"); 
  
  $sql_quotes = mysqli_query($mysqli,"SELECT * FROM quotes WHERE client_id = $client_id ORDER BY quote_id DESC");

  $sql_recurring = mysqli_query($mysqli,"SELECT * FROM recurring_invoices, invoices
    WHERE invoices.invoice_id = recurring_invoices.invoice_id
    AND invoices.client_id = $client_id
    ORDER BY recurring_invoices.recurring_invoice_id DESC");

  $sql_notes = mysqli_query($mysqli,"SELECT * FROM notes WHERE client_id = $client_id ORDER BY note_id DESC");

  //Get Counts
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts WHERE client_id = $client_id"));
  $num_contacts = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations WHERE client_id = $client_id"));
  $num_locations = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets WHERE client_id = $client_id"));
  $num_assets = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE client_id = $client_id"));
  $num_vendors = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins WHERE client_id = $client_id"));
  $num_logins = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks WHERE client_id = $client_id"));
  $num_networks = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains WHERE client_id = $client_id"));
  $num_domains = $row['num'];
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('application_id') AS num FROM applications WHERE client_id = $client_id"));
  $num_applications = $row['num'];
  
  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices WHERE client_id = $client_id"));
  $num_invoices = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id"));
  $num_payments = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes WHERE client_id = $client_id"));
  $num_quotes = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_invoice_id') AS num FROM recurring_invoices, invoices WHERE recurring_invoices.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id"));
  $num_recurring = $row['num'];

  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('note_id') AS num FROM notes WHERE client_id = $client_id"));
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
      <tr>
        <th>Net Terms</th>
        <td><?php echo $client_net_terms; ?> Day</td>
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
          <?php if($num_payments > 0){ ?> <li>Payments</li> <?php } ?>
          <?php if($num_quotes > 0){ ?> <li>Quotes</li> <?php } ?>
          <?php if($num_recurring > 0){ ?> <li>Recurring</li> <?php } ?>
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
      <th>Mobile</th>
      <th>Email</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_contacts)){
      $contact_id = $row['contact_id'];
      $contact_name = $row['contact_name'];
      $contact_title = $row['contact_title'];
      $contact_phone = $row['contact_phone'];
      if(strlen($contact_phone)>2){ 
        $contact_phone = substr($row['contact_phone'],0,3)."-".substr($row['contact_phone'],3,3)."-".substr($row['contact_phone'],6,4);
      }
      $contact_mobile = $row['contact_mobile'];
      if(strlen($contact_mobile)>2){ 
        $contact_mobile = substr($row['contact_mobile'],0,3)."-".substr($row['contact_mobile'],3,3)."-".substr($row['contact_mobile'],6,4);
      }
      $contact_email = $row['contact_email'];

    ?>
    <tr>
      <td><?php echo $contact_name; ?></td>
      <td><?php echo $contact_title; ?></td>
      <td><?php echo $contact_phone; ?></td>
      <td><?php echo $contact_mobile; ?></td>
      <td><?php echo $contact_email; ?></td>
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
      $location_id = $row['location_id'];
      $location_name = $row['location_name'];
      $location_address = $row['location_address'];
      $location_city = $row['location_city'];
      $location_state = $row['location_state'];
      $location_zip = $row['location_zip'];
      $location_phone = $row['location_phone'];
      if(strlen($location_phone)>2){ 
        $location_phone = substr($row['location_phone'],0,3)."-".substr($row['location_phone'],3,3)."-".substr($row['location_phone'],6,4);
      }

    ?>
    <tr>
      <td><?php echo $location_name; ?></td>
      <td><?php echo "$location_address $location_city $location_state $location_zip"; ?></td>
      <td><?php echo $location_phone; ?></td>
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
      <th>Model</th>
      <th>Serial</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_assets)){
      $asset_id = $row['asset_id'];
      $asset_type = $row['asset_type'];
      $asset_name = $row['asset_name'];
      $asset_make = $row['asset_make'];
      $asset_model = $row['asset_model'];
      $asset_serial = $row['asset_serial'];

    ?>
    <tr>
      <td><?php echo $asset_type; ?></td>
      <td><?php echo $asset_name; ?></td>
      <td><?php echo $asset_make; ?></td>
      <td><?php echo $asset_model; ?></td>
      <td><?php echo $asset_serial; ?></td>
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
      <th>Contact Name</th>
      <th>Phone</th>
      <th>Email</th>
      <th>Account Number</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_vendors)){
      $vendor_id = $row['vendor_id'];
      $vendor_name = $row['vendor_name'];
      $vendor_description = $row['vendor_description'];
      $vendor_account_number = $row['vendor_account_number'];
      $vendor_contact_name = $row['vendor_contact_name'];
      $vendor_phone = $row['vendor_phone'];
      if(strlen($vendor_phone)>2){ 
        $vendor_phone = substr($row['vendor_phone'],0,3)."-".substr($row['vendor_phone'],3,3)."-".substr($row['vendor_phone'],6,4);
      }
      $vendor_email = $row['vendor_email'];

    ?>
    <tr>
      <td><?php echo $vendor_name; ?></td>
      <td><?php echo $vendor_description; ?></td>
      <td><?php echo $vendor_contact_name; ?></td>
      <td><?php echo $vendor_phone; ?></td>
      <td><?php echo $vendor_email; ?></td>
      <td><?php echo $vendor_account_number; ?></td>
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
      $login_id = $row['login_id'];
      $login_description = $row['login_description'];
      $login_username = $row['login_username'];
      $login_password = $row['login_password'];

    ?>
    <tr>
      <td><?php echo $login_description; ?></td>
      <td><?php echo $login_username; ?></td>
      <td><?php echo $login_password; ?></td>
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
      $network_id = $row['network_id'];
      $network_name = $row['network_name'];
      $network = $row['network'];
      $network_gateway = $row['network_gateway'];
      $network_dhcp_range = $row['network_dhcp_range'];


    ?>
    <tr>
      <td><?php echo $network_name; ?></td>
      <td><?php echo $network; ?></td>
      <td><?php echo $network_gateway; ?></td>
      <td><?php echo $network_dhcp_range; ?></td>
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
      <th>Webhost</th>
      <th>Expire</th>
      
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_domains)){
      $domain_id = $row['domain_id'];
      $domain_name = $row['domain_name'];
      $domain_registrar = $row['domain_registrar'];
      $domain_webhost = $row['domain_webhost'];
      $domain_expire = $row['domain_expire'];

      $sql_domain_registrar = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $domain_registrar");
      $row = mysqli_fetch_array($sql_domain_registrar);
      $domain_registrar = $row['vendor_name'];

      $sql_domain_webhost = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $domain_webhost");
      $row = mysqli_fetch_array($sql_domain_webhost);
      $domain_webhost = $row['vendor_name'];
      

    ?>
    <tr>
      <td><?php echo $domain_name; ?></td>
      <td><?php echo $domain_registrar; ?></td>
      <td><?php echo $domain_webhost; ?></td>
      <td><?php echo $domain_expire; ?></td>
      
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
      $application_id = $row['application_id'];
      $application_name = $row['application_name'];
      $application_type = $row['application_type'];
      $application_license = $row['application_license'];

    ?>
    <tr>
      <td><?php echo $application_name; ?></td>
      <td><?php echo $application_type; ?></td>
      <td><?php echo $application_license; ?></td>
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
      $invoice_amount = $row['invoice_amount'];

    ?>

    <tr>
      <td><?php echo $invoice_number; ?></td>
      <td class="text-right text-monospace">$<?php echo number_format($invoice_amount,2); ?></td>
      <td><?php echo $invoice_date; ?></td>
      <td><?php echo $invoice_due; ?></td>
      <td><?php echo $invoice_status; ?></td>
    </tr>

    <?php

    }

    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_payments > 0){ ?>
<h4>Payments</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Date</th>
      <th>Invoice</th>
      <th class="text-right">Amount</th>
      <th>Account</th>
      <th>Method</th>
      <th>Check #</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_payments)){
      $invoice_id = $row['invoice_id'];
      $invoice_number = $row['invoice_number'];
      $invoice_status = $row['invoice_status'];
      $payment_date = $row['payment_date'];
      $payment_method = $row['payment_method'];
      $payment_amount = $row['payment_amount'];
      $payment_reference = $row['payment_reference'];
      $account_name = $row['account_name'];

    ?>

    <tr>
      <td><?php echo $payment_date; ?></td>
      <td><?php echo $invoice_number; ?></td>
      <td class="text-right text-monospace">$<?php echo number_format($payment_amount,2); ?></td>
      <td><?php echo $account_name; ?></td>
      <td><?php echo $payment_method; ?></td>
      <td><?php echo $payment_reference; ?></td>
    </tr>

    <?php

    }

    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_quotes > 0){ ?>
<h4>Quotes</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Number</th>
      <th class="text-right">Amount</th>
      <th>Date</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_quotes)){
      $quote_id = $row['quote_id'];
      $quote_number = $row['quote_number'];
      $quote_status = $row['quote_status'];
      $quote_date = $row['quote_date'];
      $quote_amount = $row['quote_amount'];

    ?>

    <tr>
      <td>QUO-<?php echo $quote_number; ?></td>
      <td class="text-right text-monospace">$<?php echo number_format($quote_amount,2); ?></td>
      <td><?php echo $quote_date; ?></td>
      <td><?php echo $quote_status; ?></td>
    </tr>

    <?php

    }

    ?>

  </tbody>
</table>
<?php } ?>


<?php if($num_recurring > 0){ ?>
<h4>Recurring Invoices</h4>
<table class="table table-bordered table-sm mb-4">
  <thead>
    <tr>
      <th>Frequency</th>
      <th>Start Date</th>
      <th>Last Sent</th>
      <th>Next Date</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php

    while($row = mysqli_fetch_array($sql_recurring)){
      $recurring_invoice_id = $row['recurring_invoice_id'];
      $recurring_invoice_frequency = $row['recurring_invoice_frequency'];
      $recurring_invoice_status = $row['recurring_invoice_status'];
      $recurring_invoice_start_date = $row['recurring_invoice_start_date'];
      $recurring_invoice_last_sent = $row['recurring_invoice_last_sent'];
      if($recurring_invoice_last_sent == 0){
        $recurring_invoice_last_sent = "-";
      }
      $recurring_invoice_next_date = $row['recurring_invoice_next_date'];
      $invoice_id = $row['invoice_id'];
      if($recurring_invoice_status == 1){
        $status = "Active";
      }else{
        $status = "Inactive";
      }

    ?>

    <tr>
      <td><?php echo ucwords($recurring_invoice_frequency); ?>ly</td>
      <td><?php echo $recurring_invoice_start_date; ?></td>
      <td><?php echo $recurring_invoice_last_sent; ?></td>
      <td><?php echo $recurring_invoice_next_date; ?></td>
      <td><?php echo $status; ?></td>
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
  $note_id = $row['note_id'];
  $note_subject = $row['note_subject'];
  $note_body = $row['note_body'];

?>
<h6><?php echo $note_subject; ?></h6>
<hr>
<p class="mb-4"><?php echo $note_body; ?></p>

<?php } ?>

<?php } ?>


<?php } ?>

<?php include("footer.php");