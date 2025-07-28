<?php

// Default Column Sortby/Order Filter
$sort = "revenue_date";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM revenues
    JOIN categories ON revenue_category_id = category_id
    LEFT JOIN accounts ON revenue_account_id = account_id
    WHERE (account_name LIKE '%$q%' OR revenue_payment_method LIKE '%$q%' OR category_name LIKE '%$q%' OR revenue_reference LIKE '%$q%' OR revenue_amount LIKE '%$q%')
    AND DATE(revenue_date) BETWEEN '$dtf' AND '$dtt'
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-hand-holding-usd mr-2"></i>Revenues</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRevenueModal"><i class="fas fa-plus mr-2"></i>New Revenue</button>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Revenues">
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
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=revenue_date&order=<?php echo $disp; ?>">
                            Date <?php if ($sort == 'revenue_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                            Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-right">
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=revenue_amount&order=<?php echo $disp; ?>">
                            Amount <?php if ($sort == 'revenue_amount') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=revenue_payment_method&order=<?php echo $disp; ?>">
                            Method <?php if ($sort == 'revenue_payment_method') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=revenue_reference&order=<?php echo $disp; ?>">
                            Reference <?php if ($sort == 'revenue_reference') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=account_name&order=<?php echo $disp; ?>">
                            Account <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $revenue_id = intval($row['revenue_id']);
                    $revenue_description = nullable_htmlentities($row['revenue_description']);
                    $revenue_reference = nullable_htmlentities($row['revenue_reference']);
                    if (empty($revenue_reference)) {
                        $revenue_reference_display = "-";
                    } else {
                        $revenue_reference_display = $revenue_reference;
                    }
                    $revenue_date = nullable_htmlentities($row['revenue_date']);
                    $revenue_payment_method = nullable_htmlentities($row['revenue_payment_method']);
                    $revenue_amount = floatval($row['revenue_amount']);
                    $revenue_currency_code = nullable_htmlentities($row['revenue_currency_code']);
                    $revenue_created_at = nullable_htmlentities($row['revenue_created_at']);
                    $account_id = intval($row['account_id']);
                    $account_name = nullable_htmlentities($row['account_name']);
                    $category_id = intval($row['category_id']);
                    $category_name = nullable_htmlentities($row['category_name']);

                    ?>

                    <tr>
                        <td>
                            <a href="#"
                                data-toggle = "ajax-modal"
                                data-modal-size = "lg"
                                data-ajax-url = "ajax/ajax_revenue_edit.php"
                                data-ajax-id = "<?php echo $revenue_id; ?>"
                                >
                                <?php echo $revenue_date; ?>
                            </a>
                        </td>
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
                                    <a class="dropdown-item" href="#"
                                        data-toggle = "ajax-modal"
                                        data-modal-size = "lg"
                                        data-ajax-url = "ajax/ajax_revenue_edit.php"
                                        data-ajax-id = "<?php echo $revenue_id; ?>"
                                        >
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_revenue=<?php echo $revenue_id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>
        </div>
        <?php require_once "../includes/filter_footer.php";
 ?>
    </div>
</div>

<?php

require_once "modals/revenue_add_modal.php";
require_once "../includes/footer.php";
