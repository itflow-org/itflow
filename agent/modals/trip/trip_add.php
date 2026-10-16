<?php

/*
 * New Trip. ?client_id= starts it with a client, ?location_id= with their
 * site as where to (from a location's page), ?ticket_id= with the ticket it
 * is for - its client, site and subject as the purpose (Log Trip on a
 * ticket).
 */

require_once '../../../includes/modal_header.php';

$form_row = null;
$form_mode = 'new';
$form_client_id = intval($_GET['client_id'] ?? 0);
$form_location_id = intval($_GET['location_id'] ?? 0);
$form_ticket_id = intval($_GET['ticket_id'] ?? 0);
$form_purpose = '';
if ($form_ticket_id) {
    $ticket = ticketRow($form_ticket_id);
    if ($ticket && intval($ticket['ticket_client_id']) && hasClientAccess(intval($ticket['ticket_client_id']))) {
        $form_client_id = intval($ticket['ticket_client_id']);
        $form_location_id = $form_location_id ?: intval($ticket['ticket_location_id']);
        $form_purpose = mb_substr('Onsite - ' . $ticket['ticket_subject'], 0, 200);
    } else {
        $form_ticket_id = 0;
    }
}
if ($form_client_id) {
    $client_id = $form_client_id;
    enforceClientAccess();
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-route me-2"></i><?= $form_ticket_id ? 'Log Trip <span class="fw-normal text-white-50 ms-1">' . escapeHtml(ticketReference($ticket)) . '</span>' : 'New Trip' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/trip_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_trip" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Log Trip</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
