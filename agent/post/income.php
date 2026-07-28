<?php

/*
 * ITFlow - Income POST request handler
 */

if (!defined('FROM_POST_HANDLER')) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if (isset($_POST['export_income_csv'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial');

    $date_from = escapeSql($_POST['date_from']);
    $date_to = escapeSql($_POST['date_to']);
    $account = intval($_POST['account']);

    // Client Filter - carried over from the client-scoped Income page
    $client_id = intval($_POST['client_id'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
        $payment_client_query = "AND invoice_client_id = $client_id";
        $revenue_client_query = "AND revenue_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
    } else {
        // Default - any
        $payment_client_query = '';
        $revenue_client_query = '';
        $file_name_prepend = "$session_company_name-";
    }

    // Type Filter
    $income_types_array = ['Payment', 'Revenue'];
    if (!empty($_POST['type']) && in_array($_POST['type'], $income_types_array)) {
        $type_query = "AND (income_type = '" . escapeSql($_POST['type']) . "')";
    } else {
        // Default - any
        $type_query = '';
    }

    // Account Filter
    if ($account) {
        $account_query = "AND (income_account_id = $account)";
    } else {
        // Default - any
        $account_query = '';
    }

    // Payment Method Filter
    if (!empty($_POST['method'])) {
        $method_query = "AND (income_method = '" . escapeSql($_POST['method']) . "')";
    } else {
        // Default - any
        $method_query = '';
    }

    // Search Filter - mirrors the income page search box
    $q = escapeSql($_POST['q']);
    if (!empty($q)) {
        $search_query = "AND (income_source LIKE '%$q%' OR income_client LIKE '%$q%' OR income_account LIKE '%$q%' OR income_method LIKE '%$q%' OR income_reference LIKE '%$q%' OR income_amount LIKE '%$q%')";
    } else {
        // Default - any
        $search_query = '';
    }

    // Date Filter
    if (!empty($date_from) && !empty($date_to)) {
        $date_query = "AND DATE(income_date) BETWEEN '$date_from' AND '$date_to'";
    } else {
        $date_query = '';
    }

    // Same union as income.php - payments applied to an invoice, and standalone revenues.
    // Transfers between accounts are stored as a linked expense + revenue pair, so the revenue leg
    //  is excluded here (transfer_id IS NULL) - moving your own money is not income.
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM (
            SELECT
                'Payment' AS income_type,
                payment_id AS income_id,
                payment_date AS income_date,
                payment_created_at AS income_created_at,
                CONCAT(invoice_prefix, invoice_number) AS income_source,
                NULL AS income_description,
                client_name AS income_client,
                payment_amount AS income_amount,
                payment_currency_code AS income_currency_code,
                payment_method AS income_method,
                payment_reference AS income_reference,
                payment_account_id AS income_account_id,
                account_name AS income_account
            FROM payments
            LEFT JOIN invoices ON payment_invoice_id = invoice_id
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN accounts ON payment_account_id = account_id
            WHERE payment_archived_at IS NULL
            $payment_client_query
            $access_permission_query

            UNION ALL

            SELECT
                'Revenue',
                revenue_id,
                revenue_date,
                revenue_created_at,
                category_name,
                revenue_description,
                client_name,
                revenue_amount,
                revenue_currency_code,
                revenue_payment_method,
                revenue_reference,
                revenue_account_id,
                account_name
            FROM revenues
            LEFT JOIN categories ON revenue_category_id = category_id
            LEFT JOIN clients ON revenue_client_id = client_id
            LEFT JOIN accounts ON revenue_account_id = account_id
            LEFT JOIN transfers ON transfer_revenue_id = revenue_id
            WHERE revenue_archived_at IS NULL
            AND transfer_id IS NULL
            $revenue_client_query
        ) AS income
        WHERE 1 = 1
        $date_query
        $type_query
        $account_query
        $method_query
        $search_query
        ORDER BY income_date ASC, income_created_at ASC, income_type ASC, income_id ASC"
    );

    $num_rows = mysqli_num_rows($sql);
    if ($num_rows > 0) {
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitizeFilename($file_name_prepend . "Income-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Type', 'Source', 'Description', 'Client', 'Amount', 'Currency', 'Payment Method', 'Reference', 'Account');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while ($row = mysqli_fetch_assoc($sql)) {
            $lineData = array($row['income_date'], $row['income_type'], $row['income_source'], $row['income_description'], $row['income_client'], $row['income_amount'], $row['income_currency_code'], $row['income_method'], $row['income_reference'], $row['income_account']);
            fputcsv($f, array_map('escapeCsvFormula', $lineData), $delimiter, $enclosure, $escape);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    logAudit("Income", "Export", "$session_name exported $num_rows income record(s) to CSV file");

    exit;

}
