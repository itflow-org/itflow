<?php

/*
 * ITFlow - Database update to version 2.9.8 (from 2.9.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Payment reminders on a schedule (functions/billing_email.php,
    // billingReminderRules()): days from the due date - negative before it -
    // then every so many days while it is still owed. 1 and 30, then every
    // 30, is the schedule reminders always had. Statements can go out each
    // month, and billing emails can carry their PDF.
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_invoice_reminder_days` varchar(200) NOT NULL DEFAULT '1,30' AFTER `config_send_invoice_reminders`,
        ADD COLUMN `config_invoice_reminder_repeat` int(11) NOT NULL DEFAULT 30 AFTER `config_invoice_reminder_days`,
        ADD COLUMN `config_statement_monthly` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_invoice_reminder_repeat`,
        ADD COLUMN `config_statement_day` tinyint(2) NOT NULL DEFAULT 1 AFTER `config_statement_monthly`,
        ADD COLUMN `config_billing_email_attach_pdf` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_statement_day`");

    // A client can be left out of reminders and statements
    mysqli_query($mysqli, "ALTER TABLE `clients`
        ADD COLUMN `client_reminders` tinyint(1) NOT NULL DEFAULT 1 AFTER `client_net_terms`");
