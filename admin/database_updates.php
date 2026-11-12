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
// If undefined, the file is probably being accessed directly rather than called via update_cli.php
if (!defined("LATEST_DATABASE_VERSION") || !defined("CURRENT_DATABASE_VERSION") || !isset($mysqli)) {
    echo "Cannot access this file directly.";
    exit();
}

// Migration files include-guard against this constant
define("FROM_DB_UPDATER", true);

// Outputs for the caller (update_cli.php)
$database_updates_applied = [];   // Versions successfully applied this run
$database_updates_error = null;   // "version: error message" if a migration failed
$database_updates_collations = null;   // ['changed' => [table, ...], 'failed' => [table => why]] when collations were checked

// Every table's collation against db.sql's (functions/database_collation.php), before each
// update and after the last - $database_updates_collations is what that changed and couldn't.
//
// Before each, because an update can compare text in one table with text in another, and two
// tables in different collations stop it with "Illegal mix of collations". The tables that
// 2.1.6 to 2.4.1 created took the server's collation, which on MariaDB 11.5 and later isn't
// the other tables'; 3.2.8 then matched license names against template names across the two,
// failed with its columns already added, and from there every run stopped at "Duplicate
// column name" - and 3.6.5, which puts those tables right, is on the far side of it. With
// the tables brought into line first, no update meets that, whichever version it starts from.
//
// After the last, because one of these updates may be the one that made a table without a
// COLLATE - right on the server it was written on, wrong on the others. Put right here, in
// the run that made it, the slip never reaches a page.
//
// A process that started on code from before these functions (see update_cli.php, on handing
// the database phase to a new process - one that couldn't, reads this file fresh off the new
// tree against the functions it started with) loads them here. Nothing in this can fail the
// update: a table that can't be changed is left as it was, said so, and not tried again.
if (!function_exists('databaseCollationRepair') && is_file(__DIR__ . '/../functions/database_collation.php')) {
    require_once __DIR__ . '/../functions/database_collation.php';
}

$database_updates_collation_pass = function () use (&$database_updates_collations) {
    if (!function_exists('databaseCollationRepair')) {
        return;
    }
    try {
        $pass = databaseCollationRepair(array_keys($database_updates_collations['failed'] ?? []));
        $database_updates_collations = [
            'changed' => array_values(array_unique(array_merge($database_updates_collations['changed'] ?? [], $pass['changed']))),
            'failed' => ($database_updates_collations['failed'] ?? []) + $pass['failed'],
        ];
    } catch (Throwable $e) {
        error_log("ITFlow database update: checking collations failed: " . $e->getMessage());
    }
};

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

    $database_updates_collation_pass();

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

if ($database_updates_applied && !$database_updates_error) {
    $database_updates_collation_pass();
}

if ($database_updates_collations && function_exists('logApp')) {
    try {
        if ($database_updates_collations['changed']) {
            logApp('Database Update', 'info', "Gave " . (count($database_updates_collations['changed']) === 1 ? '1 table' : count($database_updates_collations['changed']) . ' tables') . " the collation db.sql has during the database update: " . implode(', ', $database_updates_collations['changed']));
        }
        foreach ($database_updates_collations['failed'] as $table => $why) {
            logApp('Database Update', 'warning', "The table $table doesn't have the collation db.sql has, and the database update could not give it: $why");
        }
    } catch (Throwable $e) {
        error_log("ITFlow database update: logging collations failed: " . $e->getMessage());
    }
}
