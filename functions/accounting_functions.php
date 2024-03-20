<?php 

// Accounting related functions

function getMonthlyTax($tax_name, $month, $year, $mysqli)
{
    // SQL to calculate monthly tax
    $sql = "SELECT SUM(item_tax) AS monthly_tax FROM invoice_items 
            LEFT JOIN invoices ON invoice_items.item_invoice_id = invoices.invoice_id
            LEFT JOIN payments ON invoices.invoice_id = payments.payment_invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) = $month
            AND invoice_items.item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['monthly_tax'] ?? 0;
}

function getQuarterlyTax($tax_name, $quarter, $year, $mysqli)
{
    // Calculate start and end months for the quarter
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $start_month + 2;

    // SQL to calculate quarterly tax
    $sql = "SELECT SUM(item_tax) AS quarterly_tax FROM invoice_items 
            LEFT JOIN invoices ON invoice_items.item_invoice_id = invoices.invoice_id
            LEFT JOIN payments ON invoices.invoice_id = payments.payment_invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) BETWEEN $start_month AND $end_month
            AND invoice_items.item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['quarterly_tax'] ?? 0;
}

function getTotalTax($tax_name, $year, $mysqli)
{
    // SQL to calculate total tax
    $sql = "SELECT SUM(item_tax) AS total_tax FROM invoice_items 
            LEFT JOIN invoices ON invoice_items.item_invoice_id = invoices.invoice_id
            LEFT JOIN payments ON invoices.invoice_id = payments.payment_invoice_id
            WHERE YEAR(payments.payment_date) = $year
            AND invoice_items.item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total_tax'] ?? 0;
}

//Get account currency code
function getAccountCurrencyCode($account_id)
{
    $sql = mysqli_query($mysqli, "SELECT account_currency_code FROM accounts WHERE account_id = $account_id");
    $row = mysqli_fetch_array($sql);
    $account_currency_code = nullable_htmlentities($row['account_currency_code']);
    return $account_currency_code;
}

function calculateAccountBalance($account_id)
{

    $sql_account = mysqli_query($mysqli, "SELECT * FROM accounts LEFT JOIN account_types ON accounts.account_type = account_types.account_type_id WHERE account_archived_at  IS NULL AND account_id = $account_id ORDER BY account_name ASC; ");
    $row = mysqli_fetch_array($sql_account);
    $opening_balance = floatval($row['opening_balance']);
    $account_id = intval($row['account_id']);

    $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
    $row = mysqli_fetch_array($sql_payments);
    $total_payments = floatval($row['total_payments']);

    $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
    $row = mysqli_fetch_array($sql_revenues);
    $total_revenues = floatval($row['total_revenues']);

    $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
    $row = mysqli_fetch_array($sql_expenses);
    $total_expenses = floatval($row['total_expenses']);

    $sql_invoices = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_invoices FROM invoices WHERE invoice_account_id = $account_id");
    $row = mysqli_fetch_array($sql_invoice_amounts);
    $total_invoices = floatval($row['total_invoices']);

    $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

    if ($balance == '') {
        $balance = '0.00';
    }

    if ($balance < 0) {
        $balance = 0;
    }

    return $balance;
}

function calculateInvoiceBalance($invoice_id)
{
    global $mysqli;

    $invoice_id_int = intval($invoice_id);
    $sql_invoice = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = $invoice_id_int");
    $row = mysqli_fetch_array($sql_invoice);
    $invoice_amount = floatval($row['invoice_amount']);

    $sql_payments = mysqli_query(
        $mysqli,
        "SELECT SUM(payment_amount) AS total_payments FROM payments
        WHERE payment_invoice_id = $invoice_id
        "
    );

    $row = mysqli_fetch_array($sql_payments);
    $total_payments = floatval($row['total_payments']);

    $balance = $invoice_amount - $total_payments;

    if ($balance == '') {
        $balance = '0.00';
    }

    return $balance;
}

function getClientBalance($client_id, $credits = false) {

    global $mysqli;

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled'");
    $row = mysqli_fetch_array($sql_invoice_amounts);

    $invoice_amounts = floatval($row['invoice_amounts']);

    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id");
    $row = mysqli_fetch_array($sql_amount_paid);

    $amount_paid = floatval($row['amount_paid']);

    if ($credits) {
        $sql_credits = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_amounts FROM credits WHERE credit_client_id = $client_id");
        $row = mysqli_fetch_array($sql_credits);
        $credit_amounts = floatval($row['credit_amounts']);

        $balance = $invoice_amounts - ($amount_paid + $credit_amounts);

        if ($balance < 0) {
            $balance = 0;
        }
        return $balance;
    } else {
        $balance = $invoice_amounts - $amount_paid;

        if ($balance < 0) {
            $balance = 0;
        }
        return $balance;
    }
}