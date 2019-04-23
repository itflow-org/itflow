<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-file"></i> Invoice Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off"> 
      
      <div class="form-group">
        <label>Next Number</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-barcode"></i></span>
          </div>
          <input type="text" class="form-control" name="next_number" placeholder="Next Invoice Number" required autofocus>
        </div>
      </div>
      
      <div class="form-group">
        <label>Email From</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
          </div>
          <input type="email" class="form-control" name="email" placeholder="Email Address" required>
        </div>
      </div>

      <div class="form-group">
        <label>Email Name From</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="name_from" placeholder="Name" required>
        </div>
      </div>

      <div class="form-group">
        <label>Send Overdue Reminders</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="name_from" placeholder="Name" required>
        </div>
      </div>
      
      <div class="form-group mb-5">
        <label>Logo</label>
        <input type="file" class="form-control-file" name="logo">
      </div>
      
      <hr>
      <button type="submit" name="edit_settings_invoice" class="btn btn-primary">Save</button>        
    
    </form>
  </div>
</div>

<?php include("footer.php");