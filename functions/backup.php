<?php

// Backup creation, storage, retention and restore
// Shared by the admin pages, cron/backup.php and scripts/restore_cli.php
//
// Loaded from both sides: the admin tier requires it in a web request and cron/backup.php
// and scripts/restore_cli.php require it on the command line. Nothing in here may touch
// $_SERVER, $_SESSION or any other superglobal - there is no DOCUMENT_ROOT, no session and
// no request when cron runs it.
//
// The archive is a zip whose entries are encrypted with WinZip AES-256. The key is a single
// per-install value kept in config.php and NEVER in the database, so a backup that leaks
// (from the web root, from a synced folder, from a stolen laptop) cannot be opened with
// anything the database contains. It is not in the file name either - the random token in
// the name is only there to make the path unguessable.

DEFINE("BACKUP_TYPE_FULL", "full");
DEFINE("BACKUP_TYPE_DATABASE", "database");
DEFINE("BACKUP_TYPE_MASTER_KEY", "master_key");

/**
 * The backup types that can be produced without a logged-in user.
 * master_key needs the session master key, so cron can never make one.
 */
function backupUnattendedTypes(): array
{
    return [BACKUP_TYPE_FULL, BACKUP_TYPE_DATABASE];
}

function backupAllTypes(): array
{
    return [BACKUP_TYPE_FULL, BACKUP_TYPE_DATABASE, BACKUP_TYPE_MASTER_KEY];
}

function backupTypeLabel(string $type): string
{
    switch ($type) {
        case BACKUP_TYPE_FULL:
            return "Full Backup";
        case BACKUP_TYPE_DATABASE:
            return "Database Only";
        case BACKUP_TYPE_MASTER_KEY:
            return "Master Key";
    }
    return $type;
}

/**
 * Absolute path of the ITFlow root, worked out from this file rather than DOCUMENT_ROOT
 * so it is identical under the web server and under cron.
 */
function backupAppRoot(): string
{
    return dirname(__DIR__);
}

/**
 * The encryption key for this install.
 *
 * Lives in config.php only. Generated and appended on first use so nobody has to think
 * about it, then displayed in Settings > Backup so it can be written down - without it a
 * backup cannot be restored, on this server or any other.
 *
 * Returns an empty string if config.php could not be written, which every caller treats
 * as a hard failure: an unencrypted backup is worse than no backup.
 */
function backupEncryptionKey(): string
{
    global $config_backup_key;

    if (!empty($config_backup_key)) {
        return $config_backup_key;
    }

    $config_file = backupAppRoot() . "/config.php";

    if (!is_writable($config_file)) {
        return '';
    }

    $key = randomString(32);

    // Re-read rather than trusting the global: another request may have generated one
    // between the check above and here, and two keys would orphan the first backup.
    $existing = @file_get_contents($config_file);
    if ($existing !== false && preg_match('/\$config_backup_key\s*=\s*[\'"]([^\'"]+)[\'"]/', $existing, $match)) {
        $config_backup_key = $match[1];
        return $config_backup_key;
    }

    $line = "\n// Backup encryption key - keep a copy somewhere safe, backups cannot be restored without it\n";
    $line .= "\$config_backup_key = '" . $key . "';\n";

    if (@file_put_contents($config_file, $line, FILE_APPEND | LOCK_EX) === false) {
        return '';
    }

    $config_backup_key = $key;

    return $key;
}

/**
 * Keep the database handle usable across long stretches of non-database work.
 *
 * A backup dumps, zips and encrypts for minutes at a time without issuing a single query.
 * The connection is idle throughout, and a server with a short wait_timeout closes it - so
 * the tiny UPDATE that marks the backup complete is the thing that fails, long after the
 * archive was written correctly. Under PHP 8.1's default report mode that surfaces as an
 * uncaught mysqli_sql_exception, which under the dispatcher takes the rest of the cron
 * cycle with it.
 *
 * Called before every write that follows long file work. Returns a working handle, which
 * may be a new one - callers must assign the result. $GLOBALS['mysqli'] is updated too,
 * because logAudit(), appNotify() and the job scripts all reach for the global.
 */
function backupDbEnsure($mysqli)
{
    try {
        if (@mysqli_query($mysqli, "SELECT 1")) {
            return $mysqli;
        }
    } catch (Throwable $e) {
        // Connection is gone - fall through and rebuild it
    }

    global $dbhost, $dbusername, $dbpassword, $database;

    try {
        $fresh = @mysqli_connect($dbhost, $dbusername, $dbpassword, $database);
    } catch (Throwable $e) {
        $fresh = false;
    }

    if ($fresh instanceof mysqli) {
        backupDbHoldOpen($fresh);
        $GLOBALS['mysqli'] = $fresh;
        return $fresh;
    }

    // Nothing more to be done here. The caller's query will throw and be reported as the
    // job failing, which is the correct outcome - better than pretending it succeeded.
    return $mysqli;
}

/**
 * Ask the server not to hang up during the quiet stretches. Best effort: a host may cap
 * or refuse this, which is why backupDbEnsure() still exists.
 */
function backupDbHoldOpen($mysqli): void
{
    try {
        @mysqli_query($mysqli, "SET SESSION wait_timeout = 28800");
    } catch (Throwable $e) {
        // Not permitted here - the reconnect path covers it
    }
}

/**
 * Where finished archives are kept. Overridable with $config_backup_path in config.php
 * for anyone who would rather keep them off the web root entirely - which is the better
 * place for them, and what the docs recommend.
 */
function backupStorageDir(): string
{
    global $config_backup_path;

    if (!empty($config_backup_path)) {
        $dir = rtrim($config_backup_path, '/\\');
    } else {
        $dir = backupAppRoot() . "/uploads/backups";
    }

    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    backupHardenStorageDir($dir);

    return $dir;
}

/**
 * Re-assert the guards on a directory that must never be served.
 *
 * The .htaccess only covers Apache. It is deliberately a deny-all rather than the
 * "turn PHP off" rule used elsewhere in uploads/, because nothing in here should ever
 * be reachable over HTTP by any means. nginx installs get no protection from this file
 * at all, which is why the archives are encrypted and their names carry a random token.
 */
function backupHardenStorageDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $htaccess = $dir . "/.htaccess";
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nOptions -ExecCGI -Indexes\nphp_flag engine off\n");
    }

    $index = $dir . "/index.php";
    if (!file_exists($index)) {
        @file_put_contents($index, "<?php // Silence is golden\n");
    }
}

/**
 * uploads/ carries its own guards, and a restore wipes the directory before extracting.
 * A backup taken before those guards existed would therefore silently remove them, so
 * they are rewritten after every restore whatever the archive contained.
 */
function backupAssertUploadsGuards(): void
{
    $uploads = backupAppRoot() . "/uploads";

    if (!is_dir($uploads)) {
        @mkdir($uploads, 0750, true);
    }

    $htaccess = $uploads . "/.htaccess";
    $wanted = "Options -ExecCGI -Indexes\nphp_flag engine off\nRemoveHandler .php .phtml .phar .phps\nRemoveType .php .phtml .phar .phps\n<FilesMatch \"\\.(php|phtml|phar|phps|cgi|pl|sh)\$\">\n  Require all denied\n</FilesMatch>\n";

    // Overwrite unconditionally - an archive is allowed to carry a .htaccess, it is not
    // allowed to decide what ours says.
    @file_put_contents($htaccess, $wanted);

    $index = $uploads . "/index.php";
    if (!file_exists($index)) {
        @file_put_contents($index, "");
    }
}

/**
 * itflow_20260731-184500_full_<32 random chars>.zip
 *
 * The token is an unguessable path component, not a key. It buys nothing on its own -
 * the encryption is what protects the contents - but it stops a directory guess on an
 * install whose web server serves the folder anyway.
 */
function backupBuildFileName(string $type, string $token, ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();

    return "itflow_" . date('Ymd-His', $timestamp) . "_" . $type . "_" . $token . ".zip";
}

/**
 * Branch and commit read straight out of .git - CONTRIBUTING rule 6 rules out shelling
 * out to git, and the old backup handler was one of the last places still doing it.
 */
function backupGitInfo(): array
{
    $info = ['branch' => 'N/A', 'commit' => 'N/A'];

    $git_dir = backupAppRoot() . "/.git";
    if (!is_dir($git_dir)) {
        return $info;
    }

    $head = @file_get_contents($git_dir . "/HEAD");
    if ($head === false) {
        return $info;
    }

    $head = trim($head);

    if (str_starts_with($head, 'ref:')) {
        $ref = trim(substr($head, 4));
        $info['branch'] = basename($ref);

        $ref_file = $git_dir . "/" . $ref;
        if (is_file($ref_file)) {
            $info['commit'] = trim(@file_get_contents($ref_file) ?: 'N/A');
        } else {
            // Packed refs - the loose file is gone once git gc has run
            $packed = @file_get_contents($git_dir . "/packed-refs");
            if ($packed !== false) {
                foreach (explode("\n", $packed) as $line) {
                    if (str_ends_with(trim($line), " " . $ref)) {
                        $info['commit'] = strtok(trim($line), ' ');
                        break;
                    }
                }
            }
        }
    } else {
        // Detached head
        $info['commit'] = $head;
    }

    return $info;
}

/**
 * Stream a SQL dump of schema and data into $sql_file.
 *
 * Every value goes through real_escape_string, which turns newlines into \n, so no
 * statement in the output ever contains a raw newline inside a quoted value. That is what
 * lets the importer split statements by accumulating lines until one ends in a semicolon.
 * Anything that changes the escaping here has to change the importer too.
 */
function backupDumpDatabase(mysqli $mysqli, string $sql_file, ?string &$error = null): bool
{
    try {
        return backupDumpDatabaseInner($mysqli, $sql_file, $error);
    } catch (Throwable $e) {
        $error = $error ?: $e->getMessage();
        return false;
    }
}

function backupDumpDatabaseInner(mysqli $mysqli, string $sql_file, ?string &$error = null): bool
{
    $fh = fopen($sql_file, 'wb');
    if (!$fh) {
        $error = "Cannot open dump file for writing";
        return false;
    }

    $write = function ($line) use ($fh) {
        fwrite($fh, $line);
        fwrite($fh, "\n");
    };

    $write("-- ITFlow database backup");
    $write("-- Generated " . date('Y-m-d H:i:s'));
    $write("SET NAMES 'utf8mb4';");
    $write("SET FOREIGN_KEY_CHECKS = 0;");
    $write("SET UNIQUE_CHECKS = 0;");
    $write("");

    $tables = [];
    $views = [];

    $res = mysqli_query($mysqli, "SHOW FULL TABLES");
    if (!$res) {
        fclose($fh);
        $error = "Could not list tables: " . mysqli_error($mysqli);
        return false;
    }
    while ($row = mysqli_fetch_array($res, MYSQLI_NUM)) {
        if (strtoupper($row[1] ?? '') === 'VIEW') {
            $views[] = $row[0];
        } else {
            $tables[] = $row[0];
        }
    }
    mysqli_free_result($res);

    if (empty($tables)) {
        fclose($fh);
        $error = "Database contains no tables - refusing to write an empty backup";
        return false;
    }

    foreach ($tables as $table) {
        $create_res = mysqli_query($mysqli, "SHOW CREATE TABLE `$table`");
        if (!$create_res) {
            fclose($fh);
            $error = "Could not read structure of `$table`: " . mysqli_error($mysqli);
            return false;
        }
        $create_row = mysqli_fetch_assoc($create_res);
        $create_sql = array_values($create_row)[1] ?? '';
        mysqli_free_result($create_res);

        $write("-- Table `$table`");
        $write("DROP TABLE IF EXISTS `$table`;");
        $write($create_sql . ";");
        $write("");

        // Unbuffered so a large table does not have to fit in memory. Nothing else may
        // query on this connection until the result is closed.
        $data_res = mysqli_query($mysqli, "SELECT * FROM `$table`", MYSQLI_USE_RESULT);
        if ($data_res) {
            while ($row = mysqli_fetch_assoc($data_res)) {
                $cols = [];
                $vals = [];
                foreach ($row as $col => $val) {
                    $cols[] = '`' . $col . '`';
                    $vals[] = is_null($val) ? "NULL" : "'" . mysqli_real_escape_string($mysqli, $val) . "'";
                }
                $write("INSERT INTO `$table` (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ");");
            }
            mysqli_free_result($data_res);
            $write("");
        }
    }

    foreach ($views as $view) {
        $view_res = mysqli_query($mysqli, "SHOW CREATE VIEW `$view`");
        if ($view_res) {
            $row = mysqli_fetch_assoc($view_res);
            $create_view = $row['Create View'] ?? '';
            mysqli_free_result($view_res);

            $write("-- View `$view`");
            $write("DROP VIEW IF EXISTS `$view`;");
            $write(rtrim($create_view, ';') . ";");
            $write("");
        }
    }

    $write("SET FOREIGN_KEY_CHECKS = 1;");
    $write("SET UNIQUE_CHECKS = 1;");

    fclose($fh);

    return true;
}

/**
 * Zip the uploads folder, skipping symlinks and the backup storage directory itself -
 * without that exclusion every full backup would contain all previous full backups.
 */
function backupZipUploads(string $folder, string $zip_path, ?string &$error = null): bool
{
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $error = "Could not create the uploads archive";
        return false;
    }

    $folder_real = realpath($folder);
    if (!$folder_real || !is_dir($folder_real)) {
        // Nothing to add - a fresh install may not have written to uploads yet
        $zip->close();
        return true;
    }

    $exclude = realpath(backupStorageDir());

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder_real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if ($file->isDir() || $file->isLink()) {
            continue;
        }

        $file_path = $file->getRealPath();
        if ($file_path === false) {
            continue;
        }

        // Stay inside the uploads boundary
        if (strpos($file_path, $folder_real . DIRECTORY_SEPARATOR) !== 0) {
            continue;
        }

        // Never nest backups inside a backup
        if ($exclude && strpos($file_path, $exclude . DIRECTORY_SEPARATOR) === 0) {
            continue;
        }

        $zip->addFile($file_path, substr($file_path, strlen($folder_real) + 1));
    }

    $zip->close();

    return true;
}

/**
 * Put the finished parts into an AES-256 encrypted zip.
 *
 * ZipArchive encrypts entry data but not entry names, which is fine - the names are
 * db.sql, uploads.zip and version.txt on every archive we make.
 */
function backupSealArchive(array $entries, string $zip_path, string $key, ?string &$error = null): bool
{
    if ($key === '') {
        $error = "No backup encryption key available";
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $error = "Could not create the backup archive";
        return false;
    }

    $zip->setPassword($key);

    foreach ($entries as $name => $path) {
        if (!$zip->addFile($path, $name)) {
            $zip->close();
            $error = "Could not add $name to the archive";
            return false;
        }
        if (!$zip->setEncryptionName($name, ZipArchive::EM_AES_256)) {
            $zip->close();
            $error = "This server's zip library cannot produce AES-256 encrypted archives (libzip 1.2 or newer is required)";
            return false;
        }
    }

    if (!$zip->close()) {
        $error = "Could not finish writing the archive";
        return false;
    }

    return true;
}

/**
 * Put a backup on the queue for the dispatcher to build.
 *
 * The web tier never generates an archive inline. A dump of a real install takes longer
 * than a web request is allowed to live on most hosts - PHP-FPM's request_terminate_timeout
 * and the front end's read timeout both cut it off, and neither is affected by
 * set_time_limit() - so the button records the intent and cron/backup.php does the work
 * within the minute. Same shape as the Run Now button on Settings > Cron.
 */
function backupQueue(mysqli $mysqli, string $type, string $created_by, ?string &$error = null): int
{
    if (!in_array($type, backupUnattendedTypes(), true)) {
        $error = "That backup type cannot be queued";
        return 0;
    }

    if (backupEncryptionKey() === '') {
        $error = "No backup encryption key is set and config.php is not writable - cannot make an encrypted backup";
        return 0;
    }

    $type_esc = escapeSql($type);
    $created_by_esc = escapeSql($created_by);

    mysqli_query($mysqli, "INSERT INTO backups SET backup_type = '$type_esc', backup_file_name = '', backup_status = 'Pending', backup_source = 'Manual', backup_created_by = '$created_by_esc'");

    $backup_id = intval(mysqli_insert_id($mysqli));

    // Ask the dispatcher to run the backup job on its next pass. Without this the row sits
    // Pending for ever on a default install: the job ships disabled, so the schedule never
    // calls it and nothing ever builds what the button just queued. run_now is honoured
    // whether or not a job is enabled, which is exactly the case this needs.
    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_run_now = 1 WHERE cron_job_name = 'backup'");

    return $backup_id;
}

/**
 * Build every queued backup. Called by cron/backup.php.
 */
function backupRunQueued(mysqli $mysqli): int
{
    $built = 0;

    $sql = mysqli_query($mysqli, "SELECT backup_id, backup_type, backup_created_by FROM backups WHERE backup_status = 'Pending' ORDER BY backup_created_at ASC");
    if (!$sql) {
        return 0;
    }

    $queued = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $queued[] = $row;
    }

    foreach ($queued as $row) {
        $backup_id = intval($row['backup_id']);

        // Claim before building so a second dispatcher cannot pick up the same row
        mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Running' WHERE backup_id = $backup_id AND backup_status = 'Pending'");
        if (mysqli_affected_rows($mysqli) !== 1) {
            continue;
        }

        $error = null;
        $built_ok = backupCreate($mysqli, $row['backup_type'], $row['backup_created_by'] ?: 'Cron', 'Manual', $error, [], $backup_id);

        // backupCreate may have rebuilt the connection under us
        $mysqli = backupDbEnsure($GLOBALS['mysqli'] ?? $mysqli);

        if ($built_ok) {
            $built++;
            appNotify("Backup", backupTypeLabel($row['backup_type']) . " is ready to download", "/admin/backup.php");
            logAudit("Backup", "Create", backupTypeLabel($row['backup_type']) . " completed");
        } else {
            appNotify("Backup", backupTypeLabel($row['backup_type']) . " failed: " . $error, "/admin/backup.php");
            logAudit("Backup", "Create", backupTypeLabel($row['backup_type']) . " failed: " . $error);
        }
    }

    return $built;
}

/**
 * Create a backup and record it.
 *
 * $extra['master_key'] is required for the master_key type and ignored for the others.
 * $backup_id updates an existing row (a queued one) rather than inserting a new one.
 * Returns the backup_id, or 0 on failure with $error set.
 */
function backupCreate(mysqli $mysqli, string $type, string $created_by, string $source, ?string &$error = null, array $extra = [], int $backup_id = 0): int
{
    if (!in_array($type, backupAllTypes(), true)) {
        $error = "Unknown backup type";
        return 0;
    }

    if ($type === BACKUP_TYPE_MASTER_KEY && empty($extra['master_key'])) {
        $error = "The master key backup needs the master key and can only be made from the web interface";
        return 0;
    }

    $key = backupEncryptionKey();
    if ($key === '') {
        $error = "No backup encryption key is set and config.php is not writable - cannot make an encrypted backup";
        return 0;
    }

    @set_time_limit(0);
    backupDbHoldOpen($mysqli);

    $token = randomString(32);
    $file_name = backupBuildFileName($type, $token);
    $storage_dir = backupStorageDir();
    $final_path = $storage_dir . "/" . $file_name;

    $created_by_esc = escapeSql($created_by);
    $type_esc = escapeSql($type);
    $source_esc = escapeSql($source);
    $file_name_esc = escapeSql($file_name);

    if ($backup_id > 0) {
        mysqli_query($mysqli, "UPDATE backups SET backup_file_name = '$file_name_esc', backup_status = 'Running' WHERE backup_id = $backup_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO backups SET backup_type = '$type_esc', backup_file_name = '$file_name_esc', backup_status = 'Running', backup_source = '$source_esc', backup_created_by = '$created_by_esc'");
        $backup_id = intval(mysqli_insert_id($mysqli));
    }

    if (!is_dir($storage_dir) || !is_writable($storage_dir)) {
        $error = "Backup directory is not writable: $storage_dir";
        $error_esc = escapeSql($error);
        mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Failed', backup_error = '$error_esc', backup_completed_at = NOW() WHERE backup_id = $backup_id");
        return 0;
    }

    // Temp files live outside the web root and are removed however this function exits
    $temp_files = [];
    $cleanup = function () use (&$temp_files) {
        foreach ($temp_files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    };

    $fail = function ($message) use (&$mysqli, $backup_id, $cleanup, $final_path, &$error) {
        $cleanup();
        $mysqli = backupDbEnsure($mysqli);
        if (is_file($final_path)) {
            @unlink($final_path);
        }
        $error = $message;
        $message_esc = escapeSql($message);
        mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Failed', backup_error = '$message_esc', backup_completed_at = NOW() WHERE backup_id = $backup_id");
        return 0;
    };

    $entries = [];
    $sub_error = null;

    // --- db.sql ---
    if ($type === BACKUP_TYPE_FULL || $type === BACKUP_TYPE_DATABASE) {
        $sql_file = tempnam(sys_get_temp_dir(), "itflow_sql_");
        $temp_files[] = $sql_file;
        @chmod($sql_file, 0600);

        if (!backupDumpDatabase($mysqli, $sql_file, $sub_error)) {
            return $fail($sub_error ?? "Database dump failed");
        }
        $entries['db.sql'] = $sql_file;
    }

    // --- uploads.zip ---
    if ($type === BACKUP_TYPE_FULL) {
        $uploads_zip = tempnam(sys_get_temp_dir(), "itflow_uploads_");
        $temp_files[] = $uploads_zip;
        @chmod($uploads_zip, 0600);

        if (!backupZipUploads(backupAppRoot() . "/uploads", $uploads_zip, $sub_error)) {
            return $fail($sub_error ?? "Could not archive the uploads directory");
        }
        $entries['uploads.zip'] = $uploads_zip;
    }

    // --- master_key.txt ---
    if ($type === BACKUP_TYPE_MASTER_KEY) {
        $key_file = tempnam(sys_get_temp_dir(), "itflow_mk_");
        $temp_files[] = $key_file;
        @chmod($key_file, 0600);

        $key_content = "ITFlow master encryption key\n";
        $key_content .= "============================\n\n";
        $key_content .= $extra['master_key'] . "\n\n";
        $key_content .= "This key decrypts every credential stored in this ITFlow install.\n";
        $key_content .= "It is only needed if every user password is lost - a normal restore\n";
        $key_content .= "recovers the vault from the database on its own.\n";
        $key_content .= "Exported " . date('Y-m-d H:i:s') . " by " . $created_by . "\n";

        file_put_contents($key_file, $key_content);
        $entries['master_key.txt'] = $key_file;
    }

    // --- version.txt ---
    $git = backupGitInfo();
    $version_file = tempnam(sys_get_temp_dir(), "itflow_ver_");
    $temp_files[] = $version_file;
    @chmod($version_file, 0600);

    $meta = "ITFlow Backup Metadata\n";
    $meta .= "-----------------------------\n";
    $meta .= "Backup Type: " . $type . "\n";
    $meta .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $meta .= "Generated By: " . $created_by . "\n";
    $meta .= "Source: " . $source . "\n";
    $meta .= "Host: " . gethostname() . "\n";
    $meta .= "Git Branch: " . $git['branch'] . "\n";
    $meta .= "Git Commit: " . $git['commit'] . "\n";
    $meta .= "ITFlow Version: " . (defined('APP_VERSION') ? APP_VERSION : 'Unknown') . "\n";
    $meta .= "Database Version: " . backupCurrentDatabaseVersion($mysqli) . "\n";
    $meta .= "Checksums (SHA256):\n";
    foreach ($entries as $name => $path) {
        $meta .= "  " . $name . ": " . (hash_file('sha256', $path) ?: 'N/A') . "\n";
    }

    file_put_contents($version_file, $meta);
    $entries['version.txt'] = $version_file;

    // --- seal ---
    if (!backupSealArchive($entries, $final_path, $key, $sub_error)) {
        return $fail($sub_error ?? "Could not encrypt the archive");
    }

    @chmod($final_path, 0600);
    $cleanup();

    $size = filesize($final_path) ?: 0;
    $sha = hash_file('sha256', $final_path) ?: '';
    $sha_esc = escapeSql($sha);

    // The archive is written by this point. Everything below is bookkeeping, and it runs
    // after minutes of dumping, zipping and encrypting with the connection idle.
    $mysqli = backupDbEnsure($mysqli);

    mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Complete', backup_size = $size, backup_sha256 = '$sha_esc', backup_completed_at = NOW() WHERE backup_id = $backup_id");

    return $backup_id;
}

/**
 * The database version the install is currently stamped at, read from settings so it is
 * correct under cron as well as in a request.
 */
function backupCurrentDatabaseVersion(mysqli $mysqli): string
{
    $res = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return $row['config_current_database_version'] ?? 'Unknown';
    }
    return 'Unknown';
}

/**
 * Remove a backup, file and row together. Missing files are not an error - the point is
 * that neither half is left behind.
 */
function backupDeleteById(mysqli $mysqli, int $backup_id): bool
{
    $backup_id = intval($backup_id);

    $res = mysqli_query($mysqli, "SELECT backup_file_name FROM backups WHERE backup_id = $backup_id");
    if (!$res || mysqli_num_rows($res) !== 1) {
        return false;
    }
    $row = mysqli_fetch_assoc($res);

    $path = backupResolvePath($row['backup_file_name']);
    if ($path !== false && is_file($path)) {
        @unlink($path);
    }

    mysqli_query($mysqli, "DELETE FROM backups WHERE backup_id = $backup_id");

    return true;
}

/**
 * Turn a stored file name into an absolute path, refusing anything that tries to leave
 * the backup directory. The name comes from our own row, but this is the only function
 * that turns a database value into a filesystem path so the check belongs here.
 */
function backupResolvePath(string $file_name)
{
    $file_name = basename($file_name);

    if ($file_name === '' || !preg_match('/^itflow_[0-9]{8}-[0-9]{6}_[a-z_]+_[A-Za-z0-9\-_]{32}\.zip$/', $file_name)) {
        return false;
    }

    $dir = realpath(backupStorageDir());
    if ($dir === false) {
        return false;
    }

    return $dir . "/" . $file_name;
}

/**
 * Delete backups past the retention settings, and reconcile both kinds of orphan.
 *
 * Safe to run more than once in a day - everything here is a delete. Never removes the
 * most recent complete backup whatever the settings say, so a badly set retention cannot
 * leave an install with nothing.
 */
function backupRunRetention(mysqli $mysqli): array
{
    $result = ['deleted' => 0, 'orphan_files' => 0, 'orphan_rows' => 0, 'recovered' => 0];

    $res = mysqli_query($mysqli, "SELECT config_backup_retention_days, config_backup_retention_count FROM settings WHERE company_id = 1");
    $settings = $res ? mysqli_fetch_assoc($res) : [];

    $days = intval($settings['config_backup_retention_days'] ?? 30);
    $count = intval($settings['config_backup_retention_count'] ?? 5);

    // A run whose connection died before it could mark itself complete leaves a Running row
    // for an archive that is sitting there perfectly good. Anything still Running after six
    // hours is finished one way or the other, decided by whether the file exists.
    $stale = mysqli_query($mysqli, "SELECT backup_id, backup_file_name FROM backups WHERE backup_status IN ('Running','Pending') AND backup_created_at < NOW() - INTERVAL 6 HOUR");
    if ($stale) {
        while ($row = mysqli_fetch_assoc($stale)) {
            $stale_id = intval($row['backup_id']);
            $stale_path = $row['backup_file_name'] === '' ? false : backupResolvePath($row['backup_file_name']);

            if ($stale_path !== false && is_file($stale_path)) {
                $stale_size = filesize($stale_path) ?: 0;
                $stale_sha = escapeSql(hash_file('sha256', $stale_path) ?: '');
                mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Complete', backup_size = $stale_size, backup_sha256 = '$stale_sha', backup_completed_at = backup_created_at WHERE backup_id = $stale_id");
                $result['recovered'] = ($result['recovered'] ?? 0) + 1;
            } else {
                mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Failed', backup_error = 'Run did not finish', backup_completed_at = NOW() WHERE backup_id = $stale_id");
            }
        }
    }


    // Reconciliation runs BEFORE the retention maths, not after. A row this rescues or
    // adopts has to be counted by the same pass that decides what to delete - otherwise it
    // escapes retention until tomorrow, and a second run on the same day is not the no-op
    // CONTRIBUTING's third cron rule asks for.
    // Orphans: rows whose file is gone, and files with no row
    $known = [];
    $rows = mysqli_query($mysqli, "SELECT backup_id, backup_file_name, backup_status FROM backups");
    if ($rows) {
        while ($row = mysqli_fetch_assoc($rows)) {
            $known[$row['backup_file_name']] = true;
            if ($row['backup_status'] !== 'Complete') {
                continue;
            }
            $path = backupResolvePath($row['backup_file_name']);
            if ($path === false || !is_file($path)) {
                mysqli_query($mysqli, "UPDATE backups SET backup_status = 'Missing' WHERE backup_id = " . intval($row['backup_id']));
                $result['orphan_rows']++;
            }
        }
    }

    // Archives on disk that the table does not know about are ADOPTED, not deleted.
    // A restore brings back the backups table as it was when the backup was taken, so
    // every archive made since then looks unknown - deleting them would quietly destroy
    // good backups, including the one that was just restored from. Once adopted they age
    // out under the normal rules. The strict name check in backupResolvePath is what stops
    // an unrelated file being adopted.
    $dir = backupStorageDir();
    foreach (glob($dir . "/itflow_*.zip") ?: [] as $file) {
        $name = basename($file);

        if (isset($known[$name])) {
            continue;
        }

        if (backupResolvePath($name) === false) {
            continue;
        }

        $type = 'full';
        $created = date('Y-m-d H:i:s', filemtime($file) ?: time());
        if (preg_match('/^itflow_([0-9]{8})-([0-9]{6})_([a-z_]+)_[A-Za-z0-9\-_]{32}\.zip$/', $name, $m)) {
            $stamp = strtotime($m[1] . ' ' . $m[2]);
            if ($stamp !== false) {
                $created = date('Y-m-d H:i:s', $stamp);
            }
            if (in_array($m[3], backupAllTypes(), true)) {
                $type = $m[3];
            }
        }

        $name_esc = escapeSql($name);
        $type_esc = escapeSql($type);
        $size = filesize($file) ?: 0;

        mysqli_query($mysqli, "INSERT INTO backups SET backup_type = '$type_esc', backup_file_name = '$name_esc', backup_size = $size, backup_status = 'Complete', backup_source = 'Adopted', backup_created_at = '$created', backup_completed_at = '$created'");

        $result['orphan_files']++;
    }


    // Keep the newest $count complete backups OF EACH TYPE, regardless of age.
    //
    // Per type rather than one shared pool: a master key export is a few hundred bytes and
    // a full backup is gigabytes, so counting them together means five master key exports
    // silently evict every real backup on the box. They are also the artefact you would
    // least want retention to quietly remove.
    $keep = [];
    foreach (backupAllTypes() as $keep_type) {
        $keep_type_esc = escapeSql($keep_type);
        $keep_res = mysqli_query($mysqli, "SELECT backup_id FROM backups WHERE backup_status = 'Complete' AND backup_type = '$keep_type_esc' ORDER BY backup_created_at DESC LIMIT " . max(1, $count));
        if ($keep_res) {
            while ($row = mysqli_fetch_assoc($keep_res)) {
                $keep[] = intval($row['backup_id']);
            }
        }
    }

    $keep_clause = empty($keep) ? "" : " AND backup_id NOT IN (" . implode(",", $keep) . ")";

    // Age-based removal
    if ($days > 0) {
        $old = mysqli_query($mysqli, "SELECT backup_id FROM backups WHERE backup_created_at < CURDATE() - INTERVAL $days DAY $keep_clause");
        if ($old) {
            while ($row = mysqli_fetch_assoc($old)) {
                if (backupDeleteById($mysqli, intval($row['backup_id']))) {
                    $result['deleted']++;
                }
            }
        }
    }

    // Count-based removal
    if ($count > 0 && !empty($keep)) {
        $surplus = mysqli_query($mysqli, "SELECT backup_id FROM backups WHERE backup_status = 'Complete' $keep_clause");
        if ($surplus) {
            while ($row = mysqli_fetch_assoc($surplus)) {
                if (backupDeleteById($mysqli, intval($row['backup_id']))) {
                    $result['deleted']++;
                }
            }
        }
    }

    // Failed rows never had a usable file
    $failed = mysqli_query($mysqli, "SELECT backup_id FROM backups WHERE backup_status = 'Failed' AND backup_created_at < CURDATE() - INTERVAL 7 DAY");
    if ($failed) {
        while ($row = mysqli_fetch_assoc($failed)) {
            if (backupDeleteById($mysqli, intval($row['backup_id']))) {
                $result['deleted']++;
            }
        }
    }

    return $result;
}

/*
 * ###############################################################################################################
 *  RESTORE
 * ###############################################################################################################
 */

/**
 * Open an encrypted archive and read its version.txt without touching the database.
 *
 * This is the pre-flight: it proves the key is right and the archive is one of ours
 * before anything destructive happens.
 */
function backupInspectArchive(string $zip_path, string $key, ?string &$error = null)
{
    if (!is_file($zip_path) || !is_readable($zip_path)) {
        $error = "Backup file not found or not readable";
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        $error = "This file is not a readable zip archive";
        return false;
    }

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false) {
            $names[] = $name;
        }
    }

    if (!in_array('version.txt', $names, true)) {
        $zip->close();
        $error = "This does not look like an ITFlow backup - version.txt is missing";
        return false;
    }

    $zip->setPassword($key);
    $meta_raw = $zip->getFromName('version.txt');

    if ($meta_raw === false) {
        $status = $zip->getStatusString();
        $zip->close();
        if (stripos($status, 'password') !== false) {
            $error = "Wrong backup encryption key for this archive";
        } else {
            $error = "Could not read the archive: $status";
        }
        return false;
    }

    $zip->close();

    $meta = ['raw' => $meta_raw, 'entries' => $names, 'type' => BACKUP_TYPE_FULL, 'database_version' => 'Unknown', 'app_version' => 'Unknown', 'generated' => 'Unknown'];

    foreach (explode("\n", $meta_raw) as $line) {
        if (preg_match('/^Backup Type:\s*(.+)$/', $line, $m)) {
            $meta['type'] = trim($m[1]);
        } elseif (preg_match('/^Database Version:\s*(.+)$/', $line, $m)) {
            $meta['database_version'] = trim($m[1]);
        } elseif (preg_match('/^ITFlow Version:\s*(.+)$/', $line, $m)) {
            $meta['app_version'] = trim($m[1]);
        } elseif (preg_match('/^Generated:\s*(.+)$/', $line, $m)) {
            $meta['generated'] = trim($m[1]);
        }
    }

    if ($meta['type'] === BACKUP_TYPE_MASTER_KEY) {
        $error = "This is a master key export, not a restorable backup";
        return false;
    }

    if (!in_array('db.sql', $names, true)) {
        $error = "This archive contains no database dump";
        return false;
    }

    return $meta;
}

/**
 * Run a SQL file into the database, one statement at a time.
 *
 * Statements are accumulated until a line ends with the delimiter. That is safe for our
 * own dumps because backupDumpDatabase escapes every value, so no raw newline can appear
 * inside a quoted string - see the note there.
 */
function backupImportSql(mysqli $mysqli, $handle, ?string &$error = null): bool
{
    $delimiter = ';';
    $statement = '';
    $line_number = 0;

    while (($line = fgets($handle)) !== false) {
        $line_number++;
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $m)) {
            $delimiter = trim($m[1]);
            continue;
        }

        $statement .= $line;

        if (substr(rtrim($statement), -strlen($delimiter)) === $delimiter) {
            $sql = substr(rtrim($statement), 0, -strlen($delimiter));
            $statement = '';

            if (trim($sql) === '') {
                continue;
            }

            // mysqli throws on error under PHP 8.1's default report mode, so a bad
            // statement has to be caught here rather than tested for. Letting it escape
            // would abort the restore with the tables already dropped and the rollback
            // below never reached - which is the exact failure this function exists to
            // survive.
            try {
                if (!mysqli_query($mysqli, $sql)) {
                    $error = "SQL error near line $line_number: " . mysqli_error($mysqli);
                    return false;
                }
            } catch (Throwable $e) {
                $error = "SQL error near line $line_number: " . $e->getMessage();
                return false;
            }
        }
    }

    if (trim($statement) !== '') {
        $error = "The dump ended in the middle of a statement - the file is truncated";
        return false;
    }

    return true;
}

/**
 * Restore an encrypted archive over this install.
 *
 * Order matters. Everything that can fail without consequence happens first: the key is
 * checked, the archive is unpacked to a temp directory, and the current database is dumped
 * to a rollback file. Only then are the existing tables dropped. If the import fails after
 * that point the rollback dump is put back, so a bad archive cannot leave an install with
 * no database at all.
 *
 * $progress is called with a short status string so the CLI can print it and the web path
 * can ignore it.
 */
function backupRestoreArchive(mysqli $mysqli, string $zip_path, string $key, ?string &$error = null, ?callable $progress = null): bool
{
    $say = function ($message) use ($progress) {
        if ($progress) {
            $progress($message);
        }
    };

    @set_time_limit(0);

    $meta = backupInspectArchive($zip_path, $key, $error);
    if ($meta === false) {
        return false;
    }

    $say("Archive verified (" . backupTypeLabel($meta['type']) . " taken " . $meta['generated'] . ")");

    $temp_dir = sys_get_temp_dir() . "/itflow_restore_" . bin2hex(random_bytes(8));
    if (!mkdir($temp_dir, 0700, true)) {
        $error = "Could not create a temporary directory for the restore";
        return false;
    }

    $cleanup = function () use ($temp_dir) {
        backupDeleteDirectory($temp_dir);
    };

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        $cleanup();
        $error = "Could not reopen the archive";
        return false;
    }

    // Zip-slip guard on the outer archive
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) {
            continue;
        }
        if (!backupSafeEntryName($name)) {
            $zip->close();
            $cleanup();
            $error = "The archive contains an unsafe path: $name";
            return false;
        }
    }

    $zip->setPassword($key);
    if (!$zip->extractTo($temp_dir)) {
        $status = $zip->getStatusString();
        $zip->close();
        $cleanup();
        $error = "Could not extract the archive: $status";
        return false;
    }
    $zip->close();

    // Unpacking a multi-gigabyte archive is minutes of idle connection, and everything
    // below is database work.
    $mysqli = backupDbEnsure($mysqli);
    backupDbHoldOpen($mysqli);

    $sql_path = $temp_dir . "/db.sql";
    if (!is_file($sql_path)) {
        $cleanup();
        $error = "The archive did not contain db.sql";
        return false;
    }

    $say("Dumping the current database so it can be put back if this fails");

    $rollback = tempnam(sys_get_temp_dir(), "itflow_rollback_");
    @chmod($rollback, 0600);
    $rollback_error = null;
    $have_rollback = backupDumpDatabase($mysqli, $rollback, $rollback_error);

    if (!$have_rollback) {
        // An empty database is the normal case on a fresh install, and there is nothing
        // to roll back to. Any other failure means we cannot guarantee recovery.
        if (stripos((string)$rollback_error, 'no tables') === false) {
            $cleanup();
            @unlink($rollback);
            $error = "Could not dump the current database before restoring: $rollback_error";
            return false;
        }
    }

    $say("Replacing the database");

    backupDropAllTables($mysqli);

    $fh = fopen($sql_path, 'r');
    if (!$fh) {
        $cleanup();
        $error = "Could not open db.sql from the archive";
        return false;
    }

    $import_error = null;
    $imported = backupImportSql($mysqli, $fh, $import_error);
    fclose($fh);

    if (!$imported) {
        $error = "Restore failed: $import_error";

        if ($have_rollback) {
            $say("Import failed - putting the previous database back");
            backupDropAllTables($mysqli);

            $rb = fopen($rollback, 'r');
            if ($rb) {
                $rb_error = null;
                if (backupImportSql($mysqli, $rb, $rb_error)) {
                    $error .= " - the previous database has been restored, nothing was lost";
                } else {
                    $error .= " - AND the rollback also failed ($rb_error). The dump of your previous database is at $rollback - do not delete it";
                }
                fclose($rb);
            }
        }

        $cleanup();
        if (strpos($error, $rollback) === false) {
            @unlink($rollback);
        }
        return false;
    }

    @unlink($rollback);

    // --- uploads ---
    $uploads_zip = $temp_dir . "/uploads.zip";
    if (is_file($uploads_zip)) {
        $say("Restoring uploads");

        $uploads_dir = backupAppRoot() . "/uploads";

        $uz = new ZipArchive();
        if ($uz->open($uploads_zip) !== true) {
            $cleanup();
            backupAssertUploadsGuards();
            $error = "The database was restored but uploads.zip could not be opened";
            return false;
        }

        for ($i = 0; $i < $uz->numFiles; $i++) {
            $name = $uz->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (!backupSafeEntryName($name)) {
                $uz->close();
                $cleanup();
                backupAssertUploadsGuards();
                $error = "The database was restored but uploads.zip contains an unsafe path: $name";
                return false;
            }
        }

        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0750, true);
        } else {
            // Clear uploads, but never the backup directory. It lives under uploads/ by
            // default, and wiping it would destroy every other archive on the box -
            // including the one being restored from, if it was copied in there.
            backupEmptyDirectory($uploads_dir, [backupStorageDir()]);
        }

        $extracted = $uz->extractTo($uploads_dir);
        $uz->close();

        if (!$extracted) {
            $cleanup();
            backupAssertUploadsGuards();
            $error = "The database was restored but the uploads could not be extracted";
            return false;
        }
    }

    // Whatever the archive carried, our own guards are what ends up on disk
    backupAssertUploadsGuards();
    backupHardenStorageDir(backupStorageDir());

    $cleanup();

    $say("Restore complete");

    return true;
}

/**
 * Drop every table in the current database. Used before an import and again before a
 * rollback import, so it is written once.
 */
function backupDropAllTables(mysqli $mysqli): void
{
    try {
        mysqli_query($mysqli, "SET FOREIGN_KEY_CHECKS = 0");
        $tables = mysqli_query($mysqli, "SHOW TABLES");
        $names = [];
        if ($tables) {
            while ($row = mysqli_fetch_row($tables)) {
                $names[] = $row[0];
            }
        }
        foreach ($names as $name) {
            mysqli_query($mysqli, "DROP TABLE IF EXISTS `" . $name . "`");
        }
        mysqli_query($mysqli, "SET FOREIGN_KEY_CHECKS = 1");
    } catch (Throwable $e) {
        // Nothing useful to do here - the import that follows will report the real problem
    }
}

/**
 * Reject absolute paths, traversal and anything that resolves oddly on Windows.
 */
function backupSafeEntryName(string $name): bool
{
    if ($name === '') {
        return false;
    }
    if (strpos($name, '..') !== false) {
        return false;
    }
    if (preg_match('#^(?:/|\\\\|[a-zA-Z]:[\\\\/])#', $name)) {
        return false;
    }
    return true;
}

function backupEmptyDirectory(string $dir, array $preserve = []): void
{
    if (!is_dir($dir)) {
        return;
    }

    $preserve = array_filter(array_map('realpath', $preserve));

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $path = $item->getPathname();

        $skip = false;
        foreach ($preserve as $keep) {
            if ($path === $keep || strpos($path, $keep . DIRECTORY_SEPARATOR) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }

        $item->isDir() ? @rmdir($path) : @unlink($path);
    }
}

function backupDeleteDirectory(string $dir): void
{
    backupEmptyDirectory($dir);
    @rmdir($dir);
}

/**
 * The effective upload ceiling for the setup restore form, in bytes.
 * The smaller of upload_max_filesize and post_max_size is what actually applies.
 */
function backupMaxUploadBytes(): int
{
    $upload = backupParseIniBytes(ini_get('upload_max_filesize'));
    $post = backupParseIniBytes(ini_get('post_max_size'));

    $limits = array_filter([$upload, $post], fn($v) => $v > 0);

    return empty($limits) ? 0 : min($limits);
}

function backupParseIniBytes($value): int
{
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (int)$value;

    switch ($unit) {
        case 'g':
            return $number * 1024 * 1024 * 1024;
        case 'm':
            return $number * 1024 * 1024;
        case 'k':
            return $number * 1024;
    }

    return $number;
}

function backupFormatBytes($bytes): string
{
    $bytes = (float)$bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}
