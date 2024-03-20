<?php

// Default Column Sortby/Order Filter
$sort = "client_id";
$order = "DESC";

require_once "inc_all.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM credits
    LEFT JOIN clients ON credit_client_id = client_id
    LEFT JOIN accounts ON credit_account_id = account_id
    WHERE credit_archived_at IS NULL
    ORDER BY $sort $order"
);

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-credit-card mr-2"></i>Credits</h3>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q"
                            value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>"
                            placeholder="Search Credits">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse"
                                data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>"
                id="advancedFilter">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Canned Date</label>
                            <select class="form-control select2" name="canned_date">
                                <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?>
                                    value="custom">Custom</option>
                                <option <?php if ($_GET['canned_date'] == "today") { echo "selected"; } ?>
                                    value="today">Today</option>
                                <option <?php if ($_GET['canned_date'] == "yesterday") { echo "selected"; } ?>
                                    value="yesterday">Yesterday</option>
                                <option <?php if ($_GET['canned_date'] == "thisweek") { echo "selected"; } ?>
                                    value="thisweek">This Week</option>
                                <option <?php if ($_GET['canned_date'] == "lastweek") { echo "selected"; } ?>
                                    value="lastweek">Last Week</option>
                                <option <?php if ($_GET['canned_date'] == "thismonth") { echo "selected"; } ?>
                                    value="thismonth">This Month</option>
                                <option <?php if ($_GET['canned_date'] == "lastmonth") { echo "selected"; } ?>
                                    value="lastmonth">Last Month</option>
                                <option <?php if ($_GET['canned_date'] == "thisyear") { echo "selected"; } ?>
                                    value="thisyear">This Year</option>
                                <option <?php if ($_GET['canned_date'] == "lastyear") { echo "selected"; } ?>
                                    value="lastyear">Last Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" class="form-control" name="dtf" max="2999-12-31"
                                value="<?php echo nullable_htmlentities($dtf); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" class="form-control" name="dtt" max="2999-12-31"
                                value="<?php echo nullable_htmlentities($dtt); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=credit_client&order=<?php echo $disp; ?>">Client Name</a></th>
                        <th><a class="text-dark"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=credit_account&order=<?php echo $disp; ?>">Account
                                Name</a></th>
                        <th class="text-right
                            <?php if ($sort == "credit_amount") { echo "sorting-$order"; } ?>">
                            <a class="text-dark"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=credit_amount&order=<?php echo $disp; ?>">Amount</a>
                        </th>
                        <th><a class="text-dark"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=credit_date&order=<?php echo $disp; ?>">Date</a>
                        </th>
                        <th><a class="text-dark"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=credit_reference&order=<?php echo $disp; ?>">Reference</a>
                        </th>
                        <th><a class="text-dark"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=credit_payment&order=<?php echo $disp; ?>">Origin</a>
                        </th>

                        <th>Actions</th>

                    </tr>
                </thead>
                <tbody>
                <?php
                    while ($row = mysqli_fetch_array($sql)) {
                        $credit_id = intval($row['credit_id']);
                        $credit_amount = floatval($row['credit_amount']);
                        $credit_currency_code = sanitizeInput($row['credit_currency_code']);
                        $credit_date = $row['credit_date'];
                        $credit_reference = intval($row['credit_reference']);
                        $credit_client_id = intval($row['credit_client_id']);
                        $credit_payment_id = intval($row['credit_payment_id']);
                        $credit_account_id = intval($row['credit_account_id']);
                        $client_name = sanitizeInput($row['client_name']);

                        // Get account name from DB
                        if($credit_account_id != null) {
                            $accountQuery = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $credit_account_id");
                            $account = mysqli_fetch_array($accountQuery);
                            $account_name = sanitizeinput($account['account_name']);
                        } else {
                            $account_name = "Unassigned";
                        }

                        // Get payment invoice and reference from DB
                        if($credit_payment_id != null) {
                            $paymentQuery = mysqli_query($mysqli, "SELECT * FROM payments WHERE payment_id = $credit_payment_id");
                            $payment = mysqli_fetch_array($paymentQuery);
                            $payment_invoice = intval($payment['payment_invoice_id']);
                            $payment_reference = intval($payment['payment_reference']);
                        } else {
                            $payment_invoice = "Unassigned";
                            $payment_reference = "Unassigned";
                        }

                        // Get invoice prefix and number from DB
                        if($payment_invoice != null) {
                            $invoiceQuery = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = $payment_invoice");
                            $invoice = mysqli_fetch_array($invoiceQuery);
                            $invoice_prefix = sanitizeInput($invoice['invoice_prefix']);
                            $invoice_number = intval($invoice['invoice_number']);
                            $payment_invoice_display = "Payment for: " . $invoice_prefix . $invoice_number;
                        } else {
                            $invoice_prefix = "Unassigned";
                            $invoice_number = "Unassigned";
                        }

                        $credit_display_amount = numfmt_format_currency($currency_format, $credit_amount, $credit_currency_code);

                        $client_balance = getClientBalance( $credit_client_id);
                        ?>

                        <tr>
                            <td><a href="client_overview.php?client_id=<?php echo $credit_client_id; ?>"><?php echo $client_name; ?></a>
                            <td><?php echo $account_name; ?></td>
                            <td class="text-right
                                <?php if ($sort == "credit_amount") { echo "sorting-$order"; } ?>">
                                <?php echo $credit_display_amount; ?>
                            </td>
                            <td><?php echo $credit_date; ?></td>
                            <td><?php echo $credit_reference; ?></td>
                            <td><a href="client_payments.php?client_id=<?php echo $credit_client_id; ?>"><?php echo $payment_invoice_display; ?></a></td>
                            <td>
                                <a href="post.php?apply_credit=<?php echo $credit_id; ?>" class="btn btn-sm btn-primary"
                                title="Apply"><i class="fas fa-credit-card"></i></a>
                                <a href="post.php?delete_credit=<?php echo $credit_id; ?>" class="btn btn-sm btn-danger"
                                title="Delete"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php } ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "footer.php";
 ?>
