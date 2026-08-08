<?php

/*
 * guest_calendar_feed.php
 * Read-only iCalendar (ICS) feed for a single ITFlow calendar
 *
 * Unauthenticated by design - Google Calendar and Nextcloud fetch subscription
 * URLs with no credentials at all, so possession of calendar_feed_key is the
 * only authorisation. The key is 32 URL-safe base64 characters (~192 bits).
 *
 * Deliberately does NOT include guest/includes/inc_all_guest.php (that emits the
 * guest HTML layout) and deliberately does NOT start a session - an unattended
 * fetcher hitting this every 15 minutes should not be creating session files.
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";
require_once "../includes/inc_set_timezone.php";

// How much of the calendar to publish. Recurring events are always included
// regardless of window, since their first occurrence can predate it.
define("FEED_MONTHS_PAST", 12);
define("FEED_MONTHS_FUTURE", 24);

// Never let a leaked feed URL end up in a search index
header("X-Robots-Tag: noindex, nofollow");

/*
 * Bad or missing key - identical response either way, so the endpoint cannot be
 * used to tell a wrong key from a revoked one
 */
function feedNotFound() {
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: text/plain; charset=utf-8");
    echo "Not found.";
    exit();
}

if (!isset($_GET['key']) || strlen($_GET['key']) < 16 || strlen($_GET['key']) > 64) {
    feedNotFound();
}

$feed_key = escapeSql($_GET['key']);

$sql = mysqli_query(
    $mysqli,
    "SELECT calendar_id, calendar_name FROM calendars
    WHERE calendar_feed_key = '$feed_key'
    AND calendar_feed_key IS NOT NULL
    AND calendar_archived_at IS NULL
    LIMIT 1"
);

if (mysqli_num_rows($sql) !== 1) {
    feedNotFound();
}

$calendar = mysqli_fetch_assoc($sql);
$calendar_id = intval($calendar['calendar_id']);

// Gather events for this calendar only
$window_start = date('Y-m-d H:i:s', strtotime('-' . FEED_MONTHS_PAST . ' months'));
$window_end = date('Y-m-d H:i:s', strtotime('+' . FEED_MONTHS_FUTURE . ' months'));

$events_sql = mysqli_query(
    $mysqli,
    "SELECT * FROM calendar_events
    WHERE event_calendar_id = $calendar_id
    AND event_archived_at IS NULL
    AND (
        (event_repeat IS NOT NULL AND event_repeat != '')
        OR event_start BETWEEN '$window_start' AND '$window_end'
    )
    ORDER BY event_start ASC"
);

$events = [];
while ($row = mysqli_fetch_assoc($events_sql)) {
    $events[] = $row;
}

$ics = buildCalendarFeedIcs($calendar, $events, $config_timezone, $config_base_url);

// Let clients that send If-None-Match skip the transfer entirely
$etag = '"' . md5($ics) . '"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header("HTTP/1.1 304 Not Modified");
    header("ETag: $etag");
    exit();
}

// Record that something is actually subscribed, without turning a hammered URL
// into one write per request
mysqli_query(
    $mysqli,
    "UPDATE calendars
    SET calendar_feed_accessed_at = NOW()
    WHERE calendar_id = $calendar_id
    AND (calendar_feed_accessed_at IS NULL OR calendar_feed_accessed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))"
);

$filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $calendar['calendar_name']) . '.ics';

header("Content-Type: text/calendar; charset=utf-8");
header("Content-Disposition: inline; filename=\"$filename\"");
// No explicit Content-Length - mod_deflate or nginx gzip would compress the body
// after this point and leave the declared length wrong, which some clients treat
// as a truncated response
header("Cache-Control: private, max-age=300");
header("ETag: $etag");

echo $ics;
