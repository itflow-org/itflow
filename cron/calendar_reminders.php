<?php

/*
 * ITFlow - Reminders
 *
 * Reminds agents of what is coming up: an event on the calendar, at the time set on it,
 * and work booked on a ticket assigned to them, at the time they chose in Your account >
 * Preferences - a notification, and an email to those who asked for one
 * (calendarRemindersRun() in functions/calendar_reminders.php). Each goes once. Run by
 * cron/cron.php every minute; can also be run directly at any time.
 */

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Prevent overlapping runs of this script
$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

// Read the master switch here rather than trusting a global - see the cron rules in CONTRIBUTING.md
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));
if (intval($row['config_enable_cron']) === 0) {
    cronJobStop("Cron: is not enabled -- Quitting..");
}

if (!calendarRemindersReady()) {
    cronJobStop("Waiting for the database update (Maintenance > Update)");
}

$reminders_sent = calendarRemindersRun();
echo "Reminders: $reminders_sent sent\n";
