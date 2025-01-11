<div class="modal" id="archiveContactModal<?php echo $contact_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-archive mr-2"></i>Archiving contact: <strong><?php echo $contact_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                <div class="modal-body bg-white">

                    <div class="alert alert-warning">
                        Client Portal Access will be revoked upon archiving
                    </div>

                    <label>Unassign:</label>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="unassignAssetsCheckbox<?php echo $contact_id; ?>" name="unassign_assets" value="1">
                            <label class="custom-control-label" for="unassignAssetsCheckbox<?php echo $contact_id; ?>">Assets</label>
                        </div>
                    </div>
                
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="unassignLicensesCheckbox<?php echo $contact_id; ?>" name="unassign_licenses" value="1">
                            <label class="custom-control-label" for="unassignLicensesCheckbox<?php echo $contact_id; ?>">Licenses</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="unassignDocumentsCheckbox<?php echo $contact_id; ?>" name="unassign_documents" value="1">
                            <label class="custom-control-label" for="unassignDocumentsCheckbox<?php echo $contact_id; ?>">Documents</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="anonymizeCheckbox<?php echo $contact_id; ?>" name="anonymize_contact" value="1">
                            <label class="custom-control-label" for="anonymizeCheckbox<?php echo $contact_id; ?>">Anonymize Contact</label>
                        </div>
                    </div>
                
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="archive_contact" class="btn btn-danger text-bold"><i class="fas fa-check mr-2"></i>Arhive</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>