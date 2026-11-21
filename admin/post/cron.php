<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/*
 * Maintenance > Cron. Apart from the master switch, everything here identifies a job by its
 * row, and every row is checked against the registry in includes/cron_jobs.php before
 * anything is written - the database decides when and whether a job runs, never which file
 * the dispatcher executes.
 */

if (isset($_GET['enable_cron']) || isset($_GET['disable_cron'])) {

    validateCSRFToken();

    // The master switch, config_enable_cron. Most jobs check it themselves and stop; it is
    // not a dispatcher-level gate, so the two jobs that do not check it keep running. It
    // lived in Settings > Notifications until 26.08, which is nowhere near anything else
    // about cron.
    $enabled = isset($_GET['enable_cron']) ? 1 : 0;

    mysqli_query($mysqli, "UPDATE settings SET config_enable_cron = $enabled WHERE company_id = 1");

    logAudit("Cron", "Edit", "$session_name " . ($enabled ? 'enabled' : 'disabled') . " the master cron switch");

    flashAlert("Cron turned " . ($enabled ? 'on' : 'off') . ".", $enabled ? 'success' : 'error');

    redirect();

}

if (isset($_POST['edit_cron_job'])) {

    validateCSRFToken();

    require_once "../includes/cron_jobs.php";

    $cron_job_id = intval($_POST['cron_job_id']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_name FROM cron_jobs WHERE cron_job_id = $cron_job_id LIMIT 1"));
    $registry = cronJobRegistryByName();

    if (!$row || !isset($registry[$row['cron_job_name']])) {
        flashAlert("That cron job is not part of this version of ITFlow.", 'error');
        redirect();
    }

    $cron_job_name = escapeSql($row['cron_job_name']);
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $schedule = $_POST['schedule'] === 'Daily' ? 'Daily' : 'Interval';

    // A job the registry marks interval-unsafe only accepts the daily schedule. The form
    // does not offer anything else, so anything else arriving here is a crafted request
    if (($registry[$row['cron_job_name']]['interval_safe'] ?? true) === false) {
        $schedule = 'Daily';
    }

    // A job cannot be asked to run more than once a minute (the dispatcher only wakes that
    // often) and anything over a day belongs on the daily schedule instead.
    $interval_minutes = min(1440, max(1, intval($_POST['interval_minutes'])));

    // Time inputs hand back HH:MM; anything else is discarded rather than stored half-parsed
    $daily_at = 'NULL';
    if ($schedule === 'Daily') {
        $submitted = trim($_POST['daily_at']);
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $submitted)) {
            flashAlert("Enter the daily run time as a 24-hour time, for example 03:00.", 'error');
            redirect();
        }
        $daily_at = "'" . escapeSql($submitted) . ":00'";
    }

    mysqli_query($mysqli, "UPDATE cron_jobs SET
        cron_job_enabled = $enabled,
        cron_job_schedule = '$schedule',
        cron_job_interval_minutes = $interval_minutes,
        cron_job_daily_at = $daily_at
        WHERE cron_job_id = $cron_job_id");

    logAudit("Cron", "Edit", "$session_name edited the schedule for cron job $cron_job_name", 0, $cron_job_id);

    flashAlert("Cron job schedule saved");

    redirect();

}

if (isset($_GET['run_cron_job'])) {

    validateCSRFToken();

    require_once "../includes/cron_jobs.php";

    $cron_job_id = intval($_GET['run_cron_job']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_name FROM cron_jobs WHERE cron_job_id = $cron_job_id LIMIT 1"));
    $registry = cronJobRegistryByName();

    if (!$row || !isset($registry[$row['cron_job_name']])) {
        flashAlert("That cron job is not part of this version of ITFlow.", 'error');
        redirect();
    }

    $cron_job_name = escapeSql($row['cron_job_name']);
    $cron_job_label = $registry[$row['cron_job_name']]['label'];

    // The job is not run here. These scripts are written for the command line and some of
    // them take minutes, so the request is left for the dispatcher to pick up on its next
    // pass, which also means it goes through the same lock and claim as a scheduled run.
    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_run_now = 1 WHERE cron_job_id = $cron_job_id");

    logAudit("Cron", "Run", "$session_name requested an immediate run of cron job $cron_job_name", 0, $cron_job_id);

    flashAlert("$cron_job_label is queued and will start within a minute.");

    redirect();

}

if (isset($_GET['enable_cron_job']) || isset($_GET['disable_cron_job'])) {

    validateCSRFToken();

    $enabled = isset($_GET['enable_cron_job']) ? 1 : 0;
    $cron_job_id = intval($_GET[$enabled ? 'enable_cron_job' : 'disable_cron_job']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_name FROM cron_jobs WHERE cron_job_id = $cron_job_id LIMIT 1"));

    if (!$row) {
        redirect();
    }

    require_once "../includes/cron_jobs.php";

    $cron_job_name = escapeSql($row['cron_job_name']);
    $cron_job_label = cronJobRegistryByName()[$row['cron_job_name']]['label'] ?? 'Cron job';

    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_enabled = $enabled WHERE cron_job_id = $cron_job_id");

    logAudit("Cron", "Edit", "$session_name " . ($enabled ? 'enabled' : 'disabled') . " cron job $cron_job_name", 0, $cron_job_id);

    flashAlert("$cron_job_label turned " . ($enabled ? 'on' : 'off') . ".", $enabled ? 'success' : 'error');

    redirect();

}

if (isset($_GET['clear_cron_error'])) {

    validateCSRFToken();

    $cron_job_id = intval($_GET['clear_cron_error']);

    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_last_error = NULL, cron_job_last_error_at = NULL WHERE cron_job_id = $cron_job_id");

    flashAlert("Error cleared");

    redirect();

}

/*
 * The Alerts card (functions/cron_health.php): whether the Cron Watch job emails, and who to,
 * a test email to them, and a new key for the health address an uptime monitor checks.
 */

if (isset($_POST['edit_cron_alerts'])) {

    validateCSRFToken();

    if (!cronHealthReady()) {
        flashAlert("Cron alerts need the database update - Maintenance > Update.", 'error');
        redirect();
    }

    [$addresses, $not_addresses] = cronHealthAddresses($_POST['cron_alert_to'] ?? '');

    if ($not_addresses) {
        flashAlert("Not an email address: " . escapeHtml(implode(', ', $not_addresses)), 'error');
        redirect();
    }

    $alert_email = isset($_POST['cron_alert_email']) ? 1 : 0;
    $alert_to = escapeSql(substr(implode(', ', $addresses), 0, 500));

    mysqli_query($mysqli, "UPDATE settings SET config_cron_alert_email = $alert_email, config_cron_alert_to = " . ($alert_to === '' ? 'NULL' : "'$alert_to'") . " WHERE company_id = 1");

    logAudit("Cron", "Edit", "$session_name set cron alerts " . ($alert_email ? 'to email ' . ($alert_to === '' ? 'every active admin' : $alert_to) : 'not to email'));

    flashAlert("Cron alerts saved");

    redirect();

}

if (isset($_POST['test_cron_alert'])) {

    validateCSRFToken();

    if (!cronHealthReady()) {
        flashAlert("Cron alerts need the database update - Maintenance > Update.", 'error');
        redirect();
    }

    if (!emailReady()) {
        flashAlert("Set up outgoing mail first - Admin > Settings > Mail.", 'error');
        redirect();
    }

    // To the addresses in the box, saved or not - so they can be tried before they're saved
    [$addresses, $not_addresses] = cronHealthAddresses($_POST['cron_alert_to'] ?? '');

    if ($not_addresses) {
        flashAlert("Not an email address: " . escapeHtml(implode(', ', $not_addresses)), 'error');
        redirect();
    }

    $recipients = cronHealthRecipients(implode(', ', $addresses));

    if (!$recipients) {
        flashAlert("Nobody to send to - add an address, or give an admin an email address.", 'error');
        redirect();
    }

    cronHealthMail("ITFlow cron alerts: test", [
        'tone' => 'primary', 'title' => 'Cron alerts: test',
        'intro' => ['This is a test from Maintenance > Cron. When a cron job fails, gets stuck or falls behind, the alert comes to this address.'],
    ], implode(', ', $addresses));

    logAudit("Cron", "Edit", "$session_name sent a test cron alert");

    // Typed but not saved yet - say so, or the alerts still go where they did
    $saved = array_map('strtolower', cronHealthAddresses(cronHealthSettings()['config_cron_alert_to'] ?? '')[0]);
    $typed = array_map('strtolower', $addresses);
    sort($saved);
    sort($typed);

    flashAlert("Test queued for " . escapeHtml(implode(', ', array_keys($recipients))) . " - it goes out with the mail queue, within a minute while cron runs." . ($saved !== $typed ? " Save for the alerts to go there too." : ''));

    redirect();

}

if (isset($_GET['renew_cron_health_key'])) {

    validateCSRFToken();

    if (!cronHealthReady()) {
        flashAlert("The health address needs the database update - Maintenance > Update.", 'error');
        redirect();
    }

    cronHealthKey(true);

    logAudit("Cron", "Edit", "$session_name made a new health address for cron");

    flashAlert("New health address made - the old one no longer works, so give your uptime monitor the new one.");

    redirect();

}
