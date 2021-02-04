<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-envelope"></i> Mail Settings</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">
      
      <div class="form-group">
        <label>SMTP Host</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
          </div>
          <input type="text" class="form-control" name="config_smtp_host" placeholder="Mail Server Address" value="<?php echo $config_smtp_host; ?>" required>
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

      <div class="form-group">
        <label>SMTP Password</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
          </div>
          <input type="password" class="form-control" name="config_smtp_password" placeholder="Password" value="<?php echo $config_smtp_password; ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
          </div>
          <input type="email" class="form-control" name="config_mail_from_email" placeholder="Email Address" value="<?php echo $config_mail_from_email; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Name</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
          </div>
          <input type="text" class="form-control" name="config_mail_from_name" placeholder="Name" value="<?php echo $config_mail_from_name; ?>">
        </div>
      </div>

      <hr>
      
      <button type="submit" name="edit_mail_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");