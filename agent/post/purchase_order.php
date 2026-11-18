<?php

/*
 * Purchasing - To Order, purchase orders and receiving (functions/purchase_orders.php).
 * Each checks the agent's Purchasing level: Edit to add items, make, change
 * and send purchase orders, add tracking and receive; Full to approve, send
 * back, cancel past a draft and undo a receipt. Recording the vendor bill
 * needs Financial edit too.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The PO page to go back to. */
function poPageUrl($po_id) {
    return 'purchase_order.php?po_id=' . intval($po_id);
}

if (isset($_POST['order_item_save'])) {

    validateCSRFToken();
    poRequireLevel(2);

    $order_item_id = intval($_POST['order_item_id'] ?? 0);
    $saved = orderItemSave($_POST, $order_item_id, $error);
    if (!$saved) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $name = escapeSql(trim((string) ($_POST['name'] ?? '')));
    logAudit('Purchase Order', $order_item_id ? 'Edit' : 'Create', "$session_name " . ($order_item_id ? 'changed' : 'added') . " $name on To Order", intval($_POST['client'] ?? 0));
    flashAlert('<strong>' . escapeHtml(trim((string) ($_POST['name'] ?? ''))) . '</strong> ' . ($order_item_id ? 'saved' : 'is on To Order'));
    redirect();

}

if (isset($_POST['order_items_remove']) || isset($_GET['order_item_remove'])) {

    validateCSRFToken();
    poRequireLevel(2);

    $ids = isset($_GET['order_item_remove']) ? [intval($_GET['order_item_remove'])] : (array) ($_POST['order_item_ids'] ?? []);
    $removed = orderItemsRemove($ids);
    if ($removed) {
        logAudit('Purchase Order', 'Delete', "$session_name took $removed item" . ($removed === 1 ? '' : 's') . " off To Order");
    }
    flashAlert($removed ? "$removed item" . ($removed === 1 ? '' : 's') . ' taken off To Order' : 'Nothing was taken off - they may have been ordered already.', $removed ? 'error' : 'warning');
    redirect();

}

if (isset($_POST['order_items_low_stock'])) {

    validateCSRFToken();
    poRequireLevel(2);

    $added = orderItemsAddLowStock();
    if ($added) {
        logAudit('Purchase Order', 'Create', "$session_name put $added low-stock product" . ($added === 1 ? '' : 's') . " on To Order");
    }
    flashAlert($added ? "$added low-stock product" . ($added === 1 ? ' is' : 's are') . ' on To Order' : 'Nothing is low on stock and not on order.');
    redirect();

}

if (isset($_POST['po_create'])) {

    validateCSRFToken();
    poRequireLevel(2);

    $po_id = poCreate(intval($_POST['vendor'] ?? 0), (array) ($_POST['order_item_ids'] ?? []), $error);
    if (!$po_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $po = poRow($po_id);
    flashAlert('Draft <strong>' . escapeHtml(poNumber($po)) . '</strong> made');
    redirect(poPageUrl($po_id));

}

if (isset($_POST['po_add_items'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    $added = poAddItems($po, (array) ($_POST['order_item_ids'] ?? []), $error);
    if (!$added) {
        flashAlert(escapeHtml($error ?: 'Tick what goes on it.'), 'error');
        redirect();
    }
    flashAlert("$added item" . ($added === 1 ? '' : 's') . ' put on <strong>' . escapeHtml(poNumber($po)) . '</strong>');
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_line_save'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    if (!poLineSave($po, $_POST, intval($_POST['po_item_id'] ?? 0), $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect(poPageUrl($po['po_id']));
    }
    flashAlert('<strong>' . escapeHtml(trim((string) ($_POST['name'] ?? ''))) . '</strong> ' . (intval($_POST['po_item_id'] ?? 0) ? 'saved' : 'added'));
    redirect(poPageUrl($po['po_id']));

}

if (isset($_GET['po_line_remove'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_GET['po_id'] ?? 0), 2);
    if (!poLineRemove($po, intval($_GET['po_line_remove']), $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('Line taken off', 'error');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_details_save'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    $in = array_intersect_key($_POST, array_flip(['vendor', 'ship_to', 'ship_to_address', 'shipping', 'note']));
    if (!poDetailsSave($po, $in, $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('<strong>' . escapeHtml(poNumber($po)) . '</strong> saved');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_send_approval'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    if (!poSendForApproval($po, $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('<strong>' . escapeHtml(poNumber($po)) . '</strong> sent for approval - the approvers are told');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_decide'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 3);
    $approve = intval($_POST['approve'] ?? 0) === 1;
    if (!$approve && trim((string) ($_POST['note'] ?? '')) === '') {
        flashAlert('Say what to change before it comes back.', 'error');
        redirect(poPageUrl($po['po_id']));
    }
    if (!poDecide($po, $approve, $_POST['note'] ?? '', $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('<strong>' . escapeHtml(poNumber($po)) . '</strong> ' . ($approve ? 'approved' : 'sent back to draft'));
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_GET['po_back_to_draft'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_GET['po_back_to_draft']), 2);
    if (!poBackToDraft($po, $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('<strong>' . escapeHtml(poNumber($po)) . '</strong> is a draft again');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_mark_ordered'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    if (!poMarkOrdered($po, $_POST['order_date'] ?? '', $_POST['vendor_reference'] ?? '', $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('<strong>' . escapeHtml(poNumber($po)) . '</strong> marked ordered - its lines are on Receiving');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_email'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    if (!poCanOrder($po) && !in_array($po['po_status'], ['ordered', 'partial', 'received'], true)) {
        flashAlert(escapeHtml(poNumber($po) . ' needs approving before it goes to the vendor.'), 'error');
        redirect(poPageUrl($po['po_id']));
    }
    // Marked ordered first, so the PDF that goes is the order itself, not a draft
    $marked = '';
    if (!empty($_POST['mark_ordered']) && poCanOrder($po)) {
        if (!poMarkOrdered($po, date('Y-m-d'), '', $error)) {
            flashAlert(escapeHtml($error), 'error');
            redirect(poPageUrl($po['po_id']));
        }
        $po = poRow($po['po_id']);
        $marked = ' and marked ordered';
    }
    if (!poEmail($po, $_POST['to'] ?? '', $_POST['message'] ?? '', $error)) {
        flashAlert(escapeHtml($error) . ($marked ? ' It was marked ordered.' : ''), 'error');
        redirect(poPageUrl($po['po_id']));
    }
    flashAlert('<strong>' . escapeHtml(poNumber($po)) . '</strong> queued to ' . escapeHtml(trim((string) $_POST['to'])) . $marked . ' - watch it go in Maintenance &gt; Mail Queue');
    redirect(poPageUrl($po['po_id']));

}

if (isset($_GET['po_cancel'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_GET['po_cancel']), 2);
    if ($po['po_status'] !== 'draft' && poLevel() < 3) {
        enforceUserPermission('module_procurement', 3);
    }
    if (!poCancel($po, $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('<strong>' . escapeHtml(poNumber($po)) . '</strong> cancelled - its items are back on To Order', 'error');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_tracking_add'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    if (!poTrackingAdd($po, (string) ($_POST['carrier'] ?? ''), $_POST['tracking_number'] ?? '', $_POST['eta'] ?? '', $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('Tracking added');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_GET['po_tracking_remove'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_GET['po_id'] ?? 0), 2);
    poTrackingRemove($po, intval($_GET['po_tracking_remove']));
    flashAlert('Tracking taken off', 'error');
    redirect(poPageUrl($po['po_id']));

}

if (isset($_POST['po_receive'])) {

    validateCSRFToken();
    poRequireLevel(2);

    $done = poReceive((array) ($_POST['received'] ?? []));
    if (!$done['lines']) {
        flashAlert('Type how many came in on at least one line.', 'error');
        redirect();
    }
    $said = $done['lines'] . ' line' . ($done['lines'] === 1 ? '' : 's') . ' received on ' . implode(', ', $done['pos'])
        . ($done['stock'] > 0 ? ' - ' . billingQuantity($done['stock']) . ' on the shelf' : '')
        . ($done['assets'] ? ' - ' . $done['assets'] . ' asset' . ($done['assets'] === 1 ? '' : 's') . ' made' : '');
    flashAlert(escapeHtml($said) . ($done['skipped'] ? '<br>' . escapeHtml(implode('. ', $done['skipped'])) . '.' : ''), $done['skipped'] ? 'warning' : 'success');
    // Back to the PO when one was being received, else Receiving
    $po_id = intval($_POST['po_id'] ?? 0);
    redirect($po_id ? poPageUrl($po_id) : 'receiving.php');

}

if (isset($_GET['po_receipt_undo'])) {

    validateCSRFToken();
    poRequireLevel(3);

    $receipt = mysqli_fetch_row(mysqli_query($mysqli, "SELECT po_receipt_po_id FROM po_receipts WHERE po_receipt_id = " . intval($_GET['po_receipt_undo'])));
    if (!poUndoReceipt(intval($_GET['po_receipt_undo']), $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('Receipt undone - it is waiting to come in again');
    }
    redirect($receipt ? poPageUrl($receipt[0]) : 'purchase_orders.php');

}

if (isset($_POST['po_bill'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_POST['po_id'] ?? 0), 2);
    enforceUserPermission('module_financial', 2);
    if (!poRecordBill($po, $_POST, $error)) {
        flashAlert(escapeHtml($error), 'error');
    } else {
        flashAlert('Vendor bill recorded as an expense');
    }
    redirect(poPageUrl($po['po_id']));

}

if (isset($_GET['po_pdf'])) {

    validateCSRFToken();

    $po = poForRequest(intval($_GET['po_pdf']), 1);
    billingPdfSend(poPdf($po));

}
