<?php

/*
 * Link tickets already raised to a service - the outage reported before
 * anyone said which service it was. The client's tickets from the last year,
 * open ones first, not already on the service.
 */

require_once '../../../includes/modal_header.php';

$service = serviceForModal($_GET['id'] ?? 0, 2, true);
$service_id = intval($service['service_id']);

$sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_created_at, ticket_resolved_at, ticket_closed_at FROM tickets
    WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL
    AND ticket_id NOT IN (SELECT ticket_id FROM service_tickets WHERE service_id = $service_id)
    AND (" . TICKET_OPEN_SQL . " OR ticket_created_at > NOW() - INTERVAL 1 YEAR)
    ORDER BY " . TICKET_OPEN_SQL . " DESC, ticket_number DESC LIMIT 300");
$candidates = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $candidates[] = $row;
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-life-ring me-2"></i>Tickets about <strong><?= escapeHtml($service['service_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="service_id" value="<?= $service_id ?>">
    <div class="modal-body">
        <?php if ($candidates) { ?>
            <label class="form-label">Which tickets?</label>
            <select class="form-select select2" name="ticket_ids[]" multiple required data-placeholder="Search by number or subject">
                <?php foreach ($candidates as $row) {
                    $is_open = ticketIsOpen($row); ?>
                    <option value="<?= intval($row['ticket_id']) ?>" data-icon="fas fa-<?= $is_open ? 'life-ring' : 'check-circle' ?>"
                        data-description="<?= escapeHtml($row['ticket_prefix'] . $row['ticket_number'] . ' · ' . ($is_open ? 'Open' : 'Closed') . ' · ' . date('M j, Y', strtotime($row['ticket_created_at']))) ?>"
                        data-keywords="<?= escapeHtml($row['ticket_prefix'] . $row['ticket_number']) ?>"><?= escapeHtml($row['ticket_subject']) ?></option>
                <?php } ?>
            </select>
            <div class="form-text">Tickets on its assets show on the service already - this is for the rest.</div>
        <?php } else { ?>
            <div class="itflow-asset-empty py-4">
                <i class="fas fa-life-ring fa-2x mb-2"></i>
                <div>No other tickets for this client in the last year.</div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($candidates) { ?>
            <button type="submit" name="link_service_tickets" class="btn btn-primary text-bold"><i class="fas fa-link me-2"></i>Link</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
