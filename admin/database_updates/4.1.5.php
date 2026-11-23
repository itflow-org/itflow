<?php

/*
 * ITFlow - Database update to version 4.1.5 (from 4.1.4)
 * Included by admin/database_updates.php - do not access directly
 *
 * Network platforms - UniFi first (Admin > Network Platforms, functions/rmm_unifi.php). They
 * use the RMM providers' tables: a platform is an rmm_providers row of a network kind, its
 * sites are rmm_clients rows, and its switches, access points and gateways rmm_devices rows.
 *
 *   rmm_providers.rmm_provider_insecure   1 to accept the certificate a self-hosted controller
 *                                         made for itself - 0, checked as usual, for the rest
 *   rmm_devices.rmm_device_mac            the device's MAC address, 12 lower-case hex digits -
 *                                         '' when its kind doesn't say
 *
 * Re-runnable: it only adds what isn't there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_has = function ($table, $what, $name) use ($mysqli) {
        $from = $what === 'column' ? 'COLUMNS' : 'STATISTICS';
        $field = $what === 'column' ? 'COLUMN_NAME' : 'INDEX_NAME';
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.$from
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND $field = '$name' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_has('rmm_providers', 'column', 'rmm_provider_insecure')) {
        mysqli_query($mysqli, "ALTER TABLE `rmm_providers` ADD `rmm_provider_insecure` tinyint(1) NOT NULL DEFAULT 0 AFTER `rmm_provider_api_secret`");
    }
    if (!$itflow_has('rmm_devices', 'column', 'rmm_device_mac')) {
        mysqli_query($mysqli, "ALTER TABLE `rmm_devices` ADD `rmm_device_mac` varchar(12) NOT NULL DEFAULT '' AFTER `rmm_device_remote_id`");
    }
    if (!$itflow_has('rmm_devices', 'key', 'rmm_device_mac')) {
        mysqli_query($mysqli, "ALTER TABLE `rmm_devices` ADD KEY `rmm_device_mac` (`rmm_device_mac`)");
    }

    unset($itflow_has);
