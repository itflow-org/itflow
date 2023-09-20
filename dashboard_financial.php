<?php
require_once("inc_all.php");

// Enforce accountant / admin role for the financial dashboard
if ($_SESSION['user_role'] != 3 && $_SESSION['user_role'] != 1) {
    exit('<script type="text/javascript">window.location.href = \'dashboard_technical.php\';</script>');
}

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

//GET unique years from expenses, payments invoices and revenues
$sql_years_select = mysqli_query(
    $mysqli,
    "SELECT YEAR(expense_date) AS all_years FROM expenses
    UNION DISTINCT SELECT YEAR(payment_date) FROM payments
    UNION DISTINCT SELECT YEAR(revenue_date) FROM revenues
    UNION DISTINCT SELECT YEAR(invoice_date) FROM invoices
    ORDER BY all_years DESC
");

//Define var so it doesnt throw errors in logs
$largest_income_month = 0;

//Get Total income
$sql_total_payments_to_invoices = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments_to_invoices FROM payments WHERE YEAR(payment_date) = $year");
$row = mysqli_fetch_array($sql_total_payments_to_invoices);
$total_payments_to_invoices = floatval($row['total_payments_to_invoices']);
//Do not grab transfer payment as these have a category_id of 0
$sql_total_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE YEAR(revenue_date) = $year AND revenue_category_id > 0");
$row = mysqli_fetch_array($sql_total_revenues);
$total_revenues = floatval($row['total_revenues']);

$total_income = $total_payments_to_invoices + $total_revenues;

//Get Total expenses and do not grab transfer expenses as these have a vendor of 0
$sql_total_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_vendor_id > 0 AND YEAR(expense_date) = $year");
$row = mysqli_fetch_array($sql_total_expenses);
$total_expenses = floatval($row['total_expenses']);

//Total up all the Invoices that are not draft or cancelled
$sql_invoice_totals = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_totals FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' AND YEAR(invoice_date) = $year");
$row = mysqli_fetch_array($sql_invoice_totals);
$invoice_totals = floatval($row['invoice_totals']);

//Quaeries from Receivables
$sql_total_payments_to_invoices_all_years = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments_to_invoices_all_years FROM payments");
$row = mysqli_fetch_array($sql_total_payments_to_invoices_all_years);
$total_payments_to_invoices_all_years = floatval($row['total_payments_to_invoices_all_years']);

$sql_invoice_totals_all_years = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_totals_all_years FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled'");
$row = mysqli_fetch_array($sql_invoice_totals_all_years);
$invoice_totals_all_years = floatval($row['invoice_totals_all_years']);

$receivables = $invoice_totals_all_years - $total_payments_to_invoices_all_years;

$profit = $total_income - $total_expenses;

$sql_accounts = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");

$sql_latest_invoice_payments = mysqli_query(
    $mysqli,
    "SELECT * FROM payments, invoices, clients
    WHERE payment_invoice_id = invoice_id
    AND invoice_client_id = client_id
    ORDER BY payment_id DESC LIMIT 5"
);

$sql_latest_expenses = mysqli_query(
    $mysqli,
    "SELECT * FROM expenses, vendors, categories
    WHERE expense_vendor_id = vendor_id
    AND expense_category_id = category_id
    ORDER BY expense_id DESC LIMIT 5"
);

//Get Yearly Recurring Income Total
$sql_recurring_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_amount) AS recurring_yearly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'year' AND YEAR(recurring_created_at) <= $year");
$row = mysqli_fetch_array($sql_recurring_yearly_total);
$recurring_yearly_total = floatval($row['recurring_yearly_total']);

//Get Monthly Recurring Income Total
$sql_recurring_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_amount) AS recurring_monthly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'month' AND YEAR(recurring_created_at) <= $year");
$row = mysqli_fetch_array($sql_recurring_monthly_total);
$recurring_monthly_total = floatval($row['recurring_monthly_total']) + ($recurring_yearly_total / 12);

//Get Yearly Recurring Expenses Total
$sql_recurring_expense_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_expense_amount) AS recurring_expense_yearly_total FROM recurring_expenses WHERE recurring_expense_status = 1 AND recurring_expense_frequency = 2 AND YEAR(recurring_expense_created_at) <= $year");
$row = mysqli_fetch_array($sql_recurring_expense_yearly_total);
$recurring_expense_yearly_total = floatval($row['recurring_expense_yearly_total']);

//Get Monthly Recurring Expenses Total
$sql_recurring_expense_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_expense_amount) AS recurring_expense_monthly_total FROM recurring_expenses WHERE recurring_expense_status = 1 AND recurring_expense_frequency = 1 AND YEAR(recurring_expense_created_at) <= $year");
$row = mysqli_fetch_array($sql_recurring_expense_monthly_total);
$recurring_expense_monthly_total = floatval($row['recurring_expense_monthly_total']) + ($recurring_expense_yearly_total / 12);

//Get Total Miles Driven
$sql_miles_driven = mysqli_query($mysqli, "SELECT SUM(trip_miles) AS total_miles FROM trips WHERE YEAR(trip_date) = $year");
$row = mysqli_fetch_array($sql_miles_driven);
$total_miles = floatval($row['total_miles']);

//Get Total Recurring Invoices added
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_id') AS recurring_invoices_added FROM recurring WHERE YEAR(recurring_created_at) = $year"));
$recurring_invoices_added = intval($row['recurring_invoices_added']);

//Get Total Clients added
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('client_id') AS clients_added FROM clients WHERE YEAR(client_created_at) = $year AND client_archived_at IS NULL"));
$clients_added = intval($row['clients_added']);

//Get Total Vendors added
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('vendor_id') AS vendors_added FROM vendors WHERE YEAR(vendor_created_at) = $year AND vendor_client_id = 0 AND vendor_template = 0 AND vendor_archived_at IS NULL"));
$vendors_added = intval($row['vendors_added']);

?>

<form class="mb-3">
    <select onchange="this.form.submit()" class="form-control" name="year">
        <?php

        while ($row = mysqli_fetch_array($sql_years_select)) {
            $year_select = $row['all_years'];
            if (empty($year_select)) {
                $year_select = date('Y');
            }
            ?>
            <option <?php if ($year == $year_select) { echo "selected"; } ?> > <?php echo $year_select; ?></option>

            <?php
        }
        ?>

    </select>
</form>

<!-- Icon Cards-->
<div class="row">
    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- small box -->
        <a class="small-box bg-primary" href="payments.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
            <div class="inner">
                <h3><?php echo numfmt_format_currency($currency_format, $total_income, "$session_company_currency"); ?></h3>
                <p>Income</p>
                <hr>
                <small>Receivables: <?php echo numfmt_format_currency($currency_format, $receivables, "$session_company_currency"); ?></h3></small>
            </div>
            <div class="icon">
                <i class="fa fa-money-check"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- small box -->
        <a class="small-box bg-danger" href="expenses.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
            <div class="inner">
                <h3><?php echo numfmt_format_currency($currency_format, $total_expenses, "$session_company_currency"); ?></h3>
                <p>Expenses</p>
            </div>
            <div class="icon">
                <i class="fa fa-shopping-cart"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- small box -->
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?php echo numfmt_format_currency($currency_format, $profit, "$session_company_currency"); ?></h3>
                <p>Profit</p>
            </div>
            <div class="icon">
                <i class="fa fa-heart"></i>
            </div>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- small box -->
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?php echo numfmt_format_currency($currency_format, $recurring_monthly_total, "$session_company_currency"); ?></h3>
                <p>Monthly Recurring Income</p>
            </div>
            <div class="icon">
                <i class="fa fa-sync-alt"></i>
            </div>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- small box -->
        <div class="small-box bg-pink">
            <div class="inner">
                <h3><?php echo numfmt_format_currency($currency_format, $recurring_expense_monthly_total, "$session_company_currency"); ?></h3>
                <p>Monthly Recurring Expense</p>
            </div>
            <div class="icon">
                <i class="fa fa-clock"></i>
            </div>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- small box -->
        <a class="small-box bg-secondary" href="recurring_invoices.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
            <div class="inner">
                <h3><?php echo $recurring_invoices_added; ?></h3>
                <p>Recurring Invoices Added</p>
            </div>
            <div class="icon">
                <i class="fa fa-file-invoice"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-6">
        <!-- small box -->
        <a class="small-box bg-secondary" href="clients.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
            <div class="inner">
                <h3><?php echo $clients_added; ?></h3>
                <p>New Clients</p>
            </div>
            <div class="icon">
                <i class="fa fa-users"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-6">
        <!-- small box -->
        <a class="small-box bg-secondary" href="vendors.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
            <div class="inner">
                <h3><?php echo $vendors_added; ?></h3>
                <p>New Vendors</p>
            </div>
            <div class="icon">
                <i class="fa fa-building"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- small box -->
        <a class="small-box bg-secondary" href="trips.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
            <div class="inner">
                <h3><?php echo number_format($total_miles, 2); ?></h3>
                <p>Miles Traveled</p>
            </div>
            <div class="icon">
                <i class="fa fa-route"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-md-12">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-fw fa-chart-area mr-2"></i>Cash Flow</h3>
                <div class="card-tools">
                    <a href="report_income_summary.php" class="btn btn-tool">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="cashFlow" width="100%" height="20"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-fw fa-chart-pie mr-2"></i>Income by Category <small>(Top 5)</small></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="incomeByCategoryPieChart" width="100%" height="60"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-fw fa-shopping-cart mr-2"></i>Expenses by Category <small>(Top 5)</small></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="expenseByCategoryPieChart" width="100%" height="60"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-fw fa-building mr-2"></i>Expenses by Vendor <small>(Top 5)</small></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="expenseByVendorPieChart" width="100%" height="60"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-fw fa-piggy-bank mr-2"></i>Account Balances</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql_accounts)) {
                        $account_id = intval($row['account_id']);
                        $account_name = nullable_htmlentities($row['account_name']);
                        $opening_balance = floatval($row['opening_balance']);

                        ?>
                        <tr>
                            <td><?php echo $account_name; ?></td>
                            <?php
                            $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
                            $row = mysqli_fetch_array($sql_payments);
                            $total_payments = floatval($row['total_payments']);

                            $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
                            $row = mysqli_fetch_array($sql_revenues);
                            $total_revenues = floatval($row['total_revenues']);

                            $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
                            $row = mysqli_fetch_array($sql_expenses);
                            $total_expenses = floatval($row['total_expenses']);

                            $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                            if ($balance == '') {
                                $balance = '0.00';
                            }
                            ?>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $balance, "$session_company_currency"); ?></td>
                        </tr>
                        <?php
                    }
                    ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- .col -->
    <div class="col-md-4">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-fw fa-credit-card mr-2"></i>Latest Income</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-sm">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Invoice</th>
                        <th class="text-right">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql_latest_invoice_payments)) {
                        $payment_date = nullable_htmlentities($row['payment_date']);
                        $payment_amount = floatval($row['payment_amount']);
                        $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                        $invoice_number = intval($row['invoice_number']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        ?>
                        <tr>
                            <td><?php echo $payment_date; ?></td>
                            <td><?php echo $client_name; ?></td>
                            <td><?php echo "$invoice_prefix$invoice_number"; ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount, "$session_company_currency"); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- .col -->
    <div class="col-md-4">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-fw fa-shopping-cart mr-2"></i>Latest Expenses</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-borderless">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Category</th>
                        <th class="text-right">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql_latest_expenses)) {
                        $expense_date = nullable_htmlentities($row['expense_date']);
                        $expense_amount = floatval($row['expense_amount']);
                        $vendor_name = nullable_htmlentities($row['vendor_name']);
                        $category_name = nullable_htmlentities($row['category_name']);

                        ?>
                        <tr>
                            <td><?php echo $expense_date; ?></td>
                            <td><?php echo $vendor_name; ?></td>
                            <td><?php echo $category_name; ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $expense_amount, "$session_company_currency"); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- .col -->
    <div class="col-md-12">
        <div class="card card-dark mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-fw fa-route mr-2"></i>Trip Flow</h3>
                <div class="card-tools">
                    <a href="trips.php" class="btn btn-tool">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button type="button" class="btn btn-tool" data-card-widget="remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="tripFlow" width="100%" height="20"></canvas>
            </div>
        </div>
    </div>
</div> <!-- row -->

<?php require_once("footer.php"); ?>

<script>
    // Set new default font family and font color to mimic Bootstrap's default styling
    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.global.defaultFontColor = '#292b2c';

    // Area Chart Example
    var ctx = document.getElementById("cashFlow");
    var myLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            datasets: [{
                label: "Income",
                fill: false,
                borderColor: "#007bff",
                pointBackgroundColor: "#007bff",
                pointBorderColor: "#007bff",
                pointHoverRadius: 5,
                pointHoverBackgroundColor: "#007bff",
                pointHitRadius: 50,
                pointBorderWidth: 2,
                data: [
                    <?php
                    for($month = 1; $month<=12; $month++) {
                    $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payment_invoice_id = invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
                    $row = mysqli_fetch_array($sql_payments);
                    $payments_for_month = floatval($row['payment_amount_for_month']);

                    $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenue_category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month");
                    $row = mysqli_fetch_array($sql_revenues);
                    $revenues_for_month = floatval($row['revenue_amount_for_month']);

                    $income_for_month = $payments_for_month + $revenues_for_month;

                    if ($income_for_month > 0 && $income_for_month > $largest_income_month) {
                        $largest_income_month = $income_for_month;
                    }


                    ?>
                    <?php echo "$income_for_month,"; ?>

                    <?php

                    }

                    ?>

                ],
            },
                {
                    label: "LY Income",
                    fill: false,
                    borderColor: "#9932CC",
                    pointBackgroundColor: "#9932CC",
                    pointBorderColor: "#9932CC",
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: "#9932CC",
                    pointHitRadius: 50,
                    pointBorderWidth: 2,
                    data: [
                        <?php
                        for($month = 1; $month<=12; $month++) {
                        $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payment_invoice_id = invoice_id AND YEAR(payment_date) = $year-1 AND MONTH(payment_date) = $month");
                        $row = mysqli_fetch_array($sql_payments);
                        $payments_for_month = floatval($row['payment_amount_for_month']);

                        $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenue_category_id > 0 AND YEAR(revenue_date) = $year-1 AND MONTH(revenue_date) = $month");
                        $row = mysqli_fetch_array($sql_revenues);
                        $revenues_for_month = floatval($row['revenue_amount_for_month']);

                        $income_for_month = $payments_for_month + $revenues_for_month;

                        if ($income_for_month > 0 && $income_for_month > $largest_income_month) {
                            $largest_income_month = $income_for_month;
                        }


                        ?>
                        <?php echo "$income_for_month,"; ?>

                        <?php

                        }

                        ?>

                    ],
                },
                {
                    label: "Projected",
                    fill: false,
                    borderColor: "black",
                    pointBackgroundColor: "black",
                    pointBorderColor: "black",
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: "black",
                    pointHitRadius: 50,
                    pointBorderWidth: 2,
                    data: [
                        <?php

                        $largest_invoice_month = 0;

                        for($month = 1; $month<=12; $month++) {
                        $sql_projected = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amount_for_month FROM invoices WHERE YEAR(invoice_due) = $year AND MONTH(invoice_due) = $month AND invoice_status NOT LIKE 'Cancelled' AND invoice_status NOT LIKE 'Draft'");
                        $row = mysqli_fetch_array($sql_projected);
                        $invoice_for_month = floatval($row['invoice_amount_for_month']);

                        if ($invoice_for_month > 0 && $invoice_for_month > $largest_invoice_month) {
                            $largest_invoice_month = $invoice_for_month;
                        }

                        ?>
                        <?php echo "$invoice_for_month,"; ?>

                        <?php

                        }

                        ?>

                    ],
                },
                {
                    label: "Expense",
                    lineTension: 0.3,
                    fill: false,
                    borderColor: "#dc3545",
                    pointBackgroundColor: "#dc3545",
                    pointBorderColor: "#dc3545",
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: "#dc3545",
                    pointHitRadius: 50,
                    pointBorderWidth: 2,
                    data: [
                        <?php

                        $largest_expense_month = 0;

                        for($month = 1; $month<=12; $month++) {
                        $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND expense_vendor_id > 0");
                        $row = mysqli_fetch_array($sql_expenses);
                        $expenses_for_month = floatval($row['expense_amount_for_month']);

                        if ($expenses_for_month > 0 && $expenses_for_month > $largest_expense_month) {
                            $largest_expense_month = $expenses_for_month;
                        }


                        ?>
                        <?php echo "$expenses_for_month,"; ?>

                        <?php

                        }

                        ?>

                    ],
                }],
        },
        options: {
            scales: {
                xAxes: [{
                    time: {
                        unit: 'date'
                    },
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 12
                    }
                }],
                yAxes: [{
                    ticks: {
                        min: 0,
                        max: <?php $max = max(1000, $largest_expense_month, $largest_income_month, $largest_invoice_month); echo roundUpToNearestMultiple($max); ?>,
                        maxTicksLimit: 5
                    },
                    gridLines: {
                        color: "rgba(0, 0, 0, .125)",
                    }
                }],
            },
            legend: {
                display: true
            }
        }
    });

    // Set new default font family and font color to mimic Bootstrap's default styling
    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.global.defaultFontColor = '#292b2c';

    // Area Chart Example
    var ctx = document.getElementById("tripFlow");
    var myLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            datasets: [{
                label: "Trip",
                lineTension: 0.3,
                backgroundColor: "red",
                borderColor: "darkred",
                pointRadius: 5,
                pointBackgroundColor: "red",
                pointBorderColor: "red",
                pointHoverRadius: 5,
                pointHoverBackgroundColor: "darkred",
                pointHitRadius: 50,
                pointBorderWidth: 2,
                data: [
                    <?php
                    for($month = 1; $month<=12; $month++) {
                    $sql_trips = mysqli_query($mysqli, "SELECT SUM(trip_miles) AS trip_miles_for_month FROM trips WHERE YEAR(trip_date) = $year AND MONTH(trip_date) = $month");
                    $row = mysqli_fetch_array($sql_trips);
                    $trip_miles_for_month = floatval($row['trip_miles_for_month']);
                    $largest_trip_miles_month = 0;

                    if ($trip_miles_for_month > 0 && $trip_miles_for_month > $largest_trip_miles_month) {
                        $largest_trip_miles_month = $trip_miles_for_month;
                    }


                    ?>
                    <?php echo "$trip_miles_for_month,"; ?>

                    <?php

                    }

                    ?>

                ],
            }],
        },
        options: {
            scales: {
                xAxes: [{
                    time: {
                        unit: 'date'
                    },
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 12
                    }
                }],
                yAxes: [{
                    ticks: {
                        min: 0,
                        max: <?php $max = max(1000, $largest_trip_miles_month); echo roundUpToNearestMultiple($max); ?>,
                        maxTicksLimit: 5
                    },
                    gridLines: {
                        color: "rgba(0, 0, 0, .125)",
                    }
                }],
            },
            legend: {
                display: false
            }
        }
    });

    // Set new default font family and font color to mimic Bootstrap's default styling
    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.global.defaultFontColor = '#292b2c';

    // Pie Chart Example
    var ctx = document.getElementById("incomeByCategoryPieChart");
    var myPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php
                mysqli_query($mysqli, "CREATE TEMPORARY TABLE TopCategories SELECT category_name, category_id, SUM(invoice_amount) AS total_income FROM categories, invoices WHERE invoice_category_id = category_id AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year GROUP BY category_name, category_id ORDER BY total_income DESC LIMIT 5");
                $sql_categories = mysqli_query($mysqli, "SELECT category_name FROM TopCategories");
                while ($row = mysqli_fetch_array($sql_categories)) {
                    $category_name = json_encode($row['category_name']);
                    echo "$category_name,";
                }

                $sql_other_categories = mysqli_query($mysqli, "SELECT SUM(invoices.invoice_amount) AS other_income FROM categories LEFT JOIN TopCategories ON categories.category_id = TopCategories.category_id INNER JOIN invoices ON categories.category_id = invoices.invoice_category_id WHERE TopCategories.category_id IS NULL AND invoice_status = 'Paid' AND YEAR(invoice_date) = $year");
                $row = mysqli_fetch_array($sql_other_categories);
                $other_income = floatval($row['other_income']);
                if ($other_income > 0) {
                    echo "'Others',";
                }
                ?>

            ],
            datasets: [{
                data: [
                    <?php
                    $sql_categories = mysqli_query($mysqli, "SELECT total_income FROM TopCategories");
                    while ($row = mysqli_fetch_array($sql_categories)) {
                        $total_income = floatval($row['total_income']);
                        echo "$total_income,";
                    }
                    if ($other_income > 0) {
                        echo "$other_income,";
                    }
                    ?>

                ],
                backgroundColor: [
                    <?php
                    $sql_categories = mysqli_query($mysqli, "SELECT category_color FROM TopCategories JOIN categories ON TopCategories.category_id = categories.category_id");
                    while ($row = mysqli_fetch_array($sql_categories)) {
                        $category_color = json_encode($row['category_color']);
                        echo "$category_color,";
                    }
                    if ($other_income > 0) {
                        echo "'#999999',"; // color for 'Others' category
                    }
                    ?>

                ],
            }],
        },
        options: {
            legend: {
                display: true,
                position: 'right'
            }
        }
    });

    // Set new default font family and font color to mimic Bootstrap's default styling
    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.global.defaultFontColor = '#292b2c';

    // Pie Chart Example
    var ctx = document.getElementById("expenseByCategoryPieChart");
    var myPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php
                mysqli_query($mysqli, "CREATE TEMPORARY TABLE TopExpenseCategories SELECT category_name, category_id, SUM(expense_amount) AS total_expense FROM categories, expenses WHERE expense_category_id = category_id AND expense_vendor_id > 0 AND YEAR(expense_date) = $year GROUP BY category_name, category_id ORDER BY total_expense DESC LIMIT 5");
                $sql_categories = mysqli_query($mysqli, "SELECT category_name FROM TopExpenseCategories");
                while ($row = mysqli_fetch_array($sql_categories)) {
                    $category_name = json_encode($row['category_name']);
                    echo "$category_name,";
                }

                $sql_other_categories = mysqli_query($mysqli, "SELECT SUM(expenses.expense_amount) AS other_expense FROM categories LEFT JOIN TopExpenseCategories ON categories.category_id = TopExpenseCategories.category_id INNER JOIN expenses ON categories.category_id = expenses.expense_category_id WHERE TopExpenseCategories.category_id IS NULL AND expense_vendor_id > 0 AND YEAR(expense_date) = $year");
                $row = mysqli_fetch_array($sql_other_categories);
                $other_expense = floatval($row['other_expense']);
                if ($other_expense > 0) {
                    echo "'Others',";
                }
                ?>

            ],
            datasets: [{
                data: [
                    <?php
                    $sql_categories = mysqli_query($mysqli, "SELECT total_expense FROM TopExpenseCategories");
                    while ($row = mysqli_fetch_array($sql_categories)) {
                        $total_expense = floatval($row['total_expense']);
                        echo "$total_expense,";
                    }
                    if ($other_expense > 0) {
                        echo "$other_expense,";
                    }
                    ?>

                ],
                backgroundColor: [
                    <?php
                    $sql_categories = mysqli_query($mysqli, "SELECT category_color FROM TopExpenseCategories JOIN categories ON TopExpenseCategories.category_id = categories.category_id");
                    while ($row = mysqli_fetch_array($sql_categories)) {
                        $category_color = json_encode($row['category_color']);
                        echo "$category_color,";
                    }
                    if ($other_expense > 0) {
                        echo "'#999999',"; // color for 'Others' category
                    }
                    ?>

                ],
            }],
        },
        options: {
            legend: {
                display: true,
                position: 'right'
            }
        }
    });

    // Pie Chart Example
    var ctx = document.getElementById("expenseByVendorPieChart");
    var myPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php
                mysqli_query($mysqli, "CREATE TEMPORARY TABLE TopVendors SELECT vendor_name, vendor_id, SUM(expense_amount) AS total_expense FROM vendors, expenses WHERE expense_vendor_id = vendor_id AND YEAR(expense_date) = $year GROUP BY vendor_name, vendor_id ORDER BY total_expense DESC LIMIT 5");
                $sql_vendors = mysqli_query($mysqli, "SELECT vendor_name FROM TopVendors");
                while ($row = mysqli_fetch_array($sql_vendors)) {
                    $vendor_name = json_encode($row['vendor_name']);
                    echo "$vendor_name,";
                }

                $sql_other_vendors = mysqli_query($mysqli, "SELECT SUM(expenses.expense_amount) AS other_expense FROM vendors LEFT JOIN TopVendors ON vendors.vendor_id = TopVendors.vendor_id INNER JOIN expenses ON vendors.vendor_id = expenses.expense_vendor_id WHERE TopVendors.vendor_id IS NULL AND YEAR(expense_date) = $year");
                $row = mysqli_fetch_array($sql_other_vendors);
                $other_expense = floatval($row['other_expense']);
                if ($other_expense > 0) {
                    echo "'Others',";
                }
                ?>

            ],
            datasets: [{
                data: [
                    <?php
                    $sql_vendors = mysqli_query($mysqli, "SELECT total_expense FROM TopVendors");
                    while ($row = mysqli_fetch_array($sql_vendors)) {
                        $total_expense = floatval($row['total_expense']);
                        echo "$total_expense,";
                    }
                    if ($other_expense > 0) {
                        echo "$other_expense,";
                    }
                    ?>

                ],
                backgroundColor: [
                    <?php
                    $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id FROM TopVendors");
                    while ($row = mysqli_fetch_array($sql_vendors)) {
                        // Generate random color for each vendor
                        echo "'#" . substr(md5(rand()), 0, 6) . "',";
                    }
                    if ($other_expense > 0) {
                        echo "'#999999',"; // color for 'Others' vendor
                    }
                    ?>

                ],
            }],
        },
        options: {
            legend: {
                display: true,
                position: 'right'
            }
        }
    });

</script>
