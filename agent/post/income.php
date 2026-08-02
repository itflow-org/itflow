<?php

/*
 * ITFlow - Income POST request handler
 */

if (!defined('FROM_POST_HANDLER')) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if (isset($_POST['export_income'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial');

    $format = resolveExportFormat($_POST['export_income']);

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

    // Category Filter - a revenue carries its own category, a payment inherits the one on the
    // invoice it was paid against. Both come from the same 'Income' category pool.
    $category = intval($_POST['category'] ?? 0);
    if ($category) {
        $category_query = "AND (income_category_id = $category)";
    } else {
        // Default - any
        $category_query = '';
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
        $search_query = "AND (income_source LIKE '%$q%' OR income_category LIKE '%$q%' OR income_client LIKE '%$q%' OR income_account LIKE '%$q%' OR income_method LIKE '%$q%' OR income_reference LIKE '%$q%' OR income_amount LIKE '%$q%')";
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

    // Filter summary for the export header. This handler was the only one not building it,
    // so a filtered PDF came out looking like a full export.
    $filter_summary = [];

    if ($client_id) {
        $filter_summary['Client'] = $client_name;
    }
    if (!empty($_POST['type']) && in_array($_POST['type'], $income_types_array)) {
        $filter_summary['Type'] = $_POST['type'];
    }
    if ($category) {
        $filter_summary['Category'] = getFieldById('categories', $category, 'category_name');
    }
    if ($account) {
        $filter_summary['Account'] = getFieldById('accounts', $account, 'account_name');
    }
    if (!empty($_POST['method'])) {
        $filter_summary['Payment Method'] = $_POST['method'];
    }
    if (!empty($date_from) && !empty($date_to)) {
        $filter_summary['Date'] = "$date_from to $date_to";
    }
    if (!empty($_POST['q'])) {
        $filter_summary['Search'] = $_POST['q'];
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
                category_name AS income_category,
                IFNULL(invoice_category_id, 0) AS income_category_id,
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
            LEFT JOIN categories ON invoice_category_id = category_id
            WHERE payment_archived_at IS NULL
            $payment_client_query
            $access_permission_query

            UNION ALL

            SELECT
                'Revenue',
                revenue_id,
                revenue_date,
                revenue_created_at,
                revenue_description,
                category_name,
                revenue_category_id,
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
        $category_query
        $account_query
        $method_query
        $search_query
        ORDER BY income_date ASC, income_created_at ASC, income_type ASC, income_id ASC"
    );

    $num_rows = mysqli_num_rows($sql);
    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('income', $format, $file_name_prepend . 'Income', 'Income', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Income", "Export", "$session_name exported $num_rows income record(s) to a " . strtoupper($format) . " file");

    exit;

}
