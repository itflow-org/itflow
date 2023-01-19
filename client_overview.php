<?php include("inc_all_client.php"); ?>

<?php

$sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contacts.company_id = $session_company_id ORDER BY contact_updated_at, contact_created_at DESC LIMIT 5");

$sql_important_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_important = 1 AND contact_archived_at IS NULL AND contacts.company_id = $session_company_id ORDER BY contact_updated_at, contact_name DESC");

$sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL AND company_id = $session_company_id ORDER BY vendor_updated_at DESC LIMIT 5");

$sql_documents = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_client_id = $client_id AND document_archived_at IS NULL AND documents.company_id = $session_company_id ORDER BY document_updated_at DESC LIMIT 5");

$sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_client_id = $client_id AND tickets.company_id = $session_company_id ORDER BY ticket_updated_at DESC LIMIT 5");

$sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_client_id = $client_id AND company_id = $session_company_id ORDER BY login_updated_at DESC LIMIT 5");

// Expiring Items

// Get Domains Expiring
$sql_domains_expiring = mysqli_query($mysqli,"SELECT * FROM domains
    WHERE domain_client_id = $client_id
    AND domain_expire != '0000-00-00'
    AND domain_archived_at IS NULL
    AND domain_expire < CURRENT_DATE + INTERVAL 30 DAY
    AND company_id = $session_company_id ORDER BY domain_expire DESC"
);

?>

<div class="row">

  <!-- Notes -->

  <div class="col-12">

    <div class="card card-dark mb-5 elevation-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fa fa-fw fa-edit mr-2"></i>Quick Notes</h5>
      </div>
      <div class="card-body">
        <textarea class="form-control" rows=5 id="clientNotes" placeholder="Enter quick notes here" onblur="updateClientNotes(<?php echo $client_id ?>)"><?php echo $client_notes ?></textarea>
      </div>
    </div>

  </div>

<?php if(mysqli_num_rows($sql_important_contacts) > 0 ){ ?>

  <div class="col-md-4">


    <div class="card card-dark mb-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fa fa-fw fa-users mr-2"></i>Important Contacts</h5>
      </div>
      <div class="card-body p-1">
        <table class="table table-borderless table-sm">
          <?php

          while($row = mysqli_fetch_array($sql_important_contacts)){
            $contact_id = $row['contact_id'];
            $contact_name = htmlentities($row['contact_name']);
            $contact_title = htmlentities($row['contact_title']);
            $contact_email = htmlentities($row['contact_email']);
            $contact_phone = formatPhoneNumber($row['contact_phone']);
            $contact_extension = htmlentities($row['contact_extension']);
            $contact_mobile = formatPhoneNumber($row['contact_mobile']);

          ?>
          <tr>
            <td>
              <a href="client_contact_details.php?client_id=<?php echo $client_id; ?>&contact_id=<?php echo $contact_id; ?>" class="text-bold"><?php echo $contact_name; ?></a>
              <br>
              <small class="text-secondary"><?php echo $contact_title; ?></small>
            </td>
            <td>
              <?php if(!empty($contact_phone)){ ?>
              <?php echo "<i class='fa fa-fw fa-phone text-secondary'></i> $contact_phone $contact_extension"; ?>
              <?php } ?>
              <?php if(!empty($contact_mobile)){ ?>
              <br>
              <div class="text-secondary"><i class='fa fa-fw fa-mobile-alt text-secondary'></i> <?php echo "$contact_mobile"; ?></div>
              <?php } ?>
            </td>
          </tr>
        <?php
        }
        ?>

        </table>
      </div>
    </div>
  </div>

<?php } ?>

<?php if(mysqli_num_rows($sql_contacts) > 0 || mysqli_num_rows($sql_vendors) > 0 ){ ?>
  <div class="col-md-3">

    <div class="card card-dark mb-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fa fa-history mr-2"></i>Recently Updated</h5>
      </div>
      <div class="card-body">
        
          <?php

          while($row = mysqli_fetch_array($sql_contacts)){
            $contact_id = $row['contact_id'];
            $contact_name = htmlentities($row['contact_name']);
            $contact_updated_at = $row['contact_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-user text-secondary mr-1"></i>
              <a href="client_contact_details.php?client_id=<?php echo $client_id; ?>&contact_id=<?php echo $contact_id; ?>"><?php echo $contact_name; ?></a>
            </p>
          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_vendors)){
            $vendor_id = $row['vendor_id'];
            $vendor_name = htmlentities($row['vendor_name']);
            $vendor_updated_at = $row['vendor_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fas fa-fw fa-building text-secondary mr-1"></i>
              <a href="client_vendors.php?client_id=<?php echo $client_id; ?>&q=<?php echo $vendor_name; ?>"><?php echo $vendor_name; ?></a></td>
            </p>
          <?php
          }
          ?>

      </div>
    </div>
  </div>
<?php } ?>

<?php if(mysqli_num_rows($sql_contacts) > 0 || mysqli_num_rows($sql_vendors) > 0 ){ ?>

  <div class="col-md-3">

    <div class="card card-dark mb-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fa fa-fw fa-eye mr-2"></i>Recently Viewed</h5>
      </div>
      <div class="card-body">
        
          <?php

          while($row = mysqli_fetch_array($sql_contacts)){
            $contact_id = $row['contact_id'];
            $contact_name = htmlentities($row['contact_name']);
            $contact_updated_at = $row['contact_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-user text-secondary mr-1"></i>
              <a href="client_contacts.php?client_id=<?php echo $client_id; ?>&q=<?php echo $contact_name; ?>"><?php echo $contact_name; ?></a>
            </p>
          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_vendors)){
            $vendor_id = $row['vendor_id'];
            $vendor_name = htmlentities($row['vendor_name']);
            $vendor_updated_at = $row['vendor_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fas fa-fw fa-building text-secondary mr-1"></i>
              <a href="client_vendors.php?client_id=<?php echo $client_id; ?>&q=<?php echo $vendor_name; ?>"><?php echo $vendor_name; ?></a></td>
            </p>
          <?php
          }
          ?>

      </div>
    </div>
  </div>

<?php } ?>

  <div class="col-md-4">

    <div class="card card-dark mb-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fa fa-fw fa-exclamation-triangle text-warning mr-2"></i>Upcoming Expirations</h5>
      </div>
      <div class="card-body">
        
          <?php

          while($row = mysqli_fetch_array($sql_domains_expiring)){
            $domain_id = $row['domain_id'];
            $domain_name = htmlentities($row['domain_name']);
            $domain_expire = $row['domain_expire'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-globe text-secondary mr-1"></i>
              <a href="client_domains.php?client_id=<?php echo $client_id; ?>&q=<?php echo $domain_name; ?>"><?php echo $domain_name; ?></a>
              <span class="text-warning">-- <?php echo $domain_expire; ?></span>
            </p>
          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_asset_warranties_expiring)){
            $asset_id = $row['asset_id'];
            $asset_name = htmlentities($row['asset_name']);
            $asset_warranty_expire = $row['asset_warranty_expire'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
              <a href="client_assets.php?client_id=<?php echo $client_id; ?>&q=<?php echo $asset_name; ?>"><?php echo $asset_name; ?></a>
              <span class="text-warning">-- <?php echo $asset_warranty_expire; ?></span>
            </p>            


          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_asset_retire)){
            $asset_id = $row['asset_id'];
            $asset_name = htmlentities($row['asset_name']);
            $asset_install_date = $row['asset_install_date'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
              <a href="client_assets.php?client_id=<?php echo $client_id; ?>&q=<?php echo $asset_name; ?>"><?php echo $asset_name; ?></a>
              <span class="text-warning">-- <?php echo $asset_install_date; ?></span>
            </p>

          <?php
          }
          ?>

      </div>
    </div>
  </div>

  <?php if(mysqli_num_rows($sql_tickets_stale) > 0){ ?>

  <!-- Stale Tickets -->

  <div class="col-md-5">

    <div class="card card-danger mb-3">
      <div class="card-body">
        <h5 class="card-title mb-2"><i class="fa fa-life-ring"></i> Stale Tickets <small class="text-secondary">(14d)</small></h5>
        <table class="table table-borderless table-sm">
          <tbody>
          <?php

          while($row = mysqli_fetch_array($sql_tickets_stale)){
            $ticket_id = $row['ticket_id'];
            $ticket_prefix = htmlentities($row['ticket_prefix']);
            $ticket_number = $row['ticket_number'];
            $ticket_subject = htmlentities($row['ticket_subject']);
            $ticket_created_at = $row['ticket_created_at'];

            ?>
            <tr>
              <td><a href="ticket.php?ticket_id=<?php echo $ticket_id?>"><?php echo "$ticket_prefix$ticket_number"; ?></a></td>
              <td><?php echo $ticket_subject; ?></td>
              <td class="text-danger"><?php echo $ticket_created_at; ?></td>
            </tr>

            <?php
          }
          ?>

          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php } ?>

</div>

<script>
  function updateClientNotes(client_id) {
      var notes = document.getElementById("clientNotes").value;

      // Send a POST request to ajax.php as ajax.php with data client_set_notes=true, client_id=NUM, notes=NOTES
      jQuery.post(
          "ajax.php",
          {
              client_set_notes: 'TRUE',
              client_id: client_id,
              notes: notes
          }
      )


  }
</script>

<?php

include("footer.php");

?>