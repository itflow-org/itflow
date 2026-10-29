<?php

/*
 * ITFlow - Database update to version 3.3.6 (from 3.3.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Where a vendor's service status is posted and where its cases are opened,
    // on vendors and the templates they are made from, and the account
    // manager's line and email on templates as vendors have them. Each column
    // is checked first, so the step can run again.
    $itflow_columns = [
        ['vendors', 'vendor_status_page', "varchar(200) DEFAULT NULL AFTER `vendor_website`"],
        ['vendors', 'vendor_portal', "varchar(200) DEFAULT NULL AFTER `vendor_status_page`"],
        ['vendor_templates', 'vendor_template_contact_phone_country_code', "varchar(10) DEFAULT NULL AFTER `vendor_template_contact_name`"],
        ['vendor_templates', 'vendor_template_contact_phone', "varchar(200) DEFAULT NULL AFTER `vendor_template_contact_phone_country_code`"],
        ['vendor_templates', 'vendor_template_contact_email', "varchar(200) DEFAULT NULL AFTER `vendor_template_contact_phone`"],
        ['vendor_templates', 'vendor_template_status_page', "varchar(200) DEFAULT NULL AFTER `vendor_template_website`"],
        ['vendor_templates', 'vendor_template_portal', "varchar(200) DEFAULT NULL AFTER `vendor_template_status_page`"],
    ];
    foreach ($itflow_columns as [$itflow_table, $itflow_column, $itflow_definition]) {
        $itflow_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$itflow_table' AND COLUMN_NAME = '$itflow_column' LIMIT 1");
        if (!$itflow_exists || mysqli_num_rows($itflow_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `$itflow_table` ADD `$itflow_column` $itflow_definition");
        }
    }

    unset($itflow_columns, $itflow_table, $itflow_column, $itflow_definition, $itflow_exists);
