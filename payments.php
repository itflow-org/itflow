<?php

// Default Column Sortby/Order Filter
$sort = "payment_date";
$order = "DESC";

require_once "inc_all.php";


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
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-credit-card mr-2"></i>Payments</h3>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
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
                        <?php
                        $paymentColumns = [
                            'payment_date' => 'Payment Date',
                            'invoice_date' => 'Invoice Date',
                            'invoice_number' => 'Invoice',
                            'client_name' => 'Client',
                            'payment_amount' => 'Amount',
                            'payment_method' => 'Payment Method',
                            'payment_reference' => 'Reference',
                            'account_name' => 'Account'
                        ];

                        foreach ($paymentColumns as $sortParam => $columnName) {
                            $class = ($sortParam === 'payment_amount') ? 'text-right' : '';
                            echo "<th class='$class'><a class='text-dark'
                            href='?$url_query_strings_sort&sort=$sortParam&order=$disp'>$columnName</a></th>";
                        }
                        ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $invoice_id = intval($row['invoice_id']);
                        $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                        $invoice_number = intval($row['invoice_number']);
                        $invoice_status = nullable_htmlentities($row['invoice_status']);
                        $invoice_date = nullable_htmlentities($row['invoice_date']);
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
                            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                            <td><a href="client_payments.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code); ?></td>
                            <td><?php echo $payment_method; ?></td>
                            <td><?php echo $payment_reference_display; ?></td>
                            <td><?php echo "$account_archived_display$account_name"; ?></td>
                        </tr>

                    <?php } ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "pagination.php";
 ?>
        </div>
    </div>

<?php require_once "footer.php";
 ?>
