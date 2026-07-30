<?php

// iCalendar (RFC 5545) generation for the read-only guest calendar feeds
// Consumed by guest/guest_calendar_feed.php


/*
 * Escapes a value for an iCalendar TEXT property (RFC 5545 3.3.11)
 * Backslashes are escaped first, otherwise the escapes added below get escaped again
 */
function icsEscapeText($text) {
    $text = str_replace("\\", "\\\\", (string) $text);
    $text = str_replace(["\r\n", "\r", "\n"], "\\n", $text);
    return str_replace([";", ","], ["\\;", "\\,"], $text);
}

/*
 * Folds a content line to 75 octets per RFC 5545 3.1
 * Continuation lines begin with a single space which counts toward the 75, so
 * $buffer already carries it. Folding happens on character boundaries so a
 * multi-byte UTF-8 sequence is never cut in half - splitting mid-sequence is
 * what makes hand-rolled feeds fail to parse on non-ASCII event titles.
 */
function icsFoldLine($line) {

    if (strlen($line) <= 75) {
        return $line;
    }

    $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);

    // Invalid UTF-8 - fold on byte boundaries rather than emit an over-long line
    if ($chars === false) {
        return rtrim(chunk_split($line, 74, "\r\n "), "\r\n ");
    }

    $folded = '';
    $buffer = '';

    foreach ($chars as $char) {
        if (strlen($buffer) + strlen($char) > 75) {
            $folded .= $buffer . "\r\n";
            $buffer = ' ';
        }
        $buffer .= $char;
    }

    return $folded . $buffer;
}

/*
 * Converts an ITFlow datetime to an iCalendar UTC DATE-TIME
 * Stored datetimes are in the instance's configured timezone. Emitting UTC for
 * everything means the feed needs no VTIMEZONE block at all, which removes the
 * largest source of breakage in hand-written ICS.
 */
function icsFormatUtc($datetime, $timezone) {

    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return null;
    }

    try {
        $dt = new DateTime($datetime, new DateTimeZone($timezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return null;
    }

    return $dt->format('Ymd\THis\Z');
}

/*
 * Converts an ITFlow datetime to an iCalendar DATE value (all-day events)
 * No timezone conversion - a floating date must stay on the day it was entered
 */
function icsFormatDate($datetime, $offset_days = 0) {

    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return null;
    }

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return null;
    }

    return date('Ymd', strtotime("$offset_days day", $timestamp));
}

/*
 * True when an event looks like an all-day event
 * Fallback for rows written before event_all_day existed (added in 2.5.9): a
 * midnight start, and either no end or a midnight end - the same rule the
 * calendar page itself rendered by, and the rule the 2.5.9 backfill applies.
 */
function icsEventIsAllDay($start, $end) {

    if (empty($start) || date('H:i:s', strtotime($start)) !== '00:00:00') {
        return false;
    }

    if (empty($end) || $end === '0000-00-00 00:00:00') {
        return true;
    }

    return date('H:i:s', strtotime($end)) === '00:00:00';
}

/*
 * Maps ITFlow's event_repeat wording onto an RRULE
 * The repeat select is disabled in both event modals as of 2.5.x, so this only
 * applies to rows created before it was disabled or after it is re-enabled
 */
function icsRepeatToRrule($repeat) {

    $map = [
        'Day'   => 'FREQ=DAILY',
        'Week'  => 'FREQ=WEEKLY',
        'Month' => 'FREQ=MONTHLY',
        'Year'  => 'FREQ=YEARLY'
    ];

    return $map[(string) $repeat] ?? null;
}

/*
 * Builds a complete VCALENDAR document for one calendar
 *
 * $calendar - the calendars row (name, color, feed settings)
 * $events   - array of calendar_events rows
 * $timezone - config_timezone, used to shift stored datetimes to UTC
 * $host     - config_base_url, used to build stable globally-unique UIDs
 *
 * Returns the document with CRLF line endings, ready to send.
 */
function buildCalendarFeedIcs(array $calendar, array $events, $timezone, $host) {

    $busy_only = !empty($calendar['calendar_feed_busy_only']);
    $calendar_name = (string) $calendar['calendar_name'];
    $host = $host ?: 'itflow.local';
    $now_utc = gmdate('Ymd\THis\Z');

    // X-WR-CALNAME is non-standard and clients render its value literally, so a
    // TEXT-escaped comma shows up as a visible backslash in the Google Calendar
    // sidebar. Strip only what would break the line structure.
    $calendar_name_header = str_replace(["\r\n", "\r", "\n"], ' ', $calendar_name);

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//ITFlow//ITFlow Calendar Feed//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . $calendar_name_header,
        'X-WR-TIMEZONE:' . $timezone,
        // Nextcloud reads REFRESH-INTERVAL / X-PUBLISHED-TTL and stores it against
        // the subscription - but only during a refresh run, and only if no rate is
        // stored yet. The first scheduled run is still gated by its own default
        // (P1D), so there is a cold start before this takes effect. Google ignores
        // it entirely.
        'REFRESH-INTERVAL;VALUE=DURATION:PT15M',
        'X-PUBLISHED-TTL:PT15M'
    ];

    if (!empty($calendar['calendar_color'])) {
        $lines[] = 'X-APPLE-CALENDAR-COLOR:' . $calendar['calendar_color'];
    }

    foreach ($events as $event) {

        $event_id = intval($event['event_id']);
        $start = $event['event_start'];
        $end = $event['event_end'];

        $lines[] = 'BEGIN:VEVENT';

        // Stable across edits and across feed regenerations, so subscribers
        // update events in place instead of duplicating them
        $lines[] = "UID:itflow-event-$event_id@$host";

        // Derived from the event rather than the request clock: a feed whose bytes
        // change on every fetch can never produce an ETag hit, which would defeat
        // the conditional-GET path in guest_calendar_feed.php
        $stamp = icsFormatUtc($event['event_updated_at'] ?: $event['event_created_at'], $timezone);
        $lines[] = 'DTSTAMP:' . ($stamp ?: $now_utc);

        // event_all_day is authoritative as of database version 2.5.9; the
        // heuristic remains as a fallback for a row that predates the backfill
        $all_day = isset($event['event_all_day'])
            ? !empty($event['event_all_day'])
            : icsEventIsAllDay($start, $end);

        if ($all_day) {

            $dtstart = icsFormatDate($start);

            // event_end holds the last day the event covers, which is what the event
            // modal asks for. DTEND is exclusive for DATE values, so it has to land
            // one day past that. A missing or non-advancing end still falls back to a
            // single day rather than rendering as zero-length.
            $dtend = icsFormatDate($end, 1);

            if (empty($dtend) || $dtend <= $dtstart) {
                $dtend = icsFormatDate($start, 1);
            }

            $lines[] = "DTSTART;VALUE=DATE:$dtstart";
            $lines[] = "DTEND;VALUE=DATE:$dtend";

        } else {

            $dtstart = icsFormatUtc($start, $timezone);

            // The event modals default a blank end to start + 1 hour client-side;
            // match that rather than emitting an instantaneous event
            $dtend = icsFormatUtc($end, $timezone);
            if (empty($dtend) || $dtend <= $dtstart) {
                $dtend = icsFormatUtc(date('Y-m-d H:i:s', strtotime("$start +1 hour")), $timezone);
            }

            $lines[] = "DTSTART:$dtstart";
            $lines[] = "DTEND:$dtend";
        }

        $rrule = icsRepeatToRrule($event['event_repeat'] ?? '');
        if ($rrule) {
            $lines[] = "RRULE:$rrule";
        }

        if ($busy_only) {

            // Titles, descriptions and locations all withheld - the feed shows
            // only that the time is occupied
            $lines[] = 'SUMMARY:Busy';
            $lines[] = 'TRANSP:OPAQUE';

        } else {

            $lines[] = 'SUMMARY:' . icsEscapeText($event['event_title']);

            if (!empty($event['event_location'])) {
                $lines[] = 'LOCATION:' . icsEscapeText($event['event_location']);
            }

            if (!empty($event['event_description'])) {
                $lines[] = 'DESCRIPTION:' . icsEscapeText($event['event_description']);
            }
        }

        if (!empty($event['event_created_at'])) {
            $created = icsFormatUtc($event['event_created_at'], $timezone);
            if ($created) {
                $lines[] = "CREATED:$created";
            }
        }

        // Lets well-behaved clients spot changed events without a SEQUENCE counter,
        // which calendar_events has nowhere to store
        if ($stamp) {
            $lines[] = "LAST-MODIFIED:$stamp";
        }

        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    $output = '';
    foreach ($lines as $line) {
        $output .= icsFoldLine($line) . "\r\n";
    }

    return $output;
}

/*
 * Expands a repeating event into concrete occurrences within a window
 *
 * ITFlow stores recurrence as a single row with event_repeat set to Day, Week,
 * Month or Year - there is no interval, count or until, and no per-occurrence
 * override. The calendar page needs real instances to render, because the bundled
 * FullCalendar build has no rrule plugin (adding one would mean vendoring rrule.js
 * as well). The ICS feed does not use this: it emits an RRULE and lets the
 * subscribing client do its own expansion.
 *
 * Offsets are computed from the original start rather than by stepping a running
 * date, so a long series cannot drift.
 *
 * Returns an array of ['start' => ..., 'end' => ...] datetime strings, always
 * including the original occurrence.
 */
function expandRecurringEvent(array $event, $window_start, $window_end, $limit = 750) {

    $start = $event['event_start'];
    $repeat = (string) ($event['event_repeat'] ?? '');

    if (empty($start) || !icsRepeatToRrule($repeat)) {
        return [['start' => $start, 'end' => $event['event_end']]];
    }

    try {
        $base = new DateTime($start);
        $from = new DateTime($window_start);
        $to = new DateTime($window_end);
    } catch (Exception $e) {
        return [['start' => $start, 'end' => $event['event_end']]];
    }

    // Preserve the original duration on every occurrence
    $duration = 0;
    if (!empty($event['event_end']) && $event['event_end'] !== '0000-00-00 00:00:00') {
        $duration = max(0, strtotime($event['event_end']) - strtotime($start));
    }

    $step = [
        'Day'   => 'day',
        'Week'  => 'week',
        'Month' => 'month',
        'Year'  => 'year'
    ][$repeat];

    $base_day = (int) $base->format('j');
    $occurrences = [];
    $i = 0;

    // Walk forward from the original start; a series that begins after the window
    // simply yields nothing until it reaches it
    while (count($occurrences) < $limit) {

        $candidate = clone $base;

        if ($i > 0) {
            $candidate->modify("+$i $step");

            // Monthly on the 31st, or yearly on Feb 29: PHP rolls the overflow into
            // the next month, which is not what a recurrence means. RFC 5545 skips
            // those occurrences, so skip them here too rather than silently moving
            // the event to the 1st or the 3rd.
            if (($step === 'month' || $step === 'year') && (int) $candidate->format('j') !== $base_day) {
                $i++;
                if ($candidate > $to) {
                    break;
                }
                continue;
            }
        }

        if ($candidate > $to) {
            break;
        }

        if ($candidate >= $from) {
            $occurrence_end = null;
            if ($duration > 0) {
                $end_dt = clone $candidate;
                $end_dt->modify("+$duration second");
                $occurrence_end = $end_dt->format('Y-m-d H:i:s');
            }
            $occurrences[] = [
                'start' => $candidate->format('Y-m-d H:i:s'),
                'end' => $occurrence_end
            ];
        }

        $i++;
    }

    return $occurrences;
}
