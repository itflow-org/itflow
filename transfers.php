<?php

$sb = "transfer_date";
$o = "DESC";

require_once("inc_all.php");

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS transfer_created_at, expense_date AS transfer_date, expense_amount AS transfer_amount, expense_account_id AS transfer_account_from, revenue_account_id AS transfer_account_to, transfer_expense_id, transfer_revenue_id , transfer_id, transfer_notes FROM transfers, expenses, revenues
    WHERE transfer_expense_id = expense_id 
    AND transfer_revenue_id = revenue_id
    AND transfers.company_id = $session_company_id
    AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
    ORDER BY $sb $o LIMIT $record_from, $record_to"
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(htmlentities($q));} ?>" placeholder="Search Transfers">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select class="form-control select2" name="canned_date">
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
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_date&o=<?php echo $disp; ?>">Date</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_account_from&o=<?php echo $disp; ?>">From Account</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_account_to&o=<?php echo $disp; ?>">To Account</a></th>
                        <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=transfer_amount&o=<?php echo $disp; ?>">Amount</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $transfer_id = intval($row['transfer_id']);
                        $transfer_date = htmlentities($row['transfer_date']);
                        $transfer_account_from = intval($row['transfer_account_from']);
                        $transfer_account_to = intval($row['transfer_account_to']);
                        $transfer_amount = floatval($row['transfer_amount']);
                        $transfer_notes = htmlentities($row['transfer_notes']);
                        $transfer_created_at = htmlentities($row['transfer_created_at']);
                        $expense_id = intval($row['transfer_expense_id']);
                        $revenue_id = intval($row['transfer_revenue_id']);

                        $sql_from = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $transfer_account_from");
                        $row = mysqli_fetch_array($sql_from);
                        $account_name_from = htmlentities($row['account_name']);

                        $sql_to = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $transfer_account_to");
                        $row = mysqli_fetch_array($sql_to);
                        $account_name_to = htmlentities($row['account_name']);

                        ?>
                        <tr>
                            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTransferModal<?php echo $transfer_id; ?>"><?php echo $transfer_date; ?></a></td>
                            <td><?php echo $account_name_from; ?></td>
                            <td><?php echo $account_name_to; ?></td>
                            <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $transfer_amount, $session_company_currency); ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTransferModal<?php echo $transfer_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold" href="post.php?delete_transfer=<?php echo $transfer_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("transfer_edit_modal.php");

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("transfer_add_modal.php");
require_once("footer.php");
