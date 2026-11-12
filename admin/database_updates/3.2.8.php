<?php

/*
 * ITFlow - Database update to version 3.2.8 (from 3.2.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Changed after it shipped, in what it can get through and not in what it does. As
    // first written, the last statement compared a license's name with a template's, and on
    // an install where software_templates had another collation than software (MariaDB 11.5
    // and later gave it one - see 3.6.5) that stopped with "Illegal mix of collations". By
    // then the columns above it were in, so every run after stopped at the first line with
    // "Duplicate column name 'software_template_id'" and the install could go no further.
    // Each step is now checked first, so the update can run again from wherever it was
    // left, and the comparison names its collation. An install that ran it through has
    // exactly what this makes.

    // The license template a license was made from - 0 for none
    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'software' AND COLUMN_NAME = 'software_template_id' LIMIT 1");
    if (!$itflow_column || mysqli_num_rows($itflow_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `software`
            ADD `software_template_id` int(11) NOT NULL DEFAULT 0 AFTER `software_client_id`,
            ADD KEY `software_template_id` (`software_template_id`)");
    }

    // Who made a license template and who last changed it
    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'software_templates' AND COLUMN_NAME = 'software_template_created_by' LIMIT 1");
    if (!$itflow_column || mysqli_num_rows($itflow_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `software_templates`
            ADD `software_template_created_by` int(11) NOT NULL DEFAULT 0 AFTER `software_template_archived_at`,
            ADD `software_template_updated_by` int(11) NOT NULL DEFAULT 0 AFTER `software_template_created_by`");
    }

    // A license called exactly what one template - and only one - is called was made from it,
    // as the New License form filled the name in from the template. It isn't counted as a change.
    // Only licenses not yet given one, so a second run leaves alone what the first one set.
    mysqli_query($mysqli, "UPDATE `software` JOIN (
            SELECT MIN(`software_template_id`) AS `template_id`, LOWER(TRIM(`software_template_name`)) AS `template_name`
            FROM `software_templates` GROUP BY LOWER(TRIM(`software_template_name`)) HAVING COUNT(*) = 1
        ) AS `templates` ON CONVERT(LOWER(TRIM(`software`.`software_name`)) USING utf8mb4) COLLATE utf8mb4_general_ci = `templates`.`template_name`
        SET `software`.`software_template_id` = `templates`.`template_id`, `software`.`software_updated_at` = `software`.`software_updated_at`
        WHERE `software`.`software_template_id` = 0");

    unset($itflow_column);
