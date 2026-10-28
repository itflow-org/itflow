<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_reply_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_reply, ticket_reply_time_worked, ticket_reply_type,
    " . (ticketReplyEmailReady() ? 'ticket_reply_emailed, ticket_reply_emailed_to' : 'NULL AS ticket_reply_emailed, NULL AS ticket_reply_emailed_to') . " FROM ticket_replies
    LEFT JOIN tickets ON ticket_id = ticket_reply_ticket_id
    WHERE ticket_reply_id = $ticket_reply_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
if (!$row) {
    echo json_encode(['error' => 'That reply no longer exists.']);
    exit;
}
$ticket_reply_type = escapeHtml($row['ticket_reply_type']);
$ticket_reply_time_worked = date_create($row['ticket_reply_time_worked']);
$ticket_reply_time_worked_formatted = date_format($ticket_reply_time_worked, 'H:i:s');
$ticket_reply = escapeHtml($row['ticket_reply']);
$client_id = intval($row['ticket_client_id']);

if ($client_id) {
    enforceClientAccess();
}

// Who it was emailed to - they have it as it was sent
$emailed_to = '';
if (intval($row['ticket_reply_emailed']) === 1) {
    $emailed_names = array_map(function ($recipient) {
        return (string) (($recipient['name'] ?? '') !== '' ? $recipient['name'] : ($recipient['email'] ?? ''));
    }, (array) json_decode((string) $row['ticket_reply_emailed_to'], true));
    $emailed_names = array_values(array_filter($emailed_names));
    $emailed_to = $emailed_names ? $emailed_names[0] . (count($emailed_names) > 1 ? ' and ' . (count($emailed_names) - 1) . ' more' : '') : 'the client';
}

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-edit me-2"></i>Edit reply</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_reply_id" value="<?= $ticket_reply_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <div class="btn-group w-100" role="group">
                <input class="btn-check" id="ticket_reply_type_opt0" type="radio" name="ticket_reply_type" value="Internal" <?php if ($ticket_reply_type == 'Internal') { echo "checked"; } ?>>
                <label class="btn btn-outline-secondary" for="ticket_reply_type_opt0"><i class="fas fa-fw fa-lock me-1"></i>Internal note</label>
                <input class="btn-check" id="ticket_reply_type_opt1" type="radio" name="ticket_reply_type" value="Public" <?php if ($ticket_reply_type == 'Public') { echo "checked"; } ?>>
                <label class="btn btn-outline-secondary" for="ticket_reply_type_opt1"><i class="fas fa-fw fa-eye me-1"></i>Public</label>
            </div>
            <small class="text-muted">Public replies show in the client portal. Saving here never sends an email.</small>
        </div>

        <?php if ($emailed_to !== '') { ?>
            <div class="alert alert-info small py-2" data-reply-edit-emailed>
                <i class="fas fa-fw fa-paper-plane me-1"></i>This was emailed to <strong><?= escapeHtml($emailed_to) ?></strong>. They keep the email as it was sent - what you change here only changes the ticket<?= $ticket_reply_type == 'Public' ? ' and the portal' : '' ?>.
            </div>
        <?php } ?>

        <?php
        // @ offers the agents who can open the ticket, while it is an internal note (functions/ticket_mention.php)
        $mention_attr = ticketMentionAgentsAttr($client_id);
        ?>
        <div class="mb-3">
            <textarea class="form-control tinymce" name="ticket_reply"<?= $mention_attr ?>><?= $ticket_reply ?></textarea>
            <?php if ($mention_attr !== '') { ?>
                <div class="form-text" data-mention-hint hidden><i class="fas fa-fw fa-at me-1"></i>Type @ to mention someone on your team - anyone you add is told in ITFlow and by email.</div>
                <div class="form-text text-warning-emphasis" data-mention-public hidden><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Mentions only tell people in an internal note - made public, they're plain text, and nobody is told.</div>
            <?php } ?>
        </div>

        <?php if (!empty($ticket_reply_time_worked)) { ?>
            <div class="col-6 col-md-3">
                <div class="mb-3">
                    <label>Time worked</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-stopwatch"></i></span>
                        <input class="form-control" name="time" type="text" placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):([0-5]?[0-9]):([0-5]?[0-9])" value="<?= $ticket_reply_time_worked_formatted ?>" required>
                    </div>
                </div>
            </div>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ticket_reply" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
