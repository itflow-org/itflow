<?php
    require_once "inc_all_reports.php";

    validateAccountantRole();

    // Fetch Accounts and their balances
    $sql_client_balance_report = "
    SELECT 
    clients.client_id,
    clients.client_name,
    IFNULL(SUM(invoices.invoice_amount), 0) - IFNULL(SUM(payments.payment_amount), 0) AS balance,
    contacts.contact_phone AS billing_contact_phone,
    IFNULL(recurring_totals.recurring_monthly_total, 0) AS recurring_monthly_total,
    (IFNULL(SUM(invoices.invoice_amount), 0) - IFNULL(SUM(payments.payment_amount), 0) - IFNULL(recurring_totals.recurring_monthly_total, 0)) AS behind_amount,
    CASE
        WHEN IFNULL(recurring_totals.recurring_monthly_total, 0) > 0 THEN
            (IFNULL(SUM(invoices.invoice_amount), 0) - IFNULL(SUM(payments.payment_amount), 0) - IFNULL(recurring_totals.recurring_monthly_total, 0)) / recurring_totals.recurring_monthly_total
        ELSE
            0
    END AS months_behind
    FROM 
        clients
    LEFT JOIN
        invoices
    ON 
        clients.client_id = invoices.invoice_client_id 
        AND invoices.invoice_status NOT LIKE 'Draft' 
        AND invoices.invoice_status NOT LIKE 'Cancelled'
    LEFT JOIN
        (SELECT 
            payment_invoice_id, 
            SUM(payment_amount) as payment_amount 
        FROM payments 
        GROUP BY payment_invoice_id) as payments
    ON
        invoices.invoice_id = payments.payment_invoice_id
    LEFT JOIN
        contacts
    ON
        clients.client_id = contacts.contact_client_id AND contacts.contact_billing = 1
    LEFT JOIN
        (SELECT 
            recurring_client_id,
            SUM(recurring_amount) AS recurring_monthly_total 
        FROM recurring 
        WHERE recurring_status = 1 AND recurring_frequency = 'month'
        GROUP BY recurring_client_id) as recurring_totals
    ON
        clients.client_id = recurring_totals.recurring_client_id
    GROUP BY
        clients.client_id,
        clients.client_name,
        contacts.contact_phone,
        recurring_totals.recurring_monthly_total
    HAVING 
        balance > 0 AND months_behind >= 2
    ORDER BY
        months_behind DESC";

    $result_client_balance_report = mysqli_query($mysqli, $sql_client_balance_report);

    $currency_row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT company_currency FROM companies WHERE company_id = 1"));
    $company_currency = nullable_htmlentities($currency_row['company_currency']);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Collections</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div>
            <div class="table-responsive-sm">
                <table class="table table-sm">
                    <thead class="text-dark">
                        <tr>
                            <th>Client Name</th>
                            <th>Balance</th>
                            <th>Billing Contact Phone</th>
                            <th>Monthly Recurring Amount</th>
                            <th>Past Due Balance</th>
                            <th>Months Past Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $processed_clients = []; // Array to keep track of processed client IDs

                            while ($row = mysqli_fetch_assoc($result_client_balance_report)) {
                                $client_id = intval($row['client_id']);
                                    // Skip this row if we've already processed this client ID
                                if (in_array($client_id, $processed_clients)) {
                                    continue; // Skip to the next iteration of the loop
                                }    // Add the client ID to the array of processed clients
                                $processed_clients[] = $client_id;
                                
                                $client_name = nullable_htmlentities($row['client_name']);
                                $balance = floatval($row['balance']);
                                $billing_contact_phone = formatPhoneNumber($row['billing_contact_phone']);
                                $recurring_monthly_total = floatval($row['recurring_monthly_total']);
                                $behind_amount = floatval($row['behind_amount']);
                                $months_behind = number_format($row['months_behind']);
                                
                                $formatted_balance = numfmt_format_currency($currency_format, $balance, $company_currency);
                                $formatted_recurring_monthly_total = numfmt_format_currency($currency_format, $recurring_monthly_total, $company_currency);
                                $formatted_behind_amount = numfmt_format_currency($currency_format, $behind_amount, $company_currency);
                                
                                echo "<tr>";
                                echo "<td><a href='client_statement.php?client_id=$client_id'>$client_name</a></td>";
                                echo "<td>$formatted_balance</td>";
                                echo "<td>$billing_contact_phone</td>";
                                echo "<td>$formatted_recurring_monthly_total</td>";
                                echo "<td>$formatted_behind_amount</td>";
                                echo "<td>$months_behind</td>";
                                echo "</tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once "footer.php";


?>
