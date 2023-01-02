<div class="modal" id="contactDetailsModal<?php echo $contact_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-user mr-2"></i><?php echo $contact_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="pill" href="#pillsRelatedAssets<?php echo $contact_id; ?>">Assets</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pillsRelatedLogins<?php echo $contact_id; ?>">Logins</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pillsRelatedSoftware<?php echo $contact_id; ?>">Software</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pillsRelatedTickets<?php echo $contact_id; ?>">Tickets</a>
          </li>
        </ul>

        <hr>
        
        <div class="tab-content">
          <div class="tab-pane fade show active" id="pillsRelatedAssets<?php echo $contact_id; ?>">
        
            <ul>

              <?php

              while($row = mysqli_fetch_array($sql_related_assets)){
                $asset_id = $row['asset_id'];
                $asset_type = htmlentities($row['asset_type']);
                $asset_name = htmlentities($row['asset_name']);
                $asset_make = htmlentities($row['asset_make']);
                $asset_model = htmlentities($row['asset_model']);
                $asset_serial = htmlentities($row['asset_serial']);

              ?>
              
              <li><?php echo $asset_name; ?></li>

              <?php
              }
              ?>
            </ul>
           
          </div>

          <div class="tab-pane fade" id="pillsRelatedLogins<?php echo $contact_id; ?>">
            <ul>
              <?php

              while($row = mysqli_fetch_array($sql_related_logins)){
                $login_id = $row['login_id'];
                $login_name = htmlentities($row['login_name']);
                $login_uri = htmlentities($row['login_uri']);
                $login_username = htmlentities($row['login_username']);
                $login_password = htmlentities($row['login_password']);
                $login_note = htmlentities($row['login_note']);
                $vendor_id = $row['vendor_id'];
                $asset_id = $row['asset_id'];
                $software_id = $row['software_id'];

              ?>
              
              <li><?php echo "$login_name - $login_username"; ?></li>

              <?php
              }
              ?>
            </ul>

          </div>

          <div class="tab-pane fade" id="pillsRelatedSoftware<?php echo $contact_id; ?>">
            <ul>
              <?php

              while($row = mysqli_fetch_array($sql_related_software)){
                $software_id = $row['software_id'];
                $software_name = htmlentities($row['software_name']);
                $software_type = htmlentities($row['software_type']);
                $software_notes = htmlentities($row['software_notes']);

              ?>
              
              <li><?php echo "$software_name - $software_type"; ?></li>

              <?php
              }
              ?>
            </ul>
      
          </div>

          <div class="tab-pane fade" id="pillsRelatedTickets<?php echo $contact_id; ?>">
            <ul>
              <?php

              while($row = mysqli_fetch_array($sql_related_tickets)){
                $ticket_id = $row['ticket_id'];
                $ticket_prefix = htmlentities($row['ticket_prefix']);
                $ticket_number = $row['ticket_number'];
                $ticket_subject = htmlentities($row['ticket_subject']);

                ?>

                <li><a href="ticket.php?ticket_id=<?=$ticket_id ?>"><?php echo "[$ticket_prefix$ticket_number] - $ticket_subject"; ?></a></li>

                <?php
              }
              ?>
            </ul>

          </div>

        </div>

      </div>

    </div>
  </div>
</div>
