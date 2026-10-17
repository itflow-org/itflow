<?php

/*
 * Reconcile - check an account against its bank or card statement
 * (functions/reconcile.php). Enter the statement's ending date and balance,
 * then tick each line the statement shows; the difference has to come to
 * nothing to finish. Ticks are saved as they are made (agent/ajax.php's
 * reconcile_tick, js/reconcile.js), so one can be left and finished later.
 * Lines brought in from a bank statement start ticked.
 *
 * With nothing in progress: where the account is reconciled to, the form to
 * start the next statement, and the statements reconciled before - each
 * opening the lines it cleared on Transactions, the latest one with Undo.
 * An account's first statement can start from the bank's balance the day it
 * starts, bringing everything before it forward as reconciled; where ITFlow
 * was off the bank that day, the opening balance can be corrected to match.
 *
 * Takes ?account= - or the account being reconciled, the default account
 * payments go into, or the first.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_financial');

$can_edit = lookupUserPermission('module_financial') >= 2;
$can_undo = lookupUserPermission('module_financial') >= 3;
$types = accountTypes();
$kinds = accountLedgerKinds();
$open_accounts = accountsList(false);

$account_id = intval($_GET['account'] ?? 0);
$account = $account_id ? accountRow($account_id) : null;
$missing_account = $account_id && !$account;
if (!$account) {
    $pick = 0;
    foreach ($open_accounts as $id => $option) {
        if ($option['reconciling_id']) {
            $pick = $id;
            break;
        }
    }
    if (!$pick && isset($open_accounts[intval($config_default_payment_account)])) {
        $pick = intval($config_default_payment_account);
    }
    if (!$pick && $open_accounts) {
        $pick = array_key_first($open_accounts);
    }
    $account = $pick ? $open_accounts[$pick] : null;
}
$account_id = $account ? intval($account['account_id']) : 0;
$is_card = $account && $account['type'] === 3;
$currency = $account ? (string) $account['account_currency_code'] : (string) $session_company_currency;
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};
// A balance as the statement reads it - a card's as what is owed
$statement_money = function ($balance) use ($account, $money) {
    return $account ? escapeHtml(reconcileBalanceText($account, $balance)) : $money($balance);
};

$open = $account ? reconcileOpen($account_id) : null;
$last = $account ? reconcileLast($account_id) : null;
$history = $account && !$open ? reconcileHistory($account_id) : [];
$starting = $account ? reconcileStartingBalance($account_id) : 0.0;
// Brought forward from the bank's balance - and how far ITFlow was from it that day
$opening = $account ? reconcileOpening($account_id) : null;
$opening_totals = $opening ? reconcileTotals($opening) : null;
$opening_gap = $opening ? round($opening_totals['difference'], 2) : 0.0;

// The next statement most likely ends at the end of last month
$suggest = date('Y-m-t', strtotime('first day of last month'));
if ($last && $suggest <= $last['reconciliation_statement_date']) {
    $suggest = date('Y-m-d');
}
if ($open) {
    $totals = reconcileTotals($open);
    $lines = reconcileLines($open, $later);
    $statement_date = $open['reconciliation_statement_date'];
} else {
    $uncleared = $account ? mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(ledger.txn_amount), 0) FROM (" . accountLedgerSql($account_id) . ") AS ledger WHERE ledger.txn_cleared_id IS NULL")) : [0, 0];
}

$statement_form = function ($row) use ($mysqli, $account, $is_card, $suggest, $last, $starting, $money, $statement_money) {
    $date = $row ? $row['reconciliation_statement_date'] : $suggest;
    $ending = $row ? floatval($row['reconciliation_ending_balance']) * ($is_card ? -1 : 1) : null;
    $first = !$row && !$last;
    if ($first) {
        // An account with history before the statement most likely starts from the bank's balance
        $start = date('Y-m-01', strtotime($date));
        $account_id = intval($account['account_id']);
        $earlier = mysqli_fetch_row(mysqli_query($mysqli, "SELECT EXISTS (SELECT 1 FROM payments WHERE payment_account_id = $account_id AND payment_date < '$start')
            OR EXISTS (SELECT 1 FROM revenues WHERE revenue_account_id = $account_id AND revenue_date < '$start')
            OR EXISTS (SELECT 1 FROM expenses WHERE expense_account_id = $account_id AND expense_date < '$start')"))[0];
        $from_statement = intval($earlier) === 1;
        ?>
        <div class="mb-3" data-reconcile-start>
            <div class="form-label">Its first statement starts from</div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="start_from" value="statement" id="reconcileStartStatement" <?= $from_statement ? 'checked' : '' ?>>
                <label class="form-check-label" for="reconcileStartStatement">The bank's balance the day the statement starts <span class="text-body-secondary">- everything on <?= escapeHtml($account['account_name']) ?> before that day counts as reconciled</span></label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="start_from" value="opening" id="reconcileStartOpening" <?= $from_statement ? '' : 'checked' ?>>
                <label class="form-check-label" for="reconcileStartOpening">The account's opening balance, <?= $statement_money($account['opening_balance']) ?> <span class="text-body-secondary">- tick everything since it opened</span></label>
            </div>
        </div>
        <div class="row g-3 mb-3" data-reconcile-start-fields <?= $from_statement ? '' : 'hidden' ?>>
            <div class="col-md-4">
                <label class="form-label">Statement starts <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="start_date" max="<?= date('Y-m-d') ?>" value="<?= escapeHtml($start) ?>" required <?= $from_statement ? '' : 'disabled' ?>>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $is_card ? 'Owed when it starts' : 'Starting balance' ?> <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="text" class="form-control text-end fw-semibold" inputmode="decimal" pattern="-?[0-9,]*\.?[0-9]{0,2}" name="start_balance" placeholder="0.00" required <?= $from_statement ? '' : 'disabled' ?>>
                    <span class="input-group-text"><?= escapeHtml($account['account_currency_code']) ?></span>
                </div>
                <div class="form-text"><?= $is_card ? 'The previous balance the statement says you owed' : 'The beginning balance on the statement' ?></div>
            </div>
        </div>
        <script>
        (function () {
            var start = document.querySelector('[data-reconcile-start]');
            var fields = document.querySelector('[data-reconcile-start-fields]');
            if (!start || !fields || start.dataset.wired) {
                return;
            }
            start.dataset.wired = '1';
            start.addEventListener('change', function () {
                var fromStatement = start.querySelector('input[value="statement"]').checked;
                fields.hidden = !fromStatement;
                fields.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !fromStatement;
                });
            });
        })();
        </script>
        <?php
    }
    ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Statement ends <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                <input type="date" class="form-control" name="statement_date" max="<?= date('Y-m-d') ?>" <?= $last ? 'min="' . escapeHtml(date('Y-m-d', strtotime($last['reconciliation_statement_date'] . ' +1 day'))) . '"' : '' ?> value="<?= escapeHtml($date) ?>" required>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label"><?= $is_card ? 'Owed on the statement' : 'Ending balance' ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                <input type="text" class="form-control text-end fw-semibold" inputmode="decimal" pattern="-?[0-9,]*\.?[0-9]{0,2}" name="ending_balance" placeholder="0.00" required
                    value="<?= $ending === null ? '' : escapeHtml(number_format($ending, 2, '.', '')) ?>">
                <span class="input-group-text"><?= escapeHtml($account['account_currency_code']) ?></span>
            </div>
            <div class="form-text"><?= $is_card ? 'The new balance the statement says you owe' : 'The balance the statement ends on' ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Notes</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                <input type="text" class="form-control" name="notes" maxlength="250" placeholder="The statement's number, or anything to remember" value="<?= escapeHtml($row['reconciliation_notes'] ?? '') ?>">
            </div>
        </div>
    </div>
    <?php if (!$first) { ?>
        <div class="form-text mt-2">
            <i class="fas fa-info-circle me-1"></i>It starts from <?= $statement_money($starting) ?> -
            <?= $last && $last['opening'] ? "the bank's balance when " . escapeHtml(date('M j, Y', strtotime($last['reconciliation_statement_date'] . ' +1 day'))) . ' started'
                : ($last ? 'where the statement to ' . escapeHtml(date('M j, Y', strtotime($last['reconciliation_statement_date']))) . ' ended' : "the account's opening balance") ?>.
        </div>
    <?php } ?>
    <?php
};

// Where ITFlow was off the bank on the day it was brought forward - correct the opening balance, or leave it
$gap_notice = function () use ($opening, $opening_totals, $opening_gap, $account, $can_edit, $statement_money, $money) {
    if (!$opening || abs($opening_gap) < 0.005 || abs(floatval($opening['reconciliation_adjustment'])) >= 0.005) {
        return;
    }
    $day = date('M j, Y', strtotime($opening['reconciliation_statement_date']));
    ?>
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
        <i class="fas fa-exclamation-triangle"></i>
        <span class="me-auto">On <?= escapeHtml($day) ?> ITFlow had <?= escapeHtml($account['account_name']) ?> at <?= $statement_money($opening_totals['cleared']) ?> and the bank at <?= $statement_money($opening_totals['ending']) ?>
            - <?= $money(abs($opening_gap)) ?> apart. Correct the opening balance by that so ITFlow matches the bank from then on, or leave it - the statements reconcile from the bank's balance either way.</span>
        <?php if ($can_edit) { ?>
            <a class="btn btn-sm btn-warning confirm-link" href="post.php?reconcile_correct_opening=<?= intval($opening['reconciliation_id']) ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                data-confirm-title="Correct the opening balance?" data-confirm-text="<?= escapeHtml($account['account_name']) ?>'s opening balance moves by <?= $money($opening_gap) ?>, to <?= $money(floatval($account['opening_balance']) + $opening_gap) ?>, and every balance after it with it. Undoing the brought forward reconciliation puts it back."
                data-confirm-button="Correct it"><i class="fas fa-wrench me-1"></i>Correct the opening balance</a>
        <?php } ?>
    </div>
    <?php
};

?>

<?php if ($missing_account) { ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>That account no longer exists.</div>
<?php } ?>

<?php if (!$account) { ?>
    <div class="card">
        <div class="itflow-asset-empty">
            <i class="fas fa-piggy-bank fa-2x mb-2 opacity-50"></i>
            <div>Add an account to reconcile first.</div>
            <a class="btn btn-sm btn-outline-primary mt-2" href="accounts.php">Accounts</a>
        </div>
    </div>
    <?php require_once "../includes/footer.php";
    return;
} ?>

<?php if ($open) { ?>

<?php $gap_notice(); ?>

<!-- Where the statement being reconciled stands - updated as lines are ticked -->
<div class="row g-3 mb-3" id="reconcileTotals">
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-flag me-1"></i>Starting from</div>
            <div class="itflow-billing-stat-value"><?= $statement_money($totals['starting']) ?></div>
            <div class="itflow-billing-stat-note"><?= $last && $last['opening'] ? "The bank's, when " . escapeHtml(date('M j, Y', strtotime($last['reconciliation_statement_date'] . ' +1 day'))) . ' started'
                : ($last ? 'Reconciled to ' . escapeHtml(date('M j, Y', strtotime($last['reconciliation_statement_date']))) : 'The opening balance') ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-file-invoice me-1"></i>The statement</div>
            <div class="itflow-billing-stat-value"><?= $statement_money($totals['ending']) ?></div>
            <div class="itflow-billing-stat-note">Ending <?= escapeHtml(date('M j, Y', strtotime($statement_date))) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-check-double me-1"></i>Cleared</div>
            <div class="itflow-billing-stat-value" data-reconcile-cleared><?= $statement_money($totals['cleared']) ?></div>
            <div class="itflow-billing-stat-note" data-reconcile-ticked><?= $totals['count'] ?> ticked - <?= $money($totals['in']) ?> in, <?= $money($totals['out']) ?> out</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat" data-reconcile-difference-card>
            <div class="itflow-billing-stat-label"><i class="fas fa-balance-scale me-1"></i>Difference</div>
            <div class="itflow-billing-stat-value" data-reconcile-difference><?= $money($totals['difference']) ?></div>
            <div class="itflow-billing-stat-note" data-reconcile-difference-note></div>
        </div>
    </div>
</div>

<div class="card" id="reconcile">
    <div class="card-header bg-dark py-2 d-flex align-items-center gap-2">
        <h3 class="card-title text-truncate flex-grow-1 min-w-0 mb-0">
            <i class="fas fa-fw fa-tasks me-2"></i>Reconcile <?= escapeHtml($account['account_name']) ?>
            <small class="text-white-50 ms-2 d-none d-md-inline">statement to <?= escapeHtml(date('M j, Y', strtotime($statement_date))) ?></small>
        </h3>
        <div class="card-tools flex-shrink-0 ms-0">
            <?php if ($can_edit) { ?>
                <form class="d-inline" id="reconcileFinish" action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="reconciliation_id" value="<?= intval($open['reconciliation_id']) ?>">
                    <div class="btn-group">
                        <button type="submit" name="reconcile_finish" class="btn btn-success" data-reconcile-finish <?= abs($totals['difference']) >= 0.005 ? 'disabled' : '' ?>
                            title="Once the difference is nothing"><i class="fas fa-check me-2"></i>Finish</button>
                        <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="#reconcileStatement" data-bs-toggle="collapse"><i class="fa fa-fw fa-edit text-secondary me-2"></i>Change the statement</a>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header">Add what the statement has</h6>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense_add.php?account_id=<?= $account_id ?>"><i class="fa fa-fw fa-cart-plus text-secondary me-2"></i>New Expense</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/revenue/revenue_add.php?account_id=<?= $account_id ?>"><i class="fa fa-fw fa-hand-holding-usd text-secondary me-2"></i>New Income</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/transfer/transfer_add.php?<?= $is_card ? 'to' : 'from' ?>=<?= $account_id ?>"><i class="fa fa-fw fa-exchange-alt text-secondary me-2"></i>New Transfer</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="transactions.php?account=<?= $account_id ?>"><i class="fa fa-fw fa-list-alt text-secondary me-2"></i>Transactions</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?reconcile_discard=<?= intval($open['reconciliation_id']) ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                data-confirm-title="Discard this reconciliation?" data-confirm-text="What is ticked comes off, and nothing is marked cleared." data-confirm-button="Discard"><i class="fa fa-fw fa-trash-alt me-2"></i>Discard</a>
                        </div>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>

    <?php if ($can_edit) { ?>
        <div class="collapse" id="reconcileStatement">
            <form class="card-header py-3" action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="reconciliation_id" value="<?= intval($open['reconciliation_id']) ?>">
                <?php $statement_form($open); ?>
                <div class="mt-3">
                    <button type="submit" name="reconcile_update" class="btn btn-primary btn-sm"><i class="fas fa-check me-2"></i>Save the statement</button>
                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#reconcileStatement"><i class="fas fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    <?php } ?>

    <div class="card-header py-3">
        <div class="row g-2 align-items-center">
            <div class="col-lg-4 col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                    <input type="search" class="form-control" placeholder="Find a line - who, reference or amount" data-reconcile-search aria-label="Find a line">
                </div>
            </div>
            <?php if ($can_edit) { ?>
                <div class="col-lg-8 col-md-6 text-md-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-reconcile-all="1"><i class="far fa-check-square me-1"></i>Tick all shown</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-reconcile-all="0"><i class="far fa-square me-1"></i>Untick all shown</button>
                </div>
            <?php } ?>
        </div>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto" data-reconcile-pills>
            <?php foreach (['' => ['All', ''], 'in' => ['Money in', 'fa-arrow-down'], 'out' => ['Money out', 'fa-arrow-up'], 'unticked' => ['Not ticked', 'fa-hourglass-half'], 'ticked' => ['Ticked', 'fa-check']] as $key => [$label, $icon]) { ?>
                <a class="nav-link text-nowrap <?= $key === '' ? 'active' : '' ?>" href="#" data-reconcile-show="<?= $key ?>">
                    <?php if ($icon) { ?><i class="fas fa-fw <?= $icon ?> me-1"></i><?php } ?><?= $label ?>
                    <span class="badge rounded-pill ms-1" data-reconcile-count="<?= $key ?>">0</span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-reconcile-table">
            <thead class="text-nowrap table-light <?= $lines ? '' : 'd-none' ?>">
            <tr>
                <th class="text-center" style="width: 3rem;"><span class="visually-hidden">Cleared</span><i class="fas fa-check text-body-secondary" title="Cleared on this statement"></i></th>
                <th>Date</th>
                <th>Who or what</th>
                <th class="text-end">In</th>
                <th class="text-end">Out</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $line) {
                $kind = $line['txn_kind'];
                $info = $kinds[$kind];
                $amount = round(floatval($line['txn_amount']), 2);
                [$what, $description] = accountLedgerWhat($line);
                $meta = array_filter([$info['label'], (string) $line['txn_method'], $line['txn_reference'] ? '#' . $line['txn_reference'] : '']);
                $search = mb_strtolower(implode(' ', [$what, $description, (string) $line['txn_reference'], (string) $line['client_name'], number_format(abs($amount), 2, '.', ''), number_format(abs($amount), 2)]));
                ?>
                <tr data-reconcile-line="<?= escapeHtml($line['txn_line']) ?>" data-amount="<?= escapeHtml(number_format($amount, 2, '.', '')) ?>" data-search="<?= escapeHtml($search) ?>" class="<?= $line['ticked'] ? 'itflow-reconcile-ticked' : '' ?>">
                    <td class="text-center">
                        <input class="form-check-input" type="checkbox" data-reconcile-tick <?= $line['ticked'] ? 'checked' : '' ?> <?= $can_edit ? '' : 'disabled' ?> aria-label="Cleared">
                    </td>
                    <td class="text-nowrap"><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= escapeHtml(accountLedgerModal($line)) ?>"><?= escapeHtml($line['txn_date']) ?></a></td>
                    <td class="itflow-ledger-what">
                        <div class="text-truncate"><i class="fas fa-fw <?= $info['icon'] ?> text-body-secondary me-1"></i><?= escapeHtml($what) ?><?php if ($description !== '') { ?><span class="text-body-secondary"> - <?= escapeHtml($description) ?></span><?php } ?></div>
                        <div class="small text-body-secondary text-truncate">
                            <?= escapeHtml(implode(' · ', $meta)) ?>
                            <?php if (intval($line['txn_imported'])) { ?><span class="ms-1" title="Brought in from a bank or card statement"><i class="fas fa-file-import"></i></span><?php } ?>
                        </div>
                    </td>
                    <td class="text-end text-nowrap itflow-money text-success-emphasis fw-semibold"><?= $amount > 0 ? $money($amount) : '' ?></td>
                    <td class="text-end text-nowrap itflow-money fw-semibold"><?= $amount < 0 ? $money(-$amount) : '' ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if (!$lines) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-check-double fa-2x mb-2 opacity-50"></i>
            <div>Nothing on <?= escapeHtml($account['account_name']) ?> up to <?= escapeHtml(date('M j, Y', strtotime($statement_date))) ?> is left to clear.</div>
        </div>
    <?php } ?>
    <!-- Stays in view down a long statement: where it stands, and Finish -->
    <div class="card-footer itflow-reconcile-bar d-flex flex-wrap gap-2 align-items-center">
        <div class="small text-body-secondary me-auto">
            <i class="fas fa-save me-1"></i>Ticks are saved as you go - leave it and come back to finish any time.
            <?php if ($later) { ?>
                <div><?= $later ?> line<?= $later === 1 ? '' : 's' ?> after <?= escapeHtml(date('M j, Y', strtotime($statement_date))) ?> wait for the next statement.</div>
            <?php } ?>
        </div>
        <div class="text-nowrap">
            <span class="small text-body-secondary me-1">Difference</span>
            <span class="fw-semibold itflow-money" data-reconcile-difference><?= $money($totals['difference']) ?></span>
        </div>
        <?php if ($can_edit) { ?>
            <button type="submit" form="reconcileFinish" name="reconcile_finish" class="btn btn-success btn-sm" data-reconcile-finish <?= abs($totals['difference']) >= 0.005 ? 'disabled' : '' ?>
                title="Once the difference is nothing"><i class="fas fa-check me-2"></i>Finish</button>
        <?php } ?>
    </div>
</div>

<script src="/js/reconcile.js"></script>
<script>
    itflowReconcile({
        id: <?= intval($open['reconciliation_id']) ?>,
        csrf: <?= json_encode($_SESSION['csrf_token']) ?>,
        starting: <?= json_encode($totals['starting']) ?>,
        ending: <?= json_encode($totals['ending']) ?>,
        card: <?= $is_card ? 'true' : 'false' ?>,
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
</script>

<?php } else { ?>

<?php $gap_notice(); ?>

<!-- Where the account is reconciled to -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-4">
        <a class="itflow-billing-stat" href="transactions.php?account=<?= $account_id ?>">
            <div class="itflow-billing-stat-label"><i class="fas <?= $types[$account['type']]['icon'] ?> me-1"></i><?= $is_card && $account['balance'] < 0 ? 'Owed now' : 'Holds now' ?></div>
            <div class="itflow-billing-stat-value"><?= $statement_money($account['balance']) ?></div>
            <div class="itflow-billing-stat-note"><?= escapeHtml($types[$account['type']]['label']) ?> &middot; <?= escapeHtml($account['account_currency_code']) ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-check-double me-1"></i>Reconciled to</div>
            <div class="itflow-billing-stat-value"><?= $last ? escapeHtml(date('M j, Y', strtotime($last['reconciliation_statement_date']))) : 'Never' ?></div>
            <div class="itflow-billing-stat-note"><?= $last && $last['opening'] ? "Brought forward at the bank's " . $statement_money($last['reconciliation_ending_balance'])
                : ($last ? 'Ending at ' . $statement_money($last['reconciliation_ending_balance']) : 'Its first statement starts from the bank\'s balance or the opening balance') ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <a class="itflow-billing-stat" href="transactions.php?account=<?= $account_id ?>&amp;show=uncleared">
            <div class="itflow-billing-stat-label"><i class="fas fa-hourglass-half me-1"></i>Not cleared yet</div>
            <div class="itflow-billing-stat-value"><?= intval($uncleared[0]) ?> line<?= intval($uncleared[0]) === 1 ? '' : 's' ?></div>
            <div class="itflow-billing-stat-note"><?= intval($uncleared[0]) ? $money($uncleared[1]) . ' net, not on a reconciled statement' : 'Everything on it is reconciled' ?></div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2 d-flex align-items-center gap-2">
        <h3 class="card-title text-truncate flex-grow-1 min-w-0 mb-0"><i class="fas fa-fw fa-tasks me-2"></i>Reconcile <?= escapeHtml($account['account_name']) ?></h3>
        <div class="card-tools flex-shrink-0 ms-0">
            <a class="btn btn-light" href="transactions.php?account=<?= $account_id ?>"><i class="fas fa-list-alt me-2"></i>Transactions</a>
        </div>
    </div>
    <div class="card-header py-3">
        <div class="row g-2">
            <div class="col-lg-4 col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                    <select class="form-select select2" aria-label="Account" onchange="window.location.href = 'reconcile.php?account=' + this.value">
                        <?php foreach ($open_accounts + ($account['account_archived_at'] ? [$account_id => $account] : []) as $id => $option) { ?>
                            <option value="<?= $id ?>" <?= $account_id === $id ? 'selected' : '' ?> data-icon="fas <?= $types[$option['type']]['icon'] ?>"
                                data-description="<?= escapeHtml($option['reconciling_id'] ? 'Being reconciled' : ($option['reconciled_to'] ? 'Reconciled to ' . date('M j, Y', strtotime($option['reconciled_to'])) : 'Never reconciled')) ?>"><?= escapeHtml($option['account_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <?php if ($can_edit && !$account['account_archived_at']) { ?>
        <form class="card-body border-bottom" action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="account_id" value="<?= $account_id ?>">
            <h6 class="fw-semibold mb-3">The next statement</h6>
            <?php $statement_form(null); ?>
            <button type="submit" name="reconcile_start" class="btn btn-primary mt-3"><i class="fas fa-play me-2"></i>Start reconciling</button>
        </form>
    <?php } ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="text-nowrap table-light <?= $history ? '' : 'd-none' ?>">
            <tr>
                <th>Statement to</th>
                <th class="text-end"><?= $is_card ? 'Owed' : 'Ending balance' ?></th>
                <th>Cleared</th>
                <th class="text-end">In</th>
                <th class="text-end">Out</th>
                <th>Reconciled</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $i => $row) {
                $t = $row['totals']; ?>
                <tr>
                    <td class="text-nowrap fw-semibold"><?= escapeHtml(date('M j, Y', strtotime($row['reconciliation_statement_date']))) ?>
                        <?php if ($row['opening']) { ?>
                            <div class="small text-body-secondary fw-normal"><i class="fas fa-angle-double-right me-1"></i>Brought forward - everything before <?= escapeHtml(date('M j, Y', strtotime($row['reconciliation_statement_date'] . ' +1 day'))) ?></div>
                        <?php } elseif ($row['reconciliation_notes'] !== null && $row['reconciliation_notes'] !== '') { ?><div class="small text-body-secondary fw-normal text-truncate" style="max-width: 16rem;"><?= escapeHtml($row['reconciliation_notes']) ?></div><?php } ?>
                    </td>
                    <td class="text-end text-nowrap itflow-money fw-semibold"><?= $statement_money($row['reconciliation_ending_balance']) ?>
                        <?php if ($row['opening'] && abs(floatval($row['reconciliation_adjustment'])) >= 0.005) { ?>
                            <div class="small text-body-secondary fw-normal">opening balance corrected by <?= $money($row['reconciliation_adjustment']) ?></div>
                        <?php } elseif ($row['opening'] && abs($t['difference']) >= 0.005) { ?>
                            <div class="small text-warning-emphasis fw-normal"><?= $money(abs($t['difference'])) ?> off ITFlow's <?= $statement_money($t['cleared']) ?></div>
                        <?php } ?>
                    </td>
                    <td class="text-nowrap"><a href="transactions.php?account=<?= $account_id ?>&amp;reconciliation=<?= intval($row['reconciliation_id']) ?>" title="The lines it cleared"><?= $t['count'] ?> line<?= $t['count'] === 1 ? '' : 's' ?></a>
                        <div class="small text-body-secondary">from <?= $statement_money($t['starting']) ?></div>
                    </td>
                    <td class="text-end text-nowrap itflow-money text-success-emphasis"><?= $money($t['in']) ?></td>
                    <td class="text-end text-nowrap itflow-money"><?= $money($t['out']) ?></td>
                    <td class="small text-nowrap">
                        <?= escapeHtml($row['finished_by_name'] ?? 'Someone since removed') ?>
                        <div class="text-body-secondary" title="<?= escapeHtml($row['reconciliation_finished_at']) ?>"><?= escapeHtml(timeAgo($row['reconciliation_finished_at'])) ?></div>
                    </td>
                    <td class="text-end">
                        <?php if ($i === 0 && $can_undo) { ?>
                            <a class="btn btn-sm btn-outline-danger confirm-link" href="post.php?reconcile_undo=<?= intval($row['reconciliation_id']) ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                data-confirm-title="Undo this reconciliation?" data-confirm-button="Undo"
                                data-confirm-text="<?= $row['opening'] ? 'Nothing before ' . escapeHtml(date('M j, Y', strtotime($row['reconciliation_statement_date'] . ' +1 day'))) . ' counts as reconciled any more' . (abs(floatval($row['reconciliation_adjustment'])) >= 0.005 ? ', and the opening balance goes back' : '') . ' - the next statement can start again from either balance.' : 'It goes back to being reconciled, with what was ticked - its lines can change again until it is finished.' ?>"
                                title="<?= $row['opening'] ? 'Take the brought forward away' : 'Back to being reconciled' ?>"><i class="fas fa-undo me-1"></i>Undo</a>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if (!$history) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-tasks fa-2x mb-2 opacity-50"></i>
            <div><?= escapeHtml($account['account_name']) ?> hasn't been reconciled yet.</div>
        </div>
    <?php } ?>
</div>

<?php } ?>

<?php require_once "../includes/footer.php";
