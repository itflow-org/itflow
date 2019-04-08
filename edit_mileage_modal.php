<div class="modal" id="editMileageModal<?php echo $mileage_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-car-side"></i> Modify Mileage</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="mileage_id" value="<?php echo $mileage_id; ?>">
          <div class="form-row">
            <div class="form-group col-7">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo $mileage_date; ?>" required>
              </div>
            </div>
            <div class="form-group col-5">
              <label>Miles</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-car-side"></i></span>
                </div>
                <input type="number" class="form-control" name="miles" value="<?php echo $mileage_miles; ?>" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Starting Location</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-map-marker"></i></span>
              </div>
              <input type="text" class="form-control" name="starting_location" value="<?php echo $mileage_starting_location; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Destination</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-arrow-right"></i></span>
              </div>
              <input type="text" class="form-control" name="destination" value="<?php echo $mileage_destination; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Purpose</label>
            <textarea rows="4" class="form-control" name="purpose" required><?php echo $mileage_purpose; ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_mileage" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>