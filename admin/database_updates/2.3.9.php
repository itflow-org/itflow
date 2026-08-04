<?php

/*
 * ITFlow - Database update to version 2.3.9 (from 2.3.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

     mysqli_query($mysqli, "
        CREATE TABLE `task_approvals` (
          `approval_id` int(11) NOT NULL AUTO_INCREMENT,
          `approval_scope` enum('client','internal') NOT NULL,
          `approval_type` enum('any','technical','billing','specific') NOT NULL,
          `approval_required_user_id` int(11) DEFAULT NULL,
          `approval_status` enum('pending','approved','declined') NOT NULL,
          `approval_created_by` int(11) NOT NULL,
          `approval_approved_by` varchar(255) DEFAULT NULL,
          `approval_url_key` varchar(200) NOT NULL,
          `approval_task_id` int(11) NOT NULL,
          PRIMARY KEY (`approval_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
