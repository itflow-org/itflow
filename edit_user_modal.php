<div class="modal" id="editUserModal<?php echo $user_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-user"></i> <?php echo $name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
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
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo $name; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Email <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $email; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Password <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="password" class="form-control" name="password" placeholder="Enter a password" value="<?php echo $password; ?>" autocomplete="new-password" required>
            </div>
          </div>

          <div class="form-group">
            <label>Company <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
              </div>
              <select class="form-control select2" name="company" required>
                <option value="">- Company -</option>
                <?php 
                
                $sql_companies_select = mysqli_query($mysqli,"SELECT * FROM companies ORDER BY company_name ASC"); 
                while($row = mysqli_fetch_array($sql_companies_select)){
                  $company_id_select = $row['company_id'];
                  $company_name_select = $row['company_name'];
                ?>
                  <option <?php if($company_id_select == $permission_default_company){ echo "selected"; } ?> value="<?php echo $company_id_select; ?>"><?php echo $company_name_select; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Permission <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
              </div>
              <select class="form-control select2" name="level" required>
                <option value="">- Permission -</option>
                <option <?php if($permission_level == 5){ echo "selected"; } ?> value="5">Global Admininstrator</option>
                <option <?php if($permission_level == 4){ echo "selected"; } ?> value="4">Administrator</option>
                <option <?php if($permission_level == 3){ echo "selected"; } ?> value="3">Technician</option>
                <option <?php if($permission_level == 2){ echo "selected"; } ?> value="2">IT Contractor</option>
                <option <?php if($permission_level == 1){ echo "selected"; } ?> value="1">Accounting</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Avatar</label>
            <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
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