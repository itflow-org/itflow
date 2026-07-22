<?php

/*
 * ITFlow - Database update to version 2.3.0 (from 2.2.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Migrate Stripe Settings over to new Tables

    // Get Current Stripe Settings
    $sql_stripe_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql_stripe_settings);
    $config_stripe_enable = intval($row['config_stripe_enable']);
    if ($config_stripe_enable === 1) {
        $config_stripe_publishable = mysqli_real_escape_string($mysqli, $row['config_stripe_publishable']);
        $config_stripe_secret      = mysqli_real_escape_string($mysqli, $row['config_stripe_secret']);
        $config_stripe_account     = intval($row['config_stripe_account']);
        $config_stripe_expense_vendor   = intval($row['config_stripe_expense_vendor']);
        $config_stripe_expense_category = intval($row['config_stripe_expense_category']);
        $config_stripe_percentage_fee   = floatval($row['config_stripe_percentage_fee']);
        $config_stripe_flat_fee         = floatval($row['config_stripe_flat_fee']);

        mysqli_query($mysqli,"INSERT INTO payment_providers SET
            payment_provider_name = 'Stripe',
            payment_provider_public_key = '$config_stripe_publishable',
            payment_provider_private_key = '$config_stripe_secret',
            payment_provider_account = $config_stripe_account,
            payment_provider_expense_vendor = $config_stripe_expense_vendor,
            payment_provider_expense_category = $config_stripe_expense_category,
            payment_provider_expense_percentage_fee = $config_stripe_percentage_fee,
            payment_provider_expense_flat_fee = $config_stripe_flat_fee"
        );

        $provider_id = mysqli_insert_id($mysqli);

        // Migrate Clients and Payment Method over
        $sql_stripe_clients = mysqli_query($mysqli, "SELECT * FROM client_stripe WHERE stripe_pm IS NOT NULL AND stripe_pm != ''");
        while ($row = mysqli_fetch_assoc($sql_stripe_clients)) {
            $client_id = intval($row['client_id']);
            $stripe_id = mysqli_real_escape_string($mysqli, $row['stripe_id']);
            $stripe_pm = mysqli_real_escape_string($mysqli, $row['stripe_pm']);
            $stripe_pm_details = mysqli_real_escape_string($mysqli, $row['stripe_pm_details'] ?? 'Saved Card');

            mysqli_query($mysqli,"INSERT INTO client_payment_provider SET
                client_id = $client_id,
                payment_provider_id = $provider_id,
                payment_provider_client = '$stripe_id'"
            );

            mysqli_query($mysqli,"INSERT INTO client_saved_payment_methods SET
                saved_payment_provider_method = '$stripe_pm',
                saved_payment_description = '$stripe_pm_details',
                saved_payment_client_id = $client_id,
                saved_payment_provider_id = $provider_id"
            );
        }
    }

    // Get Stripe provider id
    $res = mysqli_query($mysqli, "
        SELECT payment_provider_id
        FROM payment_providers
        WHERE payment_provider_name = 'Stripe'
        ORDER BY payment_provider_id DESC
        LIMIT 1
    ");
    $stripe = mysqli_fetch_assoc($res);
    $stripe_provider_id = intval($stripe['payment_provider_id']);

    // Correct mapping: RP -> Recurring Invoice -> Client -> Client's Stripe saved method
    mysqli_query($mysqli, "
        UPDATE recurring_payments rp
        INNER JOIN recurring_invoices ri
            ON ri.recurring_invoice_id = rp.recurring_payment_recurring_invoice_id
        INNER JOIN client_saved_payment_methods spm
            ON spm.saved_payment_client_id = ri.recurring_invoice_client_id
           AND spm.saved_payment_provider_id = $stripe_provider_id
        SET
            rp.recurring_payment_method = 'Credit Card',
            rp.recurring_payment_saved_payment_id = spm.saved_payment_id
        WHERE rp.recurring_payment_method = 'Stripe'
    ");
