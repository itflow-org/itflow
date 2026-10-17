<?php

/*
 * Accounts - where the money sits: checking and savings, credit cards, cash
 * and payment processors (functions/accounts.php).
 *
 * Along the top: what the accounts hold in all (cards aside), what is owed
 * on the cards, and what came in and went out this month against the same
 * days of last month, money moved between the accounts left out. Each
 * account shows its kind, what it holds (owed, for a card), this month in
 * and out, its latest line, where it is reconciled to and what it is set as
 * by default; clicking it opens its ledger. Archived ones are on their own
 * pill, to restore.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_financial');

$can_edit = lookupUserPermission('module_financial') >= 2;
$can_delete = lookupUserPermission('module_financial') >= 3;
$show = ($_GET['show'] ?? '') === 'archived' ? 'archived' : '';
$open_accounts = accountsList(false);
$archived_accounts = accountsList(true);
$stats = accountsStats($open_accounts);
$types = accountTypes();
$accounts = $show === 'archived' ? $archived_accounts : $open_accounts;
$q_text = trim((string) ($_GET['q'] ?? ''));
if ($q_text !== '') {
    $accounts = array_filter($accounts, function ($account) use ($q_text) {
        return mb_stripos($account['account_name'] . ' ' . $account['account_description'] . ' ' . $account['account_notes'], $q_text) !== false;
    });
}
$cards = array_filter($open_accounts, function ($account) { return $account['type'] === 3; });
$owing_cards = array_filter($cards, function ($account) { return $account['balance'] < 0; });
$money = function ($amount, $currency = '') {
    return escapeHtml(billingMoney($amount, $currency));
};
$change = function ($now, $before) {
    if ($before <= 0) {
        return null;
    }
    return round(($now - $before) / $before * 100);
};
$in_change = $change($stats['in'], $stats['last_in']);
$out_change = $change($stats['out'], $stats['last_out']);
$month_url = 'canned_date=custom&dtf=' . $stats['month_start'] . '&dtt=' . date('Y-m-t');

?>

<!-- What the accounts hold -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-university me-1"></i>In the accounts</div>
            <div class="itflow-billing-stat-value <?= $stats['cash'] < 0 ? 'text-danger-emphasis' : '' ?>"><?= $money($stats['cash']) ?></div>
            <div class="itflow-billing-stat-note">Across <?= count($open_accounts) - count($cards) ?> account<?= count($open_accounts) - count($cards) === 1 ? '' : 's' ?><?= $cards ? ', cards aside' : '' ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="<?= $owing_cards ? 'transactions.php?account=' . intval(array_key_first($owing_cards)) : '#' ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-credit-card me-1"></i>Owed on cards</div>
            <div class="itflow-billing-stat-value <?= $stats['owed'] > 0 ? 'text-warning-emphasis' : '' ?>"><?= $money($stats['owed']) ?></div>
            <div class="itflow-billing-stat-note"><?= $cards ? ($owing_cards ? 'On ' . count($owing_cards) . ' card' . (count($owing_cards) === 1 ? '' : 's') . ' - pay one with a transfer to it' : 'Every card is paid off') : 'No credit card accounts' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="income.php?<?= $month_url ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-arrow-down me-1"></i>In this month</div>
            <div class="itflow-billing-stat-value text-success-emphasis"><?= $money($stats['in']) ?></div>
            <div class="itflow-billing-stat-note">
                <?php if ($in_change !== null) { ?>
                    <span class="<?= $in_change >= 0 ? 'text-success-emphasis' : 'text-warning-emphasis' ?>"><i class="fas fa-arrow-<?= $in_change >= 0 ? 'up' : 'down' ?> me-1"></i><?= abs($in_change) ?>%</span> on the same days of <?= date('F', strtotime($stats['last_start'])) ?>
                <?php } else { ?>
                    Payments and other income, not transfers
                <?php } ?>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="expenses.php?<?= $month_url ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-arrow-up me-1"></i>Out this month</div>
            <div class="itflow-billing-stat-value"><?= $money($stats['out']) ?></div>
            <div class="itflow-billing-stat-note">
                <?php if ($out_change !== null) { ?>
                    <span class="<?= $out_change <= 0 ? 'text-success-emphasis' : 'text-warning-emphasis' ?>"><i class="fas fa-arrow-<?= $out_change >= 0 ? 'up' : 'down' ?> me-1"></i><?= abs($out_change) ?>%</span> on the same days of <?= date('F', strtotime($stats['last_start'])) ?>
                <?php } else { ?>
                    Expenses and reimbursements, not transfers
                <?php } ?>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-piggy-bank me-2"></i>Accounts</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/account/account_add.php"><i class="fas fa-plus me-2"></i>New Account</button>
                <?php } ?>
                <button type="button" class="btn <?= $can_edit ? 'btn-primary dropdown-toggle-split' : 'btn-light' ?> dropdown-toggle" data-bs-toggle="dropdown" aria-label="More"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($can_edit) { ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/transfer/transfer_add.php"><i class="fa fa-fw fa-exchange-alt text-secondary me-2"></i>New Transfer</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="expense_import.php"><i class="fa fa-fw fa-file-import text-secondary me-2"></i>Import Statement - expenses</a>
                        <a class="dropdown-item" href="income_import.php"><i class="fa fa-fw fa-file-import text-secondary me-2"></i>Import Deposits - income</a>
                        <div class="dropdown-divider"></div>
                    <?php } ?>
                    <a class="dropdown-item" href="transactions.php"><i class="fa fa-fw fa-list-alt text-secondary me-2"></i>Every account's transactions</a>
                    <a class="dropdown-item" href="transactions.php?show=transfers"><i class="fa fa-fw fa-exchange-alt text-secondary me-2"></i>Transfers</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form autocomplete="off" class="me-auto" style="min-width: 16rem;">
                <?php if ($show) { ?><input type="hidden" name="show" value="<?= $show ?>"><?php } ?>
                <div class="input-group">
                    <input type="search" class="form-control" name="q" value="<?= escapeHtml($q_text) ?>" placeholder="Search accounts">
                    <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                </div>
            </form>
            <nav class="nav nav-pills itflow-asset-tabs flex-nowrap">
                <a class="nav-link text-nowrap <?= $show === '' ? 'active' : '' ?>" href="accounts.php">Open <span class="badge rounded-pill ms-1"><?= count($open_accounts) ?></span></a>
                <a class="nav-link text-nowrap <?= $show === 'archived' ? 'active' : '' ?>" href="accounts.php?show=archived"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= count($archived_accounts) ?></span></a>
            </nav>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="<?= $accounts ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <th>Account</th>
                <th>Kind</th>
                <th class="text-end">Holds</th>
                <th class="text-end">This month</th>
                <th>Latest</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $account_id => $account) {
                $type = $types[$account['type']];
                $card = $account['type'] === 3;
                $currency = $account['account_currency_code'];
                $balance = $account['balance'];
                $in_use = $account['line_count'] > 0; ?>
                <tr>
                    <td class="itflow-account-name">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="itflow-asset-icon bg-body-secondary text-body-secondary" aria-hidden="true"><i class="fas <?= $type['icon'] ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-truncate d-block" href="transactions.php?account=<?= $account_id ?>" title="Open its transactions"><?= escapeHtml($account['account_name']) ?></a>
                                <div class="small text-body-secondary text-truncate">
                                    <?= escapeHtml($account['account_description'] ?: trim(strtok((string) $account['account_notes'], "\n"))) ?: '&nbsp;' ?>
                                </div>
                                <?php if ($account['defaults']) { ?>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php foreach ($account['defaults'] as $default) { ?>
                                            <span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal"><?= escapeHtml($default) ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                    <td class="text-nowrap"><?= escapeHtml($type['label']) ?><div class="small text-body-secondary"><?= escapeHtml($currency) ?></div></td>
                    <td class="text-end text-nowrap itflow-money">
                        <span class="fw-semibold <?= $card && $balance < 0 ? 'text-warning-emphasis' : ($balance < 0 ? 'text-danger-emphasis' : '') ?>"><?= escapeHtml(accountBalanceText($account)) ?></span>
                        <?php if (floatval($account['opening_balance']) != 0) { ?>
                            <div class="small text-body-secondary">opened with <?= $money($account['opening_balance'], $currency) ?></div>
                        <?php } ?>
                    </td>
                    <td class="text-end text-nowrap itflow-money small">
                        <?php if ($account['month_in'] || $account['month_out']) { ?>
                            <?php if ($account['month_in']) { ?>
                                <div class="text-success-emphasis">+<?= $money($account['month_in'], $currency) ?></div>
                            <?php } ?>
                            <?php if ($account['month_out']) { ?>
                                <div class="text-body-secondary">-<?= $money($account['month_out'], $currency) ?></div>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="text-nowrap small">
                        <?php if ($account['last_date']) { ?>
                            <?= escapeHtml($account['last_date']) ?><div class="text-body-secondary"><?= $account['line_count'] ?> line<?= $account['line_count'] === 1 ? '' : 's' ?></div>
                        <?php } else { ?>
                            <span class="text-body-secondary">Nothing yet</span>
                        <?php } ?>
                        <?php if ($account['reconciling_id']) { ?>
                            <a class="d-block text-warning-emphasis" href="reconcile.php?account=<?= $account_id ?>" title="A statement is being reconciled - carry on"><i class="fas fa-tasks me-1"></i>Reconciling</a>
                        <?php } elseif ($account['reconciled_to']) { ?>
                            <a class="d-block text-success-emphasis" href="reconcile.php?account=<?= $account_id ?>" title="Reconciled to its statement of <?= escapeHtml($account['reconciled_to']) ?>"><i class="fas fa-check-circle me-1"></i>To <?= escapeHtml(date('M j', strtotime($account['reconciled_to']))) ?></a>
                        <?php } ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="transactions.php?account=<?= $account_id ?>"><i class="fas fa-fw fa-list-alt text-secondary me-2"></i>Transactions</a>
                                <a class="dropdown-item" href="income.php?account=<?= $account_id ?>"><i class="fas fa-fw fa-hand-holding-usd text-secondary me-2"></i>Income into it</a>
                                <a class="dropdown-item" href="expenses.php?account=<?= $account_id ?>"><i class="fas fa-fw fa-shopping-cart text-secondary me-2"></i>Expenses from it</a>
                                <a class="dropdown-item" href="reconcile.php?account=<?= $account_id ?>"><i class="fas fa-fw fa-tasks text-secondary me-2"></i><?= $account['reconciling_id'] ? 'Carry on reconciling' : 'Reconcile' ?></a>
                                <?php if ($can_edit && !$account['account_archived_at']) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/account/account_edit.php?id=<?= $account_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                    <?php if ($card) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/transfer/transfer_add.php?to=<?= $account_id ?>"><i class="fas fa-fw fa-credit-card text-secondary me-2"></i>Pay the Card</a>
                                    <?php } else { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/transfer/transfer_add.php?from=<?= $account_id ?>"><i class="fas fa-fw fa-exchange-alt text-secondary me-2"></i>Transfer from it</a>
                                    <?php } ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if (abs($balance) < 0.005) { ?>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_account=<?= $account_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="<?= escapeHtml($account['account_name']) ?> leaves the account pickers. Its history stays, and it can be restored."><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                    <?php } else { ?>
                                        <span class="dropdown-item-text small text-body-secondary"><i class="fas fa-fw fa-archive me-2"></i>Archive once it's empty</span>
                                    <?php } ?>
                                <?php } elseif ($can_edit) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="post.php?restore_account=<?= $account_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Restore</a>
                                <?php } ?>
                                <?php if ($can_delete && !$in_use && !accountInUse($account_id)) { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_account=<?= $account_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= escapeHtml($account['account_name']) ?> has never been used, so it goes for good."><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
            <?php if ($accounts && $show === '' && $q_text === '') {
                $net = array_sum(array_column($accounts, 'balance')); ?>
                <tfoot>
                <tr class="table-light">
                    <td colspan="2" class="text-body-secondary small">What you have, less what is owed on cards</td>
                    <td class="text-end fw-semibold text-nowrap itflow-money <?= $net < 0 ? 'text-danger-emphasis' : '' ?>"><?= $money($net) ?></td>
                    <td colspan="3"></td>
                </tr>
                </tfoot>
            <?php } ?>
        </table>
    </div>
    <?php if (!$accounts) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-piggy-bank fa-2x mb-2 opacity-50"></i>
            <div><?= $q_text !== '' ? 'Nothing matches.' : ($show === 'archived' ? 'Nothing archived.' : 'No accounts yet.') ?></div>
        </div>
    <?php } ?>
</div>

<?php require_once "../includes/footer.php";
