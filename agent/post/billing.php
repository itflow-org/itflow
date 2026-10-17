<?php

/*
 * ITFlow - line items on invoices, quotes and recurring invoices, and the
 * small things the billing pages do in place.
 *
 * One handler for the lines on all three - which kind is in the form's
 * "type" (invoice, quote, recurring); functions/billing.php does the work.
 * A line can only change while the document is open for it: not once an
 * invoice is paid, cancelled or non-billable, nor once a quote has been
 * answered or invoiced.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/**
 * The document a line request is for, checked: the kind, the row (scoped to
 * the clients this agent may open) and that its lines may change. Flashes
 * and goes back when not.
 */
function billingPostDocument($type, $id) {
    global $client_id;
    $t = billingType($type);
    $row = $t ? billingRow($type, $id) : null;
    if (!$row) {
        flashAlert('That document no longer exists, or is not one you can open.', 'error');
        redirect();
    }
    $client_id = intval($row['client_id']);
    enforceClientAccess();
    if (!billingCanEditItems($type, $row)) {
        flashAlert(billingNumber($type, $row) . " can't be changed now - " . ($type === 'quote' ? 'it has been answered or invoiced.' : 'it is paid, cancelled or non-billable.'), 'error');
        redirect();
    }
    return $row;
}

/** Back to the document, at the lines. */
function billingPostBack($type, $id, $hash = 'items') {
    $t = billingType($type);
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $path = (string) parse_url($referer, PHP_URL_PATH);
    $url = basename($path) === $t['page'] ? strtok($referer, '#') : $t['page'] . '?' . $t['param'] . '=' . intval($id);
    redirect($url . ($hash !== '' ? "#$hash" : ''));
}

/** What billing time did, for the flash - "TCK-12 and 3 more billed on INV-45 - 6.5 h on 4 lines". */
function unbilledResultText(array $result) {
    $tickets = $result['tickets'];
    $what = count($tickets) === 1 ? $tickets[0] : $tickets[0] . ' and ' . (count($tickets) - 1) . ' more';
    $text = '<strong>' . escapeHtml($what) . '</strong> billed on <strong>' . escapeHtml($result['number']) . '</strong> - ' . billingQuantity($result['hours']) . ' h'
        . ($result['lines'] > 1 ? ' on ' . $result['lines'] . ' lines' : '');
    if ($result['no_charge']) {
        $text .= '. ' . escapeHtml(implode(', ', $result['no_charge'])) . (count($result['no_charge']) === 1 ? ' had' : ' had') . ' no hours, so went on with no charge';
    }
    return $text;
}

/** What billing tickets, expenses and trips together did, for the flash - the time's text, then the expenses', then the trips'. */
function unbilledBothText(array $result, $currency) {
    $parts = [];
    if ($result['time']) {
        $parts[] = unbilledResultText($result['time']);
    }
    if ($result['expenses']) {
        $e = $result['expenses'];
        $parts[] = ($result['time'] ? '' : '<strong>' . escapeHtml($e['number']) . '</strong>: ') . '<strong>' . $e['expenses'] . ' expense' . ($e['expenses'] === 1 ? '' : 's') . '</strong> billed - '
            . escapeHtml(billingMoney($e['amount'], $currency)) . ' on ' . escapeHtml(billingMoney($e['cost'], $currency)) . ' of cost';
    }
    if (!empty($result['trips'])) {
        $t = $result['trips'];
        $parts[] = ($result['time'] || $result['expenses'] ? '' : '<strong>' . escapeHtml($t['number']) . '</strong>: ') . '<strong>' . $t['trips'] . ' trip' . ($t['trips'] === 1 ? '' : 's') . '</strong> billed - '
            . escapeHtml(billingMoney($t['amount'], $currency)) . ' for ' . escapeHtml(tripDistanceText($t['miles']));
    }
    $text = implode('. ', $parts);
    if ($result['error'] !== '') {
        $text .= '. Not billed: ' . escapeHtml($result['error']);
    }
    return $text;
}

/** The ticked expenses and their prices from Unbilled Time - expense id => price; empty unless the agent can bill them. */
function unbilledPostPrices() {
    $prices = [];
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['expense_ids'] ?? [])))) as $expense_id) {
        $prices[$expense_id] = $_POST['expense_price'][$expense_id] ?? null;
    }
    if ($prices) {
        enforceUserPermission('module_financial', 2);
    }
    return $prices;
}

/** The ticked trips and their prices from Unbilled Time - trip id => price; empty unless the agent can bill them. */
function unbilledPostTripPrices() {
    $prices = [];
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))) as $trip_id) {
        $prices[$trip_id] = $_POST['trip_price'][$trip_id] ?? null;
    }
    if ($prices) {
        enforceUserPermission('module_financial', 2);
    }
    return $prices;
}

/** The audit log's name for each kind. */
function billingAuditType($type) {
    return ['invoice' => 'Invoice', 'quote' => 'Quote', 'recurring' => 'Recurring Invoice'][$type] ?? 'Invoice';
}

if (isset($_POST['billing_item_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $type = (string) ($_POST['type'] ?? '');
    $doc_id = intval($_POST['doc_id'] ?? 0);
    $item_id = intval($_POST['item_id'] ?? 0);
    $row = billingPostDocument($type, $doc_id);
    $number = billingNumber($type, $row);

    $saved = billingItemSave($type, $doc_id, $_POST, $item_id, $error);
    if (!$saved) {
        flashAlert(escapeHtml($error), 'error');
        billingPostBack($type, $doc_id, $item_id ? 'items' : 'add-line');
    }

    $name = trim((string) $_POST['name']);
    logAudit(billingAuditType($type), 'Edit', escapeSql("$session_name " . ($item_id ? 'edited' : 'added') . " line $name " . ($item_id ? 'on' : 'to') . " $number"), $client_id, $doc_id);

    flashAlert('<strong>' . escapeHtml($name) . '</strong> ' . ($item_id ? 'updated' : 'added'));

    billingPostBack($type, $doc_id, $item_id ? "line-$saved" : 'add-line');

}

if (isset($_GET['billing_item_delete'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $type = (string) ($_GET['type'] ?? '');
    $doc_id = intval($_GET['doc_id'] ?? 0);
    $item_id = intval($_GET['billing_item_delete']);
    $row = billingPostDocument($type, $doc_id);

    $item = billingItemDelete($type, $doc_id, $item_id);
    if (!$item) {
        flashAlert('That line was already gone.', 'error');
        billingPostBack($type, $doc_id);
    }

    logAudit(billingAuditType($type), 'Edit', escapeSql("$session_name removed line {$item['item_name']} from " . billingNumber($type, $row)), $client_id, $doc_id);

    flashAlert('<strong>' . escapeHtml($item['item_name']) . '</strong> removed', 'error');

    billingPostBack($type, $doc_id);

}

/*
 * Pause or resume a recurring invoice. Resuming one whose next date has
 * passed says so - it only runs on its next date, so that has to move first.
 */
if (isset($_GET['recurring_invoice_set_status'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_id = intval($_GET['recurring_invoice_set_status']);
    $status = intval($_GET['status'] ?? 0) ? 1 : 0;
    $row = billingRow('recurring', $recurring_invoice_id);
    if (!$row) {
        flashAlert('That recurring invoice no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['client_id']);
    enforceClientAccess();

    $number = billingNumber('recurring', $row);
    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_status = $status WHERE recurring_invoice_id = $recurring_invoice_id");
    billingLog('recurring', $recurring_invoice_id, $status ? 'Active' : 'Paused', ($status ? 'Resumed' : 'Paused') . " by $session_name");
    logAudit('Recurring Invoice', 'Edit', escapeSql("$session_name " . ($status ? 'resumed' : 'paused') . " recurring invoice $number"), $client_id, $recurring_invoice_id);

    if ($status && $row['recurring_invoice_next_date'] < date('Y-m-d')) {
        flashAlert("<strong>" . escapeHtml($number) . "</strong> resumed - its next date has passed, so move it on before it will run again.", 'warning');
    } else {
        flashAlert("<strong>" . escapeHtml($number) . "</strong> " . ($status ? 'resumed' : 'paused'));
    }

    redirect();

}

/*
 * Move a recurring invoice's next date on to the first one that hasn't
 * passed, skipping the runs it missed (nothing is billed for them).
 */
if (isset($_GET['recurring_invoice_catch_up'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_id = intval($_GET['recurring_invoice_catch_up']);
    $row = billingRow('recurring', $recurring_invoice_id);
    if (!$row) {
        flashAlert('That recurring invoice no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['client_id']);
    enforceClientAccess();

    $next = recurringInvoiceCatchUp($row);
    $number = billingNumber('recurring', $row);
    if ($next === $row['recurring_invoice_next_date']) {
        flashAlert("<strong>" . escapeHtml($number) . "</strong> is not behind.");
        redirect();
    }
    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_next_date = '" . escapeSql($next) . "' WHERE recurring_invoice_id = $recurring_invoice_id");
    billingLog('recurring', $recurring_invoice_id, 'Rescheduled', "Next date moved from {$row['recurring_invoice_next_date']} to $next by $session_name");
    logAudit('Recurring Invoice', 'Edit', escapeSql("$session_name moved recurring invoice $number on to $next"), $client_id, $recurring_invoice_id);

    flashAlert("<strong>" . escapeHtml($number) . "</strong> next runs on " . escapeHtml($next));

    redirect();

}

/*
 * Count a recurring invoice's seat lines again now, and take its taxes at
 * today's rates - what the nightly run does before it makes an invoice.
 */
if (isset($_GET['recurring_invoice_sync'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_id = intval($_GET['recurring_invoice_sync']);
    $row = billingRow('recurring', $recurring_invoice_id);
    if (!$row) {
        flashAlert('That recurring invoice no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['client_id']);
    enforceClientAccess();

    $changes = recurringInvoiceRefresh($recurring_invoice_id, stripslashes($session_name));
    $number = billingNumber('recurring', $row);
    if ($changes) {
        logAudit('Recurring Invoice', 'Edit', escapeSql("$session_name updated the seats on recurring invoice $number: " . implode('; ', $changes)), $client_id, $recurring_invoice_id);
        flashAlert('<strong>' . escapeHtml($number) . '</strong> seats updated - ' . escapeHtml(implode('; ', $changes)));
    } else {
        flashAlert('<strong>' . escapeHtml($number) . '</strong> already bills what the client has.');
    }

    redirect();

}

/*
 * Unbilled Time, one client: bill the ticked tickets at the hours typed, and
 * the ticked expenses and trips at the prices typed, on a new draft or one
 * of the client's drafts.
 */
if (isset($_POST['unbilled_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();

    $hours = [];
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['ticket_ids'] ?? [])))) as $ticket_id) {
        $hours[$ticket_id] = $_POST['hours'][$ticket_id] ?? null;
    }
    $prices = unbilledPostPrices();
    $trip_prices = unbilledPostTripPrices();
    $result = expenseBillWithTime($client_id, $hours, $prices, [
        'invoice_id' => intval($_POST['invoice_id'] ?? 0),
        'date' => (string) ($_POST['date'] ?? ''),
        'category_id' => intval($_POST['category'] ?? 0),
        'scope' => (string) ($_POST['scope'] ?? ''),
        'item_name' => (string) ($_POST['item_name'] ?? ''),
        'price' => (string) ($_POST['price'] ?? ''),
        'tax_id' => intval($_POST['tax_id'] ?? 0),
        'combine' => ($_POST['lines'] ?? '') === 'one',
        'by' => stripslashes($session_name),
    ], $error, $trip_prices);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    flashAlert(unbilledBothText($result, $session_company_currency), $result['error'] !== '' ? 'warning' : 'success');
    redirect("invoice.php?invoice_id={$result['invoice_id']}");

}

/*
 * Unbilled Time, every client: bill each ticked client's unbilled tickets at
 * the hours their time comes to, their expenses to bill at cost with the
 * markup and their trips to bill at the trip charge and rate - on a new
 * draft each, or each one's newest draft when it has one. Expenses and
 * trips only for agents who can bill them.
 */
if (isset($_POST['unbilled_invoice_clients'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $use_draft = ($_POST['target'] ?? '') === 'draft';
    $with_expenses = lookupUserPermission('module_financial') >= 2;
    $by_client = intval($config_module_enable_ticketing) === 1 ? billingUnbilledByClient() : [];
    $by_client = $with_expenses ? tripUnbilledMerge(expenseUnbilledMerge($by_client)) : $by_client;
    $made = [];
    $failed = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['client_ids'] ?? []))) as $bill_client_id) {
        if (!isset($by_client[$bill_client_id]) || !hasClientAccess($bill_client_id)) {
            continue;
        }
        $c = $by_client[$bill_client_id];
        $result = expenseBillWithTime($bill_client_id, array_fill_keys(array_keys($c['tickets']), null), array_fill_keys(array_keys($c['expenses'] ?? []), null), [
            'invoice_id' => $use_draft && $c['drafts'] ? intval(array_key_first($c['drafts'])) : 0,
            'date' => (string) ($_POST['date'] ?? ''),
            'category_id' => intval($_POST['category'] ?? 0),
            'scope' => (string) ($_POST['scope'] ?? ''),
            'item_name' => (string) ($_POST['item_name'] ?? ''),
            'tax_id' => intval($_POST['tax_id'] ?? 0),
            'combine' => ($_POST['lines'] ?? '') === 'one',
            'by' => stripslashes($session_name),
        ], $error, array_fill_keys(array_keys($c['trips'] ?? []), null));
        if ($result) {
            $made[] = $result;
            if ($result['error'] !== '') {
                $failed[] = $c['client_name'] . ': ' . $result['error'];
            }
        } else {
            $failed[] = $c['client_name'] . ': ' . $error;
        }
    }

    if (!$made) {
        flashAlert($failed ? escapeHtml(implode(' ', $failed)) : 'Nothing billed - tick at least one client.', 'error');
        redirect('unbilled_time.php');
    }

    $tickets = array_sum(array_map(function ($r) { return $r['time'] ? count($r['time']['tickets']) : 0; }, $made));
    $hours = array_sum(array_map(function ($r) { return $r['time'] ? $r['time']['hours'] : 0; }, $made));
    $expenses = array_sum(array_map(function ($r) { return $r['expenses'] ? $r['expenses']['expenses'] : 0; }, $made));
    $trips = array_sum(array_map(function ($r) { return !empty($r['trips']) ? $r['trips']['trips'] : 0; }, $made));
    $numbers = array_column($made, 'number');
    $what = [];
    if ($tickets) {
        $what[] = $tickets . ' ticket' . ($tickets === 1 ? '' : 's') . ', ' . billingQuantity($hours) . ' h';
    }
    if ($expenses) {
        $what[] = $expenses . ' expense' . ($expenses === 1 ? '' : 's');
    }
    if ($trips) {
        $what[] = $trips . ' trip' . ($trips === 1 ? '' : 's');
    }
    flashAlert('<strong>' . count($made) . ' invoice' . (count($made) === 1 ? '' : 's') . '</strong> ' . (array_filter(array_column($made, 'new')) ? 'made or added to' : 'added to') . ' - '
        . implode(' and ', $what) . ': ' . escapeHtml(implode(', ', array_slice($numbers, 0, 12)) . (count($numbers) > 12 ? ' and more' : ''))
        . ($failed ? '. Not billed: ' . escapeHtml(implode(' ', $failed)) : ''), $failed ? 'warning' : 'success');
    redirect('invoices.php?status=Draft');

}

/* Unbilled Time: the ticked tickets, expenses and trips aren't billable after all - written off. */
if (isset($_POST['unbilled_write_off'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();

    $done = billingTimeWriteOff($client_id, array_filter(array_map('intval', (array) ($_POST['ticket_ids'] ?? []))), stripslashes($session_name));
    $expense_ids = array_keys(unbilledPostPrices());
    $done_expenses = $expense_ids ? expenseWriteOff($client_id, $expense_ids, stripslashes($session_name)) : [];
    $trip_ids = array_keys(unbilledPostTripPrices());
    $done_trips = $trip_ids ? tripWriteOff($client_id, $trip_ids, stripslashes($session_name)) : [];
    if (!$done && !$done_expenses && !$done_trips) {
        flashAlert('Nothing changed - tick the tickets, expenses or trips that are not billable.', 'error');
        redirect();
    }
    if ($done) {
        logAudit('Ticket', 'Edit', escapeSql("$session_name marked " . (count($done) === 1 ? 'ticket ' : count($done) . ' tickets ') . implode(', ', array_slice($done, 0, 20)) . ' not billable'), $client_id);
    }

    $what = [];
    if ($done) {
        $what[] = count($done) === 1 ? '<strong>' . escapeHtml($done[0]) . '</strong>' : '<strong>' . count($done) . ' tickets</strong>';
    }
    if ($done_expenses) {
        $what[] = count($done_expenses) === 1 ? 'the expense <strong>' . escapeHtml($done_expenses[0]) . '</strong>' : '<strong>' . count($done_expenses) . ' expenses</strong>';
    }
    if ($done_trips) {
        $what[] = count($done_trips) === 1 ? 'the trip <strong>' . escapeHtml($done_trips[0]) . '</strong>' : '<strong>' . count($done_trips) . ' trips</strong>';
    }
    flashAlert(ucfirst(implode(' and ', $what)) . (count($done) + count($done_expenses) + count($done_trips) === 1 ? ' is' : ' are') . ' not billable now');
    redirect();

}
