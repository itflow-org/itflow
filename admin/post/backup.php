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

    $sql = mysqli_query($mysqli, "SELECT backup_file_name, backup_type, backup_status FROM backups WHERE backup_id = $backup_id");

    if (mysqli_num_rows($sql) === 1) {
        $row = mysqli_fetch_assoc($sql);
        backupDeleteById($mysqli, $backup_id);

        // One still waiting has no file yet - taking it off the queue is all there is to it
        if ($row['backup_status'] === 'Pending') {
            logAudit("Backup", "Delete", ($session_name ?? 'Unknown User') . " took a queued " . backupTypeLabel($row['backup_type']) . " off the queue");
            flashAlert(backupTypeLabel($row['backup_type']) . " taken off the queue.", 'error');
        } else {
            logAudit("Backup", "Delete", ($session_name ?? 'Unknown User') . " deleted backup " . escapeSql($row['backup_file_name']));
            flashAlert($row['backup_status'] === 'Complete' ? "Backup deleted." : "Backup removed from the list.", 'error');
        }
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

/*
 * Development mode (functions/dev_mode.php) - written to config.php, not the database, so
 * restoring production's backup over a test copy can't turn it off.
 */
if (isset($_POST['edit_dev_mode'])) {

    validateCSRFToken();

    $dev_on = isset($_POST['dev_mode']);
    $dev_mail_to = trim((string) ($_POST['dev_mail_to'] ?? ''));

    if ($dev_mail_to !== '' && !filter_var($dev_mail_to, FILTER_VALIDATE_EMAIL)) {
        flashAlert("The test inbox isn't an email address.", 'error');
        redirect("backup.php#dev-mode");
    }

    if ($dev_on && $dev_mail_to === '') {
        flashAlert("Give a test inbox - in development mode every email goes there.", 'error');
        redirect("backup.php#dev-mode");
    }

    $dev_was_on = devModeOn();
    $dev_error = devModeConfigSet($dev_on, $dev_mail_to);

    if ($dev_error !== '') {
        flashAlert(escapeHtml($dev_error), 'error');
        redirect("backup.php#dev-mode");
    }

    logAudit("Settings", "Edit", "$session_name " . ($dev_on ? ($dev_was_on ? "set development mode's test inbox to $dev_mail_to" : "turned development mode on, with email to $dev_mail_to") : ($dev_was_on ? 'turned development mode off' : 'saved development mode, off')));

    if ($dev_on) {
        flashAlert("Development mode is on - every email goes to " . escapeHtml($dev_mail_to) . ", and nothing reaches clients or other systems.", 'warning');
    } else {
        flashAlert($dev_was_on ? "Development mode is off - email, texts and the integrations work normally again." : "Development mode is off.");
    }

    redirect("backup.php#dev-mode");
}

/*
 * Restoring from this page (functions/backup_restore.php). The admin's password and typing
 * RESTORE confirm it, then it's queued for cron/backup.php - nothing is replaced in this request.
 */
if (isset($_POST['restore_backup'])) {

    validateCSRFToken();

    $backup_id = intval($_POST['backup_id'] ?? 0);

    $user_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_password FROM users WHERE user_id = " . intval($session_user_id)));

    if (!$user_row || !password_verify((string) ($_POST['password'] ?? ''), (string) $user_row['user_password'])) {
        logAudit("Backup", "Restore", "$session_name tried to restore a backup with the wrong password");
        flashAlert("That isn't your password - nothing was restored.", 'error');
        redirect("backup.php");
    }

    if (trim((string) ($_POST['confirm'] ?? '')) !== 'RESTORE') {
        flashAlert("Type RESTORE to confirm - nothing was restored.", 'error');
        redirect("backup.php");
    }

    [$restore_ok, $restore_message] = backupRestoreQueue($mysqli, $backup_id, (string) ($_POST['restore_key'] ?? ''), stripslashes((string) $session_name), intval($session_user_id));

    if (!$restore_ok) {
        flashAlert(escapeHtml($restore_message), 'error');
        redirect("backup.php");
    }

    logAudit("Backup", "Restore", "$session_name asked to restore the " . $restore_message);

    $cron_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

    if (intval($cron_row['config_enable_cron']) === 0) {
        flashAlert("Restore queued, but cron is switched off in Maintenance > Cron - it's only done if cron runs within " . BACKUP_RESTORE_WAIT_MINUTES . " minutes.", 'warning');
    } else {
        flashAlert("Restore queued - cron starts it within a minute. ITFlow is backed up as it is first, then the backup is restored.");
    }

    redirect("backup.php");
}

if (isset($_GET['cancel_restore'])) {

    validateCSRFToken();

    if (backupRestoreCancel(stripslashes((string) $session_name))) {
        logAudit("Backup", "Restore", "$session_name cancelled a restore before it started");
        flashAlert("Restore cancelled - nothing was changed.");
    } else {
        flashAlert("There's no restore waiting to start - it may have started already.", 'error');
    }

    redirect("backup.php");
}

// Upload a backup to restore, in one request - the form without the script (js/backup_restore.js)
if (isset($_POST['backup_upload'])) {

    validateCSRFToken();

    $file = $_FILES['backup_file'] ?? [];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $too_big = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        flashAlert($too_big ? "It's bigger than this server takes in one go (" . escapeHtml(backupFormatBytes(backupMaxUploadBytes())) . ") - upload it with JavaScript on, which sends it in pieces, or restore it from the command line." : "Pick a backup .zip to upload.", 'error');
        redirect("backup.php");
    }

    [$uploaded_id, $upload_message] = backupRestoreAdopt($mysqli, $file['tmp_name'], (string) ($_POST['restore_key'] ?? ''), stripslashes((string) $session_name), true);

    if (!$uploaded_id) {
        flashAlert(escapeHtml($upload_message), 'error');
        redirect("backup.php");
    }

    logAudit("Backup", "Upload", "$session_name uploaded a backup to restore");
    flashAlert(escapeHtml($upload_message));
    redirect("backup.php?restore=$uploaded_id");
}

/*
 * Upload a backup in pieces (js/backup_restore.js), so PHP's and a proxy's request limits don't
 * matter. Answers JSON. The upload going on is kept in the admin's session; the pieces are put
 * together beside the backups (backupRestorePartPath()).
 */
if (isset($_POST['backup_upload_start']) || isset($_POST['backup_upload_chunk']) || isset($_POST['backup_upload_finish'])) {

    validateCSRFToken();

    header('Content-Type: application/json');

    try {

        if (isset($_POST['backup_upload_start'])) {

            $size = intval($_POST['size'] ?? 0);

            if (!preg_match('/\.zip$/i', (string) ($_POST['name'] ?? ''))) {
                throw new RuntimeException('Pick a backup .zip to upload.');
            }
            if ($size <= 0 || $size > BACKUP_RESTORE_MAX_BYTES) {
                throw new RuntimeException('A backup up to ' . backupFormatBytes(BACKUP_RESTORE_MAX_BYTES) . ' can be uploaded here - restore a bigger one from the command line.');
            }

            backupRestoreTidyParts();
            if (!empty($_SESSION['backup_upload']['id'])) {
                @unlink(backupRestorePartPath($_SESSION['backup_upload']['id']));
            }

            $problem = backupRestoreDiskProblem($size + 512 * 1024 * 1024, 'for the upload');
            if ($problem !== '') {
                throw new RuntimeException($problem);
            }

            $upload_id = bin2hex(random_bytes(16));
            if (@file_put_contents(backupRestorePartPath($upload_id), '') === false) {
                throw new RuntimeException("The backups folder can't be written to.");
            }
            @chmod(backupRestorePartPath($upload_id), 0600);

            $_SESSION['backup_upload'] = ['id' => $upload_id, 'size' => $size, 'received' => 0];

            echo json_encode(['id' => $upload_id, 'chunk' => backupRestoreChunkBytes()]);

        } elseif (isset($_POST['backup_upload_chunk'])) {

            $upload = $_SESSION['backup_upload'] ?? null;
            $piece = $_FILES['chunk'] ?? [];

            if (!$upload || !hash_equals((string) $upload['id'], (string) ($_POST['id'] ?? ''))) {
                throw new RuntimeException('This upload is no longer going on - start it again.');
            }
            if (($piece['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($piece['tmp_name'] ?? '')) {
                throw new RuntimeException('A piece of the file didn\'t arrive (error ' . intval($piece['error'] ?? 0) . ').');
            }
            if (intval($_POST['offset'] ?? -1) !== intval($upload['received'])) {
                throw new RuntimeException('A piece arrived out of order - start the upload again.');
            }

            $length = intval(filesize($piece['tmp_name']));
            if ($length <= 0 || $length > backupRestoreChunkBytes() || $upload['received'] + $length > $upload['size']) {
                throw new RuntimeException('A piece of the file wasn\'t the size expected - start the upload again.');
            }

            $in = fopen($piece['tmp_name'], 'rb');
            $out = fopen(backupRestorePartPath($upload['id']), 'ab');
            $copied = ($in && $out) ? stream_copy_to_stream($in, $out) : false;
            $in && fclose($in);
            $out && fclose($out);

            if ($copied !== $length) {
                throw new RuntimeException("A piece couldn't be written to the backups folder - is the disk full?");
            }

            $_SESSION['backup_upload']['received'] += $length;

            echo json_encode(['received' => $_SESSION['backup_upload']['received']]);

        } else {

            $upload = $_SESSION['backup_upload'] ?? null;
            unset($_SESSION['backup_upload']);

            if (!$upload || !hash_equals((string) $upload['id'], (string) ($_POST['id'] ?? ''))) {
                throw new RuntimeException('This upload is no longer going on - start it again.');
            }

            $part = backupRestorePartPath($upload['id']);

            if (intval($upload['received']) !== intval($upload['size']) || !is_file($part) || filesize($part) !== intval($upload['size'])) {
                @unlink($part);
                throw new RuntimeException("The upload didn't finish - start it again.");
            }

            [$uploaded_id, $upload_message] = backupRestoreAdopt($mysqli, $part, (string) ($_POST['restore_key'] ?? ''), stripslashes((string) $session_name), false);

            if (!$uploaded_id) {
                throw new RuntimeException($upload_message);
            }

            logAudit("Backup", "Upload", "$session_name uploaded a backup to restore");
            flashAlert(escapeHtml($upload_message));

            echo json_encode(['redirect' => "backup.php?restore=$uploaded_id"]);
        }

    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
    }

    exit;
}
