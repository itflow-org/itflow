<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/*
 * Settings > Cron. Everything here identifies a job by its row, and every row is checked
 * against the registry in includes/cron_jobs.php before anything is written - the database
 * decides when and whether a job runs, never which file the dispatcher executes.
 */

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

    flashAlert("Cron job schedule updated.");

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

    $cron_job_name = escapeSql($row['cron_job_name']);

    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_enabled = $enabled WHERE cron_job_id = $cron_job_id");

    logAudit("Cron", "Edit", "$session_name " . ($enabled ? 'enabled' : 'disabled') . " cron job $cron_job_name", 0, $cron_job_id);

    flashAlert("Cron job " . ($enabled ? 'enabled' : 'disabled') . ".", $enabled ? 'success' : 'error');

    redirect();

}

if (isset($_GET['clear_cron_error'])) {

    validateCSRFToken();

    $cron_job_id = intval($_GET['clear_cron_error']);

    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_last_error = NULL, cron_job_last_error_at = NULL WHERE cron_job_id = $cron_job_id");

    flashAlert("Error cleared.");

    redirect();

}
