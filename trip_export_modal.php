<div class="modal" id="exportTripsModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content bg-dark">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa fa-fw fa-download"></i> Export Trips to CSV</h5>
				<button type="button" class="close text-white" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<form action="post.php" method="post" autocomplete="off">
				<div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Date From</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="date_from" max="2999-12-31" value="<?php echo $dtf; ?>">
            </div>
          </div>
   
          <div class="form-group">
            <label>Date To</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="date_to" max="2999-12-31" value="<?php echo $dtt; ?>">
            </div>
          </div>
          
				</div>
				<div class="modal-footer bg-white">
					<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
					<button type="submit" name="export_trips_csv" class="btn btn-primary text-bold"><i class="fa fa-fw fa-download"></i> Download CSV</button>
				</div>
			</form>
		</div>
	</div>
</div>