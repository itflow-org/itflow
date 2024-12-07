#!/usr/bin/env php
<?php

// Change to the directory of this script so that all shell commands run here
chdir(__DIR__);

// Ensure script is run only from the CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once 'config.php';
require_once "functions.php";

// A function to print the help message so we don't duplicate it
function printHelp() {
    echo "Usage: php update_cli.php [options]\n\n";
    echo "Options:\n";
    echo "  --help          Show this help message.\n";
    echo "  --update        Perform a git pull to update the application.\n";
    echo "  --force_update  Perform a git fetch and hard reset to origin/master.\n";
    echo "  --update_db     Update the database structure to the latest version.\n";
    echo "  --user=USERNAME Run the git commands as USERNAME instead of www-data.\n";
    echo "\nIf no options are provided, a standard update (git pull) is performed.\n";
}

// Define allowed options
$allowed_options = [
    'help',
    'update',
    'force_update',
    'update_db',
    'user'
];

// Parse command-line options, including the optional --user argument
$options = getopt('', ['update', 'force_update', 'update_db', 'help', 'user::']);

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

        // In case there's something like --user=someuser, just consider 'user'
        $optName = preg_replace('/=.*/', '', $optName);

        if (!in_array($optName, $allowed_options)) {
            echo "Error: Unrecognized option: $arg\n\n";
            printHelp();
            exit(1);
        }
    }
}

// Determine the sudo user; default to www-data if none provided
$sudo_user = isset($options['user']) && !empty($options['user']) ? $options['user'] : 'www-data';

// If "help" is requested, show instructions and exit
if (isset($options['help'])) {
    printHelp();
    exit;
}

// If no recognized options (other than help or user) are passed, default to --update
$optionCount = count($options);
if ($optionCount === 0 || ($optionCount === 1 && isset($options['user']))) {
    $options['update'] = true;
}

// If "update" or "force_update" is requested
if (isset($options['update']) || isset($options['force_update'])) {

    // If "force_update" is requested, do a hard reset, otherwise just pull
    if (isset($options['force_update'])) {
        exec("sudo -u $sudo_user git fetch --all 2>&1", $output, $return_var);
        exec("sudo -u $sudo_user git reset --hard origin/master 2>&1", $output2, $return_var2);
        echo implode("\n", $output) . "\n" . implode("\n", $output2) . "\n";
    } else {
        exec("sudo -u $sudo_user git pull 2>&1", $output, $return_var);
        echo implode("\n", $output) . "\n";
    }

    echo "Update successful\n";
}

// If "update_db" is requested
if (isset($options['update_db'])) {
    require_once('database_version.php');

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

    if ($old_db_version !== $new_db_version) {
        echo "Database updated from version $old_db_version to $new_db_version.\n";
        echo "The latest database version is $new_db_version.\n";
    } else {
        echo "Database is already at the latest version ($new_db_version). No updates were applied.\n";
    }
}
