<div class="modal" id="addVendorModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i>New Vendor</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-basic-tab" data-toggle="pill" href="#pills-basic" role="tab" aria-controls="pills-basic" aria-selected="true">Basic</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-address-tab" data-toggle="pill" href="#pills-address" role="tab" aria-controls="pills-address" aria-selected="false">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-contact-tab" data-toggle="pill" href="#pills-contact" role="tab" aria-controls="pills-contact" aria-selected="false">Contact</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade show active" id="pills-basic" role="tabpanel" aria-labelledby="pills-basic-tab">

              <div class="form-group">
                <label>Name</label>
                <input type="text" class="form-control" name="name" required autofocus>
              </div>
              
              <div class="form-group">
                <label>Description</label>
                <input type="text" class="form-control" name="description">
              </div>

              <div class="form-group">
                <label>Account Number</label>
                <input type="text" class="form-control" name="account_number">
              </div>

            </div>

            <div class="tab-pane fade" id="pills-address" role="tabpanel" aria-labelledby="pills-address-tab">

              <div class="form-group">
                <label>Address</label>
                <input type="text" class="form-control" name="address">
              </div>
              
              <div class="form-group">
                <label>City</label>
                <input type="text" class="form-control" name="city">
              </div>
              
              <div class="form-group">
                <label>State</label>
                <select class="form-control" name="state">
                  <option value="">- State -</option>
                  <?php foreach($states_array as $state_abbr => $state_name) { ?>
                  <option value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                  <?php } ?>
                </select> 
              </div>
              
              <div class="form-group">
                <label>Zip</label>
                <input type="text" class="form-control" name="zip">
              </div>

            </div>
            
            <div class="tab-pane fade" id="pills-contact" role="tabpanel" aria-labelledby="pills-contact-tab">

              <div class="form-group">
                <label>Phone</label>
                <input type="text" class="form-control" name="phone">
              </div>
              
              <div class="form-group">
                <label>Email</label>
                <input type="email" class="form-control" name="email">
              </div>
              
              <div class="form-group">
                <label>Website</label>
                <input type="text" class="form-control" name="website">
              </div>
            
            </div>

          </div>
          
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_vendor" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>