<?php require_once("config.php"); ?>
<?php require_once("functions.php"); ?>
<?php

?>

<?php

$sql_companies = mysqli_query($mysqli,"SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id");

while($row = mysqli_fetch_array($sql_companies)){
    $company_id = $row['company_id'];
    $company_name = $row['company_name'];
    $company_phone = formatPhoneNumber($row['company_phone']);
    $company_email = $row['company_email'];
    $company_website = $row['company_website'];
    $company_city = $row['company_city'];
    $company_state = $row['company_state'];
    $company_country = $row['company_country'];
    $company_locale = $row['company_locale'];
    $company_currency = $row['company_currency'];
    $config_enable_cron = $row['config_enable_cron'];
    $config_invoice_overdue_reminders = $row['config_invoice_overdue_reminders'];
    $config_invoice_prefix = $row['config_invoice_prefix'];
    $config_invoice_from_email = $row['config_invoice_from_email'];
    $config_invoice_from_name = $row['config_invoice_from_name'];
    $config_smtp_host = $row['config_smtp_host'];
    $config_smtp_username = $row['config_smtp_username'];
    $config_smtp_password = $row['config_smtp_password'];
    $config_smtp_port = $row['config_smtp_port'];
    $config_smtp_encryption = $row['config_smtp_encryption'];
    $config_mail_from_email = $row['config_mail_from_email'];
    $config_mail_from_name = $row['config_mail_from_name'];
    $config_recurring_auto_send_invoice = $row['config_recurring_auto_send_invoice'];

    // Tickets
    $config_ticket_prefix = $row['config_ticket_prefix'];
    $config_ticket_next_number = $row['config_ticket_next_number'];
    $config_ticket_from_name = $row['config_ticket_from_name'];
    $config_ticket_from_email = $row['config_ticket_from_email'];

    //Get Config for Telemetry
    $config_theme = $row['config_theme'];
    $config_ticket_email_parse = $row['config_ticket_email_parse'];
    $config_module_enable_itdoc = $row['config_module_enable_itdoc'];
    $config_module_enable_ticketing = $row['config_module_enable_ticketing'];
    $config_module_enable_accounting = $row['config_module_enable_accounting'];
    $config_telemetry = $row['config_telemetry'];

    // Set Currency Format
    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    if($config_enable_cron == 1){

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Cron', log_action = 'Started', log_description = 'Cron started for $company_name', company_id = $company_id");

        // GET NOTIFICATIONS

        // DOMAINS EXPIRING

        $domainAlertArray = [1,7,14,30,90,120];

        foreach($domainAlertArray as $day){

            //Get Domains Expiring
            $sql = mysqli_query($mysqli,"SELECT * FROM domains
        LEFT JOIN clients ON domain_client_id = client_id 
        WHERE domain_expire = CURDATE() + INTERVAL $day DAY
        AND domains.company_id = $company_id"
            );

            while($row = mysqli_fetch_array($sql)){
                $domain_id = $row['domain_id'];
                $domain_name = mysqli_real_escape_string($mysqli,$row['domain_name']);
                $domain_expire = $row['domain_expire'];
                $client_id = $row['client_id'];
                $client_name = mysqli_real_escape_string($mysqli,$row['client_name']);

                mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Domain', notification = 'Domain $domain_name for $client_name will expire in $day Days on $domain_expire', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");

            }

        }

        // CERTIFICATES EXPIRING

        $certificateAlertArray = [1,7,14,30,90,120];

        foreach($certificateAlertArray as $day){

            //Get Certs Expiring
            $sql = mysqli_query($mysqli,"SELECT * FROM certificates
        LEFT JOIN clients ON certificate_client_id = client_id 
        WHERE certificate_expire = CURDATE() + INTERVAL $day DAY
        AND certificates.company_id = $company_id"
            );

            while($row = mysqli_fetch_array($sql)){
                $certificate_id = $row['certificate_id'];
                $certificate_name = mysqli_real_escape_string($mysqli,$row['certificate_name']);
                $certificate_domain = $row['certificate_domain'];
                $certificate_expire = $row['certificate_expire'];
                $client_id = $row['client_id'];
                $client_name = mysqli_real_escape_string($mysqli,$row['client_name']);

                mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Certificate', notification = 'Certificate $certificate_name for $client_name will expire in $day Days on $certificate_expire', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");

            }

        }

        // Asset Warranties Expiring

        $warranty_alert_array = [1,7,14,30,90,120];

        foreach($warranty_alert_array as $day){

            //Get Asset Warranty Expiring
            $sql = mysqli_query($mysqli,"SELECT * FROM assets 
        LEFT JOIN clients ON asset_client_id = client_id
        WHERE asset_warranty_expire = CURDATE() + INTERVAL $day DAY
        AND assets.company_id = $company_id"
            );

            while($row = mysqli_fetch_array($sql)){
                $asset_id = $row['asset_id'];
                $asset_name = mysqli_real_escape_string($mysqli,$row['asset_name']);
                $asset_warranty_expire = $row['asset_warranty_expire'];
                $client_id = $row['client_id'];
                $client_name = mysqli_real_escape_string($mysqli,$row['client_name']);

                mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Asset', notification = 'Asset $asset_name warranty for $client_name will expire in $day Days on $asset_warranty_expire', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");

            }

        }

        // Scheduled tickets

        // Get date for search
        $today = new DateTime();
        $today_text = $today->format('Y-m-d');

        // Get scheduled tickets for today
        $sql_scheduled_tickets = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets WHERE scheduled_ticket_next_run = '$today_text'");

        if(mysqli_num_rows($sql_scheduled_tickets) > 0){
            while($row = mysqli_fetch_array($sql_scheduled_tickets)){
                $schedule_id = $row['scheduled_ticket_id'];
                $subject = mysqli_real_escape_string($mysqli,$row['scheduled_ticket_subject']);
                $details = mysqli_real_escape_string($mysqli,$row['scheduled_ticket_details']);
                $priority = $row['scheduled_ticket_priority'];
                $frequency = strtolower($row['scheduled_ticket_frequency']);
                $created_id = $row['scheduled_ticket_created_by'];
                $client_id = $row['scheduled_ticket_client_id'];
                $contact_id = $row['scheduled_ticket_contact_id'];
                $asset_id = $row['scheduled_ticket_asset_id'];
                $company_id = $row['company_id'];

                //Get the next Ticket Number and add 1 for the new ticket number
                $ticket_number = $config_ticket_next_number;
                $new_config_ticket_next_number = $config_ticket_next_number + 1;
                mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = '$company_id'");

                // Raise the ticket
                mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 'Open', ticket_created_at = NOW(), ticket_created_by = $created_id, ticket_contact_id = $contact_id, ticket_client_id = $client_id, ticket_asset_id = $asset_id, company_id = $company_id");
                $id = mysqli_insert_id($mysqli);

                // Logging
                mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'System created scheduled $frequency ticket - $subject', log_created_at = NOW(), log_client_id = $client_id, company_id = $company_id, log_user_id = $created_id");

                // E-mail client
                if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

                    // Get contact/ticket/company details
                    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, company_phone FROM tickets 
                      LEFT JOIN clients ON ticket_client_id = client_id 
                      LEFT JOIN contacts ON ticket_contact_id = contact_id
                      LEFT JOIN companies ON tickets.company_id = companies.company_id
                      WHERE ticket_id = $id AND tickets.company_id = $company_id");
                    $row = mysqli_fetch_array($sql);

                    $contact_name = $row['contact_name'];
                    $contact_email = $row['contact_email'];
                    $ticket_prefix = $row['ticket_prefix'];
                    $ticket_number = $row['ticket_number'];
                    $ticket_subject = $row['ticket_subject'];
                    $company_phone = formatPhoneNumber($row['company_phone']);

                    // Verify contact email is valid
                    if(filter_var($contact_email, FILTER_VALIDATE_EMAIL)){

                        $subject = "Ticket created - [$ticket_prefix$ticket_number] - $ticket_subject (scheduled)";
                        $body    = "<i style='color: #808080'>#--itflow--#</i><br><br>Hello, $contact_name<br><br>A ticket regarding \"$ticket_subject\" has been automatically created for you.<br><br>--------------------------------<br>$details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: https://$config_base_url/portal/ticket.php?id=$id<br><br>~<br>$company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone";

                        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                            $config_ticket_from_email, $config_ticket_from_name,
                            $contact_email, $contact_name,
                            $subject, $body);

                        if ($mail !== true) {
                            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', notification_timestamp = NOW(), company_id = $company_id");
                            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail',  company_id = $company_id");
                        }

                    }
                }

                // Set the next run date
                if($frequency == "weekly"){
                    // Note: We seemingly have to initialize a new datetime for each loop to avoid stacking the dates
                    $now = new DateTime();
                    $next_run = date_add($now, date_interval_create_from_date_string('1 week'));
                }
                elseif($frequency == "monthly"){
                    $now = new DateTime();
                    $next_run = date_add($now, date_interval_create_from_date_string('1 month'));
                }
                elseif($frequency == "quarterly"){
                    $now = new DateTime();
                    $next_run = date_add($now, date_interval_create_from_date_string('3 months'));
                }
                elseif($frequency == "biannually"){
                    $now = new DateTime();
                    $next_run = date_add($now, date_interval_create_from_date_string('6 months'));
                }
                elseif($frequency == "annually"){
                    $now = new DateTime();
                    $next_run = date_add($now, date_interval_create_from_date_string('12 months'));
                }

                // Update the run date
                $next_run = $next_run->format('Y-m-d');
                $a = mysqli_query($mysqli, "UPDATE scheduled_tickets SET scheduled_ticket_next_run = '$next_run' WHERE scheduled_ticket_id = '$schedule_id'");

            }
        }

        // Clean-up ticket views table used for collision detection
        mysqli_query($mysqli, "TRUNCATE TABLE ticket_views");

        // Clean-up shared items that have been used
        mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_views = item_view_limit");

        // Clean-up shared items that have expired
        mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_expire_at < NOW()");

        // Invalidate any password reset links
        mysqli_query($mysqli, "UPDATE contacts SET contact_password_reset_token = NULL WHERE contact_archived_at IS NULL");

        // PAST DUE INVOICE Notifications
        //$invoiceAlertArray = [$config_invoice_overdue_reminders];
        $invoiceAlertArray = [30,60,90,120,150,180,210,240,270,300,330,360,390,420,450,480,510,540,570,590,620];

        foreach($invoiceAlertArray as $day){

            $sql = mysqli_query($mysqli,"SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON contact_id = primary_contact
        WHERE invoice_status NOT LIKE 'Draft'
        AND invoice_status NOT LIKE 'Paid'
        AND invoice_status NOT LIKE 'Cancelled'
        AND DATE_ADD(invoice_due, INTERVAL $day DAY) = CURDATE()
        AND invoices.company_id = $company_id
        ORDER BY invoice_number DESC"
            );

            while($row = mysqli_fetch_array($sql)){
                $invoice_id = $row['invoice_id'];
                $invoice_prefix = $row['invoice_prefix'];
                $invoice_number = $row['invoice_number'];
                $invoice_status = $row['invoice_status'];
                $invoice_date = $row['invoice_date'];
                $invoice_due = $row['invoice_due'];
                $invoice_url_key = $row['invoice_url_key'];
                $invoice_amount = $row['invoice_amount'];
                $invoice_currency_code = $row['invoice_currency_code'];
                $client_id = $row['client_id'];
                $client_name = mysqli_real_escape_string($mysqli,$row['client_name']);
                $contact_name = $row['contact_name'];
                $contact_email = $row['contact_email'];

                mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Invoice Overdue', notification = 'Invoice $invoice_prefix$invoice_number for $client_name in the amount of $invoice_amount is overdue by $day days', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");

                $subject = "Overdue Invoice $invoice_prefix$invoice_number";
                $body    = "Hello $contact_name,<br><br>According to our records, we have not received payment for invoice $invoice_prefix$invoice_number. Please submit your payment as soon as possible. If you have any questions please contact us at $company_phone.
            <br><br>
            Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice click <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                    $config_invoice_from_email, $config_invoice_from_name,
                    $contact_email, $contact_name,
                    $subject, $body);

                if ($mail === true) {
                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Cron Emailed Overdue Invoice', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $company_id");
                } else {
                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Cron Failed to send Overdue Invoice', history_created_at = NOW(), history_invoice_id = $invoice_id, company_id = $company_id");

                    mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', notification_timestamp = NOW(), company_id = $company_id");
                    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', company_id = $company_id");
                }

            }

        }

        //Send Recurring Invoices that match todays date and are active

        //Loop through all recurring that match today's date and is active
        $sql_recurring = mysqli_query($mysqli,"SELECT * FROM recurring LEFT JOIN clients ON client_id = recurring_client_id WHERE recurring_next_date = CURDATE() AND recurring_status = 1 AND recurring.company_id = $company_id");

        while($row = mysqli_fetch_array($sql_recurring)){
            $recurring_id = $row['recurring_id'];
            $recurring_scope = $row['recurring_scope'];
            $recurring_frequency = $row['recurring_frequency'];
            $recurring_status = $row['recurring_status'];
            $recurring_last_sent = $row['recurring_last_sent'];
            $recurring_next_date = $row['recurring_next_date'];
            $recurring_amount = $row['recurring_amount'];
            $recurring_currency_code = $row['recurring_currency_code'];
            $recurring_note = mysqli_real_escape_string($mysqli,$row['recurring_note']); //Escape SQL
            $category_id = $row['recurring_category_id'];
            $client_id = $row['recurring_client_id'];
            $client_name = mysqli_real_escape_string($mysqli,$row['client_name']); //Escape SQL just in case a name is like Safran's etc
            $client_net_terms = $row['client_net_terms'];


            //Get the last Invoice Number and add 1 for the new invoice number
            $sql_invoice_number = mysqli_query($mysqli,"SELECT * FROM settings WHERE company_id = $company_id");
            $row = mysqli_fetch_array($sql_invoice_number);
            $config_invoice_next_number = $row['config_invoice_next_number'];

            $new_invoice_number = $config_invoice_next_number;
            $new_config_invoice_next_number = $config_invoice_next_number + 1;
            mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = $company_id");

            //Generate a unique URL key for clients to access
            $url_key = randomString(156);

            mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $new_invoice_number, invoice_scope = '$recurring_scope', invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_amount = '$recurring_amount', invoice_currency_code = '$recurring_currency_code', invoice_note = '$recurring_note', invoice_category_id = $category_id, invoice_status = 'Sent', invoice_url_key = '$url_key', invoice_created_at = NOW(), invoice_client_id = $client_id, company_id = $company_id");

            $new_invoice_id = mysqli_insert_id($mysqli);

            //Copy Items from original recurring invoice to new invoice
            $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id ORDER BY item_id ASC");

            while($row = mysqli_fetch_array($sql_invoice_items)){
                $item_id = $row['item_id'];
                $item_name = mysqli_real_escape_string($mysqli,$row['item_name']); //SQL Escape incase of ,
                $item_description = mysqli_real_escape_string($mysqli,$row['item_description']); //SQL Escape incase of ,
                $item_quantity = $row['item_quantity'];
                $item_price = $row['item_price'];
                $item_subtotal = $row['item_subtotal'];
                $item_tax = $row['item_tax'];
                $item_total = $row['item_total'];
                $tax_id = $row['item_tax_id'];

                //Insert Items into New Invoice
                mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = '$item_quantity', item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', item_created_at = NOW(), item_tax_id = $tax_id, item_invoice_id = $new_invoice_id, company_id = $company_id");

            }

            mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice Generated from Recurring!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $company_id");

            mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Recurring Sent', notification = 'Recurring Invoice $config_invoice_prefix$new_invoice_number for $client_name Sent', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");

            //Update recurring dates

            mysqli_query($mysqli,"UPDATE recurring SET recurring_last_sent = CURDATE(), recurring_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_frequency), recurring_updated_at = NOW() WHERE recurring_id = $recurring_id");

            if($config_recurring_auto_send_invoice == 1){
                $sql = mysqli_query($mysqli,"SELECT * FROM invoices
          LEFT JOIN clients ON invoice_client_id = client_id
          LEFT JOIN contacts ON contact_id = primary_contact
          WHERE invoice_id = $new_invoice_id
          AND invoices.company_id = $company_id"
                );

                $row = mysqli_fetch_array($sql);
                $invoice_prefix = $row['invoice_prefix'];
                $invoice_number = $row['invoice_number'];
                $invoice_date = $row['invoice_date'];
                $invoice_due = $row['invoice_due'];
                $invoice_amount = $row['invoice_amount'];
                $invoice_url_key = $row['invoice_url_key'];
                $client_id = $row['client_id'];
                $client_name = $row['client_name'];
                $contact_name = $row['contact_name'];
                $contact_email = $row['contact_email'];


                $subject = "Invoice $invoice_prefix$invoice_number";
                $body    = "Hello $contact_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $recurring_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice click <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$new_invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
                    $config_invoice_from_email, $config_invoice_from_name,
                    $contact_email, $contact_name,
                    $subject, $body);

                if ($mail === true) {
                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Cron Emailed Invoice!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $company_id");
                    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', invoice_updated_at = NOW(), invoice_client_id = $client_id WHERE invoice_id = $new_invoice_id");

                } else {
                    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Cron Failed to send Invoice!', history_created_at = NOW(), history_invoice_id = $new_invoice_id, company_id = $company_id");

                    mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', notification_timestamp = NOW(), company_id = $company_id");
                    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', company_id = $company_id");
                }

            } //End if Autosend is on
        } //End Recurring Invoices Loop
    
        if($config_telemetry = 1){

            $current_version = exec("git rev-parse HEAD");

            // Client Count
            $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS num FROM clients"));
            $client_count = $row['num'];

            // Ticket Count
            $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM tickets"));
            $ticket_count = $row['num'];

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
                    'city' => "$company_city",
                    'state' => "$company_state",
                    'country' => "$company_country",
                    'currency' => "$company_currency",
                    'client_count' => $client_count,
                    'ticket_count' => $ticket_count,
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
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Cron', log_action = 'Telemetry', log_description = 'Cron sent telemetry results to ITFlow Developers', company_id = $company_id");

        }

        //Send Alert to inform Cron was run
        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Cron', notification = 'Cron.php successfully executed', notification_timestamp = NOW(), company_id = $company_id");
        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Cron', log_action = 'Ended', log_description = 'Cron executed successfully for $company_name', company_id = $company_id");
    } //End Cron Check

} //End Company Loop through

?>
