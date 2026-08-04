<?php

/*
 * ITFlow - Database update to version 2.3.8 (from 2.3.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "
        CREATE TABLE `asset_tags` (
            `asset_tag_asset_id` INT(11) NOT NULL,
            `asset_tag_tag_id` INT(11) NOT NULL,
            PRIMARY KEY (`asset_tag_asset_id`, `asset_tag_tag_id`),
            CONSTRAINT `fk_asset`
                FOREIGN KEY (`asset_tag_asset_id`)
                REFERENCES `assets`(`asset_id`)
                ON DELETE CASCADE,
            CONSTRAINT `fk_tag`
                FOREIGN KEY (`asset_tag_tag_id`)
                REFERENCES `tags`(`tag_id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
