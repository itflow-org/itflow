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

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE settings.company_id = 1");
$row = mysqli_fetch_assoc($sql_settings);

$config_enable_cron = intval($row['config_enable_cron']);
$config_backup_cron_type = $row['config_backup_cron_type'] ?? 'full';

if ($config_enable_cron == 0) {
    cronJobStop("Cron: is not enabled\n");
}

/*
 * Anything an administrator started from Settings > Backup is built first. Those are
 * explicit requests and somebody is waiting on the notification.
 */
$queued = backupRunQueued($mysqli);

if ($queued > 0) {
    echo "Built $queued queued backup(s)\n";
}

/*
 * Then the scheduled one. The dispatcher only calls this script when the schedule says so,
 * so reaching here means a scheduled backup is due.
 *
 * Skipped if a backup of the same type already completed today - the day-match trap from
 * CONTRIBUTING's cron rules. A second dispatch in the same day (a manual Run Now, a catch-up
 * after downtime) must not produce a second scheduled archive.
 */
$backup_job_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_enabled FROM cron_jobs WHERE cron_job_name = 'backup'"));

if (empty($backup_job_row['cron_job_enabled'])) {
    // Reached by a Run Now, which is how a queued backup gets built while the schedule is
    // off. Building the scheduled archive as well would hand somebody who asked for a
    // database backup an unasked-for full one. Scheduled work belongs to the schedule.
    echo "Scheduled backups are switched off - built queued work only\n";
    return;
}

$type = in_array($config_backup_cron_type, backupUnattendedTypes(), true) ? $config_backup_cron_type : BACKUP_TYPE_FULL;
$type_esc = escapeSql($type);

$already = mysqli_num_rows(mysqli_query($mysqli, "SELECT backup_id FROM backups WHERE backup_source = 'Cron' AND backup_type = '$type_esc' AND backup_status = 'Complete' AND backup_completed_at >= CURDATE()"));

if ($already > 0) {
    echo "Scheduled backup already ran today\n";
} else {
    $error = null;
    $backup_id = backupCreate($mysqli, $type, 'Cron', 'Cron', $error);

    if ($backup_id > 0) {
        echo "Scheduled " . backupTypeLabel($type) . " complete\n";
        appNotify("Backup", "Scheduled " . backupTypeLabel($type) . " is ready to download", "/admin/backup.php");
        logAudit("Backup", "Create", "Scheduled " . backupTypeLabel($type) . " completed");
    } else {
        echo "Scheduled backup FAILED: $error\n";
        appNotify("Backup", "Scheduled backup failed: $error", "/admin/backup.php");
        logAudit("Backup", "Create", "Scheduled backup failed: $error");
        logApp("Backup", "error", "Scheduled backup failed: $error");
    }
}
