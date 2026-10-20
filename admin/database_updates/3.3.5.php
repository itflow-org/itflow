<?php

/*
 * ITFlow - Database update to version 3.3.5 (from 3.3.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Who should have a vendor from a vendor template (functions/vendor_template.php).
    //
    // 1. No one in particular (0), every client (1), or the clients with one of
    //    its client tags (2). Checked first, so the step can run again.
    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendor_templates' AND COLUMN_NAME = 'vendor_template_required' LIMIT 1");
    if (!$itflow_column || mysqli_num_rows($itflow_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `vendor_templates` ADD `vendor_template_required` tinyint(1) NOT NULL DEFAULT 0 AFTER `vendor_template_notes`");
    }

    // 2. ...and those tags.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `vendor_template_required_tags` (
        `vendor_template_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`vendor_template_id`, `tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `vendor_template_required_tags_ibfk_1` FOREIGN KEY (`vendor_template_id`) REFERENCES `vendor_templates` (`vendor_template_id`) ON DELETE CASCADE,
        CONSTRAINT `vendor_template_required_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // 3. A client's vendors are looked up by the template they came from.
    $itflow_index = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendors' AND INDEX_NAME = 'vendor_template_id' LIMIT 1");
    if (!$itflow_index || mysqli_num_rows($itflow_index) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `vendors` ADD KEY `vendor_template_id` (`vendor_template_id`, `vendor_client_id`)");
    }

    unset($itflow_column, $itflow_index);
