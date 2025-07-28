<div class="modal" id="importAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>Import Assets</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                
                <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <div class="modal-body bg-white">
                    <p><strong>Format csv file with headings & data:</strong><br>Name, Description, Type, Make, Model, Serial, OS, Purchase Date, Assigned To, Location, Physical Location</p>
                    <hr>
                    <div class="form-group my-4">
                        <input type="file" class="form-control-file" name="file" accept=".csv" required>
                    </div>
                    <hr>
                    <div>Download <a href="post.php?download_assets_csv_template=<?php echo $client_id; ?>">sample csv template</a></div>
                    <small class="text-muted">Note: Purchase date must be in the format YYYY-MM-DD. Spreadsheet tools may automatically reformat dates.</small>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="import_assets_csv" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
