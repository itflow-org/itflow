<?php

/*
 * ITFlow - Database update to version 3.2.9 (from 3.2.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // What else a license made from a template starts with: its seats (blank for no limit), how
    // often it renews (1m, 3m, 6m, 1y, 2y or 3y - blank for not at all), the vendor template its
    // vendor comes from, and the product it is sold as
    mysqli_query($mysqli, "ALTER TABLE `software_templates`
        ADD `software_template_seats` int(11) DEFAULT NULL AFTER `software_template_notes`,
        ADD `software_template_term` varchar(10) DEFAULT NULL AFTER `software_template_seats`,
        ADD `software_template_vendor_template_id` int(11) NOT NULL DEFAULT 0 AFTER `software_template_term`,
        ADD `software_template_product_id` int(11) NOT NULL DEFAULT 0 AFTER `software_template_vendor_template_id`");
