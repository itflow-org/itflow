<?php

/*
 * ITFlow - Database update to version 2.4.9 (from 2.4.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Refunds are recorded as negative rows in payments so that every existing
    // SUM(payment_amount) balance calculation picks them up for free. This column
    // links a refund row back to the payment it reverses, which is what caps the
    // refundable amount and lets the UI hide the button once a payment is fully
    // refunded. NULL on every ordinary payment.
    mysqli_query($mysqli, "ALTER TABLE `payments` ADD COLUMN `payment_refund_of_id` INT(11) DEFAULT NULL");
    mysqli_query($mysqli, "ALTER TABLE `payments` ADD INDEX `payment_refund_of_id` (`payment_refund_of_id`)");
