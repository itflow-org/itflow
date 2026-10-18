<?php

require_once '../../includes/modal_header.php';

$software_template_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT software_template_description, software_template_license_type, software_template_name,
    software_template_notes, software_template_type, software_template_version FROM software_templates WHERE software_template_id = $software_template_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$software_name = escapeHtml($row['software_template_name']);
$software_version = escapeHtml($row['software_template_version']);
$software_description = escapeHtml($row['software_template_description']);
$software_type = escapeHtml($row['software_template_type']);
$software_license_type = escapeHtml($row['software_template_license_type']);
$software_notes = escapeHtml($row['software_template_notes']);

// The license types the license form offers (functions/software.php), keeping one this template already has
$license_types_array = array_keys(SOFTWARE_LICENSE_TYPES);
if ($row['software_template_license_type'] && !in_array($row['software_template_license_type'], $license_types_array, true)) {
    $license_types_array[] = $row['software_template_license_type'];
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube me-2"></i>Editing template: <strong><?= $software_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_template_id" value="<?= $software_template_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Template Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Software name" maxlength="200" value="<?= $software_name ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Version</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <input type="text" class="form-control" name="version" placeholder="Software version" maxlength="200" value="<?= $software_version ?>">
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Short description" value="<?= $software_description ?>">
            </div>
        </div>

        <div class="mb-3">
            <label>Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select select2" name="type" required>
                    <option value="">- Select Type -</option>
                    <?= categoryOptionsHtml('software_type', html_entity_decode($software_type, ENT_QUOTES, 'UTF-8')) ?>
                </select>
                <?= categoryAddButtonHtml('software_type', 'btn btn-secondary') ?>
            </div>
        </div>

        <div class="mb-3">
            <label>License Type</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <select class="form-select select2" name="license_type">
                    <option value="">- Select a License Type -</option>
                    <?php foreach($license_types_array as $license_type_select) { ?>
                        <option <?php if (escapeHtml($license_type_select) === $software_license_type) { echo "selected"; } ?><?= selectOptionData(['icon' => 'fas ' . softwareLicenseTypeIcon($license_type_select)]) ?>><?= escapeHtml($license_type_select) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?= $software_notes ?></textarea>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_software_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
