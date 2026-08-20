<?php

require_once '../../includes/modal_header.php';

$category_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT category_color, category_description, category_name, category_type FROM categories WHERE category_id = $category_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$category_name = escapeHtml($row['category_name']);
$category_description = escapeHtml($row['category_description']);
$category_color = escapeHtml($row['category_color']);
$category_type = escapeHtml($row['category_type']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-list-ul me-2"></i>Editing category: <strong><?= $category_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="category_id" value="<?= $category_id ?>">
    <input type="hidden" name="type" value="<?= $category_type ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?= $category_name ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                <input type="color" class="form-control col-3" name="color" value="<?= $category_color ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Enter a description" maxlength="200" value="<?= $category_description ?>">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_category" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
