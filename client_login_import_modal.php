<div class="modal" id="importLoginModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content bg-dark">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa fa-fw fa-key"></i> Import Logins</h5>
				<button type="button" class="close text-white" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
				<input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
				<div class="modal-body bg-white">
					<p><strong>Format csv file with headings & data:</strong><br>Name, Username, Password, URL</p>
					<hr>
					<div class="form-group my-4">
						<input type="file" class="form-control-file" name="file" accept=".csv">
					</div>
					<hr>
					<div>Download <a href="post.php?download_client_logins_csv_template=<?php echo $client_id; ?>">sample csv template</a></div>
				</div>
				<div class="modal-footer bg-white">
					<button type="submit" name="import_client_logins_csv" class="btn btn-primary">Import</button>
				</div>
			</form>
		</div>
	</div>
</div>