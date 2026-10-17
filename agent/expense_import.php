<?php

/*
 * Import Statement - bring a card or bank statement in as expenses.
 *
 * 1. Upload: the account it is for, how it was paid, and the CSV the bank
 *    or card exports.
 * 2. Columns: which column is the date, the description, the amount (or
 *    money out and money in), a reference, the date format, and whether
 *    charges are positive. Guessed, and remembered on the account - the
 *    next statement with the same columns goes straight to the review.
 * 3. Review: every line with the vendor, category and client it matched,
 *    ready to change; new vendors named from the text; lines imported
 *    before, or that look entered by hand already, and money in left
 *    unticked. Picking a vendor fills in the other lines from the same
 *    merchant. Import brings in the ticked ones.
 *
 * functions/expense_import.php reads, matches and saves; the handlers are
 * in agent/post/expense_import.php. The columns step is
 * agent/includes/statement_columns.php, shared with Import Deposits
 * (agent/income_import.php), which the money in can go on to.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_financial', 2);

$import = $_SESSION['expense_import'] ?? null;
$step = (string) ($_GET['step'] ?? '');
if (!$import || !in_array($step, ['columns', 'review'], true)) {
    $step = 'upload';
} elseif ($step === 'review' && empty($import['map'])) {
    $step = 'columns';
}
$accounts = paymentAccounts();
$methods = paymentMethods();
$csrf = $_SESSION['csrf_token'];
$rules_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM expense_import_rules"))[0]);
$account = $import ? ($accounts[intval($import['account_id'])] ?? null) : null;
$currency = $account ? (string) $account['account_currency_code'] : (string) $session_company_currency;
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$steps = ['upload' => 'Statement', 'columns' => 'Columns', 'review' => 'Review'];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="expenses.php">Expenses</a></li>
    <li class="breadcrumb-item active">Import Statement</li>
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
            <a class="ms-2 confirm-link" href="post.php?expense_import_discard&amp;csrf_token=<?= $csrf ?>" data-confirm-text="The statement you started is dropped - nothing from it has been imported.">Start over</a>
        </span>
    <?php } ?>
</div>

<?php if ($step === 'upload') { ?>

    <?php if ($import) { ?>
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
            <i class="fas fa-file-csv"></i>
            <span class="me-auto"><?= escapeHtml($import['file_name']) ?> is part way through - nothing from it is imported yet.</span>
            <a class="btn btn-sm btn-primary" href="expense_import.php?step=<?= $import['map'] ? 'review' : 'columns' ?>">Carry on with it</a>
        </div>
    <?php } ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-dark py-2">
                    <h3 class="card-title mt-2"><i class="fas fa-fw fa-file-import me-2"></i>Import Statement</h3>
                </div>
                <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">It is the statement of <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                                    <select class="form-select select2" name="account" required>
                                        <option value="">- Which account or card -</option>
                                        <?php foreach ($accounts as $account_id => $row) { ?>
                                            <option value="<?= $account_id ?>" <?= $account_id === intval($config_default_expense_account) ? 'selected' : '' ?> data-icon="fas fa-piggy-bank"
                                                data-description="<?= escapeHtml(billingMoney($row['balance'], $row['account_currency_code'])) ?>"><?= escapeHtml($row['account_name']) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Paid by</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                                    <select class="form-select select2" name="payment_method">
                                        <option value="">- How they were paid -</option>
                                        <?php foreach ($methods as $method) { ?>
                                            <option value="<?= escapeHtml($method) ?>" <?= $method === (string) $config_default_expense_payment_method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Statement <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-file-csv"></i></span>
                                    <input type="file" class="form-control" name="file" accept=".csv,.txt,.tsv,text/csv" required>
                                </div>
                                <div class="form-text">The CSV your bank or card's website exports - often under Download, Export or Activity. Up to <?= EXPENSE_IMPORT_MAX_LINES ?> lines.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" name="expense_import_upload" class="btn btn-primary text-bold"><i class="fas fa-upload me-2"></i>Read Statement</button>
                        <a class="btn btn-light ms-1" href="expenses.php"><i class="fas fa-times me-2"></i>Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body small">
                    <h3 class="h6 fw-semibold"><i class="fas fa-fw fa-info-circle text-secondary me-1"></i>How it goes</h3>
                    <ul class="ps-3 mb-3">
                        <li>The columns are worked out for you - check them once, and the next statement from the same account goes straight to the review.</li>
                        <li>Each line is matched to a vendor: what you picked for that merchant last time, or one of your vendors whose name is in it. A new vendor can be made from the line's text.</li>
                        <li>Lines brought in before, and ones with the same amount as an expense entered by hand within <?= EXPENSE_IMPORT_DUPLICATE_DAYS ?> days, are left out unless you tick them.</li>
                        <li>Payments to the card and other money in are left out unless you tick them - then they come in as money back.</li>
                        <li>Nothing is imported until you press Import on the review.</li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-body-secondary"><i class="fas fa-magic me-1"></i><?= $rules_count ?> merchant<?= $rules_count === 1 ? '' : 's' ?> remembered</span>
                        <?php if ($rules_count) { ?>
                            <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_import_rules.php">See them</a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php } elseif ($step === 'columns') {
    $columns_form_id = 'expenseImportColumns';
    $columns_submit = 'expense_import_columns';
    $columns_side = 'expense';
    require __DIR__ . '/includes/statement_columns.php';
    ?>

<?php } else {
    // The review
    $lines = expenseImportReview($import);
    $vendors = expenseVendors();
    $categories = expenseCategories();
    $defaults = expenseVendorDefaults();
    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])] = $row['client_name'];
    }
    $count = ['new' => 0, 'imported' => 0, 'entered' => 0, 'money_in' => 0, 'problem' => 0, 'matched' => 0];
    foreach ($lines as $i => $line) {
        $state = $line['problem'] !== '' ? 'problem' : ($line['imported'] ? 'imported' : ($line['entered'] ? 'entered' : ($line['amount'] < 0 ? 'money_in' : 'new')));
        $lines[$i]['state'] = $state;
        $count[$state]++;
        $count['matched'] += $state === 'new' && $line['vendor_id'] ? 1 : 0;
    }
    $vendor_options = '';
    foreach ($vendors as $vendor_id => $vendor) {
        $vendor_options .= '<option value="' . $vendor_id . '">' . escapeHtml($vendor['vendor_name']) . '</option>';
    }
    $category_options = '';
    foreach ($categories as $category_id => $category) {
        $category_options .= '<option value="' . $category_id . '">' . escapeHtml($category['category_name']) . '</option>';
    }
    $client_options = '';
    foreach ($clients as $client_id => $client_name) {
        $client_options .= '<option value="' . $client_id . '">' . escapeHtml($client_name) . '</option>';
    }
    $states = [
        'new' => ['label' => 'New', 'tone' => 'primary'],
        'imported' => ['label' => 'Imported before', 'tone' => 'secondary'],
        'entered' => ['label' => 'Entered already?', 'tone' => 'warning'],
        'money_in' => ['label' => 'Money in', 'tone' => 'success'],
        'problem' => ['label' => "Can't read", 'tone' => 'danger'],
    ];
    ?>

    <!-- What is in it -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-receipt me-1"></i>New charges</div>
                <div class="itflow-billing-stat-value"><?= $count['new'] ?></div>
                <div class="itflow-billing-stat-note"><?= $count['matched'] ?> matched to a vendor</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-check-double me-1"></i>Already in</div>
                <div class="itflow-billing-stat-value"><?= $count['imported'] + $count['entered'] ?></div>
                <div class="itflow-billing-stat-note"><?= $count['imported'] ?> imported before &middot; <?= $count['entered'] ?> look entered</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-arrow-down me-1"></i>Money in</div>
                <div class="itflow-billing-stat-value"><?= $count['money_in'] ?></div>
                <div class="itflow-billing-stat-note">Payments and refunds - left out<?php if ($count['money_in']) { ?> &middot; <a href="post.php?income_import_from_statement&amp;csrf_token=<?= $csrf ?>">Bring them in as income</a><?php } ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-columns me-1"></i>Columns</div>
                <div class="itflow-billing-stat-value fs-6 fw-normal mt-2"><?= $import['remembered'] ? 'As last time' : 'As you set them' ?></div>
                <div class="itflow-billing-stat-note"><a href="expense_import.php?step=columns">Change them</a><?= $count['problem'] ? ' &middot; <span class="text-danger-emphasis">' . $count['problem'] . " can't be read</span>" : '' ?></div>
            </div>
        </div>
    </div>

    <form action="post.php" method="post" autocomplete="off" id="expenseImportReview">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="picks" value="" data-import-picks>

        <div class="card">
            <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-tasks text-secondary me-2"></i>The lines</h3>
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                    <select class="form-select" data-import-bulk-category aria-label="Category for the ticked lines">
                        <option value="">- Category for the ticked -</option>
                        <?= $category_options ?>
                    </select>
                </div>
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select" data-import-bulk-client aria-label="Client for the ticked lines">
                        <option value="">- Client for the ticked -</option>
                        <option value="0">Not for a client</option>
                        <?= $client_options ?>
                    </select>
                </div>
                <?php if ($count['money_in'] || $count['imported'] || $count['entered']) { ?>
                    <div class="form-check form-switch mb-0 ms-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="importShowAll" data-import-show-all>
                        <label class="form-check-label small" for="importShowAll">Show money in and lines already in</label>
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
                        <th>Vendor</th>
                        <th>Category</th>
                        <th>Client</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $line) {
                        $n = intval($line['n']);
                        $state = $line['state'];
                        $take = $state === 'new' && $line['amount'] > 0;
                        $fixed = in_array($state, ['imported', 'problem'], true);
                        $vendor_value = $line['vendor_id'] ? (string) $line['vendor_id'] : 'new'; ?>
                        <tr data-import-row data-n="<?= $n ?>" data-key="<?= escapeHtml($line['key']) ?>" data-amount="<?= $line['amount'] === null ? 0 : $line['amount'] ?>" data-state="<?= $state ?>"
                            <?= in_array($state, ['new'], true) ? '' : 'data-import-extra' ?> class="<?= $take ? '' : 'itflow-unbilled-off' ?>">
                            <td class="checkbox-column border-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" data-import-take <?= $take ? 'checked' : '' ?> <?= $fixed ? 'disabled' : '' ?> aria-label="Import this line">
                                </div>
                            </td>
                            <td class="text-nowrap small"><?= $line['date'] ? escapeHtml($line['date']) : '<span class="text-danger-emphasis">?</span>' ?></td>
                            <td class="itflow-import-text">
                                <div class="text-truncate" title="<?= escapeHtml($line['description']) ?>"><?= escapeHtml($line['description']) ?: '<span class="text-body-secondary">-</span>' ?></div>
                                <div class="small">
                                    <span class="badge rounded-pill bg-<?= $states[$state]['tone'] ?>-subtle text-<?= $states[$state]['tone'] ?>-emphasis fw-normal"><?= $states[$state]['label'] ?></span>
                                    <?php if ($state === 'imported') { ?>
                                        <a class="ajax-modal small" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($line['imported']) ?>">Open it</a>
                                    <?php } elseif ($state === 'entered') { ?>
                                        <a class="ajax-modal small" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($line['entered']) ?>"><?= escapeHtml($line['entered_label']) ?></a>
                                    <?php } elseif ($state === 'problem') { ?>
                                        <span class="small text-danger-emphasis"><?= escapeHtml($line['problem']) ?></span>
                                    <?php } elseif ($line['matched'] === 'rule') { ?>
                                        <span class="small text-body-secondary" title="Picked for this merchant before"><i class="fas fa-magic me-1"></i>as last time</span>
                                    <?php } elseif ($line['matched'] === 'name') { ?>
                                        <span class="small text-body-secondary" title="The vendor's name is in the line"><i class="fas fa-link me-1"></i>by name</span>
                                    <?php } ?>
                                    <?php if ($line['reference'] !== '') { ?><span class="small text-body-secondary ms-1">#<?= escapeHtml($line['reference']) ?></span><?php } ?>
                                </div>
                            </td>
                            <td class="text-end text-nowrap itflow-money fw-semibold <?= $line['amount'] !== null && $line['amount'] < 0 ? 'text-success-emphasis' : '' ?>"><?= $line['amount'] === null ? '-' : $money($line['amount']) ?></td>
                            <?php if ($fixed) { ?>
                                <td colspan="3" class="small text-body-secondary"><?= $state === 'imported' ? 'Brought in from a statement before - not imported again.' : 'Not imported - fix the columns if every line says this.' ?></td>
                            <?php } else { ?>
                                <td class="itflow-import-pick">
                                    <select class="form-select form-select-sm" data-import-vendor data-value="<?= escapeHtml($vendor_value) ?>" aria-label="Vendor">
                                        <option value="">- Vendor -</option>
                                        <option value="new">+ New vendor</option>
                                        <?= $vendor_options ?>
                                    </select>
                                    <input type="text" class="form-control form-control-sm mt-1" maxlength="200" value="<?= escapeHtml($line['new_vendor']) ?>" data-import-new-vendor aria-label="New vendor name" placeholder="New vendor's name">
                                </td>
                                <td class="itflow-import-pick">
                                    <select class="form-select form-select-sm" data-import-category data-value="<?= intval($line['category_id']) ?: '' ?>" aria-label="Category">
                                        <option value="">- Category -</option>
                                        <?= $category_options ?>
                                    </select>
                                </td>
                                <td class="itflow-import-pick">
                                    <select class="form-select form-select-sm" data-import-client data-value="<?= intval($line['client_id']) ?>" aria-label="Client">
                                        <option value="0">-</option>
                                        <?= $client_options ?>
                                    </select>
                                    <div class="form-check form-switch small mt-1 mb-0" data-import-billable-box>
                                        <input class="form-check-input" type="checkbox" role="switch" id="importBillable<?= $n ?>" data-import-billable <?= $line['billable'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="importBillable<?= $n ?>">Bill on</label>
                                    </div>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap align-items-center gap-2 itflow-import-footer">
                <span class="me-auto small text-body-secondary" data-import-summary></span>
                <a class="btn btn-light" href="expense_import.php?step=columns"><i class="fas fa-arrow-left me-2"></i>Columns</a>
                <button type="submit" name="expense_import_save" value="1" class="btn btn-primary text-bold" data-import-submit><i class="fas fa-file-import me-2"></i><span data-import-submit-label>Import</span></button>
            </div>
        </div>
    </form>

    <script src="/js/expense_import.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        itflowExpenseImport({
            currency: <?= json_encode($currency) ?>,
            locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>,
            defaults: <?= json_encode((object) array_map(function ($d) { return $d['category']; }, $defaults)) ?>
        });
    });
    </script>

<?php } ?>

<?php require_once "../includes/footer.php";
