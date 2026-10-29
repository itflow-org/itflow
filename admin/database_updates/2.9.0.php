<?php

/*
 * ITFlow - Database update to version 2.9.0 (from 2.8.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Shared links rebuilt (functions/share.php). A link now carries one
    // secret, after the # where it never reaches the server's logs. The table
    // keeps a hash of it to find the link, and anything secret in the link - a
    // login, a secure note, what a client sends back - is encrypted with a key
    // made from that secret, which is never stored. Links can also be secure
    // notes and requests, need a passphrase or a code emailed to the recipient,
    // and stay listed after they end (used up, expired, turned off) so there is
    // a record of who opened what.
    //
    // item_version 1 is a link made before this update (key and ek in the
    // query string, the login under aes-128-cbc) - they keep working until
    // they expire, which is at most a month.
    mysqli_query($mysqli, "UPDATE `shared_items` SET `item_view_limit` = 0 WHERE `item_view_limit` IS NULL");
    mysqli_query($mysqli, "ALTER TABLE `shared_items`
        MODIFY `item_note` varchar(1000) DEFAULT NULL,
        MODIFY `item_views` int(11) NOT NULL DEFAULT 0,
        MODIFY `item_view_limit` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN `item_version` tinyint(1) NOT NULL DEFAULT 1 AFTER `item_key`,
        ADD COLUMN `item_name` varchar(255) DEFAULT NULL AFTER `item_related_id`,
        ADD COLUMN `item_payload` mediumtext DEFAULT NULL AFTER `item_encrypted_credential`,
        ADD COLUMN `item_vault_key` varchar(255) DEFAULT NULL AFTER `item_payload`,
        ADD COLUMN `item_passphrase` varchar(255) DEFAULT NULL AFTER `item_vault_key`,
        ADD COLUMN `item_verify_email` tinyint(1) NOT NULL DEFAULT 0 AFTER `item_passphrase`,
        ADD COLUMN `item_code` varchar(255) DEFAULT NULL AFTER `item_verify_email`,
        ADD COLUMN `item_code_sent_at` datetime DEFAULT NULL AFTER `item_code`,
        ADD COLUMN `item_failed_attempts` int(11) NOT NULL DEFAULT 0 AFTER `item_code_sent_at`,
        ADD COLUMN `item_notify` tinyint(1) NOT NULL DEFAULT 1 AFTER `item_recipient`,
        ADD COLUMN `item_last_viewed_at` datetime DEFAULT NULL AFTER `item_view_limit`,
        ADD COLUMN `item_created_by` int(11) NOT NULL DEFAULT 0 AFTER `item_created_at`,
        ADD COLUMN `item_received_at` datetime DEFAULT NULL AFTER `item_expire_at`,
        ADD COLUMN `item_ended_at` datetime DEFAULT NULL AFTER `item_received_at`,
        ADD COLUMN `item_ended_by` int(11) NOT NULL DEFAULT 0 AFTER `item_ended_at`,
        ADD COLUMN `item_end_reason` varchar(50) DEFAULT NULL AFTER `item_ended_by`,
        ADD KEY `item_client_id` (`item_client_id`),
        ADD KEY `item_type_related` (`item_type`, `item_related_id`),
        ADD KEY `item_expire_at` (`item_expire_at`)");

    // Links already out keep working - the key they carry is hashed the same
    // way a new one is looked up
    mysqli_query($mysqli, "UPDATE `shared_items` SET `item_key` = SHA2(`item_key`, 256)");

    // What each existing link was for, so the list still says once the record goes
    mysqli_query($mysqli, "UPDATE `shared_items`
        LEFT JOIN `credentials` ON `item_type` = 'Credential' AND `credential_id` = `item_related_id`
        LEFT JOIN `documents` ON `item_type` = 'Document' AND `document_id` = `item_related_id`
        LEFT JOIN `files` ON `item_type` = 'File' AND `file_id` = `item_related_id`
        SET `item_name` = LEFT(COALESCE(`credential_name`, `document_name`, `file_name`), 255)
        WHERE `item_name` IS NULL");

    // Sharing policy, Admin > Settings > Security: the longest a link may
    // last, and whether a password or secure note has to have a passphrase or
    // an emailed code. Defaults match what the share window offered before.
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_share_max_hours` int(11) NOT NULL DEFAULT 720,
        ADD COLUMN `config_share_require_protection` tinyint(1) NOT NULL DEFAULT 0");
