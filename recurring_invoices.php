<?php

// Default Column Sortby/Order Filter
$sort = "recurring_next_date";
$order = "ASC";

require_once "inc_all.php";

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM recurring
    LEFT JOIN clients ON recurring_client_id = client_id
    LEFT JOIN categories ON recurring_category_id = category_id
    WHERE (CONCAT(recurring_prefix,recurring_number) LIKE '%$q%' OR recurring_frequency LIKE '%$q%' OR recurring_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%')
    AND DATE(recurring_created_at) BETWEEN '$dtf' AND '$dtt'
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-redo-alt mr-2"></i>Recurring Invoices</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringModal"><i class="fas fa-plus mr-2"></i>New Recurring Invoice</button>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo strip_tags(nullable_htmlentities($q));} ?>" placeholder="Search Recurring Invoices">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group float-right">
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
                            <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_number&order=<?php echo $disp; ?>">Number</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_next_date&order=<?php echo $disp; ?>">Next Date</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_scope&order=<?php echo $disp; ?>">Scope</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">Client</a></th>
                    <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_amount&order=<?php echo $disp; ?>">Amount</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_frequency&order=<?php echo $disp; ?>">Frequency</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_last_sent&order=<?php echo $disp; ?>">Last Sent</a></th>

                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">Category</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_status&order=<?php echo $disp; ?>">Status</a></th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $recurring_id = intval($row['recurring_id']);
                    $recurring_prefix = nullable_htmlentities($row['recurring_prefix']);
                    $recurring_number = intval($row['recurring_number']);
                    $recurring_scope = nullable_htmlentities($row['recurring_scope']);
                    $recurring_frequency = nullable_htmlentities($row['recurring_frequency']);
                    $recurring_status = nullable_htmlentities($row['recurring_status']);
                    $recurring_discount = floatval($row['recurring_discount_amount']);
                    $recurring_last_sent = $row['recurring_last_sent'];
                    if ($recurring_last_sent == 0) {
                        $recurring_last_sent = "-";
                    }
                    $recurring_next_date = nullable_htmlentities($row['recurring_next_date']);
                    $recurring_amount = floatval($row['recurring_amount']);
                    $recurring_currency_code = nullable_htmlentities($row['recurring_currency_code']);
                    $recurring_created_at = nullable_htmlentities($row['recurring_created_at']);
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $client_currency_code = nullable_htmlentities($row['client_currency_code']);
                    $category_id = intval($row['category_id']);
                    $category_name = nullable_htmlentities($row['category_name']);
                    if ($recurring_status == 1) {
                        $status = "Active";
                        $status_badge_color = "success";
                    } else {
                        $status = "Inactive";
                        $status_badge_color = "secondary";
                    }

                    ?>

                    <tr>
                        <td class="text-bold">
                            <a href="recurring_invoice.php?recurring_id=<?php echo $recurring_id; ?>"><?php echo "$recurring_prefix$recurring_number"; ?></a>
                        </td>
                        <td class="text-bold"><?php echo $recurring_next_date; ?></td>
                        <td><?php echo $recurring_scope; ?></td>
                        <td class="text-bold"><a href="client_recurring_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                        <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $recurring_amount, $recurring_currency_code); ?></td>
                        <td><?php echo ucwords($recurring_frequency); ?>ly</td>
                        <td><?php echo $recurring_last_sent; ?></td>
                        <td><?php echo $category_name; ?></td>
                        <td>
                            <span class="p-2 badge badge-<?php echo $status_badge_color; ?>">
                                <?php echo $status; ?>
                            </span>

                        </td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRecurringModal<?php echo $recurring_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                    require "recurring_invoice_edit_modal.php";


                    }
                    ?>

                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php";
 ?>
    </div>
</div>

<?php

require_once "recurring_invoice_add_modal.php";

require_once "footer.php";
