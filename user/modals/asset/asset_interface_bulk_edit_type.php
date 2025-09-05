<div class="modal" id="bulkSetInterfaceTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-ethernet mr-2"></i>Bulk Set Interface Type</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <div class="form-group">
                    <label>Interface Type</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_type">
                            <option value="">- Select a Type -</option>
                            <?php foreach($interface_types_array as $interface_type_select) { ?>
                                <option><?php echo $interface_type_select; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" name="bulk_edit_asset_interface_type" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Set</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>