<div class="modal" id="archiveContactModal<?= $contact_id ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-archive me-2"></i>Archiving contact: <strong><?= $contact_name ?></strong></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
                <div class="modal-body">

                    <div class="alert alert-warning">
                        Client Portal Access will be revoked upon archiving
                    </div>

                    <label>Unassign:</label>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="unassignAssetsCheckbox<?= $contact_id ?>" name="unassign_assets" value="1">
                            <label class="form-check-label" for="unassignAssetsCheckbox<?= $contact_id ?>">Assets</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="unassignLicensesCheckbox<?= $contact_id ?>" name="unassign_licenses" value="1">
                            <label class="form-check-label" for="unassignLicensesCheckbox<?= $contact_id ?>">Licenses</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="unassignDocumentsCheckbox<?= $contact_id ?>" name="unassign_documents" value="1">
                            <label class="form-check-label" for="unassignDocumentsCheckbox<?= $contact_id ?>">Documents</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="anonymizeCheckbox<?= $contact_id ?>" name="anonymize_contact" value="1">
                            <label class="form-check-label" for="anonymizeCheckbox<?= $contact_id ?>">Anonymize Contact</label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="archive_contact" class="btn btn-danger text-bold"><i class="fas fa-check me-2"></i>Arhive</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
