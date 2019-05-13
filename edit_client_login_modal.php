<div class="modal" id="editClientLoginModal<?php echo $client_login_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-lock mr-2"></i>Edit Login</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_login_id" value="<?php echo $client_login_id; ?>">
        <div class="modal-body bg-white">  
          <div class="form-group">
            <label>Description</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-info-circle"></i></span>
              </div>
              <input type="text" class="form-control" name="description" value="<?php echo $client_login_description; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Web Link</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-link"></i></span>
              </div>
              <input type="text" class="form-control" name="web_link" placeholder="Please include http://" value="<?php echo $client_login_web_link; ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Username</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="username" value="<?php echo $client_login_username; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Password</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
              </div>
              <input type="text" class="form-control" name="password" value="<?php echo $client_login_password; ?>" required>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client_login" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>