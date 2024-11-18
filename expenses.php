<?php

// Default Column Sortby/Order Filter
$sort = "expense_date";
$order = "DESC";

require_once "inc_all.php";

// Perms
enforceUserPermission('module_financial');

// Account Filter
if (isset($_GET['account']) & !empty($_GET['account'])) {
    $account_query = 'AND (expense_account_id = ' . intval($_GET['account']) . ')';
    $account = intval($_GET['account']);
} else {
    // Default - any
    $account_query = '';
    $account = '';
}

// Vendor Filter
if (isset($_GET['vendor']) & !empty($_GET['vendor'])) {
    $vendor_query = 'AND (vendor_id = ' . intval($_GET['vendor']) . ')';
    $vendor = intval($_GET['vendor']);
} else {
    // Default - any
    $vendor_query = '';
    $vendor = '';
}

// Category Filter
if (isset($_GET['category']) & !empty($_GET['category'])) {
    $category_query = 'AND (category_id = ' . intval($_GET['category']) . ')';
    $category = intval($_GET['category']);
} else {
    // Default - any
    $category_query = '';
    $category = '';
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM expenses
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
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-shopping-cart mr-2"></i>Expenses</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addExpenseModal"><i class="fas fa-plus mr-2"></i>New Expense</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportExpensesModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Expenses">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="btn-group float-right">
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditCategoryModal">
                                        <i class="fas fa-fw fa-list mr-2"></i>Set Category
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditAccountModal">
                                        <i class="fas fa-fw fa-piggy-bank mr-2"></i>Set Account
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditClientModal">
                                        <i class="fas fa-fw fa-user mr-2"></i>Set Client
                                    </a>
                                    <?php if ($session_user_role == 3) { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger text-bold"
                                            type="submit" form="bulkActions" name="bulk_delete_expenses">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (isset($_GET['dtf']) || $_GET['canned_date'] !== "custom" || isset($_GET['account']) || isset($_GET['vendor']) || isset($_GET['category'])) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
                                    <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="">Custom</option>
                                    <option <?php if ($_GET['canned_date'] == "today") { echo "selected"; } ?> value="today">Today</option>
                                    <option <?php if ($_GET['canned_date'] == "yesterday") { echo "selected"; } ?> value="yesterday">Yesterday</option>
                                    <option <?php if ($_GET['canned_date'] == "thisweek") { echo "selected"; } ?> value="thisweek">This Week</option>
                                    <option <?php if ($_GET['canned_date'] == "lastweek") { echo "selected"; } ?> value="lastweek">Last Week</option>
                                    <option <?php if ($_GET['canned_date'] == "thismonth") { echo "selected"; } ?> value="thismonth">This Month</option>
                                    <option <?php if ($_GET['canned_date'] == "lastmonth") { echo "selected"; } ?> value="lastmonth">Last Month</option>
                                    <option <?php if ($_GET['canned_date'] == "thisyear") { echo "selected"; } ?> value="thisyear">This Year</option>
                                    <option <?php if ($_GET['canned_date'] == "lastyear") { echo "selected"; } ?> value="lastyear">Last Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Vendor</label>
                                <select class="form-control select2" name="vendor" onchange="this.form.submit()">
                                    <option value="" <?php if ($vendor == "") { echo "selected"; } ?>>- All Vendors -</option>

                                    <?php
                                    $sql_vendors_filter = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_client_id = 0 AND vendor_template = 0 ORDER BY vendor_name ASC");
                                    while ($row = mysqli_fetch_array($sql_vendors_filter)) {
                                        $vendor_id = intval($row['vendor_id']);
                                        $vendor_name = nullable_htmlentities($row['vendor_name']);
                                    ?>
                                        <option <?php if ($vendor == $vendor_id) { echo "selected"; } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Category</label>
                                <select class="form-control select2" name="category" onchange="this.form.submit()">
                                    <option value="" <?php if ($category == "") { echo "selected"; } ?>>- All Categories -</option>

                                    <?php
                                    $sql_categories_filter = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Expense' ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql_categories_filter)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = nullable_htmlentities($row['category_name']);
                                    ?>
                                        <option <?php if ($category == $category_id) { echo "selected"; } ?> value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Account</label>
                                <select class="form-control select2" name="account" onchange="this.form.submit()">
                                    <option value="" <?php if ($account == "") { echo "selected"; } ?>>- All Accounts -</option>

                                    <?php
                                    $sql_accounts_filter = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                                    while ($row = mysqli_fetch_array($sql_accounts_filter)) {
                                        $account_id = intval($row['account_id']);
                                        $account_name = nullable_htmlentities($row['account_name']);
                                    ?>
                                        <option <?php if ($account == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <form id="bulkActions" action="post.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <td class="bg-light pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=expense_date&order=<?php echo $disp; ?>">
                                    Date <?php if ($sort == 'expense_date') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">
                                    Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                                    Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=expense_description&order=<?php echo $disp; ?>">
                                    Description <?php if ($sort == 'expense_description') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-right">
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=expense_amount&order=<?php echo $disp; ?>">
                                    Amount <?php if ($sort == 'expense_amount') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=account_name&order=<?php echo $disp; ?>">
                                    Account <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $expense_id = intval($row['expense_id']);
                            $expense_date = nullable_htmlentities($row['expense_date']);
                            $expense_amount = floatval($row['expense_amount']);
                            $expense_currency_code = nullable_htmlentities($row['expense_currency_code']);
                            $expense_description = nullable_htmlentities($row['expense_description']);
                            $expense_receipt = nullable_htmlentities($row['expense_receipt']);
                            $expense_reference = nullable_htmlentities($row['expense_reference']);
                            $expense_created_at = nullable_htmlentities($row['expense_created_at']);
                            $expense_vendor_id = intval($row['expense_vendor_id']);
                            $vendor_name = nullable_htmlentities($row['vendor_name']);
                            $expense_category_id = intval($row['expense_category_id']);
                            $category_name = nullable_htmlentities($row['category_name']);
                            $account_name = nullable_htmlentities($row['account_name']);
                            $expense_account_id = intval($row['expense_account_id']);
                            $client_name = nullable_htmlentities($row['client_name']);
                            if(empty($client_name)) {
                                $client_name_display = "-";
                            } else {
                                $client_name_display = $client_name;
                            }
                            $expense_client_id = intval($row['expense_client_id']);

                            if (empty($expense_receipt)) {
                                $receipt_attached = "";
                            } else {
                                $path_info = pathinfo($expense_receipt);
                                $ext = $path_info['extension'];
                                $receipt_attached = "<a class='text-secondary mr-2' target='_blank' href='uploads/expenses/$expense_receipt' download='$expense_date-$vendor_name-$category_name-$expense_id.$ext'><i class='fa fa-file'></i></a>";
                            }

                            ?>

                            <tr>
                                <td class="pr-0 bg-light">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="expense_ids[]" value="<?php echo $expense_id ?>">
                                    </div>
                                </td>
                                <td><?php echo $receipt_attached; ?> <a class="text-dark" href="#" title="Created: <?php echo $expense_created_at; ?>" data-toggle="modal" data-target="#editExpenseModal<?php echo $expense_id; ?>"><?php echo $expense_date; ?></a></td>
                                <td><?php echo $vendor_name; ?></td>
                                <td><?php echo $category_name; ?></td>
                                <td><?php echo truncate($expense_description, 50); ?></td>
                                <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $expense_amount, $expense_currency_code); ?></td>
                                <td><?php echo $account_name; ?></td>
                                <td><?php echo $client_name_display; ?></td>
                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <?php
                                            if (!empty($expense_receipt)) { ?>
                                                <a class="dropdown-item" href="<?php echo "uploads/expenses/$expense_receipt"; ?>" download="<?php echo "$expense_date-$vendor_name-$category_name-$expense_id.pdf"; ?>">
                                                    <i class="fas fa-fw fa-download mr-2"></i>Download
                                                </a>
                                                <div class="dropdown-divider"></div>
                                            <?php } ?>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editExpenseModal<?php echo $expense_id; ?>">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addExpenseCopyModal<?php echo $expense_id; ?>">
                                                <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addExpenseRefundModal<?php echo $expense_id; ?>">
                                                <i class="fas fa-fw fa-undo-alt mr-2"></i>Refund
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_expense=<?php echo $expense_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <?php

                            require "expense_edit_modal.php";

                            require "expense_copy_modal.php";

                            require "expense_refund_modal.php";


                        }

                        ?>

                        </tbody>
                    </table>
                </div>
                <?php require_once "expense_bulk_edit_category_modal.php"; ?>
                <?php require_once "expense_bulk_edit_account_modal.php"; ?>
                <?php require_once "expense_bulk_edit_client_modal.php"; ?>
            </form>
            <?php require_once "pagination.php";
 ?>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php
require_once "expense_add_modal.php";
require_once "expense_export_modal.php";

require_once "footer.php";
