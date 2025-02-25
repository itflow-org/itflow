<?php

require_once '../includes/ajax_header.php';

$quote_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$quote_id = intval($row['quote_id']);
$quote_prefix = nullable_htmlentities($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$quote_scope = nullable_htmlentities($row['quote_scope']);
$quote_date = nullable_htmlentities($row['quote_date']);
$quote_expire = nullable_htmlentities($row['quote_expire']);
$quote_discount = floatval($row['quote_discount_amount']);
$quote_created_at = nullable_htmlentities($row['quote_created_at']);
$quote_category_id = intval($row['quote_category_id']);
$client_name = nullable_htmlentities($row['client_name']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title text-white"><i class="fas fa-fw fa-comment-dollar mr-2"></i>Editing quote: <span class="text-bold"><?php echo "$quote_prefix$quote_number"; ?></span> - <span class="text"><?php echo $client_name; ?></span></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">

    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Quote Date</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo $quote_date; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Expire <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?php echo $quote_expire; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Income Category</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <select class="form-control select2" name="category" required>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$quote_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_array($sql)) {
                        $category_id = intval($row['category_id']);
                        $category_name = nullable_htmlentities($row['category_name']);
                        ?>
                        <option <?php if ($quote_category_id == $category_id) { echo "selected"; } ?> value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>

                    <?php } ?>

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


        <div class='form-group'>
            <label>Discount Amount</label>
            <div class='input-group'>
                <div class='input-group-prepend'>
                    <span class='input-group-text'><i class='fa fa-fw fa-dollar-sign'></i></span>
                </div>
                <input type='text' class='form-control' inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name='quote_discount' placeholder='0.00' value="<?php echo number_format($quote_discount, 2, '.', ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Scope</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                </div>
                <input type="text" class="form-control" name="scope" placeholder="Quick description" value="<?php echo $quote_scope; ?>" maxlength="255">
            </div>
        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_quote" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";
