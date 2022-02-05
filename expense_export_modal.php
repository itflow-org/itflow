<div class="modal" id="exportExpensesModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content bg-dark">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa fa-fw fa-download"></i> Export Expenses to CSV</h5>
				<button type="button" class="close text-white" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<form action="post.php" method="post" autocomplete="off">
				<div class="modal-body bg-white">
          <div class="form-group">
            <label>Date From</label>
            <input type="date" class="form-control" name="date_from" value="<?php echo $dtf; ?>">
          </div>
   
          <div class="form-group">
            <label>Date To</label>
            <input type="date" class="form-control" name="date_to" value="<?php echo $dtt; ?>">
          </div>
          
				</div>
				<div class="modal-footer bg-white">
					<button type="submit" name="export_expenses_csv" class="btn btn-primary">Download CSV</button>
				</div>
			</form>
		</div>
	</div>
</div>