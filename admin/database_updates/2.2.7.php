<?php

/*
 * ITFlow - Database update to version 2.2.7 (from 2.2.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `credits` DROP `credit_reference`");
    mysqli_query($mysqli, "ALTER TABLE `credits` ADD `credit_type` ENUM('prepaid', 'manual', 'refund', 'promotion', 'usage') NOT NULL DEFAULT 'manual' AFTER `credit_amount`");
    mysqli_query($mysqli, "ALTER TABLE `credits` ADD `credit_note` TEXT NULL DEFAULT NULL AFTER `credit_type`");
    mysqli_query($mysqli, "ALTER TABLE `credits` ADD `credit_invoice_id` INT(11) NULL DEFAULT NULL AFTER `credit_expire_at`");
    mysqli_query($mysqli, "ALTER TABLE `credits` ADD INDEX (`credit_client_id`)");
    mysqli_query($mysqli, "ALTER TABLE `credits` ADD INDEX (`credit_invoice_id`)");
    mysqli_query($mysqli, "ALTER TABLE `credits` ADD INDEX (`credit_created_at`)");
