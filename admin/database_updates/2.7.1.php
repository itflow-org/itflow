<?php

/*
 * ITFlow - Database update to version 2.7.1 (from 2.7.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Which client record, if any, is the MSP's own company. Most installs create one to
    // hold their internal tickets, documentation, assets and credentials, but nothing in
    // the app knew that record was special. Admin > Settings > Company Details sets it.
    //
    // 0 means no client is designated, which is every existing install - nothing changes
    // until an administrator chooses one.
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_internal_client_id` int(11) NOT NULL DEFAULT 0");
