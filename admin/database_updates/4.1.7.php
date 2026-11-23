<?php

/*
 * ITFlow - Database update to version 4.1.7 (from 4.1.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * Where things plug in - from a network platform (functions/rmm_network.php).
 *
 *   rmm_seen   one of a client's assets as a platform's site last saw it on the network: the
 *              switch or access point it was on (its MAC address - a platform device's), the
 *              port or Wi-Fi network, its IP address and network. Only devices that are one of
 *              the client's assets are kept - not every phone that joined the guest Wi-Fi. A
 *              platform's own switches and access points have a row too, for what they are
 *              plugged into.
 *
 * Re-runnable: it only adds what isn't there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `rmm_seen` (
        `rmm_seen_id` int(11) NOT NULL AUTO_INCREMENT,
        `rmm_seen_provider_id` int(11) NOT NULL,
        `rmm_seen_rmm_client_id` int(11) NOT NULL,
        `rmm_seen_client_id` int(11) NOT NULL DEFAULT 0,
        `rmm_seen_asset_id` int(11) NOT NULL DEFAULT 0,
        `rmm_seen_mac` varchar(12) NOT NULL,
        `rmm_seen_hostname` varchar(200) NOT NULL DEFAULT '',
        `rmm_seen_ip` varchar(100) NOT NULL DEFAULT '',
        `rmm_seen_via_mac` varchar(12) NOT NULL DEFAULT '',
        `rmm_seen_port` varchar(50) NOT NULL DEFAULT '',
        `rmm_seen_wired` tinyint(1) NOT NULL DEFAULT 1,
        `rmm_seen_ssid` varchar(100) NOT NULL DEFAULT '',
        `rmm_seen_network` varchar(200) NOT NULL DEFAULT '',
        `rmm_seen_vlan` int(11) NOT NULL DEFAULT 0,
        `rmm_seen_at` datetime DEFAULT NULL,
        `rmm_seen_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`rmm_seen_id`),
        UNIQUE KEY `rmm_seen_device` (`rmm_seen_provider_id`, `rmm_seen_rmm_client_id`, `rmm_seen_mac`),
        KEY `rmm_seen_asset_id` (`rmm_seen_asset_id`),
        KEY `rmm_seen_via` (`rmm_seen_provider_id`, `rmm_seen_via_mac`),
        KEY `rmm_seen_client_id` (`rmm_seen_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
