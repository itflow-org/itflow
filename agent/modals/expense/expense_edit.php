<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$expense_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT category_name, expense_account_id, expense_amount, expense_category_id, expense_client_id,
    expense_created_at, expense_currency_code, expense_date, expense_description,
    expense_receipt, expense_reference, expense_vendor_id, vendor_name FROM expenses
    LEFT JOIN vendors ON expense_vendor_id = vendor_id
    LEFT JOIN categories ON expense_category_id = category_id
    WHERE expense_id = $expense_id LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$expense_date = escapeHtml($row['expense_date']);
$expense_amount = floatval($row['expense_amount']);
$expense_currency_code = escapeHtml($row['expense_currency_code']);
$expense_description = escapeHtml($row['expense_description']);
$expense_receipt = escapeHtml($row['expense_receipt']);
$expense_reference = escapeHtml($row['expense_reference']);
$expense_created_at = escapeHtml($row['expense_created_at']);
$expense_vendor_id = intval($row['expense_vendor_id']);
$expense_category_id = intval($row['expense_category_id']);
$expense_account_id = intval($row['expense_account_id']);
$client_id = intval($row['expense_client_id']);
$vendor_name = escapeHtml($row['vendor_name']);
$category_name = escapeHtml($row['category_name']);

if ($client_id) {
    enforceClientAccess();
}

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fas fa-fw fa-shopping-cart me-2'></i>Editing expense</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="expense_id" value="<?= $expense_id ?>">

        <div class="row g-2">

            <div class="mb-3 col-md">
                <label>Date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= $expense_date ?>" required>
                </div>
            </div>

            <div class="mb-3 col-md">
                <label>Amount <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="amount" value="<?= number_format($expense_amount, 2, '.', '') ?>" placeholder="0.00" required>
                </div>
            </div>

        </div>

        <div class="row g-2">
            <div class="mb-3 col-md">
                <label>Account <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                    <select class="form-select select2" name="account" required>
                        <?php

                        $sql_accounts = mysqli_query($mysqli, "SELECT account_id, account_name, opening_balance, account_archived_at FROM accounts WHERE (account_archived_at > '$expense_created_at' OR account_archived_at IS NULL) ORDER BY account_archived_at ASC, account_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_accounts)) {
                            $account_id_select = intval($row['account_id']);
                            $account_name_select = escapeHtml($row['account_name']);
                            $opening_balance = floatval($row['opening_balance']);
                            $account_archived_at = escapeHtml($row['account_archived_at']);
                            if (empty($account_archived_at)) {
                                $account_archived_display = "";
                            } else {
                                $account_archived_display = "Archived - ";
                            }

                            $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id_select");
                            $row = mysqli_fetch_assoc($sql_payments);
                            $total_payments = floatval($row['total_payments']);

                            $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id_select");
                            $row = mysqli_fetch_assoc($sql_revenues);
                            $total_revenues = floatval($row['total_revenues']);

                            $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id_select");
                            $row = mysqli_fetch_assoc($sql_expenses);
                            $total_expenses = floatval($row['total_expenses']);

                            $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                            ?>
                            <option <?php if ($expense_account_id == $account_id_select) { ?> selected <?php } ?> value="<?= $account_id_select ?>"><?= "$account_archived_display$account_name_select" ?> [$<?= number_format($balance, 2) ?>]</option>
                            <?php
                        }

                        ?>
                    </select>
                </div>
            </div>

            <div class="mb-3 col-md">
                <label>Vendor <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="vendor" required>
                        <?php

                        $sql_select = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND (vendor_archived_at > '$expense_created_at' OR vendor_archived_at IS NULL) ORDER BY vendor_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_select)) {
                            $vendor_id_select = intval($row['vendor_id']);
                            $vendor_name_select = escapeHtml($row['vendor_name']);
                            ?>
                            <option <?php if ($expense_vendor_id == $vendor_id_select) { ?> selected <?php } ?> value="<?= $vendor_id_select ?>"><?= $vendor_name_select ?></option>
                            <?php
                        }

                        ?>
                    </select>
                        <a class="btn btn-secondary" href="vendors.php" target="_blank"><i class="fas fa-fw fa-plus"></i></a>
                </div>
            </div>

        </div>

        <div class="mb-3">
            <label>Description <strong class="text-danger">*</strong></label>
            <textarea class="form-control" rows="6" name="description" placeholder="Enter a description" required><?= $expense_description ?></textarea>
        </div>

        <div class="mb-3">
            <label>Reference</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                <input type="text" class="form-control" name="reference" placeholder="Enter a reference" maxlength="200" value="<?= $expense_reference ?>">
            </div>
        </div>

        <div class="row g-2">

            <div class="mb-3 col-md">
                <label>Category <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                    <select class="form-select select2" name="category" required>
                        <?php

                        $sql_select = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND (category_archived_at > '$expense_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_select)) {
                            $category_id_select = intval($row['category_id']);
                            $category_name_select = escapeHtml($row['category_name']);
                            ?>
                            <option <?php if ($expense_category_id == $category_id_select) { ?> selected <?php } ?> value="<?= $category_id_select ?>"><?= $category_name_select ?></option>
                            <?php
                        }

                        ?>
                    </select>
                        <button class="btn btn-secondary ajax-modal" type="button"
                            data-modal-url="../admin/modals/category/category_add.php?category=Expense">
                            <i class="fas fa-plus"></i>
                        </button>
                </div>
            </div>

            <?php if (isset($_GET['client_id'])) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } else { ?>

                <div class="mb-3 col-md">
                    <label>Client</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <select class="form-select select2" name="client_id">
                            <option value="">- Select Client -</option>
                            <?php

                            $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE 1 = 1 " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients)) {
                                $client_id_select = intval($row['client_id']);
                                $client_name_select = escapeHtml($row['client_name']);
                                ?>
                                <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name_select ?></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

            <?php } ?>

        </div>

        <div class="mb-3">
            <label>Receipt</label>
            <input type="file" class="form-control" name="file" accept="image/*, application/pdf">
        </div>

        <?php if (!empty($expense_receipt)) { ?>
            <hr>
            <a class="text-secondary" href="<?= "../uploads/expenses/$expense_receipt" ?>"
                download="<?= "$expense_date-$vendor_name-$category_name-$expense_id.pdf" ?>">
                <i class="fa fa-fw fa-2x fa-file-pdf text-secondary"></i> <?= "$expense_date-$vendor_name-$category_name-$expense_id.pdf" ?>
            </a>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_expense" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
