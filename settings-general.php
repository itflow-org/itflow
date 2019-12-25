<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-cog mr-2"></i>General Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">

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
        <label>Base URL</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
          </div>
          <input type="text" class="form-control" name="config_base_url" placeholder="ex host.domain.ext" value="<?php echo $config_base_url; ?>">
        </div>
        <small class="form-text text-muted">This is used by cron to send the correct url for invoice guest views</small>
      </div>

      <div class="form-group mb-4">
        <label>Logo</label>
        <input type="file" class="form-control-file" name="file">
      </div>

      <div class="card col-md-2">
        <div class="card-body">
          <img class="img-fluid" src="<?php echo $config_invoice_logo; ?>">
        </div>
        
      </div>
      
      <hr>
      
      <button type="submit" name="edit_general_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");