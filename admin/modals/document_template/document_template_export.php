<?php

/*
 * Export document templates to a file - every current one, or the ones
 * picked, and the archived ones if asked - to import into another ITFlow
 * (export_document_templates, functions/document_template_transfer.php).
 */

require_once '../../includes/modal_header.php';

$sql = mysqli_query($mysqli, "SELECT document_template_id, document_template_name, document_template_archived_at FROM document_templates
    ORDER BY document_template_archived_at IS NOT NULL, document_template_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-download me-2"></i>Export document templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file to import into another ITFlow, or keep. Each template goes with its summary, what it says and its images (each up to <?= intval(DOCUMENT_TEMPLATE_TRANSFER_IMAGE_MAX / 1048576) ?> MB), the fill-ins set up on it and how often its documents are reviewed. Who should have one stays here.</p>

        <div class="mb-3">
            <label class="form-label">Document templates</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                <select class="form-select select2" name="document_template_ids[]" multiple data-placeholder="Every current one">
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['document_template_id']) ?>"><?= escapeHtml($row['document_template_name']) ?><?= $row['document_template_archived_at'] ? ' (archived)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Leave it empty for every current one.</div>
        </div>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="archived" value="1" id="exportDocumentTemplatesArchived">
            <label class="form-check-label" for="exportDocumentTemplatesArchived">With the archived ones too</label>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="export_document_templates" class="btn btn-primary text-bold"><i class="fa fa-download me-2"></i>Download</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
