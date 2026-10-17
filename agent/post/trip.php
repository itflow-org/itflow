<?php

/*
 * ITFlow - trips (functions/trips.php does the work).
 *
 * New, Edit, Copy and Return Trip all post through tripSave(). Anyone logs
 * and changes their own trips; someone else's needs write access to
 * Financial, deleting them full access. Bulk Set Driver, Set Client, Set
 * Vehicle and Set Billable need write access, bulk delete full access - a
 * trip paid back for or billed is left as it was. Pay Mileage and taking a
 * trip off an invoice need write access to Financial (and Sales to take
 * one off).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_trip'])) {

    validateCSRFToken();

    $trip_id = tripSave($_POST, 0, $error);
    if (!$trip_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $row = tripRow($trip_id, false);
    logAudit("Trip", "Create", "$session_name logged the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']), $trip_id);

    flashAlert("Trip to <strong>" . escapeHtml($row['trip_destination']) . "</strong> logged - " . escapeHtml(tripMilesText($row['trip_miles'])));

    redirect();

}

if (isset($_POST['edit_trip'])) {

    validateCSRFToken();

    $trip_id = intval($_POST['trip_id'] ?? 0);
    if (!tripSave($_POST, $trip_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $row = tripRow($trip_id, false);
    logAudit("Trip", "Edit", "$session_name edited the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']), $trip_id);

    flashAlert("Trip to <strong>" . escapeHtml($row['trip_destination']) . "</strong> saved");

    redirect();

}

if (isset($_GET['delete_trip'])) {

    validateCSRFToken();

    $row = tripDelete(intval($_GET['delete_trip']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Trip", "Delete", "$session_name deleted the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']));

    flashAlert("Trip to <strong>" . escapeHtml($row['trip_destination']) . "</strong> deleted", 'error');

    redirect();

}

/*
 * Set the driver (an agent), client (0 for none - the site and ticket go
 * with the old one), vehicle (1 their own, 0 a company one) or billable
 * (1 or 0) of the ticked trips. One paid back for keeps its driver and
 * vehicle, one billed its client and billable.
 */
if (isset($_POST['bulk_edit_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $field = (string) ($_POST['field'] ?? '');
    $value = intval($_POST['value'] ?? 0);
    $set = '';
    $label = '';
    $skip = '';
    if ($field === 'driver') {
        $drivers = tripDrivers();
        if (isset($drivers[$value])) {
            $set = "trip_user_id = $value";
            $label = 'the driver to ' . $drivers[$value]['user_name'];
            $skip = 'trip_expense_id = 0';
        }
    } elseif ($field === 'client') {
        $skip = 'trip_invoice_id = 0';
        if (!$value) {
            $set = 'trip_client_id = 0, trip_location_id = 0, trip_ticket_id = 0, trip_billable = 0';
            $label = 'no client';
        } elseif (mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $value " . clientScopeSql('client_id')))) {
            // The site and ticket first - the client after, as MySQL sets them in order
            $set = "trip_location_id = IF(trip_client_id = $value, trip_location_id, 0), trip_ticket_id = IF(trip_client_id = $value, trip_ticket_id, 0), trip_client_id = $value";
            $label = 'the client to ' . getFieldById('clients', $value, 'client_name');
        }
    } elseif ($field === 'vehicle') {
        $set = 'trip_own_vehicle = ' . ($value ? 1 : 0);
        $label = $value ? 'their own vehicle' : 'a company vehicle';
        $skip = 'trip_expense_id = 0';
    } elseif ($field === 'billable') {
        $set = 'trip_billable = ' . ($value ? 'IF(trip_client_id > 0, 1, 0)' : 0);
        $label = $value ? 'billable' : 'not billable';
        $skip = 'trip_invoice_id = 0';
    }
    $trip_ids = array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? []))));
    if ($set === '' || !$trip_ids) {
        flashAlert($trip_ids ? 'Pick what to set them to' : 'Tick the trips to change first', 'error');
        redirect();
    }

    $count = 0;
    $kept = 0;
    foreach ($trip_ids as $trip_id) {
        $row = tripRow($trip_id);
        if (!$row) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE trips SET $set WHERE trip_id = $trip_id" . ($skip !== '' ? " AND $skip" : ''));
        if ($skip !== '' && !mysqli_affected_rows($mysqli) && !mysqli_num_rows(mysqli_query($mysqli, "SELECT trip_id FROM trips WHERE trip_id = $trip_id AND $skip"))) {
            $kept++;
            continue;
        }
        logAudit("Trip", "Edit", "$session_name set " . escapeSql($label) . " on the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']), $trip_id);
        $count++;
    }
    logAudit("Trip", "Bulk Edit", "$session_name set " . escapeSql($label) . " on $count trip" . ($count === 1 ? '' : 's'));

    flashAlert("Set " . escapeHtml($label) . " on <strong>$count</strong> trip" . ($count === 1 ? '' : 's')
        . ($kept ? " - $kept " . ($kept === 1 ? 'was' : 'were') . ' left as ' . ($kept === 1 ? 'it was' : 'they were') . ', ' . ($skip === 'trip_expense_id = 0' ? 'paid back for already' : 'billed already') : ''), $kept ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_delete_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $count = 0;
    $kept = 0;
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))) as $trip_id) {
        $row = tripDelete($trip_id, $delete_error);
        if (!$row) {
            $kept += $delete_error !== '' && strpos($delete_error, 'no longer exists') === false ? 1 : 0;
            continue;
        }
        logAudit("Trip", "Delete", "$session_name deleted the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']));
        $count++;
    }
    logAudit("Trip", "Bulk Delete", "$session_name deleted $count trip" . ($count === 1 ? '' : 's'));

    flashAlert("<strong>$count</strong> trip" . ($count === 1 ? '' : 's') . " deleted" . ($kept ? " - $kept paid back for or billed " . ($kept === 1 ? 'was' : 'were') . ' kept' : ''), 'error');

    redirect();

}

/*
 * Pay Mileage - the driver's ticked trips in their own vehicle become a
 * mileage expense for each client, paid personally by them at the rate
 * typed; paid back from an account there and then when asked.
 */
if (isset($_POST['trip_claim'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $user_id = intval($_POST['user_id'] ?? 0);
    $result = tripClaim($user_id, array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))), [
        'rate' => (string) ($_POST['rate'] ?? ''),
        'date' => (string) ($_POST['date'] ?? ''),
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $what = '<strong>' . escapeHtml($result['user_name']) . '</strong>\'s mileage - ' . ($result['count'] === 1 ? '1 trip' : $result['count'] . ' trips') . ', '
        . escapeHtml(tripDistanceText($result['miles'])) . ' at ' . escapeHtml(tripRateText($result['rate'])) . ': <strong>' . escapeHtml(billingMoney($result['amount'], $result['currency'])) . '</strong>';
    if (empty($_POST['pay_now'])) {
        flashAlert("$what to pay back - in To reimburse on the expenses");
        redirect();
    }
    $paid = expenseReimburse($user_id, $result['expense_ids'], [
        'account_id' => intval($_POST['account'] ?? 0),
        'date' => (string) ($_POST['date'] ?? ''),
        'payment_method' => (string) ($_POST['payment_method'] ?? ''),
        'reference' => (string) ($_POST['reference'] ?? ''),
        'by' => stripslashes($session_name),
    ], $pay_error);
    if (!$paid) {
        flashAlert("$what to pay back - not paid back yet: " . escapeHtml($pay_error), 'warning');
        redirect();
    }
    flashAlert("$what, paid back");

    redirect();

}

/* Take a trip off the invoice it was billed on - it is to bill again. */
if (isset($_GET['trip_unbill'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);
    enforceUserPermission('module_sales', 2);

    $trip_id = intval($_GET['trip_unbill']);
    $row = tripRow($trip_id);
    $number = $row ? tripUnbill($trip_id, $error) : '';
    if (!$row || $number === '') {
        flashAlert(escapeHtml($row ? $error : 'That trip no longer exists'), 'error');
        redirect();
    }

    logAudit("Trip", "Unbill", "$session_name took the trip " . escapeSql(tripLabel($row)) . " off invoice " . escapeSql($number), intval($row['trip_client_id']), $trip_id);
    if ($row['invoice_status'] !== null) {
        billingLog('invoice', intval($row['trip_invoice_id']), $row['invoice_status'], 'Trip taken off by ' . stripslashes($session_name) . ' - ' . tripLabel($row));
    }

    flashAlert("Trip to <strong>" . escapeHtml($row['trip_destination']) . "</strong> taken off " . escapeHtml($number) . " - it is to bill again");

    redirect();

}

/* An invoice's Add Trips - the client's ticked trips to bill, at the prices typed. */
if (isset($_POST['invoice_add_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    $invoice = billingRow('invoice', $invoice_id);
    if (!$invoice) {
        flashAlert('That invoice no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($invoice['client_id']);
    enforceClientAccess();

    $prices = [];
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))) as $trip_id) {
        $prices[$trip_id] = $_POST['trip_price'][$trip_id] ?? null;
    }
    $result = tripBillInvoice($client_id, $prices, [
        'invoice_id' => $invoice_id,
        'tax_id' => intval($_POST['tax_id'] ?? 0),
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect("invoice.php?invoice_id=$invoice_id");
    }

    flashAlert('<strong>' . ($result['trips'] === 1 ? '1 trip' : $result['trips'] . ' trips') . '</strong> billed - ' . escapeHtml(billingMoney($result['amount'], $invoice['invoice_currency_code']))
        . ' for ' . escapeHtml(tripDistanceText($result['miles'])));

    redirect("invoice.php?invoice_id=$invoice_id#items");

}

if (isExportRequest('export_trips')) {

    validateCSRFToken();

    $format = resolveExportFormat($_POST['export_trips']);

    // The same filters as the trips list, over the trips this agent may see
    $src = $_POST;
    if (!empty($_POST['client_id'])) {
        $src['client'] = intval($_POST['client_id']);
    }
    $where = tripFiltersSql($src, $filter_summary);
    $client_id = intval($src['client'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
    }

    $sql = mysqli_query($mysqli, "SELECT trips.*, client_name, driver.user_name AS driver_name, location_name, ticket_prefix, ticket_number, ticket_subject,
            claim.expense_id AS claim_expense_id, reimbursement_date, invoice_prefix, invoice_number
        FROM trips " . tripJoinsSql() . "
        LEFT JOIN expenses AS claim ON trips.trip_expense_id > 0 AND claim.expense_id = trips.trip_expense_id
        LEFT JOIN reimbursements ON reimbursement_id = claim.expense_reimbursement_id
        LEFT JOIN invoices ON trips.trip_invoice_id > 0 AND invoice_id = trips.trip_invoice_id
        WHERE $where
        ORDER BY trips.trip_date ASC, trips.trip_id ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('trips', $format, ($client_id ? getFieldById('clients', $client_id, 'client_name') : $session_company_name) . '-Trips', 'Trips', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['user_name'] = $row['driver_name'];
            $row['round_trip_display'] = intval($row['round_trip']) ? 'Yes' : 'No';
            $row['trip_one_way'] = number_format(tripOneWay($row), 1, '.', '');
            $row['ticket_display'] = $row['ticket_number'] !== null ? ticketReference($row) . ' - ' . $row['ticket_subject'] : '';
            $row['vehicle_display'] = intval($row['trip_own_vehicle']) ? 'Own' : 'Company';
            $row['paid_back_display'] = !intval($row['trip_own_vehicle']) ? '' : ($row['claim_expense_id'] === null ? 'To pay back' : ($row['reimbursement_date'] !== null ? 'Paid back ' . $row['reimbursement_date'] : 'On a mileage expense'));
            $row['billing_display'] = $row['invoice_number'] !== null ? 'Billed on ' . $row['invoice_prefix'] . $row['invoice_number'] : (intval($row['trip_billable']) ? 'To bill' : '');
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Trip", "Export", "$session_name exported $num_rows trip(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
