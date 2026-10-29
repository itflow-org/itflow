<?php

/*
 * Transfers are the Transfers pill of Transactions now (agent/transactions.php)
 * - across every account, or one. Old links land there, with their filters.
 */

$params = ['show' => 'transfers'];
$account = intval($_GET['account_from'] ?? 0) ?: intval($_GET['account_to'] ?? 0);
if ($account) {
    $params['account'] = $account;
}
foreach (['q', 'canned_date', 'dtf', 'dtt'] as $key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $params[$key] = (string) $_GET[$key];
    }
}
header('Location: transactions.php?' . http_build_query($params), true, 302);
exit;
