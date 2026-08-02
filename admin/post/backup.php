<?php

/*
 * ITFlow - GET/POST request handler for backups
 *
 * Archives are not built here. Everything on this page records intent and lets
 * cron/backup.php do the work - see backupQueue() for why.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['queue_backup'])) {

    validateCSRFToken();

    $type = strtolower(trim($_GET['queue_backup']));

    $error = null;
    $backup_id = backupQueue($mysqli, $type, $session_name ?? 'Unknown User', $error);

    if ($backup_id > 0) {
        logAudit("Backup", "Queue", ($session_name ?? 'Unknown User') . " queued a " . backupTypeLabel($type));

        // Saying "it will start within a minute" when the master cron switch is off is a lie
        // the user only discovers by waiting, so check before promising.
        $cron_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

        if (intval($cron_row['config_enable_cron']) === 0) {
            flashAlert(backupTypeLabel($type) . " queued, but cron is switched off in Maintenance > Cron, so it will not start until that is enabled.", 'error');
        } else {
            flashAlert(backupTypeLabel($type) . " queued - it will start within a minute and you will be notified when it is ready.");
        }
    } else {
        flashAlert($error ?? "Could not queue the backup.", 'error');
    }

    redirect("backup.php");
}

if (isset($_GET['delete_backup'])) {

    validateCSRFToken();

    $backup_id = intval($_GET['delete_backup']);

    $sql = mysqli_query($mysqli, "SELECT backup_file_name, backup_type FROM backups WHERE backup_id = $backup_id");

    if (mysqli_num_rows($sql) === 1) {
        $row = mysqli_fetch_assoc($sql);
        backupDeleteById($mysqli, $backup_id);
        logAudit("Backup", "Delete", ($session_name ?? 'Unknown User') . " deleted backup " . escapeSql($row['backup_file_name']));
        flashAlert("Backup deleted.");
    } else {
        flashAlert("Backup not found.", 'error');
    }

    redirect("backup.php");
}

if (isset($_POST['edit_backup_settings'])) {

    validateCSRFToken();

    $retention_days = intval($_POST['config_backup_retention_days']);
    $retention_count = intval($_POST['config_backup_retention_count']);
    $cron_type = escapeSql($_POST['config_backup_cron_type']);

    if ($retention_days < 0) { $retention_days = 0; }
    if ($retention_count < 1) { $retention_count = 1; }

    // The scheduled job runs without a session, so it can only produce the unattended types
    if (!in_array($cron_type, backupUnattendedTypes(), true)) {
        $cron_type = BACKUP_TYPE_FULL;
    }

    mysqli_query($mysqli, "UPDATE settings SET config_backup_retention_days = $retention_days, config_backup_retention_count = $retention_count, config_backup_cron_type = '$cron_type' WHERE company_id = 1");

    logAudit("Backup", "Edit", ($session_name ?? 'Unknown User') . " updated the backup settings");
    flashAlert("Backup settings saved.");

    redirect("backup.php");
}

if (isset($_POST['backup_master_key'])) {

    validateCSRFToken();

    $password = $_POST['password'];

    $sql = mysqli_query($mysqli, "SELECT user_password, user_specific_encryption_ciphertext FROM users WHERE user_id = $session_user_id");
    $row = mysqli_fetch_assoc($sql);

    if (!$row || !password_verify($password, $row['user_password'])) {
        logAudit("Master Key", "Download", ($session_name ?? 'Unknown User') . " attempted to retrieve the master encryption key but failed");
        flashAlert("Incorrect password.", 'error');
        redirect("backup.php");
    }

    $site_encryption_master_key = decryptUserSpecificKey($row['user_specific_encryption_ciphertext'], $password);

    if (empty($site_encryption_master_key)) {
        logAudit("Master Key", "Download", ($session_name ?? 'Unknown User') . " could not unwrap the master encryption key");
        flashAlert("Your password is correct but the master key could not be unwrapped from your account.", 'error');
        redirect("backup.php");
    }

    logAudit("Master Key", "Download", ($session_name ?? 'Unknown User') . " retrieved the master encryption key");
    appNotify("Master Key", ($session_name ?? 'Unknown User') . " retrieved the master encryption key", "/admin/backup.php");

    // Written as an encrypted archive too, so it can be filed with the other backups.
    // This is the one type cron can never produce - the key only exists inside a session.
    $error = null;
    backupCreate($mysqli, BACKUP_TYPE_MASTER_KEY, $session_name ?? 'Unknown User', 'Manual', $error, ['master_key' => $site_encryption_master_key]);

    $_SESSION['backup_master_key_reveal'] = $site_encryption_master_key;

    if ($error) {
        flashAlert("Master key shown below, but the encrypted copy could not be written: $error", 'error');
    }

    redirect("backup.php");
}
