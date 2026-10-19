<?php

/*
 * Import license templates from a file exported by ITFlow - and what to do
 * with one already here by name (import_software_templates,
 * functions/software_template_transfer.php).
 */

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-upload me-2"></i>Import license templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file exported from Admin > License Templates in this or another ITFlow. Each comes in with what a license made from it starts with. Its vendor is the vendor template here of that name, or one added from the file; what it is sold as, the product here of that name. A new one is required of no one until you say who should have it on its page.</p>

        <div class="mb-3">
            <label class="form-label">File <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-file-code"></i></span>
                <input type="file" class="form-control" name="file" accept=".json,application/json" required>
            </div>
        </div>

        <div class="mb-1">
            <label class="form-label">A license template already here by name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clone"></i></span>
                <select class="form-select select2" name="existing">
                    <option value="skip" data-description="Yours stays as it is">Leave it</option>
                    <option value="copy" data-description="The file's comes in under a name of its own - Microsoft 365 Business Premium (2)">Add the file's as well</option>
                    <option value="replace" data-description="What a license starts with and its vendor become the file's. Its licenses, who should have it and - unless the file's is here - its product stay">Replace it with the file's</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="import_software_templates" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i>Import</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
