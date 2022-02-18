<div class="modal" id="addSoftwareModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-rocket"></i> New Software</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-login">Login</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">
          
              <div class="form-group">
                <label>Software Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Software name" required autofocus>
                </div>
              </div>

              <div class="form-group">
                <label>Version</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                  </div>
                  <input type="text" class="form-control" name="version" placeholder="Software version">
                </div>
              </div>
              
              <div class="form-group">
                <label>Type <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <select class="form-control select2" name="type" required>
                    <option value="">- Type -</option>
                    <?php foreach($software_types_array as $software_type) { ?>
                    <option><?php echo $software_type; ?></option>
                    <?php } ?>
                  </select> 
                </div>
              </div>
            
              <div class="form-group">
                <label>License Type</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                  </div>
                  <input type="text" class="form-control" name="license_type" placeholder="License type"> 
                </div>
              </div>

              <div class="form-group">
                <label>License</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                  </div>
                  <input type="text" class="form-control" name="license" placeholder="License key"> 
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">
              
              <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>

            </div>
            
            <div class="tab-pane fade" id="pills-login">

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
                  <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Password">
                  <div class="input-group-append">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                  </div>
                </div>
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