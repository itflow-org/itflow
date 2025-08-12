<?php

require_once '../../includes/modal_header.php';

$product_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM products WHERE product_id = $product_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$product_name = nullable_htmlentities($row['product_name']);
$product_type = nullable_htmlentities($row['product_type']);
$product_description = nullable_htmlentities($row['product_description']);
$product_code = nullable_htmlentities($row['product_code']);
$product_location = nullable_htmlentities($row['product_location']);
$product_price = floatval($row['product_price']);
$product_created_at = nullable_htmlentities($row['product_created_at']);
$category_id = intval($row['product_category_id']);
$product_tax_id = intval($row['product_tax_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-box-open mr-2"></i>Stocking: <strong><?php echo $product_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>QTY <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                </div>
                <input type="text" inputmode="numeric" pattern="[0-9]" class="form-control" name="qty" placeholder="Units to add" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Expense</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                </div>
                <select class="form-control select2" name="expense">
                    <option value="0">- Link an Expense -</option>
                    <?php

                    $expenses_sql = mysqli_query($mysqli, "SELECT expense_id, expense_description, expense_date 
                        FROM expenses 
                        WHERE expense_archived_at IS NULL ORDER BY expense_date DESC"
                    );
                    
                    while ($row = mysqli_fetch_array($expenses_sql)) {
                        $expense_id = intval($row['expense_id']);
                        $expense_description = nullable_htmlentities($row['expense_description']);
                        $expense_date = nullable_htmlentities($row['expense_date']);
                        ?>
                        <option value="<?= $expense_id ?>"><?= "($expense_date) $expense_description"; ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea class="form-control" rows="4" name="note" placeholder="Enter some notes"></textarea>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_product_stock" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Add Stock</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../includes/modal_footer.php';
