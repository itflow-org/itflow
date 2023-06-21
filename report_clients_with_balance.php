<?php

require_once("inc_all_reports.php");
validateAccountantRole();

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
                SUM(invoices.invoice_amount) - COALESCE(SUM(payments.payment_amount), 0) AS balance
            FROM 
                clients
            LEFT JOIN
                invoices
            ON 
                clients.client_id = invoices.invoice_client_id 
                AND invoices.invoice_status NOT LIKE 'Draft' 
                AND invoices.invoice_status NOT LIKE 'Cancelled'
            LEFT JOIN
                payments
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
                        <td><?php echo $client_name; ?></td>
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
require_once("footer.php");
