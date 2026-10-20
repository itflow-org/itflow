<?php

/*
 * Import vendor templates from a file exported by ITFlow - and what to do
 * with one already here by name (import_vendor_templates,
 * functions/vendor_template_transfer.php).
 */

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-upload me-2"></i>Import vendor templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/admin/post.php" method="post" enctype="multipart/form-data" autocomplete="off" data-vendor-template-import>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file exported from Admin > Vendor Templates in this or another ITFlow. Each comes in with what a vendor made from it starts with, and is required of no one until you say who should have it on its page.</p>

        <div class="mb-3">
            <label class="form-label">File <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-file-code"></i></span>
                <input type="file" class="form-control" name="file" accept=".json,application/json" required>
            </div>
        </div>

        <div class="mb-1">
            <label class="form-label">A vendor template already here by name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clone"></i></span>
                <select class="form-select select2" name="existing">
                    <option value="skip" data-description="Yours stays as it is">Leave it</option>
                    <option value="copy" data-description="The file's comes in under a name of its own - Dell (2)">Add the file's as well</option>
                    <option value="replace" data-description="What a vendor starts with becomes the file's, and the vendors made from it that still said what it did change with it. Its account number, PIN and who should have it stay">Replace it with the file's</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="import_vendor_templates" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i>Import</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
