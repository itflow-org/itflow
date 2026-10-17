<?php

/*
 * Transactions - an account's ledger: every payment, other income, expense,
 * transfer and reimbursement on it in date order, with the balance after
 * each (functions/accounts.php). Or every account's, with the account on
 * each line. Transfers is a pill here - across every account each transfer
 * shows once, from the account it left.
 *
 * Along the top: what the account holds now (or what the accounts hold), and
 * the money in, out and the difference over the dates picked. Pills cut it
 * to money in, money out and transfers; filters by date, category, client,
 * and under More filters by kind, how it was paid and the amount. A line
 * opens its quick look.
 *
 * A line cleared on a reconciled statement (agent/reconcile.php) has a tick
 * by its date; the Uncleared pill is everything not cleared yet.
 *
 * Takes ?account=, ?show= (in, out, transfers, uncleared), ?kind= (or the
 * older ?type= Revenue, Payment, Expense, Transfer In, Transfer Out,
 * Reimbursement), ?category=, ?client=, ?method= (or ?payment_method=),
 * ?amount_min=, ?amount_max=, ?reconciliation= (the lines one statement
 * cleared), ?q= and the date filter.
 */

// Default Column Sortby/Order Filter
$sort = "txn_date";
$order = "DESC";

require_once "includes/inc_all.php";

enforceUserPermission('module_financial');

$can_edit = lookupUserPermission('module_financial') >= 2;
$open_accounts = accountsList(false);
$account_filter = intval($_GET['account'] ?? 0);
$account = $account_filter ? accountRow($account_filter) : null;
$missing_account = $account_filter && !$account;
if (!$account) {
    $account_filter = 0;
}
$all = !$account;
$types = accountTypes();
$kinds = accountLedgerKinds();
$shows = accountLedgerShows($all);
$show = (string) ($_GET['show'] ?? '');
$show = isset($shows[$show]) ? $show : '';
$kind_filter = (string) ($_GET['kind'] ?? '');
if ($kind_filter === '' && isset(accountLedgerTypeNames()[(string) ($_GET['type'] ?? '')])) {
    $kind_filter = accountLedgerTypeNames()[(string) $_GET['type']];
}
$category_filter = intval($_GET['category'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);
$method_filter = (string) ($_GET['method'] ?? ($_GET['payment_method'] ?? ''));
$amount_min = (string) ($_GET['amount_min'] ?? '');
$amount_max = (string) ($_GET['amount_max'] ?? '');
$more_filters = ($kind_filter !== '' && isset($kinds[$kind_filter])) || $method_filter !== '' || $amount_min !== '' || $amount_max !== '';
$reconciliation_filter = intval($_GET['reconciliation'] ?? 0);
$reconciliation = $reconciliation_filter ? reconcileRow($reconciliation_filter) : null;

// Only these sort - filter_header.php hands through any lowercase word
$sortable_columns = ['txn_date' => null, 'txn_amount' => 'ledger.txn_amount', 'category_name' => 'ledger.category_name', 'client_name' => 'ledger.client_name',
    'txn_what' => 'ledger.txn_what', 'account_name' => 'ledger.account_name'];
if (!array_key_exists($sort, $sortable_columns)) {
    $sort = 'txn_date';
}
$order_sql = $sort === 'txn_date'
    ? "ledger.txn_date $order, ledger.txn_created_at $order, ledger.txn_order $order, ledger.txn_id $order"
    : "{$sortable_columns[$sort]} $order, ledger.txn_date DESC, ledger.txn_id DESC";
$balance_visible = !$all && $sort === 'txn_date';

$filter_src = array_merge($_GET, ['dtf' => $dtf, 'dtt' => $dtt, 'kind' => $kind_filter, 'show' => $show]);
$where = accountLedgerFiltersSql($filter_src, $summary, false, $all);
$show_query = $show !== '' ? 'AND ' . $shows[$show]['where'] : '';
$ledger = accountLedgerSql($account_filter);

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS ledger.* FROM ($ledger) AS ledger WHERE $where $show_query ORDER BY $order_sql LIMIT $record_from, $record_to");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
$rows = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[] = $row;
}

// The pills, and the money in and out of what is listed
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . ",
        COALESCE(SUM(CASE WHEN 1 $show_query AND ledger.txn_amount > 0 THEN ledger.txn_amount END), 0),
        COALESCE(SUM(CASE WHEN 1 $show_query AND ledger.txn_amount < 0 THEN -ledger.txn_amount END), 0)
    FROM ($ledger) AS ledger WHERE $where"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}
$total_in = round(floatval($row[count($shows)]), 2);
$total_out = round(floatval($row[count($shows) + 1]), 2);
$currency = $account ? (string) $account['account_currency_code'] : (string) $session_company_currency;
$money = function ($amount, $code = null) use ($currency) {
    return escapeHtml(billingMoney($amount, $code ?? $currency));
};
$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page'], $params['type'], $params['payment_method']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null && $v !== 0; }));
};
$any_filter = $q || $category_filter || $client_filter || $more_filters || $reconciliation_filter || $show !== '' || $dtf !== '1970-01-01';
$dated = $dtf !== '1970-01-01' || $dtt !== '2099-12-31';
$cash = 0.0;
$cash_accounts = 0;
foreach ($open_accounts as $open) {
    if ($open['type'] !== 3) {
        $cash += $open['balance'];
        $cash_accounts++;
    }
}
$is_card = $account && $account['type'] === 3;

// Every account's transfers: what was moved, what paid off cards and what payment processors paid out
$transfer_cards = $all && $show === 'transfers';
if ($transfer_cards) {
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(-ledger.txn_amount), 0),
            COALESCE(SUM(CASE WHEN ledger.other_account_type = 3 THEN -ledger.txn_amount END), 0), COALESCE(SUM(CASE WHEN ledger.other_account_type = 3 THEN 1 END), 0),
            COALESCE(SUM(CASE WHEN ledger.account_type = 5 THEN -ledger.txn_amount END), 0), COALESCE(SUM(CASE WHEN ledger.account_type = 5 THEN 1 END), 0)
        FROM ($ledger) AS ledger
        WHERE $where $show_query"));
    $moved = ['count' => intval($row[0]), 'total' => round(floatval($row[1]), 2), 'cards' => round(floatval($row[2]), 2), 'card_count' => intval($row[3]),
        'payouts' => round(floatval($row[4]), 2), 'payout_count' => intval($row[5])];
}

?>

<?php if ($missing_account) { ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>That account no longer exists - here is every account's.</div>
<?php } ?>

<!-- The account, and the money over the dates picked -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <?php if ($account) { ?>
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas <?= $types[$account['type']]['icon'] ?> me-1"></i><?= $is_card && $account['balance'] < 0 ? 'Owed now' : 'Holds now' ?></div>
                <div class="itflow-billing-stat-value <?= $is_card && $account['balance'] < 0 ? 'text-warning-emphasis' : ($account['balance'] < 0 ? 'text-danger-emphasis' : '') ?>">
                    <?= $money($is_card && $account['balance'] < 0 ? -$account['balance'] : $account['balance']) ?>
                </div>
                <div class="itflow-billing-stat-note"><?= escapeHtml($types[$account['type']]['label']) ?> &middot; <?= escapeHtml($account['account_currency_code']) ?><?= $account['account_archived_at'] ? ' &middot; archived' : '' ?><?= floatval($account['opening_balance']) != 0 ? ' &middot; opened with ' . $money($account['opening_balance']) : '' ?>
                    &middot; <a href="reconcile.php?account=<?= $account_filter ?>"><?= $account['reconciling_id'] ? 'being reconciled' : ($account['reconciled_to'] ? 'reconciled to ' . escapeHtml(date('M j, Y', strtotime($account['reconciled_to']))) : 'never reconciled') ?></a></div>
            </div>
        <?php } else { ?>
            <a class="itflow-billing-stat" href="accounts.php">
                <div class="itflow-billing-stat-label"><i class="fas fa-university me-1"></i>In the accounts</div>
                <div class="itflow-billing-stat-value"><?= $money($cash) ?></div>
                <div class="itflow-billing-stat-note">Across <?= $cash_accounts ?> account<?= $cash_accounts === 1 ? '' : 's' ?><?= $cash_accounts < count($open_accounts) ? ', cards aside' : '' ?></div>
            </a>
        <?php } ?>
    </div>
    <?php if ($transfer_cards) { ?>
        <div class="col-sm-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-exchange-alt me-1"></i>Moved</div>
                <div class="itflow-billing-stat-value"><?= $money($moved['total']) ?></div>
                <div class="itflow-billing-stat-note"><?= $moved['count'] ?> transfer<?= $moved['count'] === 1 ? '' : 's' ?><?= $dated ? ', ' . escapeHtml(formatExportDateRange($dtf, $dtt)) : ' in all' ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-credit-card me-1"></i>Paid onto cards</div>
                <div class="itflow-billing-stat-value"><?= $money($moved['cards']) ?></div>
                <div class="itflow-billing-stat-note"><?= $moved['card_count'] ? $moved['card_count'] . ' card payment' . ($moved['card_count'] === 1 ? '' : 's') : 'No card payments' ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-globe me-1"></i>Paid out by processors</div>
                <div class="itflow-billing-stat-value"><?= $money($moved['payouts']) ?></div>
                <div class="itflow-billing-stat-note"><?= $moved['payout_count'] ? $moved['payout_count'] . ' payout' . ($moved['payout_count'] === 1 ? '' : 's') . ' of card payments' : 'No payouts' ?></div>
            </div>
        </div>
    <?php } else { ?>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $show === 'in' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'in'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-arrow-down me-1"></i>Money in</div>
            <div class="itflow-billing-stat-value text-success-emphasis"><?= $money($total_in) ?></div>
            <div class="itflow-billing-stat-note"><?= $dated ? escapeHtml(formatExportDateRange($dtf, $dtt)) : ($all ? 'All time' : 'Since it opened') ?><?= $all ? ' - transfers counted both ways' : '' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $show === 'out' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'out'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-arrow-up me-1"></i>Money out</div>
            <div class="itflow-billing-stat-value"><?= $money($total_out) ?></div>
            <div class="itflow-billing-stat-note"><?= $dated ? escapeHtml(formatExportDateRange($dtf, $dtt)) : ($all ? 'All time' : 'Since it opened') ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-balance-scale me-1"></i>Difference</div>
            <div class="itflow-billing-stat-value <?= $total_in - $total_out < 0 ? 'text-danger-emphasis' : '' ?>"><?= $money($total_in - $total_out) ?></div>
            <div class="itflow-billing-stat-note"><?= $account && $dated && $dtt !== '2099-12-31' ? 'Held ' . $money(accountBalance($account_filter, $dtt)) . ' at the end of ' . escapeHtml($dtt) : 'Money in less money out of what is listed' ?></div>
        </div>
    </div>
    <?php } ?>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2 text-truncate">
            <?php if ($account) { ?>
                <i class="fas fa-fw <?= $types[$account['type']]['icon'] ?> me-2"></i><?= escapeHtml($account['account_name']) ?>
            <?php } else { ?>
                <i class="fas fa-fw fa-list-alt me-2"></i><?= $show === 'transfers' ? 'Transfers' : 'Transactions' ?>
            <?php } ?>
        </h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/transfer/transfer_add.php<?= $account ? ($is_card ? '?to=' : '?from=') . $account_filter : '' ?>">
                        <i class="fas fa-<?= $is_card ? 'credit-card' : 'exchange-alt' ?> me-2"></i><?= $is_card ? 'Pay the Card' : 'New Transfer' ?>
                    </button>
                <?php } ?>
                <button type="button" class="btn <?= $can_edit ? 'btn-primary dropdown-toggle-split' : 'btn-light' ?> dropdown-toggle" data-bs-toggle="dropdown" aria-label="More"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#"
                        data-modal-url="<?= buildExportModalUrl('modals/transaction/transaction_export.php', ['account', 'show', 'kind', 'type', 'category', 'client', 'method', 'payment_method', 'amount_min', 'amount_max', 'reconciliation', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                        <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                    </a>
                    <?php if ($account) { ?>
                        <a class="dropdown-item" href="reconcile.php?account=<?= $account_filter ?>"><i class="fa fa-fw fa-tasks text-secondary me-2"></i><?= $account['reconciling_id'] ? 'Carry on reconciling' : 'Reconcile' ?></a>
                    <?php } ?>
                    <?php if ($account && $can_edit) { ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/account/account_edit.php?id=<?= $account_filter ?>"><i class="fa fa-fw fa-edit text-secondary me-2"></i>Edit Account</a>
                    <?php } ?>
                    <?php if ($can_edit) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="expense_import.php"><i class="fa fa-fw fa-file-import text-secondary me-2"></i>Import Statement - expenses</a>
                        <a class="dropdown-item" href="income_import.php"><i class="fa fa-fw fa-file-import text-secondary me-2"></i>Import Deposits - income</a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="accounts.php"><i class="fa fa-fw fa-piggy-bank text-secondary me-2"></i>Accounts</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <?php if ($reconciliation_filter) { ?>
                <input type="hidden" name="reconciliation" value="<?= $reconciliation_filter ?>">
            <?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                        <select class="form-select select2" name="account" onchange="this.form.submit()" aria-label="Account">
                            <option value="">- Every account -</option>
                            <?php foreach ($open_accounts + ($account && $account['account_archived_at'] ? [$account_filter => $account] : []) as $id => $option) { ?>
                                <option value="<?= $id ?>" <?= $account_filter === $id ? 'selected' : '' ?> data-icon="fas <?= $types[$option['type']]['icon'] ?>" data-description="<?= escapeHtml(accountBalanceText($option)) ?>"><?= escapeHtml($option['account_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search transactions" title="Who or what, description, reference, amount, category, client, account">
                        <button class="btn btn-outline-secondary <?= $more_filters ? 'active' : '' ?>" title="More filters" type="button" data-bs-toggle="collapse" data-bs-target="#ledgerMoreFilters"><i class="fas fa-filter"></i></button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="Date" aria-label="Date">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Category">
                        <option value="">- Any category -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT category_id, category_name, category_type, category_color, category_icon FROM categories
                            WHERE category_type IN ('Income', 'Expense') AND category_name <> 'Account Transfer' AND (category_archived_at IS NULL OR category_id = $category_filter)
                            ORDER BY category_type DESC, category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['category_id']); ?>
                            <option <?= $category_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= escapeHtml($row['category_name']) ?> (<?= $row['category_type'] === 'Income' ? 'in' : 'out' ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="Client">
                        <option value="">- Any client -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                            WHERE (client_archived_at IS NULL OR client_id = $client_filter) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['client_id']); ?>
                            <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="collapse <?= $more_filters ? 'show' : '' ?>" id="ledgerMoreFilters">
                <div class="row g-2 mt-0">
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-shapes"></i></span>
                            <select class="form-select select2" name="kind" onchange="this.form.submit()" aria-label="Kind">
                                <option value="">- Any kind -</option>
                                <?php foreach ($kinds as $key => $info) { ?>
                                    <option value="<?= $key ?>" <?= $kind_filter === $key ? 'selected' : '' ?> data-icon="fas <?= $info['icon'] ?>"><?= escapeHtml($info['label']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                            <select class="form-select select2" name="method" onchange="this.form.submit()" aria-label="Paid by">
                                <option value="">- Any payment method -</option>
                                <?php foreach (paymentMethods() as $method) { ?>
                                    <option <?= $method_filter === $method ? 'selected' : '' ?> value="<?= escapeHtml($method) ?>"><?= escapeHtml($method) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            <input type="number" step="0.01" min="0" class="form-control" name="amount_min" value="<?= escapeHtml($amount_min) ?>" placeholder="At least" aria-label="At least" onchange="this.form.submit()">
                            <input type="number" step="0.01" min="0" class="form-control" name="amount_max" value="<?= escapeHtml($amount_max) ?>" placeholder="At most" aria-label="At most" onchange="this.form.submit()">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php if ($reconciliation_filter) { ?>
            <div class="alert alert-success py-2 mt-2 mb-0 small d-flex flex-wrap gap-2 align-items-center">
                <i class="fas fa-check-circle"></i>
                <span>The lines cleared on <?= $reconciliation ? 'the ' . escapeHtml($reconciliation['account']['account_name']) . ' statement to ' . escapeHtml(date('M j, Y', strtotime($reconciliation['reconciliation_statement_date']))) . ($reconciliation['finished'] ? '' : ' - still being reconciled, so none yet') : 'a statement since undone - none' ?></span>
                <a class="ms-auto" href="<?= escapeHtml($filter_url(['reconciliation' => ''])) ?>">Show every line</a>
            </div>
        <?php } ?>
        <!-- Cut the list - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) { ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>">
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=txn_date&order=<?= $disp ?>">Date <?php if ($sort == 'txn_date') { echo $order_icon; } ?></a></th>
                <?php if ($all) { ?>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=account_name&order=<?= $disp ?>">Account <?php if ($sort == 'account_name') { echo $order_icon; } ?></a></th>
                <?php } ?>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=txn_what&order=<?= $disp ?>">Who or what <?php if ($sort == 'txn_what') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">Category <?php if ($sort == 'category_name') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=txn_amount&order=<?= $disp ?>">In <?php if ($sort == 'txn_amount') { echo $order_icon; } ?></a></th>
                <th class="text-end">Out</th>
                <?php if ($balance_visible) { ?>
                    <th class="text-end" title="What it held after the line">Balance</th>
                <?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $kind = $row['txn_kind'];
                $info = $kinds[$kind];
                $amount = floatval($row['txn_amount']);
                $row_currency = (string) ($row['txn_currency'] ?: $row['account_currency_code']);
                $transfer = in_array($kind, ['transfer_in', 'transfer_out'], true);
                [$what, $description] = accountLedgerWhat($row);
                $meta = array_filter([(string) $row['txn_method'], $row['txn_reference'] ? '#' . $row['txn_reference'] : '']);
                ?>
                <tr>
                    <td class="text-nowrap"><a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= escapeHtml(accountLedgerModal($row)) ?>"><?= escapeHtml($row['txn_date']) ?></a><?php if ($row['txn_cleared_id']) { ?><a class="ms-1 text-success-emphasis small" href="<?= escapeHtml($filter_url(['reconciliation' => intval($row['txn_cleared_id']), 'account' => intval($row['txn_account_id'])])) ?>" title="Cleared on the statement to <?= escapeHtml(date('M j, Y', strtotime($row['txn_cleared_date']))) ?>"><i class="fas fa-check-circle"></i></a><?php } ?></td>
                    <?php if ($all) { ?>
                        <td class="text-nowrap small">
                            <a class="text-reset" href="<?= escapeHtml($filter_url(['account' => intval($row['txn_account_id'])])) ?>" title="Its transactions"><i class="fas fa-fw <?= $types[accountType($row)]['icon'] ?> text-body-secondary me-1"></i><?= escapeHtml($row['account_name']) ?></a>
                        </td>
                    <?php } ?>
                    <td class="itflow-ledger-what">
                        <div class="text-truncate"><i class="fas fa-fw <?= $info['icon'] ?> text-body-secondary me-1" title="<?= escapeHtml($info['label']) ?>"></i><?= escapeHtml($what) ?><?php if ($description !== '') { ?><span class="text-body-secondary"> - <?= escapeHtml($description) ?></span><?php } ?></div>
                        <div class="small text-body-secondary text-truncate">
                            <?= escapeHtml($info['label']) ?><?= $meta ? ' &middot; ' . escapeHtml(implode(' · ', $meta)) : '' ?>
                            <?php if (intval($row['txn_imported'])) { ?><span class="ms-1" title="Brought in from a bank or card statement"><i class="fas fa-file-import"></i></span><?php } ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($transfer) { ?>
                            <span class="small text-body-secondary" title="Money moved between your accounts - not income or spending">Transfer</span>
                        <?php } elseif (intval($row['txn_category_id']) && $row['category_name'] !== null) { ?>
                            <div class="text-truncate" style="max-width: 11rem;" title="<?= escapeHtml($row['category_name']) ?>"><?= expenseCategoryDot($row['category_color']) ?><?= escapeHtml($row['category_name']) ?></div>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if (intval($row['txn_client_id']) && $row['client_name'] !== null) { ?>
                            <div class="text-truncate" style="max-width: 11rem;"><a class="text-reset" href="<?= escapeHtml($filter_url(['client' => intval($row['txn_client_id'])])) ?>"><?= escapeHtml($row['client_name']) ?></a></div>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="text-end text-nowrap itflow-money text-success-emphasis fw-semibold"><?= $amount > 0 ? $money($amount, $row_currency) : '' ?></td>
                    <td class="text-end text-nowrap itflow-money fw-semibold"><?= $amount < 0 ? $money(-$amount, $row_currency) : '' ?></td>
                    <?php if ($balance_visible) {
                        $balance = floatval($row['txn_balance']); ?>
                        <td class="text-end text-nowrap itflow-money <?= $balance < 0 ? ($is_card ? 'text-warning-emphasis' : 'text-danger-emphasis') : 'text-body-secondary' ?>"><?= $money($balance, $row_currency) ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
            <?php if ($num_rows[0]) { ?>
                <tfoot>
                <tr class="table-light">
                    <td colspan="<?= 4 + ($all ? 1 : 0) ?>" class="text-body-secondary small">
                        <?= intval($num_rows[0]) ?> line<?= intval($num_rows[0]) === 1 ? '' : 's' ?><?= $num_rows[0] > count($rows) ? ' - the totals are for all of them, not just this page' : '' ?>
                    </td>
                    <td class="text-end fw-semibold text-nowrap itflow-money text-success-emphasis"><?= $money($total_in) ?></td>
                    <td class="text-end fw-semibold text-nowrap itflow-money"><?= $money($total_out) ?></td>
                    <?php if ($balance_visible) { ?><td></td><?php } ?>
                </tr>
                </tfoot>
            <?php } ?>
        </table>
    </div>
    <?php if (!$num_rows[0]) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-<?= $show === 'transfers' ? 'exchange-alt' : 'list-alt' ?> fa-2x mb-2 opacity-50"></i>
            <div><?= $any_filter ? 'Nothing matches.' : ($account ? 'Nothing on ' . escapeHtml($account['account_name']) . ' yet.' : 'Nothing recorded yet.') ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php require_once "../includes/footer.php";
