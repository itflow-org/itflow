<?php

/*
 * Edit Event - everything the new event form has. Saving with "Email the
 * change" sends the update to the people who have it in their calendar, the
 * invitation to anyone added, and the cancellation to anyone taken off.
 */

require_once '../../../includes/modal_header.php';

$form = calendarEventForModal($_GET['id'] ?? 0);
$event_id = intval($form['event_id']);
$form_client_id = intval($form['event_client_id']);
$form_locked_client = false;

$form_attendees = ['agents' => [], 'contacts' => [], 'emails' => []];
$form_invited = false;
foreach (calendarEventAttendees($event_id) as $attendee) {
    if ($attendee['kind'] === 'agent') {
        $form_attendees['agents'][] = intval($attendee['attendee_user_id']);
    } elseif ($attendee['kind'] === 'contact') {
        $form_attendees['contacts'][] = intval($attendee['attendee_contact_id']);
    } else {
        $form_attendees['emails'][] = $attendee['email'];
    }
    if (!empty($attendee['attendee_invited_at'])) {
        $form_invited = true;
    }
}
$repeats = calendarEventRepeats($form);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-circle me-2 small" style="color:<?= escapeHtml(calendarColor($form['calendar_color'])) ?>"></i><?= escapeHtml($form['event_title']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="event_id" value="<?= $event_id ?>">

    <div class="modal-body">
        <?php if ($repeats) { ?>
            <div class="alert alert-info py-2 small"><i class="fas fa-fw fa-redo me-1"></i>This changes every occurrence. To drop a single one, use <strong>Skip this one</strong> on it.</div>
        <?php } ?>
        <?php require 'calendar_event_form.php'; ?>
    </div>
    <div class="modal-footer">
        <a class="btn btn-outline-danger me-auto confirm-link" href="post.php?delete_event=<?= $event_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-title="Delete <?= escapeHtml($form['event_title']) ?>?" data-confirm-button="Delete"
            <?php if ($form_invited) { ?>data-confirm-text="Everyone who was sent the invitation is emailed the cancellation."<?php } ?>>
            <i class="fas fa-trash-alt me-2"></i><?= $repeats ? 'Delete series' : 'Delete' ?>
        </a>
        <button type="submit" name="edit_event" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/calendar_event_modal.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
