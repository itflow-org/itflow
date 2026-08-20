<?php

require_once '../../includes/modal_header.php';

$tax_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT tax_name, tax_percent FROM taxes WHERE tax_id = $tax_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$tax_name = escapeHtml($row['tax_name']);
$tax_percent = floatval($row['tax_percent']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-balance-scale me-2"></i>Editing tax: <strong><?= $tax_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="tax_id" value="<?= $tax_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" maxlength="200" value="<?= $tax_name ?>" required>
        </div>

        <div class="mb-3">
            <label>Percent <strong class="text-danger">*</strong></label>
            <input type="number" min="0" step="any" class="form-control col-md-4" name="percent" value="<?= $tax_percent ?>">
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_tax" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
