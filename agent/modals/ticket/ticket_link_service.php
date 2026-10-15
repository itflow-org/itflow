<?php

/*
 * Link a service to a ticket - an outage of email, a change to the backups.
 * The ticket then shows on the service's page with the tickets on its assets.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

// The ticket, after the module and client checks - or the modal's error
$row = ticketForModal($_GET['id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);
$client_id = intval($row['ticket_client_id']);
$reference = escapeHtml($row['ticket_prefix'] . $row['ticket_number']);

if (!$client_id) {
    echo json_encode(['error' => 'A ticket with no client has no services to link.']);
    exit;
}

$linked = ticketServices($ticket_id);
$sql = mysqli_query($mysqli, "SELECT service_id, service_name, service_description, service_category, service_importance FROM services
    WHERE service_client_id = $client_id AND service_archived_at IS NULL
    AND service_id NOT IN (SELECT service_id FROM service_tickets WHERE ticket_id = $ticket_id)
    ORDER BY FIELD(service_importance, 'High', 'Medium', 'Low'), service_name");
$candidates = [];
while ($candidate = mysqli_fetch_assoc($sql)) {
    $candidates[] = $candidate;
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stream me-2"></i>Link a service <small class="ms-1 opacity-75"><?= $reference ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <?php if ($candidates) { ?>
            <label class="form-label">Which service is this ticket about?</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-stream"></i></span>
                <select class="form-select select2" name="service_ids[]" multiple required data-placeholder="Pick one or more services">
                    <?php foreach ($candidates as $candidate) {
                        $via_asset = isset($linked[intval($candidate['service_id'])]); ?>
                        <option value="<?= intval($candidate['service_id']) ?>"<?= selectOptionService($candidate) ?>><?= escapeHtml($candidate['service_name']) ?><?= $via_asset ? ' (through its asset)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">The ticket shows on the service's page, in its Tickets tab.</div>
        <?php } else { ?>
            <div class="itflow-asset-empty py-4">
                <i class="fas fa-stream fa-2x mb-2"></i>
                <div><?= $linked ? 'Every service this client has is already linked.' : 'This client has no services yet.' ?></div>
            </div>
        <?php } ?>

    </div>

    <div class="modal-footer">
        <?php if ($candidates) { ?>
            <button type="submit" name="link_ticket_services" class="btn btn-primary text-bold"><i class="fas fa-link me-2"></i>Link</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
