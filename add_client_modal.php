<div class="modal" id="addClientModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-user-plus mr-2"></i>New Client</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name or Company" required autofocus>
            </div>
          </div>
          <div class="form-group">
            <label>Address</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="address" placeholder="Address">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-5">
              <label>City</label>
              <input type="text" class="form-control" name="city" placeholder="City" required>
            </div>
            <div class="form-group col-md-4">
              <label>State</label>
              <select class="form-control" name="state">
                <option value="">Select a state...</option>
                <?php foreach($states_array as $state_abbr => $state_name) { ?>
                <option value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                <?php } ?>
              </select> 
            </div>
            <div class="form-group col-md-3">
              <label>Zip</label>
              <input type="text" class="form-control" name="zip" placeholder="Zip">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-sm">
              <label>Phone</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                </div>
                <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'"> 
              </div>
            </div>
            <div class="form-group col-sm">
              <label>Email</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                </div>
                <input type="email" class="form-control" name="email" placeholder="Email Address">
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col">
              <label>Website</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                </div>
                <input type="text" class="form-control" name="website" placeholder="Web Address">
              </div>
            </div>
            <div class="form-group col">
              <label>Invoice Net Terms</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                </div>
                <select class="form-control" name="net_terms">
                  <option value="7">Default (7 Days)</option>
                  <option value="1">Upon Reciept</option>
                  <option value="14">14 Day</option>
                  <option value="30">30 Day</option>
                </select> 
              </div>
            </div>
          </div>    
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>