<?php
require_once("inc_all.php");

if (!empty($_GET['sb'])) {
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
    $sb = "recurring_next_date";
}

if (empty($_GET['canned_date'])) {
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
    $_GET['canned_date'] = 'custom';
}

//Date Filter
if ($_GET['canned_date'] == "custom" && !empty($_GET['dtf'])) {
    $dtf = strip_tags(mysqli_real_escape_string($mysqli, $_GET['dtf']));
    $dtt = strip_tags(mysqli_real_escape_string($mysqli, $_GET['dtt']));
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

if (empty($_GET['canned_date'])) {
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
    $_GET['canned_date'] = 'custom';
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM recurring
    LEFT JOIN clients ON recurring_client_id = client_id
    LEFT JOIN categories ON recurring_category_id = category_id
    WHERE recurring.company_id = $session_company_id
    AND (CONCAT(recurring_prefix,recurring_number) LIKE '%$q%' OR recurring_frequency LIKE '%$q%' OR recurring_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%')
    AND DATE(recurring_next_date) BETWEEN '$dtf' AND '$dtt'
    ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-sync-alt"></i> Recurring Invoices</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringModal"><i class="fas fa-fw fa-plus"></i> New Recurring</button>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo strip_tags(htmlentities($q));} ?>" placeholder="Search Recurring Invoices">
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
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_number&o=<?php echo $disp; ?>">Number</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_next_date&o=<?php echo $disp; ?>">Next Date</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_scope&o=<?php echo $disp; ?>">Scope</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_frequency&o=<?php echo $disp; ?>">Frequency</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
                    <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_amount&o=<?php echo $disp; ?>">Amount</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_last_sent&o=<?php echo $disp; ?>">Last Sent</a></th>

                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=recurring_status&o=<?php echo $disp; ?>">Status</a></th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $recurring_id = $row['recurring_id'];
                    $recurring_prefix = htmlentities($row['recurring_prefix']);
                    $recurring_number = htmlentities($row['recurring_number']);
                    $recurring_scope = htmlentities($row['recurring_scope']);
                    $recurring_frequency = htmlentities($row['recurring_frequency']);
                    $recurring_status = htmlentities($row['recurring_status']);
                    $recurring_last_sent = $row['recurring_last_sent'];
                    if ($recurring_last_sent == 0) {
                        $recurring_last_sent = "-";
                    }
                    $recurring_next_date = $row['recurring_next_date'];
                    $recurring_amount = floatval($row['recurring_amount']);
                    $recurring_currency_code = htmlentities($row['recurring_currency_code']);
                    $recurring_created_at = $row['recurring_created_at'];
                    $client_id = $row['client_id'];
                    $client_name = htmlentities($row['client_name']);
                    $client_currency_code = htmlentities($row['client_currency_code']);
                    $category_id = $row['category_id'];
                    $category_name = htmlentities($row['category_name']);
                    if ($recurring_status == 1) {
                        $status = "Active";
                        $status_badge_color = "success";
                    } else {
                        $status = "Inactive";
                        $status_badge_color = "secondary";
                    }

                    ?>

                    <tr>
                        <td><a href="recurring_invoice.php?recurring_id=<?php echo $recurring_id; ?>"><?php echo "$recurring_prefix$recurring_number"; ?></a></td>
                        <td><?php echo $recurring_next_date; ?></td>
                        <td><?php echo $recurring_scope; ?></td>
                        <td><?php echo ucwords($recurring_frequency); ?>ly</td>
                        <td><a href="client_recurring_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $recurring_amount, $recurring_currency_code); ?></td>
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
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editRecurringModal<?php echo $recurring_id; ?>">Edit</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">Delete</a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                    require("recurring_invoice_edit_modal.php");

                }
                ?>

                </tbody>
            </table>
        </div>
        <?php require_once("pagination.php"); ?>
    </div>
</div>

<?php

require_once("recurring_invoice_add_modal.php");
require_once("category_quick_add_modal.php");
require_once("footer.php");

?>
