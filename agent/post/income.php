<?php

/*
 * ITFlow - Income POST request handler
 */

if (!defined('FROM_POST_HANDLER')) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

// The Income page merges payments and revenues, so its bulk actions have to fan out across both
// tables. Selection parsing is shared - see income_model.php.
// Gating: these are the multi-row form of the row Edit action on the Income page, so they gate the
// same way the payment edit handler does (the strictest of the two row types).

if (isset($_POST['bulk_edit_income_account'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);
    enforceUserPermission('module_financial', 3);

    require_once 'income_model.php';

    $account_id = intval($_POST['bulk_account_id']);

    // Get Account name for logging and Notification - and confirm it is a real, un-archived account
    $sql_account = mysqli_query($mysqli, "SELECT account_name FROM accounts WHERE account_id = $account_id AND account_archived_at IS NULL LIMIT 1");
    $row = mysqli_fetch_assoc($sql_account);

    if (!$row || !$income_count) {
        flashAlert("Nothing to update", 'error');
        redirect();
    }

    $account_name = escapeSql($row['account_name']);

    $updated_count = 0;

    // Payments - client comes from the invoice the payment was made against
    foreach ($payment_ids as $payment_id) {

        $sql = mysqli_query($mysqli, "SELECT payment_reference, invoice_client_id FROM payments LEFT JOIN invoices ON payment_invoice_id = invoice_id WHERE payment_id = $payment_id AND payment_archived_at IS NULL");
        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            continue;
        }

        $payment_reference = escapeSql($row['payment_reference']);
        $client_id = intval($row['invoice_client_id']);

        if ($client_id) {
            enforceClientAccess($client_id);
        }

        mysqli_query($mysqli, "UPDATE payments SET payment_account_id = $account_id WHERE payment_id = $payment_id");

        logAudit("Payment", "Edit", "$session_name assigned payment $payment_reference to account $account_name", $client_id, $payment_id);

        $updated_count++;

    }

    // Revenues
    foreach ($revenue_ids as $revenue_id) {

        $sql = mysqli_query($mysqli, "SELECT revenue_description, revenue_client_id FROM revenues WHERE revenue_id = $revenue_id AND revenue_archived_at IS NULL");
        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            continue;
        }

        $revenue_description = escapeSql($row['revenue_description']);
        $client_id = intval($row['revenue_client_id']);

        if ($client_id) {
            enforceClientAccess($client_id);
        }

        mysqli_query($mysqli, "UPDATE revenues SET revenue_account_id = $account_id WHERE revenue_id = $revenue_id");

        logAudit("Revenue", "Edit", "$session_name assigned revenue $revenue_description to account $account_name", $client_id, $revenue_id);

        $updated_count++;

    }

    if ($updated_count) {
        logAudit("Income", "Bulk Edit", "$session_name assigned $updated_count income record(s) to account $account_name");
        flashAlert("You assigned account <strong>$account_name</strong> to <strong>$updated_count</strong> income record(s)");
    } else {
        flashAlert("No income records were updated", 'error');
    }

    redirect();

}

if (isset($_POST['bulk_edit_income_category'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);
    enforceUserPermission('module_financial', 3);

    require_once 'income_model.php';

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification - and confirm it is a live Income category
    $sql_category = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_id = $category_id AND category_type = 'Income' AND category_archived_at IS NULL LIMIT 1");
    $row = mysqli_fetch_assoc($sql_category);

    if (!$row || !$income_count) {
        flashAlert("Nothing to update", 'error');
        redirect();
    }

    $category_name = escapeSql($row['category_name']);

    $revenue_updated_count = 0;
    $invoice_updated_count = 0;
    $skipped_count = 0;

    // Revenues carry their own category
    foreach ($revenue_ids as $revenue_id) {

        $sql = mysqli_query($mysqli, "SELECT revenue_description, revenue_client_id FROM revenues WHERE revenue_id = $revenue_id AND revenue_archived_at IS NULL");
        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            $skipped_count++;
            continue;
        }

        $revenue_description = escapeSql($row['revenue_description']);
        $client_id = intval($row['revenue_client_id']);

        if ($client_id) {
            enforceClientAccess($client_id);
        }

        mysqli_query($mysqli, "UPDATE revenues SET revenue_category_id = $category_id WHERE revenue_id = $revenue_id");

        logAudit("Revenue", "Edit", "$session_name assigned revenue $revenue_description to category $category_name", $client_id, $revenue_id);

        $revenue_updated_count++;

    }

    // A payment has no category of its own - it inherits the one on the invoice it was paid
    // against, so this writes to the INVOICE. Two selected payments against the same invoice
    // therefore collapse into a single invoice update, and a payment with no invoice is skipped.
    $invoice_ids = [];

    foreach ($payment_ids as $payment_id) {

        $sql = mysqli_query($mysqli, "SELECT payment_invoice_id FROM payments WHERE payment_id = $payment_id AND payment_archived_at IS NULL");
        $row = mysqli_fetch_assoc($sql);
        $payment_invoice_id = intval($row['payment_invoice_id'] ?? 0);

        if ($payment_invoice_id) {
            $invoice_ids[$payment_invoice_id] = $payment_invoice_id;
        } else {
            $skipped_count++;
        }

    }

    foreach ($invoice_ids as $invoice_id) {

        $sql = mysqli_query($mysqli, "SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            $skipped_count++;
            continue;
        }

        $invoice_prefix = escapeSql($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $client_id = intval($row['invoice_client_id']);

        enforceClientAccess($client_id);

        mysqli_query($mysqli, "UPDATE invoices SET invoice_category_id = $category_id WHERE invoice_id = $invoice_id");

        logAudit("Invoice", "Edit", "$session_name assigned invoice $invoice_prefix$invoice_number to category $category_name", $client_id, $invoice_id);

        $invoice_updated_count++;

    }

    // Spell out the invoice leg - the user selected payments, not invoices
    $updated_summary = [];
    if ($revenue_updated_count) {
        $updated_summary[] = "<strong>$revenue_updated_count</strong> revenue(s)";
    }
    if ($invoice_updated_count) {
        $updated_summary[] = "<strong>$invoice_updated_count</strong> invoice(s) behind the selected payment(s)";
    }

    if ($updated_summary) {

        logAudit("Income", "Bulk Edit", "$session_name assigned category $category_name to $revenue_updated_count revenue(s) and $invoice_updated_count invoice(s)");

        $skipped_note = '';
        if ($skipped_count) {
            $skipped_note = " - <strong>$skipped_count</strong> record(s) skipped";
        }

        flashAlert("You assigned category <strong>$category_name</strong> to " . implode(' and ', $updated_summary) . $skipped_note);

    } else {
        flashAlert("No income records were categorised - a payment can only take a category from the invoice it was paid against", 'error');
    }

    redirect();

}

if (isset($_POST['bulk_edit_income_method'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);
    enforceUserPermission('module_financial', 3);

    require_once 'income_model.php';

    // The method is stored by name on both tables, so validate it against the lookup list
    $payment_method = escapeSql($_POST['bulk_payment_method']);

    $sql_payment_method = mysqli_query($mysqli, "SELECT payment_method_name FROM payment_methods WHERE payment_method_name = '$payment_method' LIMIT 1");
    $row = mysqli_fetch_assoc($sql_payment_method);

    if (!$row || !$income_count) {
        flashAlert("Nothing to update", 'error');
        redirect();
    }

    $payment_method = escapeSql($row['payment_method_name']);

    $updated_count = 0;

    // Payments - client comes from the invoice the payment was made against
    foreach ($payment_ids as $payment_id) {

        $sql = mysqli_query($mysqli, "SELECT payment_reference, invoice_client_id FROM payments LEFT JOIN invoices ON payment_invoice_id = invoice_id WHERE payment_id = $payment_id AND payment_archived_at IS NULL");
        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            continue;
        }

        $payment_reference = escapeSql($row['payment_reference']);
        $client_id = intval($row['invoice_client_id']);

        if ($client_id) {
            enforceClientAccess($client_id);
        }

        mysqli_query($mysqli, "UPDATE payments SET payment_method = '$payment_method' WHERE payment_id = $payment_id");

        logAudit("Payment", "Edit", "$session_name set payment $payment_reference to payment method $payment_method", $client_id, $payment_id);

        $updated_count++;

    }

    // Revenues
    foreach ($revenue_ids as $revenue_id) {

        $sql = mysqli_query($mysqli, "SELECT revenue_description, revenue_client_id FROM revenues WHERE revenue_id = $revenue_id AND revenue_archived_at IS NULL");
        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            continue;
        }

        $revenue_description = escapeSql($row['revenue_description']);
        $client_id = intval($row['revenue_client_id']);

        if ($client_id) {
            enforceClientAccess($client_id);
        }

        mysqli_query($mysqli, "UPDATE revenues SET revenue_payment_method = '$payment_method' WHERE revenue_id = $revenue_id");

        logAudit("Revenue", "Edit", "$session_name set revenue $revenue_description to payment method $payment_method", $client_id, $revenue_id);

        $updated_count++;

    }

    if ($updated_count) {
        logAudit("Income", "Bulk Edit", "$session_name set $updated_count income record(s) to payment method $payment_method");
        flashAlert("You set payment method <strong>$payment_method</strong> on <strong>$updated_count</strong> income record(s)");
    } else {
        flashAlert("No income records were updated", 'error');
    }

    redirect();

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
            " . clientScopeSql('invoice_client_id') . "

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
