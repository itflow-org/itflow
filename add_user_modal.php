<div class="modal" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-user-plus mr-2"></i>New User</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="modal-body bg-white">    
          
          <div class="form-group">
            <label>Assign a User to a Client</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" data-live-search="true" name="client">
                <option value="0">No Client Assignment</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM clients"); 
                while($row = mysqli_fetch_array($sql)){
                  $client_id = $row['client_id'];
                  $client_name = $row['client_name'];
                ?>
                  <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Full Name" required autofocus>
            </div>
          </div>

          <div class="form-group">
            <label>Email</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email Address" required>
            </div>
          </div>

          <div class="form-group">
            <label>Password</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="password" class="form-control" name="password" placeholder="Enter a Password" required>
            </div>
          </div>
          <div class="form-group">
            <label>Avatar</label>
            <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_user" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>