<?php

/*
 * ITFlow - Database update to version 3.0.4 (from 3.0.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Trips (functions/trips.php): driven in the agent's own vehicle, and the
    // mileage expense that pays them back for it; billable to the client, and
    // the invoice and line it went on
    mysqli_query($mysqli, "ALTER TABLE `trips`
        ADD COLUMN `trip_own_vehicle` tinyint(1) NOT NULL DEFAULT 0 AFTER `round_trip`,
        ADD COLUMN `trip_expense_id` int(11) NOT NULL DEFAULT 0 AFTER `trip_own_vehicle`,
        ADD COLUMN `trip_billable` tinyint(1) NOT NULL DEFAULT 0 AFTER `trip_expense_id`,
        ADD COLUMN `trip_invoice_id` int(11) NOT NULL DEFAULT 0 AFTER `trip_billable`,
        ADD COLUMN `trip_invoice_item_id` int(11) NOT NULL DEFAULT 0 AFTER `trip_invoice_id`,
        ADD KEY `trip_expense_id` (`trip_expense_id`),
        ADD KEY `trip_invoice_id` (`trip_invoice_id`)");

    // Miles or kilometres, what an agent is paid back for each in their own
    // vehicle and the expense category and vendor that goes under, and what
    // a trip bills a client: a charge for the trip, and a rate for the
    // distance beyond what the charge covers
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_mileage_unit` varchar(2) NOT NULL DEFAULT 'mi' AFTER `config_expense_markup`,
        ADD COLUMN `config_mileage_rate` decimal(10,4) NOT NULL DEFAULT 0.0000 AFTER `config_mileage_unit`,
        ADD COLUMN `config_mileage_category_id` int(11) NOT NULL DEFAULT 0 AFTER `config_mileage_rate`,
        ADD COLUMN `config_mileage_vendor_id` int(11) NOT NULL DEFAULT 0 AFTER `config_mileage_category_id`,
        ADD COLUMN `config_trip_charge` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `config_mileage_vendor_id`,
        ADD COLUMN `config_trip_bill_rate` decimal(10,4) NOT NULL DEFAULT 0.0000 AFTER `config_trip_charge`,
        ADD COLUMN `config_trip_included` decimal(10,1) NOT NULL DEFAULT 0.0 AFTER `config_trip_bill_rate`,
        ADD COLUMN `config_trip_item_name` varchar(200) NOT NULL DEFAULT 'Travel' AFTER `config_trip_included`");

    // Mileage goes under an expense category called Mileage, or Travel, when there is one
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT category_id FROM categories
        WHERE category_type = 'Expense' AND category_archived_at IS NULL AND category_name IN ('Mileage', 'Travel')
        ORDER BY category_name = 'Mileage' DESC, category_id ASC LIMIT 1"));
    if ($row) {
        mysqli_query($mysqli, "UPDATE settings SET config_mileage_category_id = " . intval($row[0]));
    }
