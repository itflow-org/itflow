<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
	<h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt me-2"></i>Import Locations</h5>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
	<input type="hidden" name="client_id" value="<?= $client_id ?>">

	<div class="modal-body">
		<p><strong>Format csv file with headings & data:</strong><br>Name, Description, Address, City, State, Postal Code, Phone, Hours</p>
		<hr>
		<div class="mb-3 my-4">
			<input type="file" class="form-control" name="file" accept=".csv" required>
		</div>
		<hr>
		<div>Download: <a class="text-bold" href="post.php?download_locations_csv_template=<?= $client_id ?>">sample csv template</a></div>
	</div>
	<div class="modal-footer">
		<button type="submit" name="import_locations_csv" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i>Import</button>
		<button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
	</div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
