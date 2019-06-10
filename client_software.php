<?php $sql = mysqli_query($mysqli,"SELECT * FROM software WHERE client_id = $client_id ORDER BY software_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-rocket mr-2"></i>Software</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addSoftwareModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Software</th>
            <th>Type</th>
            <th>License</th>
            <th></th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $software_id = $row['software_id'];
            $software_name = $row['software_name'];
            $software_type = $row['software_type'];
            $software_license = $row['software_license'];

            $sql_login = mysqli_query($mysqli,"SELECT * FROM logins WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_array($sql_login);
            $login_id = $row['login_id'];
            $login_username = $row['login_username'];
            $login_password = $row['login_password'];
            $software_id_relation = $row['software_id'];

          ?>
          <tr>
            <td><?php echo $software_name; ?></td>
            <td><?php echo $software_type; ?></td>
            <td><?php echo $software_license; ?></td>
            <td>
              <?php
              if($software_id == $software_id_relation){
              ?>  
              <button type="button" class="btn btn-dark btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $login_id; ?>"><i class="fas fa-key"></i></button>

              <div class="modal" id="viewPasswordModal<?php echo $login_id; ?>" tabindex="-1">
                <div class="modal-dialog modal-sm">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fa fa-key"></i> Login</h5>
                      <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                      </button>
                    </div>
                    <div class="modal-body">
                      <h2><?php echo $login_username; ?></h2>
                      <h3><?php echo $login_password; ?></h3>
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
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_software=<?php echo $software_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_software_modal.php"); ?>      
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_software_modal.php"); ?>