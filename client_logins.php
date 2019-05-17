<?php $sql = mysqli_query($mysqli,"SELECT * FROM logins WHERE client_id = $client_id ORDER BY login_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-key"></i> Logins</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addLoginModal"><i class="fa fa-plus"></i></button>
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
            $login_id = $row['login_id'];
            $login_description = $row['login_description'];
            $login_web_link = $row['login_web_link'];
            if(!empty($login_web_link)){
              $login_description_td = "<a href='$login_web_link' target='_blank'>$login_description <i class='fa fa-link'></i></a>";
            }else{
              $login_description_td = $row['login_description'];
            }
            $login_username = $row['login_username'];
            $login_password = $row['login_password'];
      
          ?>
          <tr>
            <td><?php echo $login_description_td; ?></td>
            <td><?php echo $login_username; ?></td>
            <td><?php echo $login_password; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_login=<?php echo $login_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_login_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_login_modal.php"); ?>