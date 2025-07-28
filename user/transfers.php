<?php

$sort = "transfer_date";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

// Account Transfer From Filter
if (isset($_GET['account_from']) & !empty($_GET['account_from'])) {
    $account_from_query = 'AND (expense_account_id = ' . intval($_GET['account_from']) . ')';
    $account_from_filter = intval($_GET['account_from']);
} else {
    // Default - any
    $account_from_query = '';
    $account_from_filter = '';
}

// Account Transfer To Filter
if (isset($_GET['account_to']) & !empty($_GET['account_to'])) {
    $account_to_query = 'AND (revenue_account_id = ' . intval($_GET['account_to']) . ')';
    $account_to_filter = intval($_GET['account_to']);
} else {
    // Default - any
    $account_to_query = '';
    $account_to_filter = '';
}


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS transfer_created_at, expense_date AS transfer_date, expense_amount AS transfer_amount, expense_account_id AS transfer_account_from, revenue_account_id AS transfer_account_to, transfer_expense_id, transfer_revenue_id , transfer_id, transfer_method, transfer_notes FROM transfers, expenses, revenues
    WHERE transfer_expense_id = expense_id
    AND transfer_revenue_id = revenue_id
    $account_from_query
    $account_to_query
    AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
    AND (transfer_notes LIKE '%$q%' OR transfer_method LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-exchange-alt mr-2"></i>Transfers</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTransferModal"><i class="fas fa-plus mr-2"></i>New Transfer</button>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Transfers">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" || $account_from_filter || $account_to_filter ) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
                                    <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="custom">Custom</option>
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
                                <label>Account From</label>
                                <select class="form-control select2" name="account_from" onchange="this.form.submit()">
                                    <option value="">- All Accounts -</option>

                                    <?php
                                    $sql_accounts_from_filter = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                                    while ($row = mysqli_fetch_array($sql_accounts_from_filter)) {
                                        $account_id = intval($row['account_id']);
                                        $account_name = nullable_htmlentities($row['account_name']);
                                    ?>
                                        <option <?php if ($account_from_filter == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Account To</label>
                                <select class="form-control select2" name="account_to" onchange="this.form.submit()">
                                    <option value="">- All Accounts -</option>

                                    <?php
                                    $sql_accounts_to_filter = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                                    while ($row = mysqli_fetch_array($sql_accounts_to_filter)) {
                                        $account_id = intval($row['account_id']);
                                        $account_name = nullable_htmlentities($row['account_name']);
                                    ?>
                                        <option <?php if ($account_to_filter == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
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
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transfer_date&order=<?php echo $disp; ?>">
                                Date <?php if ($sort == 'transfer_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transfer_account_from&order=<?php echo $disp; ?>">
                                From Account <?php if ($sort == 'transfer_account_from') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transfer_account_to&order=<?php echo $disp; ?>">
                                To Account <?php if ($sort == 'transfer_account_to') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transfer_method&order=<?php echo $disp; ?>">
                                Method <?php if ($sort == 'transfer_method') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transfer_notes&order=<?php echo $disp; ?>">
                                Notes <?php if ($sort == 'transfer_notes') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-right">
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transfer_amount&order=<?php echo $disp; ?>">
                                Amount <?php if ($sort == 'transfer_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $transfer_id = intval($row['transfer_id']);
                        $transfer_date = nullable_htmlentities($row['transfer_date']);
                        $transfer_account_from = intval($row['transfer_account_from']);
                        $transfer_account_to = intval($row['transfer_account_to']);
                        $transfer_amount = floatval($row['transfer_amount']);
                        $transfer_method = nullable_htmlentities($row['transfer_method']);
                        if($transfer_method) {
                            $transfer_method_display = $transfer_method;
                        } else {  
                            $transfer_method_display = "-";
                        }
                        $transfer_notes = nullable_htmlentities($row['transfer_notes']);
                        if(empty($transfer_notes)) {
                            $transfer_notes_display = "-";
                        } else {
                            $transfer_notes_display = nl2br($transfer_notes);
                        }
                        $transfer_created_at = nullable_htmlentities($row['transfer_created_at']);
                        $expense_id = intval($row['transfer_expense_id']);
                        $revenue_id = intval($row['transfer_revenue_id']);

                        $sql_from = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $transfer_account_from");
                        $row = mysqli_fetch_array($sql_from);
                        $account_name_from = nullable_htmlentities($row['account_name']);
                        $account_from_archived_at = nullable_htmlentities($row['account_archived_at']);
                        if (empty($account_from_archived_at)) {
                            $account_from_archived_display = "";
                        } else {
                            $account_from_archived_display = "Archived - ";
                        }

                        $sql_to = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $transfer_account_to");
                        $row = mysqli_fetch_array($sql_to);
                        $account_name_to = nullable_htmlentities($row['account_name']);
                        $account_to_archived_at = nullable_htmlentities($row['account_archived_at']);
                        if (empty($account_to_archived_at)) {
                            $account_to_archived_display = "";
                        } else {
                            $account_to_archived_display = "Archived - ";
                        }

                        ?>
                        <tr>
                            <td>
                                <a class="text-dark" href="#"
                                    data-toggle = "ajax-modal"
                                    data-ajax-url = "ajax/ajax_transfer_edit.php"
                                    data-ajax-id = "<?php echo $transfer_id; ?>"
                                    >
                                    <?php echo $transfer_date; ?>
                                </a>
                            </td>
                            <td><?php echo "$account_from_archived_display$account_name_from"; ?></td>
                            <td><?php echo "$account_to_archived_display$account_name_to"; ?></td>
                            <td><?php echo $transfer_method_display; ?></td>
                            <td><?php echo $transfer_notes_display; ?></td>
                            <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $transfer_amount, $session_company_currency); ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#"
                                            data-toggle = "ajax-modal"
                                            data-ajax-url = "ajax/ajax_transfer_edit.php"
                                            data-ajax-id = "<?php echo $transfer_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_transfer=<?php echo $transfer_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
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
            <?php require_once "../includes/filter_footer.php";
 ?>
        </div>
    </div>

<?php
require_once "modals/transfer_add_modal.php";

require_once "../includes/footer.php";

