#!/usr/bin/env php
<?php

// Ensure script is run only from the CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once 'config.php';
require_once "functions.php";

// Parse command-line options
$options = getopt('', ['update', 'force_update', 'update_db', 'help']);

// If "help" is requested, show instructions and exit
if (isset($options['help'])) {
    echo "Usage: php scriptname.php [options]\n\n";
    echo "Options:\n";
    echo "  --help          Show this help message.\n";
    echo "  --update        Perform a git pull to update the application.\n";
    echo "  --force_update  Perform a git fetch and hard reset to origin/master.\n";
    echo "  --update_db     Update the database structure to the latest version.\n";
    echo "\nIf no options are provided, a standard update (git pull) is performed.\n";
    exit;
}

// If no recognized options (other than help) are passed, default to --update
if (count($options) === 0) {
    $options['update'] = true;
}

// If "update" is requested
if (isset($options['update']) || isset($options['force_update'])) {

    // If "force_update" is requested, do a hard reset, otherwise just pull
    if (isset($options['force_update'])) {
        exec("sudo -u www-data git fetch --all 2>&1", $output, $return_var);
        exec("sudo -u www-data git reset --hard origin/master 2>&1", $output2, $return_var2);
        echo implode("\n", $output) . "\n" . implode("\n", $output2) . "\n";
    } else {
        exec("sudo -u www-data git pull 2>&1", $output, $return_var);
        echo implode("\n", $output) . "\n";
    }

    echo "Update successful\n";
}

// If "update_db" is requested
if (isset($options['update_db'])) {
    // Load the latest version constant
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
