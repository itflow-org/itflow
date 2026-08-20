<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$recurring_invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT recurring_invoice_category_id, recurring_invoice_client_id, recurring_invoice_created_at,
    recurring_invoice_discount_amount, recurring_invoice_frequency,
    recurring_invoice_next_date, recurring_invoice_number, recurring_invoice_prefix,
    recurring_invoice_scope, recurring_invoice_status FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$recurring_invoice_prefix = escapeHtml($row['recurring_invoice_prefix']);
$recurring_invoice_number = intval($row['recurring_invoice_number']);
$recurring_invoice_scope = escapeHtml($row['recurring_invoice_scope']);
$recurring_invoice_frequency = escapeHtml($row['recurring_invoice_frequency']);
$recurring_invoice_status = escapeHtml($row['recurring_invoice_status']);
$recurring_invoice_created_at = date('Y-m-d', strtotime($row['recurring_invoice_created_at']));
$recurring_invoice_next_date = escapeHtml($row['recurring_invoice_next_date']);
$recurring_invoice_discount = floatval($row['recurring_invoice_discount_amount']);
$category_id = intval($row['recurring_invoice_category_id']);
$client_id = intval($row['recurring_invoice_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt me-2"></i>Editing Recur Invoice: <strong><?= "$recurring_invoice_prefix$recurring_invoice_number" ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="recurring_invoice_id" value="<?= $recurring_invoice_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Scope</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                <input type="text" class="form-control" name="scope" placeholder="Quick description" maxlength="255" value="<?= $recurring_invoice_scope ?>">
            </div>
        </div>

        <div class="mb-3">
            <label>Frequency <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <select class="form-select select2" name="frequency" required>
                    <option value="">- Frequency -</option>
                    <option <?php if ($recurring_invoice_frequency == 'month') { echo "selected"; } ?> value="month">Monthly</option>
                    <option <?php if ($recurring_invoice_frequency == 'year') { echo "selected"; } ?> value="year">Yearly</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Next Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="next_date" max="2999-12-31" value="<?= $recurring_invoice_next_date ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Income Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select select2" name="category" required>
                    <option value="">- Category -</option>
                    <?php

                    $sql_income_category = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$recurring_invoice_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_income_category)) {
                        $category_id_select = intval($row['category_id']);
                        $category_name_select = escapeHtml($row['category_name']);
                    ?>
                        <option <?php if ($category_id == $category_id_select) { ?> selected <?php } ?> value="<?= $category_id_select ?>"><?= $category_name_select ?></option>

                    <?php
                    }
                    ?>
                </select>
                    <button class="btn btn-secondary ajax-modal" type="button"
                        data-modal-url="../admin/modals/category/category_add.php?category=Income">
                        <i class="fas fa-fw fa-plus"></i>
                    </button>
            </div>
        </div>

        <div class='mb-3'>
            <label>Discount Amount</label>
            <div class='input-group'>
                    <span class='input-group-text'><i class='fa fa-fw fa-dollar-sign'></i></span>
                <input type='text' class='form-control' inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name='recurring_invoice_discount' placeholder='0.00' value="<?= number_format($recurring_invoice_discount, 2, '.', '') ?>">
            </div>
        </div>

        <div class="mb-3">
            <label>Status <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <select class="form-select select2" name="status" required>
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
        <button type="submit" name="edit_recurring_invoice" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
