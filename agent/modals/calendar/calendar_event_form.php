<?php

/*
 * The fields of New Event and Edit Event - included by calendar_event_add.php
 * and calendar_event_edit.php, which set $form (the event's values, raw),
 * $form_client_id (its client, 0 for none), $form_locked_client (opened
 * inside a client, so the client is not picked here), $form_attendees
 * (['agents' => ids, 'contacts' => ids, 'emails' => [...]]) and $form_invited
 * (whether anyone has been sent it already). The client's sites and contacts
 * follow the client picked (agent/js/calendar_event_modal.js).
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_all_day = !empty($form['event_all_day']);
$form_start_ts = strtotime($form['event_start'] ?? '') ?: strtotime(date('Y-m-d 09:00:00'));
$form_end_ts = !empty($form['event_end']) ? (strtotime($form['event_end']) ?: $form_start_ts + 3600) : $form_start_ts + 3600;
$form_repeat = isset(CALENDAR_REPEATS[$form['event_repeat'] ?? '']) ? $form['event_repeat'] : '';
$form_interval = max(1, intval($form['event_repeat_interval'] ?? 1));
$form_calendars = calendarList();
$form_calendar_id = intval($form['event_calendar_id'] ?? 0);
if (!isset($form_calendars[$form_calendar_id])) {
    $form_calendar_id = isset($form_calendars[intval($config_default_calendar ?? 0)]) ? intval($config_default_calendar) : intval(array_key_first($form_calendars) ?? 0);
}
$form_can_email = calendarCanEmail();

?>

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <label class="form-label">Title <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
            <input type="text" class="form-control" name="title" maxlength="200" value="<?= escapeHtml($form['event_title'] ?? '') ?>" placeholder="Onsite visit, Maintenance window, Quarterly review..." required autofocus>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Calendar <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
            <select class="form-select select2" name="calendar" required>
                <?php foreach ($form_calendars as $calendar_id => $calendar) { ?>
                    <option value="<?= $calendar_id ?>"<?= selectOptionColor(calendarColor($calendar['calendar_color'])) ?> <?= $calendar_id === $form_calendar_id ? 'selected' : '' ?>><?= escapeHtml($calendar['calendar_name']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</div>

<div class="itflow-cal-form-when border rounded p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="fw-semibold"><i class="far fa-fw fa-clock text-secondary me-1"></i>When</div>
        <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input" id="event_form_all_day" name="all_day" value="1" <?= $form_all_day ? 'checked' : '' ?>>
            <label class="form-check-label" for="event_form_all_day">All day</label>
        </div>
    </div>
    <div class="row g-2">
        <div class="col-6 col-md-3">
            <label class="form-label small text-body-secondary mb-1" for="event_form_start_date">Starts</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                <input type="date" class="form-control event-start-date" id="event_form_start_date" name="start_date" value="<?= date('Y-m-d', $form_start_ts) ?>" required>
            </div>
        </div>
        <div class="col-6 col-md-3<?= $form_all_day ? ' d-none' : '' ?> event_form_time_fields">
            <label class="form-label small text-body-secondary mb-1" for="event_form_start_time">at</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-clock"></i></span>
                <input type="time" class="form-control event-start-time" id="event_form_start_time" name="start_time" value="<?= date('H:i', $form_start_ts) ?>"<?= $form_all_day ? '' : ' required' ?>>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-body-secondary mb-1" for="event_form_end_date">Ends</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                <input type="date" class="form-control" id="event_form_end_date" name="end_date" value="<?= date('Y-m-d', $form_end_ts) ?>" required>
            </div>
        </div>
        <div class="col-6 col-md-3<?= $form_all_day ? ' d-none' : '' ?> event_form_time_fields">
            <label class="form-label small text-body-secondary mb-1" for="event_form_end_time">at</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-clock"></i></span>
                <input type="time" class="form-control" id="event_form_end_time" name="end_time" value="<?= date('H:i', $form_end_ts) ?>"<?= $form_all_day ? '' : ' required' ?>>
            </div>
        </div>
    </div>
    <div class="row g-2 align-items-end mt-1">
        <div class="col-md-4">
            <label class="form-label small text-body-secondary mb-1" for="eventRepeat">Repeats</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-redo"></i></span>
                <select class="form-select" name="repeat" id="eventRepeat">
                    <option value="" <?= $form_repeat === '' ? 'selected' : '' ?>>Does not repeat</option>
                    <?php foreach (['Day' => 'Daily', 'Week' => 'Weekly', 'Month' => 'Monthly', 'Year' => 'Yearly'] as $value => $label) { ?>
                        <option value="<?= $value ?>" data-one="<?= CALENDAR_REPEATS[$value][1] ?>" data-many="<?= CALENDAR_REPEATS[$value][2] ?>" <?= $form_repeat === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-6 col-md-4" id="eventRepeatEvery" <?= $form_repeat ? '' : 'hidden' ?>>
            <label class="form-label small text-body-secondary mb-1" for="eventRepeatInterval">Every</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-sync-alt"></i></span>
                <input type="number" class="form-control" name="repeat_interval" id="eventRepeatInterval" min="1" max="99" value="<?= $form_interval ?>">
                <span class="input-group-text" id="eventRepeatUnit"><?= $form_repeat ? CALENDAR_REPEATS[$form_repeat][$form_interval === 1 ? 1 : 2] : 'weeks' ?></span>
            </div>
        </div>
        <div class="col-6 col-md-4" id="eventRepeatUntilField" <?= $form_repeat ? '' : 'hidden' ?>>
            <label class="form-label small text-body-secondary mb-1" for="eventRepeatUntil">Until <span class="fw-normal">(optional)</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-hourglass-end"></i></span>
                <input type="date" class="form-control" name="repeat_until" id="eventRepeatUntil" value="<?= escapeHtml($form['event_repeat_until'] ?? '') ?>">
            </div>
        </div>
    </div>
    <?php if (calendarSkippedDates($form)) { ?>
        <div class="small text-body-secondary mt-2"><i class="fas fa-fw fa-forward me-1"></i>Skipped: <?= escapeHtml(implode(', ', array_map(function ($date) {
            return date('M j, Y', strtotime($date));
        }, calendarSkippedDates($form)))) ?></div>
    <?php } ?>
</div>

<div class="row g-3 mb-3">
    <?php if ($form_locked_client) { ?>
        <input type="hidden" name="client_id" value="<?= $form_client_id ?>">
    <?php } else { ?>
        <div class="col-md-6">
            <label class="form-label">Client</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                <select class="form-select select2" name="client_id" id="eventClientSelect">
                    <option value="0">- Internal, no client -</option>
                    <?php
                    // A client's event takes Clients at Modify (calendarEventCan())
                    $form_can_clients = calendarEventCan(['event_client_id' => -1], 'edit');
                    $sql = $form_can_clients ? mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE (client_archived_at IS NULL OR client_id = $form_client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC") : false;
                    while ($sql && $row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['client_id']) ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?> <?= intval($row['client_id']) === $form_client_id ? 'selected' : '' ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php if (!$form_can_clients) { ?>
                <div class="form-text">An event for a client takes Clients at Modify in your role.</div>
            <?php } ?>
        </div>
    <?php } ?>
    <div class="<?= $form_locked_client ? 'col-md-6' : 'col-md-6' ?>" id="eventSiteField" <?= $form_client_id ? '' : 'hidden' ?>>
        <label class="form-label">Site</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
            <select class="form-select select2" name="location_id" id="eventSiteSelect" data-selected="<?= intval($form['event_location_id'] ?? 0) ?>">
                <option value="0">- No site -</option>
                <?php
                if ($form_client_id) {
                    $sql = mysqli_query($mysqli, "SELECT location_id, location_name, location_address, location_city FROM locations WHERE location_client_id = $form_client_id AND (location_archived_at IS NULL OR location_id = " . intval($form['event_location_id'] ?? 0) . ") ORDER BY location_primary DESC, location_name");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['location_id']) ?>" data-icon="fas fa-map-marker-alt" data-description="<?= escapeHtml(implode(', ', array_filter([$row['location_address'], $row['location_city']]))) ?>" <?= intval($row['location_id']) === intval($form['event_location_id'] ?? 0) ? 'selected' : '' ?>><?= escapeHtml($row['location_name']) ?></option>
                    <?php }
                } ?>
            </select>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Location <span class="text-body-secondary small fw-normal">- a room, an address, or a Teams / Zoom link</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
            <input type="text" class="form-control" name="location" maxlength="500" value="<?= escapeHtml($form['event_location'] ?? '') ?>" placeholder="Server room, 2nd floor - or https://teams.microsoft.com/...">
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label d-block">Who <span class="text-body-secondary small fw-normal">- agents, the client's contacts, and anyone else by email</span></label>
    <div class="row g-2">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user-tie"></i></span>
                <select class="form-select select2" name="agents[]" multiple data-placeholder="Agents">
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND user_archived_at IS NULL AND (user_status = 1 OR user_id IN (0" . implode('', array_map(function ($id) {
                        return ',' . intval($id);
                    }, $form_attendees['agents'])) . ")) ORDER BY user_name");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['user_id']) ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar']) ?> <?= in_array(intval($row['user_id']), $form_attendees['agents'], true) ? 'selected' : '' ?>><?= escapeHtml($row['user_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-6" id="eventContactsField" <?= $form_client_id ? '' : 'hidden' ?>>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-address-book"></i></span>
                <select class="form-select select2" name="contacts[]" id="eventContactsSelect" multiple data-placeholder="Contacts">
                    <?php
                    if ($form_client_id) {
                        $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_photo FROM contacts WHERE contact_client_id = $form_client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_technical DESC, contact_name");
                        while ($row = mysqli_fetch_assoc($sql)) { ?>
                            <option value="<?= intval($row['contact_id']) ?>"<?= selectOptionContact($form_client_id, $row['contact_name'], $row['contact_photo'], $row['contact_email'] ?: 'No email', $row['contact_id']) ?> <?= in_array(intval($row['contact_id']), $form_attendees['contacts'], true) ? 'selected' : '' ?>><?= escapeHtml($row['contact_name']) ?></option>
                        <?php }
                    } ?>
                </select>
            </div>
        </div>
        <div class="col-12">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                <input type="text" class="form-control" name="emails" value="<?= escapeHtml(implode(', ', $form_attendees['emails'])) ?>" placeholder="Others by email - vendor@example.com, ...">
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Notes</label>
    <textarea class="form-control" rows="3" name="description" placeholder="What it is for, what to bring, who to ask for at reception..."><?= escapeHtml($form['event_description'] ?? '') ?></textarea>
</div>

<?php if ($form_can_email) { ?>
    <div class="form-check form-switch mb-0">
        <input type="checkbox" class="form-check-input" id="eventSendInvites" name="send_invites" value="1" <?= $form_invited ? 'checked' : '' ?>>
        <label class="form-check-label" for="eventSendInvites">
            <?= $form_invited ? 'Email the change to the people on it' : 'Email the invitation to the people on it' ?>
            <span class="d-block small text-body-secondary">With a calendar invitation their Outlook, Google or phone calendar adds<?= $form_invited ? ' - and updates when it changes' : '' ?></span>
        </label>
    </div>
<?php } else { ?>
    <div class="small text-body-secondary"><i class="fas fa-fw fa-info-circle me-1"></i>Email is not set up, so invitations cannot be sent - the people on it can still subscribe to their calendar link.</div>
<?php } ?>
