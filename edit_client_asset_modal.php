<div class="modal" id="editClientAssetModal<?php echo $client_asset_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-tag"></i> Edit Asset</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_asset_id" value="<?php echo $client_asset_id; ?>">
        <div class="modal-body">
          <div class="form-group">
            <label>Asset Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name the asset" value="<?php echo $client_asset_name; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Asset Type</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tags"></i></span>
              </div>
              <select class="form-control" name="type" required>
                <?php foreach($asset_types_array as $asset_type) { ?>
                <option <?php if($client_asset_type == $asset_type) { echo "selected"; } ?>><?php echo $asset_type; ?></option>
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
              <input type="text" class="form-control" name="make" placeholder="Manufacturer" value="<?php echo $client_asset_make; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Model</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" name="model" placeholder="Model Number" value="<?php echo $client_asset_model; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Serial Number</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-barcode"></i></span>
              </div>
              <input type="text" class="form-control" name="serial" placeholder="Serial number" value="<?php echo $client_asset_serial; ?>" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client_asset" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>