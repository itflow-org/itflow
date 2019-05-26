<div class="modal" id="editUserModal<?php echo $user_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-user-edit mr-2"></i><?php echo $name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
        <input type="hidden" name="current_password_hash" value="<?php echo $password; ?>">
        <input type="hidden" name="current_avatar_path" value="<?php echo $avatar; ?>">
        <div class="modal-body bg-white">    
          <center class="mb-3">
            <?php if(!empty($avatar)){ ?>
            <img class="img-fluid rounded-circle" src="<?php echo $avatar; ?>" height="128" width="128">
            <?php }else{ ?>
            <span class="fa-stack fa-4x">
              <i class="fa fa-circle fa-stack-2x text-secondary"></i>
              <span class="fa fa-stack-1x text-white"><?php echo $initials; ?></span>
            </span>
            <?php } ?>
          </center>
          <div class="form-group">
            <label>Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo $name; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Email</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $email; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Password</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="password" class="form-control" name="password" placeholder="Enter a password" value="<?php echo $password; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Avatar</label>
            <input type="file" class="form-control-file" accept="image/*;capture=camera" name="avatar">
          </div>         
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_user" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>