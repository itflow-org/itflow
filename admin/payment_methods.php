<?php

/*
 * Admin > Payment Methods - how money comes in and goes out: Cash, Check,
 * ACH, the company card. Each row says what it is for (money in, money out
 * or both - the pickers offer only the ones that fit), what it has been used
 * on and when last, and whether it is the default for payments or expenses.
 * Methods that records were paid by but that aren't in the list - from an
 * import or an older version - are offered for adding. Archived ones leave
 * the pickers and keep their records. The work is in
 * functions/payment_method.php; the handlers are admin/post/payment_method.php.
 */

// Default Column Sortby Filter
$sort = "payment_method_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$views = [
    '' => ['label' => 'In use', 'icon' => 'fa-credit-card', 'title' => 'Offered in the pickers'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'title' => 'Kept for the records that used them, but not offered'],
];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';

// Every method that matches, then sorted and paged here - their use is counted across several tables
$search = mb_strtolower(trim(stripslashes((string) ($q ?? ''))));
$usage = paymentMethodUsage();
$defaults = paymentMethodDefaults();
$rows = [];
$view_counts = ['' => 0, 'archived' => 0];
foreach (paymentMethodsAll() as $id => $row) {
    if ($search !== '' && mb_strpos(mb_strtolower($row['payment_method_name'] . ' ' . $row['payment_method_description']), $search) === false) {
        continue;
    }
    $view = empty($row['payment_method_archived_at']) ? '' : 'archived';
    $view_counts[$view]++;
    if ($view !== $show) {
        continue;
    }
    $row['usage'] = paymentMethodUsageFor($row['payment_method_name']);
    $rows[] = $row;
}

$sortable_columns = [
    'payment_method_name' => function ($row) { return mb_strtolower($row['payment_method_name']); },
    'payment_method_use' => function ($row) { return intval($row['payment_method_use']) . mb_strtolower($row['payment_method_name']); },
    'used' => function ($row) { return $row['usage']['total']; },
    'last_used' => function ($row) { return (string) $row['usage']['last']; },
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'payment_method_name';
}
$key = $sortable_columns[$sort];
usort($rows, function ($a, $b) use ($key, $order) {
    $cmp = $key($a) <=> $key($b);
    if ($cmp === 0) {
        $cmp = strcasecmp($a['payment_method_name'], $b['payment_method_name']);
    }
    return $order === 'DESC' ? -$cmp : $cmp;
});
$num_rows = [count($rows)];
$rows = array_slice($rows, $record_from, $record_to);

$missing = $show === '' && $search === '' ? paymentMethodsNotSetUp() : [];

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($view) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $view]));
};
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card me-2"></i>Payment Methods</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/payment_method/payment_method_add.php">
                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Method</span>
            </button>
        </div>
    </div>

    <?php if ($missing) { ?>
        <div class="alert alert-warning rounded-0 border-0 border-bottom mb-0 d-flex flex-wrap align-items-center gap-2" data-payment-methods-missing>
            <i class="fas fa-fw fa-exclamation-triangle"></i>
            <div class="me-auto min-w-0">
                <strong>Used, but not in the list:</strong>
                <?= implode(', ', array_map(function ($name, $missing_usage) {
                    return '<span title="' . escapeHtml(paymentMethodUsageText($missing_usage)) . '">' . escapeHtml($name) . ' <span class="text-body-secondary">(' . number_format($missing_usage['total']) . ')</span></span>';
                }, array_keys($missing), $missing)) ?>
                <div class="small">Records say they were paid this way - from an import or an older version - but <?= count($missing) === 1 ? 'it isn\'t' : 'they aren\'t' ?> offered in the pickers. Add <?= count($missing) === 1 ? 'it' : 'them' ?> to pick <?= count($missing) === 1 ? 'it' : 'them' ?>, and to merge or rename <?= count($missing) === 1 ? 'it' : 'them' ?> here.</div>
            </div>
            <form action="post.php" method="post" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <?php foreach (array_keys($missing) as $name) { ?>
                    <input type="hidden" name="names[]" value="<?= escapeHtml($name) ?>">
                <?php } ?>
                <button type="submit" name="add_payment_method_names" class="btn btn-sm btn-warning"><i class="fas fa-plus me-1"></i>Add <?= count($missing) === 1 ? 'it' : 'them all' ?></button>
            </form>
        </div>
    <?php } ?>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search payment methods..." title="Name or description">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <?php foreach ($views as $view_key => $view) { ?>
                <a class="nav-link <?= $show === (string) $view_key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($view_key)) ?>" title="<?= escapeHtml($view['title']) ?>"><i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= intval($view_counts[$view_key]) ?></span></a>
            <?php } ?>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('payment_method_name', 'Method') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('payment_method_use', 'For') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('used', 'Used on') ?></th>
                <th class="d-none d-sm-table-cell"><?= $sort_link('last_used', 'Last used') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $payment_method_id = intval($row['payment_method_id']);
                $name = escapeHtml($row['payment_method_name']);
                $description = trim((string) $row['payment_method_description']);
                $use = intval($row['payment_method_use']);
                $use_info = PAYMENT_METHOD_USES[$use] ?? PAYMENT_METHOD_USES[0];
                $archived = !empty($row['payment_method_archived_at']);
                $online = paymentMethodIsOnline($row['payment_method_name']);
                $is_default = paymentMethodDefaultFor($row['payment_method_name'], $defaults);
                $used = $row['usage'];
                $used_text = paymentMethodUsageText($used);
                $records_url = '/agent/transactions.php?' . http_build_query(['method' => $row['payment_method_name']]);
                $edit_url = "modals/payment_method/payment_method_edit.php?id=$payment_method_id";
                $use_html = '<span class="text-body-secondary text-nowrap" title="' . escapeHtml($use_info['about']) . '"><i class="fas fa-fw ' . $use_info['icon'] . ' me-1"></i>' . escapeHtml($use_info['short']) . '</span>';
                $used_html = $used['total'] ? '<a href="' . escapeHtml($records_url) . '" class="text-reset" title="See them in Transactions">' . escapeHtml($used_text) . '</a>' : '<span class="text-body-tertiary">Not used yet</span>';
                ?>
                <tr data-payment-method-id="<?= $payment_method_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw <?= $use_info['icon'] === 'fa-exchange-alt' ? 'fa-credit-card' : $use_info['icon'] ?>"></i></span>
                            <div class="min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <a class="ajax-modal fw-semibold text-body" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                                    <?php foreach ($is_default as $way) { ?>
                                        <span class="badge rounded-pill text-bg-light border fw-normal" title="Picked to start with on a new <?= $way === 'in' ? 'payment' : 'expense' ?> - Admin > Settings > Defaults"><i class="fas fa-fw fa-star text-warning me-1"></i>Default for <?= $way === 'in' ? 'payments' : 'expenses' ?></span>
                                    <?php } ?>
                                    <?php if ($online) { ?>
                                        <span class="badge rounded-pill text-bg-light border fw-normal" title="Card payments clients make online are recorded as <?= escapeHtml(PAYMENT_METHOD_ONLINE) ?>"><i class="fas fa-fw fa-globe text-secondary me-1"></i>Online payments</span>
                                    <?php } ?>
                                </div>
                                <?php if ($description !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($description) ?></div>
                                <?php } ?>
                                <?php if ($archived) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['payment_method_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens what it is for and what it is used on come along under the name -->
                                <div class="d-lg-none small mt-1 d-flex flex-wrap gap-3">
                                    <span class="d-md-none"><?= $use_html ?></span>
                                    <span class="text-body-secondary"><?= $used_html ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= $use_html ?></td>
                    <td class="d-none d-lg-table-cell small"><?= $used_html ?></td>
                    <td class="d-none d-sm-table-cell text-nowrap text-body-secondary"><?= $used['last'] ? escapeHtml(date('M j, Y', strtotime($used['last']))) : '-' ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if ($used['total']) { ?>
                                    <a class="dropdown-item" href="<?= escapeHtml($records_url) ?>">
                                        <i class="fas fa-fw fa-exchange-alt text-secondary me-2"></i>See its transactions
                                    </a>
                                <?php } ?>
                                <?php if (!$archived) {
                                    foreach (['in' => 'payments', 'out' => 'expenses'] as $way => $label) {
                                        if (!in_array($way, $is_default, true) && !($way === 'in' && $use === 2) && !($way === 'out' && $use === 1)) { ?>
                                            <a class="dropdown-item" href="post.php?default_payment_method=<?= $payment_method_id ?>&way=<?= $way ?>&csrf_token=<?= $csrf ?>" title="Picked to start with on a new <?= $way === 'in' ? 'payment' : 'expense' ?>">
                                                <i class="fas fa-fw fa-star text-secondary me-2"></i>Make the default for <?= $label ?>
                                            </a>
                                        <?php }
                                    }
                                } ?>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <a class="dropdown-item" href="post.php?restore_payment_method=<?= $payment_method_id ?>&csrf_token=<?= $csrf ?>" title="Back in the pickers">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_payment_method=<?= $payment_method_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Archive <?= $name ?>?" data-confirm-text="It can't be picked any more<?= $is_default ? ', and stops being a default' : '' ?>. The records that used it keep it." data-confirm-button="Archive">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/payment_method/payment_method_delete.php?id=<?= $payment_method_id ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i><?= $used['total'] ? 'Merge into another' : 'Delete' ?>
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
        'thing' => 'payment methods',
        'icon' => 'fa-credit-card',
        'filtered' => $q !== '',
        'archived' => $show === 'archived',
        'views' => ['show'],
        'text' => 'How clients pay you and how you pay for things - Check, ACH, Cash, the company card.',
        'actions' => [['label' => 'New Method', 'modal' => 'modals/payment_method/payment_method_add.php']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
