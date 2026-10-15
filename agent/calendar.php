<?php

/*
 * Calendar - the events on the calendars, and what the rest of ITFlow has
 * dated: scheduled tickets, project starts and due dates, service reviews,
 * recurring tickets, renewals and invoices falling due. Every client this
 * agent can reach, or one client (opened inside it).
 *
 * Each calendar and each source can be shown or hidden, and the whole thing
 * narrowed to one agent's work. Picking a day or a time range adds an event;
 * scheduled tickets and events that do not repeat can be dragged to a new
 * time, and the agent's open unscheduled tickets dragged onto the calendar to
 * book them. Items are fetched for the range on screen (ajax.php
 * ?calendar_items) - agent/js/calendar.js. ?date=Y-m-d opens on that day.
 */

if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $page_client_id = intval($client_id);
} else {
    require_once "includes/inc_all.php";
    $page_client_id = 0;
}

$calendars = calendarList();
$sources = calendarSources();
$can_manage = calendarCanManage();
$can_schedule = calendarCanScheduleTickets();
$csrf = $_SESSION['csrf_token'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_config_calendar_first_day FROM user_settings WHERE user_id = $session_user_id"));
$first_day = intval($row['user_config_calendar_first_day'] ?? 0);

$agents = [];
$sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $agents[] = $row;
}

$my_feed = calendarUserFeed($session_user_id);

// What the page script needs to know
$page_sources = [];
foreach ($sources as $key => $source) {
    $page_sources[$key] = ['default' => $source['default']];
}

?>

<div class="row g-3 itflow-calendar-page" id="itflowCalendar"
    data-csrf="<?= $csrf ?>"
    data-client-id="<?= $page_client_id ?>"
    data-user-id="<?= intval($session_user_id) ?>"
    data-first-day="<?= $first_day ?>"
    data-can-schedule="<?= $can_schedule ? 1 : 0 ?>"
    data-mobile="<?= !empty($session_mobile) ? 1 : 0 ?>"
    data-initial-date="<?= calendarIsDate($_GET['date'] ?? '') ? escapeHtml($_GET['date']) : '' ?>"
    data-sources="<?= escapeHtml(json_encode($page_sources)) ?>">

    <div class="col-lg-auto itflow-cal-side order-2 order-lg-1 d-print-none">

        <div class="card mb-3">
            <div class="card-body">

                <button type="button" class="btn btn-primary w-100 mb-3" id="calendarNewEvent"><i class="fas fa-plus me-2"></i>New event</button>

                <label class="form-label small text-body-secondary text-uppercase fw-semibold mb-1" for="calendarAgent">Whose</label>
                <select class="form-select select2 mb-3" id="calendarAgent">
                    <option value="0" data-icon="fas fa-users">Everyone</option>
                    <?php foreach ($agents as $agent) { ?>
                        <option value="<?= intval($agent['user_id']) ?>"<?= selectOptionUser($agent['user_id'], $agent['user_name'], $agent['user_avatar']) ?>><?= escapeHtml($agent['user_name']) ?><?= intval($agent['user_id']) === intval($session_user_id) ? ' (me)' : '' ?></option>
                    <?php } ?>
                </select>

                <div class="d-flex align-items-center mb-1">
                    <span class="small text-body-secondary text-uppercase fw-semibold">Calendars</span>
                    <?php if ($can_manage) { ?>
                        <button type="button" class="btn btn-sm btn-link text-body-secondary ms-auto p-0 ajax-modal" title="New calendar" data-modal-url="modals/calendar/calendar_add.php"><i class="fas fa-plus"></i></button>
                    <?php } ?>
                </div>
                <div class="mb-3" id="calendarCalendars">
                    <?php if (!$calendars) { ?>
                        <div class="small text-body-secondary">None yet<?= $can_manage ? '' : ' - an admin adds them' ?>.</div>
                    <?php } ?>
                    <?php foreach ($calendars as $calendar_id => $calendar) {
                        $color = calendarColor($calendar['calendar_color']); ?>
                        <div class="itflow-cal-toggle d-flex align-items-center gap-2" style="--itflow-cal-color: <?= escapeHtml($color) ?>">
                            <input class="form-check-input m-0 itflow-cal-check" type="checkbox" id="calendarShow<?= $calendar_id ?>" data-calendar="<?= $calendar_id ?>" checked>
                            <label class="flex-grow-1 text-truncate small" for="calendarShow<?= $calendar_id ?>"><?= escapeHtml($calendar['calendar_name']) ?></label>
                            <?php if (!empty($calendar['calendar_feed_key'])) { ?>
                                <i class="fas fa-rss text-body-secondary small" title="Shared as a subscription link"></i>
                            <?php } ?>
                            <?php if ($can_manage) { ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-body-secondary p-0 px-1" type="button" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/calendar/calendar_edit.php?id=<?= $calendar_id ?>"><i class="fas fa-fw fa-pen me-2"></i>Edit</a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/calendar/calendar_share.php?id=<?= $calendar_id ?>"><i class="fas fa-fw fa-share-alt me-2"></i><?= empty($calendar['calendar_feed_key']) ? 'Share' : 'Sharing' ?></a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_calendar=<?= $calendar_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Delete <?= escapeHtml($calendar['calendar_name']) ?>?" data-confirm-button="Delete"
                                            data-confirm-text="Its events are deleted with it, and anyone sent an invitation to one is emailed the cancellation."><i class="fas fa-fw fa-trash-alt me-2"></i>Delete</a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <?php if ($sources) { ?>
                    <div class="small text-body-secondary text-uppercase fw-semibold mb-1">From ITFlow</div>
                    <div id="calendarSources">
                        <?php foreach ($sources as $key => $source) { ?>
                            <div class="itflow-cal-toggle d-flex align-items-center gap-2" style="--itflow-cal-color: <?= $source['color'] ?>" title="<?= escapeHtml($source['title']) ?>">
                                <input class="form-check-input m-0 itflow-cal-check" type="checkbox" id="calendarSource<?= $key ?>" data-source="<?= $key ?>" <?= $source['default'] ? 'checked' : '' ?>>
                                <label class="flex-grow-1 text-truncate small" for="calendarSource<?= $key ?>"><i class="fas fa-fw <?= $source['icon'] ?> me-1 itflow-cal-toggle-icon"></i><?= $source['label'] ?></label>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>

            </div>
        </div>

        <?php if ($can_schedule) { ?>
            <div class="card mb-3">
                <div class="card-header py-2 d-flex align-items-center">
                    <span class="fw-semibold text-nowrap"><i class="fas fa-fw fa-inbox text-secondary me-1"></i>To schedule</span>
                    <span class="badge rounded-pill bg-body-secondary text-body-secondary ms-auto" id="calendarUnscheduledCount"></span>
                </div>
                <div class="card-body p-2">
                    <div class="small text-body-secondary px-1 mb-2" id="calendarUnscheduledHint">Open tickets with no time booked - drag one onto the calendar to book it.</div>
                    <div id="calendarUnscheduled"></div>
                </div>
            </div>
        <?php } ?>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fas fa-mobile-alt fa-fw text-secondary"></i>
                    <span class="fw-semibold small">Your schedule on your phone</span>
                </div>
                <div class="small text-body-secondary mb-2">
                    <?php if ($my_feed) { ?>
                        <?= $my_feed['feed_accessed_at'] ? 'Subscribed - last fetched ' . escapeHtml(timeAgo($my_feed['feed_accessed_at'])) : 'Your link is made - not subscribed yet' ?>
                    <?php } else { ?>
                        In Outlook, Google, Apple or Nextcloud, kept up to date
                    <?php } ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 ajax-modal" data-modal-size="lg" data-modal-url="modals/calendar/calendar_feed.php" id="calendarMyLink"><i class="fas fa-link me-1"></i><?= $my_feed ? 'My calendar link' : 'Set it up' ?></button>
            </div>
        </div>

    </div>

    <div class="col-lg order-1 order-lg-2 min-w-0">
        <div class="card">
            <div class="card-body p-2 p-md-3">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

</div>

<?php require_once "../includes/footer.php"; ?>

<!-- FullCalendar v7: theme + CSS are separate plugins, loaded alongside the core bundle -->
<link href="/libs/fullcalendar/skeleton.css" rel="stylesheet">
<link href="/libs/fullcalendar/themes/classic/theme.css" rel="stylesheet">
<link href="/libs/fullcalendar/themes/classic/palette.css" rel="stylesheet">
<script src="/libs/fullcalendar/fullcalendar.global.js"></script>
<script src="/libs/fullcalendar/themes/classic/global.js"></script>
<script src="/agent/js/calendar.js"></script>
