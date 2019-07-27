<div class="modal" id="editSoftwareModal<?php echo $software_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-rocket mr-2"></i><?php echo $software_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="software_id" value="<?php echo $software_id; ?>">
        <div class="modal-body bg-white">    
          
          <div class="form-group">
            <label>Application Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Software name" value="<?php echo $software_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" name="type" required>
                <?php foreach($software_types_array as $software_type_select) { ?>
                <option <?php if($software_type == $software_type_select) { echo "selected"; } ?>><?php echo $software_type_select; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
        
          <div class="form-group">
            <label>License</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
              </div>
              <input type="text" class="form-control" name="license" placeholder="License key" value="<?php echo $software_license; ?>" required> 
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_software" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>