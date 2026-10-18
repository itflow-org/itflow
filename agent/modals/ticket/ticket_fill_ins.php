<?php

/*
 * A ticket's fill-ins - what its template asked for when it was raised - to
 * answer or change. Saved by edit_ticket_fill_ins (agent/post/ticket.php):
 * an answer given now also goes in wherever its [Name] is still waiting in
 * the subject, details or tasks.
 */

require_once '../../../includes/modal_header.php';

// The ticket, after the module and client checks - or the modal's error
$row = ticketForModal($_GET['id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);
$answers = ticketFillInsStored('ticket', $ticket_id);
if (!$answers) {
    echo json_encode(['error' => 'Ticket ' . ticketReference($row) . ' has nothing to fill in.']);
    exit;
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-pen-square me-2"></i>Fill-ins <small class="ms-1 opacity-75"><?= escapeHtml(ticketReference($row)) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <p class="small text-secondary">An answer given here also goes in wherever its <span class="font-monospace">[Name]</span> is still waiting in the subject, details or tasks. A changed answer is changed here and in the ticket's history - not in text it was already filled into.</p>

        <div class="row g-3">
            <?php foreach ($answers as $answer) { ?>
                <div class="col-md-6"><?= ticketFillInFieldHtml($answer, $answer['value'], false) ?></div>
            <?php } ?>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ticket_fill_ins" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
