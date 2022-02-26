<?php include("inc_all_admin.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-cog"></i> General Settings</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">

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

      <div class="form-group">
        <label>MeshCentral URI & Port</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
          </div>
          <input type="text" class="form-control" name="meshcentral_uri" placeholder="Format: wss://mesh.itflow.org:443" value="<?php echo $config_meshcentral_uri; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>MeshCentral Token User</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="meshcentral_user" placeholder="~t:ABCDEF" value="<?php echo $config_meshcentral_user; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>MeshCentral Token Password</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-keyboard"></i></span>
          </div>
          <input type="password" class="form-control" name="meshcentral_secret" placeholder="Auto-generated on MeshCentral" value="<?php echo $config_meshcentral_secret; ?>">
        </div>
      </div>

      <div class="form-group">
        <div class="alert alert-warning" role="alert">
          This token/user only requires <b>read access</b> to MeshCentral and is stored in <b>plaintext</b>.
        </div>
      </div>

      <hr>
      
      <button type="submit" name="edit_general_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");