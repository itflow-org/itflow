<?php

/*
 * ITFlow - Database update to version 3.1.1 (from 3.1.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A rate to the ten-thousandth of a percent (Quebec's 9.975%) rather than
    // a float, a description and the registration number the rate is charged
    // under, and - when a rate in use was changed - the rate that took its place
    // (functions/tax.php: taxChangeRate())
    mysqli_query($mysqli, "ALTER TABLE `taxes`
        MODIFY `tax_percent` decimal(9,4) NOT NULL DEFAULT 0.0000,
        ADD COLUMN `tax_description` varchar(255) DEFAULT NULL AFTER `tax_percent`,
        ADD COLUMN `tax_registration_number` varchar(100) DEFAULT NULL AFTER `tax_description`,
        ADD COLUMN `tax_replaced_by` int(11) NOT NULL DEFAULT 0 AFTER `tax_registration_number`");

    // A rate made of parts - state and county, GST and PST - each charged on
    // the line, or on the line and the parts before it (compound), each with
    // its own registration number if it has one (GST and QST do). A rate
    // without parts is its own single part; with them, tax_percent is the
    // whole rate they come to.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tax_components` (
        `component_id` int(11) NOT NULL AUTO_INCREMENT,
        `component_tax_id` int(11) NOT NULL,
        `component_name` varchar(200) NOT NULL,
        `component_percent` decimal(9,4) NOT NULL DEFAULT 0.0000,
        `component_compound` tinyint(1) NOT NULL DEFAULT 0,
        `component_registration_number` varchar(100) DEFAULT NULL,
        `component_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`component_id`),
        KEY `component_tax_id` (`component_tax_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // A client's own rate (0: the company's default) and tax exemption, with
    // the certificate it rests on and when that runs out
    mysqli_query($mysqli, "ALTER TABLE `clients`
        ADD COLUMN `client_default_tax_id` int(11) NOT NULL DEFAULT 0 AFTER `client_tax_id_number`,
        ADD COLUMN `client_tax_exempt` tinyint(1) NOT NULL DEFAULT 0 AFTER `client_default_tax_id`,
        ADD COLUMN `client_tax_exempt_number` varchar(200) DEFAULT NULL AFTER `client_tax_exempt`,
        ADD COLUMN `client_tax_exempt_expires` date DEFAULT NULL AFTER `client_tax_exempt_number`");

    // The default rate for new lines, whether billed time, trips and expenses
    // are taxed, and discounts taken off before tax is worked out
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_tax_default_id` int(11) NOT NULL DEFAULT 0 AFTER `config_invoice_show_tax_id`,
        ADD COLUMN `config_tax_time` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_tax_default_id`,
        ADD COLUMN `config_tax_trips` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_tax_time`,
        ADD COLUMN `config_tax_expenses` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_tax_trips`,
        ADD COLUMN `config_tax_discount_before` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_tax_expenses`");
