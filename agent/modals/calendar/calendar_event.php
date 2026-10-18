<?php

/*
 * Event quick look - opened from the calendar, the dashboard and a client's
 * overview. When and where (with the map and the meeting link), who is on it
 * and whether they have been sent it, and what it is for. From here: edit,
 * send the invitation, skip this one occurrence, download it, delete it.
 * GET: id, occurrence (Y-m-d, for one of a repeating event's days).
 */

require_once '../../../includes/modal_header.php';

$event = calendarEventForModal($_GET['id'] ?? 0);
$event_id = intval($event['event_id']);
$repeats = calendarEventRepeats($event);
$attendees = calendarEventAttendees($event_id);
$invited = array_filter($attendees, function ($attendee) {
    return !empty($attendee['attendee_invited_at']);
});
$emailable = array_filter($attendees, function ($attendee) {
    return filter_var($attendee['email'], FILTER_VALIDATE_EMAIL);
});

// The occurrence clicked - its day, at the event's time, the event's length
$start = $event['event_start'];
$end = $event['event_end'] ?: $start;
$occurrence = (string) ($_GET['occurrence'] ?? '');
if ($repeats && calendarIsDate($occurrence) && $occurrence !== substr($start, 0, 10)) {
    $length = strtotime($end) - strtotime($start);
    $start = $occurrence . ' ' . date('H:i:s', strtotime($event['event_start']));
    $end = date('Y-m-d H:i:s', strtotime($start) + $length);
} else {
    $occurrence = substr($start, 0, 10);
}

$where = calendarWhereText($event);
$map = calendarMapUrl($event);
$meeting = calendarMeetingUrl($event);
$color = calendarColor($event['calendar_color']);
$client_id = intval($event['event_client_id']);
$csrf = $_SESSION['csrf_token'];
// What the role allows with it (calendarEventCan())
$can_edit = calendarEventCan($event, 'edit');
$can_delete = calendarEventCan($event, 'delete');

ob_start();

?>
<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <span class="itflow-cal-tile" style="--itflow-cal-color: <?= escapeHtml($color) ?>"><i class="far fa-calendar-check"></i></span>
        <div class="min-w-0">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($event['event_title']) ?></h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3">
                <span><?= escapeHtml($event['calendar_name']) ?></span>
                <?php if ($client_id) { ?><span><?= escapeHtml($event['client_name']) ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <dl class="itflow-asset-dl mb-0">
        <dt>When</dt>
        <dd>
            <div class="fw-semibold"><?= escapeHtml(calendarWhenText($event, $start, $end)) ?></div>
            <?php if ($repeats) { ?>
                <div class="small text-body-secondary"><i class="fas fa-redo fa-fw me-1"></i><?= escapeHtml(calendarRepeatText($event)) ?>, from <?= date('M j, Y', strtotime($event['event_start'])) ?></div>
                <?php if (calendarSkippedDates($event)) { ?>
                    <div class="small text-body-secondary"><i class="fas fa-forward fa-fw me-1"></i>Skipped <?= escapeHtml(implode(', ', array_map(function ($date) {
                        return date('M j', strtotime($date));
                    }, calendarSkippedDates($event)))) ?></div>
                <?php } ?>
            <?php } ?>
        </dd>

        <?php if ($where !== '' || $meeting) { ?>
            <dt>Where</dt>
            <dd class="min-w-0">
                <?php if (!empty($event['location_name'])) { ?>
                    <div><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location.php?id=<?= intval($event['event_location_id']) ?>"><i class="fas fa-fw fa-map-marker-alt text-secondary me-1"></i><?= escapeHtml($event['location_name']) ?></a></div>
                    <?php if ($where !== $event['location_name']) { ?><div class="small text-body-secondary"><?= escapeHtml(substr($where, strlen($event['location_name']) + 3)) ?></div><?php } ?>
                    <?php if (trim((string) $event['event_location']) !== '' && stripos($where, trim($event['event_location'])) === false) { ?><div class="small text-break"><?= escapeHtml($event['event_location']) ?></div><?php } ?>
                <?php } elseif ($where !== '') { ?>
                    <div class="text-break"><?= escapeHtml($where) ?></div>
                <?php } ?>
                <div class="d-flex flex-wrap gap-2 mt-1">
                    <?php if ($meeting) { ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= escapeHtml($meeting) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-video me-1"></i>Join</a>
                    <?php } ?>
                    <?php if ($map) { ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= escapeHtml($map) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marked-alt me-1"></i>Map</a>
                    <?php } ?>
                    <?php if (!empty($event['location_phone'])) { ?>
                        <span class="small align-self-center"><i class="fas fa-phone fa-fw text-secondary"></i><?= contactPhoneLink($event['location_phone'], $event['location_phone_country_code']) ?></span>
                    <?php } ?>
                </div>
            </dd>
        <?php } ?>

        <?php if ($client_id) { ?>
            <dt>Client</dt>
            <dd><?php if (lookupUserPermission('module_client') >= 1) { ?><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($event['client_name']) ?></a><?php } else { ?><?= escapeHtml($event['client_name']) ?><?php } ?></dd>
        <?php } ?>

        <dt>Who</dt>
        <dd class="min-w-0">
            <?php if (!$attendees) { ?>
                <span class="text-body-secondary">Nobody added</span>
            <?php } ?>
            <?php foreach ($attendees as $attendee) {
                if ($attendee['kind'] === 'agent') {
                    $avatar = formatAssignee($attendee['attendee_user_id'], $attendee['user_name'], $attendee['user_avatar']);
                } elseif ($attendee['kind'] === 'contact') {
                    $avatar = contactLink($attendee['contact_client_id'], $attendee['attendee_contact_id'], $attendee['contact_name'], $attendee['contact_photo']);
                } else {
                    $avatar = '<span class="d-inline-flex align-items-center gap-1"><i class="fas fa-fw fa-envelope text-secondary"></i>' . escapeHtml($attendee['email']) . '</span>';
                }
                $phone = $attendee['kind'] === 'contact' ? (contactPhoneLink($attendee['contact_mobile'], $attendee['contact_mobile_country_code']) ?: contactPhoneLink($attendee['contact_phone'], $attendee['contact_phone_country_code'], $attendee['contact_extension'])) : '';
                ?>
                <div class="d-flex flex-wrap align-items-center column-gap-2 row-gap-1 mb-1 itflow-cal-attendee">
                    <?= $avatar ?>
                    <?php if ($phone) { ?><span class="small"><?= $phone ?></span><?php } ?>
                    <?php if ($attendee['attendee_invited_at']) { ?>
                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal" title="Sent <?= escapeHtml(date('M j, Y g:i A', strtotime($attendee['attendee_invited_at']))) ?>"><i class="fas fa-check me-1"></i>Invited</span>
                    <?php } elseif (!filter_var($attendee['email'], FILTER_VALIDATE_EMAIL)) { ?>
                        <span class="badge rounded-pill bg-body-secondary text-body-secondary fw-normal">No email</span>
                    <?php } ?>
                </div>
            <?php } ?>
        </dd>

        <?php if (!empty($event['organizer_name'])) { ?>
            <dt>Added by</dt>
            <dd><?= escapeHtml($event['organizer_name']) ?> <span class="small text-body-secondary"><?= escapeHtml(timeAgo($event['event_created_at'])) ?></span></dd>
        <?php } ?>
    </dl>

    <?php if (trim((string) $event['event_description']) !== '') { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Notes</div>
        <div class="itflow-service-text"><?= escapeHtml($event['event_description']) ?></div>
    <?php } ?>

</div>

<div class="modal-footer">
    <div class="dropdown dropup me-auto">
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-ellipsis-h"></i></button>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="post.php?export_event_ics=<?= $event_id ?>"><i class="fas fa-fw fa-download me-2"></i>Download .ics</a>
            <?php if ($repeats && $can_edit) { ?>
                <a class="dropdown-item confirm-link" href="post.php?skip_event_occurrence=<?= $event_id ?>&date=<?= escapeHtml($occurrence) ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-title="Skip <?= date('D, M j', strtotime($occurrence)) ?>?" data-confirm-button="Skip it"
                    data-confirm-text="Only this one is dropped - the rest of the series stays.<?= $invited ? ' Everyone who has the invitation is sent the update.' : '' ?>">
                    <i class="fas fa-fw fa-forward me-2"></i>Skip this one
                </a>
            <?php } ?>
            <?php if ($can_delete) { ?>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_event=<?= $event_id ?>&csrf_token=<?= $csrf ?>"
                    data-confirm-title="Delete <?= escapeHtml($event['event_title']) ?>?" data-confirm-button="Delete"
                    <?php if ($invited) { ?>data-confirm-text="Everyone who was sent the invitation is emailed the cancellation."<?php } ?>>
                    <i class="fas fa-fw fa-trash-alt me-2"></i><?= $repeats ? 'Delete series' : 'Delete' ?>
                </a>
            <?php } ?>
        </div>
    </div>
    <?php if ($can_edit && $emailable && calendarCanEmail()) { ?>
        <form action="post.php" method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <button type="submit" name="send_event_invites" class="btn btn-outline-secondary confirm-link"
                data-confirm-title="<?= $invited ? 'Send it again?' : 'Send the invitation?' ?>" data-confirm-button="Send"
                data-confirm-text="<?= count($emailable) ?> <?= count($emailable) === 1 ? 'person is' : 'people are' ?> emailed the invitation, for their calendar.">
                <i class="fas fa-paper-plane me-2"></i><?= $invited ? 'Resend' : 'Send invitation' ?>
            </button>
        </form>
    <?php } ?>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/calendar/calendar_event_edit.php?id=<?= $event_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } else { ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
    <?php } ?>
</div>

<?php
require_once '../../../includes/modal_footer.php';
