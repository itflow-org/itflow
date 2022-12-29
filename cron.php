<?php include("config.php"); ?>
<?php include("functions.php"); ?>
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
  $company_locale = $row['company_locale'];
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
        $domain_name = $row['domain_name'];
        $domain_expire = $row['domain_expire'];
        $client_id = $row['client_id'];
        $client_name = $row['client_name'];

        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Domain', notification = 'Domain $domain_name for $client_name will expire in $day Days on $domain_expire', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");

      }

    }

    // CERTIFICATES EXPIRING

    $certificateAlertArray = [1,7,14,30,90,120];

    foreach($certificateAlertArray as $day){

      //Get Domains Expiring
      $sql = mysqli_query($mysqli,"SELECT * FROM certificates
        LEFT JOIN clients ON certificate_client_id = client_id 
        WHERE certificate_expire = CURDATE() + INTERVAL $day DAY
        AND certificates.company_id = $company_id"
      );

      while($row = mysqli_fetch_array($sql)){
        $certificate_id = $row['certificate_id'];
        $certificate_name = $row['certificate_name'];
        $certificate_domain = $row['certificate_domain'];
        $certificate_expire = $row['certificate_expire'];
        $client_id = $row['client_id'];
        $client_name = $row['client_name'];

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
        $asset_name = $row['asset_name'];
        $asset_warranty_expire = $row['asset_warranty_expire'];
        $client_id = $row['client_id'];
        $client_name = $row['client_name'];

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
        $subject = $row['scheduled_ticket_subject'];
        $details = $row['scheduled_ticket_details'];
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

        // Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'System created scheduled $frequency ticket - $subject', log_created_at = NOW(), log_client_id = $client_id, company_id = $company_id, log_user_id = $created_id");

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
        $client_name = $row['client_name'];
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
      $url_key = bin2hex(random_bytes(78));

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
    //Send Alert to inform Cron was run
    mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Cron', notification = 'Cron.php successfully executed', notification_timestamp = NOW(), company_id = $company_id");
    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Cron', log_action = 'Ended', log_description = 'Cron executed successfully for $company_name', company_id = $company_id");
  } //End Cron Check

} //End Company Loop through

?>