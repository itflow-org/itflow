<?php

/*
 * ITFlow - Database update to version 3.6.3 (from 3.6.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * contract_templates.company_id goes. 2.3.7 first shipped (v25.11) creating contract_templates
 * with `company_id` INT NOT NULL and no default; the migration was then changed to leave it out,
 * but nothing dropped it from the installs that had already run it. Those still have it: the
 * debug page lists it as an extra column, and in strict SQL mode - MariaDB's default - adding a
 * contract template failed with "Field 'company_id' doesn't have a default value". Nothing ever
 * read or wrote it. Installs set up or updated after v25.11 never had it.
 *
 * Re-runnable: it only drops the column where it is.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_extra_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates' AND COLUMN_NAME = 'company_id' LIMIT 1");

    if ($itflow_extra_column && mysqli_num_rows($itflow_extra_column) > 0) {
        mysqli_query($mysqli, "ALTER TABLE `contract_templates` DROP `company_id`");
    }
