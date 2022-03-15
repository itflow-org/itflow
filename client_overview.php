<?php

$sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts LEFT JOIN departments ON contact_department_id = department_id WHERE contact_client_id = $client_id AND contacts.company_id = $session_company_id ORDER BY contact_updated_at DESC LIMIT 5");

$sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id AND company_id = $session_company_id ORDER BY vendor_updated_at DESC LIMIT 5");

$sql_documents = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_client_id = $client_id AND documents.company_id = $session_company_id ORDER BY document_updated_at DESC LIMIT 5");

$sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_client_id = $client_id AND tickets.company_id = $session_company_id ORDER BY ticket_updated_at DESC LIMIT 5");

$sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE login_client_id = $client_id AND company_id = $session_company_id ORDER BY login_updated_at DESC LIMIT 5");

// Expiring Items

// Get Domains Expiring
$sql_domains_expiring = mysqli_query($mysqli,"SELECT * FROM domains
    WHERE domain_client_id = $client_id
    AND domain_expire < CURRENT_DATE + INTERVAL 30 DAY
    AND company_id = $session_company_id ORDER BY domain_expire DESC"
);

// Get Asset Warranties Expiring
$sql_asset_warranties_expiring = mysqli_query($mysqli,"SELECT * FROM assets
    WHERE asset_client_id = $client_id
    AND asset_warranty_expire < CURRENT_DATE + INTERVAL 90 DAY
    AND company_id = $session_company_id ORDER BY asset_warranty_expire DESC"
);

// Get Stale Tickets
$sql_tickets_stale = mysqli_query($mysqli,"SELECT * FROM tickets
    WHERE ticket_client_id = $client_id
    AND ticket_created_at < CURRENT_DATE + INTERVAL 14 DAY
    AND ticket_status = 'Open'
    AND company_id = $session_company_id ORDER BY ticket_created_at DESC"
);

?>

<h4><i class="fas fa-tachometer-alt"></i> Overview</h4>
<hr>
<div class="row">

  <?php if(mysqli_num_rows($sql_contacts) > 0){ ?>

    <!-- Notes -->

    <div class="col-2">

      <div class="card card-outline card-primary mb-3">
        <div class="card-body">
          <h5 class="card-title mb-2"><i class="fa fa-sticky-note"></i> Client Notes</h5>
          <textarea class="form-control" id="clientNotes" onblur="updateClientNotes(<?php echo $client_id ?>)"><?php echo $client_notes ?></textarea>
        </div>
      </div>

    </div>

    <!-- Contacts-->

    <div class="col-4">

      <div class="card card-outline card-primary mb-3">
        <div class="card-body">
          <h5 class="card-title mb-2"><i class="fa fa-users"></i> Recent Contacts</h5>
          <table class="table table-borderless table-sm">
            <tbody>
            <?php

            while($row = mysqli_fetch_array($sql_contacts)){
              $contact_id = $row['contact_id'];
              $contact_name = $row['contact_name'];
              $contact_title = $row['contact_title'];
              $contact_phone = formatPhoneNumber($row['contact_phone']);
              $contact_extension = $row['contact_extension'];
              $contact_mobile = formatPhoneNumber($row['contact_mobile']);
              $contact_email = $row['contact_email'];
              //$client_id = $row['client_id'];
              //$client_name = $row['client_name'];
              $department_name = $row['department_name'];

              ?>
              <tr>
                <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=contacts"><?php echo $contact_name; ?></a>
                  <br><small class="text-secondary"><?php echo $contact_title; ?></small>
                </td>
                <td><?php echo $contact_email; ?></td>
                <td><?php echo "$contact_phone $contact_extension"; ?><br><?php echo $contact_mobile; ?></td>
              </tr>

              <?php
            }
            ?>

            </tbody>
          </table>
>>>>>>> 7b816e0879511ef11bd8642294103cbeaa3bf01e
        </div>
      </div>
    </div>

  <?php } ?>

  <?php if(mysqli_num_rows($sql_contacts) > 0){ ?>

    <!-- Domains Expiring -->

    <div class="col-3">

      <div class="card card-outline card-danger mb-3">
        <div class="card-body">
          <h5 class="card-title mb-2"><i class="fa fa-globe"></i> Domains Expiring Soon <small class="text-secondary">(30d)</small></h5>
          <table class="table table-borderless table-sm">
            <tbody>
            <?php

            while($row = mysqli_fetch_array($sql_domains_expiring)){
              $domain_id = $row['domain_id'];
              $domain_name = $row['domain_name'];
              $domain_expire = $row['domain_expire'];

              ?>
              <tr>
                <td><?php echo $domain_name; ?></td>
                <td class="text-danger"><?php echo $domain_expire; ?></td>
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

  <?php if(mysqli_num_rows($sql_asset_warranties_expiring) > 0){ ?>

    <!-- Asset Warrenties Expiring-->

    <div class="col-3">

      <div class="card card-outline card-danger mb-3">
        <div class="card-body">
          <h5 class="card-title mb-2"><i class="fa fa-laptop"></i> Asset Warranties Expiring Soon <small class="text-secondary">(90d)</small></h5>
          <table class="table table-borderless table-sm">
            <tbody>
            <?php

            while($row = mysqli_fetch_array($sql_asset_warranties_expiring)){
              $asset_id = $row['asset_id'];
              $asset_name = $row['asset_name'];
              $asset_warranty_expire = $row['asset_warranty_expire'];

              ?>
              <tr>
                <td><?php echo $asset_name; ?></td>
                <td class="text-danger"><?php echo $asset_warranty_expire; ?></td>
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

  <?php if(mysqli_num_rows($sql_tickets_stale) > 0){ ?>

    <!-- Stale Tickets -->

    <div class="col-5">

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
                <td><?php echo "$ticket_prefix$ticket_number"; ?></td>
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
