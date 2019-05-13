<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_applications WHERE client_id = $client_id ORDER BY client_application_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-rocket mr-2"></i>Applications</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientApplicationModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Application</th>
            <th>Type</th>
            <th>License</th>
            <th></th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_application_id = $row['client_application_id'];
            $client_application_name = $row['client_application_name'];
            $client_application_type = $row['client_application_type'];
            $client_application_license = $row['client_application_license'];

            $sql2 = mysqli_query($mysqli,"SELECT * FROM client_logins WHERE client_vendor_id = $client_vendor_id");
            $row = mysqli_fetch_array($sql2);
            $client_login_id = $row['client_login_id'];
            $client_login_username = $row['client_login_username'];
            $client_login_password = $row['client_login_password'];
            $client_application_id_relation = $row['client_application_id'];

          ?>
          <tr>
            <td><?php echo $client_application_name; ?></td>
            <td><?php echo $client_application_type; ?></td>
            <td><?php echo $client_application_license; ?></td>
            <td>
              <?php
              if($client_application_id == $client_application_id_relation){
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
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientApplicationModal<?php echo $client_application_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_client_application=<?php echo $client_application_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_client_application_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_client_application_modal.php"); ?>