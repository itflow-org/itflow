<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$quote_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT client_name, quote_category_id, quote_client_id, quote_created_at, quote_date,
    quote_discount_amount, quote_expire, quote_id, quote_number, quote_prefix, quote_scope FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$quote_id = intval($row['quote_id']);
$quote_prefix = escapeHtml($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$quote_scope = escapeHtml($row['quote_scope']);
$quote_date = escapeHtml($row['quote_date']);
$quote_expire = escapeHtml($row['quote_expire']);
$quote_discount = floatval($row['quote_discount_amount']);
$quote_created_at = escapeHtml($row['quote_created_at']);
$quote_category_id = intval($row['quote_category_id']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['quote_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-white"><i class="fas fa-fw fa-comment-dollar me-2"></i>Editing quote: <span class="text-bold"><?= "$quote_prefix$quote_number" ?></span> - <span class="text"><?= $client_name ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Quote Date</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= $quote_date ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Expire <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?= $quote_expire ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Income Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-control select2" name="category" required>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$quote_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $category_id = intval($row['category_id']);
                        $category_name = escapeHtml($row['category_name']);
                        ?>
                        <option <?php if ($quote_category_id == $category_id) { echo "selected"; } ?> value="<?= $category_id ?>"><?= $category_name ?></option>

                    <?php } ?>

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
                <div class='input-group-prepend'>
                    <span class='input-group-text'><i class='fa fa-fw fa-dollar-sign'></i></span>
                </div>
                <input type='text' class='form-control' inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name='quote_discount' placeholder='0.00' value="<?= number_format($quote_discount, 2, '.', '') ?>">
            </div>
        </div>

        <div class="mb-3">
            <label>Scope</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                <input type="text" class="form-control" name="scope" placeholder="Quick description" value="<?= $quote_scope ?>" maxlength="255">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_quote" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
