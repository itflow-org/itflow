<?php

/*
 * Texts > Unmatched - Open ticket: a ticket from a text from a number that isn't a contact's,
 * for the client and contact picked (or no client). Every unmatched text from the number in
 * the last SMS_CONVERSATION_DAYS days goes on it, and what the number texts next goes on it
 * too. The number can be saved as the contact's mobile, so their next text finds them
 * (sms_open_ticket, agent/post/texts.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$row = smsMessageRow($_GET['id'] ?? 0);
if (!$row || $row['sms_message_direction'] !== 'in' || $row['sms_message_status'] !== 'unmatched' || !hasClientAccess($row['sms_message_client_id'])) {
    echo json_encode(['error' => 'That text isn\'t waiting in Unmatched any more - someone may have opened a ticket from it.']);
    exit;
}
$number = (string) $row['sms_message_number'];
$texts = mysqli_fetch_all(mysqli_query($mysqli, "SELECT sms_message_id, sms_message_body, sms_message_media, sms_message_created_at FROM sms_messages
    WHERE sms_message_direction = 'in' AND sms_message_status = 'unmatched' AND sms_message_number = '" . escapeSql($number) . "'
    AND sms_message_created_at > NOW() - INTERVAL " . SMS_CONVERSATION_DAYS . " DAY ORDER BY sms_message_id ASC LIMIT 20"), MYSQLI_ASSOC);
$first_line = trim(strtok((string) ($texts[0]['sms_message_body'] ?? $row['sms_message_body']), "\n") ?: '');
$subject = $first_line !== '' ? mb_strimwidth($first_line, 0, 80, '...') : 'Text from ' . smsNumberText($number);
$client_id = intval($row['sms_message_client_id']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-life-ring me-2"></i>Open a ticket <small class="ms-1 opacity-75">from <?= escapeHtml(smsNumberText($number)) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="id" value="<?= intval($row['sms_message_id']) ?>">

    <div class="modal-body">

        <div class="border rounded bg-body-tertiary p-2 mb-3 small" style="max-height: 12rem; overflow-y: auto;">
            <?php foreach ($texts as $text) {
                $media = $text['sms_message_media'] ? (json_decode($text['sms_message_media'], true) ?: []) : []; ?>
                <div class="mb-2">
                    <div class="text-body-secondary"><?= escapeHtml(date('M j, g:i A', strtotime($text['sms_message_created_at']))) ?><?= $media ? ' &middot; <i class="fas fa-paperclip"></i> ' . count($media) : '' ?></div>
                    <div class="text-break"><?= nl2br(escapeHtml((string) $text['sms_message_body'])) ?></div>
                </div>
            <?php } ?>
        </div>
        <?php if (count($texts) > 1) { ?>
            <p class="small text-body-secondary">All <?= count($texts) ?> go on the ticket, and what this number texts next goes on it too.</p>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="client_select">Client</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <select class="form-select select2" name="client_id" id="client_select">
                    <option value="">- No client -</option>
                    <?php
                    $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_lead = 0 AND client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($client = mysqli_fetch_assoc($sql_clients)) {
                        $client_id_select = intval($client['client_id']); ?>
                        <option value="<?= $client_id_select ?>" <?= $client_id === $client_id_select ? 'selected' : '' ?><?= selectOptionClient($client['client_id'], $client['client_name'], $client['client_abbreviation'] ?? '') ?>><?= escapeHtml($client['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="contact_select">Contact</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-select select2" name="contact_id" id="contact_select">
                    <option value="">- Pick the client first -</option>
                </select>
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="save_number" value="1" id="smsSaveNumber" checked>
                <label class="form-check-label small" for="smsSaveNumber">Save <?= escapeHtml(smsNumberText($number)) ?> as their mobile, if they have none - so their next text finds them</label>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="smsTicketSubject">Subject <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="subject" id="smsTicketSubject" maxlength="500" required value="<?= escapeHtml($subject) ?>">
            </div>
        </div>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="assign_me" value="1" id="smsAssignMe" checked>
            <label class="form-check-label" for="smsAssignMe">Assign it to me</label>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="sms_open_ticket" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Open ticket</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/ticket_change_client.js"></script>

<?php require_once '../../../includes/modal_footer.php'; ?>
