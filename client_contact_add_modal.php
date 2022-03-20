<div class="modal" id="addContactModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="far fa-fw fa-address-card"></i> New Contact</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">   
          
          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-photo">Photo</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details"> 
          
              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Full Name" required autofocus>
                </div>
              </div>
              
              <div class="form-group">
                <label>Title / Primary Contact</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                  </div>
                  <input type="text" class="form-control" name="title" placeholder="Title">
                  <div class="input-group-append">
                    <div class="input-group-text">
                      <input type="checkbox" name="primary_contact" value="1" <?php if($primary_contact == 0){ echo "checked"; } ?>>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Department</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <select class="form-control select2" name="department">
                    <option value="">- Department -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM departments WHERE department_archived_at IS NULL AND department_client_id = $client_id ORDER BY department_name ASC"); 
                    while($row = mysqli_fetch_array($sql)){
                      $department_id = $row['department_id'];
                      $department_name = $row['department_name'];
                    ?>
                    <option value="<?php echo $department_id; ?>"><?php echo $department_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
            
              <label>Phone</label>
              <div class="form-row">
                <div class="col-8">
                  <div class="form-group">
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                      </div>
                      <input type="text" class="form-control" name="phone" placeholder="Phone Number"> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="extension" placeholder="Extension">
                </div>
              </div>

              <div class="form-group">
                <label>Mobile</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="mobile" placeholder="Mobile Phone Number"> 
                </div>
              </div>

              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email Address">
                </div>
              </div>

              <div class="form-group">
                <label>Location</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <select class="form-control select2" name="location">
                    <option value="">- Location -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC"); 
                    while($row = mysqli_fetch_array($sql)){
                      $location_id = $row['location_id'];
                      $location_name = $row['location_name'];
                    ?>
                    <option value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Portal Login</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                  </div>
                  <select class="form-control select2" name="auth_method">
                    <option value="">- None -</option>
                    <option value="local">Local</option>
                    <option value="azure">Azure</option>
                  </select>
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-photo">
              
              <div class="form-group">
                <label>Upload Photo</label>
                <input type="file" class="form-control-file" name="file">
              </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">
              
              <div class="form-group">
                <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"></textarea>
              </div>
            
            </div>
          
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_contact" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
