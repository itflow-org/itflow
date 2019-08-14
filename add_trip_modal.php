<div class="modal" id="addTripModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-bicycle mr-2"></i>New Trip</h5>
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
              <a class="nav-link" id="pills-link-tab" data-toggle="pill" href="#pills-link" role="tab" aria-controls="pills-link" aria-selected="false">Link</a>
            </li>
          </ul>

          <hr>

          <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade show active" id="pills-basic" role="tabpanel" aria-labelledby="pills-basic-tab">

              <div class="form-row">
                <div class="form-group col">
                  <label>Date <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    </div>
                    <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
                  </div>
                </div>
                <div class="form-group col">
                  <label>Miles <strong class="text-danger">*</strong></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-bicycle"></i></span>
                    </div>
                    <input type="number" class="form-control" name="miles" placeholder="Enter miles" required autofocus>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label>Location <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="starting_location" placeholder="Enter your starting location" required>
                </div>
              </div>
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-arrow-right"></i></span>
                  </div>
                  <input type="text" class="form-control" name="destination" placeholder="Enter your destination" required>
                </div>
              </div>
              <div class="form-group">
                <label>Purpose <strong class="text-danger">*</strong></label>
                <textarea rows="4" class="form-control" name="purpose" required></textarea>
              </div>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="roundtrip" value="1" >
                <label class="custom-control-label" for="customControlAutosizing">Roundtrip</label>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-link" role="tabpanel" aria-labelledby="pills-link-tab">

              <div class="form-group">
                <label>Invoice</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-file"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="invoice">
                    <option value="">- Invoice -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM clients, invoices WHERE invoices.client_id = clients.client_id AND invoices.company_id = $session_company_id ORDER BY invoice_number DESC"); 
                    while($row = mysqli_fetch_array($sql)){
                      $client_id = $row['client_id'];
                      $client_name = $row['client_name'];
                      $invoice_id = $row['invoice_id'];
                      $invoice_number = $row['invoice_number'];
                      $invoice_status = $row['invoice_status'];

                    ?>
                      <option value="<?php echo $invoice_id; ?>"><?php echo "$invoice_number - $invoice_status - $client_name"; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Client</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="client">
                    <option value="">- Client -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE company_id = $session_company_id"); 
                    while($row = mysqli_fetch_array($sql)){
                      $client_id = $row['client_id'];
                      $client_name = $row['client_name'];
                    ?>
                      <option <?php if($_GET['client_id'] == $client_id) { echo "selected"; } ?> value="<?php echo "$client_id"; ?>"><?php echo "$client_name"; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Location</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="location">
                    <option value="">- Location -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM locations, clients WHERE locations.client_id = clients.client_id AND locations.company_id = $session_company_id ORDER BY clients.client_id DESC");
                    while($row = mysqli_fetch_array($sql)){
                      $location_id = $row['location_id'];
                      $location_name = $row['location_name'];
                      $client_name = $row['client_name'];
                    ?>
                    <option value="<?php echo $location_id; ?>"><?php echo "$client_name - $location_name"; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Vendor</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="vendor">
                    <option value="">- Vendor -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = 0 AND company_id = $session_company_id ORDER BY vendor_name ASC"); 
                    while($row = mysqli_fetch_array($sql)){
                      $vendor_id = $row['vendor_id'];
                      $vendor_name = $row['vendor_name'];
                    ?>
                    <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_trip" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>