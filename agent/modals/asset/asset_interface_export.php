<div class="modal" id="exportAssetInterfaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-download mr-2"></i>Export Interfaces</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="asset_id" value="<?= $asset_id ?>">

                <div class="modal-body">

                    <?php renderExportColumnPicker('asset_interfaces'); ?>

                </div>
                <div class="modal-footer">
                    <?php renderExportButtons('export_asset_interfaces'); ?>
                </div>
            </form>
        </div>
    </div>
</div>
