<div class="modal" id="exportVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-download mr-2"></i>Export Vendors to CSV</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <?php if(isset($_GET['client_id'])) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <div class="modal-body bg-white">

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="export_vendors_csv" class="btn btn-primary text-bold"><i class="fas fa-fw fa-download mr-2"></i>Download CSV</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
