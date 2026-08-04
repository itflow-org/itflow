<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$calendar_id = intval($_POST['calendar']);
$title = escapeSql($_POST['title']);
$location = escapeSql($_POST['location']);
$description = escapeSql($_POST['description']);
$all_day = isset($_POST['all_day']) ? 1 : 0;

/*
 * The form posts the date and the time as separate fields, so recombine them into
 * the DATETIME columns.
 *
 * All-day events are pinned to midnight at both ends, and event_end holds the LAST
 * DAY the event covers - the same thing the form asks for. Both FullCalendar and
 * iCalendar treat an all-day end as exclusive, so the render and feed paths add a
 * day; do not add one here as well.
 */
$start_date = $_POST['start_date'] ?? '';
$end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : $start_date;

if ($all_day) {
    $start_raw = "$start_date 00:00:00";
    $end_raw = "$end_date 00:00:00";
} else {
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : '00:00';
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : $start_time;
    $start_raw = "$start_date $start_time";
    $end_raw = "$end_date $end_time";
}

// A malformed value would otherwise land in 1970 via strtotime() returning false
$start_ts = strtotime($start_raw) ?: time();
$end_ts = strtotime($end_raw) ?: $start_ts;

$start = escapeSql(date('Y-m-d H:i:s', $start_ts));
$end = escapeSql(date('Y-m-d H:i:s', $end_ts));

$repeat = escapeSql($_POST['repeat'] ?? 0);
$client_id = intval($_POST['client_id']);
$email_event = intval($_POST['email_event'] ?? 0);
