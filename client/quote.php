<?php
/*
 * Client Portal
 * A quote - as it prints, with what it comes to alongside (up front, and
 * each month, quarter or year for its recurring lines), how long it is good
 * for, and Accept or Decline while it waits - signed with the contact's name
 * and a note for us. Once accepted, a signed copy or purchase order can be
 * sent back. On a phone, the answer comes first.
 *
 * SCOPING: the quote must be the session's client's, and not a draft.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalBillingGate();

$quote = billingRow('quote', intval($_GET['id'] ?? 0), false);
if (!$quote || intval($quote['client_id']) !== $session_client_id || $quote['quote_status'] === 'Draft') { ?>
    <a class="itflow-portal-back" href="quotes.php"><i class="fas fa-chevron-left"></i>Quotes</a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => "That quote isn't here", 'text' => 'It may have been replaced, or the link is not quite right.', 'icon' => 'fa-comment-dollar', 'filtered' => false,
            'actions' => [['label' => 'All quotes', 'href' => 'quotes.php', 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$quote_id = intval($quote['quote_id']);
$number = billingNumber('quote', $quote);
$currency = (string) $quote['quote_currency_code'];
$status = (string) $quote['quote_status'];

portalBillingViewed('quote', $quote);

$items = billingItems('quote', $quote_id);
$totals = billingTotals('quote', $quote, $items);
$company = billingCompany();
$state = portalQuoteState($quote);
$days_left = intval(round((strtotime((string) $quote['quote_expire']) - strtotime(date('Y-m-d'))) / 86400));
$files = $status === 'Accepted' || $status === 'Invoiced' ? billingQuoteFiles($quote_id) : [];
$contact_name = (string) ($contact['contact_name'] ?? '');

// When it was answered, and by whom
$answered = in_array($state['key'], ['accepted', 'declined'], true) ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT history_description, history_created_at FROM history
    WHERE history_quote_id = $quote_id AND history_status IN ('Accepted', 'Declined') AND (history_description LIKE 'Accepted by%' OR history_description LIKE 'Declined by%')
    ORDER BY history_id DESC LIMIT 1")) : null;
$answered_by = $answered && preg_match('/^(?:Accepted|Declined) by (.+?) in the client portal/', (string) $answered['history_description'], $m) ? $m[1] : '';

// The invoices made from it, once invoiced
$made = [];
if ($status === 'Invoiced') {
    foreach (quoteMadeFrom($quote_id) as $row) {
        if ($row['type'] === 'invoice' && $row['status'] !== 'Draft') {
            $made[] = $row;
        }
    }
}

$paper = [
    'type' => 'quote',
    'row' => $quote,
    'items' => $items,
    'totals' => $totals,
    'logo_url' => $company['logo_url'],
    'client' => true,
];
?>

<a class="itflow-portal-back d-print-none" href="quotes.php"><i class="fas fa-chevron-left"></i>Quotes</a>

<div class="itflow-portal-page-head d-print-none">
    <div>
        <h1>Quote <?= escapeHtml($number) ?></h1>
        <p><?= trim((string) $quote['quote_scope']) !== '' ? escapeHtml($quote['quote_scope']) : 'Dated ' . escapeHtml(date('M j, Y', strtotime($quote['quote_date']))) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="pdf.php?quote=<?= $quote_id ?>"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>
        <button type="button" class="btn btn-outline-secondary" data-portal-print title="Print"><i class="fas fa-print"></i><span class="visually-hidden">Print</span></button>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-4 order-lg-2 d-print-none">

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-body">
                <?php if ($totals['recurring']) { ?>
                    <div class="d-flex flex-wrap gap-4 mb-3">
                        <?php foreach ($totals['groups'] as $frequency => $group) {
                            if ($frequency === '' && !$group['items'] && $group['discount'] == 0) {
                                continue;
                            } ?>
                            <div>
                                <div class="itflow-billing-stat-label"><?= $frequency === '' ? 'Up front' : escapeHtml($group['label']) ?></div>
                                <div class="itflow-portal-big-amount itflow-money"><?= escapeHtml(billingMoney($group['total'], $currency)) ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="itflow-billing-stat-label">Quote total</div>
                    <div class="itflow-portal-big-amount itflow-money mb-3"><?= escapeHtml(billingMoney($totals['total'], $currency)) ?></div>
                <?php } ?>

                <?php if ($state['key'] === 'waiting') { ?>
                    <p class="small text-body-secondary mb-3">
                        Good until <?= escapeHtml(date('M j, Y', strtotime($quote['quote_expire']))) ?><?= $days_left === 0 ? ' - today is the last day' : ' - ' . $days_left . ' day' . ($days_left === 1 ? '' : 's') . ' left' ?>.
                        Let us know whether to go ahead.
                    </p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#acceptQuoteModal"><i class="fas fa-thumbs-up me-2"></i>Accept</button>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#declineQuoteModal"><i class="fas fa-thumbs-down me-2"></i>Decline</button>
                    </div>
                <?php } elseif ($state['key'] === 'expired') { ?>
                    <div class="itflow-portal-outcome mb-3">
                        <span class="itflow-portal-outcome-icon is-warning" aria-hidden="true"><i class="fas fa-hourglass-end"></i></span>
                        <div>
                            <div class="fw-semibold">This quote has run out</div>
                            <div class="small text-body-secondary">It was good until <?= escapeHtml(date('M j, Y', strtotime($quote['quote_expire']))) ?> - prices may have moved since.</div>
                        </div>
                    </div>
                    <a class="btn btn-outline-primary w-100" href="<?= escapeHtml(portalAskUrl("Updated quote for $number")) ?>"><i class="fas fa-redo-alt me-2"></i>Ask for an updated quote</a>
                <?php } elseif ($state['key'] === 'accepted') { ?>
                    <div class="itflow-portal-outcome">
                        <span class="itflow-portal-outcome-icon is-success" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <div class="fw-semibold">Accepted - thank you</div>
                            <div class="small text-body-secondary">
                                <?php if ($answered) { ?><?= escapeHtml(date('M j, Y', strtotime($answered['history_created_at']))) ?><?= $answered_by !== '' ? ' by ' . escapeHtml($answered_by) : '' ?>. <?php } ?>
                                <?= $status === 'Invoiced' ? 'We have invoiced it.' : "We'll be in touch to get started." ?>
                            </div>
                        </div>
                    </div>
                    <?php foreach ($made as $row) { ?>
                        <a class="btn btn-outline-secondary w-100 mt-3" href="invoice.php?id=<?= intval($row['id']) ?>"><i class="fas fa-file-invoice-dollar me-2"></i>Invoice <?= escapeHtml($row['prefix'] . $row['number']) ?></a>
                    <?php } ?>
                <?php } else { ?>
                    <div class="itflow-portal-outcome">
                        <span class="itflow-portal-outcome-icon" aria-hidden="true"><i class="fas fa-times"></i></span>
                        <div>
                            <div class="fw-semibold">Declined</div>
                            <div class="small text-body-secondary">
                                <?php if ($answered) { ?><?= escapeHtml(date('M j, Y', strtotime($answered['history_created_at']))) ?><?= $answered_by !== '' ? ' by ' . escapeHtml($answered_by) : '' ?>. <?php } ?>
                                If something needs changing, let us know and we'll send a new one.
                            </div>
                        </div>
                    </div>
                    <a class="btn btn-outline-primary w-100 mt-3" href="<?= escapeHtml(portalAskUrl("New quote instead of $number")) ?>"><i class="fas fa-redo-alt me-2"></i>Ask for a new quote</a>
                <?php } ?>
            </div>
        </section>

        <?php if ($status === 'Accepted' || $files) { ?>
            <section class="itflow-portal-panel mb-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-file-signature"></i>Signed copy or PO</h2>
                </div>
                <div class="itflow-portal-panel-body">
                    <?php if ($files) { ?>
                        <div class="itflow-portal-files mb-3">
                            <?php foreach ($files as $file) { ?>
                                <span class="itflow-portal-file-chip" title="Sent <?= escapeHtml($file['file_created_at']) ?>"><i class="fas fa-file-pdf"></i><span class="text-truncate"><?= escapeHtml($file['file_name']) ?></span><span class="text-body-secondary"><?= escapeHtml(date('M j', strtotime($file['file_created_at']))) ?></span></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if ($status === 'Accepted') { ?>
                        <form action="post.php" method="post" enctype="multipart/form-data" data-portal-once>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                            <p class="small text-body-secondary mb-2">Need us to have the quote signed, or a purchase order? Send the PDF here.</p>
                            <div class="itflow-portal-drop mb-3" data-portal-drop>
                                <input type="file" name="file[]" multiple id="quoteFiles" accept=".pdf" required>
                                <i class="fas fa-file-pdf me-2"></i>Drop PDFs here, or <label for="quoteFiles">choose files</label>
                                <div class="itflow-portal-files" data-portal-files hidden></div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary" name="portal_quote_file"><i class="fas fa-upload me-2"></i>Send</button>
                        </form>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>

        <p class="small text-body-secondary px-1">
            Questions about this quote? <a href="<?= escapeHtml(portalAskUrl("Question about quote $number")) ?>">Ask us</a><?php if (!empty($company['phone_display'])) { ?> or call <?= escapeHtml($company['phone_display']) ?><?php } ?>.
        </p>

    </div>

    <div class="col-lg-8 order-lg-1 itflow-paper-col">
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/billing_paper.php'; ?>
    </div>

</div>

<?php if ($state['key'] === 'waiting') { ?>
    <div class="modal fade" id="acceptQuoteModal" tabindex="-1" aria-labelledby="acceptQuoteTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="post.php" method="post" autocomplete="off" data-portal-once>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                <input type="hidden" name="answer" value="accept">
                <div class="modal-header">
                    <h5 class="modal-title" id="acceptQuoteTitle"><i class="fas fa-fw fa-thumbs-up me-2 text-success"></i>Accept <?= escapeHtml($number) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <?php if ($totals['recurring']) { ?>
                            That is <strong><?= escapeHtml(quoteAmountText($totals['groups'], $currency)) ?></strong>.
                        <?php } else { ?>
                            That is <strong><?= escapeHtml(billingMoney($totals['total'], $currency)) ?></strong>.
                        <?php } ?>
                        We'll be told straight away and be in touch to get started.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="acceptName">Your name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-signature"></i></span>
                            <input type="text" class="form-control" id="acceptName" name="name" value="<?= escapeHtml($contact_name) ?>" maxlength="200" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="acceptNote">A note for us</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                            <textarea class="form-control" id="acceptNote" name="note" rows="3" maxlength="2000" placeholder="Optional - a PO number, when to start, who to talk to"></textarea>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="acceptAuthorised" name="authorised" value="1" required>
                        <label class="form-check-label" for="acceptAuthorised">I can accept this quote for <?= escapeHtml($session_client_name) ?></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" name="portal_quote_answer"><i class="fas fa-check me-2"></i>Accept quote</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="declineQuoteModal" tabindex="-1" aria-labelledby="declineQuoteTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="post.php" method="post" autocomplete="off" data-portal-once>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                <input type="hidden" name="answer" value="decline">
                <div class="modal-header">
                    <h5 class="modal-title" id="declineQuoteTitle"><i class="fas fa-fw fa-thumbs-down me-2 text-danger"></i>Decline <?= escapeHtml($number) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">We'll be told. If something needs changing, say what and we'll send a new quote.</p>
                    <div class="mb-3">
                        <label class="form-label" for="declineName">Your name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            <input type="text" class="form-control" id="declineName" name="name" value="<?= escapeHtml($contact_name) ?>" maxlength="200" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="declineNote">What would change your mind?</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                            <textarea class="form-control" id="declineNote" name="note" rows="3" maxlength="2000" placeholder="Optional - the price, the timing, what is missing"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" name="portal_quote_answer"><i class="fas fa-times me-2"></i>Decline quote</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php
require_once "includes/footer.php";
