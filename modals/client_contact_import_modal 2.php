<div class="modal" id="importContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-users mr-2"></i>Import Contacts</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">
                    <p><strong>Format csv file with headings & data:</strong><br>Name, Title, Department, Email, Phone, Extension, Mobile, Location</p>
                    <hr>
                    <div class="form-group my-4">
                        <input type="file" class="form-control-file" name="file" accept=".csv" required>
                    </div>
                    <hr>
                    <div>Download: <a class="text-bold" href="post.php?download_client_contacts_csv_template=<?php echo $client_id; ?>">sample csv template</a></div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="import_client_contacts_csv" class="btn btn-primary text-strong"><i class="fas fa-upload mr-2"></i>Import</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
