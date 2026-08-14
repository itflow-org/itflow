
<?php

require_once '../../../includes/modal_header.php';

$product_ids = array_map('intval', $_GET['product_ids'] ?? []);

$count = count($product_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-list me-2"></i>Set Category for <strong><?= $count ?></strong> Products</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($product_ids as $product_id) { ?><input type="hidden" name="product_ids[]" value="<?= $product_id ?>"><?php } ?>
    <div class="modal-body">

        <div class="mb-3">
            <label>Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                <select class="form-control select2" name="bulk_category_id">
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $category_id = intval($row['category_id']);
                        $category_name = escapeHtml($row['category_name']);
                        ?>
                        <option value="<?= $category_id ?>"><?= $category_name ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_product_category" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>


<?php
require_once '../../../includes/modal_footer.php';
