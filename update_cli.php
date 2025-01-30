#!/usr/bin/env php
<?php

// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);

// Change to the directory of this script so that all shell commands run here
chdir(__DIR__);

// Ensure script is run only from the CLI
if (php_sapi_name() !== 'cli') {
    die(lang('cli_error_cli_only') . "\n");
}

// Ensure the script is run by the owner of the file
$fileOwner = fileowner(__FILE__);
$currentUser = posix_geteuid();

if ($currentUser !== $fileOwner) {
    $ownerInfo = posix_getpwuid($fileOwner);
    $ownerName = $ownerInfo['name'] ?? lang('unknown');
    fwrite(STDERR, lang('cli_error_owner', ['owner' => $ownerName]) . "\n");
    exit(1);
}

require_once 'config.php';
require_once "functions.php";

// A function to print the help message so that we don't duplicate it
function printHelp() {
    echo lang('cli_help_usage') . "\n\n";
    echo lang('cli_help_options') . "\n";
    echo "  --help          " . lang('cli_help_help') . "\n";
    echo "  --update        " . lang('cli_help_update') . "\n";
    echo "  --force_update  " . lang('cli_help_force_update') . "\n";
    echo "  --update_db     " . lang('cli_help_update_db') . "\n";
    echo "\n" . lang('cli_help_no_options') . "\n";
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
array_shift($argv_copy);

foreach ($argv_copy as $arg) {
    if (substr($arg, 0, 2) === '--') {
        // Extract the option name (everything after -- and before = if present)
        $eqPos = strpos($arg, '=');
        $optName = ($eqPos !== false) ? substr($arg, 2, $eqPos - 2) : substr($arg, 2);
        
        // Check if option name is allowed
        if (!in_array($optName, $allowed_options)) {
            echo lang('cli_error_invalid_option', ['option' => $arg]) . "\n\n";
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
        if (strpos(implode("\n", $output), lang('cli_update_already_updated')) === false) {
            echo implode("\n", $output) . "\n";
            echo lang('cli_update_success') . "\n";
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
        echo lang('cli_db_updated', [
            'old_version' => $old_db_version,
            'new_version' => $new_db_version
        ]) . "\n";
        echo lang('cli_db_latest_version', ['version' => $latest_db_version]) . "\n";
    } else {
        echo lang('cli_db_already_updated', ['version' => $latest_db_version]) . "\n";
    }
}
