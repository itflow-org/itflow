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

require_once "includes/inc_all_admin.php";

$show_archived = isset($_GET['archived']);
$search_sql = $q !== '' ? "AND (tax_name LIKE '%$q%' OR tax_description LIKE '%$q%' OR tax_registration_number LIKE '%$q%')" : '';
$sql = mysqli_query(
    $mysqli,
    "SELECT tax_id FROM taxes
    WHERE tax_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . " $search_sql
    ORDER BY " . ($show_archived ? 'tax_archived_at DESC, ' : '') . "tax_name ASC, tax_percent ASC"
);
$rates = [];
while ($row = mysqli_fetch_row($sql)) {
    $rates[] = taxRow($row[0]);
}
$archived_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM taxes WHERE tax_archived_at IS NOT NULL"))[0]);
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
            <div class="card-header py-2">
                <form autocomplete="off" class="d-flex flex-wrap align-items-center gap-2">
                    <?php if ($show_archived) { ?><input type="hidden" name="archived" value="1"><?php } ?>
                    <div class="input-group input-group-sm w-auto me-auto">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml(stripslashes($q)) ?>" placeholder="Search taxes">
                        <button class="btn btn-primary" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <a class="btn btn-outline-secondary<?= $show_archived ? '' : ' active' ?>" href="tax_rates.php">Current</a>
                        <a class="btn btn-outline-secondary<?= $show_archived ? ' active' : '' ?>" href="tax_rates.php?archived=1">Archived <span class="badge text-bg-secondary ms-1"><?= $archived_count ?></span></a>
                    </div>
                </form>
            </div>

            <div class="table-responsive-sm">
                <table class="table table-borderless table-hover mb-0 align-middle">
                    <?php if ($rates) { ?>
                        <thead class="text-dark">
                        <tr>
                            <th class="ps-3">Tax</th>
                            <th>Rate</th>
                            <th>Used on</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                    <?php } ?>
                    <tbody>
                    <?php foreach ($rates as $rate) {
                        $tax_id = intval($rate['tax_id']);
                        $u = $usage[$tax_id];
                        $used = $used_text($u);
                        $replaced_by = $rate['tax_archived_at'] && intval($rate['tax_replaced_by']) ? taxRow($rate['tax_replaced_by']) : null;
                        $unused = !$used && !$u['default'] && taxUnused($tax_id); ?>
                        <tr>
                            <td class="ps-3">
                                <a class="ajax-modal fw-semibold" href="#" data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>" data-modal-size="lg"><?= escapeHtml($rate['tax_name']) ?></a>
                                <?php if ($u['default']) { ?>
                                    <span class="badge rounded-pill text-bg-primary ms-1" title="New lines get this rate unless the client or product has its own">Default</span>
                                <?php } ?>
                                <?php if ($rate['tax_description']) { ?>
                                    <div class="small text-secondary"><?= escapeHtml($rate['tax_description']) ?></div>
                                <?php } ?>
                                <?php if ($rate['tax_registration_number']) { ?>
                                    <div class="small text-secondary" title="Registration number - printed on invoices"><i class="fas fa-fw fa-id-card me-1"></i><?= escapeHtml($rate['tax_registration_number']) ?></div>
                                <?php } ?>
                                <?php if ($rate['tax_archived_at']) { ?>
                                    <div class="small text-secondary">
                                        <i class="fas fa-fw fa-archive me-1"></i><?= $replaced_by ? 'Replaced by ' . escapeHtml(taxLabel($replaced_by)) . ' on ' : 'Archived ' ?><?= date('M j, Y', strtotime($rate['tax_archived_at'])) ?>
                                    </div>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= taxPercentText($rate['tax_percent']) ?>%</span>
                                <?php if ($rate['grouped']) { ?>
                                    <div class="small text-secondary">
                                        <?php foreach ($rate['components'] as $i => $part) { ?>
                                            <?= $i ? '<span class="mx-1">+</span>' : '' ?><span class="text-nowrap"<?= $part['registration'] !== '' ? ' title="Registration number ' . escapeHtml($part['registration']) . '"' : '' ?>><?= escapeHtml($part['name']) ?> <?= taxPercentText($part['percent']) ?>%<?= $part['compound'] ? ' <em>on top</em>' : '' ?><?= $part['registration'] !== '' ? '<i class="fas fa-id-card ms-1"></i>' : '' ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </td>
                            <td class="small">
                                <?= $used ? escapeHtml(implode(', ', $used)) : '<span class="text-secondary">Not used yet</span>' ?>
                            </td>
                            <td>
                                <div class="dropdown dropstart text-center">
                                    <button class="btn btn-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>" data-modal-size="lg"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                        <?php if ($rate['tax_archived_at']) { ?>
                                            <a class="dropdown-item" href="post.php?restore_tax=<?= $tax_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                                        <?php } else { ?>
                                            <?php if (!$u['default']) { ?>
                                                <a class="dropdown-item" href="post.php?default_tax=<?= $tax_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-star me-2"></i>Make default</a>
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
            <?php if (!$rates) { ?>
                <?= emptyStateHtml([
                    'thing' => 'taxes',
                    'title' => $show_archived ? 'No archived taxes' : 'No taxes yet',
                    'text' => $show_archived ? '' : 'Sales tax, VAT or GST - one rate, or parts like a state\'s and a county\'s charged together.',
                    'icon' => 'fa-balance-scale',
                    'filtered' => $q !== '',
                    'clear_url' => 'tax_rates.php' . ($show_archived ? '?archived=1' : ''),
                    'actions' => $show_archived ? [] : [['label' => 'New Tax', 'modal' => 'modals/tax/tax_add.php', 'size' => 'lg']],
                ]) ?>
            <?php } ?>
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
                <p class="mb-0"><i class="fas fa-fw fa-user-shield me-1"></i><strong>Exempt clients.</strong> Mark a client tax exempt, with its certificate, on the client's Billing tab. Its new lines get no tax, and its invoices say why.</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
