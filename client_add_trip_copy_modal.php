<div class="modal" id="addTripCopyModal<?php echo $trip_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-bicycle mr-2"></i>Copy Trip</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">

          <div class="form-row">
            
            <div class="form-group col">
              <label>Date <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" required>
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
              <input type="text" class="form-control" name="source" value="<?php echo $trip_source; ?>" required>
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
            <input type="checkbox" class="custom-control-input" id="customControlAutosizingCopy<?php echo $trip_id; ?>" name="roundtrip" value="1" >
            <label class="custom-control-label" for="customControlAutosizingCopy<?php echo $trip_id; ?>">Round Trip</label>
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