<?php

require_once '../../../includes/modal_header.php';

$software_template_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM software_templates WHERE software_template_id = $software_template_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$software_name = escapeHtml($row['software_template_name']);
$software_version = escapeHtml($row['software_template_version']);
$software_description = escapeHtml($row['software_template_description']);
$software_type = escapeHtml($row['software_template_type']);
$software_license_type = escapeHtml($row['software_template_license_type']);
$software_notes = escapeHtml($row['software_template_notes']);

$license_types_array = array (
    'Device',
    'User',
    'Site',
    'Concurrent',
    'Trial',
    'Perpetual',
    'Usage-based'
);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube mr-2"></i>Editing template: <strong><?php echo $software_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_template_id" value="<?php echo $software_template_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Template Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Software name" maxlength="200" value="<?php echo $software_name; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Version</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                </div>
                <input type="text" class="form-control" name="version" placeholder="Software version" maxlength="200" value="<?php echo $software_version; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                </div>
                <input type="text" class="form-control" name="description" placeholder="Short description" value="<?php echo $software_description; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <select class="form-control select2" name="type" required>
                    <option value="">- Select Type -</option>
                    <<?php
                    $sql_software_types_select = mysqli_query($mysqli, "
                        SELECT category_name FROM categories
                        WHERE category_type = 'software_type'
                        AND category_archived_at IS NULL
                        ORDER BY category_order ASC, category_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_software_types_select)) {
                        $software_type_select = escapeHtml($row['category_name']);
                        ?>
                        <option <?php if($software_type == $software_type_select) { echo "selected"; } ?>>
                            <?= $software_type_select ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>License Type</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                </div>
                <select class="form-control select2" name="license_type">
                    <option value="">- Select a License Type -</option>
                    <?php foreach($license_types_array as $license_type_select) { ?>
                        <option <?php if($license_type_select == $software_license_type){ echo "selected"; } ?>><?php echo $license_type_select; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?php echo $software_notes; ?></textarea>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_software_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
