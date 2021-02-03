<div class="modal" id="addTripModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-bicycle mr-2"></i>New Trip</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
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
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>
            <div class="form-group col">
              <label>Miles <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-bicycle"></i></span>
                </div>
                <input type="number" step="0.1" min="0" class="form-control" name="miles" placeholder="Enter miles" required autofocus>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Location <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="source" placeholder="Enter your starting location" required>
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
            <label class="custom-control-label" for="customControlAutosizing">Round Trip</label>
          </div>
        
        </div>
        
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_trip" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div> <!-- Modal Content -->
  </div> <!-- Modal Dialog -->
</div> <!-- Modal -->