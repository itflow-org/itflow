<?php

/*
 * ITFlow - Restoring a backup from Maintenance > Backup
 *
 * Like a backup, a restore isn't done in the page's own request: a running ITFlow shouldn't
 * drop its own tables in the middle of a page load, behind a proxy that gives up waiting. The
 * page checks the backup and the admin's password, then queues it (backupRestoreQueue()) and
 * cron/backup.php does it (backupRestoreRunQueued()):
 *   - a Full Backup of ITFlow as it is first, kept as "Before restore", so it can be undone
 *   - then backupRestoreArchive(), which puts the database back as it was if the import fails
 *   - the backups list is the backup's own afterwards, so the "Before restore" backup and the
 *     one restored are put back in it
 *   - then the database updates (scripts/update_cli.php --update_db), for a backup from an
 *     older version
 * The cron dispatch that runs it ends its cycle there, and the dispatches started while it
 * works skip theirs (cron/cron.php) - the jobs mustn't write to tables being replaced.
 *
 * What was asked for, and how it went, are files beside the backups rather than rows: the
 * restore replaces the database they would be in. restore_queued.json is taken away as the
 * job starts, so a restore that stops part way is never started again unattended, and one
 * that waited longer than BACKUP_RESTORE_WAIT_MINUTES - cron was stopped - isn't done at all.
 * restore_status.json says how the last one went, for the page.
 *
 * A backup can also be uploaded to restore - one from another server, say. The browser sends it
 * in pieces (js/backup_restore.js, the backup_upload_* handlers in admin/post/backup.php), so
 * PHP's and a proxy's request limits don't matter, and it joins the list as "Uploaded".
 */

const BACKUP_SOURCE_BEFORE_RESTORE = 'Before restore';
const BACKUP_SOURCE_UPLOADED = 'Uploaded';
// The biggest backup the browser uploads, and the most of it in one request
const BACKUP_RESTORE_MAX_BYTES = 5 * 1024 * 1024 * 1024;
const BACKUP_RESTORE_CHUNK_BYTES = 32 * 1024 * 1024;
// A restore cron hasn't started this long after it was asked for isn't done
const BACKUP_RESTORE_WAIT_MINUTES = 60;

/* The backups folder, found once a process - cron/cron.php asks every minute */
function backupRestoreDir(): string
{
    static $dir = null;
    return $dir ?? ($dir = rtrim(backupStorageDir(), '/'));
}

function backupRestoreRequestFile(): string
{
    return backupRestoreDir() . '/restore_queued.json';
}

function backupRestoreStatusFile(): string
{
    return backupRestoreDir() . '/restore_status.json';
}

function backupRestoreReadJson(string $file): ?array
{
    $raw = @file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}

function backupRestoreWriteJson(string $file, array $data): bool
{
    $ok = @file_put_contents($file, json_encode($data), LOCK_EX) !== false;
    @chmod($file, 0600);
    return $ok;
}

/** How the last restore went - state queued, running, done or failed - or null. */
function backupRestoreStatus(): ?array
{
    return backupRestoreReadJson(backupRestoreStatusFile());
}

function backupRestoreSetStatus(array $status): void
{
    backupRestoreWriteJson(backupRestoreStatusFile(), $status + ['at' => date('Y-m-d H:i:s')]);
}

/** A restore asked for and not started yet - or null. */
function backupRestoreRequest(): ?array
{
    return backupRestoreReadJson(backupRestoreRequestFile());
}

/** Whether a restore is replacing the database now - cron/cron.php skips its cycle while it does. */
function backupRestoreInProgress(): bool
{
    $status = backupRestoreStatus();
    return ($status['state'] ?? '') === 'running' && strtotime((string) ($status['started'] ?? '')) > strtotime('-6 hours');
}

/** Who or what a backup in the list is down to, from its row - "Scheduled", "Uploaded by Sam". */
function backupMadeBy(array $backup): string
{
    $by = (string) ($backup['backup_created_by'] ?? '');
    switch ((string) $backup['backup_source']) {
        case 'Cron':
            return 'Scheduled';
        case 'Adopted':
            return 'Found on this server';
        case BACKUP_SOURCE_UPLOADED:
            return 'Uploaded' . ($by !== '' ? " by $by" : '');
        case BACKUP_SOURCE_BEFORE_RESTORE:
            return 'Before a restore' . ($by !== '' ? " by $by" : '');
    }
    return $by !== '' ? "By $by" : 'By hand';
}

/** A backup in the list that can be restored: [row, path] or [null, why not]. */
function backupRestoreStored(mysqli $mysqli, int $backup_id): array
{
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT backup_id, backup_type, backup_file_name, backup_size, backup_sha256, backup_status, backup_source,
        backup_created_by, backup_created_at, backup_completed_at FROM backups WHERE backup_id = " . intval($backup_id)));
    if (!$row || $row['backup_status'] !== 'Complete') {
        return [null, 'That backup isn\'t there, or isn\'t finished.'];
    }
    if ($row['backup_type'] === BACKUP_TYPE_MASTER_KEY) {
        return [null, 'A master key export can\'t be restored.'];
    }
    $path = backupResolvePath((string) $row['backup_file_name']);
    if ($path === false || !is_file($path)) {
        return [null, 'Its file is no longer on this server.'];
    }
    return [$row, $path];
}

/** The key to open a backup with: the one typed, else this install's. */
function backupRestoreKey(string $typed): string
{
    $typed = trim($typed);
    return $typed !== '' ? $typed : backupEncryptionKey();
}

/*
 * What is in a backup, and whether it can be restored here: [meta, problem] - problem '' when it
 * can. One from a newer database version than this code is refused - ITFlow would break after it.
 */
function backupRestoreCheck(mysqli $mysqli, string $path, string $key): array
{
    $error = null;
    $meta = backupInspectArchive($path, $key, $error);
    if ($meta === false) {
        return [null, (string) $error];
    }
    $current = backupCurrentDatabaseVersion($mysqli);
    if ($meta['database_version'] !== 'Unknown' && $current !== 'Unknown' && version_compare($meta['database_version'], $current, '>')) {
        return [$meta, "It's from a newer database version ({$meta['database_version']}) than this ITFlow ($current) - update ITFlow first."];
    }
    return [$meta, ''];
}

/** '' when the backups folder's disk has $bytes free, else why not. */
function backupRestoreDiskProblem(float $bytes, string $for): string
{
    $free = @disk_free_space(backupStorageDir());
    if ($free === false || $free >= $bytes) {
        return '';
    }
    return "There isn't room $for: about " . backupFormatBytes($bytes) . ' is needed, and ' . backupFormatBytes($free) . ' is free.';
}

/*
 * Another install's key, for cron to open its backup with - sealed with this install's own key
 * while it waits in restore_queued.json, so the file alone doesn't give it away.
 */
function backupRestoreSealKey(string $key): string
{
    if ($key === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $sealed = openssl_encrypt($key, 'aes-256-gcm', hash('sha256', backupEncryptionKey(), true), OPENSSL_RAW_DATA, $iv, $tag);
    return $sealed === false ? '' : base64_encode($iv . $tag . $sealed);
}

function backupRestoreOpenKey(string $sealed): string
{
    $raw = base64_decode($sealed, true);
    if ($sealed === '' || $raw === false || strlen($raw) < 29) {
        return '';
    }
    $key = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', backupEncryptionKey(), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $key === false ? '' : $key;
}

/*
 * Ask cron to restore a backup in the list. Checked here first - it opens with the key, isn't
 * from a newer version, and there's room - so what the page says is what cron will find.
 * Returns [ok, message].
 */
function backupRestoreQueue(mysqli $mysqli, int $backup_id, string $typed_key, string $who, int $user_id): array
{
    if (backupEncryptionKey() === '') {
        return [false, 'There\'s no encryption key, so ITFlow can\'t back itself up before the restore.'];
    }
    if (backupRestoreInProgress()) {
        return [false, 'A restore is already running.'];
    }
    [$row, $path] = backupRestoreStored($mysqli, $backup_id);
    if (!$row) {
        return [false, $path];
    }
    [$meta, $problem] = backupRestoreCheck($mysqli, $path, backupRestoreKey($typed_key));
    if (!$meta || $problem !== '') {
        return [false, $problem];
    }
    // The "Before restore" backup (about the size of what's here now), the unpacked backup and the import
    $problem = backupRestoreDiskProblem(3 * (filesize($path) ?: 0), 'to restore it');
    if ($problem !== '') {
        return [false, $problem];
    }
    $request = [
        'backup_id' => intval($row['backup_id']),
        'file' => (string) $row['backup_file_name'],
        'label' => backupTypeLabel((string) $meta['type']) . ' taken ' . $meta['generated'],
        'key' => backupRestoreSealKey(trim($typed_key)),
        'who' => $who,
        'user_id' => $user_id,
        'queued' => date('Y-m-d H:i:s'),
    ];
    if (!backupRestoreWriteJson(backupRestoreRequestFile(), $request)) {
        return [false, 'The backups folder can\'t be written to.'];
    }
    backupRestoreSetStatus(['state' => 'queued', 'who' => $who, 'file' => $request['file'], 'label' => $request['label'], 'queued' => $request['queued']]);
    // run_now works on a job that's off - the Backup job ships with its schedule off
    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_run_now = 1 WHERE cron_job_name = 'backup'");
    return [true, $request['label']];
}

/** Take a queued restore back before cron starts it. Whether there was one. */
function backupRestoreCancel(string $who): bool
{
    $request = backupRestoreRequest();
    if (!$request) {
        return false;
    }
    @unlink(backupRestoreRequestFile());
    backupRestoreSetStatus(['state' => 'cancelled', 'who' => $who, 'file' => (string) $request['file'], 'label' => (string) ($request['label'] ?? ''),
        'message' => "Cancelled by $who before it started - nothing was changed."]);
    return true;
}

/*
 * cron/backup.php: do the restore that was asked for, if there is one. Returns null when there
 * wasn't, else [ok, message]. $mysqli may be a new connection afterwards.
 */
function backupRestoreRunQueued(mysqli &$mysqli): ?array
{
    $request = backupRestoreRequest();
    if (!$request) {
        return null;
    }
    // Taken away first: a restore that stops part way - the server restarted - is looked at, not started again unattended
    @unlink(backupRestoreRequestFile());

    $who = (string) ($request['who'] ?? 'An admin');
    $base = ['who' => $who, 'file' => (string) ($request['file'] ?? ''), 'label' => (string) ($request['label'] ?? ''), 'queued' => (string) ($request['queued'] ?? '')];
    $fail = function ($message) use ($base) {
        backupRestoreSetStatus($base + ['state' => 'failed', 'message' => $message]);
        return [false, $message];
    };

    if (strtotime((string) ($request['queued'] ?? '')) < strtotime('-' . BACKUP_RESTORE_WAIT_MINUTES . ' minutes')) {
        return $fail('Not restored - cron didn\'t start it within ' . BACKUP_RESTORE_WAIT_MINUTES . ' minutes of being asked, so nothing was changed. Ask again if you still want it.');
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT backup_id FROM backups WHERE backup_file_name = '" . escapeSql($base['file']) . "'"));
    [$backup, $path] = $row ? backupRestoreStored($mysqli, intval($row['backup_id'])) : [null, 'That backup is no longer in the list.'];
    if (!$backup) {
        return $fail("Nothing was changed - $path");
    }
    $key = !empty($request['key']) ? backupRestoreOpenKey((string) $request['key']) : backupEncryptionKey();
    [$meta, $problem] = backupRestoreCheck($mysqli, $path, $key);
    if (!$meta || $problem !== '') {
        return $fail("Nothing was changed - $problem");
    }
    $problem = backupRestoreDiskProblem(3 * (filesize($path) ?: 0), 'to restore it');
    if ($problem !== '') {
        return $fail("Nothing was changed - $problem");
    }

    @set_time_limit(0);
    backupRestoreSetStatus($base + ['state' => 'running', 'started' => date('Y-m-d H:i:s')]);

    // ITFlow as it is now, kept - so the restore can be undone
    $error = null;
    $safety_id = backupCreate($mysqli, BACKUP_TYPE_FULL, $who, BACKUP_SOURCE_BEFORE_RESTORE, $error);
    if (!$safety_id) {
        return $fail("Nothing was changed - the backup of ITFlow as it is now didn't work: $error");
    }
    $safety = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT backup_type, backup_file_name, backup_size, backup_sha256, backup_status, backup_source,
        backup_created_by, backup_created_at, backup_completed_at FROM backups WHERE backup_id = " . intval($safety_id)));

    $error = null;
    $ok = backupRestoreArchive($mysqli, $path, $key, $error);
    $mysqli = backupDbEnsure($mysqli);

    // The list is the backup's own now, as it was while that backup was made - put back the one
    // restored and the "Before restore" one (the rest on this server are found again by the
    // nightly tidy-up). Done when it failed too: the database may have been restored before
    // what came after it failed.
    foreach (array_filter([$backup, $safety]) as $keep) {
        $set = [];
        foreach (['backup_type', 'backup_file_name', 'backup_size', 'backup_sha256', 'backup_status', 'backup_source', 'backup_created_by', 'backup_created_at', 'backup_completed_at'] as $column) {
            $set[] = "$column = " . ($keep[$column] === null ? 'NULL' : "'" . escapeSql((string) $keep[$column]) . "'");
        }
        $name = escapeSql((string) $keep['backup_file_name']);
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM backups WHERE backup_file_name = '$name'"))) {
            mysqli_query($mysqli, "UPDATE backups SET " . implode(', ', $set) . ", backup_error = NULL WHERE backup_file_name = '$name'");
        } else {
            mysqli_query($mysqli, "INSERT INTO backups SET " . implode(', ', $set));
        }
    }
    // The backup lists the one being made as it was made, or one that was waiting - not left as if still going
    $waiting = mysqli_query($mysqli, "SELECT backup_id FROM backups WHERE backup_status IN ('Running', 'Pending')");
    while ($waiting_row = mysqli_fetch_assoc($waiting)) {
        mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Failed', backup_error = 'Came with a restored backup - it was being made, or waiting, when that backup was taken', backup_completed_at = NOW()
            WHERE backup_id = " . intval($waiting_row['backup_id']));
    }

    // A backup from an older version - bring the database up to this code's
    $warnings = [];
    $update = backupRestoreUpdateDatabase();
    if ($update !== '') {
        $warnings[] = $update;
    }

    if (!$ok) {
        backupRestoreSetStatus($base + ['state' => 'failed', 'message' => "It didn't restore: $error", 'warnings' => $warnings]);
        return [false, "It didn't restore: $error"];
    }
    $message = $base['label'] . ' was restored. ITFlow as it was is kept as the "' . BACKUP_SOURCE_BEFORE_RESTORE . '" backup.';
    backupRestoreSetStatus($base + ['state' => 'done', 'message' => $message, 'warnings' => $warnings]);
    return [true, $message];
}

/** Run the database updates after a restore. '' when it worked, or nothing needed it, else what to tell the admin. */
function backupRestoreUpdateDatabase(): string
{
    $script = realpath(__DIR__ . '/../scripts/update_cli.php');
    if ($script === false || !function_exists('exec') || PHP_BINARY === '') {
        return 'The database updates couldn\'t be run from here - finish them from Maintenance > Update, or with php scripts/update_cli.php --update_db.';
    }
    $output = [];
    $return = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --update_db 2>&1', $output, $return);
    if ($return !== 0) {
        $lines = array_values(array_filter(array_map('trim', $output), 'strlen'));
        return 'The database update stopped: ' . implode(' | ', array_slice($lines, -2)) . ' - finish it from Maintenance > Update.';
    }
    return '';
}

/*
 * A backup file on this server - an upload - checked with its key, moved in with the other
 * backups under a backup's name, and listed as Uploaded. Removed when it isn't a backup.
 * Returns [backup_id, message].
 */
function backupRestoreAdopt(mysqli $mysqli, string $tmp, string $typed_key, string $who, bool $uploaded): array
{
    $error = null;
    $meta = backupInspectArchive($tmp, backupRestoreKey($typed_key), $error);
    if ($meta === false) {
        $uploaded || @unlink($tmp);
        return [0, "That isn't a backup that can be opened: $error"];
    }
    $type = in_array($meta['type'], backupUnattendedTypes(), true) ? $meta['type'] : BACKUP_TYPE_FULL;
    $name = backupBuildFileName($type, randomString(32));
    $path = backupRestoreDir() . '/' . $name;
    if (!($uploaded ? move_uploaded_file($tmp, $path) : @rename($tmp, $path))) {
        $uploaded || @unlink($tmp);
        return [0, 'It couldn\'t be saved with the backups - can the backups folder be written to?'];
    }
    @chmod($path, 0600);
    $size = intval(filesize($path) ?: 0);
    $sha = (string) hash_file('sha256', $path);
    mysqli_query($mysqli, "INSERT INTO backups SET backup_type = '" . escapeSql($type) . "', backup_file_name = '" . escapeSql($name) . "', backup_size = $size,
        backup_sha256 = '$sha', backup_status = 'Complete', backup_source = '" . BACKUP_SOURCE_UPLOADED . "', backup_created_by = '" . escapeSql($who) . "', backup_completed_at = NOW()");
    return [intval(mysqli_insert_id($mysqli)), backupTypeLabel($type) . ' taken ' . $meta['generated'] . ' uploaded (' . backupFormatBytes($size) . ').'];
}

/** One piece of a browser upload - under PHP's request limit on this server. */
function backupRestoreChunkBytes(): int
{
    $limit = backupMaxUploadBytes();
    return $limit > 0 ? max(512 * 1024, min(BACKUP_RESTORE_CHUNK_BYTES, $limit - 256 * 1024)) : BACKUP_RESTORE_CHUNK_BYTES;
}

/** Where a browser upload's pieces are put together. */
function backupRestorePartPath(string $id): string
{
    return backupRestoreDir() . '/upload_' . preg_replace('/[^a-f0-9]/', '', $id) . '.part';
}

/* Pieces of uploads that never finished - a browser closed part way - go after a day */
function backupRestoreTidyParts(): void
{
    foreach (glob(backupRestoreDir() . '/upload_*.part') ?: [] as $old) {
        if (filemtime($old) < time() - 86400) {
            @unlink($old);
        }
    }
}
