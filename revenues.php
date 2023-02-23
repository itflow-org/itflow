<?php

require_once("inc_all.php");

if (!empty($_GET['sb'])) {
    $sb = sanitizeInput($_GET['sb']);
} else {
    $sb = "revenue_date";
}

// Reverse default sort
if (!isset($_GET['o'])) {
    $o = "DESC";
    $disp = "ASC";
}

if (empty($_GET['canned_date'])) {
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
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
    $dtf = "0000-00-00";
    $dtt = "9999-00-00";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM revenues
    JOIN categories ON revenue_category_id = category_id
    LEFT JOIN accounts ON revenue_account_id = account_id
    WHERE revenues.company_id = $session_company_id
    AND (account_name LIKE '%$q%' OR revenue_payment_method LIKE '%$q%' OR category_name LIKE '%$q%' OR revenue_reference LIKE '%$q%' OR revenue_amount LIKE '%$q%')
    AND DATE(revenue_date) BETWEEN '$dtf' AND '$dtt'
    ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card mr-2"></i>Revenues</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRevenueModal"><i class="fas fa-plus mr-2"></i>New Revenue</button>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(htmlentities($q));} ?>" placeholder="Search Revenues">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (!empty($_GET['dtf'])) { echo "show"; } ?>" id="advancedFilter">
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
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_date&o=<?php echo $disp; ?>">Date</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
                    <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_amount&o=<?php echo $disp; ?>">Amount</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_payment_method&o=<?php echo $disp; ?>">Method</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=revenue_reference&o=<?php echo $disp; ?>">Reference</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=account_name&o=<?php echo $disp; ?>">Account</a></th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $revenue_id = intval($row['revenue_id']);
                    $revenue_description = htmlentities($row['revenue_description']);
                    $revenue_reference = htmlentities($row['revenue_reference']);
                    if (empty($revenue_reference)) {
                        $revenue_reference_display = "-";
                    } else {
                        $revenue_reference_display = $revenue_reference;
                    }
                    $revenue_date = htmlentities($row['revenue_date']);
                    $revenue_payment_method = htmlentities($row['revenue_payment_method']);
                    $revenue_amount = floatval($row['revenue_amount']);
                    $revenue_currency_code = htmlentities($row['revenue_currency_code']);
                    $revenue_created_at = htmlentities($row['revenue_created_at']);
                    $account_id = intval($row['account_id']);
                    $account_name = htmlentities($row['account_name']);
                    $category_id = intval($row['category_id']);
                    $category_name = htmlentities($row['category_name']);

                    ?>

                    <tr>
                        <td><a href="#" data-toggle="modal" data-target="#editRevenueModal<?php echo $revenue_id; ?>"><?php echo $revenue_date; ?></a></td>
                        <td><?php echo $category_name; ?></td>
                        <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $revenue_amount, $revenue_currency_code); ?></td>
                        <td><?php echo $revenue_payment_method; ?></td>
                        <td><?php echo $revenue_reference_display; ?></td>
                        <td><?php echo $account_name; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRevenueModal<?php echo $revenue_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger" href="post.php?delete_revenue=<?php echo $revenue_id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                            <?php

                            require("revenue_edit_modal.php");

                            ?>
                        </td>
                    </tr>

                <?php } ?>


                </tbody>
            </table>
        </div>
        <?php require_once("pagination.php"); ?>
    </div>
</div>

<?php

require_once("revenue_add_modal.php");
require_once("category_quick_add_modal.php");

require_once("footer.php");

?>
