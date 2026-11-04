<?php

/*
 * ITFlow - Payment provider fees job
 *
 * Records what Stripe charged on each recent card payment as an expense, once Stripe has
 * settled it - paymentProviderRecordFees() in functions/payment_provider.php, the same thing
 * Record fees now on Admin > Payment Providers does. A card payment usually settles within
 * seconds, so running this every few minutes has its fee on the books shortly after the
 * payment instead of the next morning. One that hasn't settled yet waits for a later run.
 *
 * Safe on an interval: a payment whose fee is recorded is never looked at again, and the
 * recorder holds a lock so this and Record fees now can't both record the same fee. With
 * nothing waiting, a run is one query and no call to Stripe.
 */

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Prevent overlapping runs of this script
$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$payment_provider_fees_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

if (intval($payment_provider_fees_settings['config_enable_cron'] ?? 0) == 0) {
    cronJobStop("Cron: is not enabled\n");
}

$payment_provider_fees_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_name, payment_provider_private_key, payment_provider_account,
    payment_provider_expense_vendor, payment_provider_expense_category FROM payment_providers WHERE payment_provider_name = 'Stripe' LIMIT 1"));

if (!$payment_provider_fees_provider) {
    cronJobStop("No Stripe payment provider\n");
}

$payment_provider_fees_done = paymentProviderRecordFees($payment_provider_fees_provider);

if ($payment_provider_fees_done['busy']) {
    echo "Fees are being recorded already - left to that run\n";
} else {
    echo $payment_provider_fees_done['recorded'] . " fee(s) recorded, " . $payment_provider_fees_done['waiting'] . " waiting for Stripe to settle, " . $payment_provider_fees_done['failed'] . " failed\n";
}
