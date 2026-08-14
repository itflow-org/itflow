<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$item_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT item_created_at, item_description, item_name, item_price, item_product_id, item_quantity,
    item_tax_id, recurring_invoice_client_id FROM recurring_invoice_items LEFT JOIN recurring_invoices ON recurring_invoice_id = item_recurring_invoice_id WHERE item_id = $item_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$item_name = escapeHtml($row['item_name']);
$item_description = escapeHtml($row['item_description']);
$item_quantity = floatval($row['item_quantity']);
$item_price = floatval($row['item_price']);
$item_created_at = escapeHtml($row['item_created_at']);
$tax_id = intval($row['item_tax_id']);
$product_id = intval($row['item_product_id']);
$client_id = intval($row['recurring_invoice_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit me-2"></i>Editing Line Item: <strong><?= $item_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="item_id" value="<?= $item_id ?>">
    <input type="hidden" name="product_id" value="<?= $product_id ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label>Item <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?= $item_name ?>" placeholder="Enter item name" required>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-sm">
                <div class="mb-3">
                    <label>Quantity <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                        <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="qty" value="<?= number_format($item_quantity, 2) ?>" placeholder="0.00" required>
                    </div>
                </div>
            </div>

            <div class="col-sm">
                <div class="mb-3">
                    <label>Price <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="price" value="<?= number_format($item_price, 2, '.', '') ?>" placeholder="0.00" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                <textarea class="form-control" rows="5" name="description" placeholder="Enter a description"><?= $item_description ?></textarea>
            </div>
        </div>

        <div class="mb-3">
            <label>Tax <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                <select class="form-control select2" name="tax_id" required>
                    <option value="0">No Tax</option>
                    <?php
                        $taxes_sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE (tax_archived_at > '$item_created_at' OR tax_archived_at IS NULL) ORDER BY tax_name ASC");
                        while ($row = mysqli_fetch_assoc($taxes_sql)) {
                            $tax_id_select = intval($row['tax_id']);
                            $tax_name = escapeHtml($row['tax_name']);
                            $tax_percent = floatval($row['tax_percent']);
                    ?>
                        <option <?php if ($tax_id_select == $tax_id) { echo "selected"; } ?> value="<?= $tax_id_select ?>"><?= "$tax_name $tax_percent%" ?></option>
                    <?php
                        }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_recurring_invoice_item" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
