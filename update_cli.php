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

require_once 'config.php';
require_once "functions.php";

// A function to print the help message so that we don't duplicate it
function printHelp() {
    echo "Usage: php update_cli.php [options]\n\n";
    echo "Options:\n";
    echo "  --help          Show this help message.\n";
    echo "  --update        Perform a git pull to update the application.\n";
    echo "  --force_update  Perform a git fetch and hard reset to origin/master.\n";
    echo "  --update_db     Update the database structure to the latest version.\n";
    echo "\nIf no options are provided, a standard update (git pull) is performed.\n";
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

// If no recognized options are passed, default to --update
if (count($options) === 0) {
    $options['update'] = true;
}

// If "update" or "force_update" is requested
if (isset($options['update']) || isset($options['force_update'])) {
    if (isset($options['force_update'])) {
        // Perform a hard reset
        exec("git fetch --all 2>&1", $output, $return_var);
        exec("git reset --hard origin/master 2>&1", $output2, $return_var2);
        echo implode("\n", $output) . "\n" . implode("\n", $output2) . "\n";
    } else {
        // Perform a standard update (git pull)
        exec("git pull 2>&1", $output, $return_var);
        
        // Check if the repository is already up to date
        if (strpos(implode("\n", $output), 'Already up to date.') === false) {
            echo implode("\n", $output) . "\n";
            echo "Update successful\n";
        } else {
            // If already up-to-date, don't show the update success message
            echo implode("\n", $output) . "\n";
        }
    }
}

// If "update_db" is requested
if (isset($options['update_db'])) {
    require_once('database_version.php');

    $latest_db_version = LATEST_DATABASE_VERSION;

    // Fetch the current version from the database
    $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings LIMIT 1");
    $row = mysqli_fetch_assoc($result);
    DEFINE("CURRENT_DATABASE_VERSION", $row['config_current_database_version']);
    $old_db_version = $row['config_current_database_version'];

    // Now include the update logic
    require_once('database_updates.php');

    // After database_updates.php has done its job, fetch the updated current DB version again
    $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings LIMIT 1");
    $row = mysqli_fetch_assoc($result);
    $new_db_version = $row['config_current_database_version'];

    if ($old_db_version !== $latest_db_version) {
        echo "Database updated from version $old_db_version to $new_db_version.\n";
        echo "The latest database version is $latest_db_version.\n";
    } else {
        echo "Database is already at the latest version ($latest_db_version). No updates were applied.\n";
    }
}
