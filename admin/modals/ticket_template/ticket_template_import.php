<?php

/*
 * Import ticket templates from a file exported by ITFlow - and what to do
 * with one already here by name (import_ticket_templates,
 * functions/ticket_template_transfer.php).
 */

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-upload me-2"></i>Import ticket templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file exported from Admin > Ticket Templates in this or another ITFlow. Categories and tags they use are matched by name, and made when they aren't here. A request form comes in turned off - turn it on in the template's Client portal tab.</p>

        <div class="mb-3">
            <label class="form-label">File <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-file-code"></i></span>
                <input type="file" class="form-control" name="file" accept=".json,application/json" required>
            </div>
        </div>

        <div class="mb-1">
            <label class="form-label">A template already here by name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clone"></i></span>
                <select class="form-select select2" name="existing">
                    <option value="skip" data-description="Yours stays as it is">Leave it</option>
                    <option value="copy" data-description="The file's comes in under a name of its own - New Hire Onboarding (2)">Add the file's as well</option>
                    <option value="replace" data-description="Its subject, details, setup, tasks and fill-ins become the file's. Its tickets, who it goes to and its request form's clients stay">Replace it with the file's</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="import_ticket_templates" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i>Import</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
