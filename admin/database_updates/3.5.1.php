<?php

/*
 * ITFlow - Database update to version 3.5.1 (from 3.5.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * Agent invites (functions/user_invite.php). An admin invites someone by
 * email from New User or Get started: who they are, their role, MFA and the
 * clients they see are kept here until they open the link and choose their
 * own password (/join.php) - only then are they an agent, a users row.
 *
 *   invite_token_hash   the SHA-256 of the link's token - the token itself is
 *                       never kept
 *   invite_vault        their copy of the credential vault's key until they
 *                       accept, sealed under the token as an agent's is under
 *                       their password - so it can't be opened from here alone.
 *                       Wiped once the invite is accepted or cancelled
 *   invite_client_access  {"allow": [client_id, ...], "deny": [...]} - the
 *                       Client access rules, for a role without admin
 *   invite_avatar       a picture picked for them, in uploads/users/invites/<invite_id>/
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `user_invites` (
        `invite_id` int(11) NOT NULL AUTO_INCREMENT,
        `invite_email` varchar(200) NOT NULL,
        `invite_name` varchar(200) NOT NULL,
        `invite_role_id` int(11) NOT NULL DEFAULT 0,
        `invite_force_mfa` tinyint(1) NOT NULL DEFAULT 0,
        `invite_client_access` text DEFAULT NULL,
        `invite_avatar` varchar(200) DEFAULT NULL,
        `invite_token_hash` char(64) NOT NULL DEFAULT '',
        `invite_vault` varchar(255) NOT NULL DEFAULT '',
        `invite_expires_at` datetime NOT NULL,
        `invite_sent_at` datetime DEFAULT NULL,
        `invite_sent_count` int(11) NOT NULL DEFAULT 0,
        `invite_created_by` int(11) NOT NULL DEFAULT 0,
        `invite_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `invite_accepted_at` datetime DEFAULT NULL,
        `invite_user_id` int(11) NOT NULL DEFAULT 0,
        `invite_cancelled_at` datetime DEFAULT NULL,
        PRIMARY KEY (`invite_id`),
        KEY `invite_email` (`invite_email`),
        KEY `invite_user_id` (`invite_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
