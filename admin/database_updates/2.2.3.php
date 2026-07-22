<?php

/*
 * ITFlow - Database update to version 2.2.3 (from 2.2.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE `payment_methods` (
        `payment_method_id` INT(11) NOT NULL AUTO_INCREMENT,
        `payment_method_name` VARCHAR(200) NOT NULL,
        `payment_method_description` VARCHAR(250) DEFAULT NULL,
        `payment_method_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `payment_method_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`payment_method_id`)
    )");

    mysqli_query($mysqli, "CREATE TABLE `payment_providers` (
        `payment_provider_id` INT(11) NOT NULL AUTO_INCREMENT,
        `payment_provider_name` VARCHAR(200) NOT NULL,
        `payment_provider_description` VARCHAR(250) DEFAULT NULL,
        `payment_provider_public_key` VARCHAR(250) DEFAULT NULL,
        `payment_provider_private_key` VARCHAR(250) DEFAULT NULL,
        `payment_provider_threshold` DECIMAL(15,2) DEFAULT NULL,
        `payment_provider_active` TINYINT(1) NOT NULL DEFAULT 1,
        `payment_provider_account` INT(11) NOT NULL,
        `payment_provider_expense_vendor` INT(11) NOT NULL DEFAULT 0,
        `payment_provider_expense_category` INT(11) NOT NULL DEFAULT 0,
        `payment_provider_expense_percentage_fee` DECIMAL(4,4) DEFAULT NULL,
        `payment_provider_expense_flat_fee` DECIMAL(15,2) DEFAULT NULL,
        `payment_provider_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `payment_provider_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`payment_provider_id`)
    )");

    mysqli_query($mysqli, "CREATE TABLE `client_saved_payment_methods` (
        `saved_payment_id` INT(11) NOT NULL AUTO_INCREMENT,
        `saved_payment_provider_method` VARCHAR(200) NOT NULL,
        `saved_payment_description` VARCHAR(200) DEFAULT NULL,
        `saved_payment_client_id` INT(11) NOT NULL,
        `saved_payment_provider_id` INT(11) NOT NULL,
        `saved_payment_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `saved_payment_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`saved_payment_id`),
        FOREIGN KEY (`saved_payment_client_id`) REFERENCES clients(`client_id`) ON DELETE CASCADE,
        FOREIGN KEY (`saved_payment_provider_id`) REFERENCES payment_providers(`payment_provider_id`) ON DELETE CASCADE
    )");

    mysqli_query($mysqli, "CREATE TABLE `client_payment_provider` (
        `client_id` INT(11) NOT NULL,
        `payment_provider_id` INT(11) NOT NULL,
        `payment_provider_client` VARCHAR(200) NOT NULL,
        `client_payment_provider_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`client_id`, `payment_provider_id`),
        FOREIGN KEY (`client_id`) REFERENCES clients(`client_id`) ON DELETE CASCADE,
        FOREIGN KEY (`payment_provider_id`) REFERENCES payment_providers(`payment_provider_id`) ON DELETE CASCADE
    )");

    mysqli_query($mysqli, "ALTER TABLE `recurring_payments` ADD `recurring_payment_saved_payment_id` INT(11) DEFAULT NULL AFTER `recurring_payment_recurring_invoice_id`");

    mysqli_query($mysqli, "ALTER TABLE `recurring_payments` ADD CONSTRAINT `fk_recurring_saved_payment` FOREIGN KEY (`recurring_payment_saved_payment_id`) REFERENCES `client_saved_payment_methods`(`saved_payment_id`) ON DELETE CASCADE");
