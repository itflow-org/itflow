<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_logins WHERE client_id = $client_id ORDER BY client_login_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-key"></i> Logins</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientLoginModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Description</th>
            <th>Username</th>
            <th>Password</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_login_id = $row['client_login_id'];
            $client_login_description = $row['client_login_description'];
            $client_login_web_link = $row['client_login_web_link'];
            if(!empty($client_login_web_link)){
              $client_login_description_td = "<a href='$client_login_web_link' target='_blank'>$client_login_description <i class='fa fa-link'></i></a>";
            }else{
              $client_login_description_td = $row['client_login_description'];
            }
            $client_login_username = $row['client_login_username'];
            $client_login_password = $row['client_login_password'];
      
          ?>
          <tr>
            <td><?php echo $client_login_description_td; ?></td>
            <td><?php echo $client_login_username; ?></td>
            <td><?php echo $client_login_password; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientLoginModal<?php echo $client_login_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_client_login=<?php echo $client_login_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_client_login_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_client_login_modal.php"); ?>