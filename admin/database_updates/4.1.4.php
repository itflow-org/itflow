<?php

/*
 * ITFlow - Database update to version 4.1.4 (from 4.1.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * Phone systems (functions/phone.php) - a PBX, FusionPBX the first kind, that sends ITFlow what
 * happens on it: nothing is asked of its API or its database. Its call records come in as
 * FreeSWITCH posts them, its caller ID asks ITFlow who is calling, and its phones say when they
 * start, register and ring.
 *
 *   phone_systems     a PBX - its kind, name, address (for links into it) and the key in the
 *                     addresses it sends to
 *   phone_domains     each of its domains (tenants) ITFlow has heard from, and the client it is
 *   phone_calls       a call, from its call record - the other party (matched to a contact),
 *                     the extension on this side, how it ended, how long, and the ticket it went on
 *   phone_devices     a phone, by its MAC address, as it reports itself - and the asset it is
 *   phone_events      a phone ringing, answered or hung up, for the agent whose desk phone it is
 *   user_settings.user_config_phone_line   an agent's desk phone - its extension@domain
 *
 * Re-runnable: it only adds what isn't there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `phone_systems` (
        `phone_system_id` int(11) NOT NULL AUTO_INCREMENT,
        `phone_system_kind` varchar(50) NOT NULL,
        `phone_system_name` varchar(100) NOT NULL,
        `phone_system_web_url` varchar(500) NOT NULL DEFAULT '',
        `phone_system_key` varchar(100) NOT NULL,
        `phone_system_active` tinyint(1) NOT NULL DEFAULT 1,
        `phone_system_heard_at` datetime DEFAULT NULL,
        `phone_system_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `phone_system_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`phone_system_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `phone_domains` (
        `phone_domain_id` int(11) NOT NULL AUTO_INCREMENT,
        `phone_domain_system_id` int(11) NOT NULL,
        `phone_domain_name` varchar(255) NOT NULL,
        `phone_domain_client_id` int(11) NOT NULL DEFAULT 0,
        `phone_domain_seen_at` datetime DEFAULT NULL,
        `phone_domain_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`phone_domain_id`),
        UNIQUE KEY `phone_domain_name` (`phone_domain_system_id`,`phone_domain_name`),
        KEY `phone_domain_client_id` (`phone_domain_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `phone_calls` (
        `phone_call_id` int(11) NOT NULL AUTO_INCREMENT,
        `phone_call_system_id` int(11) NOT NULL,
        `phone_call_uuid` varchar(64) NOT NULL,
        `phone_call_domain_id` int(11) NOT NULL DEFAULT 0,
        `phone_call_client_id` int(11) NOT NULL DEFAULT 0,
        `phone_call_contact_id` int(11) NOT NULL DEFAULT 0,
        `phone_call_direction` varchar(10) NOT NULL DEFAULT '',
        `phone_call_number` varchar(32) NOT NULL DEFAULT '',
        `phone_call_name` varchar(100) NOT NULL DEFAULT '',
        `phone_call_dialed` varchar(32) NOT NULL DEFAULT '',
        `phone_call_extension` varchar(32) NOT NULL DEFAULT '',
        `phone_call_user_id` int(11) NOT NULL DEFAULT 0,
        `phone_call_status` varchar(20) NOT NULL DEFAULT '',
        `phone_call_started_at` datetime NOT NULL,
        `phone_call_answered_at` datetime DEFAULT NULL,
        `phone_call_seconds` int(11) NOT NULL DEFAULT 0,
        `phone_call_talk_seconds` int(11) NOT NULL DEFAULT 0,
        `phone_call_hangup_cause` varchar(50) NOT NULL DEFAULT '',
        `phone_call_recording` varchar(500) DEFAULT NULL,
        `phone_call_ticket_id` int(11) NOT NULL DEFAULT 0,
        `phone_call_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`phone_call_id`),
        UNIQUE KEY `phone_call_uuid` (`phone_call_system_id`,`phone_call_uuid`),
        KEY `phone_call_client_id` (`phone_call_client_id`,`phone_call_started_at`),
        KEY `phone_call_contact_id` (`phone_call_contact_id`,`phone_call_started_at`),
        KEY `phone_call_started_at` (`phone_call_started_at`),
        KEY `phone_call_ticket_id` (`phone_call_ticket_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `phone_devices` (
        `phone_device_id` int(11) NOT NULL AUTO_INCREMENT,
        `phone_device_system_id` int(11) NOT NULL,
        `phone_device_mac` varchar(12) NOT NULL,
        `phone_device_vendor` varchar(30) NOT NULL DEFAULT '',
        `phone_device_model` varchar(100) NOT NULL DEFAULT '',
        `phone_device_firmware` varchar(100) NOT NULL DEFAULT '',
        `phone_device_ip` varchar(64) NOT NULL DEFAULT '',
        `phone_device_line` varchar(100) NOT NULL DEFAULT '',
        `phone_device_domain_id` int(11) NOT NULL DEFAULT 0,
        `phone_device_client_id` int(11) NOT NULL DEFAULT 0,
        `phone_device_asset_id` int(11) NOT NULL DEFAULT 0,
        `phone_device_status` varchar(20) NOT NULL DEFAULT '',
        `phone_device_status_at` datetime DEFAULT NULL,
        `phone_device_heard_at` datetime DEFAULT NULL,
        `phone_device_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`phone_device_id`),
        UNIQUE KEY `phone_device_mac` (`phone_device_system_id`,`phone_device_mac`),
        KEY `phone_device_asset_id` (`phone_device_asset_id`),
        KEY `phone_device_client_id` (`phone_device_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `phone_events` (
        `phone_event_id` int(11) NOT NULL AUTO_INCREMENT,
        `phone_event_system_id` int(11) NOT NULL,
        `phone_event_domain_id` int(11) NOT NULL DEFAULT 0,
        `phone_event_line` varchar(100) NOT NULL DEFAULT '',
        `phone_event_user_id` int(11) NOT NULL DEFAULT 0,
        `phone_event_kind` varchar(20) NOT NULL,
        `phone_event_call` varchar(100) NOT NULL DEFAULT '',
        `phone_event_number` varchar(32) NOT NULL DEFAULT '',
        `phone_event_name` varchar(100) NOT NULL DEFAULT '',
        `phone_event_contact_id` int(11) NOT NULL DEFAULT 0,
        `phone_event_client_id` int(11) NOT NULL DEFAULT 0,
        `phone_event_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`phone_event_id`),
        KEY `phone_event_user_id` (`phone_event_user_id`,`phone_event_id`),
        KEY `phone_event_at` (`phone_event_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_settings' AND COLUMN_NAME = 'user_config_phone_line' LIMIT 1");
    if ($itflow_column && mysqli_num_rows($itflow_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_phone_line` varchar(255) DEFAULT NULL AFTER `user_config_reminder_email`");
    }

    unset($itflow_column);
