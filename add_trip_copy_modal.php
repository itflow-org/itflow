<div class="modal" id="addTripCopyModal<?php echo $trip_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-route mr-2"></i>Copy Trip</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tabCopy<?php echo $trip_id; ?>">
            <li class="nav-item">
              <a class="nav-link active" id="pills-basic-tabCopy<?php echo $trip_id; ?>" data-toggle="pill" href="#pills-basicCopy<?php echo $trip_id; ?>">Basic</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-link-tabCopy<?php echo $trip_id; ?>" data-toggle="pill" href="#pills-linkCopy<?php echo $trip_id; ?>" role="tab" aria-controls="pills-link" aria-selected="false">Link</a>
            </li>
          </ul>

          <hr>
      
          <div class="tab-content" id="pills-tabContentCopy<?php echo $trip_id; ?>">

            <div class="tab-pane fade show active" id="pills-basicCopy<?php echo $trip_id; ?>" role="tabpanel" aria-labelledby="pills-basic-tab">

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
                    <input type="number" step="0.1" min="0" class="form-control" name="miles" value="<?php echo $trip_miles; ?>" required>
                  </div>
                </div>
              
              </div>

              <div class="form-group">
                <label>Location <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="starting_location" value="<?php echo $trip_starting_location; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-arrow-right"></i></span>
                  </div>
                  <input type="text" class="form-control" name="destination" value="<?php echo $trip_destination; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Purpose <strong class="text-danger">*</strong></label>
                <textarea rows="4" class="form-control" name="purpose" required><?php echo $trip_purpose; ?></textarea>
              </div>

              <div class="custom-control custom-checkbox">
                <input type="checkbox" <?php if($round_trip == 1){ echo "checked"; } ?> class="custom-control-input" id="customControlAutosizing<?php echo $trip_id; ?>" name="roundtrip" value="1" >
                <label class="custom-control-label" for="customControlAutosizingCopy<?php echo $trip_id; ?>">Round Trip</label>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-linkCopy<?php echo $trip_id; ?>" role="tabpanel" aria-labelledby="pills-link-tab">

              <div class="form-group">
                <label>Client</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <select class="form-control select2" name="client">
                    <option value="">- Client -</option>
                    <?php 
                    
                    $sql_clients = mysqli_query($mysqli,"SELECT * FROM clients WHERE company_id = $session_company_id ORDER BY client_name ASC"); 
                    while($row = mysqli_fetch_array($sql_clients)){
                      $client_id_select = $row['client_id'];
                      $client_name_select = $row['client_name'];
                    ?>
                      <option <?php if($client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo $client_name_select; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
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