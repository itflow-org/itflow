<?php

/*
 * ITFlow - Database update to version 4.1.6 (from 4.1.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * A network platform's networks and VLANs, kept up as its sites' clients' networks
 * (functions/rmm_network.php).
 *
 *   rmm_networks   one network of a platform's site, and the ITFlow network it is - 0 once
 *                  that was deleted in ITFlow, so it isn't made again (Bring back resets it).
 *                  rmm_network_synced is the JSON of what the sync last wrote to each detail,
 *                  so a detail changed in ITFlow is left as it is.
 *
 * Re-runnable: it only adds what isn't there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `rmm_networks` (
        `rmm_network_id` int(11) NOT NULL AUTO_INCREMENT,
        `rmm_network_provider_id` int(11) NOT NULL,
        `rmm_network_rmm_client_id` int(11) NOT NULL,
        `rmm_network_remote_id` varchar(100) NOT NULL,
        `rmm_network_client_id` int(11) NOT NULL DEFAULT 0,
        `rmm_network_network_id` int(11) NOT NULL DEFAULT 0,
        `rmm_network_name` varchar(200) NOT NULL DEFAULT '',
        `rmm_network_synced` text DEFAULT NULL,
        `rmm_network_synced_at` datetime DEFAULT NULL,
        `rmm_network_removed_at` datetime DEFAULT NULL,
        `rmm_network_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`rmm_network_id`),
        UNIQUE KEY `rmm_network_remote` (`rmm_network_provider_id`, `rmm_network_rmm_client_id`, `rmm_network_remote_id`),
        KEY `rmm_network_network_id` (`rmm_network_network_id`),
        KEY `rmm_network_client_id` (`rmm_network_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
