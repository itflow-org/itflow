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

// A function to print the help message so that we don't duplicate it
function printHelp() {
    echo "Usage: php update_cli.php [options]\n\n";
    echo "Options:\n";
    echo "  --help          Show this help message.\n";
    echo "  --update        Update the application only (git pull).\n";
    echo "  --force_update  Update the application only, discarding local changes: git fetch\n";
    echo "                  and hard reset to the branch this install tracks.\n";
    echo "  --update_db     Update the database structure only.\n";
    echo "\nWith no options a full update is performed - the application first, then the\n";
    echo "database. That is the same as running --update --update_db.\n";
}

// Define allowed options (removed 'user')
$allowed_options = [
    'help',
    'update',
    'force_update',
    'update_db'
];

// Parse command-line options
$options = getopt('', ['update', 'force_update', 'update_db', 'help']);

// Check for invalid options by comparing argv against allowed options
$argv_copy = $argv;
array_shift($argv_copy); // Remove script name

foreach ($argv_copy as $arg) {
    if (substr($arg, 0, 2) === '--') {
        // Extract the option name (everything after -- and before = if present)
        $eqPos = strpos($arg, '=');
        if ($eqPos !== false) {
            $optName = substr($arg, 2, $eqPos - 2);
        } else {
            $optName = substr($arg, 2);
        }

        // Check if option name is allowed
        if (!in_array($optName, $allowed_options)) {
            echo "Error: Unrecognized option: $arg\n\n";
            printHelp();
            exit(1);
        }
    }
}

// If "help" is requested, show instructions and exit
if (isset($options['help'])) {
    printHelp();
    exit;
}

// If no recognized options are passed, update everything - the application and then the
// database. The switches stay surgical for anyone who wants one or the other, which is
// what --update_db is for after updating the files from the web UI.
if (count($options) === 0) {
    $options['update'] = true;
    $options['update_db'] = true;
}

// Whether the working tree actually moved. Decides how the database phase runs below
$app_updated = false;

// If "update" or "force_update" is requested
if (isset($options['update']) || isset($options['force_update'])) {

    // Recorded either side of the update so that "did anything change" is a commit
    // comparison rather than a match on git's wording, which varies by version and locale
    $head_before = trim((string) exec("git rev-parse HEAD 2>/dev/null"));

    $output = [];

    if (isset($options['force_update'])) {
        // Perform a hard reset onto the tracked branch. This named origin/master outright
        // until 26.08, which moved any install tracking another branch onto master.
        $remote_ref = escapeshellarg("origin/" . getRepoBranch());
        exec("git fetch --all 2>&1", $output, $return_var);
        if ($return_var === 0) {
            exec("git reset --hard $remote_ref 2>&1", $output, $return_var);
        }
    } else {
        // Perform a standard update (git pull)
        exec("git pull 2>&1", $output, $return_var);
    }

    echo implode("\n", $output) . "\n";

    // git reports merge conflicts, a dirty working tree and network failures through its
    // exit status. Without this the script printed the error, said "Update successful"
    // and exited 0 - and would now go on to update the database as if all was well
    if ($return_var !== 0) {
        fwrite(STDERR, "Error: the application update failed - see the output above.\n");
        if (isset($options['update_db'])) {
            fwrite(STDERR, "The database has been left alone. Fix the problem and run this script again, or run\nphp update_cli.php --update_db on its own to update only the database.\n");
        }
        exit(1);
    }

    $head_after = trim((string) exec("git rev-parse HEAD 2>/dev/null"));
    $app_updated = $head_before !== '' && $head_after !== '' && $head_before !== $head_after;

    if ($app_updated) {
        echo "Update successful\n";
    }
}

// If "update_db" is requested
if (isset($options['update_db'])) {

    /*
     * If the application was just updated, this process is still running the code it
     * started with - config.php, functions.php and everything functions.php loaded are
     * the pre-update copies - while the migration runner and the migration files
     * themselves get read fresh off the new tree. A migration calling a helper added in
     * the same release then dies with "Call to undefined function".
     *
     * So hand the database phase to a new process, which loads the new code from the
     * start. Its output and its exit status are this script's. A process that did not
     * update anything is already running the code on disk and needs none of this.
     */
    if ($app_updated && PHP_BINARY !== '' && function_exists('passthru')) {
        echo "Running the database update against the updated code\n";
        passthru(escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__FILE__) . " --update_db", $db_return_var);
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
}
