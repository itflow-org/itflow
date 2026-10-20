<?php
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

/*
 * ITFlow - AI triage of new tickets
 *
 * Runs every minute. When Admin > Settings > Tickets says to triage new
 * tickets with AI, it asks the Tickets model (or General's) about the tickets
 * that came in by email, the portal or the API and haven't been triaged - up
 * to five a run, so a slow provider never holds the next minute up - and
 * suggests or fills in their category and priority (aiTriageNewTickets(),
 * functions/ai_ticket.php). Off, or with no model, it does nothing.
 */

// Read the master switch here rather than trusting a global - see the cron rules in CONTRIBUTING.md
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));
if (intval($row['config_enable_cron'] ?? 0) === 0) {
    cronJobStop("Cron: is not enabled -- Quitting..");
}

[$triaged, $failed] = aiTriageNewTickets(5);
if ($failed) {
    logApp('AI', 'warning', "AI triage: $failed new ticket" . ($failed === 1 ? '' : 's') . " couldn't be triaged - see AI Usage");
}
if ($triaged || $failed) {
    echo "AI triage: $triaged triaged, $failed failed\n";
}
