<?php

/*
 * ITFlow - each agent's own time zone (database 3.8.6, user_settings.user_config_timezone).
 *
 * Every request runs in the company's zone (Admin > Settings > Localization,
 * includes/inc_set_timezone.php) and bookings and events are kept as clock times
 * in it. An agent who works somewhere else picks their own zone in Account >
 * Preferences, and the calendar, the booking and event windows, the ticket's
 * booking and their own booking emails are on their clock. Nothing stored
 * changes: times going out to them are moved into their zone (timezoneToView(),
 * timezoneItems()), and times coming back - typed, dropped or dragged - are moved
 * back into the company's (timezoneFromView()) before anything else sees them.
 * The client's emails, calendar files and clash checks keep to company time.
 */

/** Whether the 3.8.6 update has run. */
function timezoneUsersReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.6', '>=');
    }
    return $ready;
}

/** The company's zone - the one stored times are in. */
function timezoneCompany() {
    return date_default_timezone_get();
}

/** An agent's own zone, or '' when they keep to the company's - read once a request. */
function timezoneOwn($user_id) {
    global $mysqli;
    static $zones = null;
    if ($zones === null) {
        $zones = [];
        $result = timezoneUsersReady() ? mysqli_query($mysqli, "SELECT user_id, user_config_timezone FROM user_settings WHERE user_config_timezone IS NOT NULL AND user_config_timezone <> ''") : false;
        while ($result && $row = mysqli_fetch_row($result)) {
            $zones[intval($row[0])] = (string) $row[1];
        }
    }
    $zone = $zones[intval($user_id)] ?? '';
    return $zone !== '' && $zone !== timezoneCompany() && in_array($zone, timezone_identifiers_list(), true) ? $zone : '';
}

/** The zone an agent's times are shown in - their own, else the company's. */
function timezoneOf($user_id) {
    return timezoneOwn($user_id) ?: timezoneCompany();
}

/** The zone the signed-in agent sees times in. */
function timezoneView() {
    $user_id = intval($GLOBALS['session_user_id'] ?? 0);
    return $user_id ? timezoneOf($user_id) : timezoneCompany();
}

/** Is a zone (the viewer's by default) away from the company's? */
function timezoneShifted($zone = null) {
    return ($zone ?? timezoneView()) !== timezoneCompany();
}

/*
 * A clock time moved from one zone to another, in the shape it came in
 * ('Y-m-d H:i:s', 'Y-m-d H:i', or with a T). A date alone, or anything else,
 * comes back as it was.
 */
function timezoneConvert($value, $from, $to) {
    $value = (string) $value;
    if ($from === $to || !preg_match('/^(\d{4}-\d{2}-\d{2})([ T])(\d{2}:\d{2})(:\d{2})?$/', $value, $m)) {
        return $value;
    }
    try {
        $date = new DateTime($m[1] . ' ' . $m[3] . ($m[4] ?? ''), new DateTimeZone($from));
        $date->setTimezone(new DateTimeZone($to));
    } catch (Throwable $e) {
        return $value;
    }
    return $date->format('Y-m-d') . $m[2] . $date->format(isset($m[4]) && $m[4] !== '' ? 'H:i:s' : 'H:i');
}

/** A stored (company) time as the viewer's clock reads it. */
function timezoneToView($value, $zone = null) {
    return timezoneConvert($value, timezoneCompany(), $zone ?? timezoneView());
}

/** A time on the viewer's clock as the company's clock reads it - what is stored. */
function timezoneFromView($value, $zone = null) {
    return timezoneConvert($value, $zone ?? timezoneView(), timezoneCompany());
}

/** Calendar items' start and end moved onto the viewer's clock - whole-day ones stay on their dates. */
function timezoneItems(array $items, $zone = null) {
    $zone = $zone ?? timezoneView();
    if (!timezoneShifted($zone)) {
        return $items;
    }
    foreach ($items as $i => $item) {
        if (!empty($item['allDay'])) {
            continue;
        }
        foreach (['start', 'end'] as $key) {
            if (!empty($item[$key])) {
                $items[$i][$key] = timezoneToView($item[$key], $zone);
            }
        }
    }
    return $items;
}

/** Now on the viewer's clock - 'Y-m-d\TH:i:s', for the calendar's now and a datetime field's earliest. */
function timezoneNow($zone = null) {
    try {
        return (new DateTime('now', new DateTimeZone($zone ?? timezoneView())))->format('Y-m-d\TH:i:s');
    } catch (Throwable $e) {
        return date('Y-m-d\TH:i:s');
    }
}

/** A zone's short name now - "JST", "PST", or "UTC+09" when it has none. */
function timezoneAbbr($zone) {
    try {
        $abbr = (new DateTime('now', new DateTimeZone($zone)))->format('T');
    } catch (Throwable $e) {
        return '';
    }
    return preg_match('/^[+-]\d/', $abbr) ? 'UTC' . $abbr : $abbr;
}

/** "Tokyo (JST)" - a zone as people say it. */
function timezoneName($zone) {
    $city = str_replace('_', ' ', substr(strrchr('/' . $zone, '/'), 1));
    $abbr = timezoneAbbr($zone);
    return $city . ($abbr !== '' ? " ($abbr)" : '');
}

/** The calendar's note when the viewer is away from the company's zone - '' when not. */
function timezoneNote() {
    $zone = timezoneView();
    if (!timezoneShifted($zone)) {
        return '';
    }
    return '<span class="badge bg-body-secondary text-body-secondary border fw-normal" title="Your time zone is in Account &gt; Preferences. Bookings are kept in the company\'s time.">'
        . '<i class="fas fa-globe me-1"></i>Times in ' . escapeHtml(timezoneName($zone)) . ' &middot; company: ' . escapeHtml(timezoneName(timezoneCompany())) . '</span>';
}

/** The line under a form's times when the viewer is away from the company's zone - '' when not. */
function timezoneFormHint() {
    $zone = timezoneView();
    if (!timezoneShifted($zone)) {
        return '';
    }
    return '<div class="form-text"><i class="fas fa-globe me-1"></i>Times in ' . escapeHtml(timezoneName($zone))
        . ' - kept in the company\'s time, ' . escapeHtml(timezoneName(timezoneCompany())) . '.</div>';
}

/** A drag or a drop on the calendar (start and end on the viewer's clock) with its times in the company's. Whole days keep their dates. */
function timezoneMovePost(array $post, $zone = null) {
    if (!empty($post['all_day']) || !timezoneShifted($zone)) {
        return $post;
    }
    foreach (['start', 'end'] as $key) {
        if (!empty($post[$key]) && is_string($post[$key])) {
            $post[$key] = timezoneFromView(trim($post[$key]), $zone);
        }
    }
    return $post;
}

/** New and Edit Event's day and time fields, typed on the viewer's clock, in the company's. Whole-day events keep their dates. */
function timezoneEventPost(array $post, $zone = null) {
    if (!empty($post['all_day']) || !timezoneShifted($zone)) {
        return $post;
    }
    foreach (['start', 'end'] as $key) {
        $date = (string) ($post[$key . '_date'] ?? ($key === 'end' ? ($post['start_date'] ?? '') : ''));
        $time = (string) ($post[$key . '_time'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            continue;
        }
        $moved = timezoneFromView("$date $time", $zone);
        $post[$key . '_date'] = substr($moved, 0, 10);
        $post[$key . '_time'] = substr($moved, 11, 5);
    }
    return $post;
}

/** An event's when (calendarWhenText()) on the viewer's clock, with the company's after it when they differ. */
function timezoneEventWhenText(array $event, $start, $end, $zone = null) {
    $zone = $zone ?? timezoneView();
    if (!empty($event['event_all_day']) || !timezoneShifted($zone)) {
        return calendarWhenText($event, $start, $end);
    }
    return calendarWhenText($event, timezoneToView($start, $zone), timezoneToView($end, $zone)) . ' ' . timezoneAbbr($zone)
        . ' (' . calendarWhenText($event, $start, $end) . ' ' . timezoneAbbr(timezoneCompany()) . ')';
}

/*
 * Booked work's when (ticketScheduleWhenText()) on an agent's own clock, with
 * the company's after it when they differ - for their booking emails.
 */
function timezoneWhenFor($start, $end, $user_id) {
    $company = ticketScheduleWhenText($start, $end);
    $zone = timezoneOf($user_id);
    if (!timezoneShifted($zone)) {
        return $company;
    }
    return ticketScheduleWhenText(timezoneToView($start, $zone), timezoneToView(ticketScheduleEnd($start, $end), $zone)) . ' ' . timezoneAbbr($zone)
        . " ($company " . timezoneAbbr(timezoneCompany()) . ')';
}

/** The My time zone field - Account > Preferences. Posts timezone ('' for the company's). Nothing before the update. */
function timezoneField($user_id, $col = 'col-md-6') {
    if (!timezoneUsersReady()) {
        return '';
    }
    $own = timezoneOwn($user_id);
    $html = '<div class="' . escapeHtml($col) . '"><label class="form-label">My time zone</label>'
        . '<div class="input-group"><span class="input-group-text"><i class="fas fa-fw fa-globe"></i></span>'
        . '<select class="form-select select2" name="timezone">'
        . '<option value="">The company\'s - ' . escapeHtml(timezoneName(timezoneCompany())) . '</option>';
    foreach (timezone_identifiers_list() as $zone) {
        if ($zone !== timezoneCompany()) {
            $html .= '<option value="' . escapeHtml($zone) . '"' . ($zone === $own ? ' selected' : '') . '>' . escapeHtml(str_replace('_', ' ', $zone)) . '</option>';
        }
    }
    return $html . '</select></div>'
        . '<div class="form-text">When you work away from the company. The calendar, bookings and your booking emails are on your clock.</div></div>';
}

/** Save the My time zone field - '' or one it doesn't know is the company's. */
function timezoneSave($user_id, array $post) {
    global $mysqli;
    $user_id = intval($user_id);
    if (!$user_id || !timezoneUsersReady() || !isset($post['timezone'])) {
        return;
    }
    $zone = (string) $post['timezone'];
    $value = in_array($zone, timezone_identifiers_list(), true) && $zone !== timezoneCompany() ? "'" . escapeSql($zone) . "'" : 'NULL';
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_timezone = $value ON DUPLICATE KEY UPDATE user_config_timezone = $value");
}
