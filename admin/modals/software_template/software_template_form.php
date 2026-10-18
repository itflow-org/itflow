<?php
/*
 * A license template's fields - shared by its New and Edit windows
 * (software_template_add.php, software_template_edit.php), which set
 * $template (softwareTemplateRow(), or [] for a new one) before including it.
 * Posts what softwareTemplateFields() reads (functions/software_template.php).
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$t = $template;
$type = (string) ($t['software_template_type'] ?? '');
$license_type = (string) ($t['software_template_license_type'] ?? '');

// The license types the license form offers (functions/software.php), keeping one this template already has
$license_types = array_keys(SOFTWARE_LICENSE_TYPES);
if ($license_type !== '' && !in_array($license_type, $license_types, true)) {
    $license_types[] = $license_type;
}

?>

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
            <input type="text" class="form-control" name="name" placeholder="Microsoft 365 Business Premium" maxlength="200" value="<?= escapeHtml($t['software_template_name'] ?? '') ?>" required autofocus>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Version</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-code-branch"></i></span>
            <input type="text" class="form-control" name="version" placeholder="2024" maxlength="200" value="<?= escapeHtml($t['software_template_version'] ?? '') ?>">
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Summary</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" placeholder="One line on what it is - shown when an agent picks a template" maxlength="500" value="<?= escapeHtml($t['software_template_description'] ?? '') ?>">
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Type <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <select class="form-select select2" name="type" required>
                <option value="">- Pick a type -</option>
                <?= categoryOptionsHtml('software_type', $type) ?>
            </select>
            <?= categoryAddButtonHtml('software_type', 'btn btn-outline-secondary') ?>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">License type</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-certificate"></i></span>
            <select class="form-select select2" name="license_type">
                <option value="">- None -</option>
                <?php foreach ($license_types as $option) { ?>
                    <option value="<?= escapeHtml($option) ?>" <?= $option === $license_type ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . softwareLicenseTypeIcon($option)]) ?>><?= escapeHtml($option) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-sticky-note"></i></span>
            <textarea class="form-control" name="notes" rows="6" placeholder="What every license from it should say - where to download it, how it is installed, who to call"><?= escapeHtml($t['software_template_notes'] ?? '') ?></textarea>
        </div>
        <div class="form-text">A license made from it starts with all of this - change anything there after.</div>
    </div>
</div>
