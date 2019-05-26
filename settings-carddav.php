<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-address-book mr-2"></i>CardDAV Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>Server</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
          </div>
          <input type="text" class="form-control" name="config_smtp_host" placeholder="CardDAV Server Address" value="<?php echo $config_carddav_server; ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label>Addressbook</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-address-book"></i></span>
          </div>
          <input type="text" class="form-control" name="config_carddav_username" placeholder="Address book name" value="<?php echo $config_carddav_address_book; ?>" required>
        </div>
      </div>
      
      <div class="form-group">
        <label>Username</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="config_carddav_username" placeholder="Username" value="<?php echo $config_carddav_username; ?>" required>
        </div>
      </div>

      <div class="form-group mb-5">
        <label>Password</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
          </div>
          <input type="password" class="form-control" name="config_carddav_password" placeholder="Password" value="<?php echo $config_carddav_password; ?>" required>
        </div>
      </div>

      <hr>
      <button type="submit" name="edit_carddav_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");