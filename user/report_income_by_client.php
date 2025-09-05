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

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-users mr-2"></i>Income By Client <small>(With payments of 600 or more)</small></h3>
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

                <?php } ?>

            </select>
        </form>

        <?php
        $sql_clients = "SELECT c.client_id, c.client_name, SUM(p.payment_amount) AS amount_paid
            FROM clients AS c
            JOIN invoices AS i ON c.client_id = i.invoice_client_id
            JOIN payments AS p ON i.invoice_id = p.payment_invoice_id";
            if ($year != 'all') {
                $sql_clients .= " WHERE YEAR(p.payment_date) = $year";
            }
        $sql_clients .= " GROUP BY c.client_id
            HAVING amount_paid > 599
            ORDER BY amount_paid DESC";

        $sql_clients = mysqli_query($mysqli, $sql_clients);
        ?>
        
        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Client</th>
                    <th class="text-right">Paid</th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = mysqli_fetch_array($sql_clients)) {
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $amount_paid = floatval($row['amount_paid']);

                    ?>

                    <tr>
                        <td><a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
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

<?php
require_once "../includes/footer.php";

