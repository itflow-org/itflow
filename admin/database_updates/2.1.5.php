<?php

/*
 * ITFlow - Database update to version 2.1.5 (from 2.1.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_timer_autostart` TINYINT(1) NOT NULL DEFAULT '0' AFTER `config_ticket_default_billable`");
    mysqli_query($mysqli, "ALTER TABLE `tickets` ADD `ticket_due_at` DATETIME DEFAULT NULL AFTER `ticket_updated_at`");
    mysqli_query($mysqli, "ALTER TABLE `companies` ADD `company_tax_id` VARCHAR(200) DEFAULT NULL AFTER `company_currency`");
    mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_invoice_show_tax_id` TINYINT(1) NOT NULL DEFAULT '0' AFTER `config_invoice_paid_notification_email`");
