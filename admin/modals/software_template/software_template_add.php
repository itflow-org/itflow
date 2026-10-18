<?php

require_once '../../includes/modal_header.php';

// The license types the license form offers (functions/software.php)
$license_types_array = array_keys(SOFTWARE_LICENSE_TYPES);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube me-2"></i>New License Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Template Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Software name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Version</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <input type="text" class="form-control" name="version" placeholder="Software version" maxlength="200">
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Short description">
            </div>
        </div>

        <div class="mb-3">
            <label>Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select select2" name="type" required>
                    <option value="">- Select Type -</option>
                    <?= categoryOptionsHtml('software_type') ?>
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
                    <?php foreach($license_types_array as $license_type) { ?>
                        <option<?= selectOptionData(['icon' => 'fas ' . softwareLicenseTypeIcon($license_type)]) ?>><?= escapeHtml($license_type) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_software_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
