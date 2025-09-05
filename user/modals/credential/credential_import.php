<div class="modal" id="importCredentialModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-dark">
				<h5 class="modal-title"><i class="fas fa-fw fa-key mr-2"></i>Import Credentials</h5>
				<button type="button" class="close text-white" data-dismiss="modal">
					<span>&times;</span>
				</button>
			</div>
			<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
				<?php if ($client_url) { ?>
				<input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
				<?php } ?>
				<div class="modal-body">
					<p><strong>Format csv file with headings & data:</strong><br>Name, Description, Username, Password, URL</p>
					<hr>
					<div class="form-group my-4">
						<input type="file" class="form-control-file" name="file" accept=".csv" required>
					</div>
					<hr>
					<div>Download <a class="text-bold" href="post.php?download_credentials_csv_template">sample csv template</a></div>
				</div>
				<div class="modal-footer">
					<button type="submit" name="import_credentials_csv" class="btn btn-primary"><i class="fa fa-upload mr-2"></i>Import</button>
					<button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>