<?php

/*
 * ITFlow - Command line restore
 *
 * Replaces the database (and the uploads folder, for a full backup) with the contents of
 * an encrypted ITFlow backup archive.
 *
 * This is the only restore path with no size limit. The setup wizard's restore has to
 * receive the archive through a browser upload, which PHP caps at upload_max_filesize /
 * post_max_size - a real full backup is usually larger than both.
 *
 * Usage:
 *   php scripts/restore_cli.php --file=/path/to/itflow_20260731-020000_full_XXXX.zip
 *
 * Options:
 *   --file=PATH     The backup archive. Required.
 *   --key=KEY       Encryption key. Defaults to $config_backup_key from config.php.
 *   --inspect       Show what is in the archive and exit without changing anything.
 *   --yes           Skip the confirmation prompt (for unattended use).
 */

chdir(dirname(__FILE__));

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

if (!file_exists("../config.php")) {
    fwrite(STDERR, "config.php not found.\n\n");
    fwrite(STDERR, "A restore needs a working database connection, so ITFlow has to be installed first.\n");
    fwrite(STDERR, "Run the setup wizard (or scripts/setup_cli.php) to create config.php, then run this again.\n");
    exit(1);
}

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$options = getopt("", ["file:", "key:", "inspect", "yes", "help"]);

if (isset($options['help']) || empty($options['file'])) {
    echo "Usage: php scripts/restore_cli.php --file=/path/to/backup.zip [--key=KEY] [--inspect] [--yes]\n\n";
    echo "  --file=PATH   The backup archive to restore. Required.\n";
    echo "  --key=KEY     Encryption key. Defaults to \$config_backup_key from config.php.\n";
    echo "  --inspect     Show what is in the archive and exit without changing anything.\n";
    echo "  --yes         Skip the confirmation prompt.\n";
    exit(isset($options['help']) ? 0 : 1);
}

$file = $options['file'];

if (!is_file($file)) {
    fwrite(STDERR, "Backup file not found: $file\n");
    exit(1);
}

// The key from config.php is the right one for a backup made by this install. A backup
// carried over from another server needs that server's key passed in.
$key = $options['key'] ?? ($config_backup_key ?? '');

if ($key === '') {
    fwrite(STDERR, "No encryption key.\n\n");
    fwrite(STDERR, "config.php has no \$config_backup_key, so pass the key from the install that made\n");
    fwrite(STDERR, "this backup with --key=... It is shown in Maintenance > Backup on that install.\n");
    exit(1);
}

$error = null;
$meta = backupInspectArchive($file, $key, $error);

if ($meta === false) {
    fwrite(STDERR, "Cannot read this backup: $error\n");
    exit(1);
}

echo "Backup archive:  " . basename($file) . "\n";
echo "Size:            " . backupFormatBytes(filesize($file)) . "\n";
echo "Type:            " . backupTypeLabel($meta['type']) . "\n";
echo "Taken:           " . $meta['generated'] . "\n";
echo "ITFlow version:  " . $meta['app_version'] . "\n";
echo "Database version:" . " " . $meta['database_version'] . "\n";
echo "Contains:        " . implode(", ", $meta['entries']) . "\n";

$current_db_version = backupCurrentDatabaseVersion($mysqli);
echo "This install is at database version $current_db_version.\n";

if (isset($options['inspect'])) {
    echo "\n--inspect given, nothing was changed.\n";
    exit(0);
}

if ($meta['database_version'] !== 'Unknown' && version_compare($meta['database_version'], $current_db_version, '>')) {
    echo "\nWARNING: this backup is from a NEWER database version than the code in this directory.\n";
    echo "Update ITFlow to a matching version before restoring, or the app will error after the restore.\n";
}

echo "\n";
echo "This will REPLACE the database";
if (in_array('uploads.zip', $meta['entries'], true)) {
    echo " and everything in the uploads folder";
}
echo ".\n";
echo "The current database is dumped first and put back automatically if the restore fails.\n";

if (!isset($options['yes'])) {
    echo "\nType 'restore' to continue: ";
    $answer = trim(fgets(STDIN) ?: '');
    if ($answer !== 'restore') {
        echo "Aborted, nothing was changed.\n";
        exit(1);
    }
}

echo "\n";

$restore_error = null;
$ok = backupRestoreArchive($mysqli, $file, $key, $restore_error, function ($message) {
    echo "  $message\n";
});

if (!$ok) {
    fwrite(STDERR, "\nRestore failed: $restore_error\n");
    exit(1);
}

logAudit("Backup", "Restore", "Restored from " . escapeSql(basename($file)) . " via the command line");

echo "\nDone.\n";
echo "\nNext steps:\n";
echo "  1. Log in with the credentials that were in use when the backup was taken.\n";
echo "  2. If the backup is older than the code in this directory, finish the database update:\n";
echo "     php " . realpath(__DIR__) . "/update_cli.php --update_db\n";
echo "  3. Check Maintenance > Cron - the schedule came back with the database.\n";

exit(0);
