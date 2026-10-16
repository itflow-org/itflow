<?php

/*
 * Put tickets on an invoice - the client's billable tickets that are
 * resolved or closed and not on an invoice yet, with the time worked on
 * each. Tick any number. ?invoice_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);
if (!$invoice) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();

$tickets = invoiceUnbilledTickets($client_id);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring me-2"></i>Add Tickets <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body p-0">
        <?php if ($tickets) { ?>
            <div class="px-3 py-2 small text-body-secondary border-bottom">
                <?= escapeHtml($invoice['client_name']) ?>'s billable tickets, resolved or closed, that aren't on an invoice. Adding one marks it billed - its time isn't added as a line.
            </div>
            <div class="list-group list-group-flush" style="max-height: 24rem; overflow-y: auto;">
                <?php foreach ($tickets as $ticket) {
                    $ticket_id = intval($ticket['ticket_id']); ?>
                    <label class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                        <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>">
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate"><span class="font-monospace small text-body-secondary me-2"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></div>
                            <div class="small text-body-secondary">Opened <?= escapeHtml(date('Y-m-d', strtotime($ticket['ticket_created_at']))) ?></div>
                        </div>
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal" title="Time worked"><i class="far fa-clock me-1"></i><?= billingDuration($ticket['seconds']) ?></span>
                    </label>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="itflow-asset-empty">
                <i class="fas fa-life-ring fa-2x mb-2 opacity-50"></i>
                <div>No unbilled tickets - <?= escapeHtml($invoice['client_name']) ?>'s billable tickets are all on an invoice, or still open.</div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($tickets) { ?>
            <button type="submit" name="add_ticket_to_invoice" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= $tickets ? 'Cancel' : 'Close' ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
