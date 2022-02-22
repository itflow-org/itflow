<?php include("inc_all.php"); ?>

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
          <input type="password" class="form-control" data-toggle="password" name="config_smtp_password" placeholder="Password" value="<?php echo $config_smtp_password; ?>" required>
          <div class="input-group-append">
            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
          </div>
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

<?php if(!empty($config_smtp_host) AND !empty($config_smtp_port) AND !empty($config_smtp_username) AND  !empty($config_smtp_password) AND !empty($config_mail_from_email) AND !empty($config_mail_from_name)){ ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-paper-plane"></i> Test Email</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">
      <div class="input-group">
        <input type="email" class="form-control " name="email" placeholder="Email address to test">
        <div class="input-group-append">
          <button type="submit" name="test_email" class="btn btn-success"><i class="fa fa-fw fa-paper-plane"></i> Send</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php } ?>

<?php include("footer.php");