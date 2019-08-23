<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-envelope mr-2"></i>Mail Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>SMTP Host</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
          </div>
          <input type="text" class="form-control" name="config_smtp_host" placeholder="Mail Server Address" value="<?php echo $config_smtp_host; ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label>SMTP Port</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
          </div>
          <input type="number" min="0" class="form-control" name="config_smtp_port" placeholder="Mail Server Port Number" value="<?php echo $config_smtp_port; ?>" required>
        </div>
      </div>
      
      <div class="form-group">
        <label>SMTP Username</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="config_smtp_username" placeholder="Username" value="<?php echo $config_smtp_username; ?>" required>
        </div>
      </div>

      <div class="form-group mb-5">
        <label>SMTP Password</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
          </div>
          <input type="password" class="form-control" name="config_smtp_password" placeholder="Password" value="<?php echo $config_smtp_password; ?>" required>
        </div>
      </div>

      <hr>
      <button type="submit" name="edit_mail_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");