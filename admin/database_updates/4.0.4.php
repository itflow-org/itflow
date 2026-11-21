<?php

/*
 * ITFlow - Database update to version 4.0.4 (from 4.0.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * password_history - a credential's earlier passwords, kept when one is changed so the old one can
 * be looked up or put back when the change breaks something (functions/credential.php). Each is
 * the encrypted value the credential had, copied as it was - encrypted with the same vault key.
 * The newest ten are kept for each credential, and they go when the credential is deleted.
 *
 * Re-runnable: the table is only made where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `password_history` (
        `password_history_id` int(11) NOT NULL AUTO_INCREMENT,
        `password_history_password` varchar(500) NOT NULL,
        `password_history_set_at` datetime DEFAULT NULL,
        `password_history_replaced_at` datetime NOT NULL DEFAULT current_timestamp(),
        `password_history_user_id` int(11) NOT NULL DEFAULT 0,
        `password_history_credential_id` int(11) NOT NULL,
        PRIMARY KEY (`password_history_id`),
        KEY `password_history_credential_id` (`password_history_credential_id`),
        CONSTRAINT `password_history_credential` FOREIGN KEY (`password_history_credential_id`) REFERENCES `credentials` (`credential_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
