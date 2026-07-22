<?php

/*
 * ITFlow - Database update to version 2.3.2 (from 2.3.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Delete all Recurring Payments that are Stripe
    mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_method = 'Stripe'");

    // Delete Stripe Specific ITFlow Client Stripe Client Relationship Table
    mysqli_query($mysqli, "DROP TABLE client_stripe");

    // Delete Unused Stripe and AI Settings now in their own tables
    mysqli_query($mysqli, "ALTER TABLE `settings`
        DROP `config_stripe_enable`,
        DROP `config_stripe_publishable`,
        DROP `config_stripe_secret`,
        DROP `config_stripe_account`,
        DROP `config_stripe_expense_vendor`,
        DROP `config_stripe_expense_category`,
        DROP `config_stripe_percentage_fee`,
        DROP `config_stripe_flat_fee`,
        DROP `config_ai_enable`,
        DROP `config_ai_provider`,
        DROP `config_ai_model`,
        DROP `config_ai_url`,
        DROP `config_ai_api_key`
    ");
