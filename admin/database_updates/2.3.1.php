<?php

/*
 * ITFlow - Database update to version 2.3.1 (from 2.3.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Migrate Payment Methods from Categories Table to new payment_methods table
    $sql_categories = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Payment Method' AND category_name != 'Stripe' AND category_archived_at IS NULL");

    while ($row = mysqli_fetch_assoc($sql_categories)) {
        $category_name = escapeSql($row['category_name']);

        mysqli_query($mysqli,"INSERT INTO payment_methods SET payment_method_name = '$category_name'");
    }
