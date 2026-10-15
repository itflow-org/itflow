<?php

/*
 * guest_calendar_feed.php
 * Read-only iCalendar (ICS) feeds:
 *
 *   - a shared calendar (calendars.calendar_feed_key), published by an admin
 *   - an agent's own schedule (user_calendar_feeds.feed_key) - their scheduled
 *     tickets, the events they are on and the projects they manage - built as
 *     that agent, so it shows only what their role and client access let them
 *     see, and stops answering once they can no longer sign in
 *
 * Unauthenticated by design - Google Calendar, Outlook and Nextcloud fetch
 * subscription URLs with no credentials at all, so possession of the key is
 * the only authorisation. Keys are 32-40 URL-safe base64 characters.
 *
 * Deliberately does NOT include guest/includes/inc_all_guest.php (that emits the
 * guest HTML layout) and deliberately does NOT start a session - an unattended
 * fetcher hitting this every 15 minutes should not be creating session files.
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";
require_once "../includes/inc_set_timezone.php";

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

if (!isset($_GET['key']) || !is_string($_GET['key']) || strlen($_GET['key']) < 16 || strlen($_GET['key']) > 64) {
    feedNotFound();
}

$feed_key = escapeSql($_GET['key']);

// How much to publish. Repeating events are always included, since their first
// occurrence can predate the window.
$window_start = date('Y-m-d H:i:s', strtotime('-' . CALENDAR_FEED_MONTHS_PAST . ' months'));
$window_end = date('Y-m-d H:i:s', strtotime('+' . CALENDAR_FEED_MONTHS_FUTURE . ' months'));

$calendar = mysqli_fetch_assoc(mysqli_query(
    $mysqli,
    "SELECT calendar_color, calendar_feed_busy_only, calendar_id, calendar_name FROM calendars
    WHERE calendar_feed_key = '$feed_key'
    AND calendar_feed_key IS NOT NULL
    AND calendar_archived_at IS NULL
    LIMIT 1"
));

if ($calendar) {

    // A shared calendar - its events, with their sites
    $calendar_id = intval($calendar['calendar_id']);

    $events_sql = mysqli_query(
        $mysqli,
        "SELECT calendar_events.*, location_name, location_address, location_city, location_state, location_zip
        FROM calendar_events
        LEFT JOIN locations ON location_id = event_location_id AND event_location_id > 0
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

    $ics = buildCalendarFeedIcs($calendar, $events, $config_timezone);
    $filename = $calendar['calendar_name'];
    $touch = "UPDATE calendars SET calendar_feed_accessed_at = NOW() WHERE calendar_id = $calendar_id
        AND (calendar_feed_accessed_at IS NULL OR calendar_feed_accessed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))";

} else {

    // An agent's own schedule
    $feed = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_calendar_feeds.*, user_name FROM user_calendar_feeds
        JOIN users ON user_id = feed_user_id
        WHERE feed_key = '$feed_key' LIMIT 1"));

    // Unknown, or the agent can no longer sign in - which also stops their link
    if (!$feed || !calendarActAs($feed['feed_user_id'])) {
        feedNotFound();
    }

    $items = calendarUserFeedItems($feed, $window_start, $window_end);
    $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"));
    $name = ($feed['feed_scope'] === 'everyone' ? 'Team schedule' : $feed['user_name']) . ' - ' . ($company['company_name'] ?? 'ITFlow');

    $ics = icsCalendar(['name' => $name], $items, $config_timezone);
    $filename = $name;
    $feed_user_id = intval($feed['feed_user_id']);
    $touch = "UPDATE user_calendar_feeds SET feed_accessed_at = NOW() WHERE feed_user_id = $feed_user_id
        AND (feed_accessed_at IS NULL OR feed_accessed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))";
}

// Let clients that send If-None-Match skip the transfer entirely
$etag = '"' . md5($ics) . '"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header("HTTP/1.1 304 Not Modified");
    header("ETag: $etag");
    exit();
}

// Record that something is actually subscribed, without turning a hammered URL
// into one write per request
mysqli_query($mysqli, $touch);

$filename = (trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $filename), '_') ?: 'calendar') . '.ics';

header("Content-Type: text/calendar; charset=utf-8");
header("Content-Disposition: inline; filename=\"$filename\"");
// No explicit Content-Length - mod_deflate or nginx gzip would compress the body
// after this point and leave the declared length wrong, which some clients treat
// as a truncated response
header("Cache-Control: private, max-age=300");
header("ETag: $etag");

echo $ics;
