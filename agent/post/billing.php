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
 * Unbilled Time, one client: bill the ticked tickets at the hours typed, on
 * a new draft or one of the client's drafts.
 */
if (isset($_POST['unbilled_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();

    $hours = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['ticket_ids'] ?? []))) as $ticket_id) {
        $hours[$ticket_id] = $_POST['hours'][$ticket_id] ?? null;
    }
    $result = billingTimeInvoice($client_id, $hours, [
        'invoice_id' => intval($_POST['invoice_id'] ?? 0),
        'date' => (string) ($_POST['date'] ?? ''),
        'category_id' => intval($_POST['category'] ?? 0),
        'scope' => (string) ($_POST['scope'] ?? ''),
        'item_name' => (string) ($_POST['item_name'] ?? ''),
        'price' => (string) ($_POST['price'] ?? ''),
        'tax_id' => intval($_POST['tax_id'] ?? 0),
        'combine' => ($_POST['lines'] ?? '') === 'one',
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    flashAlert(unbilledResultText($result));
    redirect("invoice.php?invoice_id={$result['invoice_id']}");

}

/*
 * Unbilled Time, every client: bill each ticked client's unbilled tickets at
 * the hours their time comes to - on a new draft each, or each one's newest
 * draft when it has one.
 */
if (isset($_POST['unbilled_invoice_clients'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $use_draft = ($_POST['target'] ?? '') === 'draft';
    $by_client = billingUnbilledByClient();
    $made = [];
    $failed = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['client_ids'] ?? []))) as $bill_client_id) {
        if (!isset($by_client[$bill_client_id]) || !hasClientAccess($bill_client_id)) {
            continue;
        }
        $c = $by_client[$bill_client_id];
        $result = billingTimeInvoice($bill_client_id, array_fill_keys(array_keys($c['tickets']), null), [
            'invoice_id' => $use_draft && $c['drafts'] ? intval(array_key_first($c['drafts'])) : 0,
            'date' => (string) ($_POST['date'] ?? ''),
            'category_id' => intval($_POST['category'] ?? 0),
            'scope' => (string) ($_POST['scope'] ?? ''),
            'item_name' => (string) ($_POST['item_name'] ?? ''),
            'tax_id' => intval($_POST['tax_id'] ?? 0),
            'combine' => ($_POST['lines'] ?? '') === 'one',
            'by' => stripslashes($session_name),
        ], $error);
        if ($result) {
            $made[] = $result;
        } else {
            $failed[] = $c['client_name'] . ': ' . $error;
        }
    }

    if (!$made) {
        flashAlert($failed ? escapeHtml(implode(' ', $failed)) : 'Nothing billed - tick at least one client.', 'error');
        redirect('unbilled_time.php');
    }

    $tickets = array_sum(array_map(function ($r) { return count($r['tickets']); }, $made));
    $hours = array_sum(array_column($made, 'hours'));
    $numbers = array_column($made, 'number');
    flashAlert('<strong>' . count($made) . ' invoice' . (count($made) === 1 ? '' : 's') . '</strong> ' . (array_filter(array_column($made, 'new')) ? 'made or added to' : 'added to') . ' - '
        . $tickets . ' ticket' . ($tickets === 1 ? '' : 's') . ', ' . billingQuantity($hours) . ' h: ' . escapeHtml(implode(', ', array_slice($numbers, 0, 12)) . (count($numbers) > 12 ? ' and more' : ''))
        . ($failed ? '. Not billed: ' . escapeHtml(implode(' ', $failed)) : ''), $failed ? 'warning' : 'success');
    redirect('invoices.php?status=Draft');

}

/* Unbilled Time: the ticked tickets aren't billable after all - written off. */
if (isset($_POST['unbilled_write_off'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();

    $done = billingTimeWriteOff($client_id, array_map('intval', (array) ($_POST['ticket_ids'] ?? [])), stripslashes($session_name));
    if (!$done) {
        flashAlert('Nothing changed - tick the tickets that are not billable.', 'error');
        redirect();
    }
    logAudit('Ticket', 'Edit', escapeSql("$session_name marked " . (count($done) === 1 ? 'ticket ' : count($done) . ' tickets ') . implode(', ', array_slice($done, 0, 20)) . ' not billable'), $client_id);

    flashAlert((count($done) === 1 ? '<strong>' . escapeHtml($done[0]) . '</strong> is' : '<strong>' . count($done) . ' tickets</strong> are') . ' not billable now');
    redirect();

}
