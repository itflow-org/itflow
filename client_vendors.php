<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id ORDER BY client_vendor_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Vendor</th>
        <th>Description</th>
        <th>Account Number</th>
        <th></th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_vendor_id = $row['client_vendor_id'];
        $client_vendor_name = $row['client_vendor_name'];
        $client_vendor_description = $row['client_vendor_description'];
        $client_vendor_account_number = $row['client_vendor_account_number'];
        $client_login_id = $row['client_login_id'];

        $sql2 = mysqli_query($mysqli,"SELECT * FROM client_logins WHERE client_vendor_id = $client_vendor_id");
        $row = mysqli_fetch_array($sql2);
        $client_login_id = $row['client_login_id'];
        $client_login_username = $row['client_login_username'];
        $client_login_password = $row['client_login_password'];
        $client_vendor_id_relation = $row['client_vendor_id'];
          
      ?>
      <tr>
        <td><?php echo $client_vendor_name; ?></td>
        <td><?php echo $client_vendor_description; ?></td>
        <td><?php echo $client_vendor_account_number; ?></td>
        <td>
          <?php
          if($client_vendor_id == $client_vendor_id_relation){
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
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientVendorModal<?php echo $client_vendor_id; ?>">Edit</a>
              <a class="dropdown-item" href="post.php?delete_client_vendor=<?php echo $client_vendor_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_vendor_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>