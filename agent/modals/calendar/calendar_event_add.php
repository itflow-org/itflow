<?php

/*
 * New Event - from the calendar (a day or a time range picked on it fills in
 * when), inside a client, or from a client's site, contact or ticket with
 * those already filled in. GET: client_id, start / end ('Y-m-d' or
 * 'Y-m-d H:i'), all_day, calendar_id, location_id, contact_id, title.
 */

require_once '../../../includes/modal_header.php';

$form_client_id = intval($_GET['client_id'] ?? 0);
// Opened from a contact or a site with no client in the link - theirs
if (!$form_client_id && intval($_GET['contact_id'] ?? 0)) {
    $form_client_id = intval(getFieldById('contacts', intval($_GET['contact_id']), 'contact_client_id'));
} elseif (!$form_client_id && intval($_GET['location_id'] ?? 0)) {
    $form_client_id = intval(getFieldById('locations', intval($_GET['location_id']), 'location_client_id'));
}
if ($form_client_id) {
    $client_id = $form_client_id;
    if (!hasClientAccess($client_id)) {
        echo json_encode(['error' => 'You do not have access to that client.']);
        exit;
    }
    enforceCalendarEvent(['event_client_id' => $client_id], 'edit');
}
$form_locked_client = $form_client_id && !empty($_GET['lock_client']);

// When - what was picked on the calendar, or the next whole hour
$start = ticketScheduleValue(str_replace('T', ' ', (string) ($_GET['start'] ?? '')) . (strlen((string) ($_GET['start'] ?? '')) === 10 ? ' 00:00:00' : ''));
$end = ticketScheduleValue(str_replace('T', ' ', (string) ($_GET['end'] ?? '')) . (strlen((string) ($_GET['end'] ?? '')) === 10 ? ' 00:00:00' : ''));
$all_day = !empty($_GET['all_day']);
if ($start === '') {
    $start = date('Y-m-d H:00:00', strtotime('+1 hour'));
}
if ($all_day) {
    $start = substr($start, 0, 10) . ' 00:00:00';
    // A picked range ends the day after its last day
    $end = $end !== '' ? date('Y-m-d 00:00:00', max(strtotime($start), strtotime(substr($end, 0, 10) . ' -1 day'))) : $start;
} elseif ($end === '' || $end <= $start) {
    $end = date('Y-m-d H:i:s', strtotime($start) + 3600);
}

$form = [
    'event_title' => (string) ($_GET['title'] ?? ''),
    'event_calendar_id' => intval($_GET['calendar_id'] ?? 0),
    'event_start' => $start,
    'event_end' => $end,
    'event_all_day' => $all_day ? 1 : 0,
    'event_location_id' => $form_client_id ? intval(assetClientOwnedId('location', $_GET['location_id'] ?? 0, $form_client_id)) : 0,
];
$form_attendees = [
    'agents' => [intval($session_user_id)],
    'contacts' => $form_client_id && intval(assetClientOwnedId('contact', $_GET['contact_id'] ?? 0, $form_client_id)) ? [intval($_GET['contact_id'])] : [],
    'emails' => [],
];
$form_invited = false;

if (!calendarList()) {
    echo json_encode(['error' => 'There is no calendar to put it on yet - ' . (calendarCanManage() ? 'add one first.' : 'ask an admin to add one.')]);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="far fa-fw fa-calendar-plus me-2"></i>New Event<?php if ($form_client_id) { ?> <small class="ms-1 opacity-75"><?= escapeHtml((string) getFieldById('clients', $form_client_id, 'client_name')) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require 'calendar_event_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_event" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add event</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/calendar_event_modal.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
