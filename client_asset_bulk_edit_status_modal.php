<div class="modal" id="bulkEditStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa- mr-2"></i>Bulk Edit Status</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Status</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_status">
                            <option value="">- Status -</option>
                            <?php foreach($asset_status_array as $asset_status) { ?>
                                <option><?php echo $asset_status; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_edit_asset_status" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Set</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>