<?php

require_once("inc_all.php");

if (!empty($_GET['sb'])) {
    $sb = sanitizeInput($_GET['sb']);
} else {
    $sb = "expense_date";
}

// Reverse default sort
if (!isset($_GET['o'])) {
    $o = "DESC";
    $disp = "ASC";
}

if (empty($_GET['canned_date'])) {
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 1000-01-01 / 9999-01-01
    $_GET['canned_date'] = 'custom';
}

//Date Filter
if ($_GET['canned_date'] == "custom" && !empty($_GET['dtf'])) {
    $dtf = sanitizeInput($_GET['dtf']);
    $dtt = sanitizeInput($_GET['dtt']);
} elseif ($_GET['canned_date'] == "today") {
    $dtf = date('Y-m-d');
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "yesterday") {
    $dtf = date('Y-m-d', strtotime("yesterday"));
    $dtt = date('Y-m-d', strtotime("yesterday"));
} elseif ($_GET['canned_date'] == "thisweek") {
    $dtf = date('Y-m-d', strtotime("monday this week"));
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "lastweek") {
    $dtf = date('Y-m-d', strtotime("monday last week"));
    $dtt = date('Y-m-d', strtotime("sunday last week"));
} elseif ($_GET['canned_date'] == "thismonth") {
    $dtf = date('Y-m-01');
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "lastmonth") {
    $dtf = date('Y-m-d', strtotime("first day of last month"));
    $dtt = date('Y-m-d', strtotime("last day of last month"));
} elseif ($_GET['canned_date'] == "thisyear") {
    $dtf = date('Y-01-01');
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "lastyear") {
    $dtf = date('Y-m-d', strtotime("first day of january last year"));
    $dtt = date('Y-m-d', strtotime("last day of december last year"));
} else {
    $dtf = "1000-01-01";
    $dtt = "9999-01-01";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM expenses
    LEFT JOIN categories ON expense_category_id = category_id
    LEFT JOIN vendors ON expense_vendor_id = vendor_id
    LEFT JOIN accounts ON expense_account_id = account_id
    WHERE expenses.company_id = $session_company_id
    AND expense_vendor_id > 0
    AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
    AND (vendor_name LIKE '%$q%' OR category_name LIKE '%$q%' OR account_name LIKE '%$q%' OR expense_description LIKE '%$q%' OR expense_amount LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-shopping-cart mr-2"></i>Expenses</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addExpenseModal"><i class="fas fa-plus mr-2"></i>New Expense</button>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(htmlentities($q));} ?>" placeholder="Search Expenses">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="float-right">
                            <button type="button" class="btn btn-default btn-lg" data-toggle="modal" data-target="#exportExpensesModal"><i class="fas fa-fw fa-download mr-2"></i>Export</button>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (!empty($_GET['dtf'])) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select class="form-control select2" name="canned_date">
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
                                <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo htmlentities($dtt); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=expense_date&o=<?php echo $disp; ?>">Date</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_name&o=<?php echo $disp; ?>">Vendor</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=expense_description&o=<?php echo $disp; ?>">Description</a></th>
                        <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=expense_amount&o=<?php echo $disp; ?>">Amount</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=account_name&o=<?php echo $disp; ?>">Account</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $expense_id = intval($row['expense_id']);
                        $expense_date = htmlentities($row['expense_date']);
                        $expense_amount = floatval($row['expense_amount']);
                        $expense_currency_code = htmlentities($row['expense_currency_code']);
                        $expense_description = htmlentities($row['expense_description']);
                        $expense_receipt = htmlentities($row['expense_receipt']);
                        $expense_reference = htmlentities($row['expense_reference']);
                        $expense_created_at = htmlentities($row['expense_created_at']);
                        $expense_vendor_id = intval($row['expense_vendor_id']);
                        $vendor_name = htmlentities($row['vendor_name']);
                        $expense_category_id = intval($row['expense_category_id']);
                        $category_name = htmlentities($row['category_name']);
                        $account_name = htmlentities($row['account_name']);
                        $expense_account_id = intval($row['expense_account_id']);

                        if (empty($expense_receipt)) {
                            $receipt_attached = "";
                        } else {
                            $receipt_attached = "<a class='text-secondary mr-2' target='_blank' href='uploads/expenses/$session_company_id/$expense_receipt'><i class='fa fa-file-pdf'></i></a>";
                        }

                        ?>

                        <tr>
                            <td><?php echo $receipt_attached; ?> <a class="text-dark" href="#" data-toggle="modal" data-target="#editExpenseModal<?php echo $expense_id; ?>"><?php echo $expense_date; ?></a></td>
                            <td><?php echo $vendor_name; ?></td>
                            <td><?php echo $category_name; ?></td>
                            <td><?php echo truncate($expense_description, 50); ?></td>
                            <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $expense_amount, $expense_currency_code); ?></td>
                            <td><?php echo $account_name; ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php
                                        if (!empty($expense_receipt)) { ?>
                                            <a class="dropdown-item" href="<?php echo "uploads/expenses/$session_company_id/$expense_receipt"; ?>" download="<?php echo "$expense_date-$vendor_name-$category_name-$expense_id.pdf"; ?>">
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
                                        <a class="dropdown-item text-danger text-bold" href="post.php?delete_expense=<?php echo $expense_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("expense_edit_modal.php");
                        require("expense_copy_modal.php");
                        require("expense_refund_modal.php");
                        require("expense_export_modal.php");

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("expense_add_modal.php");
require_once("category_quick_add_modal.php");
require_once("footer.php");
