<?php

/*
 * ITFlow - Database update to version 2.4.3 (from 2.4.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `categories` ADD `category_description` VARCHAR(255) DEFAULT NULL AFTER `category_name`");
    mysqli_query($mysqli, "ALTER TABLE `categories` ADD `category_order` INT(11) NOT NULL DEFAULT 0 AFTER `category_icon`");

    // Create network_interfaces
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Ethernet', category_type = 'network_interface', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'SFP', category_type = 'network_interface', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'SFP+', category_type = 'network_interface', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'QSFP28', category_type = 'network_interface', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'QSFP-DD', category_type = 'network_interface', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Coaxial', category_type = 'network_interface', category_order = 6"); // 6
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Fiber', category_type = 'network_interface', category_order = 7"); // 7
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'WiFi', category_type = 'network_interface', category_order = 8"); // 8
