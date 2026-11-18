<?php

/*
 * ITFlow - what is done to an invoice: marked sent, cancelled or deleted, from
 * its own page (agent/post/invoice.php) or to many at once from the invoices
 * list's Bulk Action menu - Mark Sent, Send by Email, Mark Paid, Cancel and
 * Delete. Each bulk action does to every invoice ticked what its button on the
 * invoice does, and only to those that button would be offered for: the rest
 * are left alone, and the window and the message after say which and why.
 *
 * The ticked invoices are kept in the session under a short key
 * (invoiceBulkRemember(), agent/ajax.php?invoice_bulk_set) - 500 ids in a
 * window's address are more than a web server takes, and a form of that many
 * fields more than PHP reads. The window is agent/modals/invoice/invoice_bulk.php,
 * saved by invoice_bulk in agent/post/invoice.php.
 */

/** The bulk actions - key => [label, icon, what the window says, button class, done]. */
function invoiceBulkActions() {
    return [
        'sent' => ['Mark Sent', 'fa-check', 'Drafts are marked sent - delivered some other way, so nothing is emailed.', 'btn-primary', 'marked sent'],
        'email' => ['Send by Email', 'fa-paper-plane', 'Each goes to its client\'s primary and billing contacts, as Quick Send does. A draft becomes Sent.', 'btn-primary', 'emailed'],
        'pay' => ['Mark Paid', 'fa-credit-card', 'Each gets one payment of what is still owed on it, so it is paid in full.', 'btn-success', 'marked paid'],
        'cancel' => ['Cancel', 'fa-times', 'Cancelled invoices stay on record, with nothing more owed on them. What they took off the shelf goes back.', 'btn-danger', 'cancelled'],
        'delete' => ['Delete', 'fa-trash', 'Deleted for good, with their lines, payments and history. Their tickets become unbilled again, and their expenses and trips are to bill again.', 'btn-danger', 'deleted'],
    ];
}

/** Most invoices one bulk action takes. */
const INVOICE_BULK_MAX = 1000;

/*
 * ---------- One invoice - its page and the bulk actions ----------
 */

/** Mark an invoice sent - delivered some other way, as $method (one of getSentMethods()), with a note for its history. $row: invoice_id, invoice_prefix, invoice_number, invoice_client_id. */
function invoiceMarkSent(array $row, $method, $note = '', $bulk = false) {
    global $mysqli, $session_name;
    $invoice_id = intval($row['invoice_id']);
    $number = $row['invoice_prefix'] . $row['invoice_number'];
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    billingLog('invoice', $invoice_id, 'Sent', 'Invoice marked sent by ' . stripslashes($session_name) . " - $method" . ($bulk ? ' (bulk)' : '') . ($note !== '' ? "\nNote: $note" : ''));
    logAudit("Invoice", "Edit", "$session_name marked invoice " . escapeSql($number) . " sent - " . escapeSql($method) . ($bulk ? ' (bulk)' : ''), intval($row['invoice_client_id']), $invoice_id);
}

/** Cancel an invoice - it stays on record with nothing more owed; what it took off the shelf goes back. */
function invoiceCancel(array $row, $bulk = false) {
    global $mysqli, $session_name;
    $invoice_id = intval($row['invoice_id']);
    $number = $row['invoice_prefix'] . $row['invoice_number'];
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Cancelled' WHERE invoice_id = $invoice_id");
    billingLog('invoice', $invoice_id, 'Cancelled', 'Invoice cancelled by ' . stripslashes($session_name) . ($bulk ? ' (bulk)' : ''));
    productStockReturnInvoice($invoice_id, "$number cancelled");
    logAudit("Invoice", "Edit", "$session_name cancelled invoice " . escapeSql($number) . ($bulk ? ' (bulk)' : ''), intval($row['invoice_client_id']), $invoice_id);
}

/**
 * Delete an invoice for good, with its lines, history and payments - check
 * reconcileInvoiceLock() first. What it took off the shelf goes back, its
 * tickets become unbilled again, and its expenses and trips are to bill again.
 */
function invoiceDelete(array $row, $bulk = false) {
    global $mysqli, $session_name;
    $invoice_id = intval($row['invoice_id']);
    $number = $row['invoice_prefix'] . $row['invoice_number'];
    // What it took off the shelf goes back before its lines go
    productStockReturnInvoice($invoice_id, "$number deleted");
    mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_id = $invoice_id");
    mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
    mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
    mysqli_query($mysqli, "DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = 0 WHERE ticket_invoice_id = $invoice_id");
    // Assets made from its hardware stay on the client's assets, unlinked
    mysqli_query($mysqli, "UPDATE assets SET asset_invoice_id = 0, asset_invoice_item_id = 0 WHERE asset_invoice_id = $invoice_id");
    // Expenses and trips billed on it are to bill again
    mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = NULL, expense_invoice_item_id = NULL WHERE expense_invoice_id = $invoice_id");
    mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = 0, trip_invoice_item_id = 0 WHERE trip_invoice_id = $invoice_id");
    logAudit("Invoice", "Delete", "$session_name deleted invoice " . escapeSql($number) . ($bulk ? ' (bulk)' : ''), intval($row['invoice_client_id']));
}

/*
 * ---------- The invoices list's Bulk Action menu ----------
 */

/** May this agent use the bulk action? As the invoice's own buttons - Send by Email also needs mail set up. */
function invoiceBulkCan($action) {
    global $config_smtp_provider;
    $sales = intval(lookupUserPermission('module_sales'));
    switch ($action) {
        case 'sent':
        case 'cancel':
            return $sales >= 2;
        case 'email':
            return $sales >= 2 && !empty($config_smtp_provider);
        case 'pay':
            return billingMoneyCan('take');
        case 'delete':
            return $sales >= 3;
    }
    return false;
}

/** Keep the ticked invoices for the window - a short key to them in the session. The newest 5 sets are kept. */
function invoiceBulkRemember(array $invoice_ids) {
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $invoice_ids)))), 0, INVOICE_BULK_MAX);
    $key = bin2hex(random_bytes(8));
    $sets = (array) ($_SESSION['invoice_bulk'] ?? []);
    $sets[$key] = $ids;
    $_SESSION['invoice_bulk'] = array_slice($sets, -5, null, true);
    return $key;
}

/** The invoices kept under a key - [] when it isn't this session's. */
function invoiceBulkRecall($key) {
    $key = (string) $key;
    return preg_match('/^[a-f0-9]{16}$/', $key) ? array_map('intval', (array) ($_SESSION['invoice_bulk'][$key] ?? [])) : [];
}

/** The invoices, with what has been paid and what is owed on each in cents, by number. */
function invoiceBulkRows(array $invoice_ids) {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', $invoice_ids))));
    if (!$ids) {
        return [];
    }
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_status, invoice_amount, invoice_currency_code, invoice_client_id, invoice_scope,
            client_name, COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS amount_paid
        FROM invoices LEFT JOIN clients ON client_id = invoice_client_id WHERE invoice_id IN (" . implode(',', $ids) . ") ORDER BY invoice_number ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['balance_cents'] = intval(round(floatval($row['invoice_amount']) * 100)) - intval(round(floatval($row['amount_paid']) * 100));
        $rows[] = $row;
    }
    return $rows;
}

/** Why an invoice is left out of the action - '' when it's in. */
function invoiceBulkWhyNot($action, array $row) {
    $status = (string) $row['invoice_status'];
    if (!hasClientAccess($row['invoice_client_id'])) {
        return 'not one of your clients';
    }
    switch ($action) {
        case 'sent':
            return $status === 'Draft' ? '' : "already $status";
        case 'email':
            if (!in_array($status, array_merge(['Draft'], invoiceOpenStatuses()), true)) {
                return $status;
            }
            return billingEmailRecipients(intval($row['invoice_client_id'])) ? '' : 'no primary or billing contact with an email address';
        case 'pay':
            if ($status === 'Draft') {
                return 'a draft - mark it sent first';
            }
            if (!in_array($status, invoiceOpenStatuses(), true)) {
                return $status;
            }
            return $row['balance_cents'] > 0 ? '' : 'nothing owed';
        case 'cancel':
            return in_array($status, ['Cancelled', 'Paid', 'Non-Billable'], true) ? $status : '';
        case 'delete':
            return reconcileInvoiceLock(intval($row['invoice_id'])) ? 'a payment on it is on a reconciled statement - cancel it instead' : '';
    }
    return 'not something that can be done';
}

/** The invoices split into those the action is done to and those left alone - ['do' => rows, 'skip' => [[row, why], ...]]. */
function invoiceBulkPlan($action, array $invoice_ids) {
    $plan = ['do' => [], 'skip' => []];
    foreach (invoiceBulkRows($invoice_ids) as $row) {
        $why = invoiceBulkWhyNot($action, $row);
        if ($why === '') {
            $plan['do'][] = $row;
        } else {
            $plan['skip'][] = [$row, $why];
        }
    }
    return $plan;
}

/** Email one invoice as Quick Send does - to its client's primary and billing contacts, the PDF as the reminder settings say. */
function invoiceBulkEmail(array $row) {
    global $mysqli, $session_name;
    $invoice_id = intval($row['invoice_id']);
    $client_id = intval($row['invoice_client_id']);
    $invoice = billingRow('invoice', $invoice_id);
    $recipients = billingEmailRecipients($client_id);
    [$subject, $email] = billingInvoiceEmail($invoice, ['kind' => 'invoice', 'note' => '']);
    $attach = [];
    if (billingReminderRules()['attach_pdf'] && ($file = billingEmailPdf(billingPdf('invoice', $invoice)))) {
        $attach[] = $file;
    }
    $sent = billingEmailQueue($recipients, $subject, $email, ['attach' => $attach]);
    $texted = smsInvoiceNotify($invoice, 'invoice', $recipients);
    billingLog('invoice', $invoice_id, $invoice['invoice_status'] === 'Draft' ? 'Sent' : $invoice['invoice_status'],
        'Invoice emailed by ' . stripslashes($session_name) . ' to ' . implode(', ', $sent) . ($attach ? ' with the PDF' : '') . ($texted ? ' - and texted to ' . implode(', ', $texted) : '') . ' (bulk)');
    foreach ($recipients as $recipient) {
        logAudit("Invoice", "Email", "$session_name emailed " . escapeSql($recipient['email']) . " invoice " . escapeSql(billingNumber('invoice', $invoice)) . ' (bulk)', $client_id, $invoice_id);
    }
    if ($invoice['invoice_status'] === 'Draft') {
        mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }
}

/** Pay one invoice in full, as Add Payment does - $pay: date, account, method, reference. What was paid. */
function invoiceBulkPay(array $row, array $pay) {
    global $mysqli, $session_name;
    $invoice_id = intval($row['invoice_id']);
    $amount = $row['balance_cents'] / 100;
    $number = $row['invoice_prefix'] . $row['invoice_number'];
    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '" . escapeSql($pay['date']) . "', payment_amount = $amount, payment_currency_code = '" . escapeSql($row['invoice_currency_code']) . "',
        payment_account_id = " . intval($pay['account']) . ", payment_method = '" . escapeSql($pay['method']) . "', payment_reference = '" . escapeSql($pay['reference']) . "', payment_invoice_id = $invoice_id");
    $status = updateInvoiceStatusFromPayments($invoice_id);
    billingLog('invoice', $invoice_id, (string) $status, 'Payment added (bulk)');
    logAudit("Invoice", "Payment", "Payment amount of " . escapeSql(billingMoney($amount, $row['invoice_currency_code'])) . " added to invoice " . escapeSql($number) . ' (bulk)', intval($row['invoice_client_id']), $invoice_id);
    triggerCustomAction('invoice_pay', $invoice_id);
    return $amount;
}

/**
 * Do the action. $opts for sent: method (one of getSentMethods()), note; for
 * pay: date, account, method, reference and receipt (one emailed to each
 * client, listing what was paid). Each invoice is read again just before it
 * is changed - another agent may have got there first. ['done' => [number,
 * ...], 'skipped' => [number => why], 'paid' => [currency => cents],
 * 'receipts' => how many].
 */
function invoiceBulkRun($action, array $invoice_ids, array $opts = []) {
    global $mysqli, $session_name, $config_smtp_provider;
    $result = ['done' => [], 'skipped' => [], 'paid' => [], 'receipts' => 0];
    $paid_by_client = [];
    foreach (invoiceBulkRows($invoice_ids) as $row) {
        $invoice_id = intval($row['invoice_id']);
        $number = $row['invoice_prefix'] . $row['invoice_number'];
        // Two people paying the same invoice at once - one waits for the other, then finds it paid
        $lock = "itflow_invoice_pay_$invoice_id";
        if ($action === 'pay' && intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT GET_LOCK('$lock', 5)"))[0] ?? 0) !== 1) {
            $result['skipped'][$number] = 'someone else is paying it right now';
            continue;
        }
        $fresh = invoiceBulkRows([$invoice_id])[0] ?? null;
        $why = $fresh ? invoiceBulkWhyNot($action, $fresh) : 'deleted since';
        if ($why === '') {
            switch ($action) {
                case 'sent':
                    invoiceMarkSent($fresh, $opts['method'], $opts['note'] ?? '', true);
                    break;
                case 'email':
                    invoiceBulkEmail($fresh);
                    break;
                case 'pay':
                    $amount = invoiceBulkPay($fresh, $opts);
                    $result['paid'][$fresh['invoice_currency_code']] = ($result['paid'][$fresh['invoice_currency_code']] ?? 0) + $fresh['balance_cents'];
                    $paid_by_client[intval($fresh['invoice_client_id'])][] = ['invoice_id' => $invoice_id, 'amount' => $amount, 'currency' => $fresh['invoice_currency_code']];
                    break;
                case 'cancel':
                    invoiceCancel($fresh, true);
                    break;
                case 'delete':
                    invoiceDelete($fresh, true);
                    break;
            }
            $result['done'][] = $number;
        } else {
            $result['skipped'][$number] = $why;
        }
        if ($action === 'pay') {
            mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
        }
    }

    // One receipt to each client, listing each invoice paid - as Batch Payment sends it
    if ($action === 'pay' && !empty($opts['receipt']) && !empty($config_smtp_provider)) {
        foreach ($paid_by_client as $client_id => $entries) {
            foreach (array_unique(array_column($entries, 'currency')) as $currency) {
                $applied = [];
                foreach ($entries as $entry) {
                    if ($entry['currency'] === $currency) {
                        $applied[] = ['invoice' => billingRow('invoice', $entry['invoice_id'], false), 'amount' => $entry['amount']];
                    }
                }
                [$subject, $email] = billingPaymentEmail($applied, ['date' => $opts['date'], 'method' => $opts['method'], 'reference' => $opts['reference'], 'currency' => $currency]);
                $receipt_to = billingEmailQueue(billingEmailRecipients($client_id), $subject, $email);
                if ($receipt_to) {
                    foreach ($applied as $entry) {
                        billingLog('invoice', $entry['invoice']['invoice_id'], 'Sent', 'Payment receipt emailed to ' . implode(', ', $receipt_to));
                    }
                    $result['receipts']++;
                }
            }
        }
    }
    if ($result['done']) {
        logAudit("Invoice", "Bulk Edit", "$session_name " . escapeSql(invoiceBulkActions()[$action][4]) . ' ' . count($result['done']) . ' invoice' . (count($result['done']) === 1 ? '' : 's') . ': ' . escapeSql(implode(', ', $result['done'])));
    }
    return $result;
}

/** What a run did, and what it left alone and why - HTML, for the message after. */
function invoiceBulkMessage($action, array $result) {
    $done = count($result['done']);
    $text = '<strong>' . $done . '</strong> invoice' . ($done === 1 ? '' : 's') . ' ' . escapeHtml(invoiceBulkActions()[$action][4]);
    if ($action === 'pay' && $result['paid']) {
        $text .= ' (' . escapeHtml(implode(', ', array_map(function ($currency, $cents) {
            return billingMoney($cents / 100, $currency);
        }, array_keys($result['paid']), $result['paid']))) . ')';
        if ($result['receipts']) {
            $text .= ' - ' . $result['receipts'] . ' receipt' . ($result['receipts'] === 1 ? '' : 's') . ' on the way';
        }
    }
    if ($result['skipped']) {
        $list = [];
        foreach ($result['skipped'] as $number => $why) {
            $list[] = "$number ($why)";
        }
        $text .= '. Left alone: ' . escapeHtml(implode(', ', array_slice($list, 0, 15)) . (count($list) > 15 ? ' and ' . (count($list) - 15) . ' more' : ''));
    }
    return $text;
}
