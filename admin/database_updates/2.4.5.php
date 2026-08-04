<?php

/*
 * ITFlow - Database update to version 2.4.5 (from 2.4.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Gateway fee expense now uses the actual fee from Stripe's balance transaction
    mysqli_query($mysqli, "ALTER TABLE `payment_providers` DROP `payment_provider_expense_percentage_fee`, DROP `payment_provider_expense_flat_fee`");
