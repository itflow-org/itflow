<?php
/*
 * Which column of a bank or card statement is which - the date, its format,
 * a reference, the description and more of it, and the amount in one column
 * (with which way round it is) or in money out and money in columns. The
 * columns step of Import Statement (expenses) and Import Deposits (income),
 * which keep the same columns on the account, so a statement read on one
 * side reads the same on the other. Set before including:
 *
 *   $import           the statement in the session (header, rows, guess)
 *   $account          the account it is for (paymentAccounts() row), or null
 *   $columns_form_id  the form's id
 *   $columns_submit   the submit button's name, the handler that saves them
 *   $columns_side     expense or income, for how the sign is put
 */
isset($mysqli) || die("Direct file access is not allowed");

$guess = $import['guess'];
$header = $import['header'];
$two = $guess['amount'] === null && ($guess['debit'] !== null || $guess['credit'] !== null);
$column_select = function ($name, $selected, $optional, $attrs = '') use ($header) {
    $html = '<select class="form-select" name="' . $name . '" ' . $attrs . '>';
    $html .= '<option value="">' . ($optional ? '- None -' : '- Pick the column -') . '</option>';
    foreach ($header as $c => $label) {
        $html .= '<option value="' . $c . '"' . ($selected !== null && intval($selected) === $c ? ' selected' : '') . '>' . escapeHtml($label) . '</option>';
    }
    return $html . '</select>';
};
// The sign is kept as which way round a charge is; the income side asks it from the deposit's side
$sign_options = $columns_side === 'income'
    ? ['negative' => 'Positive - 25.00 came in', 'positive' => 'Negative - -25.00 came in']
    : ['positive' => 'Positive - 25.00 is spent', 'negative' => 'Negative - -25.00 is spent'];
?>

<div class="card mb-3">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-columns me-2"></i>Which column is which <span class="fw-normal text-white-50 ms-1"><?= count($import['rows']) ?> lines</span></h3>
    </div>
    <form action="post.php" method="post" autocomplete="off" id="<?= escapeHtml($columns_form_id) ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <?= $column_select('date', $guess['date'], false, 'required') ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date format</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-alt"></i></span>
                        <select class="form-select" name="date_format">
                            <?php foreach (expenseImportDateFormats() as $format => $example) { ?>
                                <option value="<?= escapeHtml($format) ?>" <?= $guess['date_format'] === $format ? 'selected' : '' ?>><?= escapeHtml($example) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reference</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                        <?= $column_select('reference', $guess['reference'], true) ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <?= $column_select('description', $guess['description'], false, 'required') ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">More description</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                        <?= $column_select('memo', $guess['memo'], true) ?>
                    </div>
                    <div class="form-text">A memo or notes column, added after the description.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">The amounts are in</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-coins"></i></span>
                        <select class="form-select" name="amounts" data-import-amounts>
                            <option value="one" <?= $two ? '' : 'selected' ?>>One column</option>
                            <option value="two" <?= $two ? 'selected' : '' ?>>Money out and money in columns</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" data-import-amounts-for="one">
                    <label class="form-label">Amount <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        <?= $column_select('amount', $guess['amount'], false) ?>
                    </div>
                </div>
                <div class="col-md-4" data-import-amounts-for="one">
                    <label class="form-label"><?= $columns_side === 'income' ? 'Money in is' : 'A charge is' ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                        <select class="form-select" name="sign">
                            <?php foreach ($sign_options as $value => $label) { ?>
                                <option value="<?= $value ?>" <?= $guess['sign'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" data-import-amounts-for="two">
                    <label class="form-label">Money out</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-arrow-up"></i></span>
                        <?= $column_select('debit', $guess['debit'], true) ?>
                    </div>
                </div>
                <div class="col-md-4" data-import-amounts-for="two">
                    <label class="form-label">Money in</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-arrow-down"></i></span>
                        <?= $column_select('credit', $guess['credit'], true) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- The statement as it reads -->
        <div class="table-responsive border-top">
            <table class="table table-sm table-striped small mb-0">
                <thead class="table-light text-nowrap">
                <tr>
                    <?php foreach ($header as $c => $label) { ?>
                        <th data-import-column="<?= $c ?>"><?= escapeHtml($label) ?></th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($import['rows'], 0, 8) as $cells) { ?>
                    <tr>
                        <?php foreach ($cells as $c => $cell) { ?>
                            <td class="text-nowrap" data-import-column="<?= $c ?>"><?= escapeHtml(truncate($cell, 60)) ?></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" name="<?= escapeHtml($columns_submit) ?>" class="btn btn-primary text-bold"><i class="fas fa-arrow-right me-2"></i>Review the Lines</button>
            <span class="small text-body-secondary">The first <?= min(8, count($import['rows'])) ?> of <?= count($import['rows']) ?> lines. These columns are kept for the next statement of <?= escapeHtml($account['account_name'] ?? 'this account') ?>, for expenses and deposits alike.</span>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById(<?= json_encode($columns_form_id) ?>);
    var amounts = form.querySelector('[data-import-amounts]');
    var labels = { date: 'Date', description: 'Description', memo: 'More', amount: 'Amount', debit: 'Out', credit: 'In', reference: 'Reference' };
    function update() {
        form.querySelectorAll('[data-import-amounts-for]').forEach(function (el) {
            el.hidden = el.getAttribute('data-import-amounts-for') !== amounts.value;
            el.querySelectorAll('select').forEach(function (select) {
                select.disabled = el.hidden;
            });
        });
        // Shade the columns picked, and say what each is
        var picked = {};
        Object.keys(labels).forEach(function (field) {
            var select = form.querySelector('select[name="' + field + '"]');
            if (select && !select.disabled && select.value !== '') {
                picked[select.value] = labels[field];
            }
        });
        form.querySelectorAll('[data-import-column]').forEach(function (cell) {
            var name = picked[cell.getAttribute('data-import-column')];
            cell.classList.toggle('table-primary', !!name);
            if (cell.tagName === 'TH') {
                var tag = cell.querySelector('.badge');
                if (!tag) {
                    tag = document.createElement('span');
                    tag.className = 'badge text-bg-primary fw-normal ms-1';
                    cell.appendChild(tag);
                }
                tag.textContent = name || '';
                tag.hidden = !name;
            }
        });
    }
    form.addEventListener('change', update);
    update();
})();
</script>
