<?php

require_once '../../../includes/modal_header.php';

$contact_ids = array_map('intval', $_GET['contact_ids'] ?? []);

$count = count($contact_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user-shield me-2"></i>Set Roles for <strong><?= $count ?></strong> Contacts</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($contact_ids as $contact_id) { ?><input type="hidden" name="contact_ids[]" value="<?= $contact_id ?>"><?php } ?>
    <input type="hidden" name="bulk_contact_important" value="0">
    <input type="hidden" name="bulk_contact_billing" value="0">
    <input type="hidden" name="bulk_contact_technical" value="0">
    <div class="modal-body">
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="bulkContactImportantCheckbox" name="bulk_contact_important" value="1">
                <label class="form-check-label" for="bulkContactImportantCheckbox">Important</label>
                <small class="form-text text-muted">Important Person and pins them to the top of the contact list</small>
            </div>
        </div>

        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="bulkContactBillingCheckbox" name="bulk_contact_billing" value="1">
                <label class="form-check-label" for="bulkContactBillingCheckbox">Billing</label>
                <small class="form-text text-muted">Receives Invoices and Receipts and has access to billing via the portal</small>
            </div>
        </div>

        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="bulkContactTechnicalCheckbox" name="bulk_contact_technical" value="1">
                <label class="form-check-label" for="bulkContactTechnicalCheckbox">Technical</label>
                <small class="form-text text-muted">Person to contact for technical related things and has access to all tickets and documents via the portal</small>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_contact_role" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set Roles</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
