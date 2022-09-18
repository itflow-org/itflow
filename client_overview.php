<?php include("inc_all_client.php"); ?>

<?php

$sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contacts.company_id = $session_company_id ORDER BY contact_updated_at, contact_created_at DESC LIMIT 5");

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

// Get Asset Warranties Expiring
$sql_asset_warranties_expiring = mysqli_query($mysqli,"SELECT * FROM assets
    WHERE asset_client_id = $client_id
    AND asset_warranty_expire != '0000-00-00'
    AND asset_archived_at IS NULL  
    AND asset_warranty_expire < CURRENT_DATE + INTERVAL 90 DAY
    AND company_id = $session_company_id ORDER BY asset_warranty_expire DESC"
);

// Get Assets Retiring
$sql_asset_retire = mysqli_query($mysqli,"SELECT * FROM assets
    WHERE asset_client_id = $client_id
    AND asset_install_date != '0000-00-00'
    AND asset_archived_at IS NULL 
    AND asset_install_date + INTERVAL 7 YEAR < CURRENT_DATE + INTERVAL 90 DAY
    AND company_id = $session_company_id ORDER BY asset_install_date DESC"
);

// Get Stale Tickets
$sql_tickets_stale = mysqli_query($mysqli,"SELECT * FROM tickets
    WHERE ticket_client_id = $client_id
    AND ticket_created_at < CURRENT_DATE - INTERVAL 14 DAY
    AND ticket_status != 'Closed'
    AND company_id = $session_company_id ORDER BY ticket_created_at DESC"
);

?>

<div class="row">

  <!-- Notes -->

  <div class="col-12">

    <div class="card card-outline card-primary mb-3">
      <div class="card-body">
        <textarea class="form-control" rows=5 id="clientNotes" placeholder="Enter client notes here" onblur="updateClientNotes(<?php echo $client_id ?>)"><?php echo $client_notes ?></textarea>
      </div>
    </div>

  </div>

  <div class="col-md-3">

    <div class="card card-outline card-primary mb-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fa fa-history mr-2"></i>Recently Updated</h5>
      </div>
      <div class="card-body">
        
          <?php

          while($row = mysqli_fetch_array($sql_contacts)){
            $contact_id = $row['contact_id'];
            $contact_name = $row['contact_name'];
            $contact_updated_at = $row['contact_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-user text-secondary mr-1"></i>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=contacts&q=<?php echo $contact_name; ?>"><?php echo $contact_name; ?></a>
            </p>
          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_vendors)){
            $vendor_id = $row['vendor_id'];
            $vendor_name = $row['vendor_name'];
            $vendor_updated_at = $row['vendor_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fas fa-fw fa-building text-secondary mr-1"></i>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=vendors&q=<?php echo $vendor_name; ?>"><?php echo $vendor_name; ?></a></td>
            </p>
          <?php
          }
          ?>

      </div>
    </div>
  </div>

  <div class="col-md-3">

    <div class="card card-outline card-primary mb-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fa fa-eye mr-2"></i>Recently Viewed</h5>
      </div>
      <div class="card-body">
        
          <?php

          while($row = mysqli_fetch_array($sql_contacts)){
            $contact_id = $row['contact_id'];
            $contact_name = $row['contact_name'];
            $contact_updated_at = $row['contact_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-user text-secondary mr-1"></i>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=contacts&q=<?php echo $contact_name; ?>"><?php echo $contact_name; ?></a>
            </p>
          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_vendors)){
            $vendor_id = $row['vendor_id'];
            $vendor_name = $row['vendor_name'];
            $vendor_updated_at = $row['vendor_updated_at'];

          ?>
            <p class="mb-1">
              <i class="fas fa-fw fa-building text-secondary mr-1"></i>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=vendors&q=<?php echo $vendor_name; ?>"><?php echo $vendor_name; ?></a></td>
            </p>
          <?php
          }
          ?>

      </div>
    </div>
  </div>

  <div class="col-md-4">

    <div class="card card-outline card-warning mb-3">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-calendar-alt mr-2"></i>Upcoming Expirations</h5>
      </div>
      <div class="card-body">
        
          <?php

          while($row = mysqli_fetch_array($sql_domains_expiring)){
            $domain_id = $row['domain_id'];
            $domain_name = $row['domain_name'];
            $domain_expire = $row['domain_expire'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-globe text-secondary mr-1"></i>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=domains&q=<?php echo $domain_name; ?>"><?php echo $domain_name; ?></a>
              <span class="text-warning">-- <?php echo $domain_expire; ?></span>
            </p>
          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_asset_warranties_expiring)){
            $asset_id = $row['asset_id'];
            $asset_name = $row['asset_name'];
            $asset_warranty_expire = $row['asset_warranty_expire'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=assets&q=<?php echo $asset_name; ?>"><?php echo $asset_name; ?></a>
              <span class="text-warning">-- <?php echo $asset_warranty_expire; ?></span>
            </p>            


          <?php
          }
          ?>

          <?php

          while($row = mysqli_fetch_array($sql_asset_retire)){
            $asset_id = $row['asset_id'];
            $asset_name = $row['asset_name'];
            $asset_install_date = $row['asset_install_date'];

          ?>
            <p class="mb-1">
              <i class="fa fa-fw fa-laptop text-secondary mr-1"></i>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=assets&q=<?php echo $asset_name; ?>"><?php echo $asset_name; ?></a>
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

    <div class="card card-outline card-danger mb-3">
      <div class="card-body">
        <h5 class="card-title mb-2"><i class="fa fa-ticket-alt"></i> Stale Tickets <small class="text-secondary">(14d)</small></h5>
        <table class="table table-borderless table-sm">
          <tbody>
          <?php

          while($row = mysqli_fetch_array($sql_tickets_stale)){
            $ticket_id = $row['ticket_id'];
            $ticket_prefix = $row['ticket_prefix'];
            $ticket_number = $row['ticket_number'];
            $ticket_subject = $row['ticket_subject'];
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