<div class="modal" id="bulkEditRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-shield mr-2"></i>Bulk Set Roles</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <input type="hidden" name="bulk_contact_important" value="0">
                <input type="hidden" name="bulk_contact_billing" value="0">
                <input type="hidden" name="bulk_contact_technical" value="0">

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="bulkContactImportantCheckbox" name="bulk_contact_important" value="1">
                        <label class="custom-control-label" for="bulkContactImportantCheckbox">Important</label>
                        <small class="form-text text-muted">Important Person and pins them to the top of the contact list</small>
                    </div>
                </div>
           
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="bulkContactBillingCheckbox" name="bulk_contact_billing" value="1">
                        <label class="custom-control-label" for="bulkContactBillingCheckbox">Billing</label>
                        <small class="form-text text-muted">Receives Invoices and Receipts and has access to billing via the portal</small>
                    </div>
                </div>
           
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="bulkContactTechnicalCheckbox" name="bulk_contact_technical" value="1">
                        <label class="custom-control-label" for="bulkContactTechnicalCheckbox">Technical</label>
                        <small class="form-text text-muted">Person to contact for technical related things and has access to all tickets and documents via the portal</small>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" name="bulk_edit_contact_role" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Set</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>