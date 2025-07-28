<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_financial');

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_payment_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(payment_date) AS payment_year FROM payments
    UNION SELECT DISTINCT YEAR(revenue_date) AS payment_year FROM revenues 
    ORDER BY payment_year DESC");

$sql_categories = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' ORDER BY category_name ASC");

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-coins mr-2"></i>Income Summary</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body p-0">
        <form class="p-3">
            <select onchange="this.form.submit()" class="form-control" name="year">
                <?php

                while ($row = mysqli_fetch_array($sql_payment_years)) {
                    $payment_year = intval($row['payment_year']);
                    ?>
                    <option <?php if ($year == $payment_year) { ?> selected <?php } ?> > <?php echo $payment_year; ?></option>

                    <?php
                }
                ?>

            </select>
        </form>

        <canvas id="cashFlow" width="100%" height="20"></canvas>

        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-right">January</th>
                    <th class="text-right">February</th>
                    <th class="text-right">March</th>
                    <th class="text-right">April</th>
                    <th class="text-right">May</th>
                    <th class="text-right">June</th>
                    <th class="text-right">July</th>
                    <th class="text-right">August</th>
                    <th class="text-right">September</th>
                    <th class="text-right">October</th>
                    <th class="text-right">November</th>
                    <th class="text-right">December</th>
                    <th class="text-right">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = mysqli_fetch_array($sql_categories)) {
                    $category_id = intval($row['category_id']);
                    $category_name = nullable_htmlentities($row['category_name']);

                    ?>

                    <tr>
                        <td><?php echo $category_name; ?></td>

                        <?php

                        $total_payment_for_all_months = 0;

                        for($month = 1; $month<=12; $month++) {
                            //Payments to Invoices
                            $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS payment_amount_for_month FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_category_id = $category_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
                            $row = mysqli_fetch_array($sql_payments);
                            $payment_amount_for_month = floatval($row['payment_amount_for_month']);

                            //Revenues
                            $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenue_category_id = $category_id AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month");
                            $row = mysqli_fetch_array($sql_revenues);
                            $revenues_amount_for_month = floatval($row['revenue_amount_for_month']);

                            $payment_amount_for_month = $payment_amount_for_month + $revenues_amount_for_month;
                            $total_payment_for_all_months = $payment_amount_for_month + $total_payment_for_all_months;


                            ?>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount_for_month, $session_company_currency); ?></td>

                            <?php

                        }

                        ?>

                        <td class="text-right text-bold"><?php echo numfmt_format_currency($currency_format, $total_payment_for_all_months, $session_company_currency); ?></td>
                    </tr>

                    <?php

                }

                ?>

                <tr>
                    <th>Total</th>
                    <?php

                    for($month = 1; $month<=12; $month++) {
                        $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS payment_total_amount_for_month FROM payments, invoices WHERE payment_invoice_id = invoice_id AND YEAR(payment_date) = $year AND MONTH(payment_date) = $month");
                        $row = mysqli_fetch_array($sql_payments);
                        $payment_total_amount_for_month = floatval($row['payment_total_amount_for_month']);

                        $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS revenue_amount_for_month FROM revenues WHERE revenue_category_id > 0 AND YEAR(revenue_date) = $year AND MONTH(revenue_date) = $month");
                        $row = mysqli_fetch_array($sql_revenues);
                        $revenues_total_amount_for_month = floatval($row['revenue_amount_for_month']);

                        $payment_total_amount_for_month = $payment_total_amount_for_month + $revenues_total_amount_for_month;


                        $total_payment_for_all_months = $payment_total_amount_for_month + $total_payment_for_all_months;

                        ?>

                        <th class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_total_amount_for_month, $session_company_currency); ?></th>
                        <?php

                    }

                    ?>

                    <th class="text-right"><?php echo numfmt_format_currency($currency_format, $total_payment_for_all_months, $session_company_currency); ?></th>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

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

                    for ($month = 1; $month<=12; $month++) {
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
                        max: <?php $max = max(1000, $largest_income_month, $largest_invoice_month); echo roundUpToNearestMultiple($max); ?>,
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

</script>
