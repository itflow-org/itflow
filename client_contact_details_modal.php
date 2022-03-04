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
                $asset_type = $row['asset_type'];
                $asset_name = $row['asset_name'];
                $asset_make = $row['asset_make'];
                $asset_model = $row['asset_model'];
                $asset_serial = $row['asset_serial'];

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
                $login_name = $row['login_name'];
                $login_uri = $row['login_uri'];
                $login_username = $row['login_username'];
                $login_password = $row['login_password'];
                $login_note = $row['login_note'];
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
                $software_name = $row['software_name'];
                $software_type = $row['software_type'];
                $software_license = $row['software_license'];
                $software_notes = $row['software_notes'];

              ?>
              
              <li><?php echo "$software_name - $software_type"; ?></li>

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