<?php

/*
 * Import Deposits - bring the money in on a bank statement in as income.
 *
 * 1. Upload: the account the deposits went into, how they are usually paid,
 *    and the CSV the bank exports - the same file Import Statement takes for
 *    expenses.
 * 2. Columns: as Import Statement (agent/includes/statement_columns.php),
 *    remembered on the account for both sides.
 * 3. Review: every deposit as a payment on a client's invoices, other income
 *    or a transfer from another of our accounts, as it was matched, ready to
 *    change. Payments show what they pay off; deposits imported before, ones
 *    that look entered already and payouts recorded already are left out.
 *    Import brings in the ticked ones, emailing receipts when asked.
 *
 * functions/income_import.php matches and saves; the handlers are in
 * agent/post/income_import.php.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_financial', 2);

$import = $_SESSION['income_import'] ?? null;
$step = (string) ($_GET['step'] ?? '');
if (!$import || !in_array($step, ['columns', 'review'], true)) {
    $step = 'upload';
} elseif ($step === 'review' && empty($import['map'])) {
    $step = 'columns';
}
$accounts = paymentAccounts();
$methods = paymentMethods();
$csrf = $_SESSION['csrf_token'];
$can_pay = incomeImportCanPay();
$rules_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM income_import_rules"))[0]);
$account = $import ? ($accounts[intval($import['account_id'])] ?? null) : null;
$currency = $account ? (string) ($account['account_currency_code'] ?: $session_company_currency) : (string) $session_company_currency;
$money = function ($amount, $code = null) use ($currency) {
    return escapeHtml(billingMoney($amount, $code ?? $currency));
};

$steps = ['upload' => 'Statement', 'columns' => 'Columns', 'review' => 'Review'];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="income.php">Income</a></li>
    <li class="breadcrumb-item active">Import Deposits</li>
</ol>

<!-- Where it is up to -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <?php $i = 0; foreach ($steps as $key => $label) { $i++;
        $done = array_search($key, array_keys($steps)) < array_search($step, array_keys($steps)); ?>
        <?php if ($i > 1) { ?><i class="fas fa-chevron-right text-body-tertiary small"></i><?php } ?>
        <span class="badge rounded-pill fs-6 fw-normal <?= $key === $step ? 'text-bg-primary' : ($done ? 'bg-success-subtle text-success-emphasis' : 'bg-body-secondary text-body-secondary') ?>">
            <?= $done ? '<i class="fas fa-check me-1"></i>' : $i . '. ' ?><?= $label ?>
        </span>
    <?php } ?>
    <?php if ($import) { ?>
        <span class="ms-auto small text-body-secondary text-truncate">
            <i class="fas fa-file-csv me-1"></i><?= escapeHtml($import['file_name']) ?> &middot; <?= escapeHtml($account['account_name'] ?? 'an account since deleted') ?>
            <a class="ms-2 confirm-link" href="post.php?income_import_discard&amp;csrf_token=<?= $csrf ?>" data-confirm-text="The statement you started is dropped - nothing from it has been imported.">Start over</a>
        </span>
    <?php } ?>
</div>

<?php if ($step === 'upload') { ?>

    <?php if ($import) { ?>
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
            <i class="fas fa-file-csv"></i>
            <span class="me-auto"><?= escapeHtml($import['file_name']) ?> is part way through - nothing from it is imported yet.</span>
            <a class="btn btn-sm btn-primary" href="income_import.php?step=<?= $import['map'] ? 'review' : 'columns' ?>">Carry on with it</a>
        </div>
    <?php } ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-dark py-2">
                    <h3 class="card-title mt-2"><i class="fas fa-fw fa-file-import me-2"></i>Import Deposits</h3>
                </div>
                <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">The deposits went into <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                                    <select class="form-select select2" name="account" required>
                                        <option value="">- Which account -</option>
                                        <?php foreach ($accounts as $account_id => $row) { ?>
                                            <option value="<?= $account_id ?>" <?= $account_id === intval($config_default_payment_account) ? 'selected' : '' ?> data-icon="fas fa-piggy-bank"
                                                data-description="<?= escapeHtml(billingMoney($row['balance'], $row['account_currency_code'])) ?>"><?= escapeHtml($row['account_name']) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Usually paid by</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                                    <select class="form-select select2" name="payment_method">
                                        <option value="">- How they paid -</option>
                                        <?php foreach ($methods as $method) { ?>
                                            <option value="<?= escapeHtml($method) ?>" <?= $method === (string) $config_default_payment_method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-text">When a deposit doesn't say - ACH, a check or a wire is read from its text.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Statement <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-file-csv"></i></span>
                                    <input type="file" class="form-control" name="file" accept=".csv,.txt,.tsv,text/csv" required>
                                </div>
                                <div class="form-text">The CSV your bank's website exports - often under Download, Export or Activity. Up to <?= EXPENSE_IMPORT_MAX_LINES ?> lines. Only the money in is brought in here.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" name="income_import_upload" class="btn btn-primary text-bold"><i class="fas fa-upload me-2"></i>Read Statement</button>
                        <a class="btn btn-light ms-1" href="income.php"><i class="fas fa-times me-2"></i>Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body small">
                    <h3 class="h6 fw-semibold"><i class="fas fa-fw fa-info-circle text-secondary me-1"></i>How it goes</h3>
                    <ul class="ps-3 mb-3">
                        <li>The columns are worked out for you, and kept on the account - a statement already imported for expenses goes straight to the review.</li>
                        <li>Each deposit is matched to what it pays: an invoice number in its text, the client it came from last time or whose name is in it, or the only invoice owing that much. A client's invoices are paid oldest first, and a deposit short of them leaves the last one part paid.</li>
                        <li>Payouts from the payment provider and transfers from your other accounts come in as transfers, not income. Anything else is other income, with its category and client.</li>
                        <li>Deposits brought in before, and ones that match a payment or income entered by hand within <?= INCOME_IMPORT_DUPLICATE_DAYS ?> days, are left out unless you tick them.</li>
                        <li>Nothing is imported until you press Import on the review.</li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-body-secondary"><i class="fas fa-magic me-1"></i><?= $rules_count ?> payer<?= $rules_count === 1 ? '' : 's' ?> remembered</span>
                        <?php if ($rules_count) { ?>
                            <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/income/income_import_rules.php">See them</a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php } elseif ($step === 'columns') {
    $columns_form_id = 'incomeImportColumns';
    $columns_submit = 'income_import_columns';
    $columns_side = 'income';
    require __DIR__ . '/includes/statement_columns.php';
    ?>

<?php } else {
    // The review
    $lines = incomeImportReview($import, $charges);
    $open = $can_pay ? incomeImportOpenInvoices() : [];
    $categories = incomeCategories();
    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])] = $row['client_name'];
    }
    // Each client's open invoices, for the payment picks - and what each owes in all
    $owing = [];
    foreach ($open as $invoice) {
        $owing[$invoice['client_id']][] = ['id' => $invoice['invoice_id'], 'ref' => $invoice['ref'], 'balance' => $invoice['balance'], 'due' => $invoice['due'],
            'scope' => mb_strimwidth($invoice['scope'], 0, 60, '...'), 'currency' => $invoice['currency']];
    }
    $payer_options = '';
    foreach ($clients as $client_id => $client_name) {
        if (isset($owing[$client_id])) {
            $payer_options .= '<option value="' . $client_id . '">' . escapeHtml($client_name . ' - owes ' . billingMoney(array_sum(array_column($owing[$client_id], 'balance')))) . '</option>';
        }
    }
    $client_options = '';
    foreach ($clients as $client_id => $client_name) {
        $client_options .= '<option value="' . $client_id . '">' . escapeHtml($client_name) . '</option>';
    }
    $category_options = '';
    foreach ($categories as $category_id => $category) {
        $category_options .= '<option value="' . $category_id . '">' . escapeHtml($category['category_name']) . '</option>';
    }
    $account_options = '';
    foreach ($accounts as $account_id => $row) {
        if ($account_id !== intval($import['account_id'])) {
            $account_options .= '<option value="' . $account_id . '">' . escapeHtml($row['account_name']) . '</option>';
        }
    }

    $count = ['new' => 0, 'imported' => 0, 'entered' => 0, 'recorded' => 0, 'problem' => 0, 'payment' => 0];
    $total_new = 0;
    $total_paying = 0;
    foreach ($lines as $line) {
        $count[$line['state']]++;
        if ($line['state'] === 'new') {
            $total_new += $line['amount'];
            if ($line['action'] === 'payment' && $line['invoice_ids'] && $line['how'] !== 'over') {
                $count['payment']++;
                $total_paying += $line['amount'];
            }
        }
    }
    $states = [
        'new' => ['label' => 'New', 'tone' => 'primary'],
        'imported' => ['label' => 'Imported before', 'tone' => 'secondary'],
        'entered' => ['label' => 'Entered already?', 'tone' => 'warning'],
        'recorded' => ['label' => 'Recorded already', 'tone' => 'secondary'],
        'problem' => ['label' => "Can't read", 'tone' => 'danger'],
    ];
    $matched_icons = ['number' => 'fa-hashtag', 'rule' => 'fa-magic', 'name' => 'fa-user', 'amount' => 'fa-equals', 'payout' => 'fa-globe', 'transfer' => 'fa-exchange-alt'];
    ?>

    <!-- What is in it -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-arrow-down me-1"></i>New deposits</div>
                <div class="itflow-billing-stat-value"><?= $count['new'] ?></div>
                <div class="itflow-billing-stat-note"><?= $money($total_new) ?> came in</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-file-invoice-dollar me-1"></i>Paying invoices</div>
                <div class="itflow-billing-stat-value"><?= $count['payment'] ?></div>
                <div class="itflow-billing-stat-note"><?= $can_pay ? $money($total_paying) . ' matched to what clients owe' : 'Recording payments takes Sales access' ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-check-double me-1"></i>Already in</div>
                <div class="itflow-billing-stat-value"><?= $count['imported'] + $count['entered'] + $count['recorded'] ?></div>
                <div class="itflow-billing-stat-note"><?= $count['imported'] ?> imported before &middot; <?= $count['entered'] ?> look entered<?= $count['recorded'] ? ' &middot; ' . $count['recorded'] . ' payout' . ($count['recorded'] === 1 ? '' : 's') : '' ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-columns me-1"></i>Columns</div>
                <div class="itflow-billing-stat-value fs-6 fw-normal mt-2"><?= $import['remembered'] ? 'As last time' : 'As you set them' ?></div>
                <div class="itflow-billing-stat-note">
                    <a href="income_import.php?step=columns">Change them</a><?= $count['problem'] ? ' &middot; <span class="text-danger-emphasis">' . $count['problem'] . " can't be read</span>" : '' ?>
                    <?php if ($charges) { ?>
                        &middot; <?= $charges ?> charge<?= $charges === 1 ? '' : 's' ?> - <a href="post.php?expense_import_from_statement&amp;csrf_token=<?= $csrf ?>">bring them in as expenses</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$lines && $charges) { ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Every line on this statement reads as money out. If it has deposits, <a href="income_import.php?step=columns">check which way round the amounts are</a>.</div>
    <?php } ?>

    <form action="post.php" method="post" autocomplete="off" id="incomeImportReview">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="picks" value="" data-import-picks>

        <div class="card">
            <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-tasks text-secondary me-2"></i>The deposits</h3>
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <select class="form-select" data-income-bulk-category aria-label="Category for the ticked other income">
                        <option value="">- Category for the ticked other income -</option>
                        <?= $category_options ?>
                    </select>
                </div>
                <?php if ($count['imported'] || $count['entered'] || $count['recorded'] || $count['problem']) { ?>
                    <div class="form-check form-switch mb-0 ms-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="incomeImportShowAll" data-import-show-all>
                        <label class="form-check-label small" for="incomeImportShowAll">Show deposits already in</label>
                    </div>
                <?php } ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-import-table">
                    <thead class="table-light text-nowrap">
                    <tr>
                        <th class="checkbox-column border-end">
                            <div class="form-check"><input class="form-check-input" type="checkbox" data-import-all title="Tick or untick them all" aria-label="All"></div>
                        </th>
                        <th>Date</th>
                        <th>On the statement</th>
                        <th class="text-end">Amount</th>
                        <th>It is</th>
                        <th>What it pays, or where it goes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $line) {
                        $n = intval($line['n']);
                        $state = $line['state'];
                        $fixed = in_array($state, ['imported', 'problem'], true);
                        $action = $line['action'] === 'payment' && !$can_pay ? 'income' : $line['action'];
                        $ready = ($action === 'payment' && $line['invoice_ids'] && $line['how'] !== 'over') || $action === 'income' || ($action === 'transfer' && $line['from_account_id']);
                        $take = $state === 'new' && $ready; ?>
                        <tr data-import-row data-n="<?= $n ?>" data-key="<?= escapeHtml($line['key']) ?>" data-amount="<?= $line['amount'] === null ? 0 : $line['amount'] ?>" data-state="<?= $state ?>"
                            <?= $state === 'new' ? '' : 'data-import-extra' ?> class="<?= $take ? '' : 'itflow-unbilled-off' ?>">
                            <td class="checkbox-column border-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" data-import-take <?= $take ? 'checked' : '' ?> <?= $fixed ? 'disabled' : '' ?> aria-label="Import this deposit">
                                </div>
                            </td>
                            <td class="text-nowrap small"><?= $line['date'] ? escapeHtml($line['date']) : '<span class="text-danger-emphasis">?</span>' ?></td>
                            <td class="itflow-import-text">
                                <div class="text-truncate" title="<?= escapeHtml($line['description']) ?>"><?= escapeHtml($line['description']) ?: '<span class="text-body-secondary">-</span>' ?></div>
                                <div class="small">
                                    <span class="badge rounded-pill bg-<?= $states[$state]['tone'] ?>-subtle text-<?= $states[$state]['tone'] ?>-emphasis fw-normal"><?= $states[$state]['label'] ?></span>
                                    <?php if ($state === 'imported') { ?>
                                        <a class="ajax-modal small" href="#" data-modal-size="lg" data-modal-url="modals/<?= $line['imported'] === 'payment' ? 'payment/payment' : 'revenue/revenue' ?>.php?id=<?= intval($line['imported_id']) ?>">Open it</a>
                                    <?php } elseif ($state === 'entered') { ?>
                                        <?php if ($line['entered_type'] === 'payment') { ?>
                                            <a class="ajax-modal small" href="#" data-modal-size="lg" data-modal-url="modals/payment/payment.php?id=<?= intval($line['entered_ids'][0]) ?>"><?= escapeHtml($line['entered_label']) ?></a>
                                        <?php } else { ?>
                                            <span class="small text-body-secondary"><?= escapeHtml($line['entered_label']) ?></span>
                                        <?php } ?>
                                    <?php } elseif ($state === 'problem') { ?>
                                        <span class="small text-danger-emphasis"><?= escapeHtml($line['problem']) ?></span>
                                    <?php } ?>
                                    <?php if ($state !== 'problem' && $line['match_note'] !== '' && $state !== 'imported') { ?>
                                        <span class="small text-body-secondary"><i class="fas <?= $matched_icons[$line['matched']] ?? 'fa-info-circle' ?> me-1"></i><?= escapeHtml($line['match_note']) ?></span>
                                    <?php } ?>
                                    <?php if ($line['reference'] !== '') { ?><span class="small text-body-secondary ms-1">#<?= escapeHtml($line['reference']) ?></span><?php } ?>
                                    <?php if ($line['method'] !== '' && !$fixed) { ?><span class="small text-body-secondary ms-1">&middot; <?= escapeHtml($line['method']) ?></span><?php } ?>
                                </div>
                            </td>
                            <td class="text-end text-nowrap itflow-money fw-semibold text-success-emphasis"><?= $line['amount'] === null ? '-' : $money($line['amount']) ?></td>
                            <?php if ($fixed) { ?>
                                <td colspan="2" class="small text-body-secondary"><?= $state === 'imported' ? 'Brought in from a statement before - not imported again.' : 'Not imported - fix the columns if every line says this.' ?></td>
                            <?php } else { ?>
                                <td class="itflow-import-pick">
                                    <select class="form-select form-select-sm" data-income-action data-value="<?= escapeHtml($action) ?>" aria-label="What it is">
                                        <?php foreach (incomeImportActions() as $value => $label) { ?>
                                            <option value="<?= $value ?>" <?= $value === 'payment' && !$can_pay ? 'disabled' : '' ?>><?= $label ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td class="itflow-import-pick itflow-import-details">
                                    <div data-income-for="payment">
                                        <select class="form-select form-select-sm" data-income-payer data-value="<?= $action === 'payment' ? intval($line['client_id']) : '' ?>" aria-label="Paid by client">
                                            <option value="">- Which client paid -</option>
                                            <?= $payer_options ?>
                                        </select>
                                        <div class="itflow-import-invoices mt-1" data-income-invoices data-value="<?= escapeHtml(implode(',', $line['invoice_ids'])) ?>"></div>
                                        <div class="small mt-1" data-income-applies></div>
                                    </div>
                                    <div data-income-for="income">
                                        <select class="form-select form-select-sm" data-income-category data-value="<?= intval($line['category_id']) ?: '' ?>" aria-label="Category">
                                            <option value="">- Category -</option>
                                            <?= $category_options ?>
                                        </select>
                                        <select class="form-select form-select-sm mt-1" data-income-client data-value="<?= $action === 'income' ? intval($line['client_id']) : 0 ?>" aria-label="From client">
                                            <option value="0">- Not from a client -</option>
                                            <?= $client_options ?>
                                        </select>
                                    </div>
                                    <div data-income-for="transfer">
                                        <select class="form-select form-select-sm" data-income-account data-value="<?= intval($line['from_account_id']) ?: '' ?>" aria-label="From account">
                                            <option value="">- From which of your accounts -</option>
                                            <?= $account_options ?>
                                        </select>
                                        <div class="small text-body-secondary mt-1">Money moved between your accounts - not income.</div>
                                    </div>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                    <?php if (!$lines) { ?>
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No money in on this statement.</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap align-items-center gap-2 itflow-import-footer">
                <span class="me-auto small text-body-secondary" data-import-summary></span>
                <?php if ($can_pay) { ?>
                    <div class="form-check form-switch mb-0 me-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="receipts" value="1" id="incomeImportReceipts">
                        <label class="form-check-label small" for="incomeImportReceipts">Email each client their receipt</label>
                    </div>
                <?php } ?>
                <a class="btn btn-light" href="income_import.php?step=columns"><i class="fas fa-arrow-left me-2"></i>Columns</a>
                <button type="submit" name="income_import_save" value="1" class="btn btn-primary text-bold" data-import-submit><i class="fas fa-file-import me-2"></i><span data-import-submit-label>Import</span></button>
            </div>
        </div>
    </form>

    <script src="/js/income_import.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        itflowIncomeImport({
            currency: <?= json_encode($currency) ?>,
            locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>,
            invoices: <?= json_encode((object) $owing) ?>
        });
    });
    </script>

<?php } ?>

<?php require_once "../includes/footer.php";
