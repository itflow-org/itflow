#!/usr/bin/env php
<?php

// Change to the directory of this script so that all shell commands run here
chdir(__DIR__);

// Ensure script is run only from the CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Ensure the script is run by the owner of the file
$fileOwner = fileowner(__FILE__);
$currentUser = posix_geteuid(); // Get the current effective user ID

if ($currentUser !== $fileOwner) {
    $ownerInfo = posix_getpwuid($fileOwner);
    $ownerName = $ownerInfo['name'] ?? 'unknown';
    fwrite(STDERR, "Error: This script must be run by the file owner ($ownerName) to proceed.\nYou could try sudo -u $ownerName php update_cli.php\n");
    exit(1);
}

require_once "../config.php";
require_once "../functions.php";

/*
 * This script takes no options. It updates the application and then the database, in that
 * order, because new code against an old schema is what breaks an install mid-upgrade.
 *
 * The application update is a hard reset onto the branch this install tracks, so local
 * modifications to tracked files are discarded. Untracked files are left alone, which is
 * everything an install keeps for itself: config.php, uploads/ and the custom/ directories.
 */

function printUsage($stream = STDOUT) {
    fwrite($stream, "Usage: php update_cli.php\n\n");
    fwrite($stream, "Updates the application to the latest code on the branch this install tracks,\n");
    fwrite($stream, "discarding local changes to tracked files, and then applies any outstanding\n");
    fwrite($stream, "database updates. There are no options.\n");
}

/*
 * Set on the child process this script starts for the database phase - see the comment
 * further down. It is not part of the interface, and it is what stops that child from
 * starting a child of its own.
 */
$database_phase_only = getenv('ITFLOW_UPDATE_PHASE') === 'database';

/*
 * The switches this script used to take are gone. Anything on the command line is refused
 * rather than ignored, so that an old --update_db call from a script or a set of notes
 * cannot silently trigger a hard reset of the application instead.
 */
$arguments = array_slice($argv, 1);

if (!$database_phase_only && count($arguments) > 0) {

    if (in_array($arguments[0], ['--help', '-h', 'help'], true)) {
        printUsage();
        exit;
    }

    fwrite(STDERR, "Error: this script takes no options.\n\n");
    printUsage(STDERR);
    exit(1);
}

// Whether the working tree actually moved. Decides how the database phase runs below
$app_updated = false;

if (!$database_phase_only) {

    /*
     * An install that was dropped in as a zip has no .git and cannot be updated this way,
     * but its database still can, so the application phase is skipped rather than fatal.
     * Checked on the filesystem rather than by asking git, so that a missing git binary
     * still reports as the failure it is instead of looking like "not a checkout".
     */
    if (!file_exists(dirname(__DIR__) . "/.git")) {

        echo "This install is not a git checkout - skipping the application update.\n";

    } else {

        $update_branch = getRepoBranch();
        $remote_ref = escapeshellarg("origin/" . $update_branch);

        // Recorded either side of the update so that "did anything change" is a commit
        // comparison rather than a match on git's wording, which varies by version and locale
        $head_before = trim((string) exec("git rev-parse HEAD 2>/dev/null"));

        // A hard reset throws local modifications away without ever saying what they were
        $local_changes = [];
        exec("git status --porcelain --untracked-files=no 2>/dev/null", $local_changes);

        if (count($local_changes) > 0) {
            echo "Discarding local changes to " . count($local_changes) . " tracked file(s):\n";
            echo "  " . implode("\n  ", $local_changes) . "\n";
        }

        echo "Updating the application to origin/$update_branch\n";

        // A hard reset onto the tracked branch. This named origin/master outright until
        // 26.08, which moved any install tracking another branch onto master.
        $output = [];
        exec("git fetch --all 2>&1", $output, $return_var);

        if ($return_var === 0) {
            exec("git reset --hard $remote_ref 2>&1", $output, $return_var);
        }

        echo implode("\n", $output) . "\n";

        // git reports a missing remote, a bad ref and network failures through its exit
        // status. Without this the script printed the error, said the update succeeded and
        // exited 0 - and would now go on to update the database as if all was well
        if ($return_var !== 0) {
            fwrite(STDERR, "Error: the application update failed - see the output above.\n");
            fwrite(STDERR, "The database has been left alone. Fix the problem and run this script again.\n");
            exit(1);
        }

        $head_after = trim((string) exec("git rev-parse HEAD 2>/dev/null"));
        $app_updated = $head_before !== '' && $head_after !== '' && $head_before !== $head_after;

        echo $app_updated ? "Update successful\n" : "The application was already up to date.\n";
    }
}

/*
 * If the application was just updated, this process is still running the code it started
 * with - config.php, functions.php and everything functions.php loaded are the pre-update
 * copies - while the migration runner and the migration files themselves get read fresh off
 * the new tree. A migration calling a helper added in the same release then dies with
 * "Call to undefined function".
 *
 * So hand the database phase to a new process, which loads the new code from the start. Its
 * output and its exit status are this script's. A process that did not update anything is
 * already running the code on disk and needs none of this.
 */
if ($app_updated && PHP_BINARY !== '' && function_exists('passthru')) {
    echo "Running the database update against the updated code\n";
    putenv("ITFLOW_UPDATE_PHASE=database");
    passthru(escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__FILE__), $db_return_var);
    exit($db_return_var);
}

require_once "../includes/database_version.php";

$latest_db_version = LATEST_DATABASE_VERSION;

// Fetch the current version from the database
$result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings LIMIT 1");
$row = $result ? mysqli_fetch_assoc($result) : null;
$old_db_version = trim((string) ($row['config_current_database_version'] ?? ''));

// No version to work from means no way to tell which migrations have already run, and
// starting from the first one against a partly built database breaks it
if ($old_db_version === '') {
    fwrite(STDERR, "Error: could not read the current database version from the settings table.\n");
    fwrite(STDERR, "This install looks incomplete - finish it at /setup before updating the database.\n");
    exit(1);
}

DEFINE("CURRENT_DATABASE_VERSION", $old_db_version);

// Run the migrations - populates $database_updates_applied and $database_updates_error
require_once "../admin/database_updates.php";

foreach ($database_updates_applied as $applied_version) {
    echo "Applied database update $applied_version\n";
}

if ($database_updates_error) {
    $stopped_at_version = count($database_updates_applied) > 0 ? end($database_updates_applied) : $old_db_version;
    fwrite(STDERR, "Error: database update failed at $database_updates_error\n");
    fwrite(STDERR, "The database is at version $stopped_at_version - re-running will resume at the failed update.\n");
    exit(1);
}

if (count($database_updates_applied) > 0) {
    echo "Database updated from version $old_db_version to $latest_db_version.\n";
} else {
    echo "Database is already at the latest version ($latest_db_version). No updates were applied.\n";
}
