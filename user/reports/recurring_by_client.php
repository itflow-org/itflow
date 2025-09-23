<?php

require_once "includes/inc_all_reports.php";

validateAccountantRole();

$sql = mysqli_query($mysqli, "
    SELECT client_id, client_name,
        SUM(CASE WHEN recurring_invoice_frequency = 'month' THEN recurring_invoice_amount
            WHEN recurring_invoice_frequency = 'year' THEN recurring_invoice_amount / 12 END) AS recurring_monthly_total
    FROM clients
    LEFT JOIN recurring_invoices ON client_id = recurring_invoice_client_id
    WHERE recurring_invoice_status = 1
    GROUP BY clients.client_id
    HAVING recurring_monthly_total > 0
    ORDER BY recurring_monthly_total DESC
");

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-sync mr-2"></i>Recurring Income By Client</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-sm">
                <thead>
                <tr>
                    <th>Client</th>
                    <th class="text-right">Monthly Recurring</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $recurring_monthly_total = floatval($row['recurring_monthly_total']);
                    $recurring_total = $recurring_total + $recurring_monthly_total;
                ?>


                    <tr>
                        <td><a href="../../user/client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $recurring_monthly_total, $session_company_currency); ?></td>
                    </tr>
                    <?php
                }

                ?>
                    <tr>
                        <th>Total Monthly Income</th>
                        <th class="text-right"><?php echo numfmt_format_currency($currency_format, $recurring_total, $session_company_currency); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "../../includes/footer.php";
 ?>
