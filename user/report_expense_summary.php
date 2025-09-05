<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_financial');

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_expense_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(expense_date) AS expense_year FROM expenses WHERE expense_category_id > 0 ORDER BY expense_year DESC");

$sql_categories = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Expense' ORDER BY category_name ASC");

// For chart Y-axis max
$largest_expense_month = 0;

?>

<!-- Responsive chart helpers -->
<style>
  .chart-h-320 { position: relative; height: 320px; }
  @media (max-width: 576px) { .chart-h-320 { height: 260px; } }
</style>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-coins mr-2"></i>Expense Summary</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-3">
            <select onchange="this.form.submit()" class="form-control" name="year">
                <?php while ($row = mysqli_fetch_array($sql_expense_years)) {
                    $expense_year = intval($row['expense_year']); ?>
                    <option <?php if ($year == $expense_year) { ?> selected <?php } ?>><?php echo $expense_year; ?></option>
                <?php } ?>
            </select>
        </form>

        <div class="chart-h-320 mb-3">
            <canvas id="cashFlow"></canvas>
        </div>

        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead class="text-dark">
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
                <?php while ($row = mysqli_fetch_array($sql_categories)) {
                    $category_id = intval($row['category_id']);
                    $category_name = nullable_htmlentities($row['category_name']); ?>
                    <tr>
                        <td><?php echo $category_name; ?></td>
                        <?php
                        $total_expense_for_all_months = 0;
                        for ($month = 1; $month <= 12; $month++) {
                            $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE expense_category_id = $category_id AND YEAR(expense_date) = $year AND MONTH(expense_date) = $month");
                            $rowm = mysqli_fetch_array($sql_expenses);
                            $expense_amount_for_month = floatval($rowm['expense_amount_for_month']);
                            $total_expense_for_all_months += $expense_amount_for_month;
                            ?>
                            <td class="text-right">
                                <a class="text-dark" href="expenses.php?q=<?php echo $category_name; ?>&dtf=<?php echo "$year-$month"; ?>-01&dtt=<?php echo "$year-$month"; ?>-31">
                                    <?php echo numfmt_format_currency($currency_format, $expense_amount_for_month, $session_company_currency); ?>
                                </a>
                            </td>
                        <?php } ?>
                        <th class="text-right">
                            <a class="text-dark" href="expenses.php?q=<?php echo $category_name; ?>&dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
                                <?php echo numfmt_format_currency($currency_format, $total_expense_for_all_months, $session_company_currency); ?>
                            </a>
                        </th>
                    </tr>
                <?php } ?>

                <tr>
                    <th>Total</th>
                    <?php
                    $grand_total_all_months = 0;
                    for ($month = 1; $month <= 12; $month++) {
                        $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS expense_total_amount_for_month FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND expense_vendor_id > 0");
                        $rowt = mysqli_fetch_array($sql_expenses);
                        $expense_total_amount_for_month = floatval($rowt['expense_total_amount_for_month']);
                        $grand_total_all_months += $expense_total_amount_for_month;
                        ?>
                        <th class="text-right">
                            <a class="text-dark" href="expenses.php?dtf=<?php echo "$year-$month"; ?>-01&dtt=<?php echo "$year-$month"; ?>-31">
                                <?php echo numfmt_format_currency($currency_format, $expense_total_amount_for_month, $session_company_currency); ?>
                            </a>
                        </th>
                    <?php } ?>
                    <th class="text-right">
                        <a class="text-dark" href="expenses.php?dtf=<?php echo $year; ?>-01-01&dtt=<?php echo $year; ?>-12-31">
                            <?php echo numfmt_format_currency($currency_format, $grand_total_all_months, $session_company_currency); ?>
                        </a>
                    </th>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>

<script>
    // Bootstrap-like defaults for Chart.js v4
    Chart.defaults.font.family = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.color = '#292b2c';

    // EXPENSES LINE CHART
    (function () {
        var ctx = document.getElementById("cashFlow");
        if (!ctx) return;

        var dataPoints = [
            <?php
            // Build series and track the largest month for axis max
            for ($month = 1; $month <= 12; $month++) {
                $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS expense_amount_for_month FROM expenses WHERE YEAR(expense_date) = $year AND MONTH(expense_date) = $month AND expense_vendor_id > 0");
                $rowm = mysqli_fetch_array($sql_expenses);
                $expenses_for_month = floatval($rowm['expense_amount_for_month']);

                if ($expenses_for_month > 0 && $expenses_for_month > $largest_expense_month) {
                    $largest_expense_month = $expenses_for_month;
                }
                echo "$expenses_for_month,";
            }
            ?>
        ];

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                datasets: [{
                    label: "Expense",
                    tension: 0.3, // v4 name (v2: lineTension)
                    fill: false,
                    borderColor: "#dc3545",
                    pointBackgroundColor: "#dc3545",
                    pointBorderColor: "#dc3545",
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: "#dc3545",
                    pointBorderWidth: 2,
                    data: dataPoints
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 12 }
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        max: <?php
                            $max = max(1000, $largest_expense_month);
                            echo roundUpToNearestMultiple($max);
                        ?>,
                        ticks: { maxTicksLimit: 5 },
                        grid: { color: "rgba(0, 0, 0, .125)" }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    })();
</script>
