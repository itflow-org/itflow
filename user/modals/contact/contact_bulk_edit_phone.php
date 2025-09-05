<div class="modal" id="bulkEditPhoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-phone-alt mr-2"></i>Bulk Set Phone Number</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <label>Phone</label>
                <div class="form-group">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                        </div>
                        <input type="text" class="form-control" name="bulk_phone" placeholder="Phone Number" maxlength="200">
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" name="bulk_edit_contact_phone" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Set</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>