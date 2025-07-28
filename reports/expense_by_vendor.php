<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_financial');

if (isset($_GET['year'])) {
    if ($_GET['year'] === 'all') {
        $year = 'all';
    } else {
        $year = intval($_GET['year']);
    }
} else {
    $year = date('Y');
}

$sql_payment_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(payment_date) AS payment_year FROM payments
    UNION SELECT DISTINCT YEAR(revenue_date) AS payment_year FROM revenues
    ORDER BY payment_year DESC"
);

$year_condition = ($year == 'all') ? "" : "AND YEAR(expense_date) = $year";

$sql_vendor_expenses = mysqli_query($mysqli, "
    SELECT 
        vendors.*, 
        SUM(expenses.expense_amount) AS amount_paid 
    FROM 
        vendors 
    LEFT JOIN 
        expenses ON vendors.vendor_id = expenses.expense_vendor_id $year_condition
    GROUP BY 
        vendors.vendor_id
    HAVING
        amount_paid > 599
    ORDER BY 
        amount_paid DESC
");

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-building mr-2"></i>Expense By Vendor <small>(With expense amounts of 600 or more)</small></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-3">
            <select onchange="this.form.submit()" class="form-control" name="year">
                <option value="all" <?php if ($year == 'all') { ?> selected <?php } ?> >All Years</option>
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

        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Vendor</th>
                    <th class="text-right">Paid</th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = mysqli_fetch_array($sql_vendor_expenses)) {
                    $vendor_id = intval($row['vendor_id']);
                    $vendor_name = nullable_htmlentities($row['vendor_name']);
                    $amount_paid = floatval($row['amount_paid']); ?>

                    <tr>
                        <td><?php echo $vendor_name; ?></td>
                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $amount_paid, $session_company_currency); ?></td>
                    </tr>
                    <?php
                }
                
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php";
 ?>
