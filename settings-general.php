<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-building"></i> Company Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>Company Name</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-building"></i></span>
          </div>
          <input type="text" class="form-control" name="name" placeholder="Company Name" required autofocus>
        </div>
      </div>
      
      <div class="form-group">
        <label>Address</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-map-marker"></i></span>
          </div>
          <input type="text" class="form-control" name="address" placeholder="Street Address" required>
        </div>
      </div>

      <div class="form-group">
        <label>City</label>
        <input type="text" class="form-control" name="city" placeholder="City" required>
      </div>

      <div class="form-group">
        <label>State</label>
        <select class="form-control" name="state">
          <option value="">Select a state...</option>
          <?php foreach($states_array as $state_abbr => $state_name) { ?>
          <option value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
          <?php } ?>
        </select> 
      </div>

      <div class="form-group">
        <label>Phone</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-phone"></i></span>
          </div>
          <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" required> 
        </div>
      </div>

      <div class="form-group">
        <label>Website</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-globe"></i></span>
          </div>
          <input type="text" class="form-control" name="site" placeholder="Website address https://" required>
        </div>
      </div>
      
      <div class="form-group mb-5">
        <label>Logo</label>
        <input type="file" class="form-control-file" name="logo">
      </div>
      
      <hr>
      
      <button type="submit" name="add_client_contact" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");