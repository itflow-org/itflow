<div class="modal" id="addClientModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-user-plus mr-2"></i>New Client</h5>
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
            <li class="nav-item">
              <a class="nav-link" id="pills-contact-tab" data-toggle="pill" href="#pills-more" role="tab" aria-controls="pills-more" aria-selected="false">More</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade show active" id="pills-basic" role="tabpanel" aria-labelledby="pills-basic-tab">

              <div class="form-group">
                <label>Name</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name or Company" required autofocus>
                </div>
              </div>

            </div>
          
            <div class="tab-pane fade" id="pills-address" role="tabpanel" aria-labelledby="pills-address-tab">

              <div class="form-group">
                <label>Address</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="address" placeholder="Address">
                </div>
              </div>
          
              <div class="form-group">
                <label>City</label>
                <input type="text" class="form-control" name="city" placeholder="City" required>
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
                <input type="text" class="form-control" name="zip" placeholder="Zip">
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-contact" role="tabpanel" aria-labelledby="pills-contact-tab">

              <div class="form-group">
                <label>Phone</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                  </div>
                  <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'"> 
                </div>
              </div>
              
              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email Address">
                </div>
              </div>
        
              <div class="form-group">
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="text" class="form-control" name="website" placeholder="Web Address">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-more" role="tabpanel" aria-labelledby="pills-more-tab">

              <div class="form-group">
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
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>