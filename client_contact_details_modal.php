<?php

$sql_assets = mysqli_query($mysqli,"SELECT * FROM assets WHERE contact_id = $contact_id");
$sql_logins = mysqli_query($mysqli,"SELECT * FROM logins WHERE contact_id = $contact_id");
$sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE contact_id = $contact_id");

?>




<div class="modal" id="contactDetailsModal<?php echo $contact_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-user-edit mr-2"></i><?php echo $contact_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3" id="pills-tab">
          <li class="nav-item">
            <a class="nav-link active" id="pills-assets-tab<?php echo $contact_id; ?>" data-toggle="pill" href="#pills-assets<?php echo $contact_id; ?>">Assets</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="pills-logins-tab<?php echo $contact_id; ?>" data-toggle="pill" href="#pills-logins<?php echo $contact_id; ?>">Logins</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="pills-software-tab<?php echo $contact_id; ?>" data-toggle="pill" href="#pills-software<?php echo $contact_id; ?>">Software</a>
          </li>
        </ul>

        <hr>
        
        <div class="tab-content" id="pills-tabContent<?php echo $contact_id; ?>">

          <div class="tab-pane fade show active" id="pills-assets<?php echo $contact_id; ?>">
            <table class="table border table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Type</th>
                  <th>Name</th>
                  <th>Make<</th>
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
                
                <td><?php echo $asset_type; ?></td>
                <td><?php echo $asset_name; ?></td>
                <td><?php echo $asset_make; ?></td>
                <td><?php echo $asset_model; ?></td>
                <td><?php echo $asset_serial; ?></td>

                <?php
                }
                ?>
              </tbody>
            </table>
           
          </div>

          <div class="tab-pane fade" id="pills-logins<?php echo $contact_id; ?>">

            <table class="table border table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Name</th>
                  <th>Username</th>
                  <th>Password</th>
                </tr>
              </thead>
              <tbody>

                <?php

                while($row = mysqli_fetch_array($sql_logins)){
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
                
                <td><?php echo $login_name; ?></td>
                <td><?php echo $login_username; ?></td>
                <td><?php echo $login_password; ?></td>

                <?php
                }
                ?>
              </tbody>
            </table>

          </div>

          <div class="tab-pane fade" id="pills-software<?php echo $contact_id; ?>">
            <table class="table border table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Software</th>
                  <th>Type</th>
                  <th>License</th>
                </tr>
              </thead>
              <tbody>

                <?php

                while($row = mysqli_fetch_array($sql_logins)){
                  $software_id = $row['software_id'];
                  $software_name = $row['software_name'];
                  $software_type = $row['software_type'];
                  $software_license = $row['software_license'];
                  $software_notes = $row['software_notes'];

                  $sql_login = mysqli_query($mysqli,"SELECT *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins WHERE software_id = $software_id");
                  $row = mysqli_fetch_array($sql_login);
                  $login_id = $row['login_id'];
                  $login_username = $row['login_username'];
                  $login_password = $row['login_password'];
                  $software_id_relation = $row['software_id'];

                ?>
                
                <td><?php echo $software_name; ?></td>
                <td><?php echo $software_type; ?></td>
                <td><?php echo $software_license; ?></td>

                <?php
                }
                ?>
              </tbody>
            </table>

            

          </div>

        </div>

      </div>
      <div class="modal-footer bg-white">
        <a href="post.php?delete_contact=<?php echo $contact_id; ?>" class="btn btn-danger mr-auto"><i class="fa fa-trash text-white"></i></a>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="edit_contact" class="btn btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>