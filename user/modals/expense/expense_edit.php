<?php

require_once '../../../includes/modal_header.php';

$expense_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM expenses
    LEFT JOIN vendors ON expense_vendor_id = vendor_id
    LEFT JOIN categories ON expense_category_id = category_id
    WHERE expense_id = $expense_id LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$expense_date = nullable_htmlentities($row['expense_date']);
$expense_amount = floatval($row['expense_amount']);
$expense_currency_code = nullable_htmlentities($row['expense_currency_code']);
$expense_description = nullable_htmlentities($row['expense_description']);
$expense_receipt = nullable_htmlentities($row['expense_receipt']);
$expense_reference = nullable_htmlentities($row['expense_reference']);
$expense_created_at = nullable_htmlentities($row['expense_created_at']);
$expense_vendor_id = intval($row['expense_vendor_id']);
$expense_category_id = intval($row['expense_category_id']);
$expense_account_id = intval($row['expense_account_id']);
$expense_client_id = intval($row['expense_client_id']);
$vendor_name = nullable_htmlentities($row['vendor_name']);
$category_name = nullable_htmlentities($row['category_name']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fas fa-fw fa-shopping-cart mr-2'></i>Editing expense</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">

        <div class="form-row">

            <div class="form-group col-md">
                <label>Date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    </div>
                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo $expense_date; ?>" required>
                </div>
            </div>

            <div class="form-group col-md">
                <label>Amount <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    </div>
                    <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="amount" value="<?php echo number_format($expense_amount, 2, '.', ''); ?>" placeholder="0.00" required>
                </div>
            </div>

        </div>

        <div class="form-row">
            <div class="form-group col-md">
                <label>Account <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                    </div>
                    <select class="form-control select2" name="account" required>
                        <?php

                        $sql_accounts = mysqli_query($mysqli, "SELECT account_id, account_name, opening_balance, account_archived_at FROM accounts WHERE (account_archived_at > '$expense_created_at' OR account_archived_at IS NULL) ORDER BY account_archived_at ASC, account_name ASC");
                        while ($row = mysqli_fetch_array($sql_accounts)) {
                            $account_id_select = intval($row['account_id']);
                            $account_name_select = nullable_htmlentities($row['account_name']);
                            $opening_balance = floatval($row['opening_balance']);
                            $account_archived_at = nullable_htmlentities($row['account_archived_at']);
                            if (empty($account_archived_at)) {
                                $account_archived_display = "";
                            } else {
                                $account_archived_display = "Archived - ";
                            }

                            $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id_select");
                            $row = mysqli_fetch_array($sql_payments);
                            $total_payments = floatval($row['total_payments']);

                            $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id_select");
                            $row = mysqli_fetch_array($sql_revenues);
                            $total_revenues = floatval($row['total_revenues']);

                            $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id_select");
                            $row = mysqli_fetch_array($sql_expenses);
                            $total_expenses = floatval($row['total_expenses']);

                            $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                            ?>
                            <option <?php if ($expense_account_id == $account_id_select) { ?> selected <?php } ?> value="<?php echo $account_id_select; ?>"><?php echo "$account_archived_display$account_name_select"; ?> [$<?php echo number_format($balance, 2); ?>]</option>
                            <?php
                        }

                        ?>
                    </select>
                </div>
            </div>

            <div class="form-group col-md">
                <label>Vendor <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    </div>
                    <select class="form-control select2" name="vendor" required>
                        <?php

                        $sql_select = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND (vendor_archived_at > '$expense_created_at' OR vendor_archived_at IS NULL) ORDER BY vendor_name ASC");
                        while ($row = mysqli_fetch_array($sql_select)) {
                            $vendor_id_select = intval($row['vendor_id']);
                            $vendor_name_select = nullable_htmlentities($row['vendor_name']);
                            ?>
                            <option <?php if ($expense_vendor_id == $vendor_id_select) { ?> selected <?php } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                            <?php
                        }

                        ?>
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-secondary" type="button"
                            data-toggle="ajax-modal"
                            data-modal-size="sm"
                            data-ajax-url="ajax/ajax_category_add.php?category=Expense">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <div class="form-group">
            <label>Description <strong class="text-danger">*</strong></label>
            <textarea class="form-control" rows="6" name="description" placeholder="Enter a description" required><?php echo $expense_description; ?></textarea>
        </div>

        <div class="form-group">
            <label>Reference</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                </div>
                <input type="text" class="form-control" name="reference" placeholder="Enter a reference" maxlength="200" value="<?php echo $expense_reference; ?>">
            </div>
        </div>

        <div class="form-row">

            <div class="form-group col-md">
                <label>Category <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                    </div>
                    <select class="form-control select2" name="category" required>
                        <?php

                        $sql_select = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND (category_archived_at > '$expense_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_array($sql_select)) {
                            $category_id_select = intval($row['category_id']);
                            $category_name_select = nullable_htmlentities($row['category_name']);
                            ?>
                            <option <?php if ($expense_category_id == $category_id_select) { ?> selected <?php } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                            <?php
                        }

                        ?>
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-secondary" type="button"
                            data-toggle="ajax-modal"
                            data-modal-size="sm"
                            data-ajax-url="../admin/ajax/ajax_category_add.php?category=Expense">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['client_id'])) { ?>
                <input type="hidden" name="client" value="<?php echo $client_id; ?>">
            <?php } else { ?>

                <div class="form-group col-md">
                    <label>Client</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="client">
                            <option value="">- Select Client -</option>
                            <?php

                            $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_array($sql_clients)) {
                                $client_id_select = intval($row['client_id']);
                                $client_name_select = nullable_htmlentities($row['client_name']);
                                ?>
                                <option <?php if ($expense_client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo $client_name_select; ?></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

            <?php } ?>

        </div>

        <div class="form-group">
            <label>Receipt</label>
            <input type="file" class="form-control-file" name="file" accept="image/*, application/pdf">
        </div>

        <?php if (!empty($expense_receipt)) { ?>
            <hr>
            <a class="text-secondary" href="<?php echo "../uploads/expenses/$expense_receipt"; ?>"
                download="<?php echo "$expense_date-$vendor_name-$category_name-$expense_id.pdf" ?>">
                <i class="fa fa-fw fa-2x fa-file-pdf text-secondary"></i> <?php echo "$expense_date-$vendor_name-$category_name-$expense_id.pdf" ?>
            </a>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_expense" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';

?>

