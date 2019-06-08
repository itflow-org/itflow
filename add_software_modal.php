<div class="modal" id="addSoftwareModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-rocket mr-2"></i>New Software</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">    
          
          <div class="form-group">
            <label>Software Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Software name" required autofocus>
            </div>
          </div>
          
          <div class="form-group">
            <label>Type</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tag"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" name="type" required>
                <option value="">- Type -</option>
                <?php foreach($software_types_array as $software_type) { ?>
                <option><?php echo $software_type; ?></option>
                <?php } ?>
              </select> 
            </div>
          </div>
        
          <div class="form-group">
            <label>License</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
              </div>
              <input type="text" class="form-control" name="license" placeholder="License key" required> 
            </div>
          </div>

          <center><a class="btn btn-link" data-toggle="collapse" href="#optionsCollapse" role="button" aria-expanded="false" aria-controls="optionsCollapse">Add a pasword</a></center>
          <div class="collapse multi-collapse" id="optionsCollapse">
            <div class="form-group">
              <label>Username</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <input type="text" class="form-control" name="username" placeholder="Username">
              </div>
            </div>
            <div class="form-group">
              <label>Password</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                </div>
                <input type="text" class="form-control" name="password" placeholder="Password">
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_software" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>