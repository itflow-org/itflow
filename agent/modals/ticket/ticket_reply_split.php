<?php

require_once '../../../includes/modal_header.php';

/*
 * Split into a new ticket - a client's reply made a ticket of its own, for a second problem raised
 * in a reply. It is for the same client and the person who wrote it, with what they wrote as its
 * details and their attachments; the reply leaves this ticket (split_ticket_reply).
 */

enforceUserPermission('module_support', 2);

$ticket_reply_id = intval($_GET['id'] ?? 0);
$reply = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_reply, ticket_reply_type, ticket_reply_ticket_id, ticket_reply_created_at, contact_name
    FROM ticket_replies LEFT JOIN contacts ON contact_id = ticket_reply_by
    WHERE ticket_reply_id = $ticket_reply_id AND ticket_reply_archived_at IS NULL"));
if (!$reply || $reply['ticket_reply_type'] !== 'Client') {
    echo json_encode(['error' => "That reply can't be split off - only a client's reply can."]);
    exit;
}
$row = ticketForModal($reply['ticket_reply_ticket_id'], 2, true);
$reference = escapeHtml(ticketReference($row));

// What it says, to start the subject with - its first words
$words = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div)[^>]*>/i', ' ', (string) $reply['ticket_reply'])), ENT_QUOTES, 'UTF-8')));
$words = preg_replace('/^Email from: .*? at [^:]*:-\s*/u', '', $words);
$suggested = mb_strlen($words) > 80 ? rtrim(mb_substr($words, 0, 77)) . '...' : $words;

$assigned = intval($row['ticket_assigned_to']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-code-branch me-2"></i>Split into a new ticket <small class="ms-1 opacity-75"><?= $reference ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_reply_id" value="<?= $ticket_reply_id ?>">
    <div class="modal-body">

        <div class="alert alert-info small">
            A new ticket for <strong><?= escapeHtml($row['client_name']) ?></strong><?= $reply['contact_name'] ? ' and <strong>' . escapeHtml($reply['contact_name']) . '</strong>' : '' ?>,
            with this reply as its details and its attachments. The reply leaves <strong><?= $reference ?></strong>, and a note on each ticket points to the other.
        </div>

        <div class="mb-3">
            <label>Subject <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="subject" maxlength="500" required autofocus value="<?= escapeHtml($suggested) ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label>Priority</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                        <select class="form-select select2" name="priority">
                            <?php foreach (TICKET_PRIORITIES as $priority) { ?>
                                <option <?= $priority === $row['ticket_priority'] ? 'selected' : '' ?> data-color="var(--bs-<?= ticketPriorityColor($priority) ?>)"><?= $priority ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label>Assign to</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <select class="form-select select2" name="assigned_to">
                            <option value="0">- Unassigned -</option>
                            <?php
                            $sql_agents = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users
                                WHERE user_type = 1" . serviceAccountsOutSql() . " AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                            while ($agent = mysqli_fetch_assoc($sql_agents)) {
                                $agent_id = intval($agent['user_id']); ?>
                                <option value="<?= $agent_id ?>" <?= $agent_id === $assigned ? 'selected' : '' ?><?= selectOptionUser($agent['user_id'], $agent['user_name'], $agent['user_avatar'] ?? '') ?>><?= escapeHtml($agent['user_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <label class="form-label small text-body-secondary mb-1">The reply</label>
        <div class="border rounded p-2 small prettyContent overflow-auto" style="max-height: 14rem"><?= documentHtmlPurify((string) $reply['ticket_reply']) ?></div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="split_ticket_reply" class="btn btn-primary text-bold"><i class="fas fa-code-branch me-2"></i>Make the new ticket</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>
<script src="<?= staticFileUrl('/js/pretty_content.js') ?>"></script>

<?php
require_once '../../../includes/modal_footer.php';
