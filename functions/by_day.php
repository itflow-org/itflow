<?php

/*
 * ITFlow - Repeats by weekday: "the third Wednesday" or "the last Friday" of the month, for
 * recurring tickets and repeating calendar events (database 3.8.3).
 *
 * A rule is kept the way calendars write it (RFC 5545 BYDAY): an ordinal and a weekday - 1 to 4
 * for the first to fourth, -1 for the last - so "3WE" is the third Wednesday and "-1FR" the last
 * Friday. No rule is the same date each month, as before. The choices offered come from the date
 * picked (byDayOptions()), so a rule always matches its first run, and a rule moves with its date
 * when that's changed (byDayFollow()). js/repeat_on.js draws the Repeats on field from the date.
 *   recurring_tickets.recurring_ticket_by_day - monthly, quarterly, twice-yearly and yearly ones
 *   calendar_events.event_repeat_by_day       - monthly and yearly events
 */

const BY_DAY_WEEKDAYS = ['MO' => 'Monday', 'TU' => 'Tuesday', 'WE' => 'Wednesday', 'TH' => 'Thursday', 'FR' => 'Friday', 'SA' => 'Saturday', 'SU' => 'Sunday'];
const BY_DAY_ORDINALS = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', -1 => 'last'];

/** Whether the 3.8.3 update has run. */
function byDayReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.3', '>=');
    }
    return $ready;
}

/** The rule tidied ("3WE", "-1FR"), or '' when it isn't one. */
function byDayRule($rule) {
    $rule = strtoupper(trim((string) $rule));
    return preg_match('/^(-1|[1-4])(MO|TU|WE|TH|FR|SA|SU)$/', $rule) ? $rule : '';
}

/** [ordinal, weekday code] of a rule - [0, ''] for none. */
function byDayParts($rule) {
    $rule = byDayRule($rule);
    return $rule === '' ? [0, ''] : [intval(substr($rule, 0, -2)), substr($rule, -2)];
}

/** The date ('Y-m-d') a rule falls on in a month - the first to fourth and the last of a weekday are in every month. */
function byDayDate($year, $month, $rule) {
    [$ordinal, $code] = byDayParts($rule);
    if (!$ordinal) {
        return null;
    }
    $weekday = array_search($code, array_keys(BY_DAY_WEEKDAYS)) + 1;  // ISO, 1 (Monday) to 7 (Sunday)
    $days = intval(date('t', mktime(0, 0, 0, $month, 1, $year)));
    if ($ordinal > 0) {
        $first = intval(date('N', mktime(0, 0, 0, $month, 1, $year)));
        $day = 1 + (($weekday - $first + 7) % 7) + ($ordinal - 1) * 7;
    } else {
        $last = intval(date('N', mktime(0, 0, 0, $month, $days, $year)));
        $day = $days - (($last - $weekday + 7) % 7);
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/** "the third Wednesday", "the last Friday" - '' for no rule. */
function byDayText($rule) {
    [$ordinal, $code] = byDayParts($rule);
    return $ordinal ? 'the ' . BY_DAY_ORDINALS[$ordinal] . ' ' . BY_DAY_WEEKDAYS[$code] : '';
}

/*
 * The weekday rules a date can repeat on: its weekday of the month when that's the first to
 * fourth, and the last when it's in the month's last seven days - [rule => "the third Wednesday"].
 */
function byDayOptions($date) {
    $ts = strtotime(substr((string) $date, 0, 10));
    if (!$ts) {
        return [];
    }
    $code = array_keys(BY_DAY_WEEKDAYS)[intval(date('N', $ts)) - 1];
    $day = intval(date('j', $ts));
    $options = [];
    $nth = intdiv($day - 1, 7) + 1;
    if ($nth <= 4) {
        $options[$nth . $code] = byDayText($nth . $code);
    }
    if ($day + 7 > intval(date('t', $ts))) {
        $options['-1' . $code] = byDayText('-1' . $code);
    }
    return $options;
}

/** A posted rule, kept only when the date can repeat on it - else ''. */
function byDayForDate($rule, $date) {
    $rule = byDayRule($rule);
    return $rule !== '' && isset(byDayOptions($date)[$rule]) ? $rule : '';
}

/*
 * A rule carried to a new date - a run or an event moved by hand: kept while the new date still
 * fits it, otherwise the same kind of rule for the new date, the last staying the last when it can.
 */
function byDayFollow($rule, $date) {
    $rule = byDayRule($rule);
    if ($rule === '' || byDayForDate($rule, $date) !== '') {
        return $rule;
    }
    $options = array_keys(byDayOptions($date));
    $last = array_values(array_filter($options, function ($option) { return strpos($option, '-1') === 0; }));
    $nth = array_values(array_filter($options, function ($option) { return strpos($option, '-1') !== 0; }));
    if (strpos($rule, '-1') === 0) {
        return $last[0] ?? ($nth[0] ?? '');
    }
    return $nth[0] ?? ($last[0] ?? '');
}

/*
 * The Repeats on field (js/repeat_on.js fills it from the date): $date and $frequency are
 * selectors for those fields in the same form, $months the frequency values it's for, $yearly
 * those worded "of October". Posts repeat_on - nothing while hidden, so repeat_on_field says the
 * form had it. Nothing before the update.
 */
function byDayField($date, $frequency, array $months, array $yearly, $current, $wrap_class = 'mb-3', $label_class = '') {
    if (!byDayReady()) {
        return '';
    }
    return '<input type="hidden" name="repeat_on_field" value="1"><div class="' . escapeHtml($wrap_class) . '" data-repeat-on-wrap hidden>'
        . '<label' . ($label_class !== '' ? ' class="' . escapeHtml($label_class) . '"' : '') . '>Repeats on</label>'
        . '<div class="input-group"><span class="input-group-text"><i class="fas fa-fw fa-calendar-week"></i></span>'
        . '<select class="form-select select2" name="repeat_on" data-repeat-on data-date="' . escapeHtml($date) . '" data-frequency="' . escapeHtml($frequency) . '"'
        . ' data-months="' . escapeHtml(implode(',', $months)) . '" data-yearly="' . escapeHtml(implode(',', $yearly)) . '" data-current="' . escapeHtml(byDayRule($current)) . '" disabled></select></div>'
        . '<div class="form-text">The same date each time, or the same weekday - the third Wednesday, or the last Friday.</div>'
        . '</div><script src="' . staticFileUrl('/js/repeat_on.js') . '"></script>';
}
