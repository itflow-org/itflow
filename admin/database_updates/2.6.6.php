<?php

/*
 * ITFlow - Database update to version 2.6.6 (from 2.6.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // config_invoice_overdue_reminders never did anything. Both setup paths seeded it with
    // '1,3,7' and two files read it, but the only line that would have used it was commented
    // out in the nightly job - the reminder schedule is a hardcoded array. The read in
    // includes/load_global_settings.php also ran intval() over a comma-separated string, so
    // the global was 1 whatever the column said.
    //
    // Nothing in the app has ever written to it either, so no install has a value worth
    // keeping and there is nothing to migrate anywhere.

    mysqli_query($mysqli, "ALTER TABLE `settings` DROP COLUMN IF EXISTS `config_invoice_overdue_reminders`");
