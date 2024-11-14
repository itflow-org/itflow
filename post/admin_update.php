<?php

if (isset($_GET['update'])) {

    validateAdminRole(); // Old function

    //git fetch downloads the latest from remote without trying to merge or rebase anything. Then the git reset resets the master branch to what you just fetched. The --hard option changes all the files in your working tree to match the files in origin/master

    if (isset($_GET['force_update']) == 1) {
        exec("git fetch --all");
        exec("git reset --hard origin/master");
    } else {
        exec("git pull");
    }
    //header("Location: post.php?update_db");


    // Send Telemetry if enabled during update
    if ($config_telemetry > 0 OR $config_telemetry = 2) {

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);

        $company_name = sanitizeInput($row['company_name']);
        $website = sanitizeInput($row['company_website']);
        $city = sanitizeInput($row['company_city']);
        $state = sanitizeInput($row['company_state']);
        $country = sanitizeInput($row['company_country']);
        $currency = sanitizeInput($row['company_currency']);
        $current_version = exec("git rev-parse HEAD");

        // Client Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS num FROM clients"));
        $client_count = $row['num'];

        // Ticket Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM tickets"));
        $ticket_count = $row['num'];

        // Scheduled Ticket Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('scheduled_ticket_id') AS num FROM scheduled_tickets"));
        $scheduled_ticket_count = $row['num'];

        // Calendar Event Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('event_id') AS num FROM events"));
        $calendar_event_count = $row['num'];

        // Quote Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes"));
        $quote_count = $row['num'];

        // Invoice Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices"));
        $invoice_count = $row['num'];

        // Revenue Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('revenue_id') AS num FROM revenues"));
        $revenue_count = $row['num'];

        // Recurring Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM recurring"));
        $recurring_count = $row['num'];

        // Account Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('account_id') AS num FROM accounts"));
        $account_count = $row['num'];

        // Tax Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tax_id') AS num FROM taxes"));
        $tax_count = $row['num'];

        // Product Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('product_id') AS num FROM products"));
        $product_count = $row['num'];

        // Payment Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments WHERE payment_invoice_id > 0"));
        $payment_count = $row['num'];

        // Company Vendor Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id = 0"));
        $company_vendor_count = $row['num'];

        // Expense Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('expense_id') AS num FROM expenses WHERE expense_vendor_id > 0"));
        $expense_count = $row['num'];

        // Trip Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('trip_id') AS num FROM trips"));
        $trip_count = $row['num'];

        // Transfer Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('transfer_id') AS num FROM transfers"));
        $transfer_count = $row['num'];

        // Contact Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts"));
        $contact_count = $row['num'];

        // Location Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations"));
        $location_count = $row['num'];

        // Asset Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets"));
        $asset_count = $row['num'];

        // Software Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 0"));
        $software_count = $row['num'];

        // Software Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 1"));
        $software_template_count = $row['num'];

        // Password Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins"));
        $password_count = $row['num'];

        // Network Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks"));
        $network_count = $row['num'];

        // Certificate Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('certificate_id') AS num FROM certificates"));
        $certificate_count = $row['num'];

        // Domain Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains"));
        $domain_count = $row['num'];

        // Service Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('service_id') AS num FROM services"));
        $service_count = $row['num'];

        // Client Vendor Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id > 0"));
        $client_vendor_count = $row['num'];

        // Vendor Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 1"));
        $vendor_template_count = $row['num'];

        // File Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('file_id') AS num FROM files"));
        $file_count = $row['num'];

        // Document Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 0"));
        $document_count = $row['num'];

        // Document Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 1"));
        $document_template_count = $row['num'];

        // Shared Item Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('item_id') AS num FROM shared_items"));
        $shared_item_count = $row['num'];

        // Company Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('company_id') AS num FROM companies"));
        $company_count = $row['num'];

        // User Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('user_id') AS num FROM users"));
        $user_count = $row['num'];

        // Category Expense Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Expense'"));
        $category_expense_count = $row['num'];

        // Category Income Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Income'"));
        $category_income_count = $row['num'];

        // Category Referral Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Referral'"));
        $category_referral_count = $row['num'];

        // Category Payment Method Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Payment Method'"));
        $category_payment_method_count = $row['num'];

        // Tag Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tag_id') AS num FROM tags"));
        $tag_count = $row['num'];

        // API Key Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('api_key_id') AS num FROM api_keys"));
        $api_key_count = $row['num'];

        // Log Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('log_id') AS num FROM logs"));
        $log_count = $row['num'];

        $postdata = http_build_query(
            array(
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
            )
        );

        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents('https://telemetry.itflow.org', false, $context);

    }

    // Logging
    logAction("App", "Update", "$session_name ran updates");

    $_SESSION['alert_message'] = "Update successful";

    sleep(1);

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['update_db'])) {

    validateAdminRole(); // Old function

    // Get the current version
    require_once ('database_version.php');

    // Perform upgrades, if required
    require_once ('database_updates.php');

    // Logging
    logAction("Database", "Update", "$session_name updated the database structure");

    $_SESSION['alert_message'] = "Database structure update successful";

    sleep(1);

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
