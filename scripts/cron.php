<?php

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$sql_companies = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");

$row = mysqli_fetch_array($sql_companies);

// Company Details
$company_name = sanitizeInput($row['company_name']);
$company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
$company_email = sanitizeInput($row['company_email']);
$company_website = sanitizeInput($row['company_website']);
$company_city = sanitizeInput($row['company_city']);
$company_state = sanitizeInput($row['company_state']);
$company_country = sanitizeInput($row['company_country']);
$company_locale = sanitizeInput($row['company_locale']);
$company_currency = sanitizeInput($row['company_currency']);

// Company Settings
$config_enable_cron = intval($row['config_enable_cron']);
$config_invoice_overdue_reminders = $row['config_invoice_overdue_reminders'];
$config_invoice_prefix = sanitizeInput($row['config_invoice_prefix']);
$config_invoice_from_email = sanitizeInput($row['config_invoice_from_email']);
$config_invoice_from_name = sanitizeInput($row['config_invoice_from_name']);
$config_invoice_late_fee_enable = intval($row['config_invoice_late_fee_enable']);
$config_invoice_late_fee_percent = floatval($row['config_invoice_late_fee_percent']);

// Mail Settings
$config_smtp_host = $row['config_smtp_host'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_smtp_port = intval($row['config_smtp_port']);
$config_smtp_encryption = $row['config_smtp_encryption'];
$config_mail_from_email = sanitizeInput($row['config_mail_from_email']);
$config_mail_from_name = sanitizeInput($row['config_mail_from_name']);
$config_recurring_auto_send_invoice = intval($row['config_recurring_auto_send_invoice']);

// Tickets
$config_ticket_prefix = sanitizeInput($row['config_ticket_prefix']);
$config_ticket_from_name = sanitizeInput($row['config_ticket_from_name']);
$config_ticket_from_email = sanitizeInput($row['config_ticket_from_email']);
$config_ticket_client_general_notifications = intval($row['config_ticket_client_general_notifications']);
$config_ticket_autoclose_hours = intval($row['config_ticket_autoclose_hours']);
$config_ticket_new_ticket_notification_email = sanitizeInput($row['config_ticket_new_ticket_notification_email']);

// Get Config for Telemetry
$config_theme = $row['config_theme'];
$config_ticket_email_parse = intval($row['config_ticket_email_parse']);
$config_module_enable_itdoc = intval($row['config_module_enable_itdoc']);
$config_module_enable_ticketing = intval($row['config_module_enable_ticketing']);
$config_module_enable_accounting = intval($row['config_module_enable_accounting']);
$config_telemetry = intval($row['config_telemetry']);

// Alerts
$config_enable_alert_domain_expire = intval($row['config_enable_alert_domain_expire']);
$config_send_invoice_reminders = intval($row['config_send_invoice_reminders']);

// Remember-me Token Expiry
$config_login_remember_me_expire = intval($row['config_login_remember_me_expire']);

// Log retention
$config_log_retention = intval($row['config_log_retention']);

// Set Currency Format
$currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

// White label
$config_whitelabel_enabled = intval($row['config_whitelabel_enabled']);
$config_whitelabel_key = $row['config_whitelabel_key'];

// Check cron is enabled
if ($config_enable_cron == 0) {
    exit("Cron: is not enabled -- Quitting..");
}

/*
 * ###############################################################################################################
 *  STARTUP ACTIONS
 * ###############################################################################################################
 */

//Logging
logApp("Cron", "info", "Cron Started");

/*
 * ###############################################################################################################
 *  CLEAN UP (OLD) DATA
 * ###############################################################################################################
 */

// Clean-up ticket views table used for collision detection
mysqli_query($mysqli, "TRUNCATE TABLE ticket_views");

// Clean-up shared items that have been used
mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_views = item_view_limit");

// Clean-up shared items that have expired
mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_expire_at < NOW()");

// Invalidate any password reset links
mysqli_query($mysqli, "UPDATE users SET user_password_reset_token = NULL WHERE user_archived_at IS NULL");

// Clean-up old dismissed notifications
mysqli_query($mysqli, "DELETE FROM notifications WHERE notification_dismissed_at < CURDATE() - INTERVAL 90 DAY");

// Clean-up mail queue
mysqli_query($mysqli, "DELETE FROM email_queue WHERE email_queued_at < CURDATE() - INTERVAL 90 DAY");

// Clean-up old remember me tokens
mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_created_at < CURDATE() - INTERVAL $config_login_remember_me_expire DAY");

// Cleanup old audit logs
mysqli_query($mysqli, "DELETE FROM logs WHERE log_created_at < CURDATE() - INTERVAL $config_log_retention DAY");

// Cleanup old app/debug logs
mysqli_query($mysqli, "DELETE FROM app_logs WHERE app_log_created_at < CURDATE() - INTERVAL $config_log_retention DAY");

// Cleanup old auth logs
mysqli_query($mysqli, "DELETE FROM auth_logs WHERE auth_log_created_at < CURDATE() - INTERVAL $config_log_retention DAY");

// CLeanup old domain history
$sql = mysqli_query($mysqli, "SELECT domain_id FROM domains");
while ($row = mysqli_fetch_array($sql)) {
    $domain_id = intval($row['domain_id']);
    mysqli_query($mysqli, "
        DELETE FROM domain_history
        WHERE domain_history_id NOT IN (
            SELECT domain_history_id FROM (
                SELECT domain_history_id FROM domain_history
                WHERE domain_history_domain_id = $domain_id
                ORDER BY domain_history_modified_at DESC
                LIMIT 25
            ) AS recent_entries
        ) AND domain_history_domain_id = $domain_id
    ");
}

// Logging
// logAction("Cron", "Task", "Cron cleaned up old data");

/*
 * ###############################################################################################################
 *  ACTION DATA
 * ###############################################################################################################
 */

// Whitelabel - Disable if expired/invalid
if ($config_whitelabel_enabled && !validateWhitelabelKey($config_whitelabel_key)) {
    mysqli_query($mysqli, "UPDATE settings SET config_whitelabel_enabled = 0, config_whitelabel_key = '' WHERE company_id = 1");
    appNotify("Settings", "White-labelling was disabled due to expired/invalid key", "settings_modules.php");
}


// GET NOTIFICATIONS

// DOMAINS EXPIRING

if ($config_enable_alert_domain_expire == 1) {

    $domainAlertArray = [1,7,45];

    foreach ($domainAlertArray as $day) {

        //Get Domains Expiring
        $sql = mysqli_query(
            $mysqli,
            "SELECT * FROM domains
            LEFT JOIN clients ON domain_client_id = client_id
            WHERE domain_expire IS NOT NULL AND domain_expire = CURDATE() + INTERVAL $day DAY"
        );

        while ($row = mysqli_fetch_array($sql)) {
            $domain_id = intval($row['domain_id']);
            $domain_name = sanitizeInput($row['domain_name']);
            $domain_expire = sanitizeInput($row['domain_expire']);
            $client_id = intval($row['client_id']);
            $client_name = sanitizeInput($row['client_name']);

            appNotify("Domain Expiring", "Domain $domain_name for $client_name will expire in $day Days on $domain_expire", "domains.php?client_id=$client_id", $client_id);

        }

    }
    // Logging
    // logAction("Cron", "Task", "Cron created notifications for domains expiring");
}

// CERTIFICATES EXPIRING

$certificateAlertArray = [1,7,45];

foreach ($certificateAlertArray as $day) {

    //Get Certs Expiring
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM certificates
        LEFT JOIN clients ON certificate_client_id = client_id
        WHERE certificate_expire = CURDATE() + INTERVAL $day DAY"
    );

    while ($row = mysqli_fetch_array($sql)) {
        $certificate_id = intval($row['certificate_id']);
        $certificate_name = sanitizeInput($row['certificate_name']);
        $certificate_domain = sanitizeInput($row['certificate_domain']);
        $certificate_expire = sanitizeInput($row['certificate_expire']);
        $certificate_public_key = $row['certificate_public_key']; // Sanitize input breaks parsing
        $client_id = intval($row['client_id']);
        $client_name = sanitizeInput($row['client_name']);

        // Calculate the validity period
        if (!empty($certificate_public_key)) {
            $cert_public_key_obj = openssl_x509_parse($certificate_public_key);
            $validity_days = intval(round(($cert_public_key_obj['validTo_time_t'] - $cert_public_key_obj['validFrom_time_t']) / (60 * 60 * 24)));

            // Only raise a notification at 45 days if the certificate is valid for more than 90 days (i.e. not a LE)

            if ($day == 45 && $validity_days < 91) {
                // LE certificate - Do nothing here
                echo "Not raising notification for LE certificate $certificate_name expiring in 45 days";

            } else {
                // This certificate is either expiring in 1 or 7 days or is a non-LE certificate expiring in 45 days
                appNotify("Certificate Expiring", "Certificate $certificate_name for $client_name will expire in $day day(s) on $certificate_expire", "certificates.php?client_id=$client_id", $client_id);
            }

        } else {
            // No public key - notify anyway as we can't check the validity period
            appNotify("Certificate Expiring", "Certificate $certificate_name for $client_name will expire in $day day(s) on $certificate_expire", "certificates.php?client_id=$client_id", $client_id);
        }

    }

}
// Logging
// logAction("Cron", "Task", "Cron created notifications for certificates expiring");

// Asset Warranties Expiring

$warranty_alert_array = [1,7,45];

foreach ($warranty_alert_array as $day) {

    //Get Asset Warranty Expiring
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM assets
        LEFT JOIN clients ON asset_client_id = client_id
        WHERE asset_warranty_expire = CURDATE() + INTERVAL $day DAY"
    );

    while ($row = mysqli_fetch_array($sql)) {
        $asset_id = intval($row['asset_id']);
        $asset_name = sanitizeInput($row['asset_name']);
        $asset_warranty_expire = sanitizeInput($row['asset_warranty_expire']);
        $client_id = intval($row['client_id']);
        $client_name = sanitizeInput($row['client_name']);

        appNotify("Asset Warranty Expiring", "Asset $asset_name warranty for $client_name will expire in $day Days on $asset_warranty_expire", "assets.php?client_id=$client_id", $client_id);

    }

}
// Logging
// logAction("Cron", "Task", "Cron created notifications for asset warranties expiring");

// Notify of New Tickets
// Get Ticket Pending Assignment
$sql_tickets_pending_assignment = mysqli_query($mysqli,"SELECT ticket_id FROM tickets WHERE ticket_status = 1");

$tickets_pending_assignment = mysqli_num_rows($sql_tickets_pending_assignment);

if ($tickets_pending_assignment > 0) {

    appNotify("Pending Tickets", "There are $tickets_pending_assignment new tickets pending assignment", "tickets.php?status=New");

    // Logging
    logApp("Cron", "info", "Cron created notifications for new tickets that are pending assignment");
}

// Recurring tickets

// Get recurring tickets for today
$sql_recurring_tickets = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_next_run = CURDATE()");

if (mysqli_num_rows($sql_recurring_tickets) > 0) {
    while ($row = mysqli_fetch_array($sql_recurring_tickets)) {

        $recurring_ticket_id = intval($row['recurring_ticket_id']);
        $subject = sanitizeInput($row['recurring_ticket_subject']);
        $details = mysqli_real_escape_string($mysqli, $row['recurring_ticket_details']);
        $priority = sanitizeInput($row['recurring_ticket_priority']);
        $frequency = sanitizeInput(strtolower($row['recurring_ticket_frequency']));
        $billable = intval($row['recurring_ticket_billable']);
        $created_id = intval($row['recurring_ticket_created_by']);
        $assigned_id = intval($row['recurring_ticket_assigned_to']);
        $client_id = intval($row['recurring_ticket_client_id']);
        $contact_id = intval($row['recurring_ticket_contact_id']);
        $asset_id = intval($row['recurring_ticket_asset_id']);
        $category = intval($row['recurring_ticket_category']);

        $ticket_status = 1; // Default
        if ($assigned_id > 0) {
            $ticket_status = 2; // Set to open if we've auto-assigned an agent
        }

        // Assign this new ticket the next ticket number
        $ticket_number_sql = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_next_number FROM settings WHERE company_id = 1"));
        $ticket_number = intval($ticket_number_sql['config_ticket_next_number']);

        // Increment config_ticket_next_number by 1 (for the next ticket)
        $new_config_ticket_next_number = $ticket_number + 1;
        mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

        // Raise the ticket
        mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Recurring', ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = '$ticket_status', ticket_billable = $billable, ticket_created_by = $created_id, ticket_assigned_to = $assigned_id, ticket_contact_id = $contact_id, ticket_client_id = $client_id, ticket_asset_id = $asset_id, ticket_category = $category, ticket_recurring_ticket_id = $recurring_ticket_id");
        $id = mysqli_insert_id($mysqli);

        // Copy Additional Assets from Recurring ticket to new ticket
        mysqli_query($mysqli, "INSERT INTO ticket_assets (ticket_id, asset_id)
        SELECT $id, asset_id
        FROM recurring_ticket_assets
        WHERE recurring_ticket_id = $recurring_ticket_id");

        // Logging
        logAction("Ticket", "Create", "Cron created recurring scheduled $frequency ticket - $subject", $client_id, $id);

        customAction('ticket_create', $id);

        // Notifications

        // Get client/contact/ticket details
        $sql = mysqli_query(
            $mysqli,
            "SELECT client_name, contact_name, contact_email, ticket_prefix, ticket_number, ticket_priority, ticket_subject, ticket_details FROM tickets
                LEFT JOIN clients ON ticket_client_id = client_id
                LEFT JOIN contacts ON ticket_contact_id = contact_id
                WHERE ticket_id = $id"
        );
        $row = mysqli_fetch_array($sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_priority = sanitizeInput($row['ticket_priority']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $ticket_details = mysqli_real_escape_string($mysqli, $row['ticket_details']);

        $data = [];

        // Notify client by email their ticket has been raised, if general notifications are turned on & there is a valid contact email
        if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1 && filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

            $email_subject = "Ticket created - [$ticket_prefix$ticket_number] - $ticket_subject (scheduled)";
            $email_body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been automatically created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: https://$config_base_url/client/ticket.php?id=$id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

            $email = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $email_subject,
                    'body' => $email_body
            ];

            $data[] = $email;

        }

        // Notify agent's via the DL address of the new ticket, if it's populated with a valid email
        if (filter_var($config_ticket_new_ticket_notification_email, FILTER_VALIDATE_EMAIL)) {

            $email_subject = "ITFlow - New Recurring Ticket - $client_name: $ticket_subject";
            $email_body = "Hello, <br><br>This is a notification that a recurring (scheduled) ticket has been raised in ITFlow. <br>Ticket: $ticket_prefix$ticket_number<br>Client: $client_name<br>Priority: $priority<br>Link: https://$config_base_url/ticket.php?ticket_id=$id <br><br>--------------------------------<br><br><b>$ticket_subject</b><br>$ticket_details";

            $email = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $config_ticket_new_ticket_notification_email,
                    'recipient_name' => $config_ticket_from_name,
                    'subject' => $email_subject,
                    'body' => $email_body
            ];

            $data[] = $email;
        }

        // Add to the mail queue
        addToMailQueue($data);

        // Set the next run date
        if ($frequency == "weekly") {
            // Note: We seemingly have to initialize a new datetime for each loop to avoid stacking the dates
            $now = new DateTime();
            $next_run = date_add($now, date_interval_create_from_date_string('1 week'));
        } elseif ($frequency == "monthly") {
            $now = new DateTime();
            $next_run = date_add($now, date_interval_create_from_date_string('1 month'));
        } elseif ($frequency == "quarterly") {
            $now = new DateTime();
            $next_run = date_add($now, date_interval_create_from_date_string('3 months'));
        } elseif ($frequency == "biannually") {
            $now = new DateTime();
            $next_run = date_add($now, date_interval_create_from_date_string('6 months'));
        } elseif ($frequency == "annually") {
            $now = new DateTime();
            $next_run = date_add($now, date_interval_create_from_date_string('12 months'));
        }

        // Update the run date
        $next_run = $next_run->format('Y-m-d');
        $a = mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_next_run = '$next_run' WHERE recurring_ticket_id = $recurring_ticket_id");

    }
}

// Flag any active recurring "next run" dates that are in the past
$sql_invalid_recurring_tickets = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_next_run < CURDATE()");
while ($row = mysqli_fetch_array($sql_invalid_recurring_tickets)) {
    $subject = sanitizeInput($row['recurring_ticket_subject']);
    appNotify("Ticket", "Recurring ticket $subject next run date is in the past!", "recurring_tickets.php");
}

// Logging
// logAction("Cron", "Task", "Cron created sent out recurring tickets");


// TICKET RESOLUTION/CLOSURE PROCESS
// Changes tickets status from 'Resolved' >> 'Closed' after a defined interval

$sql_resolved_tickets_to_close = mysqli_query(
    $mysqli,
    "SELECT * FROM tickets
    WHERE ticket_status = 4
    AND ticket_updated_at < NOW() - INTERVAL $config_ticket_autoclose_hours HOUR"
);

while ($row = mysqli_fetch_array($sql_resolved_tickets_to_close)) {

    $ticket_id = $row['ticket_id'];
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_status = sanitizeInput($row['ticket_status']);
    $ticket_assigned_to = sanitizeInput($row['ticket_assigned_to']);
    $client_id = intval($row['ticket_client_id']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW(), ticket_closed_by = $ticket_assigned_to WHERE ticket_id = $ticket_id");

    //Logging
    logAction("Ticket", "Closed", "$ticket_prefix$ticket_number auto closed", $client_id, $ticket_id);

    customAction('ticket_close', $ticket_id);

    //TODO: Add client notifs if $config_ticket_client_general_notifications is on
}

if ($config_send_invoice_reminders == 1) {

    // PAST DUE INVOICE Notifications
    //$invoiceAlertArray = [$config_invoice_overdue_reminders];
    $invoiceAlertArray = [30,60,90,120,150,180,210,240,270,300,330,360,390,420,450,480,510,540,570,590,620,650,680,710,740];

    foreach ($invoiceAlertArray as $day) {

        $sql = mysqli_query(
            $mysqli,
            "SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
            WHERE invoice_status != 'Draft'
            AND invoice_status != 'Paid'
            AND invoice_status != 'Cancelled'
            AND invoice_status != 'Non-Billable'
            AND DATE_ADD(invoice_due, INTERVAL $day DAY) = CURDATE()
            ORDER BY invoice_number DESC"
        );

        while ($row = mysqli_fetch_array($sql)) {
            $invoice_id = intval($row['invoice_id']);
            $invoice_prefix = sanitizeInput($row['invoice_prefix']);
            $invoice_number = intval($row['invoice_number']);
            $invoice_status = sanitizeInput($row['invoice_status']);
            $invoice_date = sanitizeInput($row['invoice_date']);
            $invoice_due = sanitizeInput($row['invoice_due']);
            $invoice_url_key = sanitizeInput($row['invoice_url_key']);
            $invoice_amount = floatval($row['invoice_amount']);
            $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
            $client_id = intval($row['client_id']);
            $client_name = sanitizeInput($row['client_name']);
            $contact_name = sanitizeInput($row['contact_name']);
            $contact_email = sanitizeInput($row['contact_email']);

            // Late Charges

            if ($config_invoice_late_fee_enable == 1) {

                $todays_date = date('Y-m-d');
                $late_fee_amount = ($invoice_amount * $config_invoice_late_fee_percent) / 100;
                $new_invoice_amount = $invoice_amount + $late_fee_amount;

                mysqli_query($mysqli, "UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

                //Insert Items into New Invoice
                mysqli_query($mysqli, "INSERT INTO invoice_items SET item_name = 'Late Fee', item_description = '$config_invoice_late_fee_percent% late fee applied on $todays_date', item_quantity = 1, item_price = $late_fee_amount, item_total = $late_fee_amount, item_order = 998, item_invoice_id = $invoice_id");

                mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Cron applied a late fee of $late_fee_amount', history_invoice_id = $invoice_id");

                appNotify("Invoice Late Charge", "Invoice $invoice_prefix$invoice_number for $client_name in the amount of $invoice_amount was charged a late fee of $late_fee_amount", "invoice.php?invoice_id=$invoice_id", $client_id);

            }

            appNotify("Invoice Overdue", "Invoice $invoice_prefix$invoice_number for $client_name in the amount of $invoice_amount is overdue by $day days", "invoice.php?invoice_id=$invoice_id", $client_id);

            $subject = "Overdue Invoice $invoice_prefix$invoice_number";
            $body = "Hello $contact_name,<br><br>Our records indicate that we have not yet received payment for the invoice $invoice_prefix$invoice_number. We kindly request that you submit your payment as soon as possible. If you have any questions or concerns, please do not hesitate to contact us at $company_email or $company_phone.
                <br>
                Kindly review the invoice details mentioned below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br>Due Date: $invoice_due<br>Over Due By: $day Days<br><br><br>To view your invoice, please click <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>here</a>.<br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

            $mail = addToMailQueue([
                [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body
                ]
            ]);

            if ($mail === true) {
                mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Cron Emailed Overdue Invoice', history_invoice_id = $invoice_id");
            } else {
                mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Cron Failed to send Overdue Invoice', history_invoice_id = $invoice_id");

                appNotify("Mail", "Failed to send email to $contact_email");

                // Logging
                logApp("Mail", "error", "Failed to send email to $contact_email regarding $subject. $mail");
            }

        }

    }
}
// Logging
// logAction("Cron", "Task", "Cron created notifications for past due invoices and sent out notifications to the primary and billing contacts email");

// Send Recurring Invoices that match todays date and are active

//Loop through all recurring that match today's date and is active
$sql_recurring_invoices = mysqli_query($mysqli, "SELECT * FROM recurring_invoices
    LEFT JOIN recurring_payments ON recurring_invoice_id = recurring_payment_recurring_invoice_id
    LEFT JOIN clients ON client_id = recurring_invoice_client_id
    WHERE recurring_invoice_next_date = CURDATE()
    AND recurring_invoice_status = 1
");

while ($row = mysqli_fetch_array($sql_recurring_invoices)) {
    $recurring_invoice_id = intval($row['recurring_invoice_id']);
    $recurring_invoice_scope = sanitizeInput($row['recurring_invoice_scope']);
    $recurring_invoice_frequency = sanitizeInput($row['recurring_invoice_frequency']);
    $recurring_invoice_status = sanitizeInput($row['recurring_invoice_status']);
    $recurring_invoice_last_sent = sanitizeInput($row['recurring_invoice_last_sent']);
    $recurring_invoice_next_date = sanitizeInput($row['recurring_invoice_next_date']);
    $recurring_invoice_discount_amount = floatval($row['recurring_invoice_discount_amount']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);
    $recurring_invoice_currency_code = sanitizeInput($row['recurring_invoice_currency_code']);
    $recurring_invoice_note = sanitizeInput($row['recurring_invoice_note']);
    $recurring_invoice_email_notify = intval($row['recurring_invoice_email_notify']);
    $category_id = intval($row['recurring_invoice_category_id']);
    $client_id = intval($row['recurring_invoice_client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $client_net_terms = intval($row['client_net_terms']);

    $recurring_payment_recurring_invoice_id = intval($row['recurring_payment_recurring_invoice_id']);
    $recurring_payment_currency_code = sanitizeInput($row['recurring_payment_currency_code']);
    $recurring_payment_method = sanitizeInput($row['recurring_payment_method']);
    $recurring_payment_account_id = intval($row['recurring_payment_account_id']);

    // Get the last Invoice Number and add 1 for the new invoice number
    $sql_invoice_number = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
    $row = mysqli_fetch_array($sql_invoice_number);
    $config_invoice_next_number = intval($row['config_invoice_next_number']);

    $new_invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli, "UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli, "INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $new_invoice_number, invoice_scope = '$recurring_invoice_scope', invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_discount_amount = $recurring_invoice_discount_amount, invoice_amount = $recurring_invoice_amount, invoice_currency_code = '$recurring_invoice_currency_code', invoice_note = '$recurring_invoice_note', invoice_category_id = $category_id, invoice_status = 'Sent', invoice_url_key = '$url_key', invoice_recurring_invoice_id = $recurring_invoice_id, invoice_client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    //Copy Items from original recurring invoice to new invoice
    $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id ORDER BY item_id ASC");

    while ($row = mysqli_fetch_array($sql_invoice_items)) {
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']); //SQL Escape incase of ,
        $item_description = sanitizeInput($row['item_description']); //SQL Escape incase of ,
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $item_order = intval($row['item_order']);
        $tax_id = intval($row['item_tax_id']);

        //Insert Items into New Invoice
        mysqli_query($mysqli, "INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_invoice_id = $new_invoice_id");

    }

    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice Generated from Recurring!', history_invoice_id = $new_invoice_id");

    appNotify("Recurring Sent", "Recurring Invoice $config_invoice_prefix$new_invoice_number for $client_name Sent", "invoice.php?invoice_id=$new_invoice_id", $client_id);

    customAction('invoice_create', $new_invoice_id);

    //Update recurring dates

    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_last_sent = CURDATE(), recurring_invoice_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_invoice_frequency) WHERE recurring_invoice_id = $recurring_invoice_id");

    // Get details of the newly generated invoice
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
            WHERE invoice_id = $new_invoice_id"
    );
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_date = sanitizeInput($row['invoice_date']);
    $invoice_due = sanitizeInput($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);

    if ($config_recurring_auto_send_invoice == 1 && $recurring_invoice_email_notify == 1) {

        $subject = "Invoice $invoice_prefix$invoice_number";
        $body = "Hello $contact_name,<br><br>An invoice regarding \"$invoice_scope\" has been generated. Please view the details below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $recurring_invoice_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice, please click <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$new_invoice_id&url_key=$invoice_url_key\'>here</a>.<br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

        $mail = addToMailQueue([
            [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ]
        ]);

        if ($mail === true) {
            mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Cron Emailed Invoice!', history_invoice_id = $new_invoice_id");
            mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Sent', invoice_client_id = $client_id WHERE invoice_id = $new_invoice_id");

        } else {
            mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Draft', history_description = 'Cron Failed to send Invoice!', history_invoice_id = $new_invoice_id");

            appNotify("Mail", "Failed to send email to $contact_email");

            // Logging
            logApp("Mail", "error", "Failed to send email to $contact_email regarding $subject. $mail");

        }

        // Send copies of the invoice to any additional billing contacts
        $sql_billing_contacts = mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts
            WHERE contact_billing = 1
            AND contact_email != '$contact_email'
            AND contact_client_id = $client_id"
        );

        while ($billing_contact = mysqli_fetch_array($sql_billing_contacts)) {
            $billing_contact_name = sanitizeInput($billing_contact['contact_name']);
            $billing_contact_email = sanitizeInput($billing_contact['contact_email']);

            $data = [
                [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $billing_contact_email,
                    'recipient_name' => $billing_contact_name,
                    'subject' => $subject,
                    'body' => $body
                ]
            ];

            addToMailQueue($data);
        }

    } //End if Autosend is on

} //End Recurring Invoices Loop

// Start Flag any active recurring "next run" dates that are in the past
$sql_invalid_recurring_invoices = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_next_date < CURDATE() AND recurring_invoice_status = 1");
while ($row = mysqli_fetch_array($sql_invalid_recurring_invoices)) {
    $invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $invoice_number = intval($row['recurring_invoice_number']);
    appNotify("Invoice", "Recurring invoice $invoice_prefix$invoice_number next run date is in the past!", "recurring_invoices.php");
}
// End Flag any active recurring "next run" dates that are in the past


// Start Recurring Payments
$sql_recurring_payments = mysqli_query($mysqli, "
    SELECT * FROM recurring_payments
    LEFT JOIN invoices ON invoice_recurring_invoice_id = recurring_payment_recurring_invoice_id
    LEFT JOIN clients ON client_id = invoice_client_id
    LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
    WHERE invoice_due = CURDATE()
      AND (invoice_status = 'Sent' OR invoice_status = 'Viewed')
");

while ($row = mysqli_fetch_array($sql_recurring_payments)) {
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_date = sanitizeInput($row['invoice_date']);
    $invoice_due = sanitizeInput($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $recurring_payment_account_id = intval($row['recurring_payment_account_id']);
    $recurring_payment_method = sanitizeInput($row['recurring_payment_method']);
    $recurring_payment_currency_code = sanitizeInput($row['recurring_payment_currency_code']);
    $recurring_payment_saved_payment_id = intval($row['recurring_payment_saved_payment_id']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);

    // Only attempt autopay if a saved payment method is set
    if ($recurring_payment_saved_payment_id) {
        // Get the saved payment method and provider details
        $saved_payment = mysqli_fetch_array(mysqli_query($mysqli, "
            SELECT * FROM client_saved_payment_methods
            LEFT JOIN payment_providers ON saved_payment_provider_id = payment_provider_id
            WHERE saved_payment_id = $recurring_payment_saved_payment_id
              AND saved_payment_client_id = $client_id
              AND payment_provider_active = 1
            LIMIT 1
        "));

        if (!$saved_payment) {
            logAction("Invoice", "Payment", "Failed auto Payment for invoice $invoice_prefix$invoice_number: Saved payment method not found or provider inactive", $client_id, $invoice_id);
            continue;
        }

        $provider_id = intval($saved_payment['payment_provider_id']);
        $provider_name = sanitizeInput($saved_payment['payment_provider_name']);
        $provider_private_key = $saved_payment['payment_provider_private_key'];
        $account_id = intval($saved_payment['payment_provider_account']);
        $saved_payment_description = sanitizeInput($saved_payment['saved_payment_description']);
        $stripe_payment_method_id = $saved_payment['saved_payment_provider_method'];

        // NEW: Get the payment_provider_client (Stripe Customer ID) from client_payment_provider
        $cpp_query = mysqli_query($mysqli, "
            SELECT payment_provider_client FROM client_payment_provider
            WHERE client_id = $client_id
              AND payment_provider_id = $provider_id
            LIMIT 1
        ");
        $cpp_row = mysqli_fetch_array($cpp_query);
        $stripe_customer_id = $cpp_row ? sanitizeInput($cpp_row['payment_provider_client']) : '';

        // Stripe
        if ($provider_name === "Stripe") {
            if ($provider_private_key && $stripe_customer_id && $stripe_payment_method_id) {
                require_once __DIR__ . '/../plugins/stripe-php/init.php';
                $stripe = new \Stripe\StripeClient($provider_private_key);

                $balance_to_pay = round($invoice_amount, 2);
                $pi_description = "ITFlow: $client_name payment of $recurring_payment_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

                try {
                    $payment_intent = $stripe->paymentIntents->create([
                        'amount' => intval($balance_to_pay * 100),
                        'currency' => $recurring_payment_currency_code,
                        'customer' => $stripe_customer_id,
                        'payment_method' => $stripe_payment_method_id,
                        'off_session' => true,
                        'confirm' => true,
                        'description' => $pi_description,
                        'metadata' => [
                            'itflow_client_id' => $client_id,
                            'itflow_client_name' => $client_name,
                            'itflow_invoice_number' => $invoice_prefix . $invoice_number,
                            'itflow_invoice_id' => $invoice_id,
                        ]
                    ]);

                    $pi_id = sanitizeInput($payment_intent->id);
                    $pi_date = date('Y-m-d', $payment_intent->created);
                    $pi_amount_paid = floatval($payment_intent->amount_received / 100);
                    $pi_currency = strtoupper(sanitizeInput($payment_intent->currency));
                    $pi_livemode = $payment_intent->livemode;

                } catch (Exception $e) {
                    $error = $e->getMessage();
                    error_log("Stripe payment error - encountered exception during payment intent for invoice ID $invoice_id / $invoice_prefix$invoice_number: $error");
                    logApp("Stripe", "error", "Exception during PI for invoice ID $invoice_id: $error");
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe autopay failed due to payment error', history_invoice_id = $invoice_id");
                    logAction("Invoice", "Payment", "Failed auto Payment amount of invoice $invoice_prefix$invoice_number due to Stripe payment error: $error", $client_id, $invoice_id);
                    continue;
                }

                if ($payment_intent->status == "succeeded" && intval($balance_to_pay) == intval($pi_amount_paid)) {

                    // Update Invoice Status
                    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id");

                    // Add Payment to History
                    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $account_id, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (autopay)', history_invoice_id = $invoice_id");

                    // EXPENSE: Stripe gateway fee as an expense (if configured)
                    if ($config_stripe_expense_vendor > 0 && $config_stripe_expense_category > 0) {
                        $gateway_fee = round($invoice_amount * $config_stripe_percentage_fee + $config_stripe_flat_fee, 2);
                        mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$pi_date', expense_amount = $gateway_fee, expense_currency_code = '$pi_currency', expense_account_id = $config_stripe_account, expense_vendor_id = $config_stripe_expense_vendor, expense_client_id = $client_id, expense_category_id = $config_stripe_expense_category, expense_description = 'Stripe Transaction for Invoice $invoice_prefix$invoice_number in the Amount of $balance_to_pay', expense_reference = 'Stripe - $pi_id'");
                    }

                    // RECEIPT EMAIL
                    if (!empty($config_smtp_host)) {
                        $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
                        $body = "Hello $contact_name<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $recurring_payment_currency_code) . " for invoice <a href=\\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $recurring_payment_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

                        $data = [[
                            'from' => $config_invoice_from_email,
                            'from_name' => $config_invoice_from_name,
                            'recipient' => $contact_email,
                            'recipient_name' => $contact_name,
                            'subject' => $subject,
                            'body' => $body,
                        ]];

                        // Internal notification
                        if (!empty($config_invoice_paid_notification_email)) {
                            $subject_int = "Payment Received - $client_name - Invoice $invoice_prefix$invoice_number";
                            $body_int = "This is a notification that an invoice has been paid in ITFlow. Below is a copy of the receipt sent to the client:-<br><br>--------<br><br>$body";
                            $data[] = [
                                'from' => $config_invoice_from_email,
                                'from_name' => $config_invoice_from_name,
                                'recipient' => $config_invoice_paid_notification_email,
                                'recipient_name' => $contact_name,
                                'subject' => $subject_int,
                                'body' => $body_int,
                            ];
                        }
                        $mail = addToMailQueue($data);
                        $email_id = mysqli_insert_id($mysqli);
                        mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
                        logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);
                    }

                    // LOGGING
                    $extended_log_desc = !$pi_livemode ? '(DEV MODE)' : '';
                    appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number automatically paid", "invoice.php?invoice_id=$invoice_id", $client_id);
                    logAction("Invoice", "Payment", "Auto Stripe payment amount of " . numfmt_format_currency($currency_format, $invoice_amount, $recurring_payment_currency_code) . " added to invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc", $client_id, $invoice_id);
                    customAction('invoice_pay', $invoice_id);

                } else {
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe autopay failed: Status {$payment_intent->status}', history_invoice_id = $invoice_id");
                    logAction("Invoice", "Payment", "Failed auto Payment for invoice $invoice_prefix$invoice_number. Stripe PI status: {$payment_intent->status}", $client_id, $invoice_id);
                }
            } // End if Stripe creds and IDs
        } // End if Stripe provider
        // Add other provider logic here as needed
    } else {
        // Handle Non-payment-provider autopay
        mysqli_query($mysqli, "INSERT INTO payments SET payment_date = CURDATE(), payment_amount = $invoice_amount, payment_currency_code = '$recurring_payment_currency_code', payment_account_id = $recurring_payment_account_id, payment_method = '$recurring_payment_method', payment_reference = 'Paid via AutoPay', payment_invoice_id = $invoice_id");
        $payment_id = mysqli_insert_id($mysqli);

        mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Payment added via Auto Pay', history_invoice_id = $invoice_id");
        logAction("Invoice", "Payment", "Auto Payment amount of $recurring_payment_currency_code $invoice_amount added to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);
    }
}

// Recurring Expenses
// Loop through all recurring expenses that match today's date and is active
$sql_recurring_expenses = mysqli_query($mysqli, "SELECT * FROM recurring_expenses WHERE recurring_expense_next_date = CURDATE() AND recurring_expense_status = 1");

while ($row = mysqli_fetch_array($sql_recurring_expenses)) {
    $recurring_expense_id = intval($row['recurring_expense_id']);
    $recurring_expense_frequency = intval($row['recurring_expense_frequency']);
    $recurring_expense_month = intval($row['recurring_expense_month']);
    $recurring_expense_day = intval($row['recurring_expense_day']);
    $recurring_expense_description = sanitizeInput($row['recurring_expense_description']);
    $recurring_expense_amount = floatval($row['recurring_expense_amount']);
    $recurring_expense_payment_method = sanitizeInput($row['recurring_expense_payment_method']);
    $recurring_expense_reference = sanitizeInput($row['recurring_expense_reference']);
    $recurring_expense_currency_code = sanitizeInput($row['recurring_expense_currency_code']);
    $recurring_expense_vendor_id = intval($row['recurring_expense_vendor_id']);
    $recurring_expense_category_id = intval($row['recurring_expense_category_id']);
    $recurring_expense_account_id = intval($row['recurring_expense_account_id']);
    $recurring_expense_client_id = intval($row['recurring_expense_client_id']);

    // Calculate next billing date based on frequency
    if ($recurring_expense_frequency == 1) { // Monthly
        $next_date_query = "DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
    } elseif ($recurring_expense_frequency == 2) { // Yearly
        $next_date_query = "DATE(CONCAT(YEAR(CURDATE()) + 1, '-', $recurring_expense_month, '-', $recurring_expense_day))";
    } else {
        // Handle unexpected frequency values. For now, just use current date.
        $next_date_query = "CURDATE()";
    }

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = CURDATE(), expense_amount = $recurring_expense_amount, expense_currency_code = '$recurring_expense_currency_code', expense_account_id = $recurring_expense_account_id, expense_vendor_id = $recurring_expense_vendor_id, expense_client_id = $recurring_expense_client_id, expense_category_id = $recurring_expense_category_id, expense_description = '$recurring_expense_description', expense_reference = '$recurring_expense_reference'");

    $expense_id = mysqli_insert_id($mysqli);

    appNotify("Expense Created", "Expense $recurring_expense_description created from recurring expenses", "expenses.php", $recurring_expense_client_id);

    // Update recurring dates using calculated next billing date

    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_last_sent = CURDATE(), recurring_expense_next_date = $next_date_query WHERE recurring_expense_id = $recurring_expense_id");


} //End Recurring expenses loop

// Flag any active recurring "next run" dates that are in the past
$sql_invalid_recurring_expenses = mysqli_query($mysqli, "SELECT * FROM recurring_expenses WHERE recurring_expense_next_date < CURDATE() AND recurring_expense_status = 1");
while ($row = mysqli_fetch_array($sql_invalid_recurring_expenses)) {
    $recurring_expense_description = sanitizeInput($row['recurring_expense_description']);
    appNotify("Expense", "Recurring expense $recurring_expense_description next run date is in the past!", "recurring_expenses.php");
}

// Logging
//logApp("Cron", "info", "Cron created expenses from recurring expenses");

// TELEMETRY

if ($config_telemetry > 0 || $config_telemetry == 2) {

    $current_version = exec("git rev-parse HEAD");

    // Client Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('client_id') AS num FROM clients"));
    $client_count = $row['num'];

    // Ticket Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_id') AS num FROM tickets"));
    $ticket_count = $row['num'];

    // Recurring Ticket Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_ticket_id') AS num FROM recurring_tickets"));
    $recurring_ticket_count = $row['num'];

    // Calendar Event Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('event_id') AS num FROM calendar_events"));
    $calendar_event_count = $row['num'];

    // Quote Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('quote_id') AS num FROM quotes"));
    $quote_count = $row['num'];

    // Invoice Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices"));
    $invoice_count = $row['num'];

    // Revenue Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('revenue_id') AS num FROM revenues"));
    $revenue_count = $row['num'];

    // Recurring Invoice Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('recurring_invoice_id') AS num FROM recurring_invoices"));
    $recurring_invoice_count = $row['num'];

    // Account Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('account_id') AS num FROM accounts"));
    $account_count = $row['num'];

    // Tax Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('tax_id') AS num FROM taxes"));
    $tax_count = $row['num'];

    // Product Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('product_id') AS num FROM products"));
    $product_count = $row['num'];

    // Payment Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('payment_id') AS num FROM payments WHERE payment_invoice_id > 0"));
    $payment_count = $row['num'];

    // Company Vendor Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_client_id = 0"));
    $company_vendor_count = $row['num'];

    // Expense Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('expense_id') AS num FROM expenses WHERE expense_vendor_id > 0"));
    $expense_count = $row['num'];

    // Trip Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('trip_id') AS num FROM trips"));
    $trip_count = $row['num'];

    // Transfer Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('transfer_id') AS num FROM transfers"));
    $transfer_count = $row['num'];

    // Contact Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('contact_id') AS num FROM contacts"));
    $contact_count = $row['num'];

    // Location Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('location_id') AS num FROM locations"));
    $location_count = $row['num'];

    // Asset Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('asset_id') AS num FROM assets"));
    $asset_count = $row['num'];

    // Software Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('software_id') AS num FROM software"));
    $software_count = $row['num'];

    // Software Template Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('software_template_id') AS num FROM software_templates"));
    $software_template_count = $row['num'];

    // Credential Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('credential_id') AS num FROM credentials"));
    $credential_count = $row['num'];

    // Network Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('network_id') AS num FROM networks"));
    $network_count = $row['num'];

    // Certificate Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('certificate_id') AS num FROM certificates"));
    $certificate_count = $row['num'];

    // Domain Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('domain_id') AS num FROM domains"));
    $domain_count = $row['num'];

    // Service Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('service_id') AS num FROM services"));
    $service_count = $row['num'];

    // Client Vendor Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_client_id > 0"));
    $client_vendor_count = $row['num'];

    // Vendor Template Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('vendor_template_id') AS num FROM vendor_templates"));
    $vendor_template_count = $row['num'];

    // File Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('file_id') AS num FROM files"));
    $file_count = $row['num'];

    // Document Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_id') AS num FROM documents"));
    $document_count = $row['num'];

    // Document Template Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_template_id') AS num FROM document_templates"));
    $document_template_count = $row['num'];

    // Shared Item Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('item_id') AS num FROM shared_items"));
    $shared_item_count = $row['num'];

    // Company Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('company_id') AS num FROM companies"));
    $company_count = $row['num'];

    // User Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('user_id') AS num FROM users"));
    $user_count = $row['num'];

    // Category Expense Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Expense'"));
    $category_expense_count = $row['num'];

    // Category Income Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Income'"));
    $category_income_count = $row['num'];

    // Category Referral Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Referral'"));
    $category_referral_count = $row['num'];

    // Category Payment Method Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Payment Method'"));
    $category_payment_method_count = $row['num'];

    // Tag Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('tag_id') AS num FROM tags"));
    $tag_count = $row['num'];

    // API Key Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('api_key_id') AS num FROM api_keys"));
    $api_key_count = $row['num'];

    // Log Count
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('log_id') AS num FROM logs"));
    $log_count = $row['num'];

    $postdata = http_build_query(
        array(
            'installation_id' => "$installation_id",
            'version' => "$current_version",
            'company_name' => "$company_name",
            'website' => "$company_website",
            'city' => "$company_city",
            'state' => "$company_state",
            'country' => "$company_country",
            'currency' => "$company_currency",
            'client_count' => $client_count,
            'ticket_count' => $ticket_count,
            'recurring_ticket_count' => $recurring_ticket_count,
            'calendar_event_count' => $calendar_event_count,
            'quote_count' => $quote_count,
            'invoice_count' => $invoice_count,
            'revenue_count' => $revenue_count,
            'recurring_invoice_count' => $recurring_invoice_count,
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
            'credential_count' => $credential_count,
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
            'collection_method' => 3
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

    // Logging
    // logAction("Cron", "Task", "Cron sent telemetry results to ITFlow Developers");

}


// Fetch Updates
$updates = fetchUpdates();

$update_message = $updates->update_message;

if ($updates->current_version !== $updates->latest_version) {
    // Send Alert to inform Updates Available
    appNotify("Update", "$update_message", "admin_update.php");
}



/*
 * ###############################################################################################################
 *  FINISH UP
 * ###############################################################################################################
 */

// Send Alert to inform Cron was run
appNotify("Cron", "Cron successfully executed", "admin_audit_log.php");

// Logging
logApp("Cron", "info", "Cron executed successfully");
