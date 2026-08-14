<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_financial');

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_expense_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(expense_date) AS expense_year FROM expenses WHERE expense_category_id > 0 ORDER BY expense_year DESC");

$categories = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Expense' ORDER BY category_name ASC");
$monthlyTotals = array_fill(1, 12, 0);  // Initialize monthly totals for each month

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale me-2"></i>Annual Budget</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print me-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-3">
            <select onchange="this.form.submit()" class="form-select" name="year">
                <?php

                while ($row = mysqli_fetch_assoc($sql_expense_years)) {
                    $expense_year = $row['expense_year'];
                    ?>
                    <option <?php if ($year == $expense_year) { ?> selected <?php } ?> > <?= $expense_year ?></option>

                <?php } ?>

            </select>
        </form>

        <canvas id="cashFlow" width="100%" height="20"></canvas>

        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead class="text-dark">
                <tr>
                    <th>Category</th>
                    <th class="text-end">January</th>
                    <th class="text-end">February</th>
                    <th class="text-end">March</th>
                    <th class="text-end">April</th>
                    <th class="text-end">May</th>
                    <th class="text-end">June</th>
                    <th class="text-end">July</th>
                    <th class="text-end">August</th>
                    <th class="text-end">September</th>
                    <th class="text-end">October</th>
                    <th class="text-end">November</th>
                    <th class="text-end">December</th>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php
                if ($categories->num_rows > 0) {
                    while($category = $categories->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . escapeHtml($category['category_name']) . "</td>";
                        $categoryTotal = 0;
                        for ($month = 1; $month <= 12; $month++) {
                            // Fetch the monthly budget for this category for 2022
                            $sql = "SELECT budget_amount FROM budget WHERE budget_category_id = " . $category['category_id'] . " AND budget_month = $month AND budget_year = $year";
                            $result = $mysqli->query($sql);
                            if ($result->num_rows > 0) {
                                $budget = $result->fetch_assoc();
                                $amount = $budget['budget_amount'];
                                $categoryTotal += $amount;
                                $monthlyTotals[$month] += $amount;
                                echo "<td class='text-end'>" . $amount . "</td>";
                            } else {
                                echo "<td class='text-end'>0</td>";
                            }
                        }
                        echo "<td class='text-end'>" . $categoryTotal . "</td>";
                        echo "</tr>";
                    }

                    // Displaying the monthly totals row
                    echo "<tr><td><strong>Total</strong></td>";
                    $grandTotal = 0;
                    for ($month = 1; $month <= 12; $month++) {
                        $grandTotal += $monthlyTotals[$month];
                        echo "<td class='text-end'>" . $monthlyTotals[$month] . "</td>";
                    }
                    echo "<td class='text-end'>" . $grandTotal . "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "../../includes/footer.php";
 ?>
