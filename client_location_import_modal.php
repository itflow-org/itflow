<div class="modal" id="importLocationModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content bg-dark">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt"></i> Import Locations</h5>
				<button type="button" class="close text-white" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
				<input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
				<div class="modal-body bg-white">
					<p><strong>Format csv file with headings & data:</strong><br>Name, Address, City, State, Postal Code, Phone, Hours</p>
					<hr>
					<div class="form-group my-4">
						<input type="file" class="form-control-file" name="file" accept=".csv">
					</div>
					<hr>
					<div>Download <a href="post.php?download_client_locations_csv_template=<?php echo $client_id; ?>">sample csv template</a></div>
				</div>
				<div class="modal-footer bg-white">
					<button type="submit" name="import_client_locations_csv" class="btn btn-primary">Import</button>
				</div>
			</form>
		</div>
	</div>
</div>