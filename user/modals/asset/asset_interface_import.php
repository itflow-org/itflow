<div class="modal" id="importAssetInterfaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-ethernet mr-2"></i>Import Interfaces</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
                <div class="modal-body">
                    <p><strong>Format csv file with headings & data:</strong><br>Name, Description, Type, MAC, IP, NAT IP, IPv6, Network</p>
                    <hr>
                    <div class="form-group my-4">
                        <input type="file" class="form-control-file" name="file" accept=".csv" required>
                    </div>
                    <hr>
                    <div>Download <a href="post.php?download_client_asset_interfaces_csv_template=<?php echo $asset_id; ?>">sample csv template</a></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="import_client_asset_interfaces_csv" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
