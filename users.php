<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM users ORDER BY user_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-users"></i> Users</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addUserModal"><i class="fas fa-plus"></i> Add New</button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $user_id = $row['user_id'];
            $name = $row['name'];
            $email = $row['email'];
            $password = $row['password'];
      
          ?>
          <tr>
            <td><?php echo $name; ?></a></td>
            <td><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editUserModal<?php echo $user_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_user=<?php echo $user_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_user_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_user_modal.php"); ?>

<?php include("footer.php");