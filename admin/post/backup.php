<?php

/*
 * ITFlow - GET/POST request handler for DB / master key backup
 * Rewritten with streaming SQL dump, component checksums, safer zipping, and better headers.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

require_once "../includes/app_version.php";

// --- Optional performance levers for big backups ---
@set_time_limit(0);
if (function_exists('ini_set')) {
    @ini_set('memory_limit', '1024M');
}

/**
 * Write a line to a file handle with newline.
 */
function fwrite_ln($fh, string $s): void {
    fwrite($fh, $s);
    fwrite($fh, PHP_EOL);
}

/**
 * Stream a SQL dump of schema and data into $sqlFile.
 * - Tables first (DROP + CREATE + INSERTs)
 * - Views (DROP VIEW + CREATE VIEW)
 * - Triggers (DROP TRIGGER + CREATE TRIGGER)
 *
 * NOTE: Routines/events are not dumped here. Add if needed.
 */
function dump_database_streaming(mysqli $mysqli, string $sqlFile): void {
    $fh = fopen($sqlFile, 'wb');
    if (!$fh) {
        http_response_code(500);
        exit("Cannot open dump file");
    }

    // Preamble
    fwrite_ln($fh, "-- UTF-8 + Foreign Key Safe Dump");
    fwrite_ln($fh, "SET NAMES 'utf8mb4';");
    fwrite_ln($fh, "SET FOREIGN_KEY_CHECKS = 0;");
    fwrite_ln($fh, "SET UNIQUE_CHECKS = 0;");
    fwrite_ln($fh, "SET AUTOCOMMIT = 0;");
    fwrite_ln($fh, "");

    // Gather tables and views
    $tables = [];
    $views  = [];

    $res = $mysqli->query("SHOW FULL TABLES");
    if (!$res) {
        fclose($fh);
        error_log("MySQL Error (SHOW FULL TABLES): " . $mysqli->error);
        http_response_code(500);
        exit("Error retrieving tables.");
    }
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
        $name = $row[0];
        $type = strtoupper($row[1] ?? '');
        if ($type === 'VIEW') {
            $views[] = $name;
        } else {
            $tables[] = $name;
        }
    }
    $res->close();

    // --- TABLES: structure and data ---
    foreach ($tables as $table) {
        $createRes = $mysqli->query("SHOW CREATE TABLE `{$mysqli->real_escape_string($table)}`");
        if (!$createRes) {
            error_log("MySQL Error (SHOW CREATE TABLE $table): " . $mysqli->error);
            // continue to next table
            continue;
        }
        $createRow = $createRes->fetch_assoc();
        $createSQL = array_values($createRow)[1] ?? '';
        $createRes->close();

        fwrite_ln($fh, "-- ----------------------------");
        fwrite_ln($fh, "-- Table structure for `{$table}`");
        fwrite_ln($fh, "-- ----------------------------");
        fwrite_ln($fh, "DROP TABLE IF EXISTS `{$table}`;");
        fwrite_ln($fh, $createSQL . ";");
        fwrite_ln($fh, "");

        // Dump data in a streaming fashion
        $dataRes = $mysqli->query("SELECT * FROM `{$mysqli->real_escape_string($table)}`", MYSQLI_USE_RESULT);
        if ($dataRes) {
            $wroteHeader = false;
            while ($row = $dataRes->fetch_assoc()) {
                if (!$wroteHeader) {
                    fwrite_ln($fh, "-- Dumping data for table `{$table}`");
                    $wroteHeader = true;
                }
                $cols = array_map(fn($c) => '`' . $mysqli->real_escape_string($c) . '`', array_keys($row));
                $vals = array_map(
                    function ($v) use ($mysqli) {
                        return is_null($v) ? "NULL" : "'" . $mysqli->real_escape_string($v) . "'";
                    },
                    array_values($row)
                );
                fwrite_ln($fh, "INSERT INTO `{$table}` (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ");");
            }
            $dataRes->close();
            if ($wroteHeader) fwrite_ln($fh, "");
        }
    }

    // --- VIEWS ---
    foreach ($views as $view) {
        $escView = $mysqli->real_escape_string($view);
        $cRes = $mysqli->query("SHOW CREATE VIEW `{$escView}`");
        if ($cRes) {
            $row = $cRes->fetch_assoc();
            $createView = $row['Create View'] ?? '';
            $cRes->close();

            fwrite_ln($fh, "-- ----------------------------");
            fwrite_ln($fh, "-- View structure for `{$view}`");
            fwrite_ln($fh, "-- ----------------------------");
            fwrite_ln($fh, "DROP VIEW IF EXISTS `{$view}`;");
            // Ensure statement ends with semicolon
            if (!str_ends_with($createView, ';')) $createView .= ';';
            fwrite_ln($fh, $createView);
            fwrite_ln($fh, "");
        }
    }

    // --- TRIGGERS ---
    $tRes = $mysqli->query("SHOW TRIGGERS");
    if ($tRes) {
        while ($t = $tRes->fetch_assoc()) {
            $triggerName = $t['Trigger'];
            $escTrig = $mysqli->real_escape_string($triggerName);
            $crt = $mysqli->query("SHOW CREATE TRIGGER `{$escTrig}`");
            if ($crt) {
                $row = $crt->fetch_assoc();
                $createTrig = $row['SQL Original Statement'] ?? ($row['Create Trigger'] ?? '');
                $crt->close();

                fwrite_ln($fh, "-- ----------------------------");
                fwrite_ln($fh, "-- Trigger for `{$triggerName}`");
                fwrite_ln($fh, "-- ----------------------------");
                fwrite_ln($fh, "DROP TRIGGER IF EXISTS `{$triggerName}`;");
                if (!str_ends_with($createTrig, ';')) $createTrig .= ';';
                fwrite_ln($fh, $createTrig);
                fwrite_ln($fh, "");
            }
        }
        $tRes->close();
    }

    // Postamble
    fwrite_ln($fh, "SET FOREIGN_KEY_CHECKS = 1;");
    fwrite_ln($fh, "SET UNIQUE_CHECKS = 1;");
    fwrite_ln($fh, "COMMIT;");

    fclose($fh);
}

/**
 * Zip a folder to $zipFilePath, skipping symlinks and dot-entries.
 */
function zipFolderStrict(string $folderPath, string $zipFilePath): void {
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        error_log("Failed to open zip file: $zipFilePath");
        http_response_code(500);
        exit("Internal Server Error: Cannot open zip archive.");
    }

    $folderReal = realpath($folderPath);
    if (!$folderReal || !is_dir($folderReal)) {
        // Create an empty archive if uploads folder doesn't exist yet
        $zip->close();
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if ($file->isDir()) continue;
        if ($file->isLink()) continue; // skip symlinks
        $filePath = $file->getRealPath();
        if ($filePath === false) continue;

        // ensure path is inside the folder boundary
        if (strpos($filePath, $folderReal . DIRECTORY_SEPARATOR) !== 0 && $filePath !== $folderReal) {
            continue;
        }

        $relativePath = substr($filePath, strlen($folderReal) + 1);
        $zip->addFile($filePath, $relativePath);
    }

    $zip->close();
}

if (isset($_GET['download_backup'])) {

    validateCSRFToken($_GET['csrf_token']);

    $timestamp   = date('YmdHis');
    $baseName    = "itflow_{$timestamp}";
    $downloadName = $baseName . ".zip";

    // === Scoped cleanup of temp files ===
    $cleanupFiles = [];
    $registerTempFileForCleanup = function ($file) use (&$cleanupFiles) {
        $cleanupFiles[] = $file;
    };
    register_shutdown_function(function () use (&$cleanupFiles) {
        foreach ($cleanupFiles as $file) {
            if (is_file($file)) { @unlink($file); }
        }
    });

    // === Create temp files ===
    $sqlFile     = tempnam(sys_get_temp_dir(), $baseName . "_sql_");
    $uploadsZip  = tempnam(sys_get_temp_dir(), $baseName . "_uploads_");
    $versionFile = tempnam(sys_get_temp_dir(), $baseName . "_version_");
    $finalZip    = tempnam(sys_get_temp_dir(), $baseName . "_backup_");

    foreach ([$sqlFile, $uploadsZip, $versionFile, $finalZip] as $f) {
        $registerTempFileForCleanup($f);
        @chmod($f, 0600);
    }

    // === Generate SQL Dump (streaming) ===
    dump_database_streaming($mysqli, $sqlFile);

    // === Zip the uploads folder (strict) ===
    zipFolderStrict("../uploads", $uploadsZip);

    // === Gather metadata & checksums ===
    $commitHash = (function_exists('shell_exec') ? trim(shell_exec('git log -1 --format=%H 2>/dev/null')) : '') ?: 'N/A';
    $gitBranch  = (function_exists('shell_exec') ? trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null')) : '') ?: 'N/A';

    $dbSha = hash_file('sha256', $sqlFile) ?: 'N/A';
    $upSha = hash_file('sha256', $uploadsZip) ?: 'N/A';

    $versionContent  = "ITFlow Backup Metadata\n";
    $versionContent .= "-----------------------------\n";
    $versionContent .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $versionContent .= "Backup File: " . $downloadName . "\n";
    $versionContent .= "Generated By: " . ($session_name ?? 'Unknown User') . "\n";
    $versionContent .= "Host: " . gethostname() . "\n";
    $versionContent .= "Git Branch: $gitBranch\n";
    $versionContent .= "Git Commit: $commitHash\n";
    $versionContent .= "ITFlow Version: " . (defined('APP_VERSION') ? APP_VERSION : 'Unknown') . "\n";
    $versionContent .= "Database Version: " . (defined('CURRENT_DATABASE_VERSION') ? CURRENT_DATABASE_VERSION : 'Unknown') . "\n";
    $versionContent .= "Checksums (SHA256):\n";
    $versionContent .= "  db.sql: $dbSha\n";
    $versionContent .= "  uploads.zip: $upSha\n";

    file_put_contents($versionFile, $versionContent);
    @chmod($versionFile, 0600);

    // === Build final ZIP ===
    $final = new ZipArchive();
    if ($final->open($finalZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        error_log("Failed to create final zip: $finalZip");
        http_response_code(500);
        exit("Internal Server Error: Unable to create backup archive.");
    }
    $final->addFile($sqlFile, "db.sql");
    $final->addFile($uploadsZip, "uploads.zip");
    $final->addFile($versionFile, "version.txt");
    $final->close();

    @chmod($finalZip, 0600);

    // === Serve final ZIP with a stable filename ===
    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($finalZip));
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Transfer-Encoding: binary');

    // Push file
    flush();
    $fp = fopen($finalZip, 'rb');
    fpassthru($fp);
    fclose($fp);

    // Log + UX
    logAction("System", "Backup Download", ($session_name ?? 'Unknown User') . " downloaded full backup.");
    flash_alert("Full backup downloaded.");
    exit;
}

if (isset($_POST['backup_master_key'])) {

    validateCSRFToken($_POST['csrf_token']);

    $password = $_POST['password'];

    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $session_user_id");
    $row = mysqli_fetch_array($sql);

    if (password_verify($password, $row['user_password'])) {
        $site_encryption_master_key = decryptUserSpecificKey($row['user_specific_encryption_ciphertext'], $password);
        
        logAction("Master Key", "Download", "$session_name retrieved the master encryption key");

        appNotify("Master Key", "$session_name retrieved the master encryption key");

        echo "==============================";
        echo "<br>Master encryption key:<br>";
        echo "<b>$site_encryption_master_key</b>";
        echo "<br>==============================";
    
    } else {
        logAction("Master Key", "Download", "$session_name attempted to retrieve the master encryption key but failed");

        flash_alert("Incorrect password.", 'error');
        
        redirect();
    }
}

