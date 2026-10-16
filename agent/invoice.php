<?php

/*
 * Invoice - one invoice, the way an MSP works it.
 *
 * The header says where it stands - its status, whether it is due or late
 * and by how long - and has the next thing to do with it: send a draft,
 * take a payment on one that is owed. The invoice itself is on the left,
 * as the client sees it, with its lines edited in place while it is open.
 * On the right: what has been paid and what is left, the payments, the
 * tickets billed on it and its history.
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

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);

if (!$invoice || ($client_url && intval($invoice['client_id']) !== intval($client_id))) {
    billingNotFound('invoice', $client_url);
    require_once "../includes/footer.php";
    exit;
}

$client_id = intval($invoice['client_id']);
enforceClientAccess();

$number = billingNumber('invoice', $invoice);
$status = (string) $invoice['invoice_status'];
$currency = (string) $invoice['invoice_currency_code'];
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$items = billingItems('invoice', $invoice_id);
$totals = billingTotals('invoice', $invoice, $items);
$paid = invoicePaid($invoice_id);
$state = invoiceState($invoice, $paid);
$payments = invoicePayments($invoice_id);
$tickets = invoiceTickets($invoice_id);
$history = billingHistory('invoice', $invoice_id);

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;
$can_take_payment = $can_edit && lookupUserPermission('module_financial') >= 2 && invoiceCanPay($invoice, $state);
$can_manage_payments = lookupUserPermission('module_sales') >= 3 && lookupUserPermission('module_financial') >= 3;
$can_edit_lines = billingCanEditItems('invoice', $invoice);
$mail_ready = !empty($config_smtp_provider) && billingEmailableContacts('invoice', $client_id) > 0;
$saved_card = $can_take_payment && in_array($status, ['Sent', 'Viewed'], true) && invoiceHasSavedCard($client_id);
$unbilled_tickets = $can_edit ? count(invoiceUnbilledTickets($client_id)) : 0;
$guest_url = billingGuestUrl('invoice', $invoice);
$csrf = $_SESSION['csrf_token'];

// Made by a recurring invoice?
$recurring = intval($invoice['invoice_recurring_invoice_id'])
    ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number FROM recurring_invoices WHERE recurring_invoice_id = " . intval($invoice['invoice_recurring_invoice_id'])))
    : null;

$progress = $state['paid'] > 0 && $totals['total'] > 0 ? min(100, round($state['paid'] / $totals['total'] * 100)) : 0;

// Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
$tab_title = $invoice['client_name'];
$page_title = $number;

$paper = [
    'type' => 'invoice',
    'row' => $invoice,
    'items' => $items,
    'totals' => $totals,
    'paid' => $state['paid'],
    'balance' => $state['balance'],
    'edit' => $can_edit_lines,
    'notes_edit' => $can_edit,
    'taxes' => $can_edit_lines ? billingTaxes($invoice['invoice_created_at']) : [],
    'logo_url' => billingCompany()['logo_url'],
    'csrf' => $csrf,
];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($invoice['client_name']) ?></a></li>
    <li class="breadcrumb-item"><a href="invoices.php?client_id=<?= $client_id ?>">Invoices</a></li>
    <li class="breadcrumb-item active"><?= escapeHtml($number) ?></li>
</ol>

<!-- What it is, where it stands, and the next thing to do -->
<div class="card mb-3 d-print-none">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?= billingTile('invoice', 'lg') ?>

        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span>Invoice <?= escapeHtml($number) ?></span>
                <span class="fs-6"><?= invoiceStatusBadge($status) ?></span>
                <span class="fs-6"><?= invoiceDuePill($state) ?></span>
            </h1>
            <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                <span><i class="fas fa-fw fa-building me-1"></i><a class="text-reset" href="invoices.php?client_id=<?= $client_id ?>"><?= escapeHtml($invoice['client_name']) ?></a></span>
                <?php if (trim((string) $invoice['invoice_scope']) !== '') { ?><span><i class="fas fa-fw fa-comment me-1"></i><?= escapeHtml($invoice['invoice_scope']) ?></span><?php } ?>
                <?php if ($invoice['category_name']) { ?><span><i class="fas fa-fw fa-tag me-1"></i><?= escapeHtml($invoice['category_name']) ?></span><?php } ?>
                <?php if ($recurring) { ?>
                    <span><i class="fas fa-fw fa-redo-alt me-1"></i>From <a class="text-reset" href="recurring_invoice.php?recurring_invoice_id=<?= intval($recurring['recurring_invoice_id']) ?>"><?= escapeHtml($recurring['recurring_invoice_prefix'] . $recurring['recurring_invoice_number']) ?></a></span>
                <?php } ?>
                <span title="<?= escapeHtml($invoice['invoice_created_at']) ?>"><i class="far fa-fw fa-clock me-1"></i>Created <?= escapeHtml(timeAgo($invoice['invoice_created_at'])) ?></span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($can_edit && $status === 'Draft') { ?>
                <div class="btn-group">
                    <?php if ($mail_ready) { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-paper-plane me-2"></i>Send
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More ways to send"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <button type="submit" class="dropdown-item confirm-link" form="quickSendInvoice" name="invoice_id" value="<?= $invoice_id ?>"
                                data-confirm-title="Send <?= escapeHtml($number) ?> now?" data-confirm-text="It goes to the client's primary and billing contacts." data-confirm-button="Send">
                                <i class="fas fa-fw fa-bolt text-secondary me-2"></i>Quick Send
                            </button>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_mark_sent.php?invoice_id=<?= $invoice_id ?>">
                                <i class="fas fa-fw fa-check text-secondary me-2"></i>Mark Sent<span class="text-body-secondary">...</span>
                            </a>
                        </div>
                    <?php } else { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/invoice/invoice_mark_sent.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-check me-2"></i>Mark Sent
                        </button>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($can_take_payment) { ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-success ajax-modal" data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>">
                        <i class="fas fa-credit-card me-2"></i>Add Payment
                    </button>
                    <?php if ($saved_card) { ?>
                        <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More ways to pay"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_saved_method_add.php?id=<?= $invoice_id ?>">
                                <i class="fas fa-fw fa-wallet text-secondary me-2"></i>Charge Saved Card
                            </a>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($can_edit && $status !== 'Draft' && $mail_ready && in_array($status, invoiceOpenStatuses(), true)) { ?>
                <button type="button" class="btn btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                    <i class="fas fa-paper-plane me-2"></i>Send Again
                </button>
            <?php } ?>

            <?php if ($can_edit && in_array($status, ['Sent', 'Viewed'], true) && floatval($invoice['invoice_amount']) == 0) { ?>
                <a class="btn btn-outline-dark confirm-link" href="post.php?mark_invoice_non-billable=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-text="Nothing is owed on it - it is closed off as non-billable.">
                    <i class="fas fa-ban me-2"></i>Non-Billable
                </a>
            <?php } ?>

            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/invoice/invoice_edit.php?id=<?= $invoice_id ?>">
                    <i class="fas fa-edit me-2"></i>Edit
                </button>
            <?php } ?>

            <div class="dropdown dropstart">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="post.php?export_invoice_pdf=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>" target="_blank">
                        <i class="fas fa-fw fa-file-pdf text-secondary me-2"></i>Download PDF
                    </a>
                    <a class="dropdown-item" href="#" onclick="window.print(); return false;">
                        <i class="fas fa-fw fa-print text-secondary me-2"></i>Print
                    </a>
                    <a class="dropdown-item" href="post.php?export_invoice_packing_slip=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>" target="_blank">
                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Packing Slip
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item clipboardjs" href="#" data-clipboard-text="<?= escapeHtml($guest_url) ?>">
                        <i class="fas fa-fw fa-link text-secondary me-2"></i>Copy Client Link
                    </a>
                    <a class="dropdown-item" href="<?= escapeHtml($guest_url) ?>" target="_blank" rel="noopener">
                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>View as Client
                    </a>
                    <?php if ($can_edit) { ?>
                        <?php if ($status !== 'Draft' && $mail_ready && !in_array($status, invoiceOpenStatuses(), true)) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                                <i class="fas fa-fw fa-paper-plane text-secondary me-2"></i>Send Again<span class="text-body-secondary">...</span>
                            </a>
                        <?php } ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_copy.php?id=<?= $invoice_id ?>">
                            <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                        </a>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_recurring_add.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Make Recurring
                        </a>
                        <?php if (!in_array($status, ['Cancelled', 'Paid', 'Non-Billable'], true)) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?cancel_invoice=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>"
                                data-confirm-text="<?= escapeHtml($number) ?> is cancelled - nothing more is owed on it. It stays on record.">
                                <i class="fas fa-fw fa-times me-2"></i>Cancel Invoice
                            </a>
                        <?php } ?>
                    <?php } ?>
                    <?php if ($can_delete) { ?>
                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?= $invoice_id ?>&csrf_token=<?= $csrf ?>"
                            data-confirm-text="<?= escapeHtml($number) ?> goes for good, with its lines, payments and history. Its tickets become unbilled again.">
                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- The invoice, as the client sees it -->
    <div class="col-xl-8 itflow-paper-col">
        <?php require "../includes/billing_paper.php"; ?>
    </div>

    <!-- Money, tickets and what has happened -->
    <div class="col-xl-4 d-print-none">

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-coins text-secondary me-2"></i>Payment</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <div>
                        <div class="itflow-billing-stat-label"><?= $state['state'] === 'paid' ? 'Paid in full' : ($state['state'] === 'overdue' ? 'Overdue' : 'Balance') ?></div>
                        <div class="itflow-billing-stat-value <?= $state['state'] === 'overdue' ? 'text-danger-emphasis' : '' ?>"><?= $money($state['state'] === 'paid' ? $state['paid'] : $state['balance']) ?></div>
                    </div>
                    <div class="text-end small text-body-secondary">of <?= $money($totals['total']) ?></div>
                </div>
                <div class="progress itflow-billing-progress mb-3" role="progressbar" aria-label="Paid" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                </div>
                <dl class="itflow-asset-dl mb-0">
                    <dt>Paid</dt><dd><?= $money($state['paid']) ?><?= $payments ? ' <span class="text-body-secondary">in ' . count($payments) . ' payment' . (count($payments) === 1 ? '' : 's') . '</span>' : '' ?></dd>
                    <dt>Due</dt>
                    <dd>
                        <?= escapeHtml($invoice['invoice_due']) ?>
                        <?php if (in_array($state['state'], ['open', 'overdue'], true)) { ?>
                            <div class="small <?= $state['days'] < 0 ? 'text-danger-emphasis' : 'text-body-secondary' ?>">
                                <?= $state['days'] < 0 ? (-$state['days']) . ' day' . (-$state['days'] === 1 ? '' : 's') . ' late' : ($state['days'] === 0 ? 'Today' : 'In ' . $state['days'] . ' day' . ($state['days'] === 1 ? '' : 's')) ?>
                            </div>
                        <?php } ?>
                    </dd>
                    <dt>Terms</dt><dd><?= intval($invoice['client_net_terms']) ?: intval($config_default_net_terms) ?> days</dd>
                    <?php if ($status === 'Draft') { ?>
                        <dt>Client</dt><dd class="text-body-secondary">Not sent yet - the client can't see it</dd>
                    <?php } ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-credit-card text-secondary me-2"></i>Payments <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($payments) ?></span></h3>
                <?php if ($can_take_payment) { ?>
                    <button type="button" class="btn btn-sm btn-outline-success ajax-modal" data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>"><i class="fas fa-plus me-1"></i>Add</button>
                <?php } ?>
            </div>
            <?php if ($payments) { ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($payments as $payment) {
                        $payment_id = intval($payment['payment_id']); ?>
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($payment['payment_amount'], $payment['payment_currency_code'])) ?></div>
                                <div class="small text-body-secondary text-truncate">
                                    <?= escapeHtml(implode(' · ', array_filter([$payment['payment_date'], $payment['payment_method'], $payment['payment_reference'], $payment['account_name']]))) ?>
                                </div>
                            </div>
                            <?php if ($can_manage_payments) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-link text-body-secondary" type="button" data-bs-toggle="dropdown" title="Payment actions"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_edit.php?id=<?= $payment_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <?php if ($payment['payment_method'] === 'Stripe') { ?>
                                            <a class="dropdown-item text-warning confirm-link" href="post.php?refund_payment_stripe=<?= $payment_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-text="The payment is refunded to the client's card through Stripe.">
                                                <i class="fas fa-fw fa-undo me-2"></i>Refund through Stripe
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_payment=<?= $payment_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-text="The payment comes off the invoice and its account. No money moves.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="card-body small text-body-secondary">No payments yet.</div>
            <?php } ?>
        </div>

        <?php if ($tickets || $unbilled_tickets) { ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-life-ring text-secondary me-2"></i>Tickets <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($tickets) ?></span></h3>
                    <?php if ($unbilled_tickets) { ?>
                        <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-url="modals/invoice/invoice_add_ticket.php?invoice_id=<?= $invoice_id ?>">
                            <i class="fas fa-plus me-1"></i>Add <span class="badge rounded-pill text-bg-primary ms-1" title="Unbilled"><?= $unbilled_tickets ?></span>
                        </button>
                    <?php } ?>
                </div>
                <?php if ($tickets) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($tickets as $ticket) {
                            $ticket_id = intval($ticket['ticket_id']); ?>
                            <div class="list-group-item d-flex align-items-center gap-2">
                                <div class="flex-grow-1 min-w-0">
                                    <a class="text-truncate d-block" href="ticket.php?ticket_id=<?= $ticket_id ?>"><span class="font-monospace small me-1"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></a>
                                    <div class="small text-body-secondary"><i class="far fa-clock me-1"></i><?= billingDuration($ticket['seconds']) ?> worked</div>
                                </div>
                                <?php if ($can_edit) { ?>
                                    <a class="btn btn-sm btn-link text-body-secondary confirm-link" title="Take off the invoice" href="post.php?remove_ticket_from_invoice&invoice_id=<?= $invoice_id ?>&ticket_id=<?= $ticket_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-text="The ticket becomes unbilled again. The invoice's lines don't change.">
                                        <i class="fas fa-fw fa-unlink"></i>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="card-body small text-body-secondary"><?= $unbilled_tickets ?> unbilled ticket<?= $unbilled_tickets === 1 ? '' : 's' ?> for <?= escapeHtml($invoice['client_name']) ?> could go on this invoice.</div>
                <?php } ?>
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
    <!-- Quick Send buttons post this, carrying the invoice id as their own name/value -->
    <form id="quickSendInvoice" action="post.php" method="post" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="email_invoice" value="1">
        <input type="hidden" name="quick_send" value="1">
    </form>
<?php } ?>

<script src="/js/inline_notes.js"></script>
<script src="/libs/SortableJS/Sortable.min.js"></script>
<script src="/js/billing_items.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    itflowBillingItems({
        products: <?= $can_edit_lines ? getProductsForAutocomplete($mysqli) : '[]' ?>,
        csrf: <?= json_encode($csrf) ?>,
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
});
</script>

<?php require_once "../includes/footer.php";
