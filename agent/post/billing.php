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
