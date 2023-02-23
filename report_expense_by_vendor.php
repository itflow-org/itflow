<?php

require_once("inc_all_reports.php");
validateAccountantRole();

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_payment_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(payment_date) AS payment_year FROM payments WHERE company_id = $session_company_id UNION SELECT DISTINCT YEAR(revenue_date) AS payment_year FROM revenues WHERE company_id = $session_company_id ORDER BY payment_year DESC");

$sql_vendors = mysqli_query($mysqli, "SELECT * FROM vendors WHERE company_id = $session_company_id");

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-building mr-2"></i>Expense By Vendor</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-3">
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

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Vendor</th>
                    <th class="text-right">Paid</th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = mysqli_fetch_array($sql_vendors)) {
                    $vendor_id = intval($row['vendor_id']);
                    $vendor_name = htmlentities($row['vendor_name']);

                    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS amount_paid FROM expenses WHERE YEAR(expense_date) = $year AND expense_vendor_id = $vendor_id");
                    $row = mysqli_fetch_array($sql_amount_paid);

                    $amount_paid = floatval($row['amount_paid']);

                    if ($amount_paid > 599) { ?>

                        <tr>
                            <td><?php echo $vendor_name; ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $amount_paid, $session_company_currency); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("footer.php"); ?>
