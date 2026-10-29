<?php

/*
 * Export license templates to a file - every current one, or the ones
 * picked, and the archived ones if asked - to import into another ITFlow
 * (export_software_templates, functions/software_template_transfer.php).
 */

require_once '../../includes/modal_header.php';

$sql = mysqli_query($mysqli, "SELECT software_template_id, software_template_name, software_template_type, software_template_archived_at FROM software_templates
    ORDER BY software_template_archived_at IS NOT NULL, software_template_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-download me-2"></i>Export license templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file to import into another ITFlow, or keep. Each template goes with what a license made from it starts with - its type, license type, seats, renewal term and notes - its vendor template, without account numbers or codes, and the name of the product it is sold as. Who should have one stays here.</p>

        <div class="mb-3">
            <label class="form-label">License templates</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <select class="form-select select2" name="software_template_ids[]" multiple data-placeholder="Every current one">
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['software_template_id']) ?>"<?= selectOptionData(['icon' => 'fas ' . softwareTypeStyle($row['software_template_type'])['icon'], 'description' => $row['software_template_archived_at'] ? 'Archived' : $row['software_template_type']]) ?>><?= escapeHtml($row['software_template_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Leave it empty for every current one.</div>
        </div>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="archived" value="1" id="exportSoftwareTemplatesArchived">
            <label class="form-check-label" for="exportSoftwareTemplatesArchived">With the archived ones too</label>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="export_software_templates" class="btn btn-primary text-bold"><i class="fa fa-download me-2"></i>Download</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
