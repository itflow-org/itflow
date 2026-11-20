<?php

/*
 * Your account > Preferences - how ITFlow looks and behaves for you: light
 * or dark, compact rows, the day calendars start on, your time zone, your
 * colour on the calendar, how many rows a list shows, and your reminders
 * before work booked for you (functions/calendar_reminders.php).
 */

require_once "includes/inc_all_user.php";

$me = userRow($session_user_id);
$calendar_first_day = intval($me['user_config_calendar_first_day']);
$per_page = intval($me['user_config_records_per_page']) ?: 10;
$compact_rows = intval($me['user_config_compact_rows'] ?? 0);
$booking_reminder = isset($me['user_config_booking_reminder']) && $me['user_config_booking_reminder'] !== null ? intval($me['user_config_booking_reminder']) : null;
$reminder_email = intval($me['user_config_reminder_email'] ?? 0);

?>

<div class="card">
    <div class="card-header bg-dark">
        <h3 class="card-title"><i class="fas fa-fw fa-sliders-h me-2"></i>Preferences</h3>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="card-body">
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label d-block">Theme</label>
                    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Theme">
                        <input class="btn-check" type="radio" name="dark_mode" id="themeLight" value="0" autocomplete="off" <?= $user_config_theme_dark === 0 ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary itflow-theme-pick" for="themeLight">
                            <span class="itflow-mode-swatch is-light" aria-hidden="true"></span><i class="fas fa-sun me-2"></i>Light
                        </label>
                        <input class="btn-check" type="radio" name="dark_mode" id="themeDark" value="1" autocomplete="off" <?= $user_config_theme_dark === 1 ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary itflow-theme-pick" for="themeDark">
                            <span class="itflow-mode-swatch is-dark" aria-hidden="true"></span><i class="fas fa-moon me-2"></i>Dark
                        </label>
                    </div>
                </div>

                <?php if (compactRowsReady()) { ?>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="compact_rows" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="compact_rows" id="compactRows" value="1" <?= $compact_rows ? 'checked' : '' ?>>
                            <label class="form-check-label" for="compactRows">Compact rows</label>
                        </div>
                        <div class="form-text">Tighter rows on lists, so more fit on screen.</div>
                    </div>
                <?php } ?>

                <div class="col-md-6">
                    <label class="form-label">Calendars start on</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                        <select class="form-select select2" name="calendar_first_day">
                            <option value="0" <?= $calendar_first_day === 0 ? 'selected' : '' ?>>Sunday</option>
                            <option value="1" <?= $calendar_first_day === 1 ? 'selected' : '' ?>>Monday</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Rows on a list</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-list-ol"></i></span>
                        <select class="form-select select2" name="records_per_page">
                            <?php foreach ([5, 10, 20, 50, 100, 500] as $n) { ?>
                                <option value="<?= $n ?>" <?= $per_page === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">Also changed from the bottom of any list.</div>
                </div>

                <?= timezoneField($session_user_id, 'col-12') ?>

                <?= calendarAgentColorField($session_user_id, 'col-12', true) ?>

                <?php if (calendarRemindersReady()) { ?>
                    <div class="col-md-6">
                        <label class="form-label">Remind me before work booked for me</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-bell"></i></span>
                            <select class="form-select" name="booking_reminder">
                                <?= calendarReminderOptions($booking_reminder) ?>
                            </select>
                        </div>
                        <div class="form-text">Visits and calls booked on tickets assigned to you. Each event on the calendar has its own reminder.</div>
                    </div>
                    <div class="col-md-6 align-self-center">
                        <div class="form-check form-switch">
                            <input type="hidden" name="reminder_email" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="reminder_email" id="reminderEmail" value="1" <?= $reminder_email ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reminderEmail">Email me reminders too</label>
                        </div>
                        <div class="form-text">They always come as a notification.</div>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" name="edit_your_user_preferences" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save</button>
        </div>
    </form>
</div>

<?php
require_once "../../includes/footer.php";
