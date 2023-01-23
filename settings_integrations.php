<?php require_once("inc_all_settings.php"); ?>

<div class="card card-dark">
  <div class="card-header py-3">
    <h3 class="card-title"><i class="fa fa-fw fa-puzzle-piece"></i> Integration Settings</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">

      <h4>Client Portal SSO via Microsoft Azure AD</h4>
        <div class="form-group">
        <label>MS Azure OAuth App (Client) ID</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="azure_client_id" placeholder="e721e3b6-01d6-50e8-7f22-c84d951a52e7" value="<?php echo htmlentities($config_azure_client_id); ?>">
        </div>
      </div>

      <div class="form-group">
        <label>MS Azure OAuth Secret</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
          </div>
          <input type="password" class="form-control" name="azure_client_secret" placeholder="Auto-generated from App Registration" value="<?php echo htmlentities($config_azure_client_secret); ?>" autocomplete="new-password">
        </div>
      </div>

      <hr>
      
      <button type="submit" name="edit_integrations_settings" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");
