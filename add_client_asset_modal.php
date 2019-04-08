<div class="modal" id="addClientAssetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-tag"></i> New Asset</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body">
          <div class="form-group">
            <label>Asset Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name the asset" required autofocus>
            </div>
          </div>
          <div class="form-group">
            <label>Asset Type</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tags"></i></span>
              </div>
              <select class="form-control" name="type" required>
                <option value="">- Type -</option>
                <?php foreach($asset_types_array as $asset_type) { ?>
                <option><?php echo $asset_type; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>        
          <div class="form-group">
            <label>Make</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" name="make" placeholder="Manufacturer" required>
            </div>
          </div>
          <div class="form-group">
            <label>Model</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" name="model" placeholder="Model Number" required>
            </div>
          </div>
          <div class="form-group">
            <label>Serial Number</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-barcode"></i></span>
              </div>
              <input type="text" class="form-control" name="serial" placeholder="Serial number" required>
            </div>
          </div>
          <center><a class="btn btn-link" data-toggle="collapse" href="#optionsCollapse" role="button" aria-expanded="false" aria-controls="optionsCollapse">Add a pasword</a></center>
          <div class="collapse multi-collapse" id="optionsCollapse">
            <div class="form-group">
              <label>Username</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-user"></i></span>
                </div>
                <input type="text" class="form-control" name="username">
              </div>
            </div>
            <div class="form-group">
              <label>Password</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-lock"></i></span>
                </div>
                <input type="text" class="form-control" name="password">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client_asset" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>