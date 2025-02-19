<?php

// Default Column Sortby/Order Filter
$sort = "payment_date";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND invoice_client_id = $client_id";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
}

// Perms
enforceUserPermission('module_financial');

// Payment Method Filter
if (isset($_GET['method']) & !empty($_GET['method'])) {
    $payment_method_query = "AND (payment_method  = '" . sanitizeInput($_GET['method']) . "')";
    $method_filter = nullable_htmlentities($_GET['method']);
} else {
    // Default - any
    $payment_method_query = '';
    $method_filter = '';
}

// Account Filter
if (isset($_GET['account']) & !empty($_GET['account'])) {
    $account_query = 'AND (payment_account_id = ' . intval($_GET['account']) . ')';
    $account_filter = intval($_GET['account']);
} else {
    // Default - any
    $account_query = '';
    $account_filter = '';
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM payments
    LEFT JOIN invoices ON payment_invoice_id = invoice_id
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN accounts ON payment_account_id = account_id
    WHERE DATE(payment_date) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR account_name LIKE '%$q%' OR payment_method LIKE '%$q%' OR payment_reference LIKE '%$q%')
    $account_query
    $payment_method_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card mr-2"></i>Payments</h3>
            <?php if ($num_rows[0] > 0) { ?>
            <div class="card-tools">
                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportPaymentModal"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
            </div>
            <?php } ?>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <?php if(isset($_GET['client_id'])) { ?>
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="Search Payments">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <select class="form-control select2" name="account" onchange="this.form.submit()">
                                <option value="">- All Accounts -</option>

                                <?php
                                $sql_accounts_filter = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_array($sql_accounts_filter)) {
                                    $account_id = intval($row['account_id']);
                                    $account_name = nullable_htmlentities($row['account_name']);
                                ?>
                                    <option <?php if ($account_filter == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="form-group">
                            <select class="form-control select2" name="method" onchange="this.form.submit()">
                                <option value="">- All Payment Methods -</option>

                                <?php
                                $sql_payment_methods_filter = mysqli_query($mysqli, "SELECT DISTINCT payment_method FROM payments ORDER BY payment_method ASC");
                                while ($row = mysqli_fetch_array($sql_payment_methods_filter)) {
                                    $payment_method = nullable_htmlentities($row['payment_method']);
                                ?>
                                    <option <?php if ($method_filter == $payment_method) { echo "selected"; } ?>><?php echo $payment_method; ?></option>
                                <?php
                                }
                                ?>

                            </select>
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
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_date&order=<?php echo $disp; ?>">
                                Payment Date <?php if ($sort == 'payment_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_date&order=<?php echo $disp; ?>">
                                Invoice Date <?php if ($sort == 'invoice_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_number&order=<?php echo $disp; ?>">
                                Invoice <?php if ($sort == 'invoice_number') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if(!isset($_GET['client_id'])) { ?>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php } ?>
                        <th class="text-right">
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_amount&order=<?php echo $disp; ?>">
                                Invoice Amount <?php if ($sort == 'invoice_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-right">
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_amount&order=<?php echo $disp; ?>">
                                Payment Amount <?php if ($sort == 'payment_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_method&order=<?php echo $disp; ?>">
                                Payment Method <?php if ($sort == 'payment_method') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_reference&order=<?php echo $disp; ?>">
                                Reference <?php if ($sort == 'payment_reference') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=account_name&order=<?php echo $disp; ?>">
                                Account <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $invoice_id = intval($row['invoice_id']);
                        $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                        $invoice_number = intval($row['invoice_number']);
                        $invoice_status = nullable_htmlentities($row['invoice_status']);
                        $invoice_amount = floatval($row['invoice_amount']);
                        $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
                        $invoice_date = nullable_htmlentities($row['invoice_date']);
                        $payment_id = intval($row['payment_id']);
                        $payment_date = nullable_htmlentities($row['payment_date']);
                        $payment_method = nullable_htmlentities($row['payment_method']);
                        $payment_amount = floatval($row['payment_amount']);
                        $payment_currency_code = nullable_htmlentities($row['payment_currency_code']);
                        $payment_reference = nullable_htmlentities($row['payment_reference']);
                        if (empty($payment_reference)) {
                            $payment_reference_display = "-";
                        } else {
                            $payment_reference_display = $payment_reference;
                        }
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        $account_name = nullable_htmlentities($row['account_name']);
                        $account_archived_at = nullable_htmlentities($row['account_archived_at']);
                        if (empty($account_archived_at)) {
                            $account_archived_display = "";
                        } else {
                            $account_archived_display = "Archived - ";
                        }

                        ?>

                        <tr>
                            <td><?php echo $payment_date; ?></td>
                            <td><?php echo $invoice_date; ?></td>
                            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?><?php if (isset($_GET['client_id'])) { echo "&client_id=$client_id"; } ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                            <?php if(!isset($_GET['client_id'])) { ?>
                            <td><a href="payments.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <?php } ?>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code); ?></td>
                            <td><?php echo $payment_method; ?></td>
                            <td><?php echo $payment_reference_display; ?></td>
                            <td><?php echo "$account_archived_display$account_name"; ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_payment=<?php echo $payment_id; ?>">
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
            <?php require_once "includes/filter_footer.php"; ?>
        </div>
    </div>

<?php
require_once "modals/payment_export_modal.php";
require_once "includes/footer.php";
