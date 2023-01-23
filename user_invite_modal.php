<div class="modal" id="userInviteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-user-plus"></i> Invite User</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
        <div class="modal-body bg-white">

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
            <label>Company <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
              </div>
              <select class="form-control select2" name="default_company" required>
                <option value="">- Company -</option>
                <?php 
                
                $sql_companies_select = mysqli_query($mysqli,"SELECT * FROM companies ORDER BY company_name ASC"); 
                while ($row = mysqli_fetch_array($sql_companies_select)) {
                  $company_id = $row['company_id'];
                  $company_name = htmlentities($row['company_name']);
                ?>
                  <option value="<?php echo $company_id; ?>"><?php echo $company_name; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Role <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
              </div>
              <select class="form-control select2" name="role" required>
                <option value="">- Role -</option>
                <option value="3">Administrator</option>
                <option value="2">Technician</option>
                <option value="1">Accountant</option>
              </select>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="invite_user" class="btn btn-primary"><strong><i class="fas fa-paper-plane"></i> Send Invite</strong></button>
        </div>
      </form>
    </div>
  </div>
</div>