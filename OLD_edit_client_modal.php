<div class="modal" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-user-edit mr-2"></i>Edit <?php echo $client_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <input type="hidden" name="client_id" value="<?php echo $client_id; ?>" >
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Name</label>
            <div class="col-sm-10">
              <input type="text" class="form-control" name="name" value="<?php echo $client_name; ?>" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Phone</label>
            <div class="col-sm-10">
              <input type="text" class="form-control" name="phone" data-inputmask="'mask': '999-999-9999'" value="<?php echo $client_phone; ?>" required> 
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Email</label>
            <div class="col-sm-10">
              <input type="email" class="form-control" name="email" value="<?php echo $client_email; ?>" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Website</label>
            <div class="col-sm-10"> 
              <input type="text" class="form-control" name="website" value="<?php echo $client_website; ?>">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Address</label>
            <div class="col-sm-10">
              <input type="text" class="form-control" name="address" value="<?php echo $client_address; ?>" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">City</label>
            <div class="col-sm-10">
              <input type="text" class="form-control" name="city" value="<?php echo $client_city; ?>" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">State</label>
            <div class="col-sm-10">
              <select class="form-control" name="state" required>
                <option value="">- State -</option>
                  <?php 
                  foreach($states_array as $state_abbr => $state_name){ 
                  ?>
                  <option <?php if($client_state == $state_abbr) { echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                  <?php } ?>
              </select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-2 col-form-label">Zip</label>
            <div class="col-sm-10">
              <input type="text" class="form-control" name="zip" value="<?php echo $client_zip; ?>" required>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>