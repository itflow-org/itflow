<div class="modal" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>New User</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-user-tab" data-toggle="pill" href="#pills-user">User</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-assign-tab" data-toggle="pill" href="#pills-assign">Assign</a>
            </li>
          </ul>

          <hr>

          <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade show active" id="pills-user">

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
                <label>Email <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                </div>
              </div>

              <div class="form-group">
                <label>Password <strong class="text-danger">*</strong></label>
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

            <div class="tab-pane fade" id="pills-assign">

              <?php
              $sql = mysqli_query($mysqli,"SELECT * FROM companies");

              while($row = mysqli_fetch_array($sql)){
                $company_id = $row['company_id'];
                $company_name = $row['company_name'];
              ?>

                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="company[]" value="<?php echo $company_id; ?>">
                  <label class="form-check-label"><?php echo $company_name; ?></label>
                </div>

              <?php
              }
              ?>

              <div class="form-group">
                <label>Assign a User to a Client</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <select class="form-control select2" name="client">
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

            </div>

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