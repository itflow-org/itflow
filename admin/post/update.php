<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/*
 * Queueing is the ONLY way to update the application. There used to be an ?update handler
 * here that ran git pull (or a hard reset) inside this request; it is gone deliberately.
 * scripts/update_cli.php replaces the files this request is executing from and then applies
 * the migrations that came with them, which is not something to do half way through a page
 * load, and a host where PHP cannot run external commands could never do it at all.
 *
 * The row is written as well as the settings column: config_update_queued_at is what
 * cron/app_update.php acts on, and cron_job_run_now is what gets the dispatcher to look
 * before the job's own schedule comes round.
 */
if (isset($_GET['queue_update'])) {

    validateCSRFToken();

    enforceAdminPermission();

    // The files can be newer than the schema - that is the window this whole page exists to
    // close - and the column the queue is written to arrives with a migration
    if (!settingsColumnExists($mysqli, 'config_update_queued_at')) {
        flashAlert("Apply the database update first - queueing needs a schema change this install has not caught up with yet.", 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE settings SET config_update_queued_at = '" . date('Y-m-d H:i:s') . "' WHERE company_id = 1");
    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_run_now = 1 WHERE cron_job_name = 'app_update'");

    logAudit("App", "Update", "$session_name queued an update to be applied by cron");

    flashAlert("Update queued - cron will start it within a minute.");

    redirect();

}

if (isset($_GET['update_db'])) {

    validateCSRFToken();

    // Get the current version
    require_once ('../includes/database_version.php');

    // Perform upgrades, if required - populates $database_updates_applied and $database_updates_error
    require_once ('database_updates.php');

    if ($database_updates_error) {
        logAudit("Database", "Update", "$session_name ran a database update that failed at $database_updates_error");
        flashAlert("Database update failed at $database_updates_error - the version was not advanced past the last successful update, so it is safe to retry", "error");
    } else {
        logAudit("Database", "Update", "$session_name updated the database structure");
        flashAlert("Database structure update successful");
    }

    sleep(1);

    redirect();

}
