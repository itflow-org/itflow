<?php

/*
 * Admin > Payment Providers - the services clients pay invoices by card
 * through (functions/payment_provider.php). Each row says whether it is on,
 * live or test (as its keys say), the account its payments go into, its card
 * limit, whether its fees are recorded as expenses, its saved cards and the
 * autopay on them, and when a card payment last came through it. Add and
 * Edit are one window (modals/payment_provider/payment_provider_edit.php);
 * turning one off, on and deleting it are admin/post/payment_provider.php.
 */

// Default Column Sortby Filter
$sort = "payment_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'payment_provider_name' => "payment_provider_name $order",
    'account_name' => "account_name $order, payment_provider_name ASC",
    'payment_provider_threshold' => "payment_provider_threshold $order, payment_provider_name ASC",
    'saved_cards' => "saved_cards $order, payment_provider_name ASC",
    'last_paid' => "last_paid IS NULL, last_paid $order, payment_provider_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'payment_provider_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS payment_provider_id, payment_provider_name, payment_provider_description, payment_provider_private_key,
        payment_provider_threshold, payment_provider_active, payment_provider_account, payment_provider_expense_vendor, payment_provider_expense_category,
        account_name, account_archived_at, vendor_name, category_name,
        COALESCE(cards.saved_cards, 0) AS saved_cards, COALESCE(cards.autopay, 0) AS autopay,
        (SELECT MAX(payment_date) FROM payments WHERE payment_archived_at IS NULL AND payment_reference LIKE CONCAT(payment_provider_name, ' - %')) AS last_paid
    FROM payment_providers
    LEFT JOIN accounts ON account_id = payment_provider_account
    LEFT JOIN vendors ON vendor_id = payment_provider_expense_vendor AND payment_provider_expense_vendor > 0
    LEFT JOIN categories ON category_id = payment_provider_expense_category AND payment_provider_expense_category > 0
    LEFT JOIN (
        SELECT saved_payment_provider_id, COUNT(DISTINCT saved_payment_id) AS saved_cards, COUNT(recurring_payment_id) AS autopay
        FROM client_saved_payment_methods LEFT JOIN recurring_payments ON recurring_payment_saved_payment_id = saved_payment_id
        GROUP BY saved_payment_provider_id
    ) cards ON cards.saved_payment_provider_id = payment_provider_id
    WHERE (payment_provider_name LIKE '%$q%' OR payment_provider_description LIKE '%$q%' OR account_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR category_name LIKE '%$q%')
    ORDER BY {$sortable_columns[$sort]}, payment_provider_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Add only while there is a provider ITFlow takes payments through that isn't set up
$set_up = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT payment_provider_name) FROM payment_providers WHERE payment_provider_name IN ('"
    . implode("', '", array_map('escapeSql', array_keys(paymentProviderKinds()))) . "')"))[0]);
$can_add = $set_up < count(paymentProviderKinds());

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$add_url = 'modals/payment_provider/payment_provider_add.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card me-2"></i>Payment Providers</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light me-1" href="saved_payment_methods.php"><i class="fas fa-fw fa-wallet"></i><span class="d-none d-lg-inline ms-2">Saved Cards</span></a>
            <?php if ($can_add) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">Add Provider</span></button>
            <?php } ?>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search payment providers..." title="Name, description, account, fee vendor or category">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('payment_provider_name', 'Provider') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('account_name', 'Payments go into') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('payment_provider_threshold', 'Card limit') ?></th>
                <th class="d-none d-lg-table-cell">Fees</th>
                <th class="d-none d-md-table-cell"><?= $sort_link('saved_cards', 'Saved cards') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('last_paid', 'Last card payment') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $provider_id = intval($row['payment_provider_id']);
                $name = (string) $row['payment_provider_name'];
                $kind = paymentProviderKinds()[$name] ?? null;
                $on = intval($row['payment_provider_active']) === 1;
                $mode = paymentProviderMode($row['payment_provider_private_key']);
                $edit_url = "modals/payment_provider/payment_provider_edit.php?id=$provider_id";
                $cards = intval($row['saved_cards']);
                $autopay = intval($row['autopay']);
                $fees_on = intval($row['payment_provider_expense_vendor']) > 0 && intval($row['payment_provider_expense_category']) > 0;
                $ties = paymentProviderTiesText(paymentProviderTies($provider_id));
                ?>
                <tr data-payment-provider-id="<?= $provider_id ?>" class="<?= $on ? '' : 'text-body-secondary' ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-sm-inline-flex itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="<?= escapeHtml($kind['icon'] ?? 'fas fa-credit-card') ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><?= escapeHtml($name) ?></a>
                                <?php if (!$on) { ?>
                                    <span class="badge text-bg-secondary ms-1" data-provider-state="off" title="Clients can't pay by card through it, and autopay with its cards is skipped">Off</span>
                                <?php } ?>
                                <?php if ($mode === 'test') { ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" data-provider-mode="test" title="Its keys are test keys - no real money moves">Test</span>
                                <?php } elseif ($mode === 'live') { ?>
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle ms-1" data-provider-mode="live">Live</span>
                                <?php } ?>
                                <?php if (trim((string) $row['payment_provider_description']) !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($row['payment_provider_description']) ?></div>
                                <?php } ?>
                                <!-- On smaller screens where its payments go comes along under the name -->
                                <div class="d-md-none small text-body-secondary"><?= $row['account_name'] !== null ? 'Into ' . escapeHtml($row['account_name']) : '' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?php if ($row['account_name'] === null) { ?>
                            <span class="text-danger-emphasis"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>No account - pick one in Edit</span>
                        <?php } else { ?>
                            <a class="text-reset" href="/agent/accounts.php"><?= escapeHtml($row['account_name']) ?></a>
                            <?php if ($row['account_archived_at']) { ?><div class="text-danger-emphasis"><i class="fas fa-fw fa-archive me-1"></i>Archived - pick another in Edit</div><?php } ?>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-nowrap"><?= escapeHtml(paymentProviderLimitText($row['payment_provider_threshold'])) ?></td>
                    <td class="d-none d-lg-table-cell small" data-provider-fees="<?= $fees_on ? 'on' : 'off' ?>">
                        <?php if ($fees_on) { ?>
                            <div><i class="fas fa-fw fa-receipt text-secondary me-1"></i>Recorded</div>
                            <div class="text-body-secondary"><?= escapeHtml(trim($row['vendor_name'] . ' · ' . $row['category_name'], ' ·')) ?></div>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not recorded</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap">
                        <?php if ($cards) { ?>
                            <a class="text-reset" href="saved_payment_methods.php?provider=<?= $provider_id ?>"><?= $cards ?> card<?= $cards === 1 ? '' : 's' ?></a>
                            <?php if ($autopay) { ?><div><a class="text-body-secondary" href="saved_payment_methods.php?provider=<?= $provider_id ?>&amp;show=autopay">Autopay on <?= $autopay ?></a></div><?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">None yet</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-body-secondary text-nowrap"<?= $row['last_paid'] ? ' title="' . escapeHtml($row['last_paid']) . '"' : '' ?>><?= $row['last_paid'] ? escapeHtml(date('M j, Y', strtotime($row['last_paid']))) : 'None yet' ?></td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if ($on) { ?>
                                    <a class="dropdown-item confirm-link" href="post.php?turn_payment_provider_off=<?= $provider_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Turn <?= escapeHtml($name) ?> off?" data-confirm-text="Clients won't be able to pay by card, and autopay with its saved cards is skipped until it is on again. Its cards are kept." data-confirm-button="Turn off">
                                        <i class="fas fa-fw fa-toggle-off text-secondary me-2"></i>Turn off
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item" href="post.php?turn_payment_provider_on=<?= $provider_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-toggle-on text-secondary me-2"></i>Turn on
                                    </a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_payment_provider=<?= $provider_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= escapeHtml($name) ?>?" data-confirm-button="Delete"
                                    data-confirm-text="<?= escapeHtml($ties !== '' ? "Its $ties go with it. To stop card payments and keep them, turn it off instead." : 'Clients will no longer be able to pay by card.') ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
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
        'thing' => 'payment providers',
        'icon' => 'fa-credit-card',
        'text' => 'Set up Stripe and clients can pay their invoices by card, save a card, and pay recurring invoices by autopay.',
        'actions' => $can_add ? [['label' => 'Add Provider', 'modal' => $add_url]] : [],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
