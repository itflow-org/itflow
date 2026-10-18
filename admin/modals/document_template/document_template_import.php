<?php

/*
 * Import document templates from a file exported by ITFlow - and what to do
 * with one already here by name (import_document_templates,
 * functions/document_template_transfer.php).
 */

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-upload me-2"></i>Import document templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file exported from Admin > Document Templates in this or another ITFlow. Each comes in with its images, its fill-ins and how often its documents are reviewed. A new one is required of no one until you say who should have one on its page.</p>

        <div class="mb-3">
            <label class="form-label">File <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-file-code"></i></span>
                <input type="file" class="form-control" name="file" accept=".json,application/json" required>
            </div>
        </div>

        <div class="mb-1">
            <label class="form-label">A document template already here by name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clone"></i></span>
                <select class="form-select select2" name="existing">
                    <option value="skip" data-description="Yours stays as it is">Leave it</option>
                    <option value="copy" data-description="The file's comes in under a name of its own - Network Overview (2)">Add the file's as well</option>
                    <option value="replace" data-description="Its name, summary, what it says, images, fill-ins and review become the file's. Its documents and who should have one stay">Replace it with the file's</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="import_document_templates" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i>Import</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
