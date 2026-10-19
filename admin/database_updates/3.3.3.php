<?php

/*
 * ITFlow - Database update to version 3.3.3 (from 3.3.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // App logs, retention and forwarding (functions/app_log.php, functions/log_forward.php).
    //
    // Each step checks first, so it can run again.
    $itflow_has_column = function ($column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = '" . mysqli_real_escape_string($mysqli, $column) . "' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    // 1. The app logs are kept for their own number of days, starting from
    //    what the audit log keeps, as both were kept for that until now. For
    //    both, 0 is now keep forever.
    if (!$itflow_has_column('config_app_log_retention')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD COLUMN `config_app_log_retention` int(11) NOT NULL DEFAULT 90");
        mysqli_query($mysqli, "UPDATE `settings` SET `config_app_log_retention` = `config_log_retention`");
    }

    // 2. Sending log entries on to a syslog server or SIEM as they are
    //    written: on or off, where to (host, port, and UDP, TCP or TLS), how
    //    (JSON, CEF or plain text), what (the audit log, and the app logs from
    //    a level up - '' for none) and, over TLS, whether the server's
    //    certificate is checked.
    $itflow_columns = [
        'config_log_forward_enabled' => "tinyint(1) NOT NULL DEFAULT 0",
        'config_log_forward_host' => "varchar(200) DEFAULT NULL",
        'config_log_forward_port' => "int(11) NOT NULL DEFAULT 514",
        'config_log_forward_protocol' => "varchar(10) NOT NULL DEFAULT 'udp'",
        'config_log_forward_format' => "varchar(10) NOT NULL DEFAULT 'json'",
        'config_log_forward_audit' => "tinyint(1) NOT NULL DEFAULT 1",
        'config_log_forward_app_level' => "varchar(10) NOT NULL DEFAULT 'warning'",
        'config_log_forward_tls_verify' => "tinyint(1) NOT NULL DEFAULT 1",
    ];
    foreach ($itflow_columns as $itflow_column => $itflow_definition) {
        if (!$itflow_has_column($itflow_column)) {
            mysqli_query($mysqli, "ALTER TABLE `settings` ADD COLUMN `$itflow_column` $itflow_definition");
        }
    }

    // 3. The app logs are listed and cleared by date, and nightly deletes the
    //    old ones by date - a key on it.
    $itflow_index = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_logs' AND INDEX_NAME = 'app_log_created_at' LIMIT 1");
    if (!$itflow_index || mysqli_num_rows($itflow_index) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `app_logs` ADD KEY `app_log_created_at` (`app_log_created_at`)");
    }

    unset($itflow_has_column, $itflow_columns, $itflow_column, $itflow_definition, $itflow_index);
