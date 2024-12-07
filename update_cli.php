#!/usr/bin/env php
<?php

// Ensure script is run only from the CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once 'config.php';
require_once "functions.php";

// Parse command-line options
$options = getopt('', ['update', 'force_update', 'update_db']);

// If "update" is requested
if (isset($options['update'])) {

    // If "force_update" is requested, do a hard reset, otherwise just pull
    if (isset($options['force_update'])) {
        exec("sudo -u www-data git fetch --all 2>&1", $output, $return_var);
        exec("sudo -u www-data git reset --hard origin/master 2>&1", $output2, $return_var2);
        echo implode("\n", $output) . "\n" . implode("\n", $output2) . "\n";
    } else {
        exec("sudo -u www-data git pull 2>&1", $output, $return_var);
        echo implode("\n", $output) . "\n";
    }

    // If telemetry is enabled
    if ($config_telemetry > 0 || $config_telemetry == 2) {
        $sql = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        
        $company_name = sanitizeInput($row['company_name']);
        $website = sanitizeInput($row['company_website']);
        $city = sanitizeInput($row['company_city']);
        $state = sanitizeInput($row['company_state']);
        $country = sanitizeInput($row['company_country']);
        $currency = sanitizeInput($row['company_currency']);
        $current_version = exec("git rev-parse HEAD");

        $client_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS num FROM clients"))['num'];
        $ticket_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM tickets"))['num'];
        $scheduled_ticket_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('scheduled_ticket_id') AS num FROM scheduled_tickets"))['num'];
        $calendar_event_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('event_id') AS num FROM events"))['num'];
        $quote_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes"))['num'];
        $invoice_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices"))['num'];
        $revenue_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('revenue_id') AS num FROM revenues"))['num'];
        $recurring_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM recurring"))['num'];
        $account_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('account_id') AS num FROM accounts"))['num'];
        $tax_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tax_id') AS num FROM taxes"))['num'];
        $product_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('product_id') AS num FROM products"))['num'];
        $payment_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments WHERE payment_invoice_id > 0"))['num'];
        $company_vendor_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id = 0"))['num'];
        $expense_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('expense_id') AS num FROM expenses WHERE expense_vendor_id > 0"))['num'];
        $trip_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('trip_id') AS num FROM trips"))['num'];
        $transfer_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('transfer_id') AS num FROM transfers"))['num'];
        $contact_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts"))['num'];
        $location_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations"))['num'];
        $asset_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets"))['num'];
        $software_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 0"))['num'];
        $software_template_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 1"))['num'];
        $password_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins"))['num'];
        $network_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks"))['num'];
        $certificate_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('certificate_id') AS num FROM certificates"))['num'];
        $domain_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains"))['num'];
        $service_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('service_id') AS num FROM services"))['num'];
        $client_vendor_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id > 0"))['num'];
        $vendor_template_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 1"))['num'];
        $file_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('file_id') AS num FROM files"))['num'];
        $document_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 0"))['num'];
        $document_template_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 1"))['num'];
        $shared_item_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('item_id') AS num FROM shared_items"))['num'];
        $company_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('company_id') AS num FROM companies"))['num'];
        $user_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('user_id') AS num FROM users"))['num'];
        $category_expense_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Expense'"))['num'];
        $category_income_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Income'"))['num'];
        $category_referral_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Referral'"))['num'];
        $category_payment_method_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Payment Method'"))['num'];
        $tag_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tag_id') AS num FROM tags"))['num'];
        $api_key_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('api_key_id') AS num FROM api_keys"))['num'];
        $log_count = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('log_id') AS num FROM logs"))['num'];

        $postdata = http_build_query(array(
            'installation_id' => "$installation_id",
            'version' => "$current_version",
            'company_name' => "$company_name",
            'website' => "$website",
            'city' => "$city",
            'state' => "$state",
            'country' => "$country",
            'currency' => "$currency",
            'comments' => "$comments",
            'client_count' => $client_count,
            'ticket_count' => $ticket_count,
            'scheduled_ticket_count' => $scheduled_ticket_count,
            'calendar_event_count' => $calendar_event_count,
            'quote_count' => $quote_count,
            'invoice_count' => $invoice_count,
            'revenue_count' => $revenue_count,
            'recurring_count' => $recurring_count,
            'account_count' => $account_count,
            'tax_count' => $tax_count,
            'product_count' => $product_count,
            'payment_count' => $payment_count,
            'company_vendor_count' => $company_vendor_count,
            'expense_count' => $expense_count,
            'trip_count' => $trip_count,
            'transfer_count' => $transfer_count,
            'contact_count' => $contact_count,
            'location_count' => $location_count,
            'asset_count' => $asset_count,
            'software_count' => $software_count,
            'software_template_count' => $software_template_count,
            'password_count' => $password_count,
            'network_count' => $network_count,
            'certificate_count' => $certificate_count,
            'domain_count' => $domain_count,
            'service_count' => $service_count,
            'client_vendor_count' => $client_vendor_count,
            'vendor_template_count' => $vendor_template_count,
            'file_count' => $file_count,
            'document_count' => $document_count,
            'document_template_count' => $document_template_count,
            'shared_item_count' => $shared_item_count,
            'company_count' => $company_count,
            'user_count' => $user_count,
            'category_expense_count' => $category_expense_count,
            'category_income_count' => $category_income_count,
            'category_referral_count' => $category_referral_count,
            'category_payment_method_count' => $category_payment_method_count,
            'tag_count' => $tag_count,
            'api_key_count' => $api_key_count,
            'log_count' => $log_count,
            'config_theme' => "$config_theme",
            'config_enable_cron' => $config_enable_cron,
            'config_ticket_email_parse' => $config_ticket_email_parse,
            'config_module_enable_itdoc' => $config_module_enable_itdoc,
            'config_module_enable_ticketing' => $config_module_enable_ticketing,
            'config_module_enable_accounting' => $config_module_enable_accounting,
            'config_telemetry' => $config_telemetry,
            'collection_method' => 4
        ));

        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents('https://telemetry.itflow.org', false, $context);
        echo "Telemetry sent: $result\n";
    }

    echo "Update successful\n";
}

// If "update_db" is requested
if (isset($options['update_db'])) {
    require_once('database_version.php');
    $old_db_version = $db_version; // Store the old version before updates
    require_once('database_updates.php');
    $new_db_version = $db_version; // The database_updates.php should update $db_version as it applies changes
    
    echo "Database updated from version $old_db_version to $new_db_version.\n";
    echo "The latest database version is $new_db_version.\n";
}
