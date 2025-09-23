<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_financial');

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-exclamation-triangle mr-2"></i>Clients with a Balance</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">

        <?php
        $sql_clients = mysqli_query($mysqli, "
            SELECT 
                clients.client_id,
                clients.client_name,
                IFNULL(SUM(invoices.invoice_amount), 0) - IFNULL(SUM(payments.payment_amount), 0) AS balance
            FROM 
                clients
            LEFT JOIN
                invoices
            ON 
                clients.client_id = invoices.invoice_client_id 
                AND invoices.invoice_status != 'Draft' 
                AND invoices.invoice_status != 'Cancelled'
                AND invoice_status != 'Non-Billable'
            LEFT JOIN
                (SELECT 
                    payment_invoice_id, 
                    SUM(payment_amount) as payment_amount 
                 FROM payments 
                 GROUP BY payment_invoice_id) as payments
            ON
                invoices.invoice_id = payments.payment_invoice_id
            GROUP BY
                clients.client_id,
                clients.client_name
            HAVING 
                balance > 0
            ORDER BY
                balance DESC
        ");

        ?>
        
        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Client</th>
                    <th class="text-right">Balance</th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = mysqli_fetch_array($sql_clients)) {
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $balance = floatval($row['balance']);

                    ?>

                    <tr>
                        <td><a href="../../user/invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $balance, $session_company_currency); ?></td>
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
require_once "../../includes/footer.php";

