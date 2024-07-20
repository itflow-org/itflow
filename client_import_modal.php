<div class="modal" id="importClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-users mr-2"></i>Import Clients</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">
                    <p><strong>Format csv file with headings & data:</strong><br>Client Name, Industry, Referral, Website, Location Name, Location Phone, Location Address, City, State, Postal Code, Country, Contact Name, Title, Contact Phone, Extension, Contact Mobile, Contact Email, Hourly Rate, Currency, Payment Terms, Tax ID, Abbreviation</p>
                    <hr>
                    <div class="form-group my-4">
                        <input type="file" class="form-control-file" name="file" accept=".csv">
                    </div>
                    <hr>
                    <div>Download: <a class="text-bold" href="post.php?download_clients_csv_template">sample csv template</a></div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="import_clients_csv" class="btn btn-primary text-strong"><i class="fas fa-upload mr-2"></i>Import</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
