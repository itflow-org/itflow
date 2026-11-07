<?php

/*
 * ITFlow - Database update to version 3.6.2 (from 3.6.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * Cron is on for new installs: settings.config_enable_cron defaults to 1, so a new install
 * starts running its jobs once its crontab entry is in. Only the column's default changes -
 * this install keeps the setting it has, on or off, as turning it on here could start late
 * fees, overdue reminders and autopay retries on an install that has them stopped.
 *
 * Re-runnable: setting a default it already has changes nothing.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `settings` ALTER `config_enable_cron` SET DEFAULT 1");
