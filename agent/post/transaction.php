<?php

/*
 * ITFlow - exporting transactions: one account's ledger with its balance
 * after each line, or every account's, through the same filters as the
 * ledger (functions/accounts.php).
 */

if (!defined('FROM_POST_HANDLER')) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if (isExportRequest('export_transactions')) {

    validateCSRFToken();

    enforceUserPermission('module_financial');

    $format = resolveExportFormat($_POST['export_transactions']);

    $account_id = intval($_POST['account'] ?? 0);
    $account = $account_id ? accountRow($account_id) : null;
    if (!$account) {
        $account_id = 0;
    }
    $where = accountLedgerFiltersSql($_POST, $filter_summary, true, !$account);
    if ($account) {
        $filter_summary = ['Account' => $account['account_name']] + $filter_summary;
    }
    $kinds = accountLedgerKinds();

    $sql = mysqli_query($mysqli, "SELECT ledger.* FROM (" . accountLedgerSql($account_id) . ") AS ledger WHERE $where
        ORDER BY ledger.txn_date ASC, ledger.txn_created_at ASC, ledger.txn_order ASC, ledger.txn_id ASC");

    $num_rows = mysqli_num_rows($sql);
    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $name = $account ? $account['account_name'] : 'All accounts';
        $export = beginExport('transactions', $format, "$session_company_name-$name-Transactions", "$name - Transactions", summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $amount = floatval($row['txn_amount']);
            $transfer = in_array($row['txn_kind'], ['transfer_in', 'transfer_out'], true);
            addExportRow($export, [
                'transaction_date' => $row['txn_date'],
                'transaction_account' => $row['account_name'],
                'transaction_type' => $kinds[$row['txn_kind']]['label'],
                'transaction_what' => $row['txn_kind'] === 'payment' && $row['txn_what'] !== '' ? 'Payment on ' . $row['txn_what'] : $row['txn_what'],
                'transaction_description' => trim((string) $row['txn_description']),
                'transaction_other_account' => $transfer ? (string) $row['other_account_name'] : '',
                'transaction_reference' => (string) $row['txn_reference'],
                'transaction_category' => $transfer ? '' : (string) $row['category_name'],
                'transaction_client' => (string) $row['client_name'],
                'transaction_payment_method' => (string) $row['txn_method'],
                'transaction_in' => $amount > 0 ? $amount : '',
                'transaction_out' => $amount < 0 ? -$amount : '',
                'transaction_amount' => $amount,
                'transaction_balance' => $account ? floatval($row['txn_balance']) : '',
            ]);
        }

        finishExport($export);
    }

    logAudit("Transaction", "Export", "$session_name exported $num_rows transaction(s) to a " . strtoupper($format) . " file");

    exit;

}
