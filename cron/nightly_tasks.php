<?php

/*
 * ITFlow - Nightly tasks
 *
 * Everything that runs once a day: clean-up, recurring invoices and tickets, reminders,
 * autopay, telemetry, the update check. Run by cron/cron.php at 03:00; can also be run
 * directly at any time.
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

$sql_companies = mysqli_query($mysqli, "SELECT company_city, company_country, company_currency, company_email, company_locale,
    company_name, company_phone, company_phone_country_code, company_state, company_website,
    config_enable_alert_domain_expire, config_enable_cron, config_invoice_from_email,
    config_invoice_from_name, config_invoice_late_fee_enable, config_invoice_late_fee_percent,
    config_invoice_prefix, config_log_retention, config_login_remember_me_expire,
    config_mail_from_email, config_mail_from_name, config_module_enable_accounting,
    config_module_enable_itdoc, config_module_enable_ticketing,
    config_recurring_auto_send_invoice, config_send_invoice_reminders, config_smtp_encryption,
    config_smtp_host, config_smtp_password, config_smtp_port, config_smtp_provider,
    config_smtp_username, config_telemetry, config_theme, config_ticket_autoclose_hours,
    config_ticket_client_general_notifications, config_ticket_email_parse,
    config_ticket_from_email, config_ticket_from_name,
    config_ticket_new_ticket_notification_email, config_ticket_prefix,
    config_whitelabel_enabled, config_whitelabel_key FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");

$row = mysqli_fetch_assoc($sql_companies);

// Company Details
$company_name = escapeSql($row['company_name']);
$company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
$company_email = escapeSql($row['company_email']);
$company_website = escapeSql($row['company_website']);
$company_city = escapeSql($row['company_city']);
$company_state = escapeSql($row['company_state']);
$company_country = escapeSql($row['company_country']);
$company_locale = escapeSql($row['company_locale']);
$company_currency = escapeSql($row['company_currency']);

// Company Settings
$config_enable_cron = intval($row['config_enable_cron']);
$config_invoice_prefix = escapeSql($row['config_invoice_prefix']);
$config_invoice_from_email = escapeSql($row['config_invoice_from_email']);
$config_invoice_from_name = escapeSql($row['config_invoice_from_name']);
$config_invoice_late_fee_enable = intval($row['config_invoice_late_fee_enable']);
$config_invoice_late_fee_percent = floatval($row['config_invoice_late_fee_percent']);

// Mail Settings
$config_smtp_provider = escapeSql($row['config_smtp_provider']);
$config_smtp_host = $row['config_smtp_host'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_smtp_port = intval($row['config_smtp_port']);
$config_smtp_encryption = $row['config_smtp_encryption'];
$config_mail_from_email = escapeSql($row['config_mail_from_email']);
$config_mail_from_name = escapeSql($row['config_mail_from_name']);
$config_recurring_auto_send_invoice = intval($row['config_recurring_auto_send_invoice']);

// Tickets
$config_ticket_prefix = escapeSql($row['config_ticket_prefix']);
$config_ticket_from_name = escapeSql($row['config_ticket_from_name']);
$config_ticket_from_email = escapeSql($row['config_ticket_from_email']);
$config_ticket_client_general_notifications = intval($row['config_ticket_client_general_notifications']);
$config_ticket_autoclose_hours = intval($row['config_ticket_autoclose_hours']);
$config_ticket_new_ticket_notification_email = escapeSql($row['config_ticket_new_ticket_notification_email']);

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
    cronJobStop("Cron: is not enabled -- Quitting..");
}

/*
 * Whether cron has already recorded doing something to an invoice today, judged by the
 * history rows this script writes. The overdue and autopay queries below are day-matched -
 * they select the same invoices on every run of a given day - so each action that emails a
 * client or changes money checks here first. This is what makes a second run in one day
 * (Run Now after the 3am pass) safe. Named for this script per the shared-process rule in
 * CONTRIBUTING.
 */
function cronInvoiceHistoryToday(int $invoice_id, string $description_prefix): bool
{
    global $mysqli;

    $description_prefix = escapeSql($description_prefix);

    $sql = mysqli_query($mysqli, "SELECT history_id FROM history
        WHERE history_invoice_id = $invoice_id
        AND history_description LIKE '$description_prefix%'
        AND history_created_at >= CURDATE()
        LIMIT 1");

    return mysqli_num_rows($sql) > 0;
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

// Shared links: wipe the secrets of the ones that ended, and forget them
// after SHARE_KEEP_DAYS - until then they stay listed, with who opened them
sharePrune();

// Forget the portal password links that have run out - an invite works for a
// week, Forgot password for an hour (functions/portal.php), and one with no
// expiry is from before links had one
mysqli_query($mysqli, "UPDATE users SET user_password_reset_token = NULL, user_password_reset_token_expire = NULL
    WHERE user_password_reset_token IS NOT NULL AND (user_password_reset_token_expire IS NULL OR user_password_reset_token_expire < NOW())");

// Clean-up old dismissed notifications
mysqli_query($mysqli, "DELETE FROM notifications WHERE notification_dismissed_at < CURDATE() - INTERVAL 90 DAY");

// Clean-up mail queue
mysqli_query($mysqli, "DELETE FROM email_queue WHERE email_queued_at < CURDATE() - INTERVAL 90 DAY");

// Clean-up the PDFs made to go with billing emails - sent or given up on by now
billingEmailPdfCleanup(7);

// Clean-up old remember me tokens
mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_created_at < CURDATE() - INTERVAL $config_login_remember_me_expire DAY");

// Cleanup old backups, and reconcile rows whose file is gone against files with no row.
// Retention lives here rather than in cron/backup.php so a failed backup run can never
// delete the archive it was supposed to replace.
backupRunRetention($mysqli);

// Cleanup old audit logs
mysqli_query($mysqli, "DELETE FROM logs WHERE log_created_at < CURDATE() - INTERVAL $config_log_retention DAY");

// Cleanup old app/debug logs
mysqli_query($mysqli, "DELETE FROM app_logs WHERE app_log_created_at < CURDATE() - INTERVAL $config_log_retention DAY");

// Cleanup old auth logs
mysqli_query($mysqli, "DELETE FROM auth_logs WHERE auth_log_created_at < CURDATE() - INTERVAL $config_log_retention DAY");

// CLeanup old domain history
$sql = mysqli_query($mysqli, "SELECT domain_id FROM domains");
while ($row = mysqli_fetch_assoc($sql)) {
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
// logAudit("Cron", "Task", "Cron cleaned up old data");

/*
 * ###############################################################################################################
 *  ACTION DATA
 * ###############################################################################################################
 */

// Whitelabel - Disable if expired/invalid
if ($config_whitelabel_enabled && !validateWhitelabelKey($config_whitelabel_key)) {
    mysqli_query($mysqli, "UPDATE settings SET config_whitelabel_enabled = 0, config_whitelabel_key = '' WHERE company_id = 1");
    appNotify("Settings", "White-labelling was disabled due to expired/invalid key", "/admin/settings_modules.php");
}


// GET NOTIFICATIONS

// DOMAINS EXPIRING

if ($config_enable_alert_domain_expire == 1) {

    $domainAlertArray = [1,7,45];

    foreach ($domainAlertArray as $day) {

        //Get Domains Expiring
        $sql = mysqli_query(
            $mysqli,
            "SELECT client_id, client_name, domain_expire, domain_auto_renew, domain_id, domain_name FROM domains
            LEFT JOIN clients ON domain_client_id = client_id
            WHERE domain_expire IS NOT NULL AND domain_expire = CURDATE() + INTERVAL $day DAY
            AND domain_archived_at IS NULL AND client_archived_at IS NULL"
        );

        while ($row = mysqli_fetch_assoc($sql)) {
            $domain_id = intval($row['domain_id']);
            $domain_name = escapeSql($row['domain_name']);
            $domain_expire = escapeSql($row['domain_expire']);
            $client_id = intval($row['client_id']);
            $client_name = escapeSql($row['client_name']);

            // Auto-renew still fails on an expired card, so it is said rather than skipped
            $domain_renewal = intval($row['domain_auto_renew']) ? ' - set to auto-renew, check it goes through' : '';
            appNotify("Domain Expiring", "Domain $domain_name for $client_name will expire in $day Days on $domain_expire$domain_renewal", "/agent/domains.php?client_id=$client_id&open=$domain_id", $client_id);

        }

    }
    // Logging
    // logAudit("Cron", "Task", "Cron created notifications for domains expiring");
}

// CERTIFICATES EXPIRING

// A month and a half out, then a week, then the day before. A short-lived
// certificate (Let's Encrypt and friends) renews itself with about 30 days
// left, so its 45-day mark is routine - only the week and the day are said,
// and by then it should already have renewed.
$certificateAlertArray = [1,7,45];

foreach ($certificateAlertArray as $day) {

    //Get Certs Expiring
    $sql = mysqli_query(
        $mysqli,
        "SELECT certificate_domain, certificate_expire, certificate_issued_at, certificate_id, certificate_name,
            client_id, client_name FROM certificates
        LEFT JOIN clients ON certificate_client_id = client_id
        WHERE certificate_expire = CURDATE() + INTERVAL $day DAY
        AND certificate_archived_at IS NULL AND client_archived_at IS NULL"
    );

    while ($row = mysqli_fetch_assoc($sql)) {
        $certificate_id = intval($row['certificate_id']);
        $certificate_name = escapeSql($row['certificate_name']);
        $certificate_expire = escapeSql($row['certificate_expire']);
        $client_id = intval($row['client_id']);
        $client_name = escapeSql($row['client_name']);
        $short_lived = certificateShortLived($row);

        if ($day == 45 && $short_lived) {
            echo "Not raising a notification for short-lived certificate $certificate_name expiring in 45 days\n";
            continue;
        }

        $renewal = $short_lived ? ' - it renews itself, so check why it has not' : '';
        appNotify("Certificate Expiring", "Certificate $certificate_name for $client_name will expire in $day day(s) on $certificate_expire$renewal", "/agent/certificates.php?client_id=$client_id&open=$certificate_id", $client_id);

    }

}
// Logging
// logAudit("Cron", "Task", "Cron created notifications for certificates expiring");

// Asset Warranties Expiring

$warranty_alert_array = [1,7,45];

foreach ($warranty_alert_array as $day) {

    //Get Asset Warranty Expiring
    $sql = mysqli_query(
        $mysqli,
        "SELECT asset_id, asset_name, asset_warranty_expire, client_id, client_name FROM assets
        LEFT JOIN clients ON asset_client_id = client_id
        WHERE asset_warranty_expire = CURDATE() + INTERVAL $day DAY"
    );

    while ($row = mysqli_fetch_assoc($sql)) {
        $asset_id = intval($row['asset_id']);
        $asset_name = escapeSql($row['asset_name']);
        $asset_warranty_expire = escapeSql($row['asset_warranty_expire']);
        $client_id = intval($row['client_id']);
        $client_name = escapeSql($row['client_name']);

        appNotify("Asset Warranty Expiring", "Asset $asset_name warranty for $client_name will expire in $day Days on $asset_warranty_expire", "/agent/assets.php?client_id=$client_id", $client_id);

    }

}
// Logging
// logAudit("Cron", "Task", "Cron created notifications for asset warranties expiring");

// Services due for review - said once, the day it falls due
$sql = mysqli_query($mysqli, "SELECT service_id, service_name, service_importance, client_id, client_name FROM services
    JOIN clients ON service_client_id = client_id
    WHERE service_review_due = CURDATE() AND service_archived_at IS NULL AND client_archived_at IS NULL");
while ($row = mysqli_fetch_assoc($sql)) {
    $service_id = intval($row['service_id']);
    $service_name = escapeSql($row['service_name']);
    $client_id = intval($row['client_id']);
    $client_name = escapeSql($row['client_name']);
    appNotify("Service Review", "Service $service_name for $client_name (" . escapeSql($row['service_importance']) . " importance) is due for review today", "/agent/" . serviceUrl($service_id, $client_id), $client_id);
}

// Notify of New Tickets
// Get Ticket Pending Assignment
$sql_tickets_pending_assignment = mysqli_query($mysqli,"SELECT ticket_id FROM tickets WHERE ticket_status = 1");

$tickets_pending_assignment = mysqli_num_rows($sql_tickets_pending_assignment);

if ($tickets_pending_assignment > 0) {

    appNotify("Pending Tickets", "There are $tickets_pending_assignment new tickets pending assignment", "/agent/tickets.php?status=New");

    // Logging
    logApp("Cron", "info", "Cron created notifications for new tickets that are pending assignment");
}

// Recurring tickets - each one due today, or missed (a night the cron didn't
// run), raises one ticket and moves on to its next run after today. Paused ones
// raise nothing. recurringTicketRunNow() (functions/ticket.php) is the same code
// the Raise now button runs.
$sql_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_ticket_id, recurring_ticket_next_run, recurring_ticket_frequency, recurring_ticket_start_date
    FROM recurring_tickets WHERE recurring_ticket_next_run <= CURDATE() AND recurring_ticket_paused_at IS NULL");

$recurring_tickets_raised = 0;
while ($row = mysqli_fetch_assoc($sql_recurring_tickets)) {
    if (recurringTicketRunNow($row, 'Cron')) {
        $recurring_tickets_raised++;
    }
}

if ($recurring_tickets_raised) {
    logApp("Cron", "info", "Cron raised $recurring_tickets_raised recurring ticket(s)");
}

// Logging
// logAudit("Cron", "Task", "Cron created sent out recurring tickets");


// TICKET RESOLUTION/CLOSURE PROCESS
// Changes tickets status from 'Resolved' >> 'Closed' after a defined interval

$sql_resolved_tickets_to_close = mysqli_query(
    $mysqli,
    "SELECT ticket_assigned_to, ticket_client_id, ticket_id, ticket_number, ticket_prefix,
        ticket_status, ticket_subject FROM tickets
    WHERE ticket_status = 4
    AND ticket_updated_at < NOW() - INTERVAL $config_ticket_autoclose_hours HOUR"
);

while ($row = mysqli_fetch_assoc($sql_resolved_tickets_to_close)) {

    $ticket_id = $row['ticket_id'];
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_status = escapeSql($row['ticket_status']);
    $ticket_assigned_to = escapeSql($row['ticket_assigned_to']);
    $client_id = intval($row['ticket_client_id']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW(), ticket_closed_by = $ticket_assigned_to WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);

    //Logging
    logTicketHistory($ticket_id, "Automatically closed after $config_ticket_autoclose_hours hours with no activity");

    logAudit("Ticket", "Close", "$ticket_prefix$ticket_number auto closed", $client_id, $ticket_id);

    triggerCustomAction('ticket_close', $ticket_id);

    // The client is told, as when an agent closes it
    if (intval($config_ticket_client_general_notifications) === 1) {
        ticketMailClosed($ticket_id);
    }
}

/*
 * Payment reminders, on the schedule in the invoice settings (functions/billing_email.php,
 * billingReminderRules()): each open invoice whose day it is - before, on or after its due
 * date - gets one, to the client's primary and billing contacts, unless the client is left
 * out of reminders. One a recurring autopay will charge on its due date isn't reminded
 * before then. Agents hear about each one late, on its reminder days, and the late fee
 * still comes every 30 days late. At most one of each a day, however often this runs.
 */
$reminder_rules = billingReminderRules(true);
if ($reminder_rules['enabled']) {

    $earliest = min(0, $reminder_rules['days'] ? min($reminder_rules['days']) : 0);
    $sql_reminders = mysqli_query($mysqli, "SELECT invoice_id, DATEDIFF(CURDATE(), invoice_due) AS days_late, client_reminders,
            invoice_recurring_invoice_id > 0 AND EXISTS (SELECT 1 FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = invoice_recurring_invoice_id) AS autopay
        FROM invoices
        JOIN clients ON client_id = invoice_client_id
        WHERE invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_due IS NOT NULL AND DATEDIFF(CURDATE(), invoice_due) >= $earliest
        ORDER BY invoice_due ASC, invoice_id ASC");

    while ($row = mysqli_fetch_assoc($sql_reminders)) {
        $invoice_id = intval($row['invoice_id']);
        $days = intval($row['days_late']);
        $invoice = billingRow('invoice', $invoice_id, false);
        if (!$invoice) {
            continue;
        }
        $balance = invoiceState($invoice, invoicePaid($invoice_id))['balance'];
        $number = billingNumber('invoice', $invoice);

        // Nothing actually owed (paid in full but status lagging) - skip
        if ($balance <= 0) {
            continue;
        }

        // Late fee - every 30 days late, as a line of its own, at most once a day
        if ($config_invoice_late_fee_enable == 1 && $days > 0 && $days % 30 === 0 && !cronInvoiceHistoryToday($invoice_id, 'Cron applied a late fee')) {
            $late_fee = round($balance * $config_invoice_late_fee_percent / 100, 2);
            if ($late_fee > 0) {
                billingItemSave('invoice', $invoice_id, ['name' => 'Late Fee', 'description' => floatval($config_invoice_late_fee_percent) . '% late fee applied on ' . date('Y-m-d'), 'qty' => 1, 'price' => $late_fee, 'tax_id' => 0]);
                billingLog('invoice', $invoice_id, $invoice['invoice_status'], 'Cron applied a late fee of ' . billingMoney($late_fee, $invoice['invoice_currency_code']));
                appNotify("Invoice Late Charge", escapeSql("Invoice $number for {$invoice['client_name']} with a balance of " . billingMoney($balance, $invoice['invoice_currency_code']) . " was charged a late fee of " . billingMoney($late_fee, $invoice['invoice_currency_code'])), "/agent/invoice.php?invoice_id=$invoice_id", intval($invoice['client_id']));
                $invoice = billingRow('invoice', $invoice_id, false);
                $balance = invoiceState($invoice, invoicePaid($invoice_id))['balance'];
            }
        }

        if (!billingReminderOnDay($days, $reminder_rules)) {
            continue;
        }

        if ($days > 0) {
            appNotify("Invoice Overdue", escapeSql("Invoice $number for {$invoice['client_name']} with a balance of " . billingMoney($balance, $invoice['invoice_currency_code']) . " is overdue by $days days"), "/agent/invoice.php?invoice_id=$invoice_id", intval($invoice['client_id']));
        }

        // Left out, charged by autopay on the day, already reminded today, or no mail
        if (!intval($row['client_reminders']) || ($days <= 0 && intval($row['autopay'])) || empty($config_smtp_provider)
            || cronInvoiceHistoryToday($invoice_id, 'Reminder emailed') || cronInvoiceHistoryToday($invoice_id, 'Reminder not sent') || cronInvoiceHistoryToday($invoice_id, 'Cron Emailed Overdue Invoice')) {
            continue;
        }

        $recipients = billingEmailRecipients($invoice['client_id']);
        if (!$recipients) {
            billingLog('invoice', $invoice_id, $invoice['invoice_status'], 'Reminder not sent - no primary or billing contact with an email address');
            continue;
        }
        [$subject, $email] = billingInvoiceEmail($invoice, ['kind' => 'reminder']);
        $attach = [];
        if ($reminder_rules['attach_pdf'] && ($file = billingEmailPdf(billingPdf('invoice', $invoice)))) {
            $attach[] = $file;
        }
        $sent = billingEmailQueue($recipients, $subject, $email, ['attach' => $attach]);
        billingLog('invoice', $invoice_id, $invoice['invoice_status'], 'Reminder emailed to ' . implode(', ', $sent) . ' - ' . billingReminderWhen($days));
    }

    // Statements each month, on the day in the invoice settings, to every client with a balance
    if ($reminder_rules['statement_monthly'] && intval(date('j')) === $reminder_rules['statement_day'] && !empty($config_smtp_provider)) {
        $sql_statements = mysqli_query($mysqli, "SELECT DISTINCT client_id FROM invoices
            JOIN clients ON client_id = invoice_client_id
            WHERE invoice_status IN ('Sent', 'Viewed', 'Partial') AND client_reminders = 1 AND client_archived_at IS NULL");
        while ($row = mysqli_fetch_assoc($sql_statements)) {
            $client_id = intval($row['client_id']);
            $already = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM logs WHERE log_type = 'Client' AND log_action = 'Statement' AND log_client_id = $client_id AND log_created_at >= CURDATE() LIMIT 1"));
            $statement = billingStatement($client_id);
            if ($already || $statement['total'] <= 0) {
                continue;
            }
            $sent = billingStatementSend($statement, null, ['attach' => $reminder_rules['attach_pdf']]);
            if ($sent) {
                logAudit("Client", "Statement", escapeSql("Cron emailed the monthly statement (" . count($statement['invoices']) . " invoices, " . billingMoney($statement['total'], $statement['currency']) . " due) to " . implode(', ', $sent)), $client_id);
            }
        }
    }
}


// Recurring invoices - each one due today, or missed by up to
// RECURRING_INVOICE_GRACE_DAYS (a night or a few the cron didn't run), makes
// its invoice and moves on a period from the run it billed. One further
// behind than that is left for someone to look at - the page says it is late
// and offers Invoice Now, or moving it on without billing the missed runs.
// recurringInvoiceGenerate() (functions/billing.php) is the same code the
// Invoice Now button runs.
$sql_recurring_invoices = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_next_date FROM recurring_invoices
    WHERE recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL
    AND recurring_invoice_next_date <= CURDATE() AND recurring_invoice_next_date >= CURDATE() - INTERVAL " . RECURRING_INVOICE_GRACE_DAYS . " DAY
    ORDER BY recurring_invoice_next_date ASC, recurring_invoice_id ASC");

$recurring_invoices_made = 0;
while ($row = mysqli_fetch_assoc($sql_recurring_invoices)) {
    if (recurringInvoiceGenerate($row['recurring_invoice_id'], 'Cron', false, $row['recurring_invoice_next_date'])) {
        $recurring_invoices_made++;
    }
}

if ($recurring_invoices_made) {
    logApp("Cron", "info", "Cron made $recurring_invoices_made invoice(s) from recurring invoices");
}

// Start Flag any active recurring "next run" dates that are in the past - too far behind for the run above to bill
$sql_invalid_recurring_invoices = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_number, recurring_invoice_prefix, recurring_invoice_client_id FROM recurring_invoices
    WHERE recurring_invoice_next_date < CURDATE() AND recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL");
while ($row = mysqli_fetch_assoc($sql_invalid_recurring_invoices)) {
    $invoice_prefix = $row['recurring_invoice_prefix'];
    $invoice_number = intval($row['recurring_invoice_number']);
    appNotify("Invoice", escapeSql("Recurring invoice $invoice_prefix$invoice_number is behind - its next date has passed and it won't bill until it is invoiced or moved on"), "/agent/recurring_invoice.php?recurring_invoice_id=" . intval($row['recurring_invoice_id']), intval($row['recurring_invoice_client_id']));
}
// End Flag any active recurring "next run" dates that are in the past

// Start Notify draft invoices needing review
$sql_invoices_pending_send = mysqli_query($mysqli,"SELECT invoice_id FROM invoices WHERE invoice_status = 'Draft'");
$invoices_pending_send = mysqli_num_rows($sql_invoices_pending_send);

if ($invoices_pending_send > 0) {

    appNotify("Draft Invoices", "There are $invoices_pending_send draft invoices pending review", "/agent/invoices.php?&status=Draft");
}
// End Notify draft invoices needing review



// Start Recurring Payments
$sql_recurring_payments = mysqli_query($mysqli, "
    SELECT client_id, client_name, contact_email, contact_name, invoice_amount, invoice_currency_code,
        invoice_date, invoice_due, invoice_id, invoice_number, invoice_prefix, invoice_scope,
        invoice_url_key, recurring_payment_account_id, recurring_payment_currency_code,
        recurring_payment_method, recurring_payment_saved_payment_id FROM recurring_payments
    LEFT JOIN invoices ON invoice_recurring_invoice_id = recurring_payment_recurring_invoice_id
    LEFT JOIN clients ON client_id = invoice_client_id
    LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
    WHERE invoice_due = CURDATE()
      AND (invoice_status = 'Sent' OR invoice_status = 'Viewed')
");

while ($row = mysqli_fetch_assoc($sql_recurring_payments)) {
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = escapeSql($row['invoice_scope']);
    $invoice_date = escapeSql($row['invoice_date']);
    $invoice_due = escapeSql($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_url_key = escapeSql($row['invoice_url_key']);
    $invoice_currency_code = escapeSql($row['invoice_currency_code']);
    $recurring_payment_account_id = intval($row['recurring_payment_account_id']);
    $recurring_payment_method = escapeSql($row['recurring_payment_method']);
    $recurring_payment_currency_code = escapeSql($row['recurring_payment_currency_code']);
    $recurring_payment_saved_payment_id = intval($row['recurring_payment_saved_payment_id']);
    $client_id = intval($row['client_id']);
    $client_name = escapeSql($row['client_name']);
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);

    // A card that already declined today is not retried - the day-matched selection above
    // would otherwise re-attempt the same charge on every extra run of this script
    if (cronInvoiceHistoryToday($invoice_id, 'Stripe autopay failed')) {
        continue;
    }

    // Only attempt autopay if a saved payment method is set
    if ($recurring_payment_saved_payment_id) {
        // Get the saved payment method and provider details
        $saved_payment = mysqli_fetch_assoc(mysqli_query($mysqli, "
            SELECT payment_provider_account, payment_provider_id, payment_provider_name,
                payment_provider_private_key, saved_payment_description, saved_payment_provider_method FROM client_saved_payment_methods
            LEFT JOIN payment_providers ON saved_payment_provider_id = payment_provider_id
            WHERE saved_payment_id = $recurring_payment_saved_payment_id
              AND saved_payment_client_id = $client_id
              AND payment_provider_active = 1
            LIMIT 1
        "));

        if (!$saved_payment) {
            logAudit("Invoice", "Payment", "Failed auto Payment for invoice $invoice_prefix$invoice_number: Saved payment method not found or provider inactive", $client_id, $invoice_id);
            continue;
        }

        $provider_id = intval($saved_payment['payment_provider_id']);
        $provider_name = escapeSql($saved_payment['payment_provider_name']);
        $provider_private_key = $saved_payment['payment_provider_private_key'];
        $account_id = intval($saved_payment['payment_provider_account']);
        $saved_payment_description = escapeSql($saved_payment['saved_payment_description']);
        $stripe_payment_method_id = $saved_payment['saved_payment_provider_method'];

        // NEW: Get the payment_provider_client (Stripe Customer ID) from client_payment_provider
        $cpp_query = mysqli_query($mysqli, "
            SELECT payment_provider_client FROM client_payment_provider
            WHERE client_id = $client_id
              AND payment_provider_id = $provider_id
            LIMIT 1
        ");
        $cpp_row = mysqli_fetch_assoc($cpp_query);
        $stripe_customer_id = $cpp_row ? escapeSql($cpp_row['payment_provider_client']) : '';

        // Stripe
        if ($provider_name === "Stripe") {
            if ($provider_private_key && $stripe_customer_id && $stripe_payment_method_id) {
                require_once __DIR__ . '/../includes/stripe_init.php';
                $stripe = new \Stripe\StripeClient($provider_private_key);

                $balance_to_pay = round($invoice_amount, 2);
                $pi_description = "ITFlow: $client_name payment of $recurring_payment_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

                try {
                    $payment_intent = $stripe->paymentIntents->create([
                        'amount' => intval(round($balance_to_pay * 100)),
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

                    $pi_id = escapeSql($payment_intent->id);
                    $pi_date = date('Y-m-d', $payment_intent->created);
                    $pi_amount_paid = floatval($payment_intent->amount_received / 100);
                    $pi_currency = strtoupper(escapeSql($payment_intent->currency));
                    $pi_livemode = $payment_intent->livemode;

                } catch (Exception $e) {
                    $error = $e->getMessage();
                    error_log("Stripe payment error - encountered exception during payment intent for invoice ID $invoice_id / $invoice_prefix$invoice_number: $error");
                    logApp("Stripe", "error", "Exception during PI for invoice ID $invoice_id: $error");
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe autopay failed due to payment error', history_invoice_id = $invoice_id");
                    logAudit("Invoice", "Payment", "Failed auto Payment amount of invoice $invoice_prefix$invoice_number due to Stripe payment error: $error", $client_id, $invoice_id);
                    continue;
                }

                if ($payment_intent->status == "succeeded" && intval($balance_to_pay) == intval($pi_amount_paid)) {

                    // Update Invoice Status
                    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id");

                    // Add Payment to History
                    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $account_id, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (autopay)', history_invoice_id = $invoice_id");

                    // The client's receipt, and the paid notification address - functions/billing_email.php
                    if (!empty($config_smtp_provider)) {
                        $receipt_to = billingPaymentSend($invoice_id, $pi_amount_paid, ['date' => $pi_date, 'method' => 'Card', 'reference' => "Stripe - $pi_id", 'how' => 'Automatic card'], true);
                        if ($receipt_to) {
                            logAudit("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to " . escapeSql(implode(', ', $receipt_to)), $client_id, $invoice_id);
                        }
                    }

                    // LOGGING
                    $extended_log_desc = !$pi_livemode ? '(DEV MODE)' : '';
                    appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number automatically paid", "/agent/invoice.php?invoice_id=$invoice_id", $client_id);
                    logAudit("Invoice", "Payment", "Auto Stripe payment amount of " . numfmt_format_currency($currency_format, $invoice_amount, $recurring_payment_currency_code) . " added to invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc", $client_id, $invoice_id);
                    triggerCustomAction('invoice_pay', $invoice_id);

                } else {
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe autopay failed: Status {$payment_intent->status}', history_invoice_id = $invoice_id");
                    logAudit("Invoice", "Payment", "Failed auto Payment for invoice $invoice_prefix$invoice_number. Stripe PI status: {$payment_intent->status}", $client_id, $invoice_id);
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
        logAudit("Invoice", "Payment", "Auto Payment amount of $recurring_payment_currency_code $invoice_amount added to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);
    }
}

/*
 * Stripe fee reconciliation
 * A payment can complete before Stripe attaches the balance transaction,
 * in which case the fee expense is skipped at payment time. Find recent
 * Stripe payments with no matching fee expense and record the actual fee
 * now that the balance transaction exists. One card payment over several
 * invoices (the client portal's Pay invoices) is a payment row per invoice
 * with the same reference - it has one fee, so it is taken once.
 */
$stripe_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM payment_providers WHERE payment_provider_name = 'Stripe' LIMIT 1"));

if ($stripe_provider) {

    $provider_private_key = $stripe_provider['payment_provider_private_key'];
    $expense_vendor_id = intval($stripe_provider['payment_provider_expense_vendor']);
    $expense_category_id = intval($stripe_provider['payment_provider_expense_category']);
    $expense_account_id = intval($stripe_provider['payment_provider_account']);

    if ($provider_private_key && $expense_vendor_id > 0 && $expense_category_id > 0) {

        $sql_missing_fee = mysqli_query($mysqli, "
            SELECT payment_reference, MIN(payment_date) AS payment_date, SUM(payment_amount) AS payment_amount,
                GROUP_CONCAT(CONCAT(invoice_prefix, invoice_number) ORDER BY invoice_number SEPARATOR ', ') AS invoice_numbers,
                MIN(invoice_client_id) AS invoice_client_id
            FROM payments
            LEFT JOIN invoices ON payment_invoice_id = invoice_id
            WHERE payment_reference LIKE 'Stripe - pi\_%'
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM expenses WHERE LOCATE(payments.payment_reference, expenses.expense_reference) = 1
            )
            GROUP BY payment_reference
            LIMIT 50
        ");

        if ($sql_missing_fee && mysqli_num_rows($sql_missing_fee) > 0) {

            require_once __DIR__ . '/../includes/stripe_init.php';
            $stripe = new \Stripe\StripeClient($provider_private_key);

            while ($missing = mysqli_fetch_assoc($sql_missing_fee)) {

                $payment_reference = escapeSql($missing['payment_reference']);
                $payment_date = escapeSql($missing['payment_date']);
                $payment_amount = floatval($missing['payment_amount']);
                $invoice_numbers = escapeSql($missing['invoice_numbers']);
                $client_id = intval($missing['invoice_client_id']);

                $pi_id = str_replace('Stripe - ', '', $missing['payment_reference']);

                try {
                    $payment_intent = $stripe->paymentIntents->retrieve($pi_id, ['expand' => ['latest_charge.balance_transaction']]);
                } catch (Exception $e) {
                    logApp("Stripe", "warning", "Fee reconciliation - could not retrieve $pi_id: " . $e->getMessage());
                    continue;
                }

                // Actual fee from the balance transaction (null until Stripe attaches it - retried next run)
                $balance_transaction = $payment_intent->latest_charge->balance_transaction ?? null;
                if ($balance_transaction && !is_string($balance_transaction)) {
                    $gateway_fee = round($balance_transaction->fee / 100, 2);
                    $gateway_fee_currency = escapeSql(strtoupper($balance_transaction->currency));
                    mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$payment_date', expense_amount = $gateway_fee, expense_currency_code = '$gateway_fee_currency', expense_account_id = $expense_account_id, expense_vendor_id = $expense_vendor_id, expense_client_id = $client_id, expense_category_id = $expense_category_id, expense_description = 'Stripe fee for Invoice $invoice_numbers payment of $payment_amount', expense_reference = '$payment_reference'");
                    logApp("Stripe", "info", "Fee reconciliation - recorded Stripe fee of $gateway_fee for $pi_id");
                }
                // Still-missing balance transactions get picked up on the next run
            }
        }
    }
}

/*
 * Recurring expenses - each running one due today, or missed by up to
 * RECURRING_EXPENSE_GRACE_DAYS (nights the cron didn't run), is recorded as
 * an expense dated the day it was due, and moves on a period
 * (recurringExpenseRecord() in functions/expenses.php - the same code Record
 * Now runs). One with an end date stops after its last run. One that falls
 * further behind than that is left for someone to look at, and they are told
 * once, the day it does.
 */
$sql_recurring_expenses = mysqli_query($mysqli, "SELECT recurring_expense_id, recurring_expense_next_date FROM recurring_expenses
    WHERE " . recurringExpenseRunningSql() . "
    AND recurring_expense_next_date <= CURDATE() AND recurring_expense_next_date >= CURDATE() - INTERVAL " . RECURRING_EXPENSE_GRACE_DAYS . " DAY
    ORDER BY recurring_expense_next_date ASC, recurring_expense_id ASC");
while ($row = mysqli_fetch_assoc($sql_recurring_expenses)) {
    $expense_id = recurringExpenseRecord(intval($row['recurring_expense_id']), 0, $row['recurring_expense_next_date']);
    if ($expense_id) {
        $expense = expenseRow($expense_id, false);
        appNotify("Expense Created", escapeSql("Recorded " . expenseLabel($expense) . " from the recurring expense"), "/agent/expenses.php?open=$expense_id", intval($expense['expense_client_id']), $expense_id);
    }
}

$sql_recurring_expenses = mysqli_query($mysqli, "SELECT recurring_expense_id FROM recurring_expenses
    WHERE " . recurringExpenseRunningSql() . " AND recurring_expense_next_date = CURDATE() - INTERVAL " . (RECURRING_EXPENSE_GRACE_DAYS + 1) . " DAY");
while ($row = mysqli_fetch_assoc($sql_recurring_expenses)) {
    $recurring = recurringExpenseRow(intval($row['recurring_expense_id']), false);
    appNotify("Expense", escapeSql("The recurring expense " . recurringExpenseLabel($recurring) . " is " . (RECURRING_EXPENSE_GRACE_DAYS + 1) . " days behind and won't be recorded by itself - record the missed runs or move it on"),
        "/agent/recurring_expenses.php?status=late", intval($recurring['recurring_expense_client_id']));
}

// TELEMETRY

if ($config_telemetry > 0 || $config_telemetry == 2) {

    $current_version = gitCurrentCommit();

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
    // logAudit("Cron", "Task", "Cron sent telemetry results to ITFlow Developers");

}


// Fetch Updates
/*
 * The check itself is cron/update_check.php now - read what it stored rather than running a
 * second git fetch here. Nothing to say until that job has run once, which is the same
 * position this was in when a fetch failed.
 */
if (settingsColumnExists($mysqli, 'config_update_latest_commit')) {

    $update_check_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_update_latest_commit FROM settings WHERE company_id = 1"));

    $latest_version = (string) ($update_check_row['config_update_latest_commit'] ?? '');
    $current_version = gitCurrentCommit();

    if ($latest_version !== '' && $current_version !== '' && $latest_version !== $current_version) {
        // Send Alert to inform Updates Available
        appNotify("Update", "New Updates are Available [$latest_version]", "/admin/update.php");
    }

}



/*
 * ###############################################################################################################
 *  FINISH UP
 * ###############################################################################################################
 */

// Logging
logApp("Cron", "info", "Cron executed successfully");
