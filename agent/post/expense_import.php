<?php

/*
 * ITFlow - importing a card or bank statement as expenses
 * (agent/expense_import.php; functions/expense_import.php does the work).
 *
 * Upload reads the CSV into the session with a first guess at its columns -
 * or the columns the account's last statement used, going straight to the
 * review. Columns sets them; Import brings in the ticked lines, sent from the
 * review as one JSON field (picks) so a long statement stays under PHP's
 * max_input_vars.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['expense_import_upload'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_POST['account'] ?? 0);
    $accounts = paymentAccounts();
    if (!isset($accounts[$account_id])) {
        flashAlert('Pick the account the statement is for', 'error');
        redirect('expense_import.php');
    }
    $method = trim((string) ($_POST['payment_method'] ?? ''));
    if ($method !== '' && !in_array($method, paymentMethods(), true)) {
        $method = '';
    }
    $file = $_FILES['file'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        flashAlert(($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'That file is too big - export a month or two at a time' : 'Choose the statement file', 'error');
        redirect('expense_import.php');
    }
    if (!in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), ['csv', 'txt', 'tsv'], true)) {
        flashAlert('Export the statement as a CSV file - that is a ' . escapeHtml(strtoupper(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) ?: 'different') . ' file', 'error');
        redirect('expense_import.php');
    }
    $read = expenseImportRead($file['tmp_name'], $error);
    if (!$read) {
        flashAlert(escapeHtml($error), 'error');
        redirect('expense_import.php');
    }

    // The columns the account's last statement used, if it looks the same
    $map = expenseImportSavedMap($account_id, $read['header']);
    $_SESSION['expense_import'] = [
        'account_id' => $account_id,
        'method' => $method,
        'file_name' => mb_substr(basename((string) $file['name']), 0, 120),
        'header' => $read['header'],
        'rows' => $read['rows'],
        'map' => $map,
        'guess' => $map ?: expenseImportGuess($read['header'], $read['rows']),
        'remembered' => $map !== null,
    ];

    redirect('expense_import.php?step=' . ($map ? 'review' : 'columns'));

}

if (isset($_POST['expense_import_columns'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $import = $_SESSION['expense_import'] ?? null;
    if (!$import) {
        flashAlert('Upload the statement again - the one you started is gone', 'error');
        redirect('expense_import.php');
    }
    $map = expenseImportMapClean($_POST, count($import['header']));
    if (!$map) {
        flashAlert('Pick the columns with the date, the description and the amount', 'error');
        redirect('expense_import.php?step=columns');
    }
    $lines = expenseImportLines($import['rows'], $map);
    if (!array_filter($lines, function ($line) { return $line['problem'] === ''; })) {
        $_SESSION['expense_import']['guess'] = $map;
        flashAlert("None of the lines read with those columns - check the date format and the amount", 'error');
        redirect('expense_import.php?step=columns');
    }

    $_SESSION['expense_import']['map'] = $map;
    $_SESSION['expense_import']['guess'] = $map;
    $_SESSION['expense_import']['remembered'] = false;
    expenseImportSaveMap($import['account_id'], $import['header'], $map);

    redirect('expense_import.php?step=review');

}

if (isset($_POST['expense_import_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $import = $_SESSION['expense_import'] ?? null;
    if (!$import || !$import['map']) {
        flashAlert('Upload the statement again - the one you started is gone', 'error');
        redirect('expense_import.php');
    }
    $picks = [];
    foreach ((array) json_decode((string) ($_POST['picks'] ?? ''), true) as $pick) {
        if (is_array($pick) && isset($pick['n'])) {
            $picks[intval($pick['n'])] = [
                'take' => !empty($pick['take']),
                'vendor' => (string) ($pick['vendor'] ?? ''),
                'new_vendor' => (string) ($pick['new_vendor'] ?? ''),
                'category' => intval($pick['category'] ?? 0),
                'client' => intval($pick['client'] ?? 0),
                'billable' => !empty($pick['billable']),
            ];
        }
    }
    $result = expenseImportSave($import, $picks, $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect('expense_import.php?step=review');
    }
    unset($_SESSION['expense_import']);

    $account_name = (string) getFieldById('accounts', intval($import['account_id']), 'account_name');
    $currency = (string) getFieldById('accounts', intval($import['account_id']), 'account_currency_code');
    logAudit("Expense", "Import", "$session_name imported " . $result['imported'] . " expense" . ($result['imported'] === 1 ? '' : 's') . " from " . escapeSql($import['file_name'] . " for $account_name")
        . ($result['vendors'] ? ", adding " . $result['vendors'] . " vendor" . ($result['vendors'] === 1 ? '' : 's') : ''));

    $notes = [];
    if ($result['vendors']) {
        $notes[] = $result['vendors'] . ' new vendor' . ($result['vendors'] === 1 ? '' : 's');
    }
    if ($result['linked']) {
        $notes[] = $result['linked'] . ' matched to ' . ($result['linked'] === 1 ? 'an expense' : 'expenses') . ' entered already';
    }
    if ($result['skipped']) {
        $notes[] = $result['skipped'] . ' imported before';
    }
    flashAlert('<strong>' . $result['imported'] . ' expense' . ($result['imported'] === 1 ? '' : 's') . '</strong> imported - ' . escapeHtml(billingMoney($result['total'], $currency ?: $session_company_currency))
        . ($notes ? ' &middot; ' . escapeHtml(implode(', ', $notes)) : ''));

    if (!$result['imported']) {
        redirect('expenses.php');
    }
    redirect('expenses.php?' . http_build_query(['account' => intval($import['account_id']), 'canned_date' => 'custom', 'dtf' => $result['from'], 'dtt' => $result['to']]));

}

/* Start over - the statement in progress is dropped. */
if (isset($_GET['expense_import_discard'])) {

    validateCSRFToken();

    unset($_SESSION['expense_import']);

    redirect('expense_import.php');

}

/* Forget what a merchant was matched to. */
if (isset($_GET['expense_import_rule_delete'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $rule_id = intval($_GET['expense_import_rule_delete']);
    $match = getFieldById('expense_import_rules', $rule_id, 'import_rule_match');
    mysqli_query($mysqli, "DELETE FROM expense_import_rules WHERE import_rule_id = $rule_id");
    if ($match !== null) {
        logAudit("Expense", "Import Rule Delete", "$session_name removed the statement match for " . escapeSql($match));
    }

    flashAlert($match !== null ? 'Forgotten - <strong>' . escapeHtml($match) . '</strong> will be matched afresh next time' : 'That match was already gone', $match !== null ? 'success' : 'error');

    redirect();

}
