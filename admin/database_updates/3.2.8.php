<?php

/*
 * ITFlow - Database update to version 3.2.8 (from 3.2.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The license template a license was made from - 0 for none
    mysqli_query($mysqli, "ALTER TABLE `software`
        ADD `software_template_id` int(11) NOT NULL DEFAULT 0 AFTER `software_client_id`,
        ADD KEY `software_template_id` (`software_template_id`)");

    // Who made a license template and who last changed it
    mysqli_query($mysqli, "ALTER TABLE `software_templates`
        ADD `software_template_created_by` int(11) NOT NULL DEFAULT 0 AFTER `software_template_archived_at`,
        ADD `software_template_updated_by` int(11) NOT NULL DEFAULT 0 AFTER `software_template_created_by`");

    // A license called exactly what one template - and only one - is called was made from it,
    // as the New License form filled the name in from the template. It isn't counted as a change.
    mysqli_query($mysqli, "UPDATE `software` JOIN (
            SELECT MIN(`software_template_id`) AS `template_id`, LOWER(TRIM(`software_template_name`)) AS `template_name`
            FROM `software_templates` GROUP BY LOWER(TRIM(`software_template_name`)) HAVING COUNT(*) = 1
        ) AS `templates` ON LOWER(TRIM(`software`.`software_name`)) = `templates`.`template_name`
        SET `software`.`software_template_id` = `templates`.`template_id`, `software`.`software_updated_at` = `software`.`software_updated_at`");
