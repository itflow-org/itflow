<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_assets WHERE client_id = $client_id ORDER BY client_asset_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Type</th>
        <th>Name</th>
        <th>Make</th>
        <th>Serial</th>
        <th></th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_asset_id = $row['client_asset_id'];
        $client_asset_type = $row['client_asset_type'];
        $client_asset_name = $row['client_asset_name'];
        $client_asset_make = $row['client_asset_make'];
        $client_asset_model = $row['client_asset_model'];
        $client_asset_serial = $row['client_asset_serial'];

        $sql2 = mysqli_query($mysqli,"SELECT * FROM client_logins WHERE client_asset_id = $client_asset_id");
        $row = mysqli_fetch_array($sql2);
        $client_login_id = $row['client_login_id'];
        $client_login_username = $row['client_login_username'];
        $client_login_password = $row['client_login_password'];
        $client_asset_id_relation = $row['client_asset_id'];
  
      ?>
      <tr>
        <td><?php echo "$client_asset_type"; ?></td>
        <td><?php echo "$client_asset_name"; ?></td>
        <td><?php echo "$client_asset_make $client_asset_model"; ?></td>
        <td><?php echo "$client_asset_serial"; ?></td>
        <td>
          <?php
          if($client_asset_id == $client_asset_id_relation){
          ?>  
          <button type="button" class="btn btn-dark btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $client_login_id; ?>"><i class="fas fa-key"></i></button>

          <div class="modal" id="viewPasswordModal<?php echo $client_login_id; ?>" tabindex="-1">
            <div class="modal-dialog modal-sm">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title"><i class="fa fa-key"></i> Login</h5>
                  <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
                <div class="modal-body">
                  <h2><?php echo $client_login_username; ?></h2>
                  <h3><?php echo $client_login_password; ?></h3>
                </div>
              </div>
            </div>
          </div>
          <?php
          }
          ?>
        </td>
        <td>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientAssetModal<?php echo $client_asset_id; ?>">Edit</a>
              <a class="dropdown-item" href="post.php?delete_client_asset=<?php echo $client_asset_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_asset_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>