<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_financial');

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$view = isset($_GET['view']) ? $_GET['view'] : 'quarterly';

$currency_row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT company_currency FROM companies WHERE company_id = 1"));
$company_currency = nullable_htmlentities($currency_row['company_currency']);

// GET unique years from expenses, payments and revenues
$sql_all_years = mysqli_query($mysqli, "SELECT DISTINCT(YEAR(item_created_at)) AS all_years FROM invoice_items ORDER BY all_years DESC");

$sql_tax = mysqli_query($mysqli, "SELECT `tax_name` FROM `taxes`");

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Collected Tax Summary</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
            </div>
        </div>
        <div class="card-body p-0">
            <form class="p-3">
                <select onchange="this.form.submit()" class="form-control" name="year">
                    <?php

                    while ($row = mysqli_fetch_array($sql_all_years)) {
                        $all_years = intval($row['all_years']);
                        ?>
                        <option <?php if ($year == $all_years) { echo "selected"; } ?> > <?php echo $all_years; ?></option>

                        <?php
                    }
                    ?>

                </select>

                <!-- View Selection Dropdown -->
                <select onchange="this.form.submit()" class="form-control" name="view">
                    <option value="monthly" <?php if ($view == 'monthly') echo "selected"; ?>>Monthly</option>
                    <option value="quarterly" <?php if ($view == 'quarterly') echo "selected"; ?>>Quarterly</option>
                </select>
            </form>

            <div class="table-responsive-sm">
                <table class="table table-sm">
                    <thead class="text-dark">
                    <tr>
                        <th>Tax</th>
                        <?php
                        if ($view == 'monthly') {
                            for ($i = 1; $i <= 12; $i++) {
                                echo "<th class='text-right'>" . date('M', mktime(0, 0, 0, $i, 10)) . "</th>";
                            }
                        } else {
                            echo "<th class='text-right'>Jan-Mar</th>";
                            echo "<th class='text-right'>Apr-Jun</th>";
                            echo "<th class='text-right'>Jul-Sep</th>";
                            echo "<th class='text-right'>Oct-Dec</th>";
                        }
                        ?>
                        <th class="text-right">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql_tax)) {
                        $tax_name = sanitizeInput($row['tax_name']);
                        echo "<tr>";
                        echo "<td>" . $row['tax_name'] . "</td>";

                        if ($view == 'monthly') {
                            for ($i = 1; $i <= 12; $i++) {
                                $monthly_tax = getMonthlyTax($tax_name, $i, $year, $mysqli);
                                echo "<td class='text-right'>" . numfmt_format_currency($currency_format, $monthly_tax, $company_currency) . "</td>";
                            }
                        } else {
                            for ($q = 1; $q <= 4; $q++) {
                                $quarterly_tax = getQuarterlyTax($tax_name, $q, $year, $mysqli);
                                echo "<td class='text-right'>" . numfmt_format_currency($currency_format, $quarterly_tax, $company_currency) . "</td>";
                            }
                        }

                        // Calculate total for row and echo bold
                        $total_tax = getTotalTax($tax_name, $year, $mysqli);
                        echo "<td class='text-right text-bold'>" . numfmt_format_currency($currency_format, $total_tax, $company_currency) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                    <tr>
                        <th>Total</th>
                        <?php
                        if ($view == 'monthly') {
                            for ($i = 1; $i <= 12; $i++) {
                                $monthly_tax = getMonthlyTax($tax_name, $i, $year, $mysqli);
                                echo "<th class='text-right'>" . numfmt_format_currency($currency_format, $monthly_tax, $company_currency) . "</th>";
                            }
                        } else {
                            for ($q = 1; $q <= 4; $q++) {
                                $quarterly_tax = getQuarterlyTax($tax_name, $q, $year, $mysqli);
                                echo "<th class='text-right'>" . numfmt_format_currency($currency_format, $quarterly_tax, $company_currency) . "</th>";
                            }
                        }
                        ?>
                        <td class="text-right"></td>
                    </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php require_once "../../includes/footer.php";

