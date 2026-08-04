<?php

/*
 * ITFlow - Database update to version 2.0.7 (from 2.0.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Fix service_domains to yse InnoDB instead of MyISAM
    mysqli_query($mysqli, "ALTER TABLE service_domains ENGINE = InnoDB;");
