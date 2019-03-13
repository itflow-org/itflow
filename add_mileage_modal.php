<div class="modal fade" id="addMileageModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-car"></i> Add Mileage</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Date</label>
            <input type="date" class="form-control" name="date" required autofocus="autofocus">
          </div>
          <div class="form-group">
            <label>Starting Location</label>
            <input type="text" class="form-control" name="starting_location"> 
          </div>
          <div class="form-group">
            <label>Destination</label>
            <input type="text" class="form-control" name="destination">
          </div>
          <div class="form-group">
            <label>Miles</label>
            <input type="text" class="form-control" name="miles">
          </div>
          <div class="form-group">
            <label>Purpose</label>
            <textarea rows="4" class="form-control" name="purpose"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_mileage" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>