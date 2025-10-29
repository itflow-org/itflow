<div class="modal" id="bulkEditBillableRecurringTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-money mr-2"></i>Bulk Set Billable</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
                
            <div class="modal-body">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="billable" value="1" id="billable<?= $recurring_ticket_id ?>">
                        <label class="custom-control-label" for="billable<?= $recurring_ticket_id ?>">Mark Billable</label>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" name="bulk_edit_recurring_ticket_billable" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>

        </div>
    </div>
</div>
