<div class="modal" id="importContactModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content bg-dark">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa fa-fw fa-users"></i> Import Contacts via CSV</h5>
				<button type="button" class="close text-white" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
				<input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
				<div class="modal-body bg-white">
					<p><strong>Format CSV file with headings & data:</strong><br>Name, Title, Department, Email, Phone, Extension, Mobile, Location</p>
					<hr>
					<div class="form-group my-4">
						<input type="file" class="form-control-file" name="file" accept=".csv">
					</div>
					<hr>
					<div>Download <a href="post.php?download_client_contacts_csv_template=<?php echo $client_id; ?>">sample CSV template</a></div>
				</div>
				<div class="modal-footer bg-white">
					<button type="submit" name="import_client_contacts_csv" class="btn btn-primary">Upload</button>
				</div>
			</form>
		</div>
	</div>
</div>