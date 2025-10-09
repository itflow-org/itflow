<?php
// --- Helpers for restore ---

/** Delete a directory recursively (safe, no symlinks followed). */
function deleteDir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) { @rmdir($item->getPathname()); }
        else { @unlink($item->getPathname()); }
    }
    @rmdir($dir);
}

/** Import a SQL file via mysqli, supporting custom DELIMITER and multi statements. */
function importSqlFile(mysqli $mysqli, string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("SQL file not found or unreadable: $path");
    }
    $fh = fopen($path, 'r');
    if (!$fh) throw new RuntimeException("Failed to open SQL file");

    $delimiter = ';';
    $statement = '';

    while (($line = fgets($fh)) !== false) {
        $trim = trim($line);

        // skip comments and empty lines
        if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
            continue;
        }

        // handle DELIMITER changes
        if (preg_match('/^DELIMITER\s+(.+)$/i', $trim, $m)) {
            $delimiter = $m[1];
            continue;
        }

        $statement .= $line;

        // end of statement?
        if (substr(rtrim($statement), -strlen($delimiter)) === $delimiter) {
            $sql = substr($statement, 0, -strlen($delimiter));
            if ($mysqli->multi_query($sql) === false) {
                fclose($fh);
                throw new RuntimeException("SQL error: ".$mysqli->error);
            }
            // flush any result sets
            while ($mysqli->more_results() && $mysqli->next_result()) { /* discard */ }
            $statement = '';
        }
    }
    fclose($fh);
}

/** Extract a zip safely to $destDir. Blocks absolute paths, drive letters, and symlinks. */
function safeExtractZip(ZipArchive $zip, string $destDir): void {
    $rootReal = realpath($destDir);
    if ($rootReal === false) {
        if (!mkdir($destDir, 0700, true)) {
            throw new RuntimeException("Failed to create temp dir");
        }
        $rootReal = realpath($destDir);
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        // Reject absolute or drive-lettered paths
        if (preg_match('#^(?:/|\\\\|[a-zA-Z]:[\\\\/])#', $name)) {
            throw new RuntimeException("Invalid absolute path in zip: $name");
        }

        // Normalize components and skip traversal attempts
        $target = $rootReal . DIRECTORY_SEPARATOR . $name;
        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true)) {
            throw new RuntimeException("Failed to create $targetDir");
        }

        // Directories end with '/'
        $isDir = str_ends_with($name, '/');

        // Read entry
        $fp = $zip->getStream($name);
        if ($fp === false) {
            if ($isDir) continue;
            throw new RuntimeException("Failed to read $name from zip");
        }

        if ($isDir) {
            if (!is_dir($target) && !mkdir($target, 0700, true)) {
                fclose($fp);
                throw new RuntimeException("Failed to mkdir $target");
            }
            fclose($fp);
        } else {
            $out = fopen($target, 'wb');
            if (!$out) { fclose($fp); throw new RuntimeException("Failed to create $target"); }
            stream_copy_to_stream($fp, $out);
            fclose($fp);
            fclose($out);

            // Final boundary check
            $real = realpath($target);
            if ($real === false || str_starts_with($real, $rootReal) === false) {
                @unlink($target);
                throw new RuntimeException("Path traversal detected for $name");
            }

            // Disallow symlinks (in case zip contained one)
            if (is_link($real)) {
                @unlink($real);
                throw new RuntimeException("Symlink detected in archive: $name");
            }
        }
    }
}

/** Idempotently set/append a PHP config flag like $config_enable_setup = 0; */
function setConfigFlag(string $file, string $key, $value): void {
    $cfg = @file_get_contents($file);
    if ($cfg === false) throw new RuntimeException("Cannot read $file");
    $pattern = '/^\s*\$'.preg_quote($key, '/').'\s*=\s*.*?;\s*$/m';
    $line = '$'.$key.' = '.(is_bool($value)? ($value?'true':'false') : var_export($value,true)).";\n";
    if (preg_match($pattern, $cfg)) {
        $cfg = preg_replace($pattern, $line, $cfg);
    } else {
        $cfg .= "\n".$line;
    }
    if (file_put_contents($file, $cfg, LOCK_EX) === false) {
        throw new RuntimeException("Failed to update $file");
    }
}

/**
 * Return true if a filename has a disallowed extension or looks like a double-extension trick.
 */
function hasDangerousExtension(string $name, array $blockedExts): bool {
    $lower = strtolower($name);
    // Quick reject on hidden PHP or dotfiles that may alter server behavior
    if (preg_match('/(^|\/)\.(htaccess|user\.ini|env)$/i', $lower)) return true;

    // Pull last extension
    $ext = strtolower(pathinfo($lower, PATHINFO_EXTENSION));
    if (in_array($ext, $blockedExts, true)) return true;

    // Double extension (e.g., .jpg.php, .png.sh)
    if (preg_match('/\.(?:[a-z0-9]{1,5})\.(php[0-9]?|phtml|phar|cgi|pl|sh|exe|dll|bat|cmd|com|ps1|vb|vbs|jar|jsp|asp|aspx)$/i', $lower)) {
        return true;
    }

    return false;
}

/**
 * Heuristic content scan for executable code. Reads head/tail of file.
 */
function contentLooksExecutable(string $tmpPath): bool {
    // Use finfo to detect executable/script mimetypes
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($tmpPath) ?: '';

    // Quick MIME-based blocks (don’t rely solely on this)
    if (preg_match('#^(application/x-(php|elf|sharedlib|mach-o)|text/x-(php|script|shell))#i', $mime)) {
        return true;
    }

    // Read first/last 4KB for signature checks without loading whole file
    $fp = @fopen($tmpPath, 'rb');
    if (!$fp) return false;

    $head = fread($fp, 4096) ?: '';
    // Seek last 4KB if file >4KB
    $tail = '';
    $stat = fstat($fp);
    if ($stat && $stat['size'] > 4096) {
        fseek($fp, -4096, SEEK_END);
        $tail = fread($fp, 4096) ?: '';
    }
    fclose($fp);

    $blob = $head . $tail;

    // Block common code markers / execution hints
    $markers = [
        '<?php', '<?=',
        '#!/usr/bin/env php', '#!/usr/bin/php',
        '#!/bin/bash', '#!/bin/sh', '#!/usr/bin/env bash',
        'eval(', 'assert(', 'base64_decode(',
        'shell_exec(', 'proc_open(', 'popen(', 'system(', 'passthru(',
    ];
    foreach ($markers as $m) {
        if (stripos($blob, $m) !== false) return true;
    }

    return false;
}

/**
 * Extract uploads.zip to $destDir with validation & reporting.
 * - Validates each entry (boundary, extension, MIME/signatures, size)
 * - Collects all issues instead of failing fast
 * - If any issues are found, NOTHING is extracted and an array of problems is returned
 *
 * @return array{ok: bool, issues?: array<int, array{path: string, reason: string}>}
 */
function extractUploadsZipWithValidationReport(ZipArchive $zip, string $destDir, array $options = []): array {
    $maxFileBytes   = $options['max_file_bytes']   ?? (200 * 1024 * 1024); // 200MB
    $blockedExts    = $options['blocked_exts']     ?? [
        'php','php3','php4','php5','php7','php8','phtml','phar',
        'cgi','pl','sh','bash','zsh','exe','dll','bat','cmd','com',
        'ps1','vbs','vb','jar','jsp','asp','aspx','so','dylib','bin'
    ];

    $issues   = [];
    $staging  = $destDir; // caller gives us a staging dir (empty)
    $rootReal = realpath($staging);
    if ($rootReal === false) {
        if (!mkdir($staging, 0700, true)) {
            return ['ok' => false, 'issues' => [['path' => '(staging)', 'reason' => 'Failed to create staging directory']]];
        }
        $rootReal = realpath($staging);
    }

    // First pass: validate all entries and write candidates to temp files only
    // We keep a map of tmp files to final target paths; if any issue is found, we clean them and return
    $pending = []; // [ [ 'tmp' => '/tmp/..', 'target' => '/final/path', 'name' => 'zip/path' ], ... ]

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        // Reject absolute/drive-lettered paths
        if (preg_match('#^(?:/|\\\\|[a-zA-Z]:[\\\\/])#', $name)) {
            $issues[] = ['path' => $name, 'reason' => 'Invalid absolute or drive path'];
            continue;
        }

        $isDir = str_ends_with($name, '/');
        if ($isDir) {
            // We’ll create directories in the commit phase if no issues were found
            continue;
        }

        $stream = $zip->getStream($name);
        if ($stream === false) {
            $issues[] = ['path' => $name, 'reason' => 'Unable to read entry from ZIP'];
            continue;
        }

        // 1) Extension and double-extension checks
        if (hasDangerousExtension($name, $blockedExts)) {
            fclose($stream);
            $issues[] = ['path' => $name, 'reason' => 'Dangerous or disallowed file extension'];
            continue;
        }

        // 2) Stream into a temp file (size-capped) for MIME/signature checks
        $tmp = tempnam(sys_get_temp_dir(), 'uplscan_');
        if ($tmp === false) { fclose($stream); $issues[] = ['path' => $name, 'reason' => 'Failed to create temp file']; continue; }

        $out   = fopen($tmp, 'wb');
        if (!$out) { fclose($stream); @unlink($tmp); $issues[] = ['path' => $name, 'reason' => 'Failed to write temp file']; continue; }

        $bytes = 0;
        $err   = null;
        while (!feof($stream)) {
            $chunk = fread($stream, 1 << 15);
            if ($chunk === false) { $err = 'Read error while extracting'; break; }
            $bytes += strlen($chunk);
            if ($bytes > $maxFileBytes) { $err = 'File exceeds per-file size limit'; break; }
            if (fwrite($out, $chunk) === false) { $err = 'Write error while buffering'; break; }
        }
        fclose($stream);
        fclose($out);

        if ($err !== null) {
            @unlink($tmp);
            $issues[] = ['path' => $name, 'reason' => $err];
            continue;
        }

        // 3) MIME + signature checks
        if (contentLooksExecutable($tmp)) {
            @unlink($tmp);
            $issues[] = ['path' => $name, 'reason' => 'Executable/script content detected'];
            continue;
        }

        // Record as candidate for commit
        $target = $rootReal . DIRECTORY_SEPARATOR . $name;
        $pending[] = ['tmp' => $tmp, 'target' => $target, 'name' => $name];
    }

    // If any issues, cleanup temps and return report
    if (!empty($issues)) {
        foreach ($pending as $p) { @unlink($p['tmp']); }
        return ['ok' => false, 'issues' => $issues];
    }

    // Commit phase: create directories and move files into place
    foreach ($pending as $p) {
        $finalDir = dirname($p['target']);
        if (!is_dir($finalDir) && !mkdir($finalDir, 0700, true)) {
            // Rollback partially moved files
            foreach ($pending as $r) { @unlink($r['tmp']); }
            return ['ok' => false, 'issues' => [['path' => $p['name'], 'reason' => 'Failed to create destination directory']]];
        }

        // Boundary check again
        $realFinalDir = realpath($finalDir);
        if ($realFinalDir === false || strpos($realFinalDir, $rootReal) !== 0) {
            foreach ($pending as $r) { @unlink($r['tmp']); }
            return ['ok' => false, 'issues' => [['path' => $p['name'], 'reason' => 'Path traversal detected at commit phase']]];
        }

        if (!rename($p['tmp'], $p['target'])) {
            if (!copy($p['tmp'], $p['target'])) {
                @unlink($p['tmp']);
                // Cleanup remaining temps
                foreach ($pending as $r) { @unlink($r['tmp']); }
                return ['ok' => false, 'issues' => [['path' => $p['name'], 'reason' => 'Failed to place file in destination']]];
            }
            @unlink($p['tmp']);
        }

        // Permissions & final checks
        @chmod($p['target'], 0640);
        $real = realpath($p['target']);
        if ($real === false || strpos($real, $rootReal) !== 0) {
            @unlink($p['target']);
            foreach ($pending as $r) { @unlink($r['tmp']); }
            return ['ok' => false, 'issues' => [['path' => $p['name'], 'reason' => 'Boundary check failed after write']]];
        }
        if (is_link($real)) {
            @unlink($real);
            foreach ($pending as $r) { @unlink($r['tmp']); }
            return ['ok' => false, 'issues' => [['path' => $p['name'], 'reason' => 'Symlink detected in destination']]];
        }
    }

    return ['ok' => true];
}