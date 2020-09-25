<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-cog mr-2"></i>General Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">

      <div class="form-group">
        <label>API Key</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
          </div>
          <input type="text" class="form-control" name="config_api_key" placeholder="No spaces only numbers and letters" value="<?php echo $config_api_key; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>AES Decryption Key</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
          </div>
          <input type="text" class="form-control" name="config_aes_key" placeholder="Key used to decrypt passwords" value="<?php echo $config_aes_key; ?>">
        </div>
        <small class="form-text text-muted">This will also update the key on all client logins</small>
      </div>

      <div class="form-group">
        <label>Base URL</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
          </div>
          <input type="text" class="form-control" name="config_base_url" placeholder="ex host.domain.ext" value="<?php echo $config_base_url; ?>">
        </div>
        <small class="form-text text-muted">This is used by cron to send the correct url for invoice guest views</small>
      </div>
      
      <hr>
      
      <button type="submit" name="edit_general_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");