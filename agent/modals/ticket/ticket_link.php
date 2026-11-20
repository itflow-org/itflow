<?php

/*
 * Link related tickets (functions/ticket_link.php) - pick the tickets this one is about the same
 * thing as, of any client the agent can open. Posts link_tickets (agent/post/ticket_link.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

// The ticket, after the module and client checks - or the modal's error
$row = ticketForModal($_GET['ticket_id'] ?? 0, 2);
$ticket_id = intval($row['ticket_id']);
$reference = escapeHtml(ticketReference($row));

$candidates = ticketLinksReady() ? ticketLinkCandidates($ticket_id) : [];
$related_count = count(ticketRelated($ticket_id));

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-link me-2"></i>Link related tickets <small class="ms-1 opacity-75"><?= $reference ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <p class="small text-body-secondary">
            Tickets about the same thing - one outage at several clients, or a problem that keeps coming back. Each shows the others, and one update or resolve can go to them all.
            <?php if ($related_count) { ?>
                <strong><?= $reference ?></strong> is related to <?= $related_count ?> already - the ones you pick join them.
            <?php } ?>
        </p>

        <div class="mb-3">
            <label class="form-label">Related to <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                <select class="form-select select2" name="related_ticket_ids[]" data-placeholder="- Pick tickets -" multiple required>
                    <?php foreach ($candidates as $candidate) {
                        $is_open = ticketIsOpen($candidate);
                        $client = $candidate['client_name'] !== null ? $candidate['client_name'] : 'Internal'; ?>
                        <option value="<?= intval($candidate['ticket_id']) ?>"<?= selectOptionData([
                            'icon' => 'fas fa-life-ring',
                            'description' => $client . ' · ' . ($candidate['ticket_status_name'] ?? '') . ($is_open ? '' : ' - ' . timeAgo($candidate['ticket_closed_at'] ?: $candidate['ticket_resolved_at'])),
                            'keywords' => $client,
                        ]) ?>><?= escapeHtml(ticketReference($candidate) . ' ' . $candidate['ticket_subject']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Open tickets, and those closed in the last 90 days. Search by number, subject or client.</div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="link_tickets" class="btn btn-primary text-bold"><i class="fas fa-link me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
