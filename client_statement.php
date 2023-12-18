<?php


require_once "inc_all.php";


if (isset($_GET['client_id'])) {

    $client_id = intval($_GET['client_id']);

    $sql_client_details = "
    SELECT
        client_name,
        client_type,
        client_website,
        client_net_terms
    FROM
        clients
    WHERE
        client_id = $client_id";

    $result_client_details = mysqli_query($mysqli, $sql_client_details);
    $row_client_details = mysqli_fetch_assoc($result_client_details);

    $client_name = nullable_html_entities($row_client_details['client_name']);
    $client_type = nullable_html_entities($row_client_details['client_type']);
    $client_website = nullable_html_entities($row_client_details['client_website']);
    $client_net_terms = intval($row_client_details['client_net_terms']);

    $sql_client_unpaid_invoices = "
    SELECT
        invoice_id,
        invoice_number,
        invoice_prefix,
        invoice_date,
        invoice_due,
        invoice_amount
    FROM
        invoices
    WHERE
        invoice_client_id = $client_id
        AND invoice_status NOT LIKE 'Draft'
        AND invoice_status NOT LIKE 'Cancelled'
        AND invoice_status NOT LIKE 'Paid'";

    $result_client_unpaid_invoices = mysqli_query($mysqli, $sql_client_unpaid_invoices);

    $currency_code = getSettingValue($mysqli, "company_currency");

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="clients.php">Clients</a>
        </li>
        <li class="breadcrumb-item">
            <a href="client_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
        </li>
    </ol>

    <div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Statement for <?php echo $client_name ?></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div>
            <div class="table-responsive-sm">
                <table class="table table-sm">
                    <!-- Past Due Payments -->
                    <thead class="text-dark">
                        <tr>
                            <th>Invoice Number</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            while ($row = mysqli_fetch_assoc($result_client_unpaid_invoices)) {
                                $invoice_number = intval($row['invoice_number']);
                                $invoice_id = intval($row['invoice_id']);
                                $invoice_prefix = nullable_html_entities($row['invoice_prefix']);
                                $invoice_date = nullable_html_entities($row['invoice_date']);
                                $invoice_amount = floatval($row['invoice_amount']);
                                $invoice_amount_formatted = numfmt_format_currency($currency_format, $invoice_amount, $currency_code);
                                $invoice_url = "invoice.php?invoice_id=$invoice_id";
                                $invoice_due = nullable_html_entities($row['invoice_due']);

                                $invoice_balance = floatval(calculateInvoiceBalance($mysqli, $invoice_id));
                                $invoice_balance_formatted = numfmt_format_currency($currency_format, $invoice_balance, $currency_code);

                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo $invoice_url; ?>" target="_blank"><?php echo $invoice_prefix . $invoice_number; ?></a>
                                    </td>
                                    <td>
                                        <?php echo $invoice_date; ?>
                                    </td>
                                    <td>
                                        <?php echo $invoice_due; ?>
                                    </td>
                                    <td>
                                        <?php echo $invoice_amount_formatted; ?>
                                    </td>
                                    <td>
                                        <?php echo $invoice_balance_formatted; ?>
                                    </td>
                                <?php

                            }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive-sm">
                <!-- Previous Payments -->
                <table class="table table-sm">
                    <thead class="text-dark">
                        <tr>
                            <th>Payment Reference</th>
                            <th>Payment Date</th>
                            <th>Payment Amount</th>
                            <th>Invoice Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            $sql_client_payments = "
                            SELECT 
                                payments.payment_date,
                                payments.payment_amount,
                                payments.payment_reference,
                                invoices.invoice_number,
                                invoices.invoice_prefix
                            FROM 
                                payments
                            LEFT JOIN
                                invoices ON payments.payment_invoice_id = invoices.invoice_id
                            WHERE
                                payment_account_id = $client_id
                                AND payment_archived_at IS NULL
                            ORDER BY
                                payment_date DESC";

                            $result_client_payments = mysqli_query($mysqli, $sql_client_payments);

                            while ($row = mysqli_fetch_assoc($result_client_payments)) {
                                $payment_date = nullable_html_entities($row['payment_date']);
                                $payment_amount = floatval($row['payment_amount']);
                                $payment_reference = nullable_html_entities($row['payment_reference']);
                                $invoice_number = nullable_html_entities($row['invoice_prefix'].$row['invoice_number']);
                                $payment_amount_formatted = numfmt_format_currency($currency_format, $payment_amount, $currency_code);

                                ?>
                                <tr>
                                    <td>
                                        <?php echo $payment_reference; ?>
                                    </td>
                                    <td>
                                        <?php echo $payment_date; ?>
                                    </td>
                                    <td>
                                        <?php echo $payment_amount_formatted; ?>
                                    </td>
                                    <td>
                                        <?php echo $invoice_number; ?>
                                    </td>
                                <?php

                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once "footer.php";

                        }
?>
