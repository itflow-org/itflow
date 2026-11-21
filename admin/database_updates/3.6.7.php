<?php

/*
 * ITFlow - Database update to version 3.6.7 (from 3.6.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * RMM Providers - devices from an RMM as assets (functions/rmm.php), Tactical RMM the first:
 *   rmm_providers - each RMM ITFlow reads from: its kind (tactical), the name it goes by, its
 *     API address, its dashboard address, its key and - for an RMM that signs in with an id
 *     and a secret - its secret, whether it is on, and how its last sync went
 *   rmm_clients - each provider's clients, linked to an ITFlow client or not yet; only the
 *     linked ones have their devices brought in
 *   rmm_devices - one a device a provider has: the asset it is, what the RMM last said about
 *     it (online or not, last seen, who is signed in, a reboot or patches waiting, failing
 *     checks) and what the sync last wrote to the asset, so a field changed in ITFlow is left
 *     alone. A device the RMM no longer has keeps its row, marked removed.
 *
 * Re-runnable: each table is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `rmm_providers` (
        `rmm_provider_id` int(11) NOT NULL AUTO_INCREMENT,
        `rmm_provider_kind` varchar(50) NOT NULL,
        `rmm_provider_name` varchar(100) NOT NULL,
        `rmm_provider_api_url` varchar(500) NOT NULL DEFAULT '',
        `rmm_provider_web_url` varchar(500) NOT NULL DEFAULT '',
        `rmm_provider_api_key` varchar(500) NOT NULL DEFAULT '',
        `rmm_provider_api_secret` varchar(500) NOT NULL DEFAULT '',
        `rmm_provider_active` tinyint(1) NOT NULL DEFAULT 1,
        `rmm_provider_sync_at` datetime DEFAULT NULL,
        `rmm_provider_sync_ok` tinyint(1) NOT NULL DEFAULT 0,
        `rmm_provider_sync_note` varchar(500) DEFAULT NULL,
        `rmm_provider_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `rmm_provider_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`rmm_provider_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `rmm_clients` (
        `rmm_client_id` int(11) NOT NULL AUTO_INCREMENT,
        `rmm_client_provider_id` int(11) NOT NULL,
        `rmm_client_remote_id` varchar(100) NOT NULL,
        `rmm_client_name` varchar(255) NOT NULL,
        `rmm_client_sites` int(11) NOT NULL DEFAULT 0,
        `rmm_client_devices` int(11) NOT NULL DEFAULT 0,
        `rmm_client_client_id` int(11) NOT NULL DEFAULT 0,
        `rmm_client_seen_at` datetime DEFAULT NULL,
        `rmm_client_synced_at` datetime DEFAULT NULL,
        `rmm_client_removed_at` datetime DEFAULT NULL,
        `rmm_client_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `rmm_client_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`rmm_client_id`),
        UNIQUE KEY `rmm_client_remote` (`rmm_client_provider_id`,`rmm_client_remote_id`),
        KEY `rmm_client_client_id` (`rmm_client_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `rmm_devices` (
        `rmm_device_id` int(11) NOT NULL AUTO_INCREMENT,
        `rmm_device_provider_id` int(11) NOT NULL,
        `rmm_device_remote_id` varchar(100) NOT NULL,
        `rmm_device_asset_id` int(11) NOT NULL DEFAULT 0,
        `rmm_device_client_id` int(11) NOT NULL DEFAULT 0,
        `rmm_device_rmm_client_id` int(11) NOT NULL DEFAULT 0,
        `rmm_device_hostname` varchar(255) NOT NULL DEFAULT '',
        `rmm_device_site` varchar(255) DEFAULT NULL,
        `rmm_device_role` varchar(20) NOT NULL DEFAULT '',
        `rmm_device_platform` varchar(20) NOT NULL DEFAULT '',
        `rmm_device_os` varchar(255) DEFAULT NULL,
        `rmm_device_status` varchar(20) NOT NULL DEFAULT '',
        `rmm_device_last_seen_at` datetime DEFAULT NULL,
        `rmm_device_boot_at` datetime DEFAULT NULL,
        `rmm_device_logged_user` varchar(255) DEFAULT NULL,
        `rmm_device_public_ip` varchar(100) DEFAULT NULL,
        `rmm_device_local_ips` varchar(500) DEFAULT NULL,
        `rmm_device_needs_reboot` tinyint(1) NOT NULL DEFAULT 0,
        `rmm_device_patches_pending` tinyint(1) NOT NULL DEFAULT 0,
        `rmm_device_checks_total` int(11) NOT NULL DEFAULT 0,
        `rmm_device_checks_failing` int(11) NOT NULL DEFAULT 0,
        `rmm_device_maintenance` tinyint(1) NOT NULL DEFAULT 0,
        `rmm_device_agent_version` varchar(50) DEFAULT NULL,
        `rmm_device_synced` text DEFAULT NULL,
        `rmm_device_synced_at` datetime DEFAULT NULL,
        `rmm_device_removed_at` datetime DEFAULT NULL,
        `rmm_device_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`rmm_device_id`),
        UNIQUE KEY `rmm_device_remote` (`rmm_device_provider_id`,`rmm_device_remote_id`),
        KEY `rmm_device_asset_id` (`rmm_device_asset_id`),
        KEY `rmm_device_client_id` (`rmm_device_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
