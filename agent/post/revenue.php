<?php

/*
 * ITFlow - other income: money in that isn't for an invoice (revenues).
 * New and Copy post add_revenue, Edit posts edit_revenue - both through
 * incomeRevenueSave(), which checks every field. Write access to Financial
 * adds and changes it; full access deletes it.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The form's fields as incomeRevenueSave() takes them. */
function revenuePostInput() {
    return [
        'date' => (string) ($_POST['date'] ?? ''),
        'amount' => (string) ($_POST['amount'] ?? ''),
        'account' => intval($_POST['account'] ?? 0),
        'category' => intval($_POST['category'] ?? 0),
        'client_id' => intval($_POST['client_id'] ?? 0),
        'payment_method' => (string) ($_POST['payment_method'] ?? ''),
        'reference' => (string) ($_POST['reference'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
    ];
}

if (isset($_POST['add_revenue'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $revenue_id = incomeRevenueSave(revenuePostInput(), 0, $error);
    if (!$revenue_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $row = incomeRevenueRow($revenue_id);
    $label = incomeRevenueLabel($row);

    logAudit("Revenue", "Create", "$session_name added other income " . escapeSql($label), intval($row['revenue_client_id']), $revenue_id);

    flashAlert("Income <strong>" . escapeHtml($label) . "</strong> added");

    redirect();

}

if (isset($_POST['edit_revenue'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $revenue_id = intval($_POST['revenue_id'] ?? 0);
    $before = incomeRevenueRow($revenue_id);
    if (!incomeRevenueSave(revenuePostInput(), $revenue_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $row = incomeRevenueRow($revenue_id);
    $label = incomeRevenueLabel($row);

    logAudit("Revenue", "Edit", "$session_name edited other income " . escapeSql($label), intval($row['revenue_client_id']), $revenue_id);
    if ($before && intval($before['revenue_client_id']) && intval($before['revenue_client_id']) !== intval($row['revenue_client_id'])) {
        logAudit("Revenue", "Edit", "$session_name moved other income " . escapeSql($label) . " to " . escapeSql($row['client_name'] ?: 'no client'), intval($before['revenue_client_id']), $revenue_id);
    }

    flashAlert("Income <strong>" . escapeHtml($label) . "</strong> saved");

    redirect();

}

if (isset($_GET['delete_revenue'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = incomeRevenueDelete(intval($_GET['delete_revenue']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $label = incomeRevenueLabel($row);

    logAudit("Revenue", "Delete", "$session_name deleted other income " . escapeSql($label), intval($row['revenue_client_id']));

    flashAlert("Income <strong>" . escapeHtml($label) . "</strong> deleted", 'error');

    redirect();

}
