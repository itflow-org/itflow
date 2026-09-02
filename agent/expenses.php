<?php

// Default Column Sortby/Order Filter
$sort = "expense_date";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

// Account Filter
if (isset($_GET['account']) & !empty($_GET['account'])) {
    $account_query = 'AND (expense_account_id = ' . intval($_GET['account']) . ')';
    $account_filter = intval($_GET['account']);
} else {
    // Default - any
    $account_query = '';
    $account_filter = '';
}

// Vendor Filter
if (isset($_GET['vendor']) & !empty($_GET['vendor'])) {
    $vendor_query = 'AND (vendor_id = ' . intval($_GET['vendor']) . ')';
    $vendor_filter = intval($_GET['vendor']);
} else {
    // Default - any
    $vendor_query = '';
    $vendor_filter = '';
}

// Category Filter
if (isset($_GET['category']) & !empty($_GET['category'])) {
    $category_query = 'AND (category_id = ' . intval($_GET['category']) . ')';
    $category_filter = intval($_GET['category']);
} else {
    // Default - any
    $category_query = '';
    $category_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS account_name, category_name, client_name, expense_account_id, expense_amount,
        expense_category_id, expense_client_id, expense_created_at, expense_currency_code,
        expense_date, expense_description, expense_id, expense_receipt, expense_reference,
        expense_vendor_id, vendor_name FROM expenses
    LEFT JOIN categories ON expense_category_id = category_id
    LEFT JOIN vendors ON expense_vendor_id = vendor_id
    LEFT JOIN accounts ON expense_account_id = account_id
    LEFT JOIN clients ON expense_client_id = client_id
    WHERE expense_vendor_id > 0
    AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
    $vendor_query
    $category_query
    AND (vendor_name LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%' OR account_name LIKE '%$q%' OR expense_description LIKE '%$q%' OR expense_amount LIKE '%$q%')
    $account_query
    " . clientScopeSql('expense_client_id') . "
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card">
        <div class="card-header bg-dark py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-shopping-cart me-2"></i>Expenses</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/expense/expense_add.php" data-modal-size="lg"><i class="fas fa-plus me-2"></i>New Expense</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/expense/expense_export.php', ['account', 'vendor', 'category', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-header py-3">
            <form autocomplete="off">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Expenses">
                                <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="btn-group float-end">
                            <div class="dropdown ms-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/expense/expense_bulk_edit_category.php"
                                        data-bulk="true">
                                        <i class="fas fa-fw fa-list me-2"></i>Set Category
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/expense/expense_bulk_edit_account.php"
                                        data-bulk="true">
                                        <i class="fas fa-fw fa-piggy-bank me-2"></i>Set Account
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/expense/expense_bulk_edit_client.php"
                                        data-bulk="true">
                                        <i class="fas fa-fw fa-user me-2"></i>Set Client
                                    </a>
                                    <?php if ($session_user_role == 3) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold ajax-modal" href="#"
                                        data-modal-url="modals/expense/expense_bulk_delete.php"
                                        data-bulk="true">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01' || $account_filter || $vendor_filter || $category_filter) { echo"show"; } ?>" id="advancedFilter">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div>
                                <label class="form-label">Date range</label>
                                <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                                <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date']) ?? '' ?>">
                                <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                                <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div>
                                <label class="form-label">Vendor</label>
                                <select class="form-select select2" name="vendor" onchange="this.form.submit()">
                                    <option value="">- All Vendors -</option>

                                    <?php
                                    $sql_vendors_filter = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE EXISTS (SELECT 1 FROM expenses WHERE expense_vendor_id = vendor_id) ORDER BY vendor_name ASC");

                                    while ($row = mysqli_fetch_assoc($sql_vendors_filter)) {
                                        $vendor_id = intval($row['vendor_id']);
                                        $vendor_name = escapeHtml($row['vendor_name']);
                                    ?>
                                        <option <?php if ($vendor_filter == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div>
                                <label class="form-label">Category</label>
                                <select class="form-select select2" name="category" onchange="this.form.submit()">
                                    <option value="">- All Categories -</option>

                                    <?php
                                    $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND EXISTS (SELECT 1 FROM expenses WHERE expense_category_id = category_id) ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = escapeHtml($row['category_name']);
                                    ?>
                                        <option <?php if ($category_filter == $category_id) { echo "selected"; } ?> value="<?= $category_id ?>"><?= $category_name ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div>
                                <label class="form-label">Account</label>
                                <select class="form-select select2" name="account" onchange="this.form.submit()">
                                    <option value="">- All Accounts -</option>

                                    <?php
                                    $sql_accounts_filter = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE EXISTS (SELECT 1 FROM expenses WHERE expense_account_id = account_id) ORDER BY account_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_accounts_filter)) {
                                        $account_id = intval($row['account_id']);
                                        $account_name = escapeHtml($row['account_name']);
                                    ?>
                                        <option <?php if ($account_filter == $account_id) { echo "selected"; } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover mb-0">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <td class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                        </div>
                    </td>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=expense_date&order=<?= $disp ?>">
                            Date <?php if ($sort == 'expense_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">
                            Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                        </a>
                        /
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=expense_description&order=<?= $disp ?>">
                            Description <?php if ($sort == 'expense_description') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">
                            Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-end">
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=expense_amount&order=<?= $disp ?>">
                            Amount <?php if ($sort == 'expense_amount') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=account_name&order=<?= $disp ?>">
                            Account <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $expense_id = intval($row['expense_id']);
                    $expense_date = escapeHtml($row['expense_date']);
                    $expense_amount = floatval($row['expense_amount']);
                    $expense_currency_code = escapeHtml($row['expense_currency_code']);
                    $expense_description = escapeHtml($row['expense_description']);
                    $expense_receipt = escapeHtml($row['expense_receipt']);
                    $expense_reference = escapeHtml($row['expense_reference']);
                    $expense_created_at = escapeHtml($row['expense_created_at']);
                    $expense_vendor_id = intval($row['expense_vendor_id']);
                    $vendor_name = escapeHtml($row['vendor_name']);
                    $expense_category_id = intval($row['expense_category_id']);
                    $category_name = escapeHtml($row['category_name']);
                    $account_name = escapeHtml($row['account_name']);
                    $expense_account_id = intval($row['expense_account_id']);
                    $client_name = escapeHtml($row['client_name']) ?: '-';
                    $expense_client_id = intval($row['expense_client_id']);

                    if (empty($expense_receipt)) {
                        $receipt_attached = "";
                    } else {
                        $path_info = pathinfo($expense_receipt);
                        $ext = $path_info['extension'];
                        $receipt_attached = "<a class='text-secondary me-2' target='_blank' href='../uploads/expenses/$expense_receipt' download='$expense_date-$vendor_name-$category_name-$expense_id.$ext'><i class='fa fa-file'></i></a>";
                    }

                    ?>

                    <tr>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="expense_ids[]" value="<?= $expense_id ?>">
                            </div>
                        </td>
                        <td>
                            <?= $receipt_attached ?>
                            <a class="text-dark ajax-modal" href="#" title="Created: <?= $expense_created_at ?>"
                                data-modal-size="lg"
                                data-modal-url="modals/expense/expense_edit.php?id=<?= $expense_id ?>">
                                <?= $expense_date ?>
                            </a>
                        </td>
                        <td>
                            <?= $category_name ?>
                            <div class="text-secondary"><small><?= truncate($expense_description, 60) ?></small></div>
                        </td>
                        <td><?= $vendor_name ?></td>
                        <td class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $expense_amount, $expense_currency_code) ?></td>
                        <td><?= $account_name ?></td>
                        <td><?= $client_name ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <?php
                                    if (!empty($expense_receipt)) { ?>
                                        <a class="dropdown-item" href="<?= "../uploads/expenses/$expense_receipt" ?>" download="<?= "$expense_date-$vendor_name-$category_name-$expense_id.pdf" ?>">
                                            <i class="fas fa-fw fa-download me-2"></i>Download
                                        </a>
                                        <div class="dropdown-divider"></div>
                                    <?php } ?>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-size="lg"
                                        data-modal-url="modals/expense/expense_edit.php?id=<?= $expense_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-size="lg"
                                        data-modal-url="modals/expense/expense_copy.php?id=<?= $expense_id ?>">
                                        <i class="fas fa-fw fa-copy me-2"></i>Copy
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-size="lg"
                                        data-modal-url="modals/expense/expense_refund.php?id=<?= $expense_id ?>">
                                        <i class="fas fa-fw fa-undo-alt me-2"></i>Refund
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_expense=<?= $expense_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                }
                ?>

                </tbody>
            </table>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>

<script src="/js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
