<?php

/*
 * Recurring Invoice - an agreement billed every month, quarter or year.
 *
 * The header says whether it is running and when it next bills - and,
 * when a run was missed, whether the nightly run still bills it or it is
 * too far behind and needs Invoice Now or moving on. The template is on the left as
 * the invoices it makes will look, its lines edited in place. On the
 * right: the schedule (what it brings in a month, the period the next
 * invoice covers and the dates after, whether invoices go out by
 * themselves), its per-user and per-device lines against what the client
 * has now, the card it charges, the invoices it has made and whether they
 * were paid, and its history.
 */

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
}

// Perms
enforceUserPermission('module_sales');

$recurring_invoice_id = intval($_GET['recurring_invoice_id'] ?? 0);
$recurring = billingRow('recurring', $recurring_invoice_id);

if (!$recurring || ($client_url && intval($recurring['client_id']) !== intval($client_id))) {
    billingNotFound('recurring', $client_url);
    require_once "../includes/footer.php";
    exit;
}

$client_id = intval($recurring['client_id']);
enforceClientAccess();

$number = billingNumber('recurring', $recurring);
$active = intval($recurring['recurring_invoice_status']) === 1;
$frequency = (string) $recurring['recurring_invoice_frequency'];
$frequency_label = recurringInvoiceFrequencies()[$frequency] ?? ucfirst($frequency);
$currency = (string) $recurring['recurring_invoice_currency_code'];
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$items = billingItems('recurring', $recurring_invoice_id);
$totals = billingTotals('recurring', $recurring, $items);
$history = billingHistory('recurring', $recurring_invoice_id);
$generated = recurringInvoiceGenerated($recurring_invoice_id, 8);
$generated_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM invoices WHERE invoice_recurring_invoice_id = $recurring_invoice_id"))[0]);
$upcoming = $active ? recurringInvoiceUpcoming($recurring, 4) : [];
$late = $active && $recurring['recurring_invoice_next_date'] < date('Y-m-d');
$late_days = $late ? intval(round((strtotime(date('Y-m-d')) - strtotime($recurring['recurring_invoice_next_date'])) / 86400)) : 0;
$period_word = recurringInvoicePeriodWord($frequency);
$next_period = recurringInvoicePeriod($recurring, $recurring['recurring_invoice_next_date']);
$after_next = recurringInvoiceAfter($recurring, $recurring['recurring_invoice_next_date']);
$seat_lines = recurringSeatLines($recurring_invoice_id, $client_id);
$seat_drift = count(array_filter($seat_lines, function ($line) { return $line['seat_drift']; }));
$seat_sources = recurringSeatSources();
$auto_send = intval($recurring['recurring_invoice_auto_send']) === 1;
$email_notify = intval($recurring['recurring_invoice_email_notify']) === 1;

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;
$csrf = $_SESSION['csrf_token'];

// Cards on file the client could be charged on
$saved_methods = [];
$sql = mysqli_query($mysqli, "SELECT saved_payment_id, saved_payment_description, payment_provider_name, payment_provider_active FROM client_saved_payment_methods
    LEFT JOIN payment_providers ON payment_provider_id = saved_payment_provider_id
    WHERE saved_payment_client_id = $client_id ORDER BY saved_payment_description ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $saved_methods[intval($row['saved_payment_id'])] = $row;
}
$autopay_id = intval($recurring['recurring_payment_saved_payment_id'] ?? 0);

// Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
$tab_title = $recurring['client_name'];
$page_title = $number;

$paper = [
    'type' => 'recurring',
    'row' => $recurring,
    'items' => $items,
    'totals' => $totals,
    'edit' => $can_edit,
    'notes_edit' => $can_edit,
    'taxes' => $can_edit ? billingTaxes($recurring['recurring_invoice_created_at']) : [],
    'logo_url' => billingCompany()['logo_url'],
    'csrf' => $csrf,
];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($recurring['client_name']) ?></a></li>
    <li class="breadcrumb-item"><a href="recurring_invoices.php?client_id=<?= $client_id ?>">Recurring Invoices</a></li>
    <li class="breadcrumb-item active"><?= escapeHtml($number) ?></li>
</ol>

<!-- What it is, whether it is running, and when it bills next -->
<div class="card mb-3 d-print-none">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?= billingTile('recurring', 'lg') ?>

        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span>Recurring Invoice <?= escapeHtml($number) ?></span>
                <span class="fs-6"><span class="badge rounded-pill text-bg-<?= $active ? 'success' : 'secondary' ?>"><?= $active ? 'Active' : 'Paused' ?></span></span>
                <?php if ($active) { ?><span class="fs-6"><?= recurringInvoiceNextPill($recurring) ?></span><?php } ?>
            </h1>
            <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                <span><i class="fas fa-fw fa-building me-1"></i><a class="text-reset" href="recurring_invoices.php?client_id=<?= $client_id ?>"><?= escapeHtml($recurring['client_name']) ?></a></span>
                <?php if (trim((string) $recurring['recurring_invoice_scope']) !== '') { ?><span><i class="fas fa-fw fa-comment me-1"></i><?= escapeHtml($recurring['recurring_invoice_scope']) ?></span><?php } ?>
                <?php if ($recurring['category_name']) { ?><span><i class="fas fa-fw fa-tag me-1"></i><?= escapeHtml($recurring['category_name']) ?></span><?php } ?>
                <span><i class="fas fa-fw fa-sync-alt me-1"></i><?= escapeHtml($frequency_label) ?></span>
                <?php if (intval($recurring['recurring_invoice_quote_id'] ?? 0) && ($from_quote = billingRow('quote', intval($recurring['recurring_invoice_quote_id'])))) { ?>
                    <span><i class="fas fa-fw fa-comment-dollar me-1"></i>From <a class="text-reset" href="quote.php?quote_id=<?= intval($from_quote['quote_id']) ?>"><?= escapeHtml(billingNumber('quote', $from_quote)) ?></a></span>
                <?php } ?>
                <?php if ($autopay_id && isset($saved_methods[$autopay_id])) { ?><span><i class="fas fa-fw fa-wallet me-1"></i>Autopay</span><?php } ?>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($can_edit) { ?>
                <a class="btn btn-primary confirm-link" href="post.php?force_recurring=<?= $recurring_invoice_id ?>&run=<?= escapeHtml(urlencode($recurring['recurring_invoice_next_date'])) ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-title="Make an invoice from <?= escapeHtml($number) ?> now?"
                    data-confirm-text="The invoice for the run due <?= escapeHtml($recurring['recurring_invoice_next_date']) ?> (<?= escapeHtml($next_period['{period}']) ?>) is made now, dated today<?= $auto_send && $email_notify && !empty($config_recurring_auto_send_invoice) && !empty($config_smtp_provider) ? ', and emailed' : ', as a draft to check' ?>. The next one moves to <?= escapeHtml($after_next) ?>."
                    data-confirm-button="Invoice Now">
                    <i class="fas fa-bolt me-2"></i>Invoice Now
                </a>
                <?php if ($active) { ?>
                    <a class="btn btn-outline-secondary confirm-link" href="post.php?recurring_invoice_set_status=<?= $recurring_invoice_id ?>&status=0&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Pause <?= escapeHtml($number) ?>?" data-confirm-text="No invoices are made from it until it is resumed." data-confirm-button="Pause">
                        <i class="fas fa-pause me-2"></i>Pause
                    </a>
                <?php } else { ?>
                    <a class="btn btn-outline-success" href="post.php?recurring_invoice_set_status=<?= $recurring_invoice_id ?>&status=1&csrf_token=<?= $csrf ?>">
                        <i class="fas fa-play me-2"></i>Resume
                    </a>
                <?php } ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/recurring_invoice/recurring_invoice_edit.php?id=<?= $recurring_invoice_id ?>">
                    <i class="fas fa-edit me-2"></i>Edit
                </button>
            <?php } ?>
            <div class="dropdown dropstart">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="#" onclick="window.print(); return false;">
                        <i class="fas fa-fw fa-print text-secondary me-2"></i>Print
                    </a>
                    <a class="dropdown-item" href="invoices.php?client_id=<?= $client_id ?>&recurring_invoice_id=<?= $recurring_invoice_id ?>">
                        <i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Invoices Made <span class="text-body-secondary">(<?= $generated_count ?>)</span>
                    </a>
                    <?php if ($can_edit && $auto_send) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="post.php?recurring_invoice_email_notify=<?= $email_notify ? 0 : 1 ?>&recurring_invoice_id=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>">
                            <i class="fas fa-fw <?= $email_notify ? 'fa-bell-slash' : 'fa-bell' ?> text-secondary me-2"></i><?= $email_notify ? "Don't email the invoices" : 'Email the invoices' ?>
                        </a>
                    <?php } ?>
                    <?php if ($can_delete) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_invoice=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>"
                            data-confirm-text="<?= escapeHtml($number) ?> goes for good, with its lines, autopay and history. The invoices it made stay.">
                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($late) { ?>
    <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2 d-print-none">
        <i class="fas fa-exclamation-triangle"></i>
        <?php if ($late_days <= RECURRING_INVOICE_GRACE_DAYS) { ?>
            <span class="me-auto">Its run on <?= escapeHtml($recurring['recurring_invoice_next_date']) ?> was missed - the next nightly run bills it. Invoice Now bills it straight away.</span>
        <?php } else { ?>
            <span class="me-auto">Its next date, <?= escapeHtml($recurring['recurring_invoice_next_date']) ?>, is <?= $late_days ?> days gone - too far behind to bill by itself. Use Invoice Now for each run it missed, or move it on without billing them.</span>
        <?php } ?>
        <?php if ($can_edit) { ?>
            <a class="btn btn-sm btn-danger confirm-link" href="post.php?recurring_invoice_catch_up=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>"
                data-confirm-title="Skip the missed runs?" data-confirm-text="The next date moves on to <?= escapeHtml(recurringInvoiceCatchUp($recurring)) ?>. Nothing is billed for the runs it missed - use Invoice Now for those." data-confirm-button="Move on">
                <i class="fas fa-forward me-1"></i>Move on to <?= escapeHtml(recurringInvoiceCatchUp($recurring)) ?>
            </a>
        <?php } ?>
    </div>
<?php } ?>

<div class="row g-3">

    <!-- The invoice it makes -->
    <div class="col-xl-8 itflow-paper-col">
        <?php require "../includes/billing_paper.php"; ?>
    </div>

    <div class="col-xl-4 d-print-none">

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-calendar-alt text-secondary me-2"></i>Schedule</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <div class="itflow-billing-stat-label">Each <?= $period_word ?></div>
                        <div class="itflow-billing-stat-value"><?= $money($totals['total']) ?></div>
                    </div>
                    <?php if ($frequency !== 'month') { ?>
                        <div class="text-end small text-body-secondary"><?= $money(recurringInvoiceMonthly($totals['total'], $frequency)) ?> a month</div>
                    <?php } ?>
                </div>
                <dl class="itflow-asset-dl mb-0">
                    <dt>Next</dt>
                    <dd>
                        <?php if ($active) { ?>
                            <?= escapeHtml($recurring['recurring_invoice_next_date']) ?>
                            <div class="small text-body-secondary">For <?= escapeHtml($next_period['{period}']) ?></div>
                            <?php if (count($upcoming) > 1 && !$late) { ?>
                                <div class="small text-body-secondary">then <?= escapeHtml(implode(', ', array_slice($upcoming, 1))) ?></div>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">Paused - nothing until it is resumed</span>
                        <?php } ?>
                    </dd>
                    <dt>Last</dt><dd><?= $recurring['recurring_invoice_last_sent'] ? escapeHtml($recurring['recurring_invoice_last_sent']) : '<span class="text-body-secondary">Not yet</span>' ?></dd>
                    <?php $margin_html = billingMarginHtml('recurring', $items, $currency, $period_word); if ($margin_html) { ?>
                        <dt>Margin</dt><dd><?= $margin_html ?></dd>
                    <?php } ?>
                    <?php $reprice_doc = $can_edit && empty($recurring['recurring_invoice_archived_at']) ? productRepriceCount(['kind' => 'recurring', 'doc_id' => $recurring_invoice_id]) : null; if ($reprice_doc && $reprice_doc['lines']) { ?>
                        <dt>Prices</dt>
                        <dd><a class="text-warning-emphasis" href="products_reprice.php?kind=recurring&amp;doc_id=<?= $recurring_invoice_id ?>"><i class="fas fa-tags me-1"></i><?= intval($reprice_doc['lines']) ?> line<?= $reprice_doc['lines'] == 1 ? '' : 's' ?> at an old price</a></dd>
                    <?php } ?>
                    <dt>Sending</dt>
                    <dd>
                        <?php if (!$auto_send) { ?>
                            Made as a draft for you to check and send
                        <?php } elseif (empty($config_recurring_auto_send_invoice)) { ?>
                            Made as a draft<div class="small text-body-secondary">Sending recurring invoices is off in the billing settings</div>
                        <?php } elseif ($email_notify && empty($config_smtp_provider)) { ?>
                            Made as a draft<div class="small text-body-secondary">Outgoing mail isn't set up</div>
                        <?php } elseif ($email_notify) { ?>
                            Emailed to the primary and billing contacts as it is made
                        <?php } else { ?>
                            Made as a draft<div class="small text-body-secondary">Emailing is off for this one</div>
                        <?php } ?>
                    </dd>
                    <dt>Since</dt><dd title="<?= escapeHtml($recurring['recurring_invoice_created_at']) ?>"><?= escapeHtml(date('Y-m-d', strtotime($recurring['recurring_invoice_created_at']))) ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($seat_lines) { ?>
            <!-- Per user, per device: what it bills and what the client has now -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-users text-secondary me-2"></i>Seats</h3>
                    <?php if ($can_edit && $seat_drift) { ?>
                        <a class="btn btn-sm btn-outline-warning" href="post.php?recurring_invoice_sync=<?= $recurring_invoice_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-sync-alt me-1"></i>Update now</a>
                    <?php } ?>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($seat_lines as $seat) {
                        $source = $seat_sources[$seat['item_seat_source']] ?? ['icon' => 'fa-users', 'unit' => 'seat']; ?>
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <span class="itflow-asset-icon itflow-asset-icon-sm itflow-billing-tile itflow-billing-tile-recurring" aria-hidden="true"><i class="fas fa-fw <?= $source['icon'] ?>"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="text-truncate"><?= escapeHtml($seat['item_name']) ?></div>
                                <div class="small text-body-secondary text-truncate"><?= escapeHtml($seat['seat_label']) ?></div>
                            </div>
                            <div class="text-end text-nowrap">
                                <div class="fw-semibold"><?= billingQuantity($seat['item_quantity']) ?></div>
                                <?php if ($seat['seat_count'] === null) { ?>
                                    <div class="small text-danger-emphasis">License gone</div>
                                <?php } elseif ($seat['seat_drift']) { ?>
                                    <div class="small text-warning-emphasis">now <?= billingQuantity($seat['seat_quantity']) ?></div>
                                <?php } else { ?>
                                    <div class="small text-body-secondary">up to date</div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="card-footer small text-body-secondary">Counted again each time an invoice is made<?= $seat_drift ? ' - the next one bills the new counts' : '' ?>.</div>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-wallet text-secondary me-2"></i>Autopay</h3>
            </div>
            <div class="card-body">
                <?php if ($saved_methods) { ?>
                    <form action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="set_recurring_payment" value="1">
                        <input type="hidden" name="recurring_invoice_id" value="<?= $recurring_invoice_id ?>">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-credit-card"></i></span>
                            <select class="form-select" name="saved_payment_id" onchange="this.form.submit()" <?= $can_edit ? '' : 'disabled' ?> aria-label="Card to charge">
                                <option value="0">Off - the client pays each invoice</option>
                                <?php foreach ($saved_methods as $saved_id => $method) { ?>
                                    <option value="<?= $saved_id ?>" <?= $autopay_id === $saved_id ? 'selected' : '' ?>><?= escapeHtml($method['saved_payment_description']) ?><?= $method['payment_provider_name'] ? ' (' . escapeHtml($method['payment_provider_name']) . (intval($method['payment_provider_active']) ? '' : ', turned off') . ')' : '' ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </form>
                    <div class="form-text"><?= $autopay_id ? 'Charged on the due date of each invoice it makes.' : 'Charge a card on file on the due date of each invoice it makes.' ?></div>
                <?php } else { ?>
                    <div class="small text-body-secondary">No card on file for <?= escapeHtml($recurring['client_name']) ?>. Once they save one paying an invoice online, it can be charged here automatically.</div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Invoices Made <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= $generated_count ?></span></h3>
                <?php if ($generated_count > count($generated)) { ?>
                    <a class="small" href="invoices.php?client_id=<?= $client_id ?>&recurring_invoice_id=<?= $recurring_invoice_id ?>">All</a>
                <?php } ?>
            </div>
            <?php if ($generated) { ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($generated as $made) {
                        $made_state = invoiceState($made, $made['paid']); ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="invoice.php?invoice_id=<?= intval($made['invoice_id']) ?>">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold"><?= escapeHtml($made['invoice_prefix'] . $made['invoice_number']) ?> <span class="fw-normal text-body-secondary small ms-1"><?= escapeHtml($made['invoice_date']) ?></span></div>
                                <div class="small"><?= invoiceStatusBadge($made['invoice_status'], $made_state['state'] === 'overdue') ?></div>
                            </div>
                            <span class="itflow-money small"><?= escapeHtml(billingMoney($made['invoice_amount'], $made['invoice_currency_code'])) ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="card-body small text-body-secondary">None yet<?= $active ? ' - the first is made on ' . escapeHtml($recurring['recurring_invoice_next_date']) : '' ?>.</div>
            <?php } ?>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-history text-secondary me-2"></i>History</h3>
            </div>
            <?= billingHistoryHtml($history) ?>
        </div>

    </div>
</div>

<script src="/js/inline_notes.js"></script>
<script src="/libs/SortableJS/Sortable.min.js"></script>
<script src="/js/billing_items.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    itflowBillingItems({
        products: <?= $can_edit ? getProductsForAutocomplete($mysqli) : '[]' ?>,
        csrf: <?= json_encode($csrf) ?>,
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
});
</script>

<?php require_once "../includes/footer.php";
