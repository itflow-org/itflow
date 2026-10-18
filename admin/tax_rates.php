<?php

/*
 * Admin > Taxes - the rates charged on invoice and quote lines, and how new
 * lines get one.
 *
 * A rate is a single percent, or parts (state and county, GST and PST) each
 * charged on the line or on top of the parts before it. Changing the rate of
 * one already billed makes a new rate in its place (functions/tax.php:
 * taxChangeRate()), so each shows what uses it and, once archived, what
 * replaced it. A rate nothing has used can be deleted.
 */

// Default Column Sortby Filter
$sort = "tax_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// ?archived=1 is how older links asked for the archived ones
$show_archived = ($_GET['show'] ?? '') === 'archived' || !empty($_GET['archived']);

// Only these sort - an unknown column would take the page down. Used on sorts by the invoices with a line at it
$sortable_columns = [
    'tax_name' => "tax_name $order, tax_percent ASC",
    'tax_percent' => "tax_percent $order, tax_name ASC",
    'invoice_count' => "invoice_count $order, tax_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'tax_name';
}

$search = "(tax_name LIKE '%$q%' OR tax_description LIKE '%$q%' OR tax_registration_number LIKE '%$q%')";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS tax_id, COALESCE(invoices.invoice_count, 0) AS invoice_count
    FROM taxes
    LEFT JOIN (SELECT item_tax_id, COUNT(DISTINCT item_invoice_id) AS invoice_count FROM invoice_items WHERE item_tax_id > 0 GROUP BY item_tax_id) invoices ON invoices.item_tax_id = tax_id
    WHERE $search AND tax_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    ORDER BY {$sortable_columns[$sort]}, tax_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$rates = [];
while ($row = mysqli_fetch_row($sql)) {
    $rates[] = taxRow($row[0]);
}
$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(tax_archived_at IS NULL), 0), COALESCE(SUM(tax_archived_at IS NOT NULL), 0) FROM taxes WHERE $search"));
$usage = taxUsageAll();
$tax_settings = taxSettings();
$active_taxes = billingTaxes();

// "4 invoices, 1 quote" - what has a line at a rate, and what picks it for new ones
$used_text = function (array $u) {
    $words = ['invoices' => ['invoice', 'invoices'], 'quotes' => ['quote', 'quotes'], 'recurring' => ['recurring invoice', 'recurring invoices'],
        'products' => ['product', 'products'], 'clients' => ['client', 'clients']];
    $parts = [];
    foreach ($words as $key => [$one, $many]) {
        if ($u[$key]) {
            $parts[] = number_format($u[$key]) . ' ' . ($u[$key] === 1 ? $one : $many);
        }
    }
    return $parts;
};

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

?>

<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header bg-dark py-2">
                <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale me-2"></i>Taxes</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/tax/tax_add.php" data-modal-size="lg"><i class="fas fa-plus me-2"></i>New Tax</button>
                </div>
            </div>
            <div class="card-header py-3">
                <form autocomplete="off">
                    <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-7">
                            <div class="input-group">
                                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search taxes..." title="Name, description or registration number">
                                <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </form>
                <nav class="nav nav-pills itflow-asset-tabs mt-2">
                    <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '']))) ?>"><i class="fas fa-fw fa-balance-scale me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
                    <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => 'archived']))) ?>" title="Kept on what was billed with them, not offered for new lines"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
                </nav>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th class="ps-3"><?= $sort_link('tax_name', 'Tax') ?></th>
                        <th><?= $sort_link('tax_percent', 'Rate') ?></th>
                        <th class="d-none d-sm-table-cell"><?= $sort_link('invoice_count', 'Used on') ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rates as $rate) {
                        $tax_id = intval($rate['tax_id']);
                        $u = $usage[$tax_id];
                        $used = $used_text($u);
                        $replaced_by = $rate['tax_archived_at'] && intval($rate['tax_replaced_by']) ? taxRow($rate['tax_replaced_by']) : null;
                        $unused = !$used && !$u['default'] && taxUnused($tax_id); ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-balance-scale"></i></span>
                                    <div class="min-w-0">
                                        <a class="ajax-modal fw-semibold text-body" href="#" data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>" data-modal-size="lg"><?= escapeHtml($rate['tax_name']) ?></a>
                                        <?php if ($u['default']) { ?>
                                            <span class="badge rounded-pill text-bg-primary ms-1" title="New lines get this rate unless the client or product has its own">Default</span>
                                        <?php } ?>
                                        <?php if ($rate['tax_description']) { ?>
                                            <div class="small text-body-secondary"><?= escapeHtml($rate['tax_description']) ?></div>
                                        <?php } ?>
                                        <?php if ($rate['tax_registration_number']) { ?>
                                            <div class="small text-body-secondary" title="Registration number - printed on invoices"><i class="fas fa-fw fa-id-card me-1"></i><?= escapeHtml($rate['tax_registration_number']) ?></div>
                                        <?php } ?>
                                        <?php if ($rate['tax_archived_at']) { ?>
                                            <div class="small text-body-secondary">
                                                <i class="fas fa-fw fa-archive me-1"></i><?= $replaced_by ? 'Replaced by ' . escapeHtml(taxLabel($replaced_by)) . ' on ' : 'Archived ' ?><?= date('M j, Y', strtotime($rate['tax_archived_at'])) ?>
                                            </div>
                                        <?php } ?>
                                        <!-- On a phone what uses it comes along under the name -->
                                        <div class="d-sm-none small text-body-secondary mt-1"><?= $used ? escapeHtml(implode(', ', $used)) : 'Not used yet' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= taxPercentText($rate['tax_percent']) ?>%</span>
                                <?php if ($rate['grouped']) { ?>
                                    <div class="small text-body-secondary">
                                        <?php foreach ($rate['components'] as $i => $part) { ?>
                                            <?= $i ? '<span class="mx-1">+</span>' : '' ?><span class="text-nowrap"<?= $part['registration'] !== '' ? ' title="Registration number ' . escapeHtml($part['registration']) . '"' : '' ?>><?= escapeHtml($part['name']) ?> <?= taxPercentText($part['percent']) ?>%<?= $part['compound'] ? ' <em>on top</em>' : '' ?><?= $part['registration'] !== '' ? '<i class="fas fa-id-card ms-1"></i>' : '' ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </td>
                            <td class="d-none d-sm-table-cell small">
                                <?= $used ? escapeHtml(implode(', ', $used)) : '<span class="text-body-secondary">Not used yet</span>' ?>
                            </td>
                            <td class="text-end">
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>" data-modal-size="lg"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                        <?php if ($rate['tax_archived_at']) { ?>
                                            <a class="dropdown-item" href="post.php?restore_tax=<?= $tax_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore</a>
                                        <?php } else { ?>
                                            <?php if (!$u['default']) { ?>
                                                <a class="dropdown-item" href="post.php?default_tax=<?= $tax_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-star text-secondary me-2"></i>Make default</a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/tax/tax_archive.php?id=<?= $tax_id ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                        <?php } ?>
                                        <?php if ($unused) { ?>
                                            <?php if ($rate['tax_archived_at']) { ?><div class="dropdown-divider"></div><?php } ?>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_tax=<?= $tax_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php
            $empty_state = [
                'thing' => 'taxes',
                'icon' => 'fa-balance-scale',
                'filtered' => $q !== '',
                'archived' => $show_archived,
                'views' => ['show'],
                'text' => 'Sales tax, VAT or GST - one rate, or parts like a state\'s and a county\'s charged together.',
                'actions' => [['label' => 'New Tax', 'modal' => 'modals/tax/tax_add.php', 'size' => 'lg']],
            ];
            require_once "../includes/filter_footer.php";
            ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card card-dark">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-sliders-h me-2"></i>How lines are taxed</h3>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Default rate</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-star"></i></span>
                            <select class="form-select" name="default_tax_id">
                                <option value="0">No default</option>
                                <?php foreach ($active_taxes as $id => $tax) { ?>
                                    <option value="<?= intval($id) ?>"<?= intval($id) === $tax_settings['default_id'] ? ' selected' : '' ?>><?= escapeHtml(taxLabel($tax)) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <small class="text-secondary">For new lines when the client has no rate of its own - after the product's rate, for a product's line. Products set to no tax stay untaxed, and tax exempt clients get none.</small>
                    </div>

                    <label class="form-label">Also tax</label>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="tax_time" value="1" id="taxTime"<?= $tax_settings['time'] ? ' checked' : '' ?>>
                        <label class="form-check-label" for="taxTime">Billed time</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="tax_trips" value="1" id="taxTrips"<?= $tax_settings['trip'] ? ' checked' : '' ?>>
                        <label class="form-check-label" for="taxTrips">Trips</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="tax_expenses" value="1" id="taxExpenses"<?= $tax_settings['expense'] ? ' checked' : '' ?>>
                        <label class="form-check-label" for="taxExpenses">Expenses billed to clients</label>
                    </div>
                    <small class="text-secondary d-block mb-3">Lines added from tickets, Unbilled Time, trips and expenses get the client's rate when these are on, and no tax when off. Any line can still be changed.</small>

                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="tax_discount_before" value="1" id="taxDiscountBefore"<?= $tax_settings['discount_before'] ? ' checked' : '' ?>>
                        <label class="form-check-label" for="taxDiscountBefore">Take discounts off before tax</label>
                    </div>
                    <small class="text-secondary d-block">Tax is worked out on what the client pays after the discount, shared across the lines. Off, tax is on the full price. Paid, cancelled and answered invoices and quotes never change; the rest follow the next time they are edited.</small>
                </div>
                <div class="card-footer">
                    <button type="submit" name="edit_tax_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-body small text-secondary">
                <p class="mb-2"><i class="fas fa-fw fa-history me-1"></i><strong>Changing a rate.</strong> Once a rate is on an invoice or quote, saving a new percent or parts makes a new rate with the same name. What was already billed keeps the old rate; products, clients, the default and recurring invoices move to the new one.</p>
                <p class="mb-2"><i class="fas fa-fw fa-user-shield me-1"></i><strong>Exempt clients.</strong> Mark a client tax exempt, with its certificate, on the client's Billing tab. Its new lines get no tax, and its invoices say why.</p>
                <p class="mb-0"><i class="fas fa-fw fa-percent me-1"></i><strong>Filing.</strong> The <a href="/agent/reports/tax_summary.php">Tax Collected</a> report adds up each part by month or quarter, invoiced or paid, with exempt sales and the tax paid on expenses.</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
