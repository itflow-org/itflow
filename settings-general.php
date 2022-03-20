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

      <h4>Mesh Central Asset Integration</h4>

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
          <input type="password" class="form-control" name="meshcentral_secret" placeholder="Auto-generated on MeshCentral" value="<?php echo $config_meshcentral_secret; ?>" autocomplete="new-password">
        </div>
      </div>

      <div class="form-group">
        <div class="alert alert-warning">
          This token/user only requires <b>read access</b> to MeshCentral and is stored in <b>plaintext</b>.
        </div>
      </div>

      <hr>

      <h4>Client Portal SSO via Microsoft Azure AD</h4>
      <div class="form-group">
        <label>MS Azure OAuth App (Client) ID</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="azure_client_id" placeholder="e721e3b6-01d6-50e8-7f22-c84d951a52e7" value="<?php echo $config_azure_client_id; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>MS Azure OAuth Secret</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
          </div>
          <input type="password" class="form-control" name="azure_client_secret" placeholder="Auto-generated from App Registration" value="<?php echo $config_azure_client_secret; ?>" autocomplete="new-password">
        </div>
      </div>

      <hr>
      
      <button type="submit" name="edit_general_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");