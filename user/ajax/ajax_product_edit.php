<?php

require_once '../../includes/modal_header.php';

$product_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM products WHERE product_id = $product_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$product_name = nullable_htmlentities($row['product_name']);
$product_description = nullable_htmlentities($row['product_description']);
$product_price = floatval($row['product_price']);
$product_created_at = nullable_htmlentities($row['product_created_at']);
$category_id = intval($row['product_category_id']);
$product_tax_id = intval($row['product_tax_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-box-open mr-2"></i>Editing product: <strong><?php echo $product_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-fw fa-box"></i></span>
                </div>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $product_name; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                </div>
                <select class="form-control select2" name="category" required>
                    <?php

                    $sql_select = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$product_created_at' OR category_archived_at IS NULL)");
                    while ($row = mysqli_fetch_array($sql_select)) {
                        $category_id_select = intval($row['category_id']);
                        $category_name_select = nullable_htmlentities($row['category_name']);
                        ?>
                        <option <?php if ($category_id == $category_id_select) { echo "selected"; } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                        <?php
                    }

                    ?>
                </select>
                <div class="input-group-append">
                    <button class="btn btn-secondary" type="button"
                        data-toggle="ajax-modal"
                        data-modal-size="sm"
                        data-ajax-url="ajax/ajax_category_add.php?category=Income">
                        <i class="fas fa-fw fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="col">
                <div class="form-group">
                    <label>Price <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        </div>
                        <input type="text" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" class="form-control" name="price" value="<?php echo number_format($product_price, 2, '.', ''); ?>" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label>Tax</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                        </div>
                        <select class="form-control select2" name="tax">
                            <option value="0">None</option>
                            <?php

                            $taxes_sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE (tax_archived_at > '$product_created_at' OR tax_archived_at IS NULL) ORDER BY tax_name ASC");
                            while ($row = mysqli_fetch_array($taxes_sql)) {
                                $tax_id_select = intval($row['tax_id']);
                                $tax_name = nullable_htmlentities($row['tax_name']);
                                $tax_percent = floatval($row['tax_percent']);
                                ?>
                                <option <?php if ($tax_id_select == $product_tax_id) { echo "selected"; } ?> value="<?php echo $tax_id_select; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="5" name="description"><?php echo $product_description; ?></textarea>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_product" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../includes/modal_footer.php';
