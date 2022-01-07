<div class="modal" id="addAssetCSVModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-desktop"></i> Import Assets via CSV</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">
                <p>Format: name, type, make, model, serial, os</p>
                <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                    <div class="form-group">
                        <input type="file" class="form-control-file" name="file">
                    </div>

            </div>
            <div class="modal-footer bg-white">
                <button type="submit" name="import_client_assets_csv" class="btn btn-primary">Upload</button>
            </div>
            </form>
        </div>
    </div>
</div>
