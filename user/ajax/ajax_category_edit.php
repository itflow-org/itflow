<?php

require_once '../includes/ajax_header.php';

$category_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_id = $category_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$category_name = nullable_htmlentities($row['category_name']);
$category_color = nullable_htmlentities($row['category_color']);
$category_type = nullable_htmlentities($row['category_type']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-list-ul mr-2"></i>Editing category: <strong><?php echo $category_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
    <input type="hidden" name="type" value="<?php echo $category_type; ?>">
    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                </div>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $category_name; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                </div>
                <input type="color" class="form-control col-3" name="color" value="<?php echo $category_color; ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_category" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
