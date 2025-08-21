<?php

require_once '../../includes/modal_header.php';

$recurring_invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$recurring_invoice_prefix = nullable_htmlentities($row['recurring_invoice_prefix']);
$recurring_invoice_number = intval($row['recurring_invoice_number']);
$recurring_invoice_scope = nullable_htmlentities($row['recurring_invoice_scope']);
$recurring_invoice_frequency = nullable_htmlentities($row['recurring_invoice_frequency']);
$recurring_invoice_status = nullable_htmlentities($row['recurring_invoice_status']);
$recurring_invoice_created_at = date('Y-m-d', strtotime($row['recurring_invoice_created_at']));
$recurring_invoice_next_date = nullable_htmlentities($row['recurring_invoice_next_date']);
$recurring_invoice_discount = floatval($row['recurring_invoice_discount_amount']);
$category_id = intval($row['recurring_invoice_category_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt mr-2"></i>Editing Recur Invoice: <strong><?php echo "$recurring_invoice_prefix$recurring_invoice_number"; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="recurring_invoice_id" value="<?php echo $recurring_invoice_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Scope</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                </div>
                <input type="text" class="form-control" name="scope" placeholder="Quick description" maxlength="255" value="<?php echo $recurring_invoice_scope; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Frequency <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                </div>
                <select class="form-control select2" name="frequency" required>
                    <option value="">- Frequency -</option>
                    <option <?php if ($recurring_invoice_frequency == 'month') { echo "selected"; } ?> value="month">Monthly</option>
                    <option <?php if ($recurring_invoice_frequency == 'year') { echo "selected"; } ?> value="year">Yearly</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Next Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="next_date" max="2999-12-31" value="<?php echo $recurring_invoice_next_date; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Income Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <select class="form-control select2" name="category" required>
                    <option value="">- Category -</option>
                    <?php

                    $sql_income_category = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$recurring_invoice_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_array($sql_income_category)) {
                        $category_id_select = intval($row['category_id']);
                        $category_name_select = nullable_htmlentities($row['category_name']);
                    ?>
                        <option <?php if ($category_id == $category_id_select) { ?> selected <?php } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>

                    <?php
                    }
                    ?>
                </select>
                <div class="input-group-append">
                    <button class="btn btn-secondary" type="button"
                        data-toggle="ajax-modal"
                        data-modal-size="sm"
                        data-ajax-url="../admin/ajax/ajax_category_add.php?category=Income">
                        <i class="fas fa-fw fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class='form-group'>
            <label>Discount Amount</label>
            <div class='input-group'>
                <div class='input-group-prepend'>
                    <span class='input-group-text'><i class='fa fa-fw fa-dollar-sign'></i></span>
                </div>
                <input type='text' class='form-control' inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name='recurring_invoice_discount' placeholder='0.00' value="<?php echo number_format($recurring_invoice_discount, 2, '.', ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Status <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                </div>
                <select class="form-control select2" name="status" required>
                    <option <?php if ($recurring_invoice_status == 1) {
                                echo "selected";
                            } ?> value="1">Active</option>
                    <option <?php if ($recurring_invoice_status == 0) {
                                echo "selected";
                            } ?> value="0">InActive</option>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_recurring_invoice" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../includes/modal_footer.php';
