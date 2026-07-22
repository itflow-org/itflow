<?php

/*
 * ITFlow
 * Applies pending database migrations from admin/database_updates/
 * Used in conjunction with database_version.php
 *
 * Each file in admin/database_updates/ is named for the database version it
 * upgrades TO (e.g. 2.4.5.php contains the queries that bring 2.4.4 to 2.4.5).
 * This runner applies every file newer than the current database version, in
 * order, and bumps config_current_database_version after each one - so a
 * single run brings the database all the way to the latest version.
 *
 * To add a migration: create admin/database_updates/<new version>.php - that
 * is the whole job. The latest version is derived from the directory listing
 * (see includes/database_version.php) and this runner handles the version
 * bump, so there is no constant to update and no bump query to remember.
 */

// Check if our database versions are defined
// If undefined, the file is probably being accessed directly rather than called via post.php?update_db or update_cli.php
if (!defined("LATEST_DATABASE_VERSION") || !defined("CURRENT_DATABASE_VERSION") || !isset($mysqli)) {
    echo "Cannot access this file directly.";
    exit();
}

// Migration files include-guard against this constant
define("FROM_DB_UPDATER", true);

// Outputs for the caller (post/update.php, update_cli.php)
$database_updates_applied = [];   // Versions successfully applied this run
$database_updates_error = null;   // "version: error message" if a migration failed

// Collect and order the migration files by version (glob sorts alphabetically,
// which would put 2.4.10 before 2.4.9 - version_compare gets it right)
$database_update_files = [];
foreach (glob(__DIR__ . "/database_updates/*.php") as $file) {
    $version = basename($file, ".php");
    if (preg_match('/^\d+(\.\d+)+$/', $version)) {
        $database_update_files[$version] = $file;
    }
}
uksort($database_update_files, "version_compare");

// Apply everything newer than the current database version, in order
// (CURRENT_DATABASE_VERSION is a constant frozen at page load, so track progress in a variable)
$database_current_version = CURRENT_DATABASE_VERSION;

foreach ($database_update_files as $version => $file) {

    if (version_compare($version, $database_current_version, "<=")) {
        continue;
    }

    try {
        require $file;
    } catch (Throwable $e) {
        // Stop here - config_current_database_version still points at the last
        // completed migration, so a re-run resumes at this file
        $database_updates_error = "$version: " . $e->getMessage();
        error_log("ITFlow database update to version $version failed: " . $e->getMessage());
        break;
    }

    // Migration succeeded - bump the database version
    mysqli_query($mysqli, "UPDATE `settings` SET `config_current_database_version` = '" . escapeSql($version) . "'");
    $database_current_version = $version;
    $database_updates_applied[] = $version;
}
