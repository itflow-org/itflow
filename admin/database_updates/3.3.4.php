<?php

/*
 * ITFlow - Database update to version 3.3.4 (from 3.3.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Vendor templates (functions/vendor_template.php).
    //
    // 1. Who made a vendor template and who last changed it. Checked first, so
    //    the step can run again.
    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendor_templates' AND COLUMN_NAME = 'vendor_template_created_by' LIMIT 1");
    if (!$itflow_column || mysqli_num_rows($itflow_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `vendor_templates`
            ADD `vendor_template_created_by` int(11) NOT NULL DEFAULT 0 AFTER `vendor_template_archived_at`,
            ADD `vendor_template_updated_by` int(11) NOT NULL DEFAULT 0 AFTER `vendor_template_created_by`");
    }

    // 2. ...taken from the audit log where it has a record of it - the first
    //    Create on the template and the latest Edit. Not counted as a change.
    mysqli_query($mysqli, "UPDATE `vendor_templates` JOIN (
            SELECT `log_entity_id`, SUBSTRING_INDEX(GROUP_CONCAT(`log_user_id` ORDER BY `log_id` ASC), ',', 1) AS `user_id`
            FROM `logs` WHERE `log_type` = 'Vendor Template' AND `log_action` = 'Create' AND `log_entity_id` > 0 AND `log_user_id` > 0 GROUP BY `log_entity_id`
        ) AS `made` ON `made`.`log_entity_id` = `vendor_templates`.`vendor_template_id`
        SET `vendor_template_created_by` = `made`.`user_id`, `vendor_template_updated_at` = `vendor_template_updated_at`
        WHERE `vendor_template_created_by` = 0");
    mysqli_query($mysqli, "UPDATE `vendor_templates` JOIN (
            SELECT `log_entity_id`, SUBSTRING_INDEX(GROUP_CONCAT(`log_user_id` ORDER BY `log_id` DESC), ',', 1) AS `user_id`
            FROM `logs` WHERE `log_type` = 'Vendor Template' AND `log_action` = 'Edit' AND `log_entity_id` > 0 AND `log_user_id` > 0 GROUP BY `log_entity_id`
        ) AS `changed` ON `changed`.`log_entity_id` = `vendor_templates`.`vendor_template_id`
        SET `vendor_template_updated_by` = `changed`.`user_id`, `vendor_template_updated_at` = `vendor_template_updated_at`
        WHERE `vendor_template_updated_by` = 0 AND `vendor_template_updated_at` IS NOT NULL");

    unset($itflow_column);
