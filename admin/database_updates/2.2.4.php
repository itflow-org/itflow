<?php

/*
 * ITFlow - Database update to version 2.2.4 (from 2.2.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE `credits` (
        `credit_id` INT(11) NOT NULL AUTO_INCREMENT,
        `credit_amount` DECIMAL(15,2) NOT NULL,
        `credit_reference` VARCHAR(250) DEFAULT NULL,
        `credit_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        `credit_created_by` INT(11) NOT NULL,
        `credit_expire_at` DATE DEFAULT NULL,
        `credit_client_id` INT(11) NOT NULL,
        PRIMARY KEY (`credit_id`)
    )");

    mysqli_query($mysqli, "ALTER TABLE `invoices` ADD `invoice_credit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `invoice_discount_amount`");

    mysqli_query($mysqli, "CREATE TABLE `discount_codes` (
        `discount_code_id` INT(11) NOT NULL AUTO_INCREMENT,
        `discount_code_description` VARCHAR(250) DEFAULT NULL,
        `discount_code_amount` DECIMAL(15,2) NOT NULL,
        `discount_code` VARCHAR(200) NOT NULL,
        `discount_code_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        `discount_code_created_by` INT(11) NOT NULL,
        `discount_code_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `discount_code_archived_at` DATETIME NULL DEFAULT NULL,
        `discount_code_expire_at` DATE DEFAULT NULL,
        PRIMARY KEY (`discount_code_id`)
    )");
