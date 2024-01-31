<div class="modal" id="bulkCloseTicketsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-gavel mr-2"></i>Closing Multiple Tickets</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <input type="hidden" name="bulk_private_note" value="0">

                <div class="form-group">
                    <textarea class="form-control tinymce" rows="5" name="bulk_details" placeholder="Enter closing remarks"></textarea>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="bulkPrivateCheckbox" name="bulk_private_note" value="1">
                        <label class="custom-control-label" for="bulkPrivateCheckbox">Mark as a Private</label>
                        <small class="form-text text-muted">If checked the contact and any watcher will not be informed</small>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_close_tickets" class="btn btn-primary text-bold"><i class="fas fa-gavel mr-2"></i>Close</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
