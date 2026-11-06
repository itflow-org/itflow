<?php

/*
 * ITFlow - Text Messages job
 *
 * Sends the texts waiting to go (smsQueueRun() in functions/sms.php) - a reply too long for
 * one text, one that couldn't go the first time, one queued while the service was busy -
 * oldest first, a second apart, within the service's limit, those waiting for the morning once
 * it comes; queues reminders of visits and calls coming up (smsVisitReminders()); and tidies the
 * log (smsTidy()):
 * a verification code's words are wiped a day after it came, spam and dismissed texts go
 * after a month, the rest after a year.
 *
 * Safe on an interval: a text is claimed before it is sent, so this and a reply sent from a
 * ticket can't both send it. With texting not set up, a run is one query and nothing else.
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

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$sms_queue_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

if (intval($sms_queue_settings['config_enable_cron'] ?? 0) == 0) {
    cronJobStop("Cron: is not enabled\n");
}

if (!smsProvider()) {
    cronJobStop("Texting isn't set up\n");
}

smsTidy();

// Reminders of visits and calls coming up, to contacts who agreed to texts - queued, then sent with the rest
$sms_queue_reminders = smsVisitReminders();

$sms_queue_counts = smsQueueRun(45);

echo "Texts: {$sms_queue_counts['sent']} sent, {$sms_queue_counts['failed']} not sent, {$sms_queue_counts['waiting']} waiting" . ($sms_queue_reminders ? ", $sms_queue_reminders reminders queued" : '') . "\n";
