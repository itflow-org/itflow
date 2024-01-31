<div class="modal" id="bulkReplyTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Bulk Reply</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <input type="hidden" name="bulk_private_reply" value="0">

                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                    </div>
                    <select class="form-control select2" name="bulk_status">
                        <option>In-Progress</option>
                        <option>Pending-Client</option>
                        <option>Pending-Vendor</option>
                        <option>Pending-Shipment</option>
                        <option>Scheduled</option>
                    </select>
                </div>

                <div class="form-group">
                    <textarea class="form-control tinymce" rows="5" name="bulk_reply_details" placeholder="Type an update here"></textarea>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="bulkPrivateReplyCheckbox" name="bulk_private_reply" value="1">
                        <label class="custom-control-label" for="bulkPrivateReplyCheckbox">Mark as a Private</label>
                        <small class="form-text text-muted">If checked the contact and any watcher will not be informed</small>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_ticket_reply" class="btn btn-primary text-bold"><i class="fas fa-paper-plane mr-2"></i>Reply</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
