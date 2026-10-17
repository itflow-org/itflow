<?php

/*
 * ITFlow - importing the deposits on a bank statement as income
 * (agent/income_import.php; functions/income_import.php does the work, and
 * functions/expense_import.php reads the file).
 *
 * Upload reads the CSV into the session with a first guess at its columns -
 * or the columns the account's last statement used, from either side, going
 * straight to the review. Columns sets them; Import brings in the ticked
 * deposits, sent from the review as one JSON field (picks). A statement read
 * on one side can go on to the other without uploading it again.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['income_import_upload'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_POST['account'] ?? 0);
    $accounts = paymentAccounts();
    if (!isset($accounts[$account_id])) {
        flashAlert('Pick the account the deposits went into', 'error');
        redirect('income_import.php');
    }
    $method = trim((string) ($_POST['payment_method'] ?? ''));
    if ($method !== '' && !in_array($method, paymentMethods(), true)) {
        $method = '';
    }
    $file = $_FILES['file'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        flashAlert(($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'That file is too big - export a month or two at a time' : 'Choose the statement file', 'error');
        redirect('income_import.php');
    }
    if (!in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), ['csv', 'txt', 'tsv'], true)) {
        flashAlert('Export the statement as a CSV file - that is a ' . escapeHtml(strtoupper(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) ?: 'different') . ' file', 'error');
        redirect('income_import.php');
    }
    $read = expenseImportRead($file['tmp_name'], $error);
    if (!$read) {
        flashAlert(escapeHtml($error), 'error');
        redirect('income_import.php');
    }

    $map = expenseImportSavedMap($account_id, $read['header']);
    $_SESSION['income_import'] = [
        'account_id' => $account_id,
        'method' => $method,
        'file_name' => mb_substr(basename((string) $file['name']), 0, 120),
        'header' => $read['header'],
        'rows' => $read['rows'],
        'map' => $map,
        'guess' => $map ?: expenseImportGuess($read['header'], $read['rows']),
        'remembered' => $map !== null,
    ];

    redirect('income_import.php?step=' . ($map ? 'review' : 'columns'));

}

if (isset($_POST['income_import_columns'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $import = $_SESSION['income_import'] ?? null;
    if (!$import) {
        flashAlert('Upload the statement again - the one you started is gone', 'error');
        redirect('income_import.php');
    }
    $map = expenseImportMapClean($_POST, count($import['header']));
    if (!$map) {
        flashAlert('Pick the columns with the date, the description and the amount', 'error');
        redirect('income_import.php?step=columns');
    }
    if (!array_filter(expenseImportLines($import['rows'], $map), function ($line) { return $line['problem'] === ''; })) {
        $_SESSION['income_import']['guess'] = $map;
        flashAlert("None of the lines read with those columns - check the date format and the amount", 'error');
        redirect('income_import.php?step=columns');
    }

    $_SESSION['income_import']['map'] = $map;
    $_SESSION['income_import']['guess'] = $map;
    $_SESSION['income_import']['remembered'] = false;
    expenseImportSaveMap($import['account_id'], $import['header'], $map);

    redirect('income_import.php?step=review');

}

if (isset($_POST['income_import_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $import = $_SESSION['income_import'] ?? null;
    if (!$import || !$import['map']) {
        flashAlert('Upload the statement again - the one you started is gone', 'error');
        redirect('income_import.php');
    }
    $picks = [];
    foreach ((array) json_decode((string) ($_POST['picks'] ?? ''), true) as $pick) {
        if (is_array($pick) && isset($pick['n'])) {
            $picks[intval($pick['n'])] = [
                'take' => !empty($pick['take']),
                'action' => (string) ($pick['action'] ?? ''),
                'client' => intval($pick['client'] ?? 0),
                'invoices' => array_map('intval', (array) ($pick['invoices'] ?? [])),
                'category' => intval($pick['category'] ?? 0),
                'account' => intval($pick['account'] ?? 0),
            ];
        }
    }
    $result = incomeImportSave($import, $picks, ['receipts' => !empty($_POST['receipts'])], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect('income_import.php?step=review');
    }
    unset($_SESSION['income_import']);

    $account_name = (string) getFieldById('accounts', intval($import['account_id']), 'account_name');
    $currency = (string) getFieldById('accounts', intval($import['account_id']), 'account_currency_code') ?: $session_company_currency;
    $count = $result['payments'] + $result['income'] + $result['transfers'];
    logAudit("Income", "Import", "$session_name imported deposits from " . escapeSql($import['file_name'] . " for $account_name") . ": " . $result['payments'] . " payment(s), "
        . $result['income'] . " other income, " . $result['transfers'] . " transfer(s)");

    $parts = [];
    if ($result['payments']) {
        $parts[] = '<strong>' . escapeHtml(billingMoney($result['paid'], $currency)) . '</strong> paid on ' . ($result['invoices_paid'] + $result['invoices_part']) . ' invoice' . (($result['invoices_paid'] + $result['invoices_part']) === 1 ? '' : 's')
            . ($result['invoices_part'] ? ' (' . $result['invoices_part'] . ' part paid)' : '');
    }
    if ($result['income']) {
        $parts[] = '<strong>' . escapeHtml(billingMoney($result['income_total'], $currency)) . '</strong> other income';
    }
    if ($result['transfers']) {
        $parts[] = $result['transfers'] . ' transfer' . ($result['transfers'] === 1 ? '' : 's') . ' in';
    }
    $notes = [];
    if ($result['receipts']) {
        $notes[] = $result['receipts'] . ' receipt' . ($result['receipts'] === 1 ? '' : 's') . ' emailed';
    }
    if ($result['linked']) {
        $notes[] = $result['linked'] . ' matched to what was entered already';
    }
    if ($result['skipped']) {
        $notes[] = $result['skipped'] . ' imported before';
    }
    flashAlert(($count ? implode(', ', $parts) . ' brought in' : 'Nothing brought in') . ($notes ? ' &middot; ' . escapeHtml(implode(', ', $notes)) : ''));

    if (!$count) {
        redirect('income.php');
    }
    redirect('income.php?' . http_build_query(['account' => intval($import['account_id']), 'canned_date' => 'custom', 'dtf' => $result['from'], 'dtt' => $result['to']]));

}

/* Start over - the statement in progress is dropped. */
if (isset($_GET['income_import_discard'])) {

    validateCSRFToken();

    unset($_SESSION['income_import']);

    redirect('income_import.php');

}

/* The statement read for expenses, on to its deposits - and back - without uploading it again. */
if (isset($_GET['income_import_from_statement']) || isset($_GET['expense_import_from_statement'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $to_income = isset($_GET['income_import_from_statement']);
    $from = $_SESSION[$to_income ? 'expense_import' : 'income_import'] ?? null;
    if (!$from) {
        flashAlert('Upload the statement again - the one you started is gone', 'error');
        redirect($to_income ? 'income_import.php' : 'expense_import.php');
    }
    $_SESSION[$to_income ? 'income_import' : 'expense_import'] = [
        'account_id' => $from['account_id'],
        'method' => $to_income ? (string) $config_default_payment_method : (string) $config_default_expense_payment_method,
        'file_name' => $from['file_name'],
        'header' => $from['header'],
        'rows' => $from['rows'],
        'map' => $from['map'],
        'guess' => $from['guess'],
        'remembered' => $from['remembered'],
    ];

    redirect(($to_income ? 'income_import.php' : 'expense_import.php') . '?step=' . ($from['map'] ? 'review' : 'columns'));

}

/* Forget what a payer was matched to. */
if (isset($_GET['income_import_rule_delete'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $rule_id = intval($_GET['income_import_rule_delete']);
    $match = getFieldById('income_import_rules', $rule_id, 'import_rule_match');
    mysqli_query($mysqli, "DELETE FROM income_import_rules WHERE import_rule_id = $rule_id");
    if ($match !== null) {
        logAudit("Income", "Import Rule Delete", "$session_name removed the deposit match for " . escapeSql($match));
    }

    flashAlert($match !== null ? 'Forgotten - <strong>' . escapeHtml($match) . '</strong> will be matched afresh next time' : 'That match was already gone', $match !== null ? 'success' : 'error');

    redirect();

}
