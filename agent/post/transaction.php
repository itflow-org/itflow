<?php

/*
 * ITFlow - Transactions POST request handler
 */

if (!defined('FROM_POST_HANDLER')) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if (isset($_POST['export_transactions_csv'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_financial');

    $date_from = escapeSql($_POST['date_from']);
    $date_to = escapeSql($_POST['date_to']);
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);

    // Type Filter
    $transaction_types_array = ['Revenue', 'Payment', 'Expense', 'Transfer In', 'Transfer Out'];
    if (!empty($_POST['type']) && in_array($_POST['type'], $transaction_types_array)) {
        $type_query = "AND (transaction_type = '" . escapeSql($_POST['type']) . "')";
    } else {
        // Default - any
        $type_query = '';
    }

    // Category Filter
    if ($category) {
        $category_query = "AND (transaction_category_id = $category)";
    } else {
        // Default - any
        $category_query = '';
    }

    // Client Filter
    $client = intval($_POST['client']);
    if ($client) {
        $client_query = "AND (transaction_client_id = $client)";
    } else {
        // Default - any
        $client_query = '';
    }

    // Payment Method Filter
    if (!empty($_POST['payment_method'])) {
        $payment_method_query = "AND (transaction_payment_method = '" . escapeSql($_POST['payment_method']) . "')";
    } else {
        // Default - any
        $payment_method_query = '';
    }

    // Amount Range Filter - matched on absolute value so direction doesn't matter
    if (isset($_POST['amount_min']) && $_POST['amount_min'] != '') {
        $amount_min_query = 'AND (ABS(transaction_amount) >= ' . floatval($_POST['amount_min']) . ')';
    } else {
        // Default - any
        $amount_min_query = '';
    }
    if (isset($_POST['amount_max']) && $_POST['amount_max'] != '') {
        $amount_max_query = 'AND (ABS(transaction_amount) <= ' . floatval($_POST['amount_max']) . ')';
    } else {
        // Default - any
        $amount_max_query = '';
    }

    // Search Filter - mirrors the transactions page search box
    $q = escapeSql($_POST['q']);
    if (!empty($q)) {
        $search_query = "AND (transaction_description LIKE '%$q%' OR transaction_category LIKE '%$q%' OR transaction_reference LIKE '%$q%' OR transaction_other_account LIKE '%$q%' OR transaction_amount LIKE '%$q%')";
    } else {
        // Default - any
        $search_query = '';
    }

    // Date Filter
    if (!empty($date_from) && !empty($date_to)) {
        $date_query = "AND DATE(transaction_date) BETWEEN '$date_from' AND '$date_to'";
    } else {
        $date_query = '';
    }

    // Account is required - export is a per-account ledger
    if ($account) {

        // Account details for the running balance and file name
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $account LIMIT 1"));
        $account_name = $row['account_name'];
        $account_opening_balance = floatval($row['opening_balance']);

        // Same ledger as transactions.php - full unfiltered ledger for the balance, filters applied outside
        $sql = mysqli_query(
            $mysqli,
            "SELECT * FROM (
                SELECT *,
                    $account_opening_balance + SUM(transaction_amount) OVER (ORDER BY transaction_date ASC, transaction_created_at ASC, transaction_type ASC, transaction_id ASC) AS transaction_balance
                FROM (
                    SELECT
                        CASE WHEN transfer_id IS NOT NULL THEN 'Transfer In' ELSE 'Revenue' END AS transaction_type,
                        revenue_id AS transaction_id,
                        revenue_date AS transaction_date,
                        revenue_created_at AS transaction_created_at,
                        CASE WHEN transfer_id IS NOT NULL THEN transfer_notes ELSE revenue_description END AS transaction_description,
                        from_account.account_name AS transaction_other_account,
                        revenue_reference AS transaction_reference,
                        revenue_client_id AS transaction_client_id,
                        CASE WHEN transfer_id IS NOT NULL THEN transfer_method ELSE revenue_payment_method END AS transaction_payment_method,
                        revenue_category_id AS transaction_category_id,
                        category_name AS transaction_category,
                        revenue_amount AS transaction_amount
                    FROM revenues
                    LEFT JOIN categories ON revenue_category_id = category_id
                    LEFT JOIN transfers ON transfer_revenue_id = revenue_id
                    LEFT JOIN expenses AS transfer_expense ON transfer_expense_id = transfer_expense.expense_id
                    LEFT JOIN accounts AS from_account ON transfer_expense.expense_account_id = from_account.account_id
                    WHERE revenue_account_id = $account
                    AND revenue_archived_at IS NULL

                    UNION ALL

                    SELECT
                        CASE WHEN transfer_id IS NOT NULL THEN 'Transfer Out' ELSE 'Expense' END,
                        expense_id,
                        expense_date,
                        expense_created_at,
                        CASE WHEN transfer_id IS NOT NULL THEN transfer_notes ELSE expense_description END,
                        to_account.account_name,
                        expense_reference,
                        expense_client_id,
                        CASE WHEN transfer_id IS NOT NULL THEN transfer_method ELSE expense_payment_method END,
                        expense_category_id,
                        category_name,
                        -expense_amount
                    FROM expenses
                    LEFT JOIN categories ON expense_category_id = category_id
                    LEFT JOIN transfers ON transfer_expense_id = expense_id
                    LEFT JOIN revenues AS transfer_revenue ON transfer_revenue_id = transfer_revenue.revenue_id
                    LEFT JOIN accounts AS to_account ON transfer_revenue.revenue_account_id = to_account.account_id
                    WHERE expense_account_id = $account
                    AND expense_archived_at IS NULL

                    UNION ALL

                    SELECT
                        'Payment',
                        payment_id,
                        payment_date,
                        payment_created_at,
                        CONCAT('Payment for Invoice ', invoice_prefix, invoice_number),
                        NULL,
                        payment_reference,
                        invoice_client_id,
                        payment_method,
                        0,
                        'Invoice Payment',
                        payment_amount
                    FROM payments
                    LEFT JOIN invoices ON payment_invoice_id = invoice_id
                    WHERE payment_account_id = $account
                    AND payment_archived_at IS NULL
                ) AS ledger
            ) AS transactions
            WHERE 1 = 1
            $date_query
            $type_query
            $category_query
            $client_query
            $payment_method_query
            $amount_min_query
            $amount_max_query
            $search_query
            ORDER BY transaction_date ASC, transaction_created_at ASC, transaction_type ASC, transaction_id ASC"
        );

        $num_rows = mysqli_num_rows($sql);
        if ($num_rows > 0) {
            $delimiter = ",";
            $enclosure = '"';
            $escape    = '\\';   // backslash
            $filename = sanitizeFilename("$session_company_name-$account_name-Transactions-" . date('Y-m-d_H-i-s') . ".csv");

            //create a file pointer
            $f = fopen('php://memory', 'w');

            //set column headers
            $fields = array('Date', 'Type', 'Description', 'Transfer Account', 'Reference', 'Category', 'Payment Method', 'Amount', 'Balance');
            fputcsv($f, $fields, $delimiter, $enclosure, $escape);

            //output each row of the data, format line as csv and write to file pointer
            while ($row = mysqli_fetch_assoc($sql)) {
                $lineData = array($row['transaction_date'], $row['transaction_type'], $row['transaction_description'], $row['transaction_other_account'], $row['transaction_reference'], $row['transaction_category'], $row['transaction_payment_method'], $row['transaction_amount'], $row['transaction_balance']);
                fputcsv($f, $lineData, $delimiter, $enclosure, $escape);
            }

            //move back to beginning of file
            fseek($f, 0);

            //set headers to download file rather than displayed
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '";');

            //output all remaining data on a file pointer
            fpassthru($f);
        }

        logAudit("Transaction", "Export", "$session_name exported $num_rows transaction(s) to CSV file");

    }

    exit;

}
