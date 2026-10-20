<?php

/*
 * The setup wizard's Database step (setup/index.php) - connecting to the
 * database it is given, making sure it is empty, creating ITFlow's tables in
 * it from db.sql, and the config.php that points ITFlow at it.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

/*
 * A connection to the database these details name - or null, with $error
 * saying why not, in words. mysqli throws on a failed connection from PHP 8.1
 * on, which is what used to end setup on an error page.
 */
function setupConnect($host, $username, $password, $database, &$error = '') {
    $error = '';
    try {
        $connection = @mysqli_connect($host, $username, $password, $database);
    } catch (mysqli_sql_exception $e) {
        $connection = false;
        $error = $e->getMessage();
    }
    if (!$connection) {
        $error = $error !== '' ? $error : (mysqli_connect_error() ?: 'the server did not answer');
        return null;
    }
    mysqli_set_charset($connection, 'utf8mb4');
    return $connection;
}

/* What is wrong with a database host - '' when nothing is. A name or an address, without a port or a path. */
function setupHostProblem($host) {
    if ($host === '') {
        return "The database's host - localhost, unless it is on another server.";
    }
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
        return 'The host is a name or an address - localhost, db.example.com or 10.0.0.5 - without a port or a slash.';
    }
    return '';
}

/* The tables a database has - their names. */
function setupTables($connection) {
    $tables = [];
    try {
        $result = mysqli_query($connection, "SHOW TABLES");
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }
    } catch (mysqli_sql_exception $e) {
        return [];
    }
    return $tables;
}

/*
 * Create ITFlow's tables from db.sql - one statement at a time, stopping at
 * the first that fails, with $error saying which and why. A failed import
 * takes the tables it made away again, so the database is left as it was
 * found: empty.
 */
function setupImportSchema($connection, $file, &$error = '') {
    $error = '';
    $lines = @file($file);
    if ($lines === false) {
        $error = "db.sql could not be read from " . $file;
        return false;
    }
    $statement = '';
    foreach ($lines as $line) {
        // Comments and blank lines between statements
        if ($statement === '' && (substr($line, 0, 2) === '--' || trim($line) === '')) {
            continue;
        }
        $statement .= $line;
        if (substr(rtrim($line), -1) !== ';') {
            continue;
        }
        try {
            $ok = mysqli_query($connection, $statement);
        } catch (mysqli_sql_exception $e) {
            $ok = false;
            $error = $e->getMessage();
        }
        if (!$ok) {
            $error = ($error !== '' ? $error : mysqli_error($connection)) . ' - in: ' . mb_substr(trim(preg_replace('/\s+/', ' ', $statement)), 0, 120);
            setupDropTables($connection);
            return false;
        }
        $statement = '';
    }
    if (!in_array('users', setupTables($connection), true)) {
        $error = "db.sql ran, but the tables are not there";
        return false;
    }
    return true;
}

/* Every table in a database, dropped - after a failed import. */
function setupDropTables($connection) {
    try {
        mysqli_query($connection, "SET FOREIGN_KEY_CHECKS = 0");
        foreach (setupTables($connection) as $table) {
            mysqli_query($connection, "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`");
        }
        mysqli_query($connection, "SET FOREIGN_KEY_CHECKS = 1");
    } catch (mysqli_sql_exception $e) {
        // Left for whoever empties the database by hand
    }
}

/* What config.php says - the database to use, where ITFlow is reached, the branch it updates from, and this install's id. */
function setupConfigContents($host, $username, $password, $database, $base_url, $installation_id, $repo_branch = 'master') {
    $config = "<?php\n\n";
    $config .= "\$dbhost = " . var_export($host, true) . ";\n";
    $config .= "\$dbusername = " . var_export($username, true) . ";\n";
    $config .= "\$dbpassword = " . var_export($password, true) . ";\n";
    $config .= "\$database = " . var_export($database, true) . ";\n";
    $config .= "\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database) or die('Database Connection Failed');\n";
    $config .= "\$config_app_name = 'ITFlow';\n";
    $config .= "\$config_base_url = " . var_export($base_url, true) . ";\n";
    $config .= "\$config_https_only = TRUE;\n";
    $config .= "\$repo_branch = " . var_export((string) $repo_branch, true) . ";\n";
    $config .= "\$installation_id = " . var_export($installation_id, true) . ";\n";
    return $config;
}

/*
 * Close setup - $config_enable_setup = 0 at the end of config.php, so /setup
 * sends everyone to the sign-in page from now on. False when config.php can't
 * be written to.
 */
function setupClose($config_file) {
    if (@file_put_contents($config_file, "\n\$config_enable_setup = 0;\n\n", FILE_APPEND | LOCK_EX) === false) {
        return false;
    }
    setupConfigChanged($config_file);
    return true;
}

/*
 * config.php has just been written - drop the copy opcache keeps of it. Within
 * revalidate_freq (2 seconds by default) of its last look, the very next page
 * - the sign-in page Finish goes to - would otherwise still read setup as open,
 * and send you back to it.
 */
function setupConfigChanged($config_file) {
    clearstatcache(true, $config_file);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($config_file, true);
    }
}
