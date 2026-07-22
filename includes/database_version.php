<?php
/*
 * ITFlow
 * This file defines the current "latest" database version
 * It is used in conjunction with database_updates.php
 *
 * The latest version is derived from the migration files in
 * admin/database_updates/ - the highest-versioned filename wins.
 * Adding a migration file automatically raises the latest version.
 */

$database_latest_version = "0.0.0";

foreach (glob(dirname(__DIR__) . "/admin/database_updates/*.php") as $database_version_file) {
    $database_file_version = basename($database_version_file, ".php");
    if (preg_match('/^\d+(\.\d+)+$/', $database_file_version)
        && version_compare($database_file_version, $database_latest_version, ">")
    ) {
        $database_latest_version = $database_file_version;
    }
}

if ($database_latest_version == "0.0.0") {
    exit("Error: admin/database_updates/ is missing or empty - the install may be incomplete.");
}

DEFINE("LATEST_DATABASE_VERSION", $database_latest_version);
