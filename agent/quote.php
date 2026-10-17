<?php

/*
 * Quote - one quote, from draft to answer to invoice.
 *
 * The header says where it stands - its status, and how long it has left
 * (or that it ran out) while it waits for an answer - with the next thing
 * to do: send a draft, record the client's answer, turn an accepted quote
 * into an invoice. The quote itself is on the left as the client sees it,
 * its lines edited in place until it is answered. On the right: what it
 * comes to - what is billed once and what each month, quarter or year, its
 * recurring lines kept apart - the ticket it was raised from, what it was
 * invoiced as, files the client sent back with their answer, and its history.
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

$quote_id = intval($_GET['quote_id'] ?? 0);
$quote = billingRow('quote', $quote_id);

if (!$quote || ($client_url && intval($quote['client_id']) !== intval($client_id))) {
    billingNotFound('quote', $client_url);
    require_once "../includes/footer.php";
    exit;
}

$client_id = intval($quote['client_id']);
enforceClientAccess();

$number = billingNumber('quote', $quote);
$status = (string) $quote['quote_status'];
$currency = (string) $quote['quote_currency_code'];
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$items = billingItems('quote', $quote_id);
$totals = billingTotals('quote', $quote, $items);
$made = in_array($status, ['Invoiced', 'Accepted'], true) ? quoteMadeFrom($quote_id) : [];
$history = billingHistory('quote', $quote_id);
$expired = quoteExpired($quote);
$awaiting = in_array($status, ['Sent', 'Viewed'], true);

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;
$can_edit_lines = billingCanEditItems('quote', $quote);
$mail_ready = !empty($config_smtp_provider) && billingEmailableContacts('quote', $client_id) > 0;
$guest_url = billingGuestUrl('quote', $quote);
$csrf = $_SESSION['csrf_token'];

// The ticket it was raised from, and what the client sent back with their answer
$ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject FROM tickets WHERE ticket_quote_id = $quote_id LIMIT 1"));
$files = [];
$sql = mysqli_query($mysqli, "SELECT files.file_id, file_name, file_ext, file_size, file_created_at FROM quote_files JOIN files ON quote_files.file_id = files.file_id
    WHERE quote_id = $quote_id AND file_archived_at IS NULL ORDER BY file_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $files[] = $row;
}

// Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
$tab_title = $quote['client_name'];
$page_title = $number;

$paper = [
    'type' => 'quote',
    'row' => $quote,
    'items' => $items,
    'totals' => $totals,
    'edit' => $can_edit_lines,
    'notes_edit' => $can_edit,
    'taxes' => $can_edit_lines ? billingTaxes($quote['quote_created_at']) : [],
    'logo_url' => billingCompany()['logo_url'],
    'csrf' => $csrf,
];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($quote['client_name']) ?></a></li>
    <li class="breadcrumb-item"><a href="quotes.php?client_id=<?= $client_id ?>">Quotes</a></li>
    <li class="breadcrumb-item active"><?= escapeHtml($number) ?></li>
</ol>

<!-- What it is, where it stands, and the next thing to do -->
<div class="card mb-3 d-print-none">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?= billingTile('quote', 'lg') ?>

        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span>Quote <?= escapeHtml($number) ?></span>
                <span class="fs-6"><?= quoteStatusBadge($quote) ?></span>
                <?php if (!$expired) { ?><span class="fs-6"><?= quoteExpiryPill($quote) ?></span><?php } ?>
            </h1>
            <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                <span><i class="fas fa-fw fa-building me-1"></i><a class="text-reset" href="quotes.php?client_id=<?= $client_id ?>"><?= escapeHtml($quote['client_name']) ?></a></span>
                <?php if (trim((string) $quote['quote_scope']) !== '') { ?><span><i class="fas fa-fw fa-comment me-1"></i><?= escapeHtml($quote['quote_scope']) ?></span><?php } ?>
                <?php if ($quote['category_name']) { ?><span><i class="fas fa-fw fa-tag me-1"></i><?= escapeHtml($quote['category_name']) ?></span><?php } ?>
                <?php if ($ticket) { ?>
                    <span><i class="fas fa-fw fa-life-ring me-1"></i>For <a class="text-reset" href="ticket.php?ticket_id=<?= intval($ticket['ticket_id']) ?>"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></a></span>
                <?php } ?>
                <span title="<?= escapeHtml($quote['quote_created_at']) ?>"><i class="far fa-fw fa-clock me-1"></i>Created <?= escapeHtml(timeAgo($quote['quote_created_at'])) ?></span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($can_edit && $status === 'Draft') { ?>
                <div class="btn-group">
                    <?php if ($mail_ready) { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/quote/quote_email.php?quote_id=<?= $quote_id ?>">
                            <i class="fas fa-paper-plane me-2"></i>Send
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More ways to send"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <button type="submit" class="dropdown-item confirm-link" form="quickSendQuote" name="quote_id" value="<?= $quote_id ?>"
                                data-confirm-title="Send <?= escapeHtml($number) ?> now?" data-confirm-text="It goes to the client's primary contact." data-confirm-button="Send">
                                <i class="fas fa-fw fa-bolt text-secondary me-2"></i>Quick Send
                            </button>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/quote/quote_mark_sent.php?quote_id=<?= $quote_id ?>">
                                <i class="fas fa-fw fa-check text-secondary me-2"></i>Mark Sent<span class="text-body-secondary">...</span>
                            </a>
                        </div>
                    <?php } else { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/quote/quote_mark_sent.php?quote_id=<?= $quote_id ?>">
                            <i class="fas fa-check me-2"></i>Mark Sent
                        </button>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($can_edit && $awaiting) { ?>
                <a class="btn btn-success confirm-link" href="post.php?accept_quote=<?= $quote_id ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-title="Mark <?= escapeHtml($number) ?> accepted?" data-confirm-text="For when the client said yes by phone or email - its lines are locked and it can be invoiced." data-confirm-button="Accepted">
                    <i class="fas fa-thumbs-up me-2"></i>Accepted
                </a>
                <a class="btn btn-outline-danger confirm-link" href="post.php?decline_quote=<?= $quote_id ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-title="Mark <?= escapeHtml($number) ?> declined?" data-confirm-text="The client said no - its lines are locked." data-confirm-button="Declined">
                    <i class="fas fa-thumbs-down me-2"></i>Declined
                </a>
            <?php } ?>

            <?php if ($can_edit && $status === 'Accepted') { ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/quote/quote_to_invoice.php?quote_id=<?= $quote_id ?>">
                        <i class="fas fa-file-invoice-dollar me-2"></i><?= $totals['recurring'] ? 'Start Billing' : 'Make Invoice' ?>
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item confirm-link" href="post.php?mark_quote_invoiced=<?= $quote_id ?>&csrf_token=<?= $csrf ?>"
                            data-confirm-text="For a quote billed some other way - it is marked invoiced without making an invoice.">
                            <i class="fas fa-fw fa-check-double text-secondary me-2"></i>Mark Invoiced
                        </a>
                    </div>
                </div>
            <?php } ?>

            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/quote/quote_edit.php?id=<?= $quote_id ?>">
                    <i class="fas fa-edit me-2"></i>Edit
                </button>
            <?php } ?>

            <div class="dropdown dropstart">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="post.php?export_quote_pdf=<?= $quote_id ?>&csrf_token=<?= $csrf ?>" target="_blank">
                        <i class="fas fa-fw fa-file-pdf text-secondary me-2"></i>Download PDF
                    </a>
                    <a class="dropdown-item" href="#" onclick="window.print(); return false;">
                        <i class="fas fa-fw fa-print text-secondary me-2"></i>Print
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item clipboardjs" href="#" data-clipboard-text="<?= escapeHtml($guest_url) ?>">
                        <i class="fas fa-fw fa-link text-secondary me-2"></i>Copy Client Link
                    </a>
                    <a class="dropdown-item" href="<?= escapeHtml($guest_url) ?>" target="_blank" rel="noopener">
                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>View as Client
                    </a>
                    <?php if ($can_edit) { ?>
                        <?php if ($status !== 'Draft' && $mail_ready) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/quote/quote_email.php?quote_id=<?= $quote_id ?>">
                                <i class="fas fa-fw fa-paper-plane text-secondary me-2"></i>Send Again<span class="text-body-secondary">...</span>
                            </a>
                        <?php } ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/quote/quote_copy.php?id=<?= $quote_id ?>">
                            <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                        </a>
                    <?php } ?>
                    <?php if ($can_delete) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?= $quote_id ?>&csrf_token=<?= $csrf ?>"
                            data-confirm-text="<?= escapeHtml($number) ?> goes for good, with its lines and history.">
                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($expired && $can_edit) { ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 d-print-none">
        <i class="fas fa-hourglass-end"></i>
        <span class="me-auto">It ran out on <?= escapeHtml($quote['quote_expire']) ?> without an answer - the client can still see it but can't accept it.</span>
        <button type="button" class="btn btn-sm btn-warning ajax-modal" data-modal-url="modals/quote/quote_edit.php?id=<?= $quote_id ?>"><i class="fas fa-calendar-plus me-1"></i>Extend</button>
    </div>
<?php } ?>

<?= taxDocWarning('quote', $quote_id, $client_id, $items, billingLinesOpen('quote', $status), $can_edit) ?>

<div class="row g-3">

    <!-- The quote, as the client sees it -->
    <div class="col-xl-8 itflow-paper-col">
        <?php require "../includes/billing_paper.php"; ?>
    </div>

    <div class="col-xl-4 d-print-none">

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-comment-dollar text-secondary me-2"></i>Quote</h3>
            </div>
            <div class="card-body">
                <?php if ($totals['recurring']) { ?>
                    <div class="d-flex flex-wrap gap-4 mb-3">
                        <?php foreach ($totals['groups'] as $frequency => $group) {
                            if ($frequency === '' && !$group['items'] && $group['discount'] == 0) {
                                continue;
                            } ?>
                            <div>
                                <div class="itflow-billing-stat-label"><?= $frequency === '' ? 'Up front' : escapeHtml($group['label']) ?></div>
                                <div class="itflow-billing-stat-value"><?= $money($group['total']) ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="itflow-billing-stat-label">Total</div>
                    <div class="itflow-billing-stat-value mb-3"><?= $money($totals['total']) ?></div>
                <?php } ?>
                <dl class="itflow-asset-dl mb-0">
                    <dt>Status</dt><dd><?= quoteStatusBadge($quote) ?></dd>
                    <dt>Dated</dt><dd><?= escapeHtml($quote['quote_date']) ?></dd>
                    <dt>Valid until</dt>
                    <dd>
                        <?= escapeHtml($quote['quote_expire']) ?>
                        <?php if ($awaiting || $status === 'Draft') { ?><div class="mt-1"><?= quoteExpiryPill($quote) ?></div><?php } ?>
                    </dd>
                    <?php $margin_html = billingMarginHtml('quote', $items, $currency); if ($margin_html) { ?>
                        <dt>Margin</dt><dd><?= $margin_html ?></dd>
                    <?php } ?>
                    <?php $reprice_doc = $can_edit && $status === 'Draft' ? productRepriceCount(['kind' => 'quote', 'doc_id' => $quote_id]) : null; if ($reprice_doc && $reprice_doc['lines']) { ?>
                        <dt>Prices</dt>
                        <dd><a class="text-warning-emphasis" href="products_reprice.php?kind=quote&amp;doc_id=<?= $quote_id ?>"><i class="fas fa-tags me-1"></i><?= intval($reprice_doc['lines']) ?> line<?= $reprice_doc['lines'] == 1 ? '' : 's' ?> at an old price</a></dd>
                    <?php } ?>
                    <?php if ($status === 'Draft') { ?>
                        <dt>Client</dt><dd class="text-body-secondary">Not sent yet - the client can't see it</dd>
                    <?php } elseif ($awaiting) { ?>
                        <dt>Client</dt><dd class="text-body-secondary"><?= $status === 'Viewed' ? 'Has opened it' : "Hasn't opened it yet" ?> - they can accept or decline it from the link</dd>
                    <?php } ?>
                </dl>
            </div>
        </div>

        <?php if ($made) { ?>
            <!-- What it was invoiced as -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Invoiced as</h3>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($made as $made_doc) {
                        $made_type = $made_doc['type'];
                        $made_url = $made_type === 'invoice' ? 'invoice.php?invoice_id=' . intval($made_doc['id']) : 'recurring_invoice.php?recurring_invoice_id=' . intval($made_doc['id']); ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= $made_url ?>">
                            <?= billingTile($made_type, 'sm') ?>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold"><?= escapeHtml($made_doc['prefix'] . $made_doc['number']) ?></div>
                                <div class="small text-body-secondary"><?= $made_type === 'invoice' ? 'Invoice' : 'Recurring, ' . strtolower(recurringInvoiceFrequencies()[$made_doc['frequency']] ?? 'monthly') . ($made_doc['added'] ? ', lines added' : '') ?> &middot; <?= escapeHtml($made_doc['status']) ?></div>
                            </div>
                            <div class="text-end text-nowrap fw-semibold"><?= escapeHtml(billingMoney($made_doc['amount'], $made_doc['currency'])) ?></div>
                        </a>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if ($files) { ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-paperclip text-secondary me-2"></i>From the client <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($files) ?></span></h3>
                </div>
                <div class="list-group list-group-flush">
                    <?php /* Through the file quick look and agent/file.php, which check access - not a link straight into the uploads folder */ ?>
                    <?php foreach ($files as $file) {
                        $file_id = intval($file['file_id']); ?>
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <?= fileTypeTile($file['file_ext'], 'sm') ?>
                            <div class="flex-grow-1 min-w-0">
                                <a class="ajax-modal text-break" href="#" data-modal-size="lg" data-modal-url="modals/file/file.php?id=<?= $file_id ?>"><?= escapeHtml($file['file_name']) ?></a>
                                <div class="small text-body-secondary"><?= formatBytes(intval($file['file_size'])) ?> &middot; <span title="<?= escapeHtml($file['file_created_at']) ?>"><?= escapeHtml(timeAgo($file['file_created_at'])) ?></span></div>
                            </div>
                            <a class="btn btn-sm btn-link text-body-secondary" href="file.php?file_id=<?= $file_id ?>" title="Download"><i class="fas fa-fw fa-download"></i></a>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-history text-secondary me-2"></i>History</h3>
            </div>
            <?= billingHistoryHtml($history) ?>
        </div>

    </div>
</div>

<?php if ($can_edit && $mail_ready) { ?>
    <!-- Quick Send posts this, the button carrying the quote id as its own name/value -->
    <form id="quickSendQuote" action="post.php" method="post" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="email_quote" value="1">
        <input type="hidden" name="quick_send" value="1">
    </form>
<?php } ?>

<script src="/js/inline_notes.js"></script>
<script src="/libs/SortableJS/Sortable.min.js"></script>
<script src="/js/billing_items.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    itflowBillingItems({
        products: <?= $can_edit_lines ? getProductsForAutocomplete($mysqli, intval($quote['client_id'])) : '[]' ?>,
        csrf: <?= json_encode($csrf) ?>,
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
});
</script>

<?php require_once "../includes/footer.php";
